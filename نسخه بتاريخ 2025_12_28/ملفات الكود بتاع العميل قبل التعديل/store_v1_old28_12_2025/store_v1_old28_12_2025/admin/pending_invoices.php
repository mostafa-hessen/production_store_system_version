    <?php
    // admin/pending_invoices.php
    // الفواتير غير المستلمة - مع مودال تفاصيل (عرض -> تعديل: تسليم / حذف) + بحث برقم العميل
    // تم تعديل: معالجة AJAX قبل إخراج HTML لتفادي "خطأ في الاتصال..." ودمج مودال محسّن

    $page_title = "الفواتير غير المستلمة";
    $class_dashboard = "active";

    require_once dirname(__DIR__) . '/config.php';
    require_once BASE_DIR . 'partials/session_admin.php';

    // دوال مساعدة
    function e($s)
    {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    function json_out($arr)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    }
    function normalize_decimal($val, $scale = 4)
    {
        $s = (string)$val;
        // remove commas, trim
        $s = str_replace(',', '.', trim($s));
        // ensure numeric-like
        if (!is_numeric($s)) return '0';
        // use number_format to standardize (but that returns string with comma in some locales).
        // For safety use bcmul to round: multiply then divide
        if (!extension_loaded('bcmath')) {
            // fallback (may lose precision)
            return number_format((float)$s, $scale, '.', '');
        }
        // round to scale: round(x, scale) via bc
        $factor = '1' . str_repeat('0', $scale);
        $rounded = bcdiv(bcmul($s, $factor, $scale + 2), $factor, $scale);
        // remove trailing zeros? keep as is
        return $rounded;
    }
    // CSRF token
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrf_token = $_SESSION['csrf_token'];

    // ------------------ AJAX endpoint (يجب أن يكون قبل أي إخراج HTML) ------------------
    // AJAX endpoint لجلب قائمة الفواتير (للبحث المباشر)
    if (isset($_GET['action']) && $_GET['action'] === 'fetch_invoices_list' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        $current_page_link_temp = htmlspecialchars($_SERVER['PHP_SELF']);
        // استخدام نفس منطق البحث الموجود
        $invoice_q = isset($_GET['invoice_q']) ? trim((string)$_GET['invoice_q']) : '';
        $mobile_q  = isset($_GET['mobile_q']) ? trim((string)$_GET['mobile_q']) : '';
        $selected_group = isset($_GET['filter_group_val']) ? trim((string)$_GET['filter_group_val']) : '';
        $customer_filter_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
        $notes_q = isset($_GET['notes_q']) ? trim((string)$_GET['notes_q']) : '';
        $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
        $date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

        $sql_select = "SELECT i.id, i.invoice_group, i.created_at,
                        COALESCE(c.name,'(عميل نقدي)') AS customer_name,
                        COALESCE(c.mobile,'-') AS customer_mobile,
                        u.username AS creator_name,
                        COALESCE(i.notes,'') AS notes,
                        COALESCE((SELECT SUM(item.total_price) FROM invoice_out_items item WHERE item.invoice_out_id = i.id),0) AS invoice_total,
                        i.total_before_discount,
                        i.discount_type,
                        i.discount_value,
                        i.discount_amount,
                        i.total_after_discount
                FROM invoices_out i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN users u ON i.created_by = u.id
                WHERE i.delivered = 'no' ";

        $params = [];
        $types = "";

        if ($customer_filter_id > 0) {
            $sql_select .= " AND i.customer_id = ? ";
            $params[] = $customer_filter_id;
            $types .= "i";
        }

        if ($selected_group !== '') {
            $sql_select .= " AND i.invoice_group = ? ";
            $params[] = $selected_group;
            $types .= "s";
        }

        if ($invoice_q !== '') {
            $digits = preg_replace('/\D/', '', $invoice_q);
            if ($digits !== '') {
                $sql_select .= " AND i.id = ? ";
                $params[] = intval($digits);
                $types .= "i";
            }
        } elseif ($mobile_q !== '') {
            $sql_select .= " AND COALESCE(c.mobile,'') LIKE ? ";
            $params[] = '%' . $mobile_q . '%';
            $types .= "s";
        }

        if ($notes_q !== '') {
            $sql_select .= " AND COALESCE(i.notes,'') LIKE ? ";
            $params[] = '%' . $notes_q . '%';
            $types .= "s";
        }

        if ($date_from !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $date_from);
            if ($d !== false) {
                $start = $d->format('Y-m-d') . ' 00:00:00';
                $sql_select .= " AND i.created_at >= ? ";
                $params[] = $start;
                $types .= 's';
            }
        }
        if ($date_to !== '') {
            $d2 = DateTime::createFromFormat('Y-m-d', $date_to);
            if ($d2 !== false) {
                $d2->modify('+1 day');
                $end = $d2->format('Y-m-d') . ' 00:00:00';
                $sql_select .= " AND i.created_at < ? ";
                $params[] = $end;
                $types .= 's';
            }
        }

        $sql_select .= " ORDER BY i.created_at DESC, i.id DESC LIMIT 2000";

        $invoices = [];
        $count = 0;
        $displayed_total_after_discount = 0;
        $displayed_total_before_discount = 0;

        if ($stmt = $conn->prepare($sql_select)) {
            if (!empty($params)) {
                $bind_names[] = $types;
                for ($i = 0; $i < count($params); $i++) $bind_names[] = &$params[$i];
                call_user_func_array([$stmt, 'bind_param'], $bind_names);
                unset($bind_names);
            }
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $count = $result->num_rows;
                while ($row = $result->fetch_assoc()) {
                    $total_before = floatval($row["total_before_discount"] ?? 0);
                    $total_after = floatval($row["total_after_discount"] ?? 0);
                    $invoice_total = floatval($row["invoice_total"] ?? 0);

                    if ($total_before <= 0) $total_before = $invoice_total;
                    if ($total_after <= 0) $total_after = $total_before;

                    $displayed_total_before_discount += $total_before;
                    $displayed_total_after_discount += $total_after;

                    $invoices[] = $row;
                }
            }
            $stmt->close();
        }

        // بناء HTML للقائمة
        ob_start();
        if (count($invoices) > 0) {
            foreach ($invoices as $row) {
                $current_invoice_total_for_row = floatval($row["invoice_total"] ?? 0);
                $total_before_discount = floatval($row["total_before_discount"] ?? 0);
                $total_after_discount = floatval($row["total_after_discount"] ?? 0);
                $discount_amount = floatval($row["discount_amount"] ?? 0);
                $discount_type = $row["discount_type"] ?? 'percent';
                $discount_value = floatval($row["discount_value"] ?? 0);

                if ($total_before_discount <= 0) $total_before_discount = $current_invoice_total_for_row;
                if ($total_after_discount <= 0) $total_after_discount = $total_before_discount;

                $has_discount = ($discount_amount > 0 && abs($total_after_discount - $total_before_discount) > 0.01);
                $final_amount = $has_discount ? $total_after_discount : $total_before_discount;

                $noteText = trim((string)($row['notes'] ?? ''));
                $noteDisplay = $noteText;
                if (mb_strlen($noteDisplay) > 30) {
                    $noteDisplay = mb_substr($noteDisplay, 0, 30) . '...';
                }
                $created_date = date('m/d/Y', strtotime($row["created_at"]));
    ?>
                <article class="invoice">
                    <div class="invoice-left">
                        <!-- أضف هذا السطر -->
                        <input type="checkbox" class="invoice-checkbox" data-invoice-id="<?php echo e($row["id"]); ?>">
                        <div class="badge">#<?php echo e($row["id"]); ?></div>
                        <div class="meta">
                            <div class="name"><?php echo e($row["customer_name"]); ?></div>
                            <?php if ($noteDisplay): ?>
                                <div class="notes" title="<?php echo e($noteText); ?>"><?php echo e($noteDisplay); ?></div>
                            <?php endif; ?>
                            <div class="extra">
                                <div class="phone">📞 <?php echo e($row["customer_mobile"]); ?></div>
                                <div class="creator">👤 <?php echo e($row["creator_name"] ?? 'غير معروف'); ?></div>
                                <div>📅 <?php echo e($created_date); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="invoice-right">
                        <?php if ($has_discount): ?>
                            <div class="amount-with-discount">
                                <div class="amount-original"><?php echo number_format($total_before_discount, 2); ?> ج.م</div>
                                <div class="amount-final"><?php echo number_format($total_after_discount, 2); ?> ج.م</div>
                                <div class="discount-badge">
                                    <?php
                                    if ($discount_type === 'percent') {
                                        echo number_format($discount_value, 2) . '% خصم';
                                    } else {
                                        echo number_format($discount_amount, 2) . ' ج.م خصم';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="amount"><?php echo number_format($final_amount, 2); ?> ج.م</div>
                        <?php endif; ?>
                        <div class="status pending">معلقة</div>
                        <div class="actions">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                                <button class="show btn-open-modal" data-invoice-id="<?php echo e($row["id"]); ?>">عرض</button>
                                <form action="<?php echo $current_page_link_temp; ?>" method="post" style="display:inline;">
                                    <input type="hidden" name="invoice_id_to_deliver" value="<?php echo e($row["id"]); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <button type="submit" name="mark_delivered" class="deliver">تسليم</button>
                                </form>
                                <button class="cancel btn-cancel-invoice" data-invoice-id="<?php echo e($row['id']); ?>">إلغاء</button>
                                <button class="edit btn-edit-items" data-id="<?php echo e($row['id']); ?>">تعديل</button>
                                <button class="edit btn-edit-items" data-id="<?php echo e($row['id']); ?>">تعديل</button>
                                <button class="btn btn-sm btn-outline-secondary btn-return-invoice btn"
                                    data-invoice-id="<?php echo e($row['id']); ?>"
                                    title="ارجاع">
                                    ارجاع
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
    <?php
            }
        } else {
            echo '<div style="text-align:center;padding:40px;color:var(--muted)">لا توجد فواتير غير مستلمة حالياً.</div>';
        }
        $html = ob_get_clean();

        json_out([
            'success' => true,
            'html' => $html,
            'count' => $count,
            'total_after_discount' => $displayed_total_after_discount,
            'total_before_discount' => $displayed_total_before_discount
        ]);
    }

    if (isset($_GET['action']) && $_GET['action'] === 'fetch_invoice_details' && isset($_GET['id'])) {
        $inv_id = intval($_GET['id']);
        if ($inv_id <= 0) json_out(['success' => false, 'message' => 'invoice id invalid']);

        // جلب رأس الفاتورة
        $st = $conn->prepare("SELECT io.*, COALESCE(c.name,'(عميل نقدي)') AS customer_name, c.mobile AS customer_mobile, c.city AS customer_city, u.username AS creator_name, u2.username AS updater_name
                            FROM invoices_out io
                            LEFT JOIN customers c ON io.customer_id = c.id
                            LEFT JOIN users u ON io.created_by = u.id
                            LEFT JOIN users u2 ON io.updated_by = u2.id
                            WHERE io.id = ? LIMIT 1");
        if (!$st) json_out(['success' => false, 'message' => 'prepare failed: ' . $conn->error]);
        $st->bind_param("i", $inv_id);
        $st->execute();
        $h = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$h) json_out(['success' => false, 'message' => 'الفاتورة غير موجودة']);

        // جلب البنود
        $it = [];
        $s2 = $conn->prepare("SELECT i.*, p.name AS product_name, p.product_code FROM invoice_out_items i LEFT JOIN products p ON i.product_id = p.id WHERE i.invoice_out_id = ?");
        if ($s2) {
            $s2->bind_param("i", $inv_id);
            $s2->execute();
            $res2 = $s2->get_result();
            while ($r = $res2->fetch_assoc()) $it[] = $r;
            $s2->close();
        }

        json_out(['success' => true, 'invoice' => $h, 'items' => $it]);
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_invoice') {
        try {
            // basic session auth checks (tune to your app)
            if (empty($_SESSION['id']) && empty($_SESSION['user_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'غير مسموح.']);
                exit;
            }
            if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'لا تملك صلاحية إجراء هذا الإجراء.']);
                exit;
            }

            if (!isset($conn) || !($conn instanceof mysqli)) {
                throw new Exception("خطأ داخلي: اتصال قاعدة البيانات غير مُعد.");
            }

            // enable mysqli exceptions
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            $invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
            $csrf = $_POST['csrf_token'] ?? '';
            $reason = trim($_POST['reason'] ?? '');

            // CSRF
            if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$csrf)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'رمز الحماية غير صالح.']);
                exit;
            }

            if ($invoiceId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'معرف الفاتورة غير صحيح.']);
                exit;
            }

            if ($reason === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'حقل سبب الإلغاء مطلوب. من فضلك أدخل سببًا.']);
                exit;
            }

            $current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;

            // Start transaction
            $conn->begin_transaction();

            // 1) lock invoice
            $stmt = $conn->prepare("SELECT id, delivered FROM invoices_out WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $invoiceId);
            $stmt->execute();
            $res = $stmt->get_result();
            $inv = $res->fetch_assoc();
            $stmt->close();
            if (!$inv) throw new Exception("الفاتورة غير موجودة.");

            if ($inv['delivered'] !== 'no') {
                throw new Exception("لا يمكن إلغاء هذه الفاتورة. حالتها: " . $inv['delivered']);
            }

            // 2) ensure no previous cancellation
            $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM invoice_cancellations WHERE invoice_out_id = ?");
            $chk->bind_param("i", $invoiceId);
            $chk->execute();
            $rc = $chk->get_result()->fetch_assoc();
            $chk->close();
            if ((int)($rc['cnt'] ?? 0) > 0) throw new Exception("تم تسجيل إلغاء سابق لهذه الفاتورة.");

            // 3) detect FK name in sale_item_allocations
            $cols = [];
            $colQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sale_item_allocations'";
            $colRes = $conn->query($colQuery);
            while ($r = $colRes->fetch_assoc()) $cols[] = $r['COLUMN_NAME'];

            $possible_fk = ['sale_item_id', 'invoice_item_id', 'invoice_out_item_id', 'invoice_item', 'invoice_out_item'];
            $found_fk = null;
            foreach ($possible_fk as $c) if (in_array($c, $cols, true)) {
                $found_fk = $c;
                break;
            }

            if ($found_fk) {
                $allocSql = "
                    SELECT sa.id AS alloc_id, sa.batch_id, sa.qty, sa.unit_cost
                    FROM sale_item_allocations sa
                    JOIN invoice_out_items ioi ON sa.`{$found_fk}` = ioi.id
                    WHERE ioi.invoice_out_id = ?
                    FOR UPDATE
                ";
            } else {
                // fallback (less safe)
                $allocSql = "
                    SELECT sa.id AS alloc_id, sa.batch_id, sa.qty, sa.unit_cost, ioi.id AS invoice_item_id
                    FROM sale_item_allocations sa
                    JOIN invoice_out_items ioi ON sa.product_id = ioi.product_id
                    WHERE ioi.invoice_out_id = ?
                    FOR UPDATE
                ";
            }

            $allocStmt = $conn->prepare($allocSql);
            $allocStmt->bind_param("i", $invoiceId);
            $allocStmt->execute();
            $allocRes = $allocStmt->get_result();
            $allocs = [];
            while ($r = $allocRes->fetch_assoc()) $allocs[] = $r;
            $allocStmt->close();

            // prepare batch statements
            $getBatchStmt = $conn->prepare("SELECT id, remaining, original_qty, qty AS batch_qty, status FROM batches WHERE id = ? FOR UPDATE");
            $updateIncrementAndStatusStmt = $conn->prepare("
                UPDATE batches
                SET remaining = remaining + ?,
                    status = CASE WHEN (remaining + ?) > 0 THEN 'active' ELSE 'consumed' END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $getBatchAfterStmt = $conn->prepare("SELECT id, remaining, original_qty, qty AS batch_qty, status FROM batches WHERE id = ?");

            $totalRestored = 0.0;

            // iterate allocations
            foreach ($allocs as $a) {
                $batchId = (int)$a['batch_id'];
                $qty = isset($a['qty']) ? (float)$a['qty'] : 0.0;
                if ($qty <= 0) continue;

                // read and lock batch
                $getBatchStmt->bind_param("i", $batchId);
                $getBatchStmt->execute();
                $bRes = $getBatchStmt->get_result();
                $batch = $bRes->fetch_assoc();
                if (!$batch) throw new Exception("دفعة (batch) رقم {$batchId} غير موجودة (allocation #{$a['alloc_id']}).");

                // determine cap (original_qty or batch_qty)
                $origQty = null;
                if (isset($batch['original_qty']) && $batch['original_qty'] !== null) $origQty = (float)$batch['original_qty'];
                elseif (isset($batch['batch_qty']) && $batch['batch_qty'] !== null) $origQty = (float)$batch['batch_qty'];

                // update remaining + status in one query
                $updateIncrementAndStatusStmt->bind_param("ddi", $qty, $qty, $batchId);
                $updateIncrementAndStatusStmt->execute();
                if ($updateIncrementAndStatusStmt->affected_rows === 0) {
                    // maybe id not found--log and continue/throw
                    // ci_log("Warning: update affected 0 rows for batch {$batchId}");
                }

                // read batch after update
                $getBatchAfterStmt->bind_param("i", $batchId);
                $getBatchAfterStmt->execute();
                $after = $getBatchAfterStmt->get_result()->fetch_assoc();
                if (!$after) throw new Exception("خطأ: لم نتمكن من قراءة الباتش بعد التحديث {$batchId}.");

                $newRemaining = (float)$after['remaining'];
                if ($origQty !== null && $newRemaining > $origQty + 0.000001) {
                    throw new Exception("العملية ستجعل remaining تتجاوز original_qty للدفعة #{$batchId}.");
                }

                $totalRestored += $qty;
            }

            // close batch stmts
            $getBatchStmt->close();
            $updateIncrementAndStatusStmt->close();
            $getBatchAfterStmt->close();

            // insert cancellation header
            $insCancel = $conn->prepare("
                INSERT INTO invoice_cancellations (invoice_out_id, cancelled_by, cancelled_at, reason, total_restored_qty)
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $insCancel->bind_param("iisd", $invoiceId, $current_user_id, $reason, $totalRestored);
            $insCancel->execute();
            $cancelId = $conn->insert_id;
            $insCancel->close();

            // insert allocation logs
            $insAllocLog = $conn->prepare("
                INSERT INTO invoice_cancellation_allocations (cancellation_id, sale_item_allocation_id, batch_id, qty_restored, unit_cost)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($allocs as $a) {
                $allocId = (int)$a['alloc_id'];
                $batchId = (int)$a['batch_id'];
                $qtyRestored = isset($a['qty']) ? (float)$a['qty'] : 0.0;
                $unitCost = isset($a['unit_cost']) ? (float)$a['unit_cost'] : 0.0;
                $insAllocLog->bind_param("iiidd", $cancelId, $allocId, $batchId, $qtyRestored, $unitCost);
                $insAllocLog->execute();
            }
            $insAllocLog->close();

            // update invoice delivered flag
            $updInv = $conn->prepare("UPDATE invoices_out SET delivered = 'canceled', updated_at = NOW(), updated_by = ? WHERE id = ?");
            $updInv->bind_param("ii", $current_user_id, $invoiceId);
            $updInv->execute();
            $updInv->close();

            // commit
            $conn->commit();

            // ci_log("Cancel success: invoice {$invoiceId}, restored {$totalRestored} units, cancel_id={$cancelId}");
            echo json_encode(['success' => true, 'message' => 'تم إلغاء الفاتورة واستعادة الكميات بنجاح.', 'restored_qty' => $totalRestored]);
            exit;
        } catch (Throwable $e) {
            // rollback safely
            try {
                if (isset($conn) && ($conn instanceof mysqli) && $conn->connect_errno === 0) $conn->rollback();
            } catch (Throwable $_) {
            }
            // log full error
            $msg = "cancel_invoice EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
            // ci_log($msg);
            // return safe JSON (for dev you may include trace)
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }



    // helper: send json and exit
    function json_exit($arr, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($arr);
        exit;
    }

    $action = $_REQUEST['action'] ?? null;

    // ------------- 1) Preview endpoint (GET) -------------

    // ---------------------- بداية API للارجاع (ضع هذا تحت اتصال DB مباشرة) ----------------------


    // Handle AJAX before any HTML output
    // AJAX: get invoice items (for return modal)
    if (isset($_GET['action']) && $_GET['action'] === 'get_invoice_items') {


        // if (isset($_GET['action']) && $_GET['action'] === 'get_item_return_info' && isset($_GET['item_id'])) {
        //     $item_id = intval($_GET['item_id']);
        //     if ($item_id <= 0) json_out(['success' => false, 'message' => 'item_id invalid']);

        //     // إجمالي التخصيصات (الكمية المخصصة التي يمكن إرجاعها)
        //     $st = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS total_alloc FROM sale_item_allocations WHERE invoice_out_item_id = ?");
        //     if (!$st) json_out(['success' => false, 'message' => 'prepare failed: ' . $conn->error]);
        //     $st->bind_param('i', $item_id);
        //     $st->execute();
        //     $r = $st->get_result()->fetch_assoc();
        //     $st->close();
        //     $total_alloc = intval($r['total_alloc'] ?? 0);

        //     // جلب معلومات البند واسمه
        //     $st = $conn->prepare("SELECT i.id, i.invoice_out_id, i.product_id, i.quantity, p.name AS product_name FROM invoice_out_items i LEFT JOIN products p ON i.product_id = p.id WHERE i.id = ? LIMIT 1");
        //     if (!$st) json_out(['success' => false, 'message' => 'prepare failed: ' . $conn->error]);
        //     $st->bind_param('i', $item_id);
        //     $st->execute();
        //     $item = $st->get_result()->fetch_assoc();
        //     $st->close();
        //     if (!$item) json_out(['success' => false, 'message' => 'بند غير موجود']);

        //     json_out([
        //         'success' => true,
        //         'item_id' => intval($item['id']),
        //         'invoice_out_id' => intval($item['invoice_out_id']),
        //         'product_name' => $item['product_name'] ?? '',
        //         'original_qty' => floatval($item['quantity']),
        //         'max_returnable' => $total_alloc
        //     ]);
        // }
        header('Content-Type: application/json; charset=utf-8');

        // تحقق سريع من $conn
        if (!isset($conn) || !($conn instanceof mysqli)) {
            echo json_encode(['success' => false, 'error' => 'خطأ داخلي: $conn ليس كائن mysqli صالح.']);
            exit;
        }

        $invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
        if ($invoiceId <= 0) {
            echo json_encode(['success' => false, 'error' => 'معرف الفاتورة غير صالح.']);
            exit;
        }
        try {
            $stmt = $conn->prepare("
            SELECT ioi.id AS invoice_item_id, ioi.product_id, p.name, ioi.quantity AS qty
            FROM invoice_out_items ioi
            JOIN products p ON p.id = ioi.product_id
            WHERE ioi.invoice_out_id = ?
        ");
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $res = $stmt->get_result();
            $items = [];
            while ($r = $res->fetch_assoc()) {
                $items[] = [
                    'invoice_item_id' => (int)$r['invoice_item_id'],
                    'product_id' => (int)$r['product_id'],
                    'name' => $r['name'],
                    'qty' => (float)$r['qty']
                ];
            }
            $stmt->close();
            echo json_encode(['success' => true, 'items' => $items]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'خطأ في جلب البنود.', 'detail' => $e->getMessage()]);
        }
        exit;
    }






    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_return') {
        header('Content-Type: application/json; charset=utf-8');

        try {
            if (session_status() === PHP_SESSION_NONE) session_start();

            // auth
            if (empty($_SESSION['id']) && empty($_SESSION['user_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'غير مسموح.']);
                exit;
            }
            if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'لا تملك صلاحية إجراء هذا الإجراء.']);
                exit;
            }

            // inputs
            $invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
            $itemsJson = $_POST['items'] ?? '[]';
            $items = json_decode($itemsJson, true);
            if (!is_array($items)) $items = [];

            // CSRF
            if (!isset($_POST['csrf_token']) || ($_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? ''))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'CSRF token invalid']);
                exit;
            }

            if ($invoiceId <= 0) throw new Exception("معرف الفاتورة غير صالح.");

            // transaction
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $inTransaction = false;
            if (method_exists($conn, 'begin_transaction')) {
                $conn->begin_transaction();
                $inTransaction = true;
            } else {
                $conn->autocommit(false);
                $inTransaction = true;
            }

            // lock invoice


            // ---------- (استبدال) قفل صف الفاتورة كما سابقا لكن بدون قراءة عمود total ----------
            $sql = "SELECT id FROM invoices_out WHERE id = ? FOR UPDATE";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) throw new Exception("prepare failed: " . $conn->error);
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $res = method_exists($stmt, 'get_result') ? $stmt->get_result() : null;
            $inv = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if (!$inv) throw new Exception("الفاتورة غير موجودة.");
            // $inv موجود ومقفول لكن لا نحدث عمود total لأنك لا تريده


            // ---------- (استبدال) جلب بنود الفاتورة مع total_price و selling_price وقفلها ----------
            $sql = "SELECT id, product_id, `quantity` AS qty, COALESCE(total_price,0) AS total_price, COALESCE(selling_price,0) AS selling_price
        FROM invoice_out_items WHERE invoice_out_id = ? FOR UPDATE";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) throw new Exception("prepare failed: " . $conn->error);
            $stmt->bind_param('i', $invoiceId);
            $stmt->execute();
            $res = method_exists($stmt, 'get_result') ? $stmt->get_result() : null;
            $currentItems = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $currentItems[(int)$r['id']] = [
                        'product_id' => (int)$r['product_id'],
                        'qty' => (float)$r['qty'],
                        'total_price' => (float)$r['total_price'],
                        'selling_price' => (float)$r['selling_price']
                    ];
                }
            } else {
                // fallback bind_result (نادر)
                $stmt->bind_result($rid, $rproduct_id, $rqty, $rtotal_price, $rselling_price);
                while ($stmt->fetch()) {
                    $currentItems[(int)$rid] = [
                        'product_id' => (int)$rproduct_id,
                        'qty' => (float)$rqty,
                        'total_price' => (float)$rtotal_price,
                        'selling_price' => (float)$rselling_price
                    ];
                }
            }
            $stmt->close();
            $itemsCount = count($currentItems);


            // validate requested items (support multiple field names from client)
            $toProcess = [];
            foreach ($items as $it) {
                $iid = isset($it['invoice_item_id']) ? (int)$it['invoice_item_id'] : (isset($it['id']) ? (int)$it['id'] : 0);
                if (isset($it['qty'])) $q = (float)$it['qty'];
                elseif (isset($it['quantity'])) $q = (float)$it['quantity'];
                else $q = 0.0;
                $del = isset($it['delete']) ? (int)$it['delete'] : (isset($it['remove']) ? (int)$it['remove'] : 0);

                if ($iid <= 0 || $q <= 0) continue;
                if (!isset($currentItems[$iid])) throw new Exception("بند الفاتورة (#{$iid}) غير موجود.");
                $max = $currentItems[$iid]['qty'];
                if ($q > $max + 1e-9) throw new Exception("الكمية المراد إرجاعها أكبر من الكمية المباعة لبند (#{$iid}).");
                if ($itemsCount === 1 && abs($q - $max) < 1e-9) {
                    throw new Exception("الفاتورة تحتوي على بند واحد فقط — لا يُسمح بإرجاع كل الكمية. الرجاء إلغاء الفاتورة إذا أردت إزالة كل الكمية.");
                }
                $toProcess[$iid] = ['qty' => $q, 'delete' => $del];
            }

            if (count($toProcess) === 0) throw new Exception("لا توجد بنود صحيحة للإرجاع.");

            // prepare statements (we DO NOT insert return master records)
            $selectAllocs = $conn->prepare("SELECT id AS alloc_id, batch_id, qty, unit_cost FROM sale_item_allocations WHERE sale_item_id = ? ORDER BY id DESC FOR UPDATE");
            if ($selectAllocs === false) throw new Exception("prepare failed: " . $conn->error);
            $updateBatch = $conn->prepare("UPDATE batches SET remaining = ?, status = ?, adjusted_at = NOW(), adjusted_by = ? WHERE id = ?");
            if ($updateBatch === false) throw new Exception("prepare failed: " . $conn->error);
            // تحديث qty و line_cost معاً
            $updateAlloc = $conn->prepare("UPDATE sale_item_allocations SET qty = ?, line_cost = ? WHERE id = ?");
            if ($updateAlloc === false) throw new Exception("prepare failed: " . $conn->error);

            $deleteAlloc = $conn->prepare("DELETE FROM sale_item_allocations WHERE id = ?");
            if ($deleteAlloc === false) throw new Exception("prepare failed: " . $conn->error);

            // ---------- (استبدال) تحديث بند الفاتورة: تحديث quantity و total_price ؛ وحذف بند ----------
            $updateItem = $conn->prepare("UPDATE invoice_out_items SET `quantity` = ?, total_price = ? WHERE id = ?");
            if ($updateItem === false) throw new Exception("prepare failed: " . $conn->error);
            $deleteItem = $conn->prepare("DELETE FROM invoice_out_items WHERE id = ?");
            if ($deleteItem === false) throw new Exception("prepare failed: " . $conn->error);

            $totalRestored = 0.0;
            $current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;

            // process each item: restore batches (LIFO) and update allocations + invoice items
            foreach ($toProcess as $invoiceItemId => $info) {
                $need = (float)$info['qty'];
                if ($need <= 0) continue;
                $curQty = $currentItems[$invoiceItemId]['qty'];



                // 
                if ($need > $curQty + 1e-9) throw new Exception("كمية الإرجاع لبند #{$invoiceItemId} أكبر من المسموح.");

                // get allocations for this sale item (locked)
                $selectAllocs->bind_param('i', $invoiceItemId);
                $selectAllocs->execute();
                $ares = method_exists($selectAllocs, 'get_result') ? $selectAllocs->get_result() : null;
                $allocs = [];
                if ($ares) {
                    while ($ar = $ares->fetch_assoc()) $allocs[] = $ar;
                } else {
                    $selectAllocs->bind_result($alloc_id_f, $batch_id_f, $qty_f, $unit_cost_f);
                    while ($selectAllocs->fetch()) {
                        $allocs[] = ['alloc_id' => $alloc_id_f, 'batch_id' => $batch_id_f, 'qty' => $qty_f, 'unit_cost' => $unit_cost_f];
                    }
                }

                // restore from allocations (LIFO)
                foreach ($allocs as $a) {
                    if ($need <= 0) break;
                    $allocId = (int)$a['alloc_id'];
                    $batchId = (int)$a['batch_id'];
                    $allocQty = (float)$a['qty'];
                    if ($allocQty <= 0) continue;

                    $takeBack = min($allocQty, $need);

                    // lock batch row and read remaining
                    $bstmt = $conn->prepare("SELECT remaining FROM batches WHERE id = ? FOR UPDATE");
                    if ($bstmt === false) throw new Exception("prepare failed: " . $conn->error);
                    $bstmt->bind_param('i', $batchId);
                    $bstmt->execute();
                    $bres = method_exists($bstmt, 'get_result') ? $bstmt->get_result() : null;
                    $brow = $bres ? $bres->fetch_assoc() : null;
                    $bstmt->close();
                    $curRem = (float)($brow['remaining'] ?? 0.0);
                    $newRem = $curRem + $takeBack;
                    $newStatus = ($newRem > 0) ? 'active' : 'consumed';

                    // update batch
                    $updateBatch->bind_param('dsii', $newRem, $newStatus, $current_user_id, $batchId);
                    $updateBatch->execute();

                    // update or delete allocation
                    $remainingAllocAfter = $allocQty - $takeBack;
                    // ===== inside foreach($allocs as $a) { ... } after $remainingAllocAfter is set =====

                    // احصل unit_cost من الصف الحالي (احتياط لعدة أسماء مفاتيح)
                    $allocUnitCost = (float) ($a['unit_cost'] ?? $a['unitCost'] ?? 0.0);

                    // إذا المتبقي موجب — حدّث qty و line_cost
                    if ($remainingAllocAfter > 1e-9) {
                        // حساب line_cost بدقة 4 منازل عشرية (مطابق schema)
                        $newLineCost = round($remainingAllocAfter * $allocUnitCost, 4);

                        // debug صغير مفيد أثناء الاختبار (احذف أو علقه بعد التأكد)
                        error_log("DEBUG updateAlloc allocId={$allocId} remainingAllocAfter={$remainingAllocAfter} allocUnitCost={$allocUnitCost} newLineCost={$newLineCost}");

                        // bind types: double (qty), double (line_cost), int (id)
                        $updateAlloc->bind_param('ddi', $remainingAllocAfter, $newLineCost, $allocId);
                        if ($updateAlloc->execute() === false) {
                            throw new Exception("فشل تحديث sale_item_allocations id={$allocId}: " . $updateAlloc->error);
                        }
                    } else {
                        // حذف التخصيص إذا لم يبقَ كمية
                        $deleteAlloc->bind_param('i', $allocId);
                        if ($deleteAlloc->execute() === false) {
                            throw new Exception("فشل حذف sale_item_allocations id={$allocId}: " . $deleteAlloc->error);
                        }
                    }


                    $totalRestored += $takeBack;
                    $need -= $takeBack;
                } // end allocations for this item


                if ($need > 1e-9) {
                    throw new Exception("تعذر استرجاع الكمية المطلوبة لبند #{$invoiceItemId}. تحقق من السجلات.");
                }

                // start recalculate cost_price_per_unit after restoring qty
                // ===== بعد انتهاء معالجة الـ allocations ولديك التأكد أن $need == 0 =====

                $sumStmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(qty * unit_cost), 0) AS sum_cost,
        COALESCE(SUM(qty), 0) AS sum_qty
    FROM sale_item_allocations
    WHERE sale_item_id = ?
    FOR UPDATE
 ");
                if ($sumStmt === false) throw new Exception("prepare failed: " . $conn->error);
                $sumStmt->bind_param('i', $invoiceItemId);
                $sumStmt->execute();
                $sumRes = method_exists($sumStmt, 'get_result') ? $sumStmt->get_result() : null;
                $sumCost = 0.0;
                $sumQty = 0.0;
                if ($sumRes) {
                    $row = $sumRes->fetch_assoc();
                    $sumCost = (float)($row['sum_cost'] ?? 0.0);
                    $sumQty  = (float)($row['sum_qty'] ?? 0.0);
                } else {
                    $sumStmt->bind_result($sum_cost_f, $sum_qty_f);
                    if ($sumStmt->fetch()) {
                        $sumCost = (float)$sum_cost_f;
                        $sumQty  = (float)$sum_qty_f;
                    }
                }
                $sumStmt->close();

                // احسب المتوسط الجديد - دقة داخلية 4 منازل عشرية
                if ($sumQty > 1e-9) {
                    $new_unit_cost = round($sumCost / $sumQty, 4);
                    // debug server-side فقط
                    error_log(sprintf("return calc: invoice_item_id=%d sumCost=%.4f sumQty=%.4f new_unit_cost=%.4f", $invoiceItemId, $sumCost, $sumQty, $new_unit_cost));
                } else {
                    $new_unit_cost = 0.0;
                    error_log(sprintf("return calc: invoice_item_id=%d sumQty=0 -> new_unit_cost set to 0", $invoiceItemId));
                }

                // حدث cost_price_per_unit في invoice_out_items ليعكس المتوسط الجديد
                $updateCostStmt = $conn->prepare("UPDATE invoice_out_items SET cost_price_per_unit = ? WHERE id = ?");
                if ($updateCostStmt === false) throw new Exception("prepare failed: " . $conn->error);
                $updateCostStmt->bind_param('di', $new_unit_cost, $invoiceItemId);
                $updateCostStmt->execute();
                $updateCostStmt->close();
                // end recalculate cost_price_per_unit


                // ---------- (استبدال داخل foreach $toProcess) تحديث بند الفاتورة بناءً على selling_price أو على total_price/qty ----------
                $curQty = $currentItems[$invoiceItemId]['qty'];
                $curTotalPrice = $currentItems[$invoiceItemId]['total_price'];
                $selling_price = $currentItems[$invoiceItemId]['selling_price'];

                // الكمية الجديدة بعد الإرجاع
                $newItemQty = $curQty - $info['qty'];

                if ($newItemQty > 1e-9) {
                    // خذ selling_price إن موجود، وإلا احسب من total_price/qty كاحتياط
                    if ($selling_price > 0.0) {
                        $unit_price = $selling_price;
                    } else if ($curQty > 1e-9) {
                        $unit_price = $curTotalPrice / $curQty;
                    } else {
                        $unit_price = 0.0;
                    }

                    $newTotalPrice = round($unit_price * $newItemQty, 2);

                    // حدث الكمية والسعر الإجمالي للبند
                    $updateItem->bind_param('ddi', $newItemQty, $newTotalPrice, $invoiceItemId);
                    $updateItem->execute();

                    // فرق التخفيض للبند لنستخدمه لاحقاً لحساب المجموع المعاد
                    $itemPriceReduction = $curTotalPrice - $newTotalPrice;
                } else {
                    // حذف البند بالكامل
                    $deleteItem->bind_param('i', $invoiceItemId);
                    $deleteItem->execute();

                    // نقسم هنا: نخصم سعر البند الحالي كاملاً من إجمالي الفاتورة
                    $itemPriceReduction = $curTotalPrice;
                }

                // نجمع التخفيض لحساب إجمالي الفاتورة من البنود لاحقاً
                $invoiceTotalReduction = ($invoiceTotalReduction ?? 0.0) + $itemPriceReduction;
            } // end foreach items

            // ---------- (إضافة) حساب المجموع الحالي من بنود الفاتورة بعد التحديثات ----------
            $sstmt = $conn->prepare("SELECT COALESCE(SUM(total_price),0) AS sum_total FROM invoice_out_items WHERE invoice_out_id = ?");
            if ($sstmt === false) throw new Exception("prepare failed: " . $conn->error);
            $sstmt->bind_param('i', $invoiceId);
            $sstmt->execute();
            $sres = method_exists($sstmt, 'get_result') ? $sstmt->get_result() : null;
            $newInvoiceTotal = 0.0;
            if ($sres) {
                $row = $sres->fetch_assoc();
                $newInvoiceTotal = (float)$row['sum_total'];
            }
            $sstmt->close();

            // لا نحدث invoices_out.total لأنك طلبت ذلك؛ سنرجع المجموع في الاستجابة فقط.

            // commit transaction
            if ($inTransaction) $conn->commit();


            // echo json_encode(['success' => true, 'message' => 'تمت عملية الإرجاع بنجاح (بدون إنشاء سجلات).', 'restored_qty' => $totalRestored]);
            echo json_encode([
                'success' => true,
                'message' => 'تمت عملية الإرجاع بنجاح (بدون إنشاء سجلات).',
                'restored_qty' => $totalRestored,
                'new_invoice_total' => round($newInvoiceTotal, 2)
            ]);

            exit;
        } catch (Throwable $e) {
            // rollback
            if (isset($inTransaction) && $inTransaction && isset($conn) && $conn instanceof mysqli) {
                try {
                    $conn->rollback();
                } catch (Throwable $_) { /* ignore */
                }
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'فشل تنفيذ الإرجاع: ' . $e->getMessage()]);
            exit;
        }
    }




    // ---------------------- نهاية API للارجاع ----------------------


    // ---------- نهاية: إضافات الإرجاع ----------


    //end delete-invoices


    // الآن آمِن لإخراج الرأس/الصفحة
    require_once BASE_DIR . 'partials/header.php';

    $message = "";
    $result = null;
    $grand_total_all_pending = 0;
    $displayed_invoices_sum = 0;

    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
    }

    // ---------------- POST: تسليم فاتورة (mark_delivered) ----------------
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mark_delivered'])) {
        ob_start(); // علشان نمنع أي headers conflict
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $alert = ["error", "خطأ: طلب غير صالح (CSRF)."];
        } elseif (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $alert = ["error", "ليس لديك صلاحية لتنفيذ هذه العملية."];
        } else {
            $invoice_id_to_deliver = intval($_POST['invoice_id_to_deliver'] ?? 0);
            if ($invoice_id_to_deliver > 0) {
                $updated_by = intval($_SESSION['id'] ?? 0);
                $sql_update_delivery = "UPDATE invoices_out 
                                    SET delivered = 'yes', updated_by = ?, updated_at = NOW() 
                                    WHERE id = ?";
                if ($stmt_update = $conn->prepare($sql_update_delivery)) {
                    $stmt_update->bind_param("ii", $updated_by, $invoice_id_to_deliver);
                    if ($stmt_update->execute()) {
                        if ($stmt_update->affected_rows > 0) {
                            $alert = ["success", "تم تحديث حالة الفاتورة رقم #{$invoice_id_to_deliver} إلى مستلمة."];
                        } else {
                            $alert = ["warning", "لم يتم تعديل الحالة — ربما كانت الفاتورة مستلمة سابقاً."];
                        }
                    } else {
                        $alert = ["error", "خطأ أثناء التحديث: " . e($stmt_update->error)];
                    }
                    $stmt_update->close();
                } else {
                    $alert = ["error", "خطأ في تحضير استعلام التحديث: " . e($conn->error)];
                }
            } else {
                $alert = ["warning", "رقم فاتورة غير صالح."];
            }
        }

        // بعد التحديث، نعرض SweetAlert
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
      Swal.fire({
        icon: '{$alert[0]}',
        title: '{$alert[1]}',
        confirmButtonText: 'تم',
        confirmButtonColor: '#3085d6'
      }).then(() => {
        window.location.href = '" . htmlspecialchars($_SERVER['PHP_SELF']) . "';
      });
    </script>";
        exit;
    }


    // ---------------- POST: حذف فاتورة (delete_sales_invoice) ----------------
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_sales_invoice'])) {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $_SESSION['message'] = "<div class='alert alert-danger'>خطأ: طلب غير صالح (CSRF).</div>";
        } elseif (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $_SESSION['message'] = "<div class='alert alert-danger'>ليس لديك صلاحية لحذف الفواتير.</div>";
        } else {
            $invoice_out_id_to_delete = intval($_POST['invoice_out_id_to_delete'] ?? 0);
            if ($invoice_out_id_to_delete > 0) {
                try {
                    $conn->begin_transaction();
                    // جلب حالة الفاتورة وبنودها
                    $s1 = $conn->prepare("SELECT delivered FROM invoices_out WHERE id = ? LIMIT 1");
                    $s1->bind_param("i", $invoice_out_id_to_delete);
                    $s1->execute();
                    $info = $s1->get_result()->fetch_assoc();
                    $s1->close();
                    $is_delivered = ($info && $info['delivered'] === 'yes');

                    // جلب البنود
                    $s2 = $conn->prepare("SELECT product_id, quantity FROM invoice_out_items WHERE invoice_out_id = ?");
                    $s2->bind_param("i", $invoice_out_id_to_delete);
                    $s2->execute();
                    $res2 = $s2->get_result();
                    $items_to_restore = [];
                    while ($r = $res2->fetch_assoc()) $items_to_restore[] = $r;
                    $s2->close();

                    // إذا كانت مستلمة، نعيد الكميات إلى المخزون
                    if ($is_delivered && !empty($items_to_restore)) {
                        $upd = $conn->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                        foreach ($items_to_restore as $it) {
                            $q = floatval($it['quantity']);
                            $pid = intval($it['product_id']);
                            $upd->bind_param("di", $q, $pid);
                            $upd->execute();
                        }
                        $upd->close();
                    }

                    // حذف البنود ثم رأس الفاتورة
                    $d1 = $conn->prepare("DELETE FROM invoice_out_items WHERE invoice_out_id = ?");
                    $d1->bind_param("i", $invoice_out_id_to_delete);
                    $d1->execute();
                    $d1->close();

                    $d2 = $conn->prepare("DELETE FROM invoices_out WHERE id = ?");
                    $d2->bind_param("i", $invoice_out_id_to_delete);
                    $d2->execute();
                    $affected = $d2->affected_rows;
                    $d2->close();

                    $conn->commit();
                    if ($affected > 0) {
                        $_SESSION['message'] = "<div class='alert alert-success'>تم حذف الفاتورة #{$invoice_out_id_to_delete} وحذف بنودها.</div>";
                    } else {
                        $_SESSION['message'] = "<div class='alert alert-warning'>لم يتم العثور على الفاتورة أو تم حذفها مسبقاً.</div>";
                    }
                } catch (Exception $ex) {
                    // if ($conn->in_transaction) $conn->rollback();
                    $_SESSION['message'] = "<div class='alert alert-danger'>خطأ أثناء الحذف: " . e($ex->getMessage()) . "</div>";
                }
            } else {
                $_SESSION['message'] = "<div class='alert alert-warning'>رقم فاتورة غير صالح للحذف.</div>";
            }
        }

        // PRG مع الحفاظ على GET params
        $redirect = htmlspecialchars($_SERVER['PHP_SELF']);
        $params = [];
        if (!empty($_GET['invoice_q'])) $params[] = 'invoice_q=' . urlencode($_GET['invoice_q']);
        if (!empty($_GET['mobile_q'])) $params[] = 'mobile_q=' . urlencode($_GET['mobile_q']);
        if (!empty($_GET['filter_group_val'])) $params[] = 'filter_group_val=' . urlencode($_GET['filter_group_val']);
        if (!empty($_GET['customer_id'])) $params[] = 'customer_id=' . urlencode($_GET['customer_id']);
        if (!empty($params)) $redirect .= '?' . implode('&', $params);
        header("Location: " . $redirect);
        exit;
    }




    // ---------------- قراءة معايير البحث/الفلترة ================
    $invoice_q = isset($_GET['invoice_q']) ? trim((string)$_GET['invoice_q']) : '';
    $mobile_q  = isset($_GET['mobile_q']) ? trim((string)$_GET['mobile_q']) : '';
    $selected_group = isset($_GET['filter_group_val']) ? trim((string)$_GET['filter_group_val']) : '';
    $customer_filter_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
    $notes_q = isset($_GET['notes_q']) ? trim((string)$_GET['notes_q']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    // إجمالي الفواتير غير المستلمة (بدون تطبيق البحث) بعد الخصم
    $sql_grand_total = "SELECT 
                        COALESCE(SUM(CASE WHEN io.total_after_discount > 0 THEN io.total_after_discount ELSE io.total_before_discount END), 0) AS grand_total_after_discount,
                        COALESCE(SUM(io.total_before_discount), 0) AS grand_total_before_discount
                        FROM invoices_out io
                        WHERE io.delivered = 'no'";
    $res_gt = $conn->query($sql_grand_total);
    $grand_total_all_pending = 0;
    $grand_total_all_pending_before = 0;
    if ($res_gt) {
        $gt_row = $res_gt->fetch_assoc();
        $grand_total_all_pending_before = floatval($gt_row['grand_total_before_discount'] ?? 0);
        $grand_total_all_pending = floatval($gt_row['grand_total_after_discount'] ?? 0);
        // إذا كان total_after_discount = 0، استخدم total_before_discount
        if ($grand_total_all_pending <= 0) {
            $grand_total_all_pending = $grand_total_all_pending_before;
        }
        $res_gt->free();
    }

    // بناء استعلام جلب
    $sql_select = "SELECT i.id, i.invoice_group, i.created_at,
                        COALESCE(c.name,'(عميل نقدي)') AS customer_name,
                        COALESCE(c.mobile,'-') AS customer_mobile,
                        u.username AS creator_name,
                        COALESCE(i.notes,'') AS notes,
                        COALESCE((SELECT SUM(item.total_price) FROM invoice_out_items item WHERE item.invoice_out_id = i.id),0) AS invoice_total,
                        i.total_before_discount,
                        i.discount_type,
                        i.discount_value,
                        i.discount_amount,
                        i.total_after_discount
                FROM invoices_out i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN users u ON i.created_by = u.id
                WHERE i.delivered = 'no' ";


    $params = [];
    $types = "";

    // فلترة بالعميل id (إن وُجد في GET)
    if ($customer_filter_id > 0) {
        $sql_select .= " AND i.customer_id = ? ";
        $params[] = $customer_filter_id;
        $types .= "i";
    }

    // فلتر المجموعة
    if ($selected_group !== '') {
        $sql_select .= " AND i.invoice_group = ? ";
        $params[] = $selected_group;
        $types .= "s";
    }

    // رقم الفاتورة (أولوية إذا معطى)
    if ($invoice_q !== '') {
        $digits = preg_replace('/\D/', '', $invoice_q);
        if ($digits !== '') {
            $sql_select .= " AND i.id = ? ";
            $params[] = intval($digits);
            $types .= "i";
        }
    } elseif ($mobile_q !== '') {
        $sql_select .= " AND COALESCE(c.mobile,'') LIKE ? ";
        $params[] = '%' . $mobile_q . '%';
        $types .= "s";
    }
    if ($notes_q !== '') {
        $sql_select .= " AND COALESCE(i.notes,'') LIKE ? ";
        $params[] = '%' . $notes_q . '%';
        $types .= "s";
    }

    if ($date_from !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $date_from);
        if ($d !== false) {
            $start = $d->format('Y-m-d') . ' 00:00:00';
            $sql_select .= " AND i.created_at >= ? ";
            $params[] = $start;
            $types .= 's';
        }
    }
    if ($date_to !== '') {
        $d2 = DateTime::createFromFormat('Y-m-d', $date_to);
        if ($d2 !== false) {
            // inclusive to date -> use next day as exclusive upper bound
            $d2->modify('+1 day');
            $end = $d2->format('Y-m-d') . ' 00:00:00';
            $sql_select .= " AND i.created_at < ? ";
            $params[] = $end;
            $types .= 's';
        }
    }

    $sql_select .= " ORDER BY i.created_at DESC, i.id DESC LIMIT 2000";

    if ($stmt = $conn->prepare($sql_select)) {
        if (!empty($params)) {
            $bind_names[] = $types;
            for ($i = 0; $i < count($params); $i++) $bind_names[] = &$params[$i];
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
            unset($bind_names);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
        } else {
            $message = "<div class='alert alert-danger'>خطأ أثناء تنفيذ استعلام جلب الفواتير: " . e($stmt->error) . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert alert-danger'>خطأ في تحضير استعلام: " . e($conn->error) . "</div>";
    }

    // روابط
    $view_invoice_page_link = BASE_URL . "invoices_out/view_invoice_detaiels.php";
    $delivered_invoices_link = BASE_URL . "admin/delivered_invoices.php";
    $current_page_link = htmlspecialchars($_SERVER['PHP_SELF']);

    require_once BASE_DIR . 'partials/sidebar.php';
    ?>

    <style>
        /* جميع الـ styles داخل pending-invoices-page لتجنب override */



        /* منع scroll على body عند وجود pending-invoices-page */
        /* body:has(.pending-invoices-page) {
            overflow-x: hidden;
        } */

        .pending-invoices-page .shell {
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-height: calc(100vh - 70px);
            /* 70px navbar + 40px padding */
            overflow: hidden;
        }

        .pending-invoices-page header.top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            flex-shrink: 0;
        }

        .pending-invoices-page .brand {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .pending-invoices-page .logo {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-sm, 8px);
            background: var(--grad-1, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 700;
            box-shadow: var(--shadow-1, 0 1px 3px rgba(0, 0, 0, 0.1));
        }

        .pending-invoices-page h1 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text, #1f2937);
        }

        .pending-invoices-page .sub {
            color: var(--muted, #6b7280);
            font-size: 0.9rem;
        }

        /* top stats */
        .pending-invoices-page .top-stats {
            display: flex;
            gap: 12px;
            /* align-items: center; */
            flex-wrap: wrap;
        }

        .pending-invoices-page .stat {
            background: var(--surface, #fff);
            padding: 12px 16px;
            border-radius: var(--radius-sm, 8px);
            box-shadow: var(--shadow-1, 0 1px 3px rgba(0, 0, 0, 0.1));
            min-width: 140px;
            border: 1px solid var(--border, #e5e7eb);
        }

        .pending-invoices-page .stat .lbl {
            color: var(--muted, #6b7280);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .pending-invoices-page .stat .num {
            font-weight: 800;
            margin-top: 4px;
            color: var(--text, #1f2937);
            font-size: 1.1rem;
        }

        /* main layout - بدون scroll خارجي */
        .pending-invoices-page .pending-invoices-main {
            display: flex;
            gap: 16px;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            padding: 20px 0px;
        }

        .pending-invoices-page .pending-invoices-main.row {
            margin: 0;
        }

        .pending-invoices-page .filters-section {
            background: var(--surface, #fff);
            border-radius: var(--radius, 12px);
            padding: 16px;
            box-shadow: var(--shadow-1, 0 1px 3px rgba(0, 0, 0, 0.1));
            border: 1px solid var(--border, #e5e7eb);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            flex-shrink: 0;
            max-height: 67vh;
        }

        .pending-invoices-page .filters-section.col-3 {
            max-width: 100%;
            flex: 0 0 25%;
            /* 25% من العرض */
            min-width: 250px;
            /* الحد الأدنى للعرض */
            width: 25%;
        }

        .pending-invoices-page .content {
            background: transparent;
            display: flex;
            flex-direction: column;
            gap: 16px;
            flex: 1;
            min-height: 0;
            max-height: 67vh;
            /* overflow-y: hidden; */
        }

        .pending-invoices-page .content.col-9 {
            max-width: 100%;
            flex: 1 1 auto;
            min-width: 300px;
            /* الحد الأدنى للعرض */
            width: 100%;
        }

        /* filters */
        .pending-invoices-page .filter-title {
            font-weight: 800;
            margin-bottom: 12px;
            color: var(--text, #1f2937);
            font-size: 1rem;
        }

        .pending-invoices-page .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .pending-invoices-page .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .pending-invoices-page .field label {
            font-size: 0.9rem;
            color: var(--text-soft, #4b5563);
            font-weight: 700;
        }

        .pending-invoices-page .field input[type="text"],
        .pending-invoices-page .field input[type="number"],
        .pending-invoices-page .field input[type="date"],
        .pending-invoices-page .field textarea,
        .pending-invoices-page .field select {
            padding: 10px 12px;
            border-radius: var(--radius-sm, 8px);
            border: 1px solid var(--border, #e5e7eb);
            background: var(--surface-2, #f9fafb);
            font-size: 0.95rem;
            color: var(--text, #1f2937);
            width: 100%;
        }

        .pending-invoices-page .field input:focus,
        .pending-invoices-page .field select:focus,
        .pending-invoices-page .field textarea:focus {
            border-color: var(--primary, #3b82f6);
            box-shadow: var(--ring, 0 0 0 3px rgba(59, 130, 246, 0.1));
            outline: none;
        }

        .pending-invoices-page .field input::placeholder {
            color: var(--muted, #6b7280);
        }

        .pending-invoices-page .small-hint {
            font-size: 0.82rem;
            color: var(--muted, #6b7280);
        }

        .pending-invoices-page .filters-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .pending-invoices-page .btn.apply {
            background: var(--primary, #3b82f6);
            color: #fff;
            box-shadow: var(--shadow-2, 0 4px 6px rgba(0, 0, 0, 0.1));
            padding: 10px 20px;
            border-radius: var(--radius-sm, 8px);
            border: 0;
            cursor: pointer;
            font-weight: 700;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .pending-invoices-page .btn.apply:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-2, 0 6px 12px rgba(0, 0, 0, 0.15));
        }

        .pending-invoices-page .btn.reset {
            background: transparent;
            border: 1px solid var(--border, #e5e7eb);
            color: var(--text, #1f2937);
            padding: 10px 20px;
            border-radius: var(--radius-sm, 8px);
            cursor: pointer;
            font-weight: 700;
            transition: background 0.2s;
        }

        .pending-invoices-page .btn.reset:hover {
            background: var(--surface-2, #f9fafb);
        }

        /* summary cards */
        .pending-invoices-page .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .pending-invoices-page .summary-card {
            background: var(--surface, #fff);
            border-radius: var(--radius, 12px);
            padding: 16px;
            box-shadow: var(--shadow-1, 0 1px 3px rgba(0, 0, 0, 0.1));
            border: 1px solid var(--border, #e5e7eb);
        }

        .pending-invoices-page .summary-card .title {
            font-weight: 700;
            color: var(--text-soft, #4b5563);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .pending-invoices-page .summary-card .value {
            font-weight: 800;
            color: var(--text, #1f2937);
            font-size: 1.3rem;
        }

        /* list area */
        .pending-invoices-page .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .pending-invoices-page .toolbar .small {
            color: var(--muted, #6b7280);
            font-size: 0.9rem;
        }

        .pending-invoices-page .list-wrapper {
            background: var(--surface, #fff);
            border-radius: var(--radius, 12px);
            padding: 16px;
            box-shadow: var(--shadow-1, 0 1px 3px rgba(0, 0, 0, 0.1));
            border: 1px solid var(--border, #e5e7eb);
            overflow-y: auto;

            flex: 1;
            min-height: 0;
            /* max-height: 100%; */
            -webkit-overflow-scrolling: touch;
        }

        .pending-invoices-page .list {
            display: grid;
            gap: 12px;
        }

        /* invoice card improved */
        .pending-invoices-page .invoice {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            background: var(--surface, #fff);
            padding: 16px;
            border-radius: var(--radius-sm, 8px);
            box-shadow: var(--shadow-1, 0 1px 3px rgba(0, 0, 0, 0.1));
            border: 1px solid var(--border, #e5e7eb);
            align-items: flex-start;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }

        .pending-invoices-page .invoice:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-2, 0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .pending-invoices-page .invoice-left {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            min-width: 0;
            flex: 1;
            max-width: 100%;
        }

        .pending-invoices-page .invoice-left .badge {
            background: var(--grad-1, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
            color: #fff;
            padding: 8px 12px;
            border-radius: var(--radius-sm, 8px);
            font-weight: 800;
            font-size: 0.9rem;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .pending-invoices-page .meta {
            min-width: 0;
            flex: 1;
            max-width: 100%;
            overflow: hidden;
        }

        .pending-invoices-page .meta .name {
            font-weight: 800;
            color: var(--text, #1f2937);
            font-size: 1rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pending-invoices-page .meta .name::before {
            content: "👤";
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .pending-invoices-page .meta .notes {
            color: var(--muted, #6b7280);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-top: 8px;
            min-height: 1.5em;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }

        .pending-invoices-page .meta .extra {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            color: var(--muted, #6b7280);
            font-size: 0.85rem;
            flex-wrap: wrap;
        }

        .pending-invoices-page .meta .extra>div {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .pending-invoices-page .invoice-right {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            flex-shrink: 0;
            justify-content: flex-end;
        }

        .pending-invoices-page .amount {
            font-weight: 800;
            min-width: 120px;
            text-align: left;
            color: var(--text, #1f2937);
            font-size: 1.1rem;
        }

        .pending-invoices-page .amount-with-discount {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
            min-width: 140px;
        }

        .pending-invoices-page .amount-original {
            text-decoration: line-through;
            color: var(--muted, #6b7280);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .pending-invoices-page .amount-final {
            font-weight: 800;
            color: var(--primary, #3b82f6);
            font-size: 1.2rem;
        }

        .pending-invoices-page .discount-badge {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            padding: 4px 10px;
            border-radius: var(--radius-sm, 8px);
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid #fbbf24;
        }

        .pending-invoices-page .status {
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .pending-invoices-page .status.pending {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .pending-invoices-page .status.paid {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .pending-invoices-page .status.overdue {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }

        .pending-invoices-page .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pending-invoices-page .actions button {
            padding: 8px 12px;
            border-radius: var(--radius-sm, 8px);
            border: 0;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
            transition: transform 0.2s;
        }

        .pending-invoices-page .actions button:hover {
            transform: translateY(-1px);
        }

        .pending-invoices-page .actions .deliver {
            background: var(--teal, #14b8a6);
            color: #fff;
        }

        .pending-invoices-page .actions .cancel {
            background: var(--rose, #f43f5e);
            color: #fff;
        }

        .pending-invoices-page .actions .show {
            background: var(--primary, #3b82f6);
            color: #fff;
        }

        .pending-invoices-page .actions .edit {
            background: var(--surface-2, #f9fafb);
            color: var(--text, #1f2937);
            border: 1px solid var(--border, #e5e7eb);
        }

        /* pagination */
        .pending-invoices-page .pager {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* toast */
        .pending-invoices-page .ipc-toast {
            position: fixed;
            right: 20px;
            bottom: 20px;
            background: #111827;
            color: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            z-index: 16000;
            opacity: 0;
            transform: translateY(8px);
            transition: all .28s;
        }

        .pending-invoices-page .ipc-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .pending-invoices-page .rim-qty-input {
            width: 80px;
        }

        .pending-invoices-page .rim-delete-btn {
            color: #b00;
            cursor: pointer;
        }

        .pending-invoices-page .swal2-container {
            z-index: 10000 !important;
        }

        /* Responsive - ممتاز */
        @media (max-width: 1200px) {

            /* إزالة تحويل layout إلى عمودي - نريد أن يبقى side-by-side */
            .pending-invoices-page .pending-invoices-main {
                flex-direction: row;
            }

            .pending-invoices-page .filters-section {
                max-height: none;
                /* height: 100%; */
                flex: 0 0 30%;
                /* زيادة العرض قليلاً على الشاشات المتوسطة */
                width: 30%;
                min-width: 250px;
            }

            .pending-invoices-page .content.col-9 {
                flex: 1 1 70%;
                /* 70% للـ content */
                min-width: 400px;
                /* زيادة الحد الأدنى */
            }

            /* فقط على الشاشات الصغيرة جداً نجعله عمودي */
            @media (max-height: 600px) {
                .pending-invoices-page .pending-invoices-main {
                    flex-direction: column;
                }

                .pending-invoices-page .filters-section {
                    max-height: 300px;
                    width: 100% !important;
                }


            }
        }

        @media (max-width: 992px) {
            .pending-invoices-page {
                padding: 12px;
                margin-top: 70px;
                /* الحفاظ على المسافة تحت navbar */
            }

            /* على الشاشات المتوسطة، يمكن تحويل layout إلى عمودي */
            .pending-invoices-page .pending-invoices-main {
                flex-direction: column;
            }

            .pending-invoices-page .filters-section {
                max-height: 400px;
                height: auto;
                width: 100% !important;
                flex: 0 0 auto !important;
            }



            .pending-invoices-page header.top {
                flex-direction: column;
                align-items: flex-start;
            }

            .pending-invoices-page .top-stats {
                width: 100%;
            }

            .pending-invoices-page .stat {
                flex: 1;
                min-width: 120px;
            }
        }

        @media (max-width: 768px) {
            .pending-invoices-page {
                padding: 8px;
                margin-top: 70px;
                /* الحفاظ على المسافة تحت navbar */
            }

            .pending-invoices-page .filters-grid {
                grid-template-columns: 1fr;
            }

            .pending-invoices-page .summary-cards {
                grid-template-columns: 1fr;
            }

            .pending-invoices-page .invoice {
                flex-direction: column;
                align-items: flex-start;
            }

            .pending-invoices-page .invoice-right {
                width: 100%;
                justify-content: space-between;
                margin-top: 12px;
            }

            .pending-invoices-page .amount,
            .pending-invoices-page .amount-with-discount {
                min-width: auto;
                width: 100%;
            }

            .pending-invoices-page .actions {
                width: 100%;
                justify-content: flex-start;
            }

            .pending-invoices-page .actions button {
                flex: 1;
                min-width: 80px;
            }

            .pending-invoices-page .filters-actions {
                flex-direction: column;
            }

            .pending-invoices-page .filters-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .pending-invoices-page h1 {
                font-size: 1rem;
            }

            .pending-invoices-page .sub {
                font-size: 0.8rem;
            }

            .pending-invoices-page .logo {
                width: 48px;
                height: 48px;
                font-size: 0.9rem;
            }

            .pending-invoices-page .stat {
                padding: 10px 12px;
                min-width: 100px;
            }

            .pending-invoices-page .stat .num {
                font-size: 1rem;
            }

            .pending-invoices-page .invoice {
                padding: 12px;
            }

            .pending-invoices-page .meta .name {
                font-size: 0.9rem;
            }

            .pending-invoices-page .meta .extra {
                font-size: 0.75rem;
                gap: 8px;
            }
        }

        @media print {
            .pending-invoices-page .no-print {
                display: none !important;
            }
        }

        .swal2-container {
            z-index: 10000;
        }
    </style>

    <div class="pending-invoices-page">
        <div class="shell container-fluid">
            <header class="top pt-2">
                <div class="brand">
                    <div class="logo">INV</div>
                    <div>
                        <h1>الفواتير المؤجله</h1>
                        <div class="sub">فلترة متقدمة — عرض واضح ومعلومات مُكملة لكل فاتورة</div>
                    </div>
                </div>

                <div class="top-stats">
                    <div class="stat">
                        <div class="lbl">عدد الفواتير</div>
                        <div class="num" id="stat-count"><?php echo ($result && $result->num_rows > 0) ? $result->num_rows : 0; ?></div>
                    </div>
                    <?php
                    // حساب إجمالي الفواتير المعروضة بعد الخصم
                    $displayed_total_after_discount = 0;
                    $displayed_total_before_discount = 0;
                    if ($result && $result->num_rows > 0) {
                        $result->data_seek(0);
                        while ($row = $result->fetch_assoc()) {
                            $total_before = floatval($row["total_before_discount"] ?? 0);
                            $total_after = floatval($row["total_after_discount"] ?? 0);
                            $invoice_total = floatval($row["invoice_total"] ?? 0);

                            if ($total_before <= 0) {
                                $total_before = $invoice_total;
                            }
                            if ($total_after <= 0) {
                                $total_after = $total_before;
                            }

                            $displayed_total_before_discount += $total_before;
                            $displayed_total_after_discount += $total_after;
                        }
                        $result->data_seek(0); // إعادة تعيين المؤشر
                    }
                    ?>
                    <!-- <div class="summary-card">
                        <div class="title">💰 الإجمالي الكلي (جميع الفواتير المعلقة)</div>
                        <div class="value" style="color:var(--primary)"><?php echo number_format($grand_total_all_pending, 2); ?> ج.م</div>
                        <?php if ($grand_total_all_pending < $grand_total_all_pending_before): ?>
                            <div style="font-size:0.85rem; color:var(--muted); margin-top:4px">
                                قبل الخصم: <span style="text-decoration:line-through"><?php echo number_format($grand_total_all_pending_before, 2); ?> ج.م</span>
                            </div>
                        <?php endif; ?>
                    </div> -->
                    <div class="summary-card">
                        <div class="title">📊 الإجمالي للفواتير المعروضة</div>
                        <div class="value" style="color:var(--teal)"><?php echo number_format($displayed_total_after_discount, 2); ?> ج.م</div>
                        <?php if ($displayed_total_after_discount < $displayed_total_before_discount): ?>
                            <div style="font-size:0.85rem; color:var(--muted); margin-top:4px">
                                قبل الخصم: <span style="text-decoration:line-through"><?php echo number_format($displayed_total_before_discount, 2); ?> ج.م</span>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </header>


            <div class="pending-invoices-main row  ">
                <!-- الفلاتر داخل main-content -->
                <section class="filters-section col-12 col-md-3" id=aria-label="مرشحات الفواتير">
                    <div class="filter-title">🔍 مرشحات البحث</div>

                    <form method="get" action="<?php echo $current_page_link; ?>" id="filterForm">
                        <div class="filters-grid">
                            <div class="row  ">
                                <div class="col-6 col-md-6 field">
                                    <label for="fInvoice">بحث برقم الفاتورة</label>
                                    <input id="fInvoice" name="invoice_q" type="text" placeholder="مثال: 123" value="<?php echo e($invoice_q); ?>" />
                                </div>

                                <div class="col-6 col-md-6 field">
                                    <label for="fPhone"> برقم هاتف العميل</label>
                                    <input id="fPhone" name="mobile_q" type="text" placeholder="مثال: 01012345678" value="<?php echo e($mobile_q); ?>" />
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-12 field">
                                    <label for="fNotes">بحث حسب الملاحظات</label>
                                    <input id="fNotes" name="notes_q" type="text" placeholder="كلمات من الملاحظات..." value="<?php echo e($notes_q); ?>" />
                                </div>
                            </div>

                            <div class="row">

                                <div class="col-6   field">
                                    <label>من تاريخ</label>
                                    <input id="fFrom" name="date_from" type="date" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>" />
                                </div>

                                <div class="col-6 field">
                                    <label>إلى تاريخ</label>
                                    <input id="fTo" name="date_to" type="date" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>" />
                                </div>
                            </div>
                        </div>

                        <div class="filters-actions">
                            <button type="submit" class="btn apply">تطبيق الفلاتر</button>
                            <a href="<?php echo $current_page_link; ?>" class="btn reset">إعادة</a>
                            <a href="<?php echo $delivered_invoices_link; ?>" class="btn" style="background:var(--teal); color:#fff">عرض الفواتير المستلمة</a>
                        </div>
                    </form>
                </section>

                <!-- CONTENT -->
                <main class="content col-12 col-md-12 col-lg-8" id="contentArea">
                    <!-- كارد الإجماليات -->
                    <div class="top-actions" style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                            <input type="checkbox" id="selectAllInvoices">
                            تحديد الكل
                        </label>
                        <button id="printSelectedInvoices" class="btn" style="background: var(--primary); color: white; padding: 8px 16px;">
                            🖨️ طباعة الفواتير المحددة
                        </button>
                    </div>

                    <div class="list-wrapper">
                        <section id="list" class="list" aria-label="قائمة الفواتير">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php
                                $result->data_seek(0); // إعادة تعيين المؤشر
                                while ($row = $result->fetch_assoc()):
                                    $current_invoice_total_for_row = floatval($row["invoice_total"] ?? 0);
                                    $displayed_invoices_sum += $current_invoice_total_for_row;

                                    // حساب الخصم
                                    $total_before_discount = floatval($row["total_before_discount"] ?? 0);
                                    $total_after_discount = floatval($row["total_after_discount"] ?? 0);
                                    $discount_amount = floatval($row["discount_amount"] ?? 0);
                                    $discount_type = $row["discount_type"] ?? 'percent';
                                    $discount_value = floatval($row["discount_value"] ?? 0);

                                    // إذا كان total_before_discount = 0 أو null، استخدم invoice_total
                                    if ($total_before_discount <= 0) {
                                        $total_before_discount = $current_invoice_total_for_row;
                                    }
                                    if ($total_after_discount <= 0) {
                                        $total_after_discount = $total_before_discount;
                                    }

                                    // التحقق من وجود خصم فعلي
                                    $has_discount = ($discount_amount > 0 && abs($total_after_discount - $total_before_discount) > 0.01);
                                    $final_amount = $has_discount ? $total_after_discount : $total_before_discount;

                                    $noteText = trim((string)($row['notes'] ?? ''));
                                    $noteDisplay = $noteText;
                                    if (mb_strlen($noteDisplay) > 30) {
                                        $noteDisplay = mb_substr($noteDisplay, 0, 30) . '...';
                                    }
                                    $created_date = date('m/d/Y', strtotime($row["created_at"]));
                                ?>
                                    <article class="invoice">
                                        <div class="invoice-left">
                                            <input type="checkbox" class="invoice-checkbox" data-invoice-id=<?php echo e($row["id"]); ?>>

                                            <div class="badge">#<?php echo e($row["id"]); ?></div>
                                            <div class="meta">
                                                <div class="name"><?php echo e($row["customer_name"]); ?></div>
                                                <?php if ($noteDisplay): ?>
                                                    <div class="notes" title="<?php echo e($noteText); ?>"><?php echo e($noteDisplay); ?></div>
                                                <?php endif; ?>
                                                <div class="extra">
                                                    <div class="phone">📞 <?php echo e($row["customer_mobile"]); ?></div>
                                                    <div class="creator">👤 <?php echo e($row["creator_name"] ?? 'غير معروف'); ?></div>
                                                    <div>📅 <?php echo e($created_date); ?></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="invoice-right">
                                            <?php if ($has_discount): ?>
                                                <div class="amount-with-discount">
                                                    <div class="amount-original"><?php echo number_format($total_before_discount, 2); ?> ج.م</div>
                                                    <div class="amount-final"><?php echo number_format($total_after_discount, 2); ?> ج.م</div>
                                                    <div class="discount-badge">
                                                        <?php
                                                        if ($discount_type === 'percent') {
                                                            echo number_format($discount_value, 2) . '% خصم';
                                                        } else {
                                                            echo number_format($discount_amount, 2) . ' ج.م خصم';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="amount"><?php echo number_format($final_amount, 2); ?> ج.م</div>
                                            <?php endif; ?>

                                            <div class="status pending">
                                                مؤجله
                                            </div>

                                            <div class="actions">
                                                <button class="show btn-open-modal" data-invoice-id="<?php echo e($row["id"]); ?>">عرض</button>

                                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                                                    <form action="<?php echo $current_page_link; ?>?<?php echo http_build_query($_GET); ?>" method="post" style="display:inline;">
                                                        <input type="hidden" name="invoice_id_to_deliver" value="<?php echo e($row["id"]); ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                        <button type="submit" name="mark_delivered" class="deliver">تسليم</button>
                                                    </form>

                                                    <button class="cancel btn-cancel-invoice" data-invoice-id="<?php echo e($row['id']); ?>">إلغاء</button>

                                                    <button class="edit btn-edit-items" data-id="<?php echo e($row['id']); ?>">تعديل</button>
                                                    <button class="btn btn-sm btn-outline-secondary btn-return-invoice btn"
                                                        data-invoice-id="<?php echo e($row['id']); ?>"
                                                        title="ارجاع">
                                                        ارجاع
                                                    </button> <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div style="text-align:center;padding:40px;color:var(--muted)">
                                    لا توجد فواتير غير مستلمة حالياً.
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </main>
            </div>
        </div>

        <!-- ======= مودال التفاصيل المحسّن (مضمّن داخل الصفحة ويستخدم endpoint JSON الحالي) ======= -->
        <div id="invoiceModal" class="modal-backdrop" aria-hidden="true" aria-labelledby="modalTitle" role="dialog">
            <div class="modal-card mymodal" role="document" id="invoiceModalCard">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <h4 id="modalTitle">تفاصيل الفاتورة</h4>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <div id="modalTotal" class="fw-bold" style="min-width:160px;text-align:left;"></div>

                        <button id="modalPrintBtn" class="btn btn-secondary btn-sm" title="طباعة"><i class="fas fa-print"></i></button>
                        <form id="modalDeliverForm" method="post" style="display:inline-block;">
                            <input type="hidden" name="invoice_id_to_deliver" id="modal_invoice_id_deliver" value="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="redirect_to" value="pending">
                            <button type="submit" name="mark_delivered" class="btn btn-success" id="modalDeliverBtn"><i class="fas fa-check-circle"></i> تسليم</button>
                        </form>

                        <form id="modalDeleteForm" method="post" style="display:inline-block;" onsubmit="return confirm('تأكيد حذف الفاتورة؟ سيتم إعادة الكميات إذا كانت الفاتورة مستلمة.');">
                            <input type="hidden" name="invoice_out_id_to_delete" id="modal_invoice_id_delete" value="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="redirect_to" value="pending">
                            <!-- <button type="submit" name="delete_sales_invoice" class="btn btn-danger" id="modalDeleteBtn"><i class="fas fa-trash"></i> حذف</button> -->
                        </form>
                        <!-- <br/> -->
                    </div>
                </div>

                <div id="modalContentArea">
                    <!-- سيتم بناء المحتوى هنا بالـ JS من JSON المرسل من endpoint -->
                    <div style="padding:20px;text-align:center;color:#6b7280;">جارٍ التحميل...</div>
                </div>

                <!-- <div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
                <div id="modalActionsLeft" class="ipc-actions">
                    <form id="modalDeliverForm" method="post" style="display:inline-block;">
                        <input type="hidden" name="invoice_id_to_deliver" id="modal_invoice_id_deliver" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="redirect_to" value="pending">
                        <button type="submit" name="mark_delivered" class="btn btn-success" id="modalDeliverBtn"><i class="fas fa-check-circle"></i> تسليم</button>
                    </form>

                    <form id="modalDeleteForm" method="post" style="display:inline-block;" onsubmit="return confirm('تأكيد حذف الفاتورة؟ سيتم إعادة الكميات إذا كانت الفاتورة مستلمة.');">
                        <input type="hidden" name="invoice_out_id_to_delete" id="modal_invoice_id_delete" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="redirect_to" value="pending">
                        <button type="submit" name="delete_sales_invoice" class="btn btn-danger" id="modalDeleteBtn"><i class="fas fa-trash"></i> حذف</button>
                    </form>
                </div>

                <div id="modalTotal" class="fw-bold" style="min-width:160px;text-align:left;"></div>
            </div> -->

                <button id="modalClose" class="text-left mt-4 btn btn-outline-secondary btn-sm">إغلاق</button>

            </div>
        </div>
        <!-- Cancel Modal (ضعه لمرة واحدة في الصفحة) -->
        <div id="cancelInvoiceModal" class="modal-backdrop">
            <div class="mymodal">
                <h3>تأكيد إلغاء الفاتورة</h3>
                <p id="cancelInvoiceInfo">هل تريد حقاً إلغاء الفاتورة <strong id="ci_invoice_id"></strong>؟</p>
                <label for="ci_reason">سبب الإلغاء (مطلوب):</label>
                <textarea id="ci_reason" rows="3" style="width:100%;" required placeholder="اكتب سبب الإلغاء هنا"></textarea>

                <div style="margin-top:12px; text-align:right;">
                    <button id="ci_cancel_btn" class="btn btn-warning" style="margin-right:8px;">إغلاق</button>
                    <button id="ci_confirm_btn" class="btn btn-danger">تأكيد الإلغاء</button>
                </div>
                <div id="ci_feedback" style="margin-top:10px;color:#d00;display:none;"></div>
            </div>
        </div>

        <!-- مودال إرجاع الأصناف (structure مثل اللي طلبته) -->
        <!-- مودال تعديل/ارجاع الفاتورة-->
        <div id="invoiceReturnModal" class="modal-backdrop" style="display:non=e;">
            <div class="mymodal">
                <h3>تعديل/إرجاع بنود الفاتورة</h3>
                <p>الفاتورة: <strong id="ir_invoice_id"></strong></p>

                <div style="max-height:420px; overflow:auto; margin-top:8px;">
                    <table class="custom-table" id="ir_items_table">
                        <thead>
                            <tr>
                                <th style="text-align:right">#</th>
                                <th style="text-align:right">المنتج</th>
                                <th style="text-align:right">الكمية الحالية</th>
                                <th style="text-align:right">الكمية الجديدة</th>
                                <th style="text-align:center">حذف</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <label for="ir_note" style="display:block;margin-top:8px;">ملاحظة (اختياري)</label>
                <textarea id="ir_note" rows="2" style="width:100%"></textarea>

                <div style="margin-top:12px; text-align:right;">
                    <button id="ir_close_btn" class="btn btn-warning" style="margin-right:8px;">إغلاق</button>
                    <button id="ir_confirm_btn" class="btn btn-danger">تطبيق التعديلات</button>
                </div>

                <div id="ir_feedback" style="margin-top:10px; display:none;color:#d00"></div>
            </div>
        </div>
        <div id="returnInvoiceModal" class="modal-backdrop">
            <div class="mymodal" id="returnInvoiceModalInner">
                <h3>إرجاع من الفاتورة — <span id="rim_invoice_no"></span></h3>
                <div id="rim_body">
                    <p>تحميل بيانات...</p>
                </div>

                <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end;">
                    <button id="rim_cancel" class="btn btn-danger">إغلاق</button>
                    <button id="rim_submit" class="btn btn-primary">تنفيذ الإرجاع</button>
                </div>
            </div>
        </div>


        <!-- Button: ضع هذا الزر داخل صف الفاتورة -->

        <!-- Modal container (مخفي) — نستخدمه لعرض المحتوى داخل SweetAlert أو كـ inline modal -->
        <!-- <div id="return-modal-root" style="display:none;"></div> -->


        <!-- ستملىء ديناميكياً -->




        <div id="ipc_toast_holder"></div>
        <!-- زر الارجاع داخل صف الفاتورة -->

        <!-- مودال تعديل/ارجاع الفاتورة (انت طلبت نفس الكلاسات modal_backdrop و mymodal) -->
        <!-- sweetalert2 (كما طلبت) -->

        <!-- Return modal (نفس نمط modal_backdrop الذي تستعمله) -->


        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <!-- الزر (مثال واحد لصف) - ضع مثيلاً داخل حلقة عرض الفواتير -->
        <!--
<button class="btn-return-invoice" data-invoice-id="<?= $inv['id'] ?>">إرجاع</button>
-->


        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            document.addEventListener("click", (e) => {
                if (e.target.classList.contains("btn-edit-items")) {
                    const id = e.target.dataset.id;

                    Swal.fire({
                        title: 'تأكيد الدخول لوضع تعديل البنود',
                        text: 'هل ترغب في تعديل بنود هذه الفاتورة؟',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'نعم، تعديل البنود',
                        cancelButtonText: 'إلغاء'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const redirectBase = (typeof baseUrl !== 'undefined') ? baseUrl : (window.BASE_URL || (location.origin + '/store_v1/'));
                            window.location.href = redirectBase + 'invoices_out/create_invoice.php?mode=edit&id=' + encodeURIComponent(id);
                        }
                    });
                }
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('invoiceModal');
                const modalCard = document.getElementById('invoiceModalCard');
                const modalClose = document.getElementById('modalClose');
                const modalContent = document.getElementById('modalContentArea');
                const modalTotal = document.getElementById('modalTotal');
                const deliverIdInput = document.getElementById('modal_invoice_id_deliver');
                const deleteIdInput = document.getElementById('modal_invoice_id_delete');
                const printBtn = document.getElementById('modalPrintBtn');
                const toastHolder = document.getElementById('ipc_toast_holder');

                const baseUrl = <?php echo json_encode(BASE_URL); ?>;
                const currentQuery = <?php echo json_encode(http_build_query($_GET)); ?>;
                const currentPage = <?php echo json_encode($current_page_link); ?>;

                function showModal() {
                    modal.style.display = 'flex';
                    modal.setAttribute('aria-hidden', 'false');
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }

                function hideModal() {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    modalContent.innerHTML = '';
                    modalTotal.innerText = '';
                    deliverIdInput.value = '';
                    deleteIdInput.value = '';
                }

                modalClose.addEventListener('click', hideModal);
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) hideModal();
                });

                function showToast(msg, type = 'info', ms = 3000) {
                    const t = document.createElement('div');
                    t.className = 'ipc-toast';
                    if (type === 'success') t.style.background = 'linear-gradient(90deg,#10b981,#059669)';
                    if (type === 'error') t.style.background = 'linear-gradient(90deg,#ef4444,#dc2626)';
                    t.innerText = msg;
                    toastHolder.appendChild(t);
                    requestAnimationFrame(() => t.classList.add('show'));
                    setTimeout(() => {
                        t.classList.remove('show');
                        setTimeout(() => t.remove(), 350);
                    }, ms);
                }

                // زر العرض في كل صف
                document.querySelectorAll('.btn-open-modal').forEach(btn => {
                    btn.addEventListener('click', async function() {
                        const invId = parseInt(this.dataset.invoiceId || 0, 10);
                        if (!invId) {
                            showToast('معرف الفاتورة غير صالح', 'error');
                            return;
                        }
                        modalContent.innerHTML = '<div style="padding:30px;text-align:center;color:#6b7280">جارٍ التحميل...</div>';
                        showModal();

                        try {
                            // استخدم endpoint الموجود في أعلى الملف الذي يعيد JSON
                            const url = location.pathname + '?action=fetch_invoice_details&id=' + encodeURIComponent(invId);
                            const res = await fetch(url, {
                                credentials: 'same-origin'
                            });
                            const contentType = res.headers.get('content-type') || '';
                            const txt = await res.text();

                            if (contentType.includes('application/json')) {
                                const data = JSON.parse(txt);
                                if (!data.success) {
                                    showToast(data.message || 'خطأ: لم نتمكن من جلب التفاصيل', 'error');
                                    console.error('server message:', data);
                                    modalContent.innerHTML = '<div style="padding:20px;color:#b91c1c">الفاتورة غير موجودة أو حدث خطأ.</div>';
                                    return;
                                }
                                buildModalFromJson(data.invoice, data.items);
                            } else {
                                // إذا لم يرجع JSON قد يكون خطأ PHP => عرض النص في الـ console
                                console.error('Non-JSON response when fetching invoice:', txt);
                                modalContent.innerHTML = '<div style="padding:20px;color:#b91c1c">استجابة غير متوقعة من السيرفر. افتح Console لرؤية التفاصيل.</div>';
                            }
                        } catch (err) {
                            console.error('fetch error:', err);
                            modalContent.innerHTML = '<div style="padding:20px;color:#b91c1c">خطأ في الاتصال عند جلب تفاصيل الفاتورة.</div>';
                        }
                    });
                });

                function buildModalFromJson(inv, items) {
                    // header
                    const titleHtml = `
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;">
                <div style="flex:1">
                <div style="font-weight:700;font-size:1.05rem">فاتورة مبيعات — <span style="color:var(--bs-primary,#0d6efd)">#${escapeHtml(inv.id)}</span></div>
                <div style="font-size:0.85rem;color:#6b7280">تاريخ الإنشاء: ${escapeHtml(fmt_dt(inv.created_at))}</div>
                </div>
                <div style="text-align:left">
                ${inv.delivered === 'yes' ? '<span style="display:inline-block;padding:6px 12px;border-radius:24px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff">تم الدفع</span>' : '<span style="display:inline-block;padding:6px 12px;border-radius:24px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff">مؤجل</span>'}
                </div>
            </div>
            `;

                    // info cards
                    const infoHtml = `
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:12px;">
                <div style="flex:1;min-width:220px;padding:12px;border-radius:10px;background:var(--card-bg,rgba(0,0,0,0.03))">
                <div style="font-weight:700;margin-bottom:6px">معلومات الفاتورة</div>
                <div><strong>المجموعة:</strong> ${escapeHtml(inv.invoice_group || '—')}</div>
                <div><strong>منشأ الفاتورة:</strong> ${escapeHtml(inv.creator_name || '-')}</div>
                <div><strong>آخر تحديث:</strong> ${escapeHtml(fmt_dt(inv.updated_at || inv.created_at))}</div>
                </div>
                <div style="flex:1;min-width:220px;padding:12px;border-radius:10px;background:var(--card-bg,rgba(0,0,0,0.03))">
                <div style="font-weight:700;margin-bottom:6px">معلومات العميل</div>
                <div><strong>الاسم:</strong> ${escapeHtml(inv.customer_name || 'غير محدد')}</div>
                <div><strong>الموبايل:</strong> ${escapeHtml(inv.customer_mobile || '—')}</div>
                <div><strong>المدينة:</strong> ${escapeHtml(inv.customer_city || '—')}</div>
                </div>
            </div>
            `;

                    // items table
                    let itemsHtml = `<div class="custom-table-wrapper">
    <table class="custom-table">
      <thead class="center">
        <tr>
          <th style="width:40px">#</th>
          <th style="text-align:right;">اسم المنتج</th>
          <th style="text-align:right;">الكمية</th>
          <th style="text-align:right;">سعر الوحدة</th>
          <th style="text-align:right;">الإجمالي</th>
        </tr>
      </thead>
      <tbody>`;
                    let total = 0;
                    if (items && items.length) {
                        items.forEach((it, idx) => {
                            const name = it.product_name ? (it.product_name + (it.product_code ? (' — ' + it.product_code) : '')) : ('#' + it.product_id);
                            const qty = parseFloat(it.quantity || 0).toFixed(2);
                            const price = parseFloat(it.selling_price || it.cost_price_per_unit || 0).toFixed(2);
                            const line = parseFloat(it.total_price || 0).toFixed(2);
                            total += parseFloat(line || 0);

                            itemsHtml += `<tr>
            <td style="padding:10px">${idx+1}</td>
            <td style="padding:10px;text-align:right">${escapeHtml(name)}</td>
            <td style="padding:10px;text-align:right">${qty}</td>
            <td style="padding:10px;text-align:right">${price}</td>
            <td style="padding:10px;text-align:right;font-weight:700">${line} ج.م</td>
        </tr>`;
                        });
                    } else {
                        itemsHtml += `<tr><td colspan="5" style="padding:12px;text-align:center;color:#6b7280">لا يوجد بنود</td></tr>`;
                    }
                    itemsHtml += `</tbody></table></div>`;

                    // حساب الخصم
                    let totalBeforeDiscount = parseFloat(inv.total_before_discount || total);
                    let totalAfterDiscount = parseFloat(inv.total_after_discount || total);
                    const discountAmount = parseFloat(inv.discount_amount || 0);
                    const discountType = inv.discount_type || 'percent';
                    const discountValue = parseFloat(inv.discount_value || 0);

                    // إذا كان total_before_discount = 0 أو null، استخدم total من البنود
                    if (totalBeforeDiscount <= 0) {
                        totalBeforeDiscount = total;
                    }
                    if (totalAfterDiscount <= 0) {
                        totalAfterDiscount = totalBeforeDiscount;
                    }

                    // التحقق من وجود خصم فعلي
                    const hasDiscount = (discountAmount > 0 && Math.abs(totalAfterDiscount - totalBeforeDiscount) > 0.01);

                    // ملخص الإجماليات مع الخصم
                    let summaryHtml = `<div style="margin-top:16px;padding:16px;border-radius:10px;background:rgba(0,0,0,0.02);border-top:2px solid var(--accent,#0d6efd)">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <strong>المجموع قبل الخصم:</strong>
                        <span style="font-weight:700">${totalBeforeDiscount.toFixed(2)} ج.م</span>
                    </div>`;

                    if (hasDiscount) {
                        summaryHtml += `
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;color:#b45309">
                        <strong>الخصم:</strong>
                        <span style="font-weight:700">
                            ${discountType === 'percent' ? discountValue.toFixed(2) + '%' : discountAmount.toFixed(2) + ' ج.م'}
                        </span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:2px solid #e5e7eb">
                        <strong style="font-size:1.1rem">المجموع بعد الخصم:</strong>
                        <span style="font-weight:800;font-size:1.2rem;color:var(--accent,#0d6efd)">${totalAfterDiscount.toFixed(2)} ج.م</span>
                    </div>`;
                    } else {
                        summaryHtml += `
                    <div style="display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:2px solid #e5e7eb">
                        <strong style="font-size:1.1rem">الإجمالي:</strong>
                        <span style="font-weight:800;font-size:1.2rem;color:var(--accent,#0d6efd)">${totalBeforeDiscount.toFixed(2)} ج.م</span>
                    </div>`;
                    }
                    summaryHtml += `</div>`;

                    // notes
                    let notesHtml = '';
                    if (inv.notes && inv.notes.trim() !== '') {
                        notesHtml = `<div style="margin-top:12px;padding:12px;border-radius:8px;background:rgba(0,0,0,0.02)"  class="no-print">
                <div style="font-weight:700;margin-bottom:8px ">ملاحظات</div><div style="white-space:pre-wrap;">${escapeHtml(inv.notes).replace(/\n/g,'<br>')}</div><div style="margin-top:8px"><button class="btn-copy-notes btn btn-outline-secondary btn-sm" data-notes="${escapeHtml(inv.notes)}">نسخ الملاحظات</button></div></div>`;
                    }

                    modalContent.innerHTML = titleHtml + infoHtml + itemsHtml + summaryHtml + notesHtml;

                    // set modal forms values
                    deliverIdInput.value = inv.id;
                    deleteIdInput.value = inv.id;
                    modalTotal.innerText = hasDiscount ?
                        `الإجمالي: ${totalAfterDiscount.toFixed(2)} ج.م (بعد خصم ${discountType === 'percent' ? discountValue.toFixed(2) + '%' : discountAmount.toFixed(2) + ' ج.م'})` :
                        `الإجمالي: ${totalBeforeDiscount.toFixed(2)} ج.م`;

                    // attach copy notes handler if present
                    const copyBtn = modalContent.querySelector('.btn-copy-notes');
                    if (copyBtn) {
                        copyBtn.addEventListener('click', function() {
                            const notes = this.dataset.notes || '';
                            if (!notes) return showToast('لا توجد ملاحظات للنسخ', 'error');
                            navigator.clipboard?.writeText(notes).then(() => showToast('تم نسخ الملاحظات', 'success')).catch(() => {
                                alert('نسخ فشل');
                            });
                        });
                    }

                    showModal();
                }

                // طباعة المودال (يطبع المحتوى الداخلي فقط)


                // utility funcs
                function escapeHtml(s) {
                    if (s === null || s === undefined) return '';
                    return String(s).replace(/[&<>"']/g, function(m) {
                        return {
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#39;'
                        } [m];
                    });
                }

                function fmt_dt(raw) {
                    if (!raw) return '—';
                    try {
                        const d = new Date(raw);
                        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' + d.toLocaleTimeString();
                    } catch (e) {
                        return raw;
                    }
                }

                // expose open function
                window.openInvoiceModal = function(id) {
                    const btn = document.querySelector('.btn-open-modal[data-invoice-id="' + id + '"]');
                    if (btn) btn.click();
                };


                const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;




                (function() {
                    const modal = document.getElementById('cancelInvoiceModal');
                    const info = document.getElementById('ci_invoice_id');
                    const reasonInput = document.getElementById('ci_reason');
                    const feedback = document.getElementById('ci_feedback');
                    const btnClose = document.getElementById('ci_cancel_btn');
                    const btnConfirm = document.getElementById('ci_confirm_btn');
                    let currentInvoiceId = null;

                    // delegate click on cancel buttons
                    document.addEventListener('click', function(e) {
                        if (e.target && e.target.classList.contains('btn-cancel-invoice')) {
                            currentInvoiceId = e.target.dataset.invoiceId;
                            info.textContent = currentInvoiceId;
                            reasonInput.value = '';
                            feedback.style.display = 'none';
                            modal.style.display = 'flex';
                        }
                    });

                    btnClose.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });

                    btnConfirm.addEventListener('click', function() {
                        feedback.style.display = 'none';

                        // validation on client: reason is required
                        const reasonTrim = (reasonInput.value || '').trim();
                        if (!reasonTrim) {
                            feedback.style.display = 'block';
                            feedback.textContent = 'حقل السبب مطلوب. من فضلك اشرح سبب الإلغاء.';
                            reasonInput.focus();
                            return;
                        }
                        btnConfirm.disabled = true;
                        btnConfirm.textContent = 'جارٍ الإلغاء...';

                        const fd = new FormData();
                        fd.append('action', 'cancel_invoice');
                        fd.append('invoice_id', currentInvoiceId);
                        fd.append('csrf_token', CSRF_TOKEN);
                        fd.append('reason', reasonInput.value || '');

                        fetch(window.location.href, {
                                method: 'POST',
                                body: fd,
                                credentials: 'same-origin'
                            })
                            .then(r => r.json())
                            .then(json => {
                                btnConfirm.disabled = false;
                                btnConfirm.textContent = 'تأكيد الإلغاء';
                                if (json.success) {
                                    // إغلاق المودال وإعلام المستخدم
                                    modal.style.display = 'none';
                                    alert(json.message || 'تم الإلغاء');
                                    window.location.reload()

                                    // تحديث الواجهة: ابحث عن صف الفاتورة وقم بتغيير عمود delivered إلى 'canceled' أو أحذفه
                                    const btn = document.querySelector('.btn-cancel-invoice[data-invoice-id="' + currentInvoiceId + '"]');
                                    if (btn) {
                                        const row = btn.closest('tr');
                                        if (row) {
                                            // مثال: تغيير خلية delivered (ابحث فيها حسب بنية الجدول)
                                            const deliveredCell = row.querySelector('.cell-delivered');
                                            if (deliveredCell) {
                                                deliveredCell.textContent = 'canceled'; // أو 'ملغاة' حسب الترجمة
                                            }
                                            // تعطيل الزر
                                            btn.disabled = true;
                                        }
                                    }
                                } else {
                                    feedback.style.display = 'block';
                                    feedback.textContent = json.error || 'حدث خطأ أثناء الإلغاء.';
                                }
                            })
                            .catch(err => {
                                btnConfirm.disabled = false;
                                btnConfirm.textContent = 'تأكيد الإلغاء';
                                feedback.style.display = 'block';
                                feedback.textContent = 'خطأ في الاتصال.';
                                console.error(err);
                            });
                    });

                    // إغلاق المودال عند الضغط خارج الصندوق (اختياري)
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) modal.style.display = 'none';
                    });

                })();


                // Open Invoice Return modal when clicking the invoice-level return button
                // Open Invoice Return modal when clicking the invoice-level return button
                document.addEventListener('click', async function(e) {
                    if (e.target && e.target.classList.contains('btn-return-invoice')) {
                        const invoiceId = e.target.dataset.invoiceId;
                        if (!invoiceId) return;

                        const modal = document.getElementById('invoiceReturnModal');
                        const feedback = document.getElementById('ir_feedback');
                        feedback.style.display = 'none';
                        document.getElementById('ir_note').value = '';

                        // fetch invoice items
                        try {
                            const res = await fetch(location.pathname + '?action=get_invoice_return_info&invoice_id=' + encodeURIComponent(invoiceId), {
                                credentials: 'same-origin'
                            });
                            const j = await res.json();
                            if (!j.success) {
                                feedback.style.display = 'block';
                                feedback.textContent = j.message || 'فشل في جلب بنود الفاتورة';
                                return;
                            }

                            const items = j.items || [];
                            const tbody = document.querySelector('#ir_items_table tbody');
                            tbody.innerHTML = '';
                            items.forEach((it, idx) => {
                                const name = (it.product_name || '') + (it.product_code ? (' — ' + it.product_code) : '');
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                    <td style="text-align:right">${idx+1}</td>
                    <td style="text-align:right">${escapeHtml(name)}</td>
                    <td style="text-align:right">${it.quantity}</td>
                    <td style="text-align:right">
                        <input type="number" class="ir_new_qty" data-item-id="${it.id}" min="0" step="0.01" value="${it.quantity}" style="width:110px;padding:6px;text-align:right" />
                    </td>
                    <td style="text-align:center">
                        <button type="button" class="btn btn-sm btn-outline-danger ir_delete_btn" data-item-id="${it.id}">حذف</button>
                    </td>
                `;
                                tbody.appendChild(tr);
                            });

                            document.getElementById('ir_invoice_id').textContent = invoiceId;
                            modal.style.display = 'flex';
                        } catch (err) {
                            feedback.style.display = 'block';
                            feedback.textContent = 'خطأ في الاتصال';
                            console.error(err);
                        }
                    }
                });

                // helper: simple escape
                function escapeHtml(s) {
                    return String(s || '').replace(/[&<>"']/g, function(m) {
                        return ({
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#39;'
                        })[m];
                    });
                }

                // Delete button: set qty to 0 visually
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('ir_delete_btn')) {
                        const id = e.target.dataset.itemId;
                        const input = document.querySelector('.ir_new_qty[data-item-id="' + id + '"]');
                        if (input) {
                            input.value = 0;
                            input.classList.add('marked-for-delete');
                        }
                    }
                });

                // Close modal
                document.addEventListener('click', function(e) {
                    if (e.target && e.target.id === 'ir_close_btn') {
                        document.getElementById('invoiceReturnModal').style.display = 'none';
                    }
                });

                // Confirm and send changes
                document.addEventListener('click', async function(e) {
                    if (e.target && e.target.id === 'ir_confirm_btn') {
                        const modal = document.getElementById('invoiceReturnModal');
                        const feedback = document.getElementById('ir_feedback');
                        feedback.style.display = 'none';

                        const invoiceId = document.getElementById('ir_invoice_id').textContent;
                        if (!invoiceId) {
                            feedback.style.display = 'block';
                            feedback.textContent = 'خطأ: رقم الفاتورة غير موجود';
                            return;
                        }

                        // collect changes
                        const inputs = Array.from(document.querySelectorAll('.ir_new_qty'));
                        const items = [];
                        inputs.forEach(inp => {
                            const itemId = inp.dataset.itemId;
                            const newQty = parseFloat(inp.value || '0');
                            const origQty = parseFloat(inp.getAttribute('value') || '0'); // initial value attribute
                            // include item if changed (or zero)
                            if (isNaN(newQty)) return;
                            if (Math.abs(newQty - origQty) > 1e-9) {
                                items.push({
                                    item_id: parseInt(itemId, 10),
                                    new_qty: newQty
                                });
                            }
                        });

                        if (items.length === 0) {
                            feedback.style.display = 'block';
                            feedback.textContent = 'لا تغييرات تم إدخالها';
                            return;
                        }

                        // prepare POST
                        const fd = new FormData();
                        fd.append('action', 'return_invoice_items');
                        fd.append('invoice_id', invoiceId);
                        fd.append('items', JSON.stringify(items));
                        fd.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');

                        e.target.disabled = true;
                        e.target.textContent = 'جارٍ التطبيق...';
                        try {
                            const resp = await fetch(location.pathname, {
                                method: 'POST',
                                body: fd,
                                credentials: 'same-origin'
                            });
                            const j = await resp.json();
                            if (j.success) {
                                // نجاح: اغلاق المودال واعادة تحميل لعرض التغييرات
                                modal.style.display = 'none';
                                // يمكنك اختيار تحديث جزئي بدلاً من reload
                                window.location.reload();
                            } else {
                                feedback.style.display = 'block';
                                feedback.textContent = j.message || 'فشل تطبيق التعديلات';
                            }
                        } catch (err) {
                            feedback.style.display = 'block';
                            feedback.textContent = 'خطأ في الاتصال';
                            console.error(err);
                        } finally {
                            e.target.disabled = false;
                            e.target.textContent = 'تطبيق التعديلات';
                        }
                    }
                });




            });
        </script>


        <script>
            const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

            (function() {
                // عناصر DOM
                const modal = document.getElementById('returnInvoiceModal');
                const modalBody = document.getElementById('rim_body');
                const invoiceNoSpan = document.getElementById('rim_invoice_no');
                const btnCancel = document.getElementById('rim_cancel');
                const btnSubmit = document.getElementById('rim_submit');
                let currentInvoiceId = 0;
                let originalItems = []; // array of objects { invoice_item_id, product_id, product_name, qty_sold }
                // دالة بسيطة لقراءة التوكن من الميتا
                function readCsrfTokenFromPage() {
                    const m = document.querySelector('meta[name="csrf_token"]');
                    if (m) return m.getAttribute('content') || '';
                    return (window.csrf_token || '');
                }

                // قبل بناء FormData

                // send request with credentials so cookie (PHPSESSID) يروح


                // فتح المودال: يتم تحميل بنود الفاتورة عبر AJAX (endpoint بسيط يعيد JSON ببنود الفاتورة)
                async function openReturnModal(invoiceId) {
                    currentInvoiceId = invoiceId;
                    invoiceNoSpan.textContent = invoiceId;
                    modalBody.innerHTML = '<p>جاري جلب بنود الفاتورة...</p>';
                    modal.style.display = 'flex';

                    try {
                        const csrf = document.querySelector('meta[name="csrf_token"]')?.content || window.csrf_token || '';

                        const resp = await fetch('pending_invoices.php?action=get_invoice_items&invoice_id=' + encodeURIComponent(invoiceId), {
                            credentials: 'same-origin'
                        });
                        const data = await resp.json();
                        if (!data.success) {
                            modalBody.innerHTML = `<div class="alert alert-danger">${data.error || 'خطأ في جلب بنود الفاتورة'}</div>`;
                            return;
                        }
                        originalItems = data.items; // expected array
                        renderItemsTable();
                    } catch (err) {
                        modalBody.innerHTML = '<div class="alert alert-danger">خطأ في الاتصال.</div>';
                        console.error(err);
                    }
                }

                function renderItemsTable() {
                    if (!originalItems || originalItems.length === 0) {
                        modalBody.innerHTML = '<p>لا توجد بنود.</p>';
                        return;
                    }

                    // build table
                    let html = `<table class="custom-table" id="rim_items_table">
      <thead><tr><th>المنتج</th><th>كمية مباعة</th><th>كمية لإرجاع</th><th>إجراء</th></tr></thead>
      <tbody>`;
                    originalItems.forEach(it => {
                        // each item must include invoice_item_id, product_id, name, qty
                        html += `<tr data-invoice-item-id="${it.invoice_item_id}">
        <td>${escapeHtml(it.name)}</td>
        <td>${it.qty}</td>
        <td><input class="rim-qty-input" type="number" min="0" max="${it.qty}" step="0.01" value="0" data-max="${it.qty}"></td>
        <td><button class="rim-delete-btn btn btn-danger text-white"   title="حذف البند">حذف</button></td>
      </tr>`;
                    });
                    html += `</tbody></table>`;
                    modalBody.innerHTML = html;

                    // attach handlers
                    modalBody.querySelectorAll('.rim-delete-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const tr = e.target.closest('tr');
                            const iid = parseInt(tr.dataset.invoiceItemId || tr.getAttribute('data-invoice-item-id'), 10);
                            handleDeleteItemClick(iid, tr);
                        });
                    });
                }

                function handleDeleteItemClick(invoiceItemId, trElem) {
                    // if invoice contains only 1 item, show message: cancel invoice instead
                    if (originalItems.length === 1) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'لا يمكن حذف بند وحيد',
                            text: 'الفاتورة تحتوي على بند واحد فقط. لإزالة كل البنود يرجى إلغاء الفاتورة بدلاً من حذف البند.',
                        });
                        return;
                    }
                    // confirmation
                    Swal.fire({
                        title: 'تأكيد حذف البند',
                        text: 'هل تريد حذف هذا البند بالكامل واستعادة كمياته إلى الدفعات؟',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'نعم حذف واستعادة',
                        cancelButtonText: 'إلغاء'
                    }).then(result => {
                        if (result.isConfirmed) {
                            // set return input to max (simulate full remove) and mark row with data-delete="1"
                            const input = trElem.querySelector('.rim-qty-input');
                            input.value = input.dataset.max || input.max || input.getAttribute('max') || 0;
                            trElem.dataset.toDelete = '1';
                            trElem.style.opacity = '0.6';
                        }
                    });
                }

                // helper escape
                function escapeHtml(s) {
                    return String(s).replace(/[&<>"']/g, function(m) {
                        return {
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#39;'
                        } [m];
                    });
                }

                // close
                btnCancel.addEventListener('click', () => {
                    modal.style.display = 'none';
                });
                // submit handler
                btnSubmit.addEventListener('click', async () => {
                    // gather requested returns
                    const rows = Array.from(modalBody.querySelectorAll('tbody tr'));
                    const payloadItems = [];
                    let totalReturnQty = 0;
                    for (const r of rows) {
                        const iid = parseInt(r.dataset.invoiceItemId, 10);
                        const inp = r.querySelector('.rim-qty-input');
                        const q = parseFloat(inp.value || 0);
                        const max = parseFloat(inp.dataset.max || 0);
                        if (isNaN(q) || q < 0) {
                            Swal.fire('قيمة غير صحيحة', 'أدخل قيمة صالحة للكمية', 'error');
                            return;
                        }
                        if (q > max) {
                            Swal.fire('الكمية أكبر من المسموح', 'حاول إرجاع أقل أو تواصل مع الدعم', 'error');
                            return;
                        }
                        if (q > 0) {
                            payloadItems.push({
                                invoice_item_id: iid,
                                qty: q,
                                delete: r.dataset.toDelete === '1' ? 1 : 0
                            });
                            totalReturnQty += q;
                        }
                    }

                    if (payloadItems.length === 0) {
                        Swal.fire('لا شيء للإرجاع', 'حدد كمية أو اضغط إلغاء', 'info');
                        return;
                    }

                    // if the invoice has only 1 item, prevent full return (server also enforces)
                    if (originalItems.length === 1) {
                        const only = originalItems[0];
                        // if user tries to return equal to sold qty for that single item -> forbid
                        if (payloadItems.length === 1 && Math.abs(payloadItems[0].qty - only.qty) < 1e-9) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'لا يمكن إرجاع الكمية كلها',
                                text: 'الفاتورة تحتوي على بند واحد فقط. لإلغاء الفاتورة استخدم خيار إلغاء الفاتورة.',
                            });
                            return;
                        }
                    }

                    // confirm
                    const confirm = await Swal.fire({
                        title: 'تأكيد تنفيذ الإرجاع',
                        html: `سيتم استعادة مجموع <b>${totalReturnQty}</b> وحدة(وحدات). هل تتابع؟`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'نعم نفّذ الإرجاع',
                        cancelButtonText: 'إلغاء'
                    });
                    if (!confirm.isConfirmed) return;

                    // send to server
                    try {
                        // build form data
                        const fd = new FormData();
                        fd.append('action', 'process_return');
                        fd.append('invoice_id', currentInvoiceId);
                        // include CSRF token present on page as meta[name="csrf"] or a hidden field (adjust selector if different)
                        fd.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');

                        fd.append('items', JSON.stringify(payloadItems));

                        const r = await fetch('pending_invoices.php', {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: fd
                        });
                        const resp = await r.json();
                        if (!resp.success) {
                            Swal.fire('خطأ', resp.error || 'فشل تنفيذ الإرجاع', 'error');
                            return;
                        }
                        Swal.fire('تم بنجاح', resp.message || 'تمت عملية الإرجاع بنجاح', 'success');
                        modal.style.display = 'none';
                        // optional: تحديث السطر في الجدول (reload الصفحة أو تحديث جزئي)
                        setTimeout(() => location.reload(), 800);
                    } catch (err) {
                        console.error(err);
                        Swal.fire('خطأ اتصال', 'تعذر الاتصال بالخادم', 'error');
                    }
                });

                // delegate buttons (افتراض أن الزر يملك class .btn-return-invoice)
                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('.btn-return-invoice');
                    if (!btn) return;
                    const invoiceId = btn.dataset.invoiceId || btn.getAttribute('data-invoice-id');
                    if (!invoiceId) {
                        Swal.fire('خطأ', 'معرف الفاتورة غير موجود في الزر', 'error');
                        return;
                    }
                    openReturnModal(invoiceId);
                });

            })();
        </script>

        <!-- Live Search & Filter Reset Script -->
        <script>
            (function() {
                'use strict';

                const filterForm = document.getElementById('filterForm');
                const filterInputs = {
                    invoice_q: document.getElementById('fInvoice'),
                    mobile_q: document.getElementById('fPhone'),
                    notes_q: document.getElementById('fNotes'),
                    date_from: document.getElementById('fFrom'),
                    date_to: document.getElementById('fTo')
                };

                const listWrapper = document.querySelector('.pending-invoices-page .list-wrapper');
                const contentArea = document.getElementById('contentArea');
                const resetBtn = document.querySelector('.pending-invoices-page .btn.reset');

                // 1. إعادة الفلاتر للوضع الافتراضي عند refresh (إذا لم تكن هناك query params)
                // ملاحظة: القيم يتم جلبها من PHP، لكن إذا كان URL نظيف سنمسحها
                const urlParams = new URLSearchParams(window.location.search);
                const hasFilters = Array.from(urlParams.keys()).some(key => ['invoice_q', 'mobile_q', 'notes_q', 'date_from', 'date_to', 'filter_group_val', 'customer_id'].includes(key));

                // إذا لم تكن هناك فلاتر في URL، تأكد من أن القيم فارغة
                if (!hasFilters) {
                    Object.keys(filterInputs).forEach(key => {
                        if (filterInputs[key] && filterInputs[key].value) {
                            // فقط إذا كانت القيمة موجودة لكن غير موجودة في URL
                            filterInputs[key].value = '';
                        }
                    });
                }

                // 2. Live Search مع debounce
                let searchTimeout = null;
                const debounceDelay = 500; // 500ms تأخير

                function performLiveSearch() {
                    const params = new URLSearchParams();
                    params.append('action', 'fetch_invoices_list');

                    Object.keys(filterInputs).forEach(key => {
                        const input = filterInputs[key];
                        if (input && input.value && input.value.trim() !== '') {
                            params.append(key, input.value.trim());
                        }
                    });

                    const queryString = params.toString();
                    const url = window.location.pathname + '?' + queryString;

                    // تحديث URL بدون reload (لكن بدون action param)
                    const urlParams = new URLSearchParams();
                    Object.keys(filterInputs).forEach(key => {
                        const input = filterInputs[key];
                        if (input && input.value && input.value.trim() !== '') {
                            urlParams.append(key, input.value.trim());
                        }
                    });
                    const cleanUrl = urlParams.toString() ?
                        window.location.pathname + '?' + urlParams.toString() :
                        window.location.pathname;
                    window.history.pushState({}, '', cleanUrl);

                    // إظهار loading
                    const listSection = listWrapper ? listWrapper.querySelector('.list') : null;
                    if (listSection) {
                        listSection.innerHTML = '<div style="padding:40px;text-align:center;color:var(--muted)">جاري البحث...</div>';
                    } else if (listWrapper) {
                        listWrapper.innerHTML = '<div style="padding:40px;text-align:center;color:var(--muted)">جاري البحث...</div>';
                    }

                    // جلب البيانات من AJAX endpoint
                    fetch(url, {
                            method: 'GET',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message || 'فشل البحث');
                            }

                            // تحديث قائمة الفواتير
                            if (listSection) {
                                listSection.innerHTML = data.html || '';
                            } else if (listWrapper) {
                                const list = listWrapper.querySelector('.list');
                                if (list) {
                                    list.innerHTML = data.html || '';
                                } else {
                                    listWrapper.innerHTML = '<section class="list">' + (data.html || '') + '</section>';
                                }
                            }

                            // تحديث الإحصائيات
                            const countStat = document.querySelector('.pending-invoices-page .stat .num');
                            if (countStat && data.count !== undefined) {
                                countStat.textContent = data.count;
                            }

                            // تحديث الإجمالي
                            const summaryCard = document.querySelector('.pending-invoices-page .summary-card .value');
                            if (summaryCard && data.total_after_discount !== undefined) {
                                summaryCard.textContent = parseFloat(data.total_after_discount).toFixed(2) + ' ج.م';
                            }

                            // إعادة ربط event listeners للأزرار الجديدة
                            reattachEventListeners();
                        })
                        .catch(error => {
                            console.error('خطأ في البحث:', error);
                            if (listSection) {
                                listSection.innerHTML = '<div style="padding:40px;text-align:center;color:#dc2626">حدث خطأ أثناء البحث. يرجى تحديث الصفحة.</div>';
                            } else if (listWrapper) {
                                listWrapper.innerHTML = '<div style="padding:40px;text-align:center;color:#dc2626">حدث خطأ أثناء البحث. يرجى تحديث الصفحة.</div>';
                            }
                        });
                }

                // إضافة event listeners للبحث المباشر
                Object.keys(filterInputs).forEach(key => {
                    const input = filterInputs[key];
                    if (input) {
                        input.addEventListener('input', function() {
                            clearTimeout(searchTimeout);
                            searchTimeout = setTimeout(performLiveSearch, debounceDelay);
                        });

                        // للتواريخ، استخدم change بدلاً من input
                        if (input.type === 'date') {
                            input.addEventListener('change', function() {
                                clearTimeout(searchTimeout);
                                searchTimeout = setTimeout(performLiveSearch, debounceDelay);
                            });
                        }
                    }
                });

                // 3. زر إعادة التعيين
                if (resetBtn) {
                    resetBtn.addEventListener('click', function(e) {
                        e.preventDefault();

                        // مسح جميع القيم
                        Object.keys(filterInputs).forEach(key => {
                            if (filterInputs[key]) {
                                filterInputs[key].value = '';
                            }
                        });

                        // إعادة التوجيه بدون query params
                        window.location.href = window.location.pathname;
                    });
                }

                // 4. إعادة ربط event listeners بعد تحديث DOM
                function reattachEventListeners() {
                    // إعادة ربط أزرار العرض
                    document.querySelectorAll('.pending-invoices-page .btn-open-modal').forEach(btn => {
                        btn.removeEventListener('click', handleOpenModal);
                        btn.addEventListener('click', handleOpenModal);
                    });

                    // إعادة ربط أزرار التعديل
                    document.querySelectorAll('.pending-invoices-page .btn-edit-items').forEach(btn => {
                        btn.removeEventListener('click', handleEditItems);
                        btn.addEventListener('click', handleEditItems);
                    });

                    // إعادة ربط أزرار الإلغاء
                    document.querySelectorAll('.pending-invoices-page .btn-cancel-invoice').forEach(btn => {
                        btn.removeEventListener('click', handleCancelInvoice);
                        btn.addEventListener('click', handleCancelInvoice);
                    });
                }

                // Handlers للأزرار
                function handleOpenModal(e) {
                    const invId = parseInt(this.dataset.invoiceId || 0, 10);
                    if (invId && window.openInvoiceModal) {
                        window.openInvoiceModal(invId);
                    }
                }

                function handleEditItems(e) {
                    const id = this.dataset.id;
                    if (id) {
                        Swal.fire({
                            title: 'تأكيد الدخول لوضع تعديل البنود',
                            text: 'هل ترغب في تعديل بنود هذه الفاتورة؟',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'نعم، تعديل البنود',
                            cancelButtonText: 'إلغاء'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                const redirectBase = (typeof baseUrl !== 'undefined') ? baseUrl : (window.BASE_URL || (location.origin + '/store_v1/'));
                                window.location.href = redirectBase + 'invoices_out/create_invoice.php?mode=edit&id=' + encodeURIComponent(id);
                            }
                        });
                    }
                }

                function handleCancelInvoice(e) {
                    const invoiceId = this.dataset.invoiceId;
                    if (invoiceId && window.dispatchEvent) {
                        const event = new CustomEvent('cancelInvoice', {
                            detail: {
                                invoiceId
                            }
                        });
                        document.dispatchEvent(event);
                    }
                }

                // منع submit للـ form (لأننا نستخدم live search)
                if (filterForm) {
                    filterForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        performLiveSearch();
                    });
                }

                // التأكد من أن scroll يعمل فقط داخل list-wrapper
                if (listWrapper) {
                    listWrapper.style.overflowY = 'auto';
                    listWrapper.style.overflowX = 'hidden';
                }

            })();
        </script>
        <!-- في قسم الـ script، استبدل دالة الطباعة الحالية بهذا الكود -->

        <script>
            const printBtn = document.getElementById('modalPrintBtn');
            const deliverIdInput = document.getElementById('modal_invoice_id_deliver');

            function printPOSReceipt(invoice, items) {
                const printWindow = window.open('', '_blank', 'width=300,height=600');
                const receiptContent = generatePOSReceiptHTML(invoice, items);

                printWindow.document.write(`
                <!DOCTYPE html>
                <html dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>فاتورة مبيعات</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    body { 
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 14px;
                        font-weight: bold;
                        width: 72mm;
                        margin: 0 auto;
                        padding: 1px 3px;
                        line-height: 1.2;
                        background: white;
                        color: #000;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .receipt-container {
                        border: 2px solid #000;
                        padding: 8px;
                        background: white;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 12px;
                        padding-bottom: 8px;
                    }
                    .company-name {
                        font-weight: 900;
                        font-size: 18px;
                        margin-bottom: 4px;
                        letter-spacing: 0.5px;
                    }
                    .store-info {
                        font-weight: bold;
                        font-size: 12px;
                        margin: 3px 0;
                    }
                    .invoice-title {
                        font-weight: 900;
                        font-size: 16px;
                        margin: 8px 0;
                        text-decoration: underline;
                    }
                    .invoice-info {
                        margin: 6px 0;
                        display: flex;
                        justify-content: space-between;
                        font-weight: bold;
                        padding: 3px 0;
                    }
                    .customer-info {
                        background: #f0f0f0;
                        padding: 6px;
                        margin: 6px 0;
                        font-weight: bold;
                    }
                  /* --- ضع هذا في الـ <style> داخل صفحة الطباعة --- */
.items-section {
    margin: 10px 0;
    font-weight: bold;
    font-size: 12px; /* غيّر لو حابب أصغر */
}

/* رأس الجدول */
.items-header {
    display: grid;
    grid-template-columns: 1fr 50px 60px 60px; /* اسم - كمية - سعر - مجموع */
    gap: 4px;
    align-items: center;
    font-weight: 900;
    padding: 6px 0;
    border-bottom: 2px solid #000;
    border-top: 2px solid #000;
    margin-bottom: 5px;
}

/* صف العنصر */
.item-row {
    display: grid;
    grid-template-columns: 1fr 50px 60px 60px; /* نفس التخطيط */
    gap: 4px;
    align-items: center;
    padding: 6px 0;
    margin: 3px 0;
    border-bottom: 1px dashed #333;
    font-weight: bold;
}

/* تنسيقات الأعمدة */
.item-name {
    text-align: right; /* اسم المنتج على اليمين (لغة عربية) */
    padding-right: 6px;
    overflow: hidden;
   break-word: break-word;
    font-weight: bold;
}



/* إذا حابب تقلل الحجم بشكل إضافي عند طباعة */
@media print {
    body { font-size: 12px; }
    .items-header, .item-row { font-size: 12px; }
}

                    .subtotal {
                        border-bottom: 1px solid #000;
                    }
                    .discount-row {
                        color: #d00;
                        font-weight: 900;
                    }
                    .final-total {
                        font-weight: 900;
                        font-size: 16px;
                
                        color: black;
                        padding: 8px;
                        margin-top: 8px;
                        text-align: center;
                    }
                
                    .footer {
                        text-align: center;
                        margin-top: 15px;
                        padding-top: 10px;
                        border-top: 2px solid #000;
                        font-weight: bold;
                    }
                    .thank-you {
                        font-weight: 900;
                        font-size: 14px;
                        margin: 8px 0;
                    }
                    .staff-info {
                
                        color: black;
                        padding: 5px;
                        margin: 5px 0;
                        font-weight: bold;
                    }
                    .print-date {
                        font-weight: bold;
                        margin: 4px 0;
                    }
                    
                
                </style>
            </head>
            <body>
                <div class="receipt-container">
                    ${receiptContent}
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 1000);
                    };
                <\/script>
            </body>
            </html>
        `);

                printWindow.document.close();
            }

            // دالة توليد محتوى الإيصال المحسن
            function generatePOSReceiptHTML(invoice, items) {
                const totalBeforeDiscount = parseFloat(invoice.total_before_discount || 0);
                const totalAfterDiscount = parseFloat(invoice.total_after_discount || 0);
                const discountAmount = parseFloat(invoice.discount_amount || 0);
                const discountType = invoice.discount_type || 'percent';
                const discountValue = parseFloat(invoice.discount_value || 0);

                let itemsTotal = 0;
                items.forEach(item => {
                    itemsTotal += parseFloat(item.total_price || 0);
                });

                const finalTotal = totalAfterDiscount > 0 ? totalAfterDiscount : (totalBeforeDiscount > 0 ? totalBeforeDiscount : itemsTotal);
                const hasDiscount = discountAmount > 0;

                // <div class="header">
                //     <div class="company-name">${escapeHtml(invoice.store_name || 'متجرنا')}</div>
                //     <div class="store-info">${escapeHtml(invoice.store_address || 'عنوان المتجر')}</div>
                //     <div class="store-info">هاتف: ${escapeHtml(invoice.store_phone || '01xxxxxxxx')}</div>
                // </div>
                return `
            
            <div class="invoice-title">فاتورة مبيعات</div>
            
            <div class="invoice-info">
                <span>الفاتورة: #${escapeHtml(invoice.id)}</span>
                <span>${escapeHtml(formatDate(invoice.created_at))}</span>
            </div>
            
            <div class="customer-info">
                <div>العميل: ${escapeHtml(invoice.customer_name || 'نقدي')}</div>
                ${invoice.customer_mobile ? `<div>هاتف: ${escapeHtml(invoice.customer_mobile)}</div>` : ''}
            </div>
            
            <div class="double-separator"></div>
            
        <div class="items-section">
        <div class="items-header">
            <div class="item-name">المنتج</div>
            <div class="item-qty">الكمية</div>
            <div class="item-price">السعر</div>
            <div class="item-total">المجموع</div>
        </div>
        
${items.map((item, index) => {
    const productName = item.product_name || 'منتج #' + item.product_id;
    const quantity = Number(item.quantity || 0);
    const price = Number(item.selling_price || 0);
    const total = Number(item.total_price || (quantity * price || 0));

    // صيغة الأرقام: عدّل toFixed حسب ما تحب (0 أو 2)
    const qtyStr = quantity % 1 === 0 ? quantity.toFixed(0) : quantity.toFixed(2);
    const priceStr = price.toFixed(2);   // لو تفضل بدون كسور ضع toFixed(0)
    const totalStr = total.toFixed(2);

    return `
        <div class="item-row">
            <div class="item-name">${escapeHtml(productName)}</div>
            <div class="item-qty">${escapeHtml(qtyStr)}</div>
            <div class="item-price">${escapeHtml(priceStr)}</div>
            <div class="item-total">${escapeHtml(totalStr)}</div>
        </div>
    `;
}).join('')}

    </div>
            
            <div class="separator"></div>
            
            <div class="totals-section">
                ${totalBeforeDiscount > 0 ? `
                    <div class="total-row subtotal">
                        <span>المجموع الفرعي:</span>
                        <span>${totalBeforeDiscount.toFixed(2)} ج.م</span>
                    </div>
                ` : ''}
                
                ${hasDiscount ? `
                    <div class="total-row discount-row">
                        <span>الخصم:</span>
                        <span>${discountType === 'percent' ? discountValue.toFixed(2) + '%' : discountAmount.toFixed(2) + ' ج.م'}</span>
                    </div>
                ` : ''}
                
                <div class="final-total">
                    <span>الإجمالي النهائي: ${finalTotal.toFixed(2)} ج.م</span>
                </div>
            </div>
            
            ${invoice.notes ? `
                <div class="notes-section">
                    <div style="font-weight: 900; margin-bottom: 5px;">ملاحظات:</div>
                    <div>${escapeHtml(invoice.notes)}</div>
                </div>
            ` : ''}
            
            <div class="footer">
                <div class="thank-you">شكراً لزيارتكم</div>
                <div class="staff-info">
                    <div>المستخدم: ${escapeHtml(invoice.creator_name || 'النظام')}</div>
                </div>
                <div class="print-date">${new Date().toLocaleString('ar-EG', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                })}</div>
                <div style="margin-top: 8px; font-weight: 900;">نتمنى لكم يومًا سعيداً</div>
            </div>
        `;
            }

            // دالة مساعدة لتنسيق التاريخ
            function formatDate(dateString) {
                if (!dateString) return '--';
                try {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('ar-EG') + ' ' +
                        date.toLocaleTimeString('ar-EG', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                } catch (e) {
                    return dateString;
                }
            }

            // تحديث event listener للطباعة
            printBtn.addEventListener('click', function() {
                const invoiceId = deliverIdInput.value;
                if (!invoiceId) {
                    alert('خطأ: لا يوجد معرف فاتورة');
                    return;
                }

                // إظهار رسالة تحميل
                const originalText = printBtn.innerHTML;
                printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الطباعة...';
                printBtn.disabled = true;

                fetch(location.pathname + '?action=fetch_invoice_details&id=' + encodeURIComponent(invoiceId), {
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            printPOSReceipt(data.invoice, data.items);
                        } else {
                            alert('خطأ في جلب بيانات الفاتورة للطباعة: ' + (data.message || ''));
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching invoice for print:', err);
                        alert('خطأ في الاتصال بالخادم');
                    })
                    .finally(() => {
                        // إعادة حالة الزر
                        printBtn.innerHTML = originalText;
                        printBtn.disabled = false;
                    });
            });

            // دالة escapeHtml
            function escapeHtml(s) {
                if (s === null || s === undefined) return '';
                return String(s).replace(/[&<>"']/g, function(m) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    } [m];
                });
            }
        </script>

        <script>
            // دالة لتحديد الكل
            document.addEventListener('DOMContentLoaded', function() {
                const selectAllCheckbox = document.getElementById('selectAllInvoices');
                const printSelectedBtn = document.getElementById('printSelectedInvoices');

                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('.invoice-checkbox');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                        updatePrintButtonState();
                    });
                }

                // تحديث حالة زر الطباعة
                function updatePrintButtonState() {
                    const selectedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
                    printSelectedBtn.disabled = selectedCheckboxes.length === 0;
                }

                // تحديث عند تغيير أي checkbox
                document.addEventListener('change', function(e) {
                    if (e.target.classList.contains('invoice-checkbox')) {
                        updatePrintButtonState();

                        // تحديث selectAll إذا تم تحديد جميع الفواتير
                        const allCheckboxes = document.querySelectorAll('.invoice-checkbox');
                        const checkedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
                            selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
                        }
                    }
                });

                // طباعة الفواتير المحددة
                if (printSelectedBtn) {
                    printSelectedBtn.addEventListener('click', printSelectedInvoices);
                }
            });

            // دالة طباعة الفواتير المحددة
            async function printSelectedInvoices() {
                const selectedCheckboxes = document.querySelectorAll('.invoice-checkbox:checked');

                if (selectedCheckboxes.length === 0) {
                    Swal.fire('تنبيه', 'يرجى تحديد فواتير للطباعة', 'warning');
                    return;
                }

                try {
                    // إظهار تحميل
                    Swal.fire({
                        title: 'جاري التحميل',
                        text: 'جارٍ تجميع بيانات الفواتير المحددة...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    const invoiceIds = Array.from(selectedCheckboxes).map(checkbox =>
                        parseInt(checkbox.getAttribute('data-invoice-id'))
                    );

                    // جلب بيانات جميع الفواتير المحددة
                    const invoicesData = await Promise.all(
                        invoiceIds.map(id => fetchInvoiceData(id))
                    );

                    // إنشاء التقرير المجمع
                    const aggregatedReport = createAggregatedReport(invoicesData);

                    // طباعة التقرير
                    printAggregatedReport(aggregatedReport);

                    Swal.close();

                } catch (error) {
                    console.error('Error printing selected invoices:', error);
                    Swal.fire('خطأ', 'حدث خطأ أثناء تجميع البيانات', 'error');
                }
            }

            // دالة لجلب بيانات الفاتورة
            async function fetchInvoiceData(invoiceId) {
                const response = await fetch(location.pathname + '?action=fetch_invoice_details&id=' + encodeURIComponent(invoiceId), {
                    credentials: 'same-origin'
                });
                const data = await response.json();

                if (!data.success) {
                    throw new Error('Failed to fetch invoice data');
                }

                return data;
            }

            // دالة لإنشاء التقرير المجمع
            function createAggregatedReport(invoicesData) {
                const aggregatedItems = {};
                let totalBeforeDiscount = 0;
                let totalAfterDiscount = 0;
                let totalDiscount = 0;

                // تجميع البنود
                invoicesData.forEach(({
                    invoice,
                    items
                }) => {
                    // جمع الإجماليات
                    const invoiceTotalBefore = parseFloat(invoice.total_before_discount || 0);
                    const invoiceTotalAfter = parseFloat(invoice.total_after_discount || 0);

                    totalBeforeDiscount += invoiceTotalBefore > 0 ? invoiceTotalBefore :
                        items.reduce((sum, item) => sum + parseFloat(item.total_price || 0), 0);
                    totalAfterDiscount += invoiceTotalAfter > 0 ? invoiceTotalAfter : invoiceTotalBefore;

                    // تجميع البنود
                    items.forEach(item => {
                        const productId = item.product_id;
                        const productName = item.product_name || `منتج #${productId}`;
                        const quantity = parseFloat(item.quantity || 0);
                        const price = parseFloat(item.selling_price || item.cost_price_per_unit || 0);
                        const total = parseFloat(item.total_price || 0);

                        if (!aggregatedItems[productId]) {
                            aggregatedItems[productId] = {
                                name: productName,
                                quantity: 0,
                                price: price,
                                total: 0
                            };
                        }

                        aggregatedItems[productId].quantity += quantity;
                        aggregatedItems[productId].total += total;
                    });
                });

                totalDiscount = totalBeforeDiscount - totalAfterDiscount;

                return {
                    invoicesCount: invoicesData.length,
                    items: Object.values(aggregatedItems),
                    totals: {
                        beforeDiscount: totalBeforeDiscount,
                        afterDiscount: totalAfterDiscount,
                        discount: totalDiscount
                    },
                    invoices: invoicesData.map(({
                        invoice
                    }) => ({
                        id: invoice.id,
                        customer: invoice.customer_name,
                        total: parseFloat(invoice.total_after_discount || invoice.total_before_discount || 0)
                    }))
                };
            }

            // دالة طباعة التقرير المجمع
            function printAggregatedReport(report) {
                const printWindow = window.open('', '_blank', 'width=300,height=600');

                const itemsHTML = report.items.map(item => `
        <div class="item-row">
            <div class="item-name">${escapeHtml(item.name)}</div>
            <div class="item-qty">${item.quantity.toFixed(2)}</div>
            <div class="item-price">${item.price.toFixed(2)}</div>
            <div class="item-total">${item.total.toFixed(2)}</div>
        </div>
    `).join('');

                const invoicesHTML = report.invoices.map(inv => `
        <div style="display: flex; justify-content: space-between; margin: 5px 0; font-size: 12px;">
            <span>#${inv.id} - ${escapeHtml(inv.customer)}</span>
            <span>${inv.total.toFixed(2)} ج.م</span>
        </div>
    `).join('');

                const receiptContent = `
        <div class="header">
            <div class="company-name">تقرير الفواتير المجمع</div>
        </div>
        
        <div class="invoice-info">
            <span>عدد الفواتير: ${report.invoicesCount}</span>
        </div>
        
      
        
        <div class="items-section">
            <div class="items-header">
                <div class="item-name">المنتج</div>
                <div class="item-qty">الكمية</div>
                <div class="item-price">السعر</div>
                <div class="item-total">المجموع</div>
            </div>
            ${itemsHTML}
        </div>
        
        <div class="separator"></div>
        
        <div class="totals-section">
            <div class="total-row subtotal">
                <span>المجموع قبل الخصم:</span>
                <span>${report.totals.beforeDiscount.toFixed(2)} ج.م</span>
            </div>
            
            ${report.totals.discount > 0 ? `
                <div class="total-row discount-row">
                    <span>إجمالي الخصم:</span>
                    <span>-${report.totals.discount.toFixed(2)} ج.م</span>
                </div>
            ` : ''}
            
            <div class="final-total">
                <span>الإجمالي النهائي: ${report.totals.afterDiscount.toFixed(2)} ج.م</span>
            </div>
        </div>
        
        <div class="footer">
            <div class="print-date">${new Date().toLocaleString('ar-EG')}</div>
            <div style="margin-top: 8px; font-weight: bold;">تم الطباعة من النظام</div>
        </div>
    `;

                printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>تقرير الفواتير المجمع</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: 'Courier New', Courier, monospace;
                    font-size: 14px;
                    font-weight: bold;
                    width: 72mm;
                    margin: 0 auto;
                    padding: 1px 3px;
                    line-height: 1.2;
                    background: white;
                    color: #000;
                }
                .header { text-align: center; margin-bottom: 12px; padding-bottom: 8px; }
                .company-name { font-weight: 900; font-size: 18px; margin-bottom: 4px; }
                .invoice-info { margin: 6px 0; display: flex; justify-content: space-between; }
                .separator { border-bottom: 1px dashed #000; margin: 8px 0; }
                .items-header, .item-row { 
                    display: grid; 
                    grid-template-columns: 1fr 50px 60px 60px;
                    gap: 4px;
                    align-items: center;
                    padding: 6px 0;
                }
                .items-header { border-bottom: 2px solid #000; border-top: 2px solid #000; font-weight: 900; }
                .item-name { text-align: right; padding-right: 6px; }
                .total-row { display: flex; justify-content: space-between; margin: 4px 0; }
                .final-total { font-weight: 900; font-size: 16px; padding: 8px; margin-top: 8px; text-align: center; }
                .footer { text-align: center; margin-top: 15px; padding-top: 10px; border-top: 2px solid #000; }
                .print-date { font-weight: bold; margin: 4px 0; }
                .discount-row { color: #d00; }
                .invoices-list { margin: 8px 0; }
            </style>
        </head>
        <body>
            <div class="receipt-container">
                ${receiptContent}
            </div>
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 1000);
                };
            <\/script>
        </body>
        </html>
    `);

                printWindow.document.close();
            }
        </script>
        <?php
        // تحرير الموارد
        if ($result && is_object($result)) $result->free();
        $conn->close();
        require_once BASE_DIR . 'partials/footer.php';
        ?>