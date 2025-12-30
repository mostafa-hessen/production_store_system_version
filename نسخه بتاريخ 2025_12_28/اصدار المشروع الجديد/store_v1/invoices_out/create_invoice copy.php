        <?php
        // create_invoice.php (mysqli version)
        // إنشاء فاتورة — يدعم FIFO allocations, CSRF (meta + JS), اختيار عميل مثبت، إضافة عميل، created_by tracking.

        // ========== BOOT (config + session) ==========
        $page_title = "إنشاء فاتورة بيع";
        $page_css = "invoice_out.css";
        $class_dashboard = "active";

        require_once dirname(__DIR__) . '/config.php';
        require_once BASE_DIR . 'partials/session_admin.php'; // تأكد أن session_start() هنا وأن $_SESSION['id'] متوفر

        // buffer to capture unexpected output for AJAX
        ob_start();

        // helper JSON (يجب أن يكون متاحًا مبكراً)
        function jsonOut($payload)
        {
            if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
            // clear any buffered output to avoid HTML leakage
            if (ob_get_length() !== false) ob_clean();
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }

        // تأكد أن $conn موجود وهو mysqli
        if (!isset($conn) || !($conn instanceof mysqli)) {
            http_response_code(500);
            die("خطأ: \$conn غير معرف أو ليس mysqli. تأكد من ملف config.php");
        }

        // CSRF token
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $csrf_token = $_SESSION['csrf_token'];

        // تأكد وجود مستخدم مسجل
        if (empty($_SESSION['id'])) {
            error_log("create_invoice: no session user_id. Session keys: " . json_encode(array_keys($_SESSION)));
            jsonOut(['ok' => false, 'error' => 'المستخدم غير معرف. الرجاء تسجيل الدخول مجدداً.']);
        }
        $created_by = (int)$_SESSION['id'];

        // جلب اسم المستخدم لعرضه في الـ JS (اختياري)
        $created_by_name_js = '';
        $stmtUser = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
        if ($stmtUser) {
            $stmtUser->bind_param('i', $created_by);
            $stmtUser->execute();
            $resUser = $stmtUser->get_result();
            $u = $resUser->fetch_assoc();
            if ($u) $created_by_name_js = addslashes($u['username']);
            $stmtUser->close();
        }


        // حدد الوضع بدايه
        $mode = $_GET['mode'] ?? 'create';
        $invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $invoice = [];
        $invoiceItems = [];



        // حدد الوضع بدايه end

        /* =========================
        AJAX endpoints
        Must run before any HTML output
        ========================= */
        if (isset($_REQUEST['action'])) {
            $action = $_REQUEST['action'];

            // 0) sync_consumed
            if ($action === 'sync_consumed') {
                try {
                    $stmt = $conn->prepare("UPDATE batches SET status = 'consumed', updated_at = NOW() WHERE status = 'active' AND COALESCE(remaining,0) <= 0");
                    if (!$stmt) throw new Exception($conn->error);
                    $stmt->execute();
                    $updated = $conn->affected_rows;
                    $stmt->close();
                    jsonOut(['ok' => true, 'updated' => $updated]);
                } catch (Exception $e) {
                    jsonOut(['ok' => false, 'error' => 'فشل تحديث حالات الدفعات.', 'detail' => $e->getMessage()]);
                }
            }

            // 1) products (with aggregates)
            if ($action === 'products') {
                $q = trim($_GET['q'] ?? '');
                try {
                    if ($q === '') {
                        $sql = "
                            SELECT p.id, p.product_code, p.name, p.unit_of_measure, p.current_stock, p.reorder_level,
                            p.selling_price AS product_sale_price,p.retail_price,
                                    COALESCE(b.rem_sum,0) AS remaining_active,
                                COALESCE(b.val_sum,0) AS stock_value_active,
                                (SELECT b2.unit_cost FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_purchase_price,
                                (SELECT b2.sale_price FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_sale_price,
                                (SELECT b2.received_at FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_batch_date
                            FROM products p
                            LEFT JOIN (
                            SELECT product_id, SUM(remaining) AS rem_sum, SUM(remaining * unit_cost) AS val_sum
                            FROM batches
                            WHERE status = 'active' AND remaining > 0
                            GROUP BY product_id
                            ) b ON b.product_id = p.id
                            ORDER BY p.id DESC
                            LIMIT 2000
                        ";
                        $res = $conn->query($sql);
                        if (!$res) throw new Exception($conn->error);
                        $rows = $res->fetch_all(MYSQLI_ASSOC);
                    } else {
                        $sql = "
                            SELECT p.id, p.product_code, p.name, p.unit_of_measure, p.current_stock, p.reorder_level,
                            p.selling_price AS product_sale_price,p.retail_price ,
                                COALESCE(b.rem_sum,0) AS remaining_active,
                                COALESCE(b.val_sum,0) AS stock_value_active,
                                (SELECT b2.unit_cost FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_purchase_price,
                                (SELECT b2.sale_price FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_sale_price,
                                (SELECT b2.received_at FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_batch_date
                            FROM products p
                            LEFT JOIN (
                            SELECT product_id, SUM(remaining) AS rem_sum, SUM(remaining * unit_cost) AS val_sum
                            FROM batches
                            WHERE status = 'active' AND remaining > 0
                            GROUP BY product_id
                            ) b ON b.product_id = p.id
                            WHERE (p.name LIKE ? OR p.product_code LIKE ? OR p.id = ?)
                            ORDER BY p.id DESC
                            LIMIT 2000
                        ";
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) throw new Exception($conn->error);
                        $like = "%{$q}%";
                        $q_id = is_numeric($q) ? (int)$q : 0;
                        $stmt->bind_param('ssi', $like, $like, $q_id);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $rows = $res->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                    }

                    jsonOut(['ok' => true, 'products' => $rows]);
                } catch (Exception $e) {
                    jsonOut(['ok' => false, 'error' => 'فشل جلب المنتجات.', 'detail' => $e->getMessage()]);
                }
            }

            // 2) batches list for a product
            if ($action === 'batches' && isset($_GET['product_id'])) {
                $product_id = (int)$_GET['product_id'];
                try {
                    $sql = "SELECT id, product_id, qty, remaining, original_qty, unit_cost, sale_price, received_at, expiry, notes, source_invoice_id, source_item_id, created_by, adjusted_by, adjusted_at, created_at, updated_at, revert_reason, cancel_reason, status FROM batches WHERE product_id = ? ORDER BY received_at DESC, created_at DESC, id DESC";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) throw new Exception($conn->error);
                    $stmt->bind_param('i', $product_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $batches = $res->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    $pstmt = $conn->prepare("SELECT id, name, product_code FROM products WHERE id = ?");
                    if (!$pstmt) throw new Exception($conn->error);
                    $pstmt->bind_param('i', $product_id);
                    $pstmt->execute();
                    $pres = $pstmt->get_result();
                    $prod = $pres->fetch_assoc();
                    $pstmt->close();

                    jsonOut(['ok' => true, 'batches' => $batches, 'product' => $prod]);
                } catch (Exception $e) {
                    jsonOut(['ok' => false, 'error' => 'فشل جلب الدفعات.', 'detail' => $e->getMessage()]);
                }
            }

            // 3) customers list/search
            if ($action === 'customers') {
                $q = trim($_GET['q'] ?? '');
                try {
                    if ($q === '') {
                        $res = $conn->query("SELECT id,name,mobile,city,address FROM customers ORDER BY name LIMIT 200");
                        if (!$res) throw new Exception($conn->error);
                        $rows = $res->fetch_all(MYSQLI_ASSOC);
                    } else {
                        $stmt = $conn->prepare("SELECT id,name,mobile,city,address FROM customers WHERE name LIKE ? OR mobile LIKE ? ORDER BY name LIMIT 200");
                        if (!$stmt) throw new Exception($conn->error);
                        $like = "%{$q}%";
                        $stmt->bind_param('ss', $like, $like);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $rows = $res->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                    }
                    jsonOut(['ok' => true, 'customers' => $rows]);
                } catch (Exception $e) {
                    jsonOut(['ok' => false, 'error' => 'فشل جلب العملاء.', 'detail' => $e->getMessage()]);
                }
            }

            // 4) add customer (POST)
            if ($action === 'add_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
                    jsonOut(['ok' => false, 'error' => 'رمز التحقق (CSRF) غير صالح. أعد تحميل الصفحة وحاول مجدداً.']);
                }

                $name = trim($_POST['name'] ?? '');
                $mobile = trim($_POST['mobile'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $notes = trim($_POST['notes'] ?? '');

                if ($name === '') jsonOut(['ok' => false, 'error' => 'الرجاء إدخال اسم العميل.']);
                if ($mobile === '') jsonOut(['ok' => false, 'error' => 'الرجاء إدخال رقم الموبايل.']);

                $mobile_digits = preg_replace('/\D+/', '', $mobile);
                if (strlen($mobile_digits) < 7 || strlen($mobile_digits) > 15) {
                    jsonOut(['ok' => false, 'error' => 'رقم الموبايل غير صحيح. الرجاء إدخال رقم صالح.']);
                }
                $mobile_clean = $mobile_digits;
                $created_by_i = (int)$created_by;

                try {
                    $chk = $conn->prepare("SELECT id, name FROM customers WHERE mobile = ? LIMIT 1");
                    if (!$chk) throw new Exception($conn->error);
                    $chk->bind_param('s', $mobile_clean);
                    $chk->execute();
                    $cres = $chk->get_result();
                    $exists = $cres->fetch_assoc();
                    $chk->close();

                    if ($exists) {
                        jsonOut(['ok' => false, 'error' => "رقم الموبايل مسجل بالفعل للعميل \"{$exists['name']}\"."]);
                    }

                    $stmt = $conn->prepare("INSERT INTO customers (name,mobile,city,address,notes,created_by,created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    if (!$stmt) throw new Exception($conn->error);
                    $stmt->bind_param('sssssi', $name, $mobile_clean, $city, $address, $notes, $created_by_i);
                    $stmt->execute();
                    if ($stmt->errno) {
                        $err = $stmt->error;
                        $stmt->close();
                        throw new Exception($err);
                    }
                    $newId = (int)$conn->insert_id;
                    $stmt->close();

                    $pstmt = $conn->prepare("SELECT id,name,mobile,city,address FROM customers WHERE id = ?");
                    if (!$pstmt) throw new Exception($conn->error);
                    $pstmt->bind_param('i', $newId);
                    $pstmt->execute();
                    $pres = $pstmt->get_result();
                    $new = $pres->fetch_assoc();
                    $pstmt->close();

                    jsonOut(['ok' => true, 'msg' => 'تم إضافة العميل', 'customer' => $new]);
                } catch (Exception $e) {
                    // تحقق من duplicate key عبر كود الخطأ من MySQL (إذا ظهر)
                    $errMsg = $e->getMessage();
                    if (strpos($errMsg, 'Duplicate') !== false || strpos($errMsg, 'duplicate') !== false) {
                        jsonOut(['ok' => false, 'error' => 'قيمة مكررة — رقم الموبايل مستخدم بالفعل.']);
                    }
                    error_log("MySQL add_customer error: " . $e->getMessage());
                    jsonOut(['ok' => false, 'error' => 'فشل إضافة العميل. حاول مرة أخرى.']);
                }
            }
            // في قسم AJAX endpoints في PHP، أضف:
            if ($action === 'product_by_barcode') {
                // $barcode = trim($_GET['barcode'] ?? '');
                $barcode = trim($_REQUEST['barcode'] ?? '');

                if ($barcode === '') {
                    jsonOut(['ok' => false, 'error' => 'باركود فارغ']);
                }

                try {
                    $stmt = $conn->prepare("
                    SELECT p.id, p.product_code, p.name, p.unit_of_measure, p.current_stock, p.reorder_level,
                        p.selling_price, p.retail_price,
                        COALESCE(b.rem_sum,0) AS remaining_active,
                        COALESCE(b.val_sum,0) AS stock_value_active,
                        (SELECT b2.unit_cost FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_purchase_price,
                        (SELECT b2.sale_price FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_sale_price,
                        (SELECT b2.received_at FROM batches b2 WHERE b2.product_id = p.id AND b2.status IN ('active','consumed') ORDER BY b2.received_at DESC, b2.created_at DESC LIMIT 1) AS last_batch_date
                    FROM products p
                    LEFT JOIN (
                        SELECT product_id, SUM(remaining) AS rem_sum, SUM(remaining * unit_cost) AS val_sum
                        FROM batches
                        WHERE status = 'active' AND remaining > 0
                        GROUP BY product_id
                    ) b ON b.product_id = p.id
                    WHERE p.product_code = ?
                    LIMIT 1
                ");
                    if (!$stmt) throw new Exception($conn->error);
                    $stmt->bind_param('s', $barcode);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $product = $res->fetch_assoc();
                    $stmt->close();

                    if ($product) {
                        jsonOut(['ok' => true, 'product' => $product]);
                    } else {
                        jsonOut(['ok' => false, 'error' => 'المنتج غير موجود']);
                    }
                } catch (Exception $e) {
                    jsonOut(['ok' => false, 'error' => 'فشل جلب المنتج', 'detail' => $e->getMessage()]);
                }
            }

            // 5) select customer (store in session) - POST
            if ($action === 'select_customer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], (string)$token)) jsonOut(['ok' => false, 'error' => 'رمز التحقق (CSRF) غير صالح.']);
                $cid = (int)($_POST['customer_id'] ?? 0);
                if ($cid <= 0) {
                    unset($_SESSION['selected_customer']);
                    jsonOut(['ok' => true, 'msg' => 'تم إلغاء اختيار العميل']);
                }
                try {
                    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
                    if (!$stmt) throw new Exception($conn->error);
                    $stmt->bind_param('i', $cid);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $cust = $res->fetch_assoc();
                    $stmt->close();
                    if (!$cust) jsonOut(['ok' => false, 'error' => 'العميل غير موجود']);
                    $_SESSION['selected_customer'] = $cust;
                    jsonOut(['ok' => true, 'customer' => $cust]);
                } catch (Exception $e) {
                    jsonOut(['ok' => false, 'error' => 'تعذر اختيار العميل.', 'detail' => $e->getMessage()]);
                }
            }
            if ($action === 'fifo_simulation') {
                $product_id = (int)$_GET['product_id'];
                $qty = (float)$_GET['qty'];

                try {
                    // جلب الدفعات المتاحة مرتبة من الأقدم (FIFO)
                    $sql = "SELECT id, product_id, remaining, unit_cost, received_at 
                        FROM batches 
                        WHERE product_id = ? AND status = 'active' AND remaining > 0 
                        ORDER BY received_at ASC, created_at ASC, id ASC";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) throw new Exception($conn->error);
                    $stmt->bind_param('i', $product_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $batches = $res->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    // محاكاة توزيع FIFO
                    $need = $qty;
                    $allocations = [];
                    $total_cost = 0;

                    foreach ($batches as $batch) {
                        if ($need <= 0) break;

                        $batch_id = (int)$batch['id'];
                        $avail = (float)$batch['remaining'];
                        $unit_cost = (float)$batch['unit_cost'];
                        $received_at = $batch['received_at'];

                        if ($avail <= 0) continue;

                        $take = min($avail, $need);
                        $cost = $take * $unit_cost;

                        $allocations[] = [
                            'batch_id' => $batch_id,
                            'received_at' => $received_at,
                            'remaining_before' => $avail,
                            'remaining_after' => $avail - $take,
                            'unit_cost' => $unit_cost,
                            'taken' => $take,
                            'cost' => $cost
                        ];

                        $total_cost += $cost;
                        $need -= $take;
                    }

                    // جلب اسم المنتج
                    $pstmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
                    $product_name = '';
                    if ($pstmt) {
                        $pstmt->bind_param('i', $product_id);
                        $pstmt->execute();
                        $pres = $pstmt->get_result();
                        $prod = $pres->fetch_assoc();
                        $product_name = $prod ? $prod['name'] : '';
                        $pstmt->close();
                    }

                    jsonOut([
                        'ok' => true,
                        'allocations' => $allocations,
                        'total_cost' => $total_cost,
                        'product_name' => $product_name,
                        'sufficient' => ($need <= 0)
                    ]);
                } catch (Exception $e) {
                    jsonOut(['ok' => false, 'error' => 'فشل محاكاة FIFO', 'detail' => $e->getMessage()]);
                }
            }




            //             if ($action === 'save_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {

            //                 $token = $_POST['csrf_token'] ?? '';
            //                 if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
            //                     jsonOut(['ok' => false, 'error' => 'رمز التحقق (CSRF) غير صالح. أعد تحميل الصفحة وحاول مجدداً.']);
            //                 }

            //                 $customer_id = (int)($_POST['customer_id'] ?? 0);
            // $work_order_id = (!isset($_POST['work_order_id']) || $_POST['work_order_id'] === '' || $_POST['work_order_id'] === 'null')
            //     ? null
            //     : (int)$_POST['work_order_id'];
            //                 $items_json = $_POST['items'] ?? '';
            //                 $notes = trim($_POST['notes'] ?? '');
            //                 $discount_type = in_array($_POST['discount_type'] ?? 'percent', ['percent', 'amount']) ? $_POST['discount_type'] : 'percent';
            //                 $discount_value = (float)($_POST['discount_value'] ?? 0.0);
            //                 $created_by_i = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

            //                 if ($customer_id <= 0) jsonOut(['ok' => false, 'error' => 'الرجاء اختيار عميل.']);
            //                 if (empty($items_json)) jsonOut(['ok' => false, 'error' => 'لا توجد بنود لإضافة الفاتورة.']);

            //                 $items = json_decode($items_json, true);
            //                 if (!is_array($items) || count($items) === 0) jsonOut(['ok' => false, 'error' => 'بنود الفاتورة غير صالحة.']);

            //                 // ===== التحقق من الشغلانة إذا أُرسلت =====
            //                 if ($work_order_id) {
            //                     $checkWorkOrderStmt = $conn->prepare("
            //             SELECT id, customer_id, status, title 
            //             FROM work_orders 
            //             WHERE id = ? AND customer_id = ? AND status != 'cancelled'
            //         ");
            //                     $checkWorkOrderStmt->bind_param('ii', $work_order_id, $customer_id);
            //                     $checkWorkOrderStmt->execute();
            //                     $workOrderResult = $checkWorkOrderStmt->get_result();

            //                     if ($workOrderResult->num_rows === 0) {
            //                         $checkWorkOrderStmt->close();
            //                         jsonOut(['ok' => false, 'error' => 'الشغلانة غير موجودة أو لا تنتمي لهذا العميل أو ملغية.']);
            //                     }
            //                     $workOrderRow = $workOrderResult->fetch_assoc();
            //                     $workOrderName = $workOrderRow['title'] ?? '';
            //                     $checkWorkOrderStmt->close();
            //                 } else {
            //                     $workOrderName = '';
            //                 }

            //                 // ===== حساب الإجماليات =====
            //                 $total_before = 0.0;
            //                 $total_cost = 0.0;
            //                 foreach ($items as $it) {
            //                     $qty = (float)($it['qty'] ?? $it['quantity'] ?? 0);
            //                     $sp = (float)($it['selling_price'] ?? $it['price'] ?? 0);
            //                     $cp = (float)($it['cost_price_per_unit'] ?? $it['cost_price'] ?? 0);

            //                     $total_before += round($qty * $sp, 2);
            //                     $total_cost += round($qty * $cp, 2);
            //                 }
            //                 $total_before = round($total_before, 2);
            //                 $total_cost = round($total_cost, 2);

            //                 // حساب الخصم
            //                 if ($discount_type === 'percent') {
            //                     $discount_amount = round($total_before * ($discount_value / 100.0), 2);
            //                 } else {
            //                     $discount_amount = round($discount_value, 2);
            //                 }
            //                 if ($discount_amount > $total_before) $discount_amount = $total_before;

            //                 $total_after = round($total_before - $discount_amount, 2);
            //                 $profit_after = round($total_after - $total_cost, 2);

            //                 // ==== القيم الثابتة للفاتورة الجديدة ====
            //                 $status = 'pending';
            //                 $delivered = 'no';
            //                 $paid_amount = 0;
            //                 $remaining_amount = $total_after;

            //                 try {
            //                     $conn->begin_transaction();

            //                     // ===== إدراج رأس الفاتورة =====
            //                     $invoice_group = 'group1';
            //                     $stmt = $conn->prepare("
            //             INSERT INTO invoices_out
            //             (customer_id, delivered, invoice_group, created_by, created_at, notes,
            //             total_before_discount, discount_type, discount_value, discount_amount, 
            //             total_after_discount, total_cost, profit_amount, paid_amount, remaining_amount, work_order_id)
            //             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            //         ");
            //                     if (!$stmt) throw new Exception($conn->error);

            //                     // bind_param مع التحقق من null لـ work_order_id
            //                   $stmt->bind_param(
            //     'issisdsdddddddi',
            //     $customer_id,
            //     $delivered,
            //     $invoice_group,
            //     $created_by_i,
            //     $notes,
            //     $total_before,
            //     $discount_type,
            //     $discount_value,
            //     $discount_amount,
            //     $total_after,
            //     $total_cost,
            //     $profit_after,
            //     $paid_amount,
            //     $remaining_amount,
            //     $work_order_id   // ← null مسموح
            // );


            //                     $stmt->execute();
            //                     if ($stmt->errno) {
            //                         $e = $stmt->error;
            //                         $stmt->close();
            //                         throw new Exception($e);
            //                     }

            //                     $invoice_id = (int)$conn->insert_id;
            //                     $stmt->close();

            //                     // ===== تسجيل حركة العميل =====
            //                     $balanceStmt = $conn->prepare("SELECT balance ,wallet FROM customers WHERE id = ? FOR UPDATE");
            //                     $balanceStmt->bind_param('i', $customer_id);
            //                     $balanceStmt->execute();
            //                     $balanceRow = $balanceStmt->get_result()->fetch_assoc();
            //                     $balance_before = (float)($balanceRow['balance'] ?? 0);
            //                     $balance_after = $balance_before + $total_after;

            // $wallet_before  = (float)$balanceRow['wallet'];
            // $work_order_id = $work_order_id ?: null;
            // $wallet_after  = $wallet_before; 
            //                     $balanceStmt->close();

            //                     $description = "فاتورة مبيعات جديدة #{$invoice_id}";
            //                     if ($work_order_id) {
            //                         $description .= " (الشغلانة: \"{$workOrderName}\" رقم #{$work_order_id})";
            //                     }

            //                   $transactionStmt = $conn->prepare("
            //     INSERT INTO customer_transactions 
            //     (
            //         customer_id,
            //         transaction_type,
            //         amount,
            //         description,
            //         invoice_id,
            //         work_order_id,
            //         balance_before,
            //         balance_after,
            //         wallet_before,
            //         wallet_after,
            //         transaction_date,
            //         created_by
            //     )
            //     VALUES (?, 'invoice', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            // ");

            // $transactionStmt->bind_param(
            //     'idsiiddddi' ,
            //     $customer_id,
            //     $total_after,
            //     $description,
            //     $invoice_id,
            //     $work_order_id,
            //     $balance_before,
            //     $balance_after,
            //     $wallet_before,
            //     $wallet_after,
            //     $created_by_i
            // );

            // $transactionStmt->execute();
            // $transactionStmt->close();



            //                     // ===== تحديث رصيد العميل =====
            //                     $updateBalanceStmt = $conn->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?");
            //                     $updateBalanceStmt->bind_param('di', $total_after, $customer_id);
            //                     $updateBalanceStmt->execute();
            //                     $updateBalanceStmt->close();

            //                     // ===== إدراج البنود وتخصيص FIFO =====
            //                     $totalRevenue = 0.0;
            //                     $totalCOGS = 0.0;

            //                     $insertItemStmt = $conn->prepare("
            //             INSERT INTO invoice_out_items
            //             (invoice_out_id, product_id, quantity, total_price, cost_price_per_unit, selling_price, price_type, created_at)
            //             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            //         ");
            //                     $insertAllocStmt = $conn->prepare("INSERT INTO sale_item_allocations (sale_item_id, batch_id, qty, unit_cost, line_cost, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            //                     $updateBatchStmt = $conn->prepare("UPDATE batches SET remaining = ?, status = ?, adjusted_at = NOW(), adjusted_by = ? WHERE id = ?");
            //                     $selectBatchesStmt = $conn->prepare("SELECT id, remaining, unit_cost FROM batches WHERE product_id = ? AND status = 'active' AND remaining > 0 ORDER BY received_at ASC, created_at ASC, id ASC FOR UPDATE");

            //                     foreach ($items as $it) {
            //                         $product_id = (int)($it['product_id'] ?? 0);
            //                         $qty = (float)($it['qty'] ?? 0);
            //                         $selling_price = (float)($it['selling_price'] ?? 0);
            //                         $price_type = strtolower(trim((string)($it['price_type'] ?? 'wholesale')));

            //                         if ($product_id <= 0 || $qty <= 0) {
            //                             $conn->rollback();
            //                             jsonOut(['ok' => false, 'error' => "بند غير صالح (معرف/كمية)."]);
            //                         }

            //                         // جلب اسم المنتج
            //                         $product_name = null;
            //                         $pnameStmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
            //                         if ($pnameStmt) {
            //                             $pnameStmt->bind_param('i', $product_id);
            //                             $pnameStmt->execute();
            //                             $prow = $pnameStmt->get_result()->fetch_assoc();
            //                             $product_name = $prow['name'] ?? '';
            //                             $pnameStmt->close();
            //                         }

            //                         // تخصيص FIFO
            //                         $selectBatchesStmt->bind_param('i', $product_id);
            //                         $selectBatchesStmt->execute();
            //                         $availableBatches = $selectBatchesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            //                         $need = $qty;
            //                         $allocations = [];
            //                         foreach ($availableBatches as $b) {
            //                             if ($need <= 0) break;
            //                             $avail = (float)$b['remaining'];
            //                             if ($avail <= 0) continue;
            //                             $take = min($avail, $need);
            //                             $allocations[] = ['batch_id' => (int)$b['id'], 'take' => $take, 'unit_cost' => (float)$b['unit_cost']];
            //                             $need -= $take;
            //                         }

            //                         if ($need > 0.00001) {
            //                             $conn->rollback();
            //                             jsonOut([
            //                                 'ok' => false,
            //                                 'error' => "الرصيد غير كافٍ للمنتج '{$product_name}'. (ID: {$product_id})"
            //                             ]);
            //                         }

            //                         $itemTotalCost = 0.0;
            //                         foreach ($allocations as $a) $itemTotalCost += $a['take'] * $a['unit_cost'];
            //                         $cost_price_per_unit = ($qty > 0) ? ($itemTotalCost / $qty) : 0.0;

            //                         // جلب سعر البيع إذا لم يُرسل
            //                         if ($selling_price <= 0) {
            //                             $ppriceStmt = $conn->prepare("SELECT retail_price, selling_price FROM products WHERE id = ?");
            //                             $ppriceStmt->bind_param('i', $product_id);
            //                             $ppriceStmt->execute();
            //                             $prow = $ppriceStmt->get_result()->fetch_assoc();
            //                             if ($prow) {
            //                                 $selling_price = ($price_type === 'retail') ? (float)($prow['retail_price'] ?? 0) : (float)($prow['selling_price'] ?? 0);
            //                             }
            //                             $ppriceStmt->close();
            //                         }

            //                         $lineTotalPrice = $qty * $selling_price;

            //                         // إدراج البند
            //                         $invoice_item_id = $invoice_id;
            //                         $insertItemStmt->bind_param('iidddds', $invoice_item_id, $product_id, $qty, $lineTotalPrice, $cost_price_per_unit, $selling_price, $price_type);
            //                         $insertItemStmt->execute();
            //                         if ($insertItemStmt->errno) {
            //                             $err = $insertItemStmt->error;
            //                             $insertItemStmt->close();
            //                             throw new Exception($err);
            //                         }
            //                         $invoice_item_id = (int)$conn->insert_id;

            //                         // تطبيق التخصيصات على الـ batches
            //                         foreach ($allocations as $a) {
            //                             $stmtCur = $conn->prepare("SELECT remaining FROM batches WHERE id = ? FOR UPDATE");
            //                             $stmtCur->bind_param('i', $a['batch_id']);
            //                             $stmtCur->execute();
            //                             $curRow = $stmtCur->get_result()->fetch_assoc();
            //                             $stmtCur->close();

            //                             $newRem = max(0.0, ((float)$curRow['remaining']) - $a['take']);
            //                             $newStatus = ($newRem <= 0) ? 'consumed' : 'active';

            //                             $updateBatchStmt->bind_param('dsii', $newRem, $newStatus, $created_by_i, $a['batch_id']);
            //                             $updateBatchStmt->execute();

            //                             $lineCost = $a['take'] * $a['unit_cost'];
            //                             $insertAllocStmt->bind_param('iidddi', $invoice_item_id, $a['batch_id'], $a['take'], $a['unit_cost'], $lineCost, $created_by_i);
            //                             $insertAllocStmt->execute();
            //                         }

            //                         $totalRevenue += $lineTotalPrice;
            //                         $totalCOGS += $itemTotalCost;
            //                     }

            //                     $insertItemStmt->close();
            //                     $insertAllocStmt->close();
            //                     $updateBatchStmt->close();
            //                     $selectBatchesStmt->close();

            //                     function updateWorkOrderTotals($conn, $work_order_id)
            //                     {
            //                         $stmt = $conn->prepare("
            //         SELECT 
            //             COALESCE(SUM(total_after_discount), 0) as total_invoices,
            //             COALESCE(SUM(paid_amount), 0) as total_paid,
            //             COALESCE(SUM(remaining_amount), 0) as total_remaining
            //         FROM invoices_out 
            //         WHERE work_order_id = ?   AND delivered NOT IN ('reverted', 'cancelled')

            //     ");
            //                         $stmt->bind_param('i', $work_order_id);
            //                         $stmt->execute();
            //                         $result = $stmt->get_result()->fetch_assoc();
            //                         $stmt->close();

            //                         $stmt = $conn->prepare("
            //         UPDATE work_orders 
            //         SET total_invoice_amount = ?, total_paid = ?, total_remaining = ?, updated_at = NOW() 
            //         WHERE id = ?
            //     ");
            //                         $stmt->bind_param(
            //                             'dddi',
            //                             $result['total_invoices'],
            //                             $result['total_paid'],
            //                             $result['total_remaining'],
            //                             $work_order_id
            //                         );
            //                         $stmt->execute();
            //                         $stmt->close();
            //                     }
            //                     // تحديث الشغلانة
            //                     if ($work_order_id) {
            //                         updateWorkOrderTotals($conn, $work_order_id);
            //                     }

            //                     $conn->commit();

            //                     jsonOut([
            //                         'ok' => true,
            //                         'msg' => 'تم إنشاء الفاتورة بنجاح.',
            //                         'invoice_id' => $invoice_id,
            //                         'invoice_number' => $invoice_id,
            //                         'total_revenue' => round($totalRevenue, 2),
            //                         'total_cogs' => round($totalCOGS, 2),
            //                         'paid_amount' => 0,
            //                         'remaining_amount' => $total_after,
            //                         'payment_status' => 'pending',
            //                         'discount_type' => $discount_type,
            //                         'discount_value' => $discount_value,
            //                         'discount_amount' => $discount_amount,
            //                         'total_before' => $total_before,
            //                         'total_after' => $total_after,
            //                         'work_order_id' => $work_order_id,
            //                         'customer_balance_after' => $balance_after
            //                     ]);
            //                 } catch (Exception $e) {
            //                     if (isset($conn)) $conn->rollback();
            //                     error_log("save_invoice error: " . $e->getMessage());
            //                     jsonOut(['ok' => false, 'error' => 'حدث خطأ أثناء حفظ الفاتورة.', 'detail' => $e->getMessage()]);
            //                 }
            //             }

            // دالة تحديث بيانات الشغلانة




            if ($_SERVER['REQUEST_METHOD'] === 'POST'  && $action === 'process_return') {
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




            // unknown action
            jsonOut(['ok' => false, 'error' => 'action غير معروف']);
        } // end if action


        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (isset($_SESSION['selected_customer'])) {
                unset($_SESSION['selected_customer']);
            }
        }
        // Read selected customer from session (if any) to pre-fill UI
        $selected_customer_js = 'null';
        if (!empty($_SESSION['selected_customer']) && is_array($_SESSION['selected_customer'])) {
            $sc = $_SESSION['selected_customer'];
            $selected_customer_js = json_encode($sc, JSON_UNESCAPED_UNICODE);
        }

        // If user session id not set, created_by will be null - but we try:

        // After this point, safe to include header and render HTML
        require_once BASE_DIR . 'partials/header.php';
        ?>
        <!-- put csrf token in meta so JS reads it reliably -->
        <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
        <?php require_once BASE_DIR . 'partials/sidebar.php'; ?>




        <div class="invoice-container mt-3 container-fluid">
            <!-- الجزء الأيسر (الفاتورة والمنتجات) -->
            <div class="invoice-main">
                <div class="invoice-panel">
                    <!-- عرض رقم الفاتورة -->
                    <div class="invoice-header-info">
                        <div class="invoice-number">
                            <span>رقم الفاتورة:</span>
                            <strong id="current-invoice-number">--</strong>
                        </div>

                    </div>


                    <!-- مسح الباركود -->
                    <div class="barcode-section">
                        <div class="barcode-input">
                            <input type="text" id="barcode-input" placeholder="مسح الباركود أو البحث عن منتج...">
                            <button class="btn btn-primary" id="scan-barcode">
                                <i class="fas fa-barcode"></i> مسح
                            </button>
                        </div>
                    </div>

                    <div class="integrated-search-container" style="position: relative; margin-bottom: 15px;">
                        <div class="search-input-wrapper">
                            <input type="text"
                                id="integrated-product-search"
                                class="form-control"
                                placeholder="🔍 اكتب اسم المنتج أو الكود... (استخدم ↑↓ للتنقل، Enter للتحديد)"
                                style="padding-right: 40px;">
                            <div class="search-icon" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #666;">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>

                        <div id="integrated-search-results"
                            class="search-results-dropdown">
                            <!-- النتائج تظهر هنا -->
                        </div>
                    </div>
                    <!-- إضافة منتج جديد -->
                    <div class="add-product-section">
                        <select id="product-select">
                            <option value="">اختر منتج للإضافة</option>
                        </select>
                        <input type="number" id="product-qty" min="1" value="1" placeholder="الكمية">
                        <div class="price-type-buttons">
                            <button id="price-wholesale-btn" class="active btn" style="color:#fff">جملة</button>
                            <button id="price-retail-btn" class="btn btn-info" style="color:#fff">قطاعي</button>
                        </div>
                        <input type="number" id="product-price" step="0.01" placeholder="السعر">
                        <button class="btn btn-primary" id="add-product-btn">
                            <i class="fas fa-plus"></i> إضافة
                        </button>
                    </div>

                    <!-- جدول البنود -->
                    <div class="invoice-table-container">
                        <table class="invoice-table">
                            <thead>
                              <tr>
    <th width="25%">المنتج</th>
    <th width="8%">الكمية</th>
    <th width="10%">سعر الوحدة</th>
    <th width="8%">نوع السعر</th>
    <th width="10%">الإجمالي قبل الخصم</th>
    <th width="8%">قيمة الخصم</th>
    <th width="8%">نوع الخصم</th>
    <th width="10%">الإجمالي بعد الخصم</th>
    <th width="8%">خيارات</th>
</tr>
                            </thead>
                            <tbody id="invoice-items">
                                <!-- سيتم تعبئتها بالبيانات -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- الجزء الأيمن (الملخص والإجراءات) -->
            <div class="invoice-sidebar">
                <div class="panel">
                    <div class="panel-title">
                        <i class="fas fa-receipt"></i>
                        الفاتورة
                    </div>

                    <!-- العميل -->
                    <div class="customer-section justify-content-between">
                        <div class="choosse-customer-panel">

                            <div class="customer-avatar">?</div>
                            <div class="customer-info">
                                <div class="customer-name">لم يتم اختيار عميل</div>
                                <div class="customer-details">يرجى اختيار عميل</div>
                                <button class="change-customer" id="change-customer">اختيار</button>
                            </div>
                        </div>

                        <div id="customer-actions" class=" row">


                        </div>
                    </div>
                    <div class="work-order-section mb-3" id="work-order-section" style="display: none;">
                        <div class="panel-title">
                            <i class="fas fa-tasks"></i>
                            الشغلانة
                        </div>
                        <div class="work-order-controls">
                            <!-- سيكون محتوى هذا القسم ديناميكيًا -->
                        </div>
                    </div>
                    <!-- الخصم -->
                    <!-- في قسم الخصم، أضف زر الإلغاء -->
                    <div class="discount-section d-none">
                        <div class="panel-title">
                            <i class="fas fa-tag"></i>
                            الخصم
                            <button class="btn btn-danger btn-sm " id="cancel-discount" style="margin-right: auto; font-size: 12px;">
                                إلغاء الخصم
                            </button>
                        </div>
                        <div class="discount-inputs">
                            <select id="discount-type">
                                <option value="percent">نسبة مئوية</option>
                                <option value="amount">مبلغ ثابت</option>
                            </select>
                            <input type="number" id="discount-value" placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div class="quick-discounts">
                            <div class="quick-discount" data-value="5">5%</div>
                            <div class="quick-discount" data-value="10">10%</div>
                            <div class="quick-discount" data-value="15">15%</div>
                            <div class="quick-discount" data-value="20">20%</div>
                        </div>
                    </div>
                    <!-- الإجمالي -->
                    <div class="summary-section">
                        <div class="panel-title">
                            <i class="fas fa-calculator"></i>
                            الإجمالي
                        </div>
                        <div class="summary-row">
                            <span>الإجمالي قبل الخصم:</span>
                            <span id="subtotal">٠٫٠٠ ج.م</span>
                        </div>
                        <div class="summary-row">
                            <span>الخصم:</span>
                            <span id="discount-amount">٠٫٠٠ ج.م</span>
                        </div>

                        <div class="summary-row summary-total">
                            <span>الإجمالي النهائي:</span>
                            <span id="total-amount">٠٫٠٠ ج.م</span>
                        </div>
                    </div>

                    <!-- الدفع -->
                    <!-- في قسم الدفع، استبدال الكود الحالي بالكود التالي -->
                    <!-- الدفع -->
                    <!-- الدفع -->
                    <div class="payment-section">
                        <div class="panel-title">
                            <i class="fas fa-credit-card"></i>
                            الدفع
                        </div>
                        <div class="payment-toggle">
                            <div class="payment-option">
                                <input type="radio" id="payment-pending" name="payment" value="pending" checked>
                                <label for="payment-pending" class="payment-label">مؤجل</label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="payment-partial" name="payment" value="partial">
                                <label for="payment-partial" class="payment-label">جزئي</label>
                            </div>
                            <div class="payment-option">
                                <input type="radio" id="payment-paid" name="payment" value="paid">
                                <label for="payment-paid" class="payment-label">مدفوع</label>
                            </div>
                        </div>



                        <!-- قسم الدفع الكامل -->
                        <div class="full-payment" id="full-payment-section" style="display: none;">
                            <div class="payment-amounts">
                                <div class="payment-amount">
                                    <div class="label">الإجمالي</div>
                                    <div class="value" id="full-payment-total">&rlm;٠٠٫٠٠&nbsp;ج.م.&rlm;</div>
                                </div>
                                <div class="payment-amount">
                                    <div class="label">المدفوع</div>
                                    <div class="value" id="full-payment-paid_amount">&rlm;٠٫٠٠&nbsp;ج.م.&rlm;</div>
                                </div>
                                <div class="payment-amount">
                                    <div class="label">المتبقي</div>
                                    <div class="value" id="full-payment-remaining">&rlm;٠٫٠٠&nbsp;ج.م.&rlm;</div>
                                </div>
                            </div>
                            <div class="payment-methods">
                                <div class="payment-method selected" data-method="cash">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>نقدي</div>
                                </div>
                                <div class="payment-method" data-method="bank_transfer">
                                    <i class="fas fa-university"></i>
                                    <div>تحويل بنكي</div>
                                </div>
                                <div class="payment-method" data-method="check">
                                    <i class="fas fa-money-check"></i>
                                    <div>شيك</div>
                                </div>
                                <div class="payment-method" data-method="card">
                                    <i class="fas fa-credit-card"></i>
                                    <div>بطاقة</div>
                                </div>
                                <div class="payment-method" data-method="wallet">
                                    <i class="fas fa-wallet"></i>
                                    <div>محفظة</div>
                                    <small id="full-wallet-balance" style="font-size: 10px; display: block;"></small>
                                </div>
                            </div>
                            <div class="payment-input">
                                <input type="number" placeholder="المبلغ المدفوع" step="0.01" min="0" id="current-payment-full">
                                <button class="btn btn-primary add-payment-btn" data-type="full">إضافة دفعة</button>

                            </div>
                            <div class="payment-notes" style="margin-top: 10px;">
                                <label>ملاحظات الدفع:</label>
                                <textarea id="full-payment-notes" placeholder="أدخل ملاحظات الدفع (رقم الحساب، المرجع، إلخ)..." rows="2"></textarea>
                            </div>
                            <div class="payments-list" id="full-payments-list">
                                <!-- سيتم تعبئتها بالمدفوعات -->
                            </div>

                            <!-- ملاحظات الدفع للدفع الكامل -->

                        </div>

                        <!-- قسم الدفع الجزئي -->
                        <div class="partial-payment" id="partial-payment-section" style="display: none;">
                            <div class="payment-amounts">
                                <div class="payment-amount">
                                    <div class="label">الإجمالي</div>
                                    <div class="value" id="partial-payment-total">٠٫٠٠ ج.م</div>
                                </div>
                                <div class="payment-amount">
                                    <div class="label">المدفوع</div>
                                    <div class="value" id="partial-payment-paid_amount">٠٫٠٠ ج.م</div>
                                </div>
                                <div class="payment-amount">
                                    <div class="label">المتبقي</div>
                                    <div class="value" id="partial-payment-remaining">٠٫٠٠ ج.م</div>
                                </div>
                            </div>

                            <div class="payment-methods">
                                <div class="payment-method selected" data-method="cash">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>نقدي</div>
                                </div>
                                <div class="payment-method" data-method="bank_transfer">
                                    <i class="fas fa-university"></i>
                                    <div>تحويل بنكي</div>
                                </div>
                                <div class="payment-method" data-method="check">
                                    <i class="fas fa-money-check"></i>
                                    <div>شيك</div>
                                </div>
                                <div class="payment-method" data-method="card">
                                    <i class="fas fa-credit-card"></i>
                                    <div>بطاقة</div>
                                </div>
                                <div class="payment-method" data-method="wallet">
                                    <i class="fas fa-wallet"></i>
                                    <div>محفظة</div>
                                    <small id="partial-wallet-balance" style="font-size: 10px; display: block;"></small>
                                </div>
                            </div>

                            <!-- ملاحظات الدفع للجزئي -->


                            <div class="payment-input">
                                <input type="number" placeholder="المبلغ المدفوع" step="0.01" min="0" id="current-payment-partial">
                                <button class="btn btn-primary add-payment-btn" data-type="partial">إضافة دفعة</button>
                            </div>
                            <div class="payment-notes" style="margin-top: 10px;">
                                <label>ملاحظات الدفع:</label>
                                <textarea id="partial-payment-notes" placeholder="أدخل ملاحظات الدفع (رقم الحساب، المرجع، إلخ)..." rows="2"></textarea>
                            </div>
                            <div class="payments-list" id="partial-payments-list">
                                <!-- سيتم تعبئتها بالمدفوعات -->
                            </div>


                        </div>
                    </div>


                    <!-- الملاحظات -->
                    <div class="notes-section">
                        <div class="panel-title">
                            <i class="fas fa-sticky-note"></i>
                            ملاحظات الفاتورة
                        </div>
                        <textarea id="invoice-notes" placeholder="أدخل ملاحظات الفاتورة هنا..." rows="3"></textarea>
                    </div>



                    <div class="actions">
                        <button class="btn btn-outline" id="clear-btn">
                            <i class="fas fa-trash"></i> تفريغ
                        </button>
                        <button class="btn btn-primary" id="confirm-btn">
                            <i class="fas fa-check"></i> تأكيد الفاتورة
                        </button>
                    </div>
                </div>
            </div>
        </div>

        </div>
        </div>
        <!-- نماذج اختيار العملاء والمنتجات -->
        <div class="modal-backdrop" id="customers-modal">
            <div class="mymodal">
                <div class="title">اختيار العميل</div>

                <div class="search-box">
                    <input type="text" id="customer-search" placeholder="ابحث عن عميل...">
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>

                <div class="customers-list" id="customers-container">
                    <!-- سيتم تعبئتها بالعملاء -->
                </div>
            </div>
        </div>
        <div class="modal-backdrop" id="success-modal">
            <div class="mymodal">
                <div class="title" style="color: #28a745;">
                    <i class="fas fa-check-circle"></i>
                    تم إنشاء الفاتورة بنجاح
                </div>

                <div class="success-content" style="text-align: center; padding: 20px;">
                    <div style="font-size: 18px; margin-bottom: 15px;">
                        <strong>رقم الفاتورة: <span id="success-invoice-id"></span></strong>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div>حالة الدفع: <span id="success-payment-status"></span></div>
                        <div>الإجمالي: <span id="success-total-amount"></span></div>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: center; margin-top: 25px;">
                        <button class="btn btn-outline" id="stay-and-create">
                            <i class="fas fa-plus"></i> البقاء وإنشاء جديد
                        </button>
                        <button class="btn btn-primary" id="go-to-invoices">
                            <i class="fas fa-list"></i> الذهاب للفواتير
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop" id="products-modal">
            <div class="mymodal">
                <div class="title">اختيار المنتج</div>

                <div class="search-box">
                    <input type="text" id="product-search" placeholder="ابحث عن منتج...">
                    <div class="search-icon">
                        <i class="fas fa-search"></i>
                    </div>
                </div>

                <div class="products-list" id="products-container">
                    <!-- سيتم تعبئتها بالمنتجات -->
                </div>
            </div>
        </div>

        <!-- نموذج التأكيد -->
        <!-- نموذج التأكيد -->
        <div class="modal-backdrop" id="confirm-modal">
            <div class="mymodal">
                <div class="title">تأكيد إنشاء الفاتورة</div>

                <div id="confirm-content">
                    <!-- سيتم تعبئته بالبيانات -->
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end; margin-top: 20px;">
                    <button class="btn btn-outline" id="cancel-confirm">إلغاء</button>
                    <button class="btn btn-primary" id="print-only">طباعة فقط</button>
                    <button class="btn btn-primary" id="final-confirm">تأكيد وإنشاء</button>
                </div>
            </div>
        </div>


        <!-- نموذج إضافة عميل جديد -->
        <div class="modal-backdrop" id="add-customer-modal">
            <div class="mymodal">
                <div class="title">إضافة عميل جديد</div>

                <div id="add-customer-message" class="msg"></div>

                <div style="display: grid; gap: 12px; margin-top: 15px;">
                    <input type="text" id="new-customer-name" placeholder="الاسم" class="form-input">
                    <input type="text" id="new-customer-mobile" placeholder="رقم الموبايل (11 رقم)" class="form-input">
                    <input type="text" id="new-customer-city" placeholder="المدينة" class="form-input">
                    <input type="text" id="new-customer-address" placeholder="العنوان" class="form-input">
                    <textarea id="new-customer-notes" placeholder="ملاحظات عن العميل (اختياري)" class="form-input" rows="3"></textarea>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
                        <button type="button" id="cancel-add-customer" class="btn btn-outline">إلغاء</button>
                        <button type="button" id="submit-add-customer" class="btn btn-primary">حفظ وإختيار</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- نموذج إنشاء شغلانة -->
        <div class="modal-backdrop" id="create-work-order-modal">
            <div class="mymodal" style="max-width: 500px;">
                <div class="title">إنشاء شغلانة جديدة</div>

                <div id="create-work-order-message" class="msg"></div>

                <div style="display: grid; gap: 12px; margin-top: 15px;">
                    <input type="text" id="work-order-title" placeholder="عنوان الشغلانة" class="form-input" required>
                    <textarea id="work-order-description" placeholder="وصف تفصيلي (اختياري)" class="form-input" rows="3"></textarea>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label>تاريخ البدء</label>
                            <input type="date" id="work-order-start-date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label>الحالة</label>
                            <select id="work-order-status" class="form-input" class="d-none">
                                <option value="pending">قيد الانتظار</option>
                                <option value="in_progress">قيد التنفيذ</option>
                            </select>
                        </div>
                    </div>
                    <textarea id="work-order-notes" placeholder="ملاحظات إضافية (اختياري)" class="form-input" rows="2"></textarea>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
                        <button type="button" id="cancel-create-work-order" class="btn btn-outline">إلغاء</button>
                        <button type="button" id="submit-create-work-order" class="btn btn-primary">
                            <span class="btn-text">إنشاء الشغلانة</span>

                            <span class="spinner-border spinner-border-sm ms-2" role="status" style="display: none;"></span>

                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal تفاصيل FIFO -->
        <div class="modal-backdrop" id="batchDetailModal_backdrop" style="display: none;">
            <div class="mymodal">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div><strong id="batchTitle">تفاصيل FIFO</strong></div>
                    <div><button id="closeBatchDetailBtn" class="btn ghost">إغلاق</button></div>
                </div>
                <div id="batchDetailBody" class="custom-table-wrapper" style="margin-top:10px">
                    <!-- سيتم تعبئته بالبيانات -->
                </div>
            </div>
        </div>
        <!-- Toast Notification -->
        <div class="toast" id="toast">
            <i class="fas fa-check-circle"></i>
            <span id="toast-message">تمت العملية بنجاح</span>
        </div>

        <div id="print-container" style="display: none;">
            <!-- سيتم تعبئته ديناميكياً -->
        </div>

        </div>
        </div>
        </div>


        <script type="module">
            import apisForInvoices from "./constant/api_links.js"

            const App = {
                async init() {
                    await DataManager.loadProductSelect();
                    await DataManager.loadProductsModal();
                    await DataManager.loadCustomersModal();

                    EventManager.setup();
                    UI.update();
                    UI.updatePriceButtons();
                    // UI.focusBarcodeField();
                    UI.focusProductSearch()

                    UI.addIntegratedSearch();
                    UI.setupIntegratedSearch();


                    // // جلب رقم الفاتورة التالي

                }
            };

            // بدء التطبيق عند تحميل الصفحة
            document.addEventListener('DOMContentLoaded', () => {
                App.init();
            });

            const AppData = {
                products: [],
                customers: []
            };

            // ============================
            // حالة التطبيق
            // ============================
            const AppState = {
                invoiceItems: [],
                currentCustomer: null,
                currentWorkOrder: null, // الشغلانة المختارة
                availableWorkOrders: [],
                payments: [],
                discount: {
                    type: "percent",
                    value: 0
                },
                currentInvoiceId: null // لتخزين معرف الفاتورة الحالية
                    ,
                currentPaymentMethod: 'cash',
                currentPriceType: 'wholesale', // 'retail' or 'wholesale'
                currentPaymentMethod: 'cash',
                csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content'),

                fifoData: {}, // أضف هذا السطر - تخزين بيانات FIFO لكل بند
                currentInvoiceNumber: '--',
                lastCreatedInvoice: null,
                invoiceNotes: '', // ملاحظات الفاتورة
                currentPaymentMethod: 'cash',
                paymentNotes: '', // 
                fullPaymentNotes: '' // ملاحظات الدفع الكامل
            };
            // ============================
            // إدارة عناصر DOM
            // ============================
            const DOM = {
                // حقول الإدخال
                productSelect: document.getElementById('product-select'),
                productQty: document.getElementById('product-qty'),
                productPrice: document.getElementById('product-price'),
                barcodeInput: document.getElementById('barcode-input'),
                discountType: document.getElementById('discount-type'),
                discountValue: document.getElementById('discount-value'),
                currentPaymentFull: document.getElementById('current-payment-full'),
                currentPaymentPartial: document.getElementById('current-payment-partial'),
                transferInfo: document.getElementById('transfer-info'),
                integratedProductSearch: document.getElementById('integrated-product-search'),
                // i document.get

                // الأزرار
                addProductBtn: document.getElementById('add-product-btn'),
                scanBarcodeBtn: document.getElementById('scan-barcode'),
                changeCustomerBtn: document.getElementById('change-customer'),
                clearBtn: document.getElementById('clear-btn'),
                confirmBtn: document.getElementById('confirm-btn'),
                addPaymentBtn: document.querySelectorAll('.add-payment-btn'),
                priceRetailBtn: document.getElementById('price-retail-btn'),
                priceWholesaleBtn: document.getElementById('price-wholesale-btn'),
                cancelConfirm: document.getElementById('cancel-confirm'),
                finalConfirm: document.getElementById('final-confirm'),

                // العناصر المعروضة
                invoiceItems: document.getElementById('invoice-items'),
                subtotal: document.getElementById('subtotal'),
                discountAmount: document.getElementById('discount-amount'),

                totalAmount: document.getElementById('total-amount'),
                // paymentTotal: document.getElementById('payment-total'),
                // paymentPaid: document.getElementById('payment-paid'),
                // paymentPaidAmmount: document.getElementById('payment-paid_amount'),
                // paymentRemaining: document.getElementById('payment-remaining'),
                partialpaymentsList: document.getElementById('partial-payments-list'),
                fullpaymentsList: document.getElementById('full-payments-list'),
                partialPaymentSection: document.getElementById('partial-payment-section'),
                transferDetails: document.getElementById('transfer-details'),
                toast: document.getElementById('toast'),
                toastMessage: document.getElementById('toast-message'),
                confirmContent: document.getElementById('confirm-content'),

                // النماذج
                customersModal: document.getElementById('customers-modal'),
                productsModal: document.getElementById('products-modal'),
                confirmModal: document.getElementById('confirm-modal'),
                customersContainer: document.getElementById('customers-container'),
                productsContainer: document.getElementById('products-container'),
                customerSearch: document.getElementById('customer-search'),
                productSearch: document.getElementById('product-search'),
                batchDetailModal: document.getElementById("batchDetailModal_backdrop"),
                batchDetailBody: document.getElementById("batchDetailBody"),

                walletBalanceElementPartial: document.getElementById('partial-wallet-balance'),
                walletBalanceElementFull: document.getElementById('full-wallet-balance'),
                // section
                customerActions: document.getElementById('customer-actions'),


            };

            // التحقق من وجود العناصر الأساسية
            function validateDOMElements() {
                const requiredElements = [
                    'product-select', 'invoice-items', 'subtotal', 'total-amount',
                    'change-customer', 'confirm-btn'
                ];

                const missingElements = requiredElements.filter(id => !document.getElementById(id));

                if (missingElements.length > 0) {
                    console.error('عناصر DOM مفقودة:', missingElements);
                    return false;
                }

                return true;
            }
            // ============================
            // دوال التواصل مع الخادم
            // ============================
            const ApiManager = {
                async request(endpoint, data = null, external = false) {
                    const url = external ? endpoint : `?action=${endpoint}`;
                    const options = {
                        method: data ? 'POST' : 'GET',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        }
                    };

                    if (data) {
                        const formData = new URLSearchParams();
                        for (const key in data) {
                            formData.append(key, data[key]);
                        }
                        options.body = formData;
                    }

                    try {
                        const response = await fetch(url, options);
                        const result = await response.json();
                        return result;
                    } catch (error) {
                        console.error('API Error:', error);
                        Helpers.showToast('خطأ في الاتصال بالخادم', 'error');
                        return {
                            ok: false,
                            error: 'خطأ في الاتصال'
                        };
                    }
                },

                // جلب المنتجات
                async loadProducts(search = '') {
                    const result = await this.request('products', {
                        q: search
                    });
                    if (result.ok) {
                        AppData.products = result.products;
                        return result.products;
                    }
                    return [];
                },
                async getProductByBarcode(barcode) {
                    const result = await this.request(`product_by_barcode&barcode=${encodeURIComponent(barcode)}`);

                    if (result.ok && result.product) {
                        return result.product;
                    }
                    return null;
                },
                // في ApiManager أضف هذه الدالة:
                async simulateFIFO(productId, quantity) {
                    const result = await this.request(`fifo_simulation&product_id=${productId}&qty=${quantity}`);
                    return result;
                },
                // جلب العملاء
                async loadCustomers(search = '') {
                    const result = await this.request('customers', {
                        q: search
                    });
                    if (result.ok) {
                        AppData.customers = result.customers;
                        return result.customers;
                    }
                    return [];
                },

                // إضافة عميل جديد
                async addCustomer(customerData) {
                    const result = await this.request('add_customer', {
                        ...customerData,
                        csrf_token: AppState.csrfToken
                    });
                    return result;
                },

                // اختيار عميل
                async selectCustomerApi(customerId) {
                    const result = await this.request('select_customer', {
                        customer_id: customerId,
                        csrf_token: AppState.csrfToken
                    });
                    return result;
                },

                // حفظ الفاتورة




                async saveInvoice(invoiceData) {
                    'Saving invoice with data:', invoiceData);

                    // const result = await this.request(apisForInvoices.saveInvoice, invoiceData, true);

                    // if (!result.ok) {
                    //     return result;
                    // }



                    // return {
                    //     ...result,
                    //     message: this.getInvoiceMessage(result)
                    // };
                },
                getInvoiceMessage(result) {
                    if (result.paid_amount <= 0) {
                        return 'تم إنشاء فاتورة مؤجلة';
                    }

                    if (result.remaining_amount > 0) {
                        return 'تم إنشاء فاتورة بدفع جزئي';
                    }

                    return 'تم إنشاء فاتورة مدفوعة بالكامل';
                },

                // استخدام الدالة


                // جلب رقم الفاتورة التالي
                async getNextInvoiceNumber() {
                    const result = await this.request('next_invoice_number');
                    return result.ok ? result.next : 1;
                },
                // في ApiManager:
                async loadWorkOrders(customerId, search = '') {
                    try {
                        const response = await fetch(
                            `${apisForInvoices.getCustomerWorkOrders}${encodeURIComponent(customerId)}`, {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                }
                            }
                        );

                        const result = await response.json();


                        return result.success ? (result.work_orders || []) : [];
                    } catch (err) {
                        console.error('loadWorkOrders error:', err);
                        return [];
                    }
                },


                async createWorkOrder(workOrderData) {

                    try {
                        const response = await fetch(apisForInvoices.createWorkOrder, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(workOrderData)
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const data = await response.json();
                        return data
                    } catch (error) {
                        Helpers.showToast('❌ خطأ في إنشاء الشغلانة:', error.message);
                        return {
                            success: false,
                            message: error.message
                        };
                    }
                },
            };


            // ============================
            // دوال المساعدة المحدثة
            // ============================
            const Helpers = {
                // تنسيق العملة (جنيه مصري)
                formatCurrency(amount) {
                    return new Intl.NumberFormat('ar-EG', {
                        style: 'currency',
                        currency: 'EGP',
                        minimumFractionDigits: 2
                    }).format(amount);
                },

                // الحصول على نص طريقة الدفع
                getPaymentMethodText(method) {
                    const methods = {
                        'cash': 'نقدي',
                        'bank': 'تحويل',
                        'card': 'بطاقة',
                        'wallet': 'محفظة',
                        'check': "شيك",
                        'other': 'أخرى'
                    };
                    return methods[method] || method;
                },

                // حساب الإجمالي
               calculateTotal() {
                // في Helpers - إلغاء استخدام خصم الفاتورة:

    // حساب مجموع صافي البنود (بعد خصم البنود)
    const subtotal = AppState.invoiceItems.reduce((sum, item) => {
        return sum + (item.total_after_discount || 0);
    }, 0);
    
    // خصم الفاتورة (سيتم إهماله عندما discount_scope = 'items')
    let discountAmount = 0;
    if (AppState.discount.type === 'percent') {
        discountAmount = subtotal * (AppState.discount.value / 100);
    } else {
        discountAmount = AppState.discount.value;
    }
    
    const afterDiscount = Math.max(0, subtotal - discountAmount);
    
    return afterDiscount;
},

                // حساب الربح
                calculateProfit() {
    // الإيرادات: مجموع صافي البنود
    const totalRevenue = AppState.invoiceItems.reduce((sum, item) => {
        return sum + (item.total_after_discount || 0);
    }, 0);
    
    // التكلفة: مجموع (الكمية × تكلفة الوحدة)
    const totalCost = AppState.invoiceItems.reduce((sum, item) => {
        return sum + (item.quantity * item.cost);
    }, 0);
    
    // خصم الفاتورة (قد يكون صفراً)
    let discountAmount = 0;
    if (AppState.discount.type === 'percent') {
        discountAmount = totalRevenue * (AppState.discount.value / 100);
    } else {
        discountAmount = AppState.discount.value;
    }
    
    const revenueAfterDiscount = Math.max(0, totalRevenue - discountAmount);
    const profitBeforeTax = revenueAfterDiscount - totalCost;
    const netProfit = profitBeforeTax;
    
    return {
        totalRevenue,
        totalCost,
        discountAmount,
        revenueAfterDiscount,
        profitBeforeTax,
        netProfit
    };

    
},
// في Helpers - إضافة دالة جديدة:
calculateInvoiceTotals() {
    let subtotal = 0; // مجموع البنود قبل الخصم
    let totalDiscount = 0; // مجموع خصومات البنود
    let netTotal = 0; // صافي الفاتورة
    
    AppState.invoiceItems.forEach(item => {
        // حساب إجمالي البند قبل الخصم
        const itemTotal = item.quantity * item.price;
        subtotal += itemTotal;
        
        // حساب خصم البند
        let itemDiscount = 0;
        if (item.discount_type === 'percent') {
            const percentValue = Math.max(0, Math.min(100, parseFloat(item.discount_value) || 0));
            itemDiscount = itemTotal * (percentValue / 100);
        } else {
            itemDiscount = parseFloat(item.discount_value) || 0;
        }
        
        // التأكد من أن الخصم لا يتجاوز قيمة البند
        if (itemDiscount > itemTotal) {
            itemDiscount = itemTotal * 0.9999;
        }
        
        itemDiscount = Math.round(itemDiscount * 100) / 100;
        totalDiscount += itemDiscount;
        
        // حساب صافي البند
        const itemNet = Math.max(0, itemTotal - itemDiscount);
        netTotal += itemNet;
    });
    
    // تقريب القيم
    subtotal = Math.round(subtotal * 100) / 100;
    totalDiscount = Math.round(totalDiscount * 100) / 100;
    netTotal = Math.round(netTotal * 100) / 100;
    
    return {
        subtotal: subtotal,
        totalDiscount: totalDiscount,
        netTotal: netTotal
    };
},

// تحديث UI.updateSummary():
             // دالة لحساب خصم البند وتحديث القيم ذات الصلة
// في Helpers.calculateItemDiscount() - تحديث لحفظ النتائج:
calculateItemDiscount(item) {
    if (!item) return;

    const qty   = parseFloat(item.quantity) || 0;
    const price = parseFloat(item.price) || 0;

    if (qty <= 0 || price <= 0) {
        item.total_before_discount = 0;
        item.discount_amount = 0;
        item.total_after_discount = 0;
        return;
    }

    const totalBefore = qty * price;
    const discountValue = parseFloat(item.discount_value) || 0;

    // طلب تأكيد عند 100%
    if (item.discount_type === 'percent' && discountValue === 100) {
        const confirmed = confirm(
            '⚠️ سيتم بيع البند مجانًا (خصم 100%). هل أنت متأكد؟'
        );

        if (!confirmed) {
            item.discount_value = item._last_discount_value || 0;
            return;
        }
    }

    let discountAmount = 0;

    if (item.discount_type === 'percent') {
        discountAmount = totalBefore * (discountValue / 100);
    }

    if (item.discount_type === 'amount') {
        discountAmount = Math.min(totalBefore, discountValue);
    }

    item._last_discount_value = discountValue;

    item.total_before_discount = totalBefore;
    item.discount_amount = discountAmount;
    item.total_after_discount = totalBefore - discountAmount;
}
,
                // إظهار Toast Notification
                showToast(message, type) {
                    DOM.toastMessage.textContent = message;
                    DOM.toast.className = `toast ${type} show`;

                    setTimeout(() => {
                        DOM.toast.classList.remove('show');
                    }, 3000);
                },

                // التحقق من رقم الهاتف
                validatePhone(phone) {
                    const digits = phone.replace(/\D/g, '');
                    return digits.length >= 7 && digits.length <= 15;
                },

                // في Helpers أضف:
                calculateAverageCost(allocations) {
                    if (!allocations || allocations.length === 0) return 0;

                    const totalQty = allocations.reduce((sum, alloc) => sum + alloc.taken, 0);
                    const totalCost = allocations.reduce((sum, alloc) => sum + alloc.cost, 0);

                    return totalQty > 0 ? totalCost / totalQty : 0;
                },
                // في Helpers، نعدل:
                getPaymentMethodText(method) {
                    const methods = {
                        'cash': 'نقدي',
                        'bank_transfer': 'تحويل بنكي',
                        'check': 'شيك',
                        'card': 'بطاقة'
                    };
                    return methods[method] || method;
                },
                // دالة لحساب المخزون المتبقي بعد استبعاد البنود الحالية
                calculateAvailableStock(productId, excludeIndex = null) {
                    const product = AppData.products.find(p => +p.id === productId);
                    if (!product) return 0;

                    const totalStock = +product.remaining_active || 0;

                    const reserved = AppState.invoiceItems.reduce((sum, item, idx) => {
                        if (item.id === productId) {
                            if (excludeIndex !== null && idx === excludeIndex) return sum;
                            return sum + item.quantity;
                        }
                        return sum;
                    }, 0);

                    return totalStock - reserved;
                }


            };

            // ============================
            // دوال إدارة البيانات المحدثة
            // ============================
            const DataManager = {
                // تحميل قائمة المنتجات في الـ select

                async loadProductSelect() {
                    await ApiManager.loadProducts();
                    DOM.productSelect.innerHTML = '<option value="">اختر منتج للإضافة</option>';

                    AppData.products.forEach(product => {
                        // التحقق من المخزون
                        const availableStock = product.remaining_active || 0;
                        const isOutOfStock = availableStock <= 0;

                        const option = document.createElement('option');
                        option.value = product.id;

                        if (isOutOfStock) {
                            option.textContent = `${product.name} - ⛔ مستهلك`;
                            option.disabled = true;
                            option.style.color = '#ff6b6b';
                        } else {
                            // عرض كلا السعرين
                            const retailPrice = product.retail_price || 0;
                            const wholesalePrice = product.product_sale_price || 0;
                            option.textContent = `${product.name} - قطاعي: ${Helpers.formatCurrency(retailPrice)} | جملة: ${Helpers.formatCurrency(wholesalePrice)}`;
                        }



                        option.dataset.product = JSON.stringify(product);
                        DOM.productSelect.appendChild(option);

                        // إضافة حدث التغيير بعد تحميل المنتجات

                    });


                },

                // تحميل المنتجات في النافذة
                async loadProductsModal(search = '') {
                    await ApiManager.loadProducts(search);
                    DOM.productsContainer.innerHTML = '';

                    if (AppData.products.length === 0) {
                        DOM.productsContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--muted);">لا توجد منتجات</div>';
                        return;
                    }

                    AppData.products.forEach(product => {
                        const availableStock = product.remaining_active || 0;
                        const isOutOfStock = availableStock <= 0;
                        const stockStatus = isOutOfStock ? '⛔ مستهلك' : `متبقي: ${availableStock}`;
                        const cardClass = isOutOfStock ? 'product-card out-of-stock disabled' : 'product-card';

                        const card = document.createElement('div');
                        card.className = cardClass;
                        card.dataset.id = product.id;

                        if (isOutOfStock) {
                            card.innerHTML = `
                        <div class="product-info">
                            <h3 style="color: #ff6b6b;">${product.name} ⛔</h3>
                            <div class="product-prices">
                                <div class="price-wholesale">جملة: ${Helpers.formatCurrency(product.product_sale_price || 0)}</div>
                                <div class="price-retail">قطاعي: ${Helpers.formatCurrency(product.retail_price||0)}</div>
                            </div>
                            <div class="product-meta">
                                <span>${product.product_code} • ID:${product.id}</span>
                                <span class="stock-status out-of-stock">${stockStatus}</span>
                            </div>
                        </div>
                        <div class="product-actions">
                            <button class="btn btn-outline btn-sm" disabled>غير متاح</button>
                        </div>
                    `;
                        } else {
                            card.innerHTML = `
                        <div class="product-info">
                            <h3>${product.name}</h3>
                            <div class="product-prices">
                                <div class="price-wholesale">جملة: ${Helpers.formatCurrency(product.product_sale_price || 0)}</div>
                                <div class="price-retail">قطاعي: ${Helpers.formatCurrency(product.retail_price||0)}</div>
                                <div class="cost_price">اخر سعر شراء: ${Helpers.formatCurrency(product.last_purchase_price||0)}</div>
                            </div>

                            <div class="product-meta">
                                <span>${product.product_code} • ID:${product.id}</span>
                                <span class="stock-status">${stockStatus}</span>
                            </div>
                        </div>
                        <div class="product-actions">
                            <button class="btn btn-primary btn-sm select-price" data-type="retail" data-price="${product.retail_price || 0}">قطاعي</button>
                            <button class="btn btn-success btn-sm select-price" data-type="wholesale" data-price="${product.product_sale_price}">جملة</button>
                        </div>
                    `;
                        }

                        DOM.productsContainer.appendChild(card);
                    });
                },

                // تحميل العملاء في النافذة
                async loadCustomersModal(search = '') {
                    await ApiManager.loadCustomers(search);
                    DOM.customersContainer.innerHTML = '';

                    if (AppData.customers.length === 0) {
                        DOM.customersContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--muted);">لا توجد عملاء</div>';
                        return;
                    }

                    AppData.customers.forEach(customer => {
                        const card = document.createElement('div');
                        card.className = 'customer-card';
                        card.dataset.id = customer.id;
                        card.innerHTML = `
                        <div class="customer-info">
                            <h3>${customer.name}</h3>
                            <div class="customer-meta">
                                <span>${customer.mobile}</span>
                                <span>${customer.city || ''}</span>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm select-customer" style="margin-top: 15px;">اختيار</button>
                    `;
                        DOM.customersContainer.appendChild(card);
                    });
                },

                async findProductByBarcode(barcode) {
                    if (!barcode || barcode.trim() === '') {
                        Helpers.showToast('الرجاء إدخال باركود', 'error');
                        return false;
                    }

                    Helpers.showToast('جاري البحث عن المنتج...', 'info');

                    try {
                        const product = await ApiManager.getProductByBarcode(barcode);

                        if (product) {
                            // التحقق إذا كان المنتج مستهلكاً
                            const availableStock = parseFloat(product.remaining_active) || 0;
                            if (availableStock <= 0) {
                                Helpers.showToast('هذا المنتج مستهلك ولا يمكن إضافته', 'error');
                                return false;
                            }

                            // تحديث واجهة المستخدم
                            if (DOM.productSelect) {
                                DOM.productSelect.value = product.id;
                                // إذا لم يكن المنتج موجوداً في القائمة، نضيفه
                                let optionExists = false;
                                for (let option of DOM.productSelect.options) {
                                    if (option.value == product.id) {
                                        optionExists = true;
                                        break;
                                    }
                                }
                                if (!optionExists) {
                                    const option = document.createElement('option');
                                    option.value = product.id;
                                    option.textContent = `${product.name} - ${Helpers.formatCurrency(product.selling_price)}`;
                                    option.dataset.product = JSON.stringify(product);
                                    DOM.productSelect.appendChild(option);
                                }
                            }

                            UI.updatePriceField(product);
                            if (DOM.productQty) DOM.productQty.focus();
                            Helpers.showToast(`تم العثور على ${product.name}`, 'success');
                            return true;
                        } else {
                            Helpers.showToast('لم يتم العثور على المنتج بهذا الباركود', 'error');
                            return false;
                        }
                    } catch (error) {
                        console.error('Error finding product by barcode:', error);
                        Helpers.showToast('خطأ في البحث عن المنتج', 'error');
                        return false;
                    }
                },



                // تصفية المنتجات
                filterProducts(query) {
                    const products = document.querySelectorAll('.product-card');

                    products.forEach(product => {
                        const nameElement = product.querySelector('h3');
                        if (nameElement) {
                            const name = nameElement.textContent.toLowerCase();
                            if (name.includes(query.toLowerCase())) {
                                product.style.display = 'flex';
                            } else {
                                product.style.display = 'none';
                            }
                        }
                    });
                },

                // تصفية العملاء
                filterCustomers(query) {
                    const customers = document.querySelectorAll('.customer-card');
                    customers.forEach(customer => {
                        const nameElement = customer.querySelector('h3');
                        if (nameElement) {
                            const name = nameElement.textContent.toLowerCase();
                            if (name.includes(query.toLowerCase())) {
                                customer.style.display = 'flex';
                            } else {
                                customer.style.display = 'none';
                            }
                        }
                    });
                },


            };

            const UI = {
                // تحديث واجهة المستخدم بالكامل
                update() {
                    this.updateInvoiceDisplay();
                    this.updateSummary();
                    this.updatePaymentSection();
                    this.updateCustomerUI();
                    this.updateProfitDisplay();
                    // this.focusBarcodeField();
                    this.focusProductSearch()
                    this.updateWalletBalance()

                },

                // في UI أضف:
                // في UI أضف:
                setupIntegratedSearch() {
                    const searchInput = document.getElementById('integrated-product-search');
                    const resultsContainer = document.getElementById('integrated-search-results');

                    if (!searchInput || !resultsContainer) return;

                    let currentSelectionIndex = -1;
                    let searchResults = [];

                    // البحث أثناء الكتابة
                    searchInput.addEventListener('input', function() {
                        const query = this.value.trim();
                        currentSelectionIndex = -1;

                        if (query.length < 1) {
                            resultsContainer.style.display = 'none';
                            return;
                        }

                        // البحث في المنتجات
                        searchResults = AppData.products.filter(product => {
                            return product.name.includes(query) ||
                                product.product_code.includes(query) ||
                                (product.barcode && product.barcode.includes(query));
                        });

                        renderSearchResults(query);
                    });

                    // عرض النتائج مع التمييز
                    function renderSearchResults(query) {
                        if (searchResults.length === 0) {
                            resultsContainer.innerHTML = `
                <div class="no-results" style="padding: 20px; text-align: center; color: #666;">
                    <i class="fas fa-search"></i>
                    <div>لم يتم العثور على "${query}"</div>
                </div>
            `;
                            resultsContainer.style.display = 'block';
                            return;
                        }

                        let html = '';

                        searchResults.forEach((product, index) => {
                            // (product);

                            const isSelected = index === currentSelectionIndex;
                            const productName = highlightText(product.name, query);
                            const productCode = highlightText(product.product_code, query);

                            html += `
                <div class="search-result-item ${isSelected ? 'selected' : ''}" 
                     data-index="${index}"
                     >
                    
                    <div style="flex: 1;">
                        <div style="font-weight: 500; margin-bottom: 4px;">
                            ${productName}
                        </div>
                        <div style="font-size: 12px; color: #666;">
                            <span>${productCode}</span>
                            <span style="margin: 0 8px;">•</span>
                            <span>المخزون: ${product.remaining_active || 0}</span>
                        </div>
                    </div>
                    
                    <div style="text-align: left; min-width: 120px;">
                        <div style="font-size: 14px; color: #28a745;">
                            قطاعي: ${Helpers.formatCurrency(product.retail_price || 0)}
                        </div>
                        <div style="font-size: 14px; color: #007bff;">
                            جملة: ${Helpers.formatCurrency(product.selling_price || product.product_sale_price||0)}
                        </div>
                    </div>
                </div>
            `;
                        });

                        resultsContainer.innerHTML = html;
                        resultsContainer.style.display = 'block';

                        // إضافة أحداث للنقر
                        document.querySelectorAll('.search-result-item').forEach(item => {
                            item.addEventListener('click', () => {
                                selectProduct(parseInt(item.dataset.index));
                            });
                        });
                    }

                    // دالة لتظليل النص المطابق
                    function highlightText(text, query) {
                        if (!query) return text;

                        const regex = new RegExp(`(${query})`, 'gi');
                        return text.replace(regex, '<mark style="background-color: #fff3cd; padding: 0 2px; border-radius: 2px;">$1</mark>');
                    }

                    // اختيار المنتج
                    function selectProduct(index) {
                        if (index >= 0 && index < searchResults.length) {
                            const product = searchResults[index];

                            // تحديث الحقول
                            DOM.productSelect.value = product.id;
                            UI.updatePriceField(product);

                            // التركيز على الكمية واختيار النص
                            setTimeout(() => {
                                if (DOM.productQty) {
                                    DOM.productQty.focus();
                                    DOM.productQty.select();
                                }
                            }, 50);

                            // إخفاء النتائج
                            resultsContainer.style.display = 'none';
                            searchInput.value = '';

                            Helpers.showToast(`تم اختيار ${product.name}`, 'success');
                        }
                    }

                    // **التنقل باستخدام الأسهم**
                    searchInput.addEventListener('keydown', (e) => {
                        if (!resultsContainer.style.display || resultsContainer.style.display === 'none') {
                            return;
                        }

                        switch (e.key) {
                            case 'ArrowDown':
                                e.preventDefault();
                                currentSelectionIndex = Math.min(currentSelectionIndex + 1, searchResults.length - 1);
                                renderSearchResults(searchInput.value);
                                scrollToSelected();
                                break;

                            case 'ArrowUp':
                                e.preventDefault();
                                currentSelectionIndex = Math.max(currentSelectionIndex - 1, 0);
                                renderSearchResults(searchInput.value);
                                scrollToSelected();
                                break;

                            case 'Enter':
                                e.preventDefault();
                                if (currentSelectionIndex >= 0) {
                                    selectProduct(currentSelectionIndex);
                                } else if (searchResults.length > 0) {
                                    // إذا لم يتم التحديد، اختر أول نتيجة
                                    selectProduct(0);
                                }
                                break;

                            case 'Escape':
                                resultsContainer.style.display = 'none';
                                break;
                        }
                    });

                    // التمرير للعنصر المحدد
                    function scrollToSelected() {
                        const selectedElement = resultsContainer.querySelector('.selected');
                        if (selectedElement) {
                            selectedElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'nearest'
                            });
                        }
                    }

                    // إغلاق النتائج عند النقر خارجها
                    document.addEventListener('click', (e) => {
                        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                            resultsContainer.style.display = 'none';
                        }
                    });
                }, // في App.init() أو في HTML أضف:
                addIntegratedSearch() {
                    const searchHTML = `
        <div class="integrated-search-container" style="position: relative; margin-bottom: 15px;">
            <div class="search-input-wrapper">
                <input type="text" 
                       id="integrated-product-search" 
                       class="form-control"
                       placeholder="🔍 اكتب اسم المنتج أو الكود... (استخدم ↑↓ للتنقل، Enter للتحديد)"
                       style="padding-right: 40px;">
                <div class="search-icon" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #666;">
                    <i class="fas fa-search"></i>
                </div>
            </div>
            
            <div id="integrated-search-results" 
                 class="search-results-dropdown"
                 style="display: none; position: absolute; top: 100%; left: 0; right: 0; 
                        background: white; border: 1px solid #ddd; border-radius: 5px;
                        max-height: 400px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <!-- النتائج تظهر هنا -->
            </div>
        </div>
    `;

                    // وضع حقل البحث فوق حقول الإدخال
                    const productForm = document.querySelector('.product-form');
                    if (productForm) {
                        productForm.insertAdjacentHTML('afterbegin', searchHTML);
                    }
                },
                // تركيز على حقل الباركود
                focusBarcodeField() {
                    if (DOM.barcodeInput) {
                        setTimeout(() => {
                            DOM.barcodeInput.focus();
                            DOM.barcodeInput.select(); // اختيار النص الموجود لتسهيل المسح
                        }, 100);
                    }
                },
                focusProductSearch() {
                    if (DOM.integratedProductSearch) {
                        setTimeout(() => {
                            DOM.integratedProductSearch.focus();
                            DOM.integratedProductSearch.select(); // اختيار النص الموجود لتسهيل المسح
                        }, 100);
                    }
                },


                // تحديث واجهة العميل
                updateCustomerUI() {
                    const customerSection = document.querySelector('.customer-section');
                    if (!customerSection) return;

                    const customerAvatar = customerSection.querySelector('.customer-avatar');
                    const customerName = customerSection.querySelector('.customer-name');
                    const customerDetails = customerSection.querySelector('.customer-details');

                    if (AppState.currentCustomer) {
                        customerAvatar.textContent = AppState.currentCustomer.name.charAt(0);
                        customerName.textContent = AppState.currentCustomer.name;
                        customerDetails.textContent = `${AppState.currentCustomer.mobile} - ${AppState.currentCustomer.city || ''}`;
                    } else {
                        customerAvatar.textContent = '?';
                        customerName.textContent = 'لم يتم اختيار عميل';
                        customerDetails.textContent = 'يرجى اختيار عميل';
                    }
                },
                // دالة جديدة لتحديث حالة أزرار نوع السعر

                updatePriceButtons() {
                    if (DOM.priceRetailBtn && DOM.priceWholesaleBtn) {
                        // إزالة الكلاس النشط من جميع الأزرار أولاً
                        DOM.priceRetailBtn.classList.remove('active');
                        DOM.priceWholesaleBtn.classList.remove('active');

                        // إضافة الكلاس النشط للزر المحدد فقط
                        if (AppState.currentPriceType === 'retail') {
                            DOM.priceRetailBtn.classList.add('active');
                        } else {
                            DOM.priceWholesaleBtn.classList.add('active');
                        }
                    }
                },
                updatePriceField(product = null) {
                    if (!DOM.productSelect || !DOM.productPrice) return;

                    if (!product) {
                        const productId = parseInt(DOM.productSelect.value);
                        if (productId) {
                            product = AppData.products.find(p => +p.id === productId);
                        }
                    }

                    if (product) {


                        const price = AppState.currentPriceType === 'retail' ?
                            product.retail_price || 0 : product.selling_price || product.product_sale_price || 0;

                        DOM.productPrice.value = price;
                        this.updatePriceButtons();

                    }
                },

                showFIFODetails(item, fifoData) {
                    if (!DOM.batchDetailModal || !DOM.batchDetailBody) return;


                    let html = `<h4>تفاصيل FIFO — ${item.name}</h4>`;
                    html += `<table class="fifo-table" style="width:100%"><thead><tr><th>رقم الدفعة</th><th>التاريخ</th><th>المتبقي قبل السحب</th><th>المتبقي بعد السحب المطلوب</th><th>سعر الشراء</th><th>مأخوذ</th><th>تكلفة</th></tr></thead><tbody>`;

                    fifoData.allocations.forEach(alloc => {
                        html += `<tr>
                    <td class="monos">${alloc.batch_id}</td>
                    <td>${alloc.received_at}</td>
                    <td>${alloc.remaining_before}</td>
                    <td>${alloc.remaining_after}</td>
                    <td>${alloc.unit_cost}</td>
                    <td>${alloc.taken}</td>
                    <td>${alloc.cost}</td>
                </tr>`;
                    });

                    html += `</tbody></table>`;
                    html += `<div style="margin-top:8px"><strong>إجمالي تكلفة البند:</strong> ${Helpers.formatCurrency(fifoData.total_cost)}</div>`;

                    DOM.batchDetailBody.innerHTML = html;
                    DOM.batchDetailModal.style.display = 'flex';
                },

                // في UI أضف:
                async validateItemQuantity(index, quantity) {
                    const item = AppState.invoiceItems[index];
                    if (!item) return;

                    // محاكاة FIFO للتحقق من الكمية المتاحة
                    const fifoResult = await ApiManager.simulateFIFO(item.id, quantity);
                    if (fifoResult.ok) {
                        item.availableStock = fifoResult.allocations.reduce((sum, alloc) => sum + alloc.taken, 0);
                        item.sufficient = fifoResult.sufficient;

                        // تحديث تكلفة البند بناءً على FIFO
                        if (fifoResult.allocations.length > 0) {
                            item.cost = Helpers.calculateAverageCost(fifoResult.allocations);
                            AppState.fifoData[index] = fifoResult;
                        }
                    }
                }, // تحديث عرض الفاتورة
               updateInvoiceDisplay() {
    if (!DOM.invoiceItems) return;

    DOM.invoiceItems.innerHTML = '';

    AppState.invoiceItems.forEach((item, index) => {
        // تحديث حسابات الخصم قبل العرض
        Helpers.calculateItemDiscount(item);
        
        // تنسيق قيمة الخصم للعرض
        const discountDisplay = item.discount_type === 'percent' 
            ? `${item.discount_value}%` 
            : Helpers.formatCurrency(item.discount_value || 0);

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div>${item.name}</div>
                <small style="color: var(--muted);">${item.product_code}</small>
            </td>
            <td>
                <input type="number" 
                       class="input-qty" 
                       value="${item.quantity}" 
                       min="0.01" 
                       step="0.01" 
                       data-index="${index}" 
                       data-id="${item.id}"
                       style="width: 100%;">
            </td>
            <td>
                <input type="number" 
                       class="input-price" 
                       value="${item.price}" 
                       step="0.01" 
                       min="0" 
                       data-index="${index}"
                       style="width: 100%;">
            </td>
            <td>
                <span class="price-type-badge ${item.priceType === 'retail' ? 'retail' : 'wholesale'}">
                    ${item.priceType === 'retail' ? 'قطاعي' : 'جملة'}
                </span>
            </td>
            <td class="total-before-cell" style="background-color: #f8f9fa; padding: 8px; text-align: center;">
                ${Helpers.formatCurrency(item.total_before_discount || 0)}
            </td>
       <td>
    <input type="number" 
           class="input-discount-value" 
           value="${item.discount_value || 0}" 
           min="0" 
           max="${item.discount_type === 'percent' ? '99.99' : ''}"
           data-index="${index}"
           style="width: 100%; text-align: center;">
</td>
<td>
    <select class="input-discount-type" data-index="${index}" style="width: 100%; padding: 4px;">
    <option value="amount" ${item.discount_type === 'amount' ? 'selected' : ''}>ج.م</option>
        <option value="percent" ${item.discount_type === 'percent' ? 'selected' : ''}>%</option>
    </select>
</td>
<td class="total-after-cell" style="background-color: #e8f5e8; padding: 8px; text-align: center; font-weight: bold;">
    ${Helpers.formatCurrency(item.total_after_discount || (item.quantity * item.price))}
</td>
            <td>
                <button class="remove-item" data-index="${index}" style="color: #dc3545; background: none; border: none; cursor: pointer;">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        `;

        row);
        
        DOM.invoiceItems.appendChild(row);
    });

    this.setupTableEventListeners();
},

                setupTableEventListeners() {



                    document.querySelectorAll('.input-qty').forEach(input => {
                        input.addEventListener('change', async function() {
                            const itemIndex = parseInt(this.dataset.index);

                            const item = AppState.invoiceItems[itemIndex];
                            if (!item) return;

                            const newQuantity = parseFloat(this.value) || 1;
                            const oldQuantity = item.quantity;
                            if (newQuantity === oldQuantity) return;

                            // كمية البنود الأخرى لنفس المنتج
                            const otherReserved = AppState.invoiceItems.reduce((sum, it, idx) => {



                                if (idx !== itemIndex && it.id === item.id) return sum + it.quantity;
                                return sum;
                            }, 0);



                            const available = Helpers.calculateAvailableStock(item.id, itemIndex);
                            const diff = newQuantity - oldQuantity;

                            if (diff > 0 && diff > available) {
                                Helpers.showToast(
                                    `الكمية غير متاحة، المتاح ${available}`,
                                    'error'
                                );
                                this.value = oldQuantity;
                                return;
                            }

                            const totalNeeded = newQuantity + otherReserved;
                            const fifoResult = await ApiManager.simulateFIFO(item.id, totalNeeded);

                            if (!fifoResult.ok || !fifoResult.sufficient) {
                                Helpers.showToast(
                                    `المخزون لا يكفي (الإجمالي المطلوب ${totalNeeded}
                المتاح ${available})`,
                                    'error'
                                );
                                this.value = oldQuantity;
                                return;
                            }

                            item.quantity = newQuantity;
                            item.cost = Helpers.calculateAverageCost(fifoResult.allocations);
                            AppState.fifoData[itemIndex] = fifoResult;
    Helpers.calculateItemDiscount(item);
                            UI.update();
                            Helpers.showToast(`تم تحديث الكمية بنجاح`, 'success');
                        });
                    });






                    // باقي الأحداث كما هي...
                    document.querySelectorAll('.input-price').forEach(input => {
                        input.addEventListener('change', function() {
                            const index = parseInt(this.dataset.index);
                            const item = AppState.invoiceItems[index];
                            if (item) {
                                item.price = parseFloat(this.value) || 0;
            Helpers.calculateItemDiscount(item);

                                UI.update();
                            }
                        });

                        input.addEventListener('click', function() {
                            this.select();
                        });
                    });

                    document.querySelectorAll('.remove-item').forEach(button => {
                        button.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            const item = AppState.invoiceItems[index];
                            AppState.invoiceItems.splice(index, 1);
                            delete AppState.fifoData[index]; // حذف بيانات FIFO المرتبطة
                            UI.update();
                            Helpers.showToast(`تم حذف ${item.name} من الفاتورة`, 'success');
                        });
                    });

                    document.querySelectorAll('.fifo-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const index = parseInt(this.dataset.index);
                            const item = AppState.invoiceItems[index];
                            if (item && AppState.fifoData[index]) {
                                UI.showFIFODetails(item, AppState.fifoData[index]);
                            }
                        });
                    });


    
    // حدث تغيير قيمة الخصم
  document.querySelectorAll('.input-discount-value').forEach(input => {

    input.addEventListener('change', function () {
        const index = parseInt(this.dataset.index);
        const item = AppState.invoiceItems[index];
        if (!item) return;

        const newValue = parseFloat(this.value) || 0;

        // منع القيم السالبة
        if (newValue < 0) {
            this.value = 0;
            item.discount_value = 0;
            return;
        }

        // حفظ آخر قيمة صحيحة
        item._last_discount_value ??= item.discount_value;

        // خصم 100% يحتاج موافقة
        if (item.discount_type === 'percent' && newValue === 100) {
            const confirmed = confirm(
                `⚠️ سيتم بيع "${item.name}" مجانًا (خصم 100%). هل أنت متأكد؟`
            );

            if (!confirmed) {
                this.value = item._last_discount_value;
                item.discount_value = item._last_discount_value;
                return;
            }
        }

        item.discount_value = newValue;
        item._last_discount_value = newValue;

        Helpers.calculateItemDiscount(item);
        UI.update();
    });

    input.addEventListener('focus', function () {
        this.select();
    });
});
;
  document.querySelectorAll('.input-discount-type').forEach(select => {

    select.addEventListener('change', function () {
        const index = parseInt(this.dataset.index);
        const item = AppState.invoiceItems[index];
        if (!item) return;

        item.discount_type = this.value;

        // لا نفرض 100 ولا نمنعها
        if (this.value === 'percent' && item.discount_value > 100) {
            item.discount_value = 100;
        }

        Helpers.calculateItemDiscount(item);
        UI.update();

        Helpers.showToast(`تم تغيير نوع خصم ${item.name}`, 'success');
    });
});



    
                },

                // تحديث الملخص
        updateSummary() {
    if (!DOM.subtotal || !DOM.discountAmount || !DOM.totalAmount) return;

    // حساب الإجماليات الجديدة
    const totals = Helpers.calculateInvoiceTotals();
    
    // عرض الإجمالي قبل الخصم (مجموع البنود قبل الخصم)
    DOM.subtotal.textContent = Helpers.formatCurrency(totals.subtotal);
    
    // عرض إجمالي الخصم (مجموع خصومات البنود)
    DOM.discountAmount.textContent = Helpers.formatCurrency(totals.totalDiscount);
    
    // عرض الإجمالي النهائي (صافي الفاتورة بعد خصم البنود)
    DOM.totalAmount.textContent = Helpers.formatCurrency(totals.netTotal);
},
   

                updatePaymentSection() {

                    const total = Helpers.calculateTotal();
                    const paidAmount = AppState.payments.reduce((sum, payment) => sum + payment.amount, 0);
                    const remainingAmount = total - paidAmount;


                    const updatePaymentElements = (sectionType) => {
                        const prefix = sectionType === 'full' ? 'full' : 'partial';
                        const totalEl = document.getElementById(`${prefix}-payment-total`);
                        const paidEl = document.getElementById(`${prefix}-payment-paid_amount`);
                        const remainingEl = document.getElementById(`${prefix}-payment-remaining`);

                        if (totalEl) totalEl.textContent = Helpers.formatCurrency(total);
                        if (paidEl) paidEl.textContent = Helpers.formatCurrency(paidAmount);
                        if (remainingEl) {
                            remainingEl.textContent = Helpers.formatCurrency(remainingAmount);

                            // تحديث لون المبلغ المتبقي
                            if (remainingAmount <= 0) {
                                remainingEl.style.color = '#28a745';
                            } else if (remainingAmount < total * 0.5) {
                                remainingEl.style.color = '#ffc107';
                            } else {
                                remainingEl.style.color = '#dc3545';
                            }
                        }
                    };


                    const paymentStatus = document.querySelector('input[name="payment"]:checked');
                    if (!paymentStatus) return;

                    const fullPaymentSection = document.getElementById('full-payment-section');
                    const partialPaymentSection = document.getElementById('partial-payment-section');

                    if (paymentStatus.value === 'paid') {
                        // إظهار قسم الدفع الكامل
                        if (fullPaymentSection) fullPaymentSection.style.display = 'block';
                        if (partialPaymentSection) partialPaymentSection.style.display = 'none';
                        updatePaymentElements('full');

                        this.renderPaymentsList(DOM.fullpaymentsList);



                    } else if (paymentStatus.value === 'partial') {
                        // إظهار قسم الدفع الجزئي وإخفاء الكامل


                        if (fullPaymentSection) fullPaymentSection.style.display = 'none';
                        if (partialPaymentSection) partialPaymentSection.style.display = 'block';
                        updatePaymentElements('partial');

                        this.renderPaymentsList(DOM.partialpaymentsList);

                    } else {
                        // مؤجل - إخفاء كلا القسمين
                        if (fullPaymentSection) fullPaymentSection.style.display = 'none';
                        if (partialPaymentSection) partialPaymentSection.style.display = 'none';
                        AppState.payments = []; // مسح المدفوعات إذا كان مؤجل
                    }

                    // تحديث لون المبلغ المتبقي بناءً على الحالة
                    if (DOM.paymentRemaining) {
                        if (remainingAmount <= 0) {
                            DOM.paymentRemaining.style.color = '#28a745';
                        } else if (remainingAmount < total * 0.5) {
                            DOM.paymentRemaining.style.color = '#ffc107';
                        } else {
                            DOM.paymentRemaining.style.color = '#dc3545';
                        }
                    }
                },



                renderPaymentsList(paymentList) {

                    if (!paymentList) return;

                    paymentList.innerHTML = '';

                    if (AppState.payments.length === 0) {
                        paymentList.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--muted);">لا توجد مدفوعات مضافة</div>';
                        return;
                    }

                    AppState.payments.forEach(payment => {
                        const div = document.createElement('div');
                        div.className = 'payment-item';
                        div.innerHTML = `
                    <div class="payment-details">
                        <div class="payment-amount-display">${Helpers.formatCurrency(payment.amount)}</div>
                        <div class="payment-meta">
                            ${payment.date} - ${Helpers.getPaymentMethodText(payment.method)}
                            ${payment.notes ? `<br><small>${payment.notes}</small>` : ''}
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm remove-payment " data-id="${payment.id}">حذف</button>
                `;
                        paymentList.appendChild(div);
                    });

                    // إضافة الأحداث لحذف المدفوعات
                    document.querySelectorAll('.remove-payment').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const paymentId = parseInt(this.dataset.id);
                            InvoiceManager.removePayment(paymentId);
                        });
                    });
                },

                // تحديث عرض الأرباح
                updateProfitDisplay() {
                    const profit = Helpers.calculateProfit();
                    let profitElement = document.getElementById('profit-display');

                    if (!profitElement) {
                        profitElement = this.createProfitDisplay();
                    }

                    profitElement.innerHTML = `
                    <div class="position-fixed end-0 bottom-0 me-3 mb-3 z-3 btn  ${profit.netProfit < 0 ? 'btn-danger' : 'btn-success'}">
                        <span> ID : ${profit.netProfit} </span>
                        </div>
                 `
                },

                // إنشاء عرض الأرباح
                createProfitDisplay() {
                    const profitSection = document.createElement('div');
                    profitSection.id = 'profit-display';
                    const summarySection = document.querySelector('.summary-section');
                    if (summarySection && summarySection.parentNode) {
                        summarySection.parentNode.insertBefore(profitSection, summarySection);
                    }
                    return profitSection;
                },

                // إضافة دالة لعرض تفاصيل FIFO
                showFIFODetails(item, fifoData) {
                    if (!DOM.batchDetailModal || !DOM.batchDetailBody) return;

                    let html = `<h4>تفاصيل FIFO — ${item.name}</h4>`;
                    html += `<table class="fifo-table" style="width:100%"><thead><tr><th>رقم الدفعة</th><th>التاريخ</th><th>المتبقي قبل السحب</th><th>المتبقي بعد السحب المطلوب</th><th>سعر الشراء</th><th>مأخوذ</th><th>تكلفة</th></tr></thead><tbody>`;

                    fifoData.allocations.forEach(alloc => {
                        html += `<tr>
                                <td class="monos">${alloc.batch_id}</td>
                                <td>${alloc.received_at}</td>
                                <td>${alloc.remaining_before}</td>
                                <td>${alloc.remaining_after}</td>
                                <td>${alloc.unit_cost}</td>
                                <td>${alloc.taken}</td>
                                <td>${alloc.cost}</td>
                            </tr>`;
                    });

                    html += `</tbody></table>`;
                    html += `<div style="margin-top:8px"><strong>إجمالي تكلفة البند:</strong> ${Helpers.formatCurrency(fifoData.total_cost)}</div>`;

                    DOM.batchDetailBody.innerHTML = html;
                    DOM.batchDetailModal.style.display = 'flex';
                },
                // إعادة تعيين نموذج المنتج
                resetProductForm() {
                    if (DOM.productSelect) DOM.productSelect.value = '';
                    if (DOM.productQty) DOM.productQty.value = 1;
                    if (DOM.productPrice) DOM.productPrice.value = '';
                    if (DOM.barcodeInput) DOM.barcodeInput.value = '';
                    if (DOM.productSelect) DOM.productSelect.focus();
                    this.focusProductSearch();
                },

                // إعداد التنقل بين الحقول
                setupFieldNavigation() {
                    const fields = [
                        DOM.barcodeInput,
                        DOM.productSelect,
                        DOM.productQty,
                        DOM.productPrice,
                        DOM.addProductBtn
                    ].filter(field => field !== null);

                    fields.forEach((field, index) => {
                        if (!field) return;

                        field.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                const nextField = fields[index + 1];
                                if (nextField) {
                                    nextField.focus();
                                    if (nextField.tagName === 'INPUT') {
                                        nextField.select();
                                    }

                                    if (nextField === DOM.addProductBtn) {
                                        InvoiceManager.addProductToInvoice();
                                    }
                                }
                            }

                            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                                e.preventDefault();
                                const direction = e.key === 'ArrowUp' ? -1 : 1;
                                const nextIndex = (index + direction + fields.length) % fields.length;
                                fields[nextIndex].focus();
                            }
                        });
                    });
                },




                // عرض نموذج التأكيد
                // في UI.showConfirmModal، استبدل الكود بالكامل
                showConfirmModal() {
                    if (!DOM.confirmModal || !DOM.confirmContent) return;

                    // التحقق من صحة البيانات أولاً
                    if (!this.validateInvoiceBeforeConfirm()) {
                        return;
                    }

                    const paymentStatus = document.querySelector('input[name="payment"]:checked');
                    if (!paymentStatus) return;

                    const statusText = {
                        'pending': 'مؤجل',
                        'partial': 'مدفوع جزئياً',
                        'paid': 'مدفوع بالكامل'
                    } [paymentStatus.value] || 'مؤجل';

                    const paidAmount = AppState.payments.reduce((sum, p) => sum + p.amount, 0);
                    const profit = Helpers.calculateProfit();

                    let html = `
            <div class="confirm-modal-content">
                <div>
                    <div class="confirm-section">
                        <h3>العميل</h3>
                        <div>${AppState.currentCustomer.name}</div>
                        <div>${AppState.currentCustomer.mobile}</div>
                        <div>${AppState.currentCustomer.city || ''}</div>
                    </div>
                    
                    <div class="confirm-section">
                        <h3>حالة الدفع</h3>
                        <div>${statusText}</div>
                    </div>
                    
                    <div class="confirm-section">
                        <h3>الخصم</h3>
                        <div>${AppState.discount.type === 'percent' ? AppState.discount.value + '%' : Helpers.formatCurrency(AppState.discount.value)}</div>
                    </div>
                </div>
                
                <div>
                    <div class="confirm-section">
                        <h3>بنود الفاتورة</h3>
                        <div class="confirm-items">
            `;

                    AppState.invoiceItems.forEach(item => {
                        html += `
                <div class="confirm-item">
                    <div>${item.name} (${item.quantity})</div>
                    <div>${Helpers.formatCurrency(item.price * item.quantity)}</div>
                </div>
                `;
                    });

                    html += `
                        </div>
                    </div>
                    
                    <div class="confirm-section">
                        <h3>الإجماليات</h3>
                        <div class="confirm-item">
                            <div>الإجمالي:</div>
                            <div>${Helpers.formatCurrency(profit.totalRevenue)}</div>
                        </div>
                        <div class="confirm-item">
                            <div>الخصم:</div>
                            <div>${Helpers.formatCurrency(profit.discountAmount)}</div>
                        </div>
                    
                        <div class="confirm-item" style="font-weight: bold;">
                            <div>الإجمالي النهائي:</div>
                            <div>${Helpers.formatCurrency(profit.revenueAfterDiscount)}</div>
                        </div>
                
                    </div>
                </div>
            </div>
            
            <div class="print-options" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <h4>خيارات الطباعة:</h4>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 10px;">
                    <button class="btn btn-outline" id="confirm-without-print">تأكيد وإنشاء بدون طباعة</button>
                    <button class="btn btn-primary" id="confirm-and-print">تأكيد وإنشاء وطباعة</button>
                </div>
            </div>
            `;

                    //  <div class="confirm-item ${profit.netProfit < 0 ? 'loss' : 'profit'}">
                    //             <div>صافي الربح:</div>
                    //             <div>${Helpers.formatCurrency(profit.netProfit)}</div>
                    //         </div>

                    DOM.confirmContent.innerHTML = html;
                    DOM.confirmModal.style.display = 'flex';

                    // إضافة event listeners للأزرار الجديدة
                    this.setupConfirmButtons();
                },

                // دالة جديدة للتحقق من صحة الفاتورة قبل التأكيد
                validateInvoiceBeforeConfirm() {
                    if (AppState.invoiceItems.length === 0) {
                        Helpers.showToast('يرجى إضافة منتجات إلى الفاتورة أولاً', 'error');
                        return false;
                    }

                    if (!AppState.currentCustomer) {
                        Helpers.showToast('يرجى اختيار عميل', 'error');
                        return false;
                    }

                    // التحقق من أن جميع البنود صالحة
                    const invalidItems = AppState.invoiceItems.filter(item => {
                        return !item.id || item.quantity <= 0 || item.price <= 0;
                    });

                    if (invalidItems.length > 0) {
                        Helpers.showToast('هناك بنود غير صالحة في الفاتورة', 'error');
                        return false;
                    }

                    return true;
                },

                // دالة جديدة لإعداد أزرار التأكيد
                // في UI.setupConfirmButtons، أضف زر الطباعة فقط:
                setupConfirmButtons() {
                    const confirmWithoutPrint = document.getElementById('confirm-without-print');
                    const confirmAndPrint = document.getElementById('confirm-and-print');
                    const printOnly = document.getElementById('print-only');

                    if (confirmWithoutPrint) {
                        confirmWithoutPrint.addEventListener('click', () => {
                            InvoiceManager.processInvoice(false);
                            if (DOM.confirmModal) DOM.confirmModal.style.display = 'none';
                        });
                    }

                    if (confirmAndPrint) {
                        confirmAndPrint.addEventListener('click', () => {
                            InvoiceManager.processInvoice(true);
                            if (DOM.confirmModal) DOM.confirmModal.style.display = 'none';
                        });
                    }

                    if (printOnly) {
                        printOnly.addEventListener('click', () => {
                            InvoiceManager.printOnly();
                        });
                    }
                },


                //                 async processInvoice(shouldPrint = false) {
                //                     // التحقق من صحة البيانات أولاً
                //                     if (!this.validateInvoiceBeforeSave()) {
                //                         return;
                //                     }

                //                     Helpers.showToast('جاري إنشاء الفاتورة...', 'info');

                //                     const paymentStatus = document.querySelector('input[name="payment"]:checked').value;
                //                     const profit = Helpers.calculateProfit();



                //                     const invoiceData = {
                //   customer_id: AppState.currentCustomer.id,
                //   work_order_id: AppState.currentWorkOrderId || null,

                //   items: JSON.stringify(
                //     AppState.invoiceItems.map(item => ({
                //       product_id: item.id,
                //       qty: item.quantity,
                //       selling_price: item.price,
                //       price_type: item.priceType,
                //       cost_price_per_unit: item.cost || 0
                //     }))
                //   ),

                //   discount_type: AppState.discount.type,
                //   discount_value: AppState.discount.value,
                //   notes: AppState.invoiceNotes,
                //   csrf_token: AppState.csrfToken,

                //   // 🔥 الجديد
                //   payments: JSON.stringify(AppState.payments || [])
                // };



                //                     try {
                //                         const result = await ApiManager.saveInvoice(invoiceData);

                //                         if (result.ok) {
                //                             Helpers.showToast('تم إنشاء الفاتورة بنجاح!', 'success');

                //                             // حفظ معرف الفاتورة للطباعة لاحقاً إذا لزم
                //                             AppState.currentInvoiceId = result.invoice_id;

                //                             if (shouldPrint) {
                //                                 this.printInvoice(result.invoice_id);
                //                             }

                //                             UI.resetInvoice();
                //                         } else {
                //                             Helpers.showToast(result.error || 'فشل إنشاء الفاتورة', 'error');
                //                         }
                //                     } catch (error) {
                //                         console.error('Error saving invoice:', error);
                //                         Helpers.showToast('حدث خطأ أثناء حفظ الفاتورة', 'error');
                //                     }
                //                 },

                // دالة التحقق من صحة البيانات قبل الحفظ
                // validateInvoiceBeforeSave() {
                //     if (AppState.invoiceItems.length === 0) {
                //         Helpers.showToast('يرجى إضافة منتجات إلى الفاتورة أولاً', 'error');
                //         return false;
                //     }

                //     if (!AppState.currentCustomer) {
                //         Helpers.showToast('يرجى اختيار عميل', 'error');
                //         return false;
                //     }

                //     // التحقق من أن جميع البنود صالحة
                //     for (let item of AppState.invoiceItems) {
                //         if (!item.id || item.quantity <= 0 || item.price <= 0) {
                //             Helpers.showToast('هناك بنود غير صالحة في الفاتورة', 'error');
                //             return false;
                //         }

                //         // التحقق من المخزون المتاح
                //         const product = AppData.products.find(p => p.id === item.id);
                //         if (product && item.quantity > product.remaining_active) {
                //             Helpers.showToast(`الكمية المطلوبة لـ ${item.name} تتجاوز المخزون المتاح`, 'error');
                //             return false;
                //         }
                //     }

                //     return true;
                // },

                // دالة إعادة تعيين الفاتورة بعد الحفظ
                resetInvoice() {
                    AppState.invoiceItems = [];
                    AppState.fifoData = {};
                    AppState.payments = [];
                    AppState.discount = {
                        type: "percent",
                        value: 0
                    };
                    AppState.currentInvoiceId = null;
                    AppState.currentPaymentMethod = 'cash';
                    AppState.fullPaymentNotes = '';
                    AppState.invoiceNotes = '';
                    AppState.paymentNotes = '';
                    AppState.currentCustomer = null;
                    AppState.currentWorkOrder = null;
                    AppState.availableWorkOrders = []

                    // إعادة تعيين واجهة الدفع الكامل
                    const fullPaymentNotes = document.getElementById('full-payment-notes');
                    if (fullPaymentNotes) fullPaymentNotes.value = '';




                    // إعادة تعيين أزرار نوع الدفع
                    document.querySelectorAll('#full-payment-section .payment-method').forEach(m => m.classList.remove('selected'));
                    const cashMethod = document.querySelector('#full-payment-section .payment-method[data-method="cash"]');
                    if (cashMethod) cashMethod.classList.add('selected');

                    // إعادة تعيين واجهة المستخدم
                    if (DOM.discountType) DOM.discountType.value = 'percent';
                    if (DOM.discountValue) DOM.discountValue.value = '0';
                    document.querySelectorAll('.quick-discount').forEach(d => d.classList.remove('active'));
                    // إعادة تعيين حالة الدفع إلى "مؤجل"
                    const pendingRadio = document.querySelector('input[name="payment"][value="pending"]');
                    if (pendingRadio) pendingRadio.checked = true;
                    const invoice_notes = document.getElementById('invoice-notes');
                    if (invoice_notes) invoice_notes.value = '';
                    const partial_payment_notes = document.getElementById('partial-payment-notes');
                    if (partial_payment_notes) partial_payment_notes.value = '';
                    const workOrderSection = document.getElementById('work-order-section');
                    if (workOrderSection) workOrderSection.style.display = 'none';


                    UI.resetProductForm();


                    Helpers.showToast('تم تفريغ الفاتورة وجاهز لإنشاء فاتورة جديدة', 'success');
                    UI.update();
                },

                // تحديث عرض رقم الفاتورة
                updateInvoiceNumberDisplay() {
                    const display = document.getElementById('current-invoice-display');
                    if (display) {
                        display.textContent = AppState.currentInvoiceNumber;
                    }
                },

                // عرض نموذج النجاح
                // عرض رسالة النجاح
                // عرض رسالة النجاح
                // في دالة showSuccessMessage في UI، استبدل القسم المطلوب بالكود التالي:
                showSuccessMessage(result) {
                    const successModal = document.getElementById('success-modal');
                    const successContent = document.querySelector('.success-content');

                    if (successModal && successContent) {
                        let detailsHTML = `
                    <div style="font-size: 18px; margin-bottom: 15px;">
                        <strong>✅ تم إنشاء الفاتورة بنجاح</strong>
                    </div>
                    <div class="invoice-details" style="text-align: right;; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <div style="margin-bottom: 10px;">
                            <strong>رقم الفاتورة: #${result.invoice_id}</strong>
                        </div>

                        <div  style="gap:20px; border-bottom: 1px dashed #ddd; padding-bottom: 12px; margin-bottom: 12px; line-height: 1.8;">
            
            <div>
                الإجمالي قبل الخصم:
                <strong>${Helpers.formatCurrency(result.total_before || result.total_revenue)}</strong>
            </div>

            <div>
                الخصم:
                <strong>-${Helpers.formatCurrency(result.discount_amount || 0)}</strong>
            </div>

            <div style="color: #28a745; font-weight: bold;">
                الإجمالي النهائي:
                ${Helpers.formatCurrency(result.total_after || result.total_revenue)}
            </div>

        

                `;

                        // إضافة تفاصيل الدفع حسب الحالة
                        if (result.payment_status === 'partial') {
                            detailsHTML += `
                        <div style="color: #ffc107;">
                            <div>المدفوع: ${Helpers.formatCurrency(result.paid_amount || 0)}</div>
                            <div>المتبقي: ${Helpers.formatCurrency(result.remaining_amount || 0)}</div>
                            <div>🟡 حالة الدفع: مدفوع جزئياً</div>
                        </div>
                    `;
                        } else if (result.payment_status === 'paid') {
                            detailsHTML += `
                        <div style="color: #28a745;">
                            <div>✅ حالة الدفع: مدفوع بالكامل</div>
                        </div>
                    `;
                        } else {
                            detailsHTML += `
                        <div style="color: #dc3545;">
                            <div>⏳ حالة الدفع: مؤجل</div>
                            <div>المستحق: ${Helpers.formatCurrency(result.remaining_amount || result.total_after)}</div>
                        </div>
                    `;
                        }

                        // إضافة تفاصيل الدفعات إذا كانت جزئية
                        if (result.payment_status === 'partial' && result.payments) {
                            detailsHTML += `<div style="margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 10px;">`;
                            detailsHTML += `<strong>تفاصيل الدفعات:</strong>`;
                            result.payments.forEach((payment, index) => {
                                detailsHTML += `
                            <div style="font-size: 12px; margin-top: 5px;">
                                ${index + 1}. ${Helpers.formatCurrency(payment.amount)} - ${Helpers.getPaymentMethodText(payment.method)} 
                                ${payment.notes ? `(${payment.notes})` : ''}
                            </div>
                        `;
                            });
                            detailsHTML += `</div>`;
                        }

                        detailsHTML += `</div>`;

                        successContent.innerHTML = detailsHTML;

                        // تحديث أزرار التوجيه
                        const stayButton = document.getElementById('stay-and-create');
                        const goButton = document.getElementById('go-to-invoices');

                        if (stayButton && goButton) {
                            // تحديد الصفحة المستهدفة حسب حالة الدفع
                            let targetPage = 'invoices_out.php'; // افتراضي
                            if (result.payment_status === 'pending') {
                                targetPage = 'pending_invoices.php';
                            } else if (result.payment_status === 'partial') {
                                targetPage = 'partials_invoices.php';
                            } else if (result.payment_status === 'paid') {
                                targetPage = 'delivered_invoices.php';
                            }

                            // تحديث حدث الزر
                            goButton.onclick = () => {
                                window.location.href = targetPage;
                            };

                            // زر البقاء وإنشاء جديد
                            stayButton.onclick = () => {
                                successModal.style.display = 'none';
                                // لا تقم بإعادة التعيين التلقائي هنا، دع المستخدم يتحكم
                            };
                        }

                        successModal.style.display = 'flex';
                    }

                    Helpers.showToast(`تم إنشاء الفاتورة رقم ${result.invoice_id} بنجاح!`, 'success');
                },
                // الحصول على نص حالة الدفع
                getPaymentStatusText(status) {
                    const statusMap = {
                        'paid': 'مدفوع',
                        'pending': 'مؤجل',
                        'partial': 'جزئي'
                    };
                    return statusMap[status] || status;
                },


                // في UI:
                updateWorkOrderSection() {
                    const workOrderSection = document.getElementById('work-order-section');
                    const controls = workOrderSection?.querySelector('.work-order-controls');

                    if (!workOrderSection || !controls) return;

                    // إظهار القسم فقط إذا تم اختيار عميل
                    if (AppState.currentCustomer && AppState.currentCustomer.id !== 8) {
                        workOrderSection.style.display = 'block';

                        let html = '';

                        if (AppState.availableWorkOrders.length === 0) {
                            html = `
                    <div style="text-align: center; padding: 10px; color: var(--muted);">
                        لا توجد شغلات نشطة لهذا العميل
                    </div>
                    <button class="btn btn-primary btn-sm" id="create-work-order-btn">
                        <i class="fas fa-plus"></i> إنشاء شغلانة جديدة
                    </button>
                `;
                        } else {
                            html = `
                    <select class="form-select" id="work-order-select">
                        <option value="">-- اختر شغلانة --</option>
                        ${AppState.availableWorkOrders.map(wo => `
                            <option value="${wo.id}" ${AppState.currentWorkOrder?.id === wo.id ? 'selected' : ''}>
                                ${wo.title} (${wo.status === 'pending' ? 'قيد الانتظار' : 
                                        wo.status === 'in_progress' ? 'قيد التنفيذ' : 
                                        wo.status === 'completed' ? 'مكتمل' : 'ملغى'})
                            </option>
                        `).join('')}
                    </select>
                    <div style="margin-top: 10px; display: flex; gap: 8px;">
                       
                        <button class="btn btn-primary btn-sm" id="create-work-order-btn">
                            <i class="fas fa-plus"></i> إنشاء جديدة
                        </button>
                    </div>
                `;
                        }

                        controls.innerHTML = html;

                        // إضافة الأحداث
                        this.setupWorkOrderEvents();
                    } else {
                        workOrderSection.style.display = 'none';
                    }
                },

                setupWorkOrderEvents() {
                    // حدث تغيير الشغلانة
                    const select = document.getElementById('work-order-select');
                    if (select) {
                        select.addEventListener('change', (e) => {
                            const workOrderId = parseInt(e.target.value);
                            if (workOrderId) {
                                const workOrder = AppState.availableWorkOrders.find(wo => wo.id === workOrderId);
                                AppState.currentWorkOrder = workOrder;


                                Helpers.showToast(`تم ربط الفاتورة بالشغلانة: ${workOrder.title}`, 'success');
                            } else {
                                AppState.currentWorkOrder = null;
                            }
                        });
                    }

                    // حدث إنشاء شغلانة جديدة
                    const createBtn = document.getElementById('create-work-order-btn');
                    if (createBtn) {
                        createBtn.addEventListener('click', () => {
                            this.showCreateWorkOrderModal();
                        });
                    }

                    // حدث تحديث القائمة
                    const refreshBtn = document.getElementById('refresh-work-orders-btn');
                    if (refreshBtn) {
                        refreshBtn.addEventListener('click', async () => {
                            await this.loadWorkOrdersForCurrentCustomer();
                        });
                    }
                },
                // في UI:
                showCreateWorkOrderModal() {
                    const modal = document.getElementById('create-work-order-modal');
                    if (modal) {
                        modal.style.display = 'flex';

                        // إعادة تعيين الحقول
                        document.getElementById('work-order-title').value = '';
                        document.getElementById('work-order-description').value = '';
                        document.getElementById('work-order-start-date').value = new Date().toISOString().split('T')[0];
                        document.getElementById('work-order-status').value = 'pending';
                        document.getElementById('work-order-notes').value = '';
                    }
                },

                // في :

                updateWalletBalance() {


                    if (AppState.currentCustomer) {
                        const walletBalance = AppState.currentCustomer.wallet;



                        if (DOM.walletBalanceElementFull) {
                            DOM.walletBalanceElementFull.textContent = `المتاح: ${Helpers.formatCurrency(walletBalance)}`;
                        }
                        if (DOM.walletBalanceElementPartial) {
                            DOM.walletBalanceElementPartial.textContent = `المتاح: ${Helpers.formatCurrency(walletBalance)}`;
                        }
                        // تعطيل/تفعيل طريقة الدفع حسب الرصيد
                        const walletMethods = document.querySelectorAll('.payment-method[data-method="wallet"]');
                        if (walletMethods) {
                            walletMethods.forEach((walletMethod) => {
                                const total = Helpers.calculateTotal();
                                const paidSoFar = AppState.payments.reduce((sum, p) => sum + p.amount, 0);
                                const remaining = total - paidSoFar;

                                if (walletBalance <= 0) {
                                    walletMethod.classList.add('disabled');
                                    walletMethod.style.opacity = '0.5';
                                    walletMethod.style.pointerEvents = 'none';
                                } else {
                                    walletMethod.classList.remove('disabled');
                                    walletMethod.style.opacity = '1';
                                    walletMethod.style.pointerEvents = 'auto';
                                }

                                // إذا كان المبلغ المتبقي أكبر من رصيد المحفظة
                                // if (remaining > result.wallet_balance) {
                                //     Helpers.showToast(`رصيد المحفظة لا يكفي. المتاح: ${Helpers.formatCurrency(result.wallet_balance)}`, 'warning');
                                // }
                            })
                        }
                    } else {

                        const walletMethods = document.querySelectorAll('.payment-method[data-method="wallet"]');
                        DOM.walletBalanceElementFull ?
                            DOM.walletBalanceElementFull.textContent = "يجب اختيار عميل اولا" : ""

                        DOM.walletBalanceElementPartial ? DOM.walletBalanceElementPartial.textContent = "يجب اختيار عميل اولا" : ""
                        if (walletMethods) {
                            walletMethods.forEach((walletMethod) => {



                                walletMethod.classList.add('disabled');
                                walletMethod.style.opacity = '0.5';
                                walletMethod.style.pointerEvents = 'none';

                            });
                        }

                        // Helpers.showToast('مفيش محفظه','errore')

                    }
                }

                // في UI.updatePaymentSection أضف:



            }
            // ============================
            // دوال إدارة الفواتير المحدثة
            // ============================
            const InvoiceManager = {
                // التحقق من صحة المدفوعات
                // في InvoiceManager - قبل validateInvoiceBeforeSave()
validateItemDiscounts() {
    let isValid = true;
    let errorMessage = '';
    
    AppState.invoiceItems.forEach((item, index) => {
        // التحقق من القيم السالبة
        if (item.discount_value < 0) {
            isValid = false;
            errorMessage = `قيمة خصم البند ${index + 1} (${item.name}) لا يمكن أن تكون سالبة`;
            return;
        }
        
        // التحقق من النسبة المئوية (لا تزيد عن 99.99%)
        if (item.discount_type === 'percent' && item.discount_value >= 100) {
            isValid = false;
            errorMessage = `نسبة خصم البند ${index + 1} (${item.name}) لا يمكن أن تكون 100% أو أكثر`;
            return;
        }
        
        // التحقق أن الخصم لا يتجاوز قيمة البند
        const totalBefore = item.quantity * item.price;
        let maxDiscount = 0;
        
        if (item.discount_type === 'percent') {
            maxDiscount = totalBefore * (item.discount_value / 100);
        } else {
            maxDiscount = item.discount_value;
        }
        
        if (maxDiscount >= totalBefore) {
            isValid = false;
            errorMessage = `خصم البند ${index + 1} (${item.name}) يتجاوز قيمة البند`;
            return;
        }
    });
    
    return { isValid, errorMessage };
}                   ,
                validatePayments() {
                    const total = Helpers.calculateTotal();
                    const paidAmount = AppState.payments.reduce((sum, p) => sum + p.amount, 0);

                    if (paidAmount > total) {
                        Helpers.showToast('المبلغ المدفوع يتجاوز المبلغ المستحق!', 'error');
                        return false;
                    }

                    const paymentStatus = document.querySelector('input[name="payment"]:checked').value;

                    // التحقق من الفاتورة الجزئية
                    if (paymentStatus === 'partial') {
                        if (paidAmount <= 0) {
                            Helpers.showToast('الفاتورة الجزئية تحتاج إلى مدفوعات', 'error');
                            return false;
                        }

                        if (paidAmount >= total) {
                            Helpers.showToast('المبلغ المدفوع يساوي أو يتجاوز الإجمالي. الرجاء تغيير الحالة إلى "مدفوع"', 'error');
                            return false;
                        }
                    }

                    // التحقق من الفاتورة المدفوعة بالكامل
                    if (paymentStatus === 'paid') {
                        // التحقق من أن مجموع المدفوعات يساوي الإجمالي بالضبط (مع هامش خطأ صغير)
                        if (Math.abs(paidAmount - total) > 0.01) {
                            Helpers.showToast(`مجموع المدفوعات (${Helpers.formatCurrency(paidAmount)}) لا يساوي الإجمالي المطلوب (${Helpers.formatCurrency(total)})`, 'error');
                            return false;
                        }

                        // التحقق من وجود دفعة واحدة على الأقل للفاتورة المدفوعة بالكامل
                        if (AppState.payments.length === 0) {
                            Helpers.showToast('الفاتورة المدفوعة بالكامل تحتاج إلى مدفوعات', 'error');
                            return false;
                        }
                    }

                    // التحقق من المحفظة
                    if (AppState.currentCustomer) {
                        const walletBalance = AppState.currentCustomer.wallet || 0;
                        const totalWalletPayments = AppState.payments
                            .filter(p => p.method === 'wallet')
                            .reduce((sum, p) => sum + p.amount, 0);

                        if (totalWalletPayments > walletBalance) {
                            Helpers.showToast(`إجمالي السحب من المحفظة (${Helpers.formatCurrency(totalWalletPayments)}) يتجاوز الرصيد المتاح (${Helpers.formatCurrency(walletBalance)})`, 'error');
                            return false;
                        }
                    }

                    return true;
                }, // تأكيد تحويل الفاتورة إلى مدفوعة بالكامل
                async confirmFullPayment() {
                    const total = Helpers.calculateTotal();
                    const paidAmount = AppState.payments.reduce((sum, p) => sum + p.amount, 0);

                    if (Math.abs(paidAmount - total) < 0.01) {
                        return new Promise((resolve) => {
                            if (confirm(`المبلغ المدفوع (${Helpers.formatCurrency(paidAmount)}) يساوي المبلغ الإجمالي. هل تريد تحويل الفاتورة إلى "مدفوع بالكامل"؟`)) {
                                // تغيير حالة الدفع إلى مدفوع
                                const paidRadio = document.querySelector('input[name="payment"][value="paid"]');
                                if (paidRadio) {
                                    paidRadio.checked = true;
                                    UI.updatePaymentSection();
                                }
                                resolve(true);
                            } else {
                                resolve(false);
                            }
                        });
                    }
                    return true;
                },
                addPayment(type) {
                    const amount = (type == "full" ? parseFloat(DOM.currentPaymentFull.value) : parseFloat(DOM.currentPaymentPartial.value)) || 0;
                    const method = AppState?.currentPaymentMethod;
                    const notes = (type == "full" ? document.getElementById('full-payment-notes')?.value.trim() : document.getElementById('partial-payment-notes')?.value.trim()) || '';

                    if (amount <= 0) {
                        Helpers.showToast('يرجى إدخال مبلغ صحيح', 'error');
                        return;
                    }

                    const total = Helpers.calculateTotal();
                    const paidSoFar = AppState.payments.reduce((sum, p) => sum + p.amount, 0);
                    const remaining = total - paidSoFar;

                    // التحقق من أن المبلغ لا يتجاوز المتبقي
                    if (amount > remaining) {
                        Helpers.showToast(`المبلغ المدخل (${Helpers.formatCurrency(amount)}) يتجاوز المبلغ المطلوب (${Helpers.formatCurrency(remaining)})`, 'error');
                        return;
                    }

                    // التحقق من المحفظة إذا كانت طريقة الدفع محفظة
                    if (method === 'wallet' && AppState.currentCustomer) {
                        const walletBalance = AppState.currentCustomer.wallet || 0;

                        // التحقق من أن السحب لا يتجاوز رصيد المحفظة
                        if (amount > walletBalance) {
                            Helpers.showToast(`المبلغ المدخل (${Helpers.formatCurrency(amount)}) يتجاوز رصيد المحفظة (${Helpers.formatCurrency(walletBalance)})`, 'error');
                            return;
                        }



                        // التحقق من أن المبلغ المدخل متوفر في المحفظة بعد إضافة المدفوعات الأخرى
                        const walletPayments = AppState.payments
                            .filter(p => p.method === 'wallet')
                            .reduce((sum, p) => sum + p.amount, 0);

                        const totalWalletUsage = walletPayments + amount;

                        if (totalWalletUsage > walletBalance) {
                            Helpers.showToast(`إجمالي السحب من المحفظة (${Helpers.formatCurrency(totalWalletUsage)}) يتجاوز رصيد المحفظة (${Helpers.formatCurrency(walletBalance)})`, 'error');
                            return;
                        }
                    }

                    const existingPayment = AppState.payments.find(p => p.method === method);
                    if (existingPayment) {
                        existingPayment.amount += amount;
                        existingPayment.notes += `, ${notes}`;
                        Helpers.showToast('تم تحديث الدفع الحالي', 'success');
                    } else {
                        AppState.payments.push({
                            id: Date.now(),
                            amount,
                            method,
                            notes,
                            date: new Date().toLocaleDateString('ar-EG')
                        });
                        Helpers.showToast('تم إضافة الدفعة', 'success');
                    }


                    // AppState.payments.push(payment);
                    // AppState.payments
                    // (AppState.payments);

                    UI.updatePaymentSection();

                    // إعادة تعيين الحقول
                    DOM.currentPaymentPartial.value = '';
                    DOM.currentPaymentFull.value = '';
                    document.getElementById('full-payment-notes').value = '';
                    document.getElementById('partial-payment-notes').value = '';



                    // التحقق إذا كان المبلغ المدفوع يساوي الإجمالي
                    if (type == "partial") {
                        const newPaidSoFar = AppState.payments.reduce((sum, p) => sum + p.amount, 0);
                        if (Math.abs(newPaidSoFar - total) < 0.01) {
                            setTimeout(() => {
                                this.confirmFullPayment();
                            }, 500);
                        }
                    }
                },

                // دالة لحذف دفعة
                removePayment(paymentId) {
                    AppState.payments = AppState.payments.filter(p => p.id !== paymentId);
                    UI.updatePaymentSection();
                    Helpers.showToast('تم حذف الدفعة', 'success');
                },
                // إضافة منتج إلى الفاتورة
                async addProductToInvoice() {
                    const productId = parseInt(DOM.productSelect.value);
                    const quantity = parseFloat(DOM.productQty.value) || 1;
                    const price = parseFloat(DOM.productPrice.value);

                    // تحقق من وجود productId وسعر صحيح
                    if (!productId || isNaN(productId)) {
                        Helpers.showToast('الرجاء اختيار منتج صحيح', 'error');
                        return;
                    }

                    if (isNaN(price) || price <= 0) {
                        Helpers.showToast('الرجاء إدخال سعر صحيح', 'error');
                        return;
                    }

                    const product = AppData.products.find(p => parseInt(p.id) === productId);

                    // تحقق من وجود المنتج
                    if (!product) {
                        Helpers.showToast('المنتج غير موجود', 'error');
                        return;
                    }

                    // تحقق من الكمية المتاحة
                    const availableStock = parseFloat(product.remaining_active) || 0;
                    if (quantity > availableStock) {
                        Helpers.showToast(`الكمية المطلوبة (${quantity}) تتجاوز الكمية المتاحة (${availableStock})`, 'error');
                        return;
                    }

                    await this.addToInvoice(productId, quantity, price);
                    AppState.currentPriceType = 'wholesale';
                    UI.updatePriceButtons();

                    UI.resetProductForm();

                    // Helpers.showToast('تم إضافة المنتج إلى الفاتورة', 'success');
                },
                // في InvoiceManager، أضف دالة clearInvoice
                // في InvoiceManager، عدل دالة clearInvoice
                clearInvoice() {
                    if (confirm('هل أنت متأكد من تفريغ جميع بنود الفاتورة وإزالة العميل؟')) {
                        AppState.invoiceItems = [];
                        AppState.fifoData = {};
                        AppState.currentCustomer = null; // إزالة العميل المختار
                        AppState.payments = []; // تفريغ المدفوعات أيضاً
                        AppState.discount = {
                            type: "percent",
                            value: 0
                        }; // إعادة تعيين الخصم

                        // إعادة تعيين واجهة المستخدم
                        if (DOM.discountType) DOM.discountType.value = 'percent';
                        if (DOM.discountValue) DOM.discountValue.value = '0';

                        // إزالة التحديد من الخصومات السريعة
                        document.querySelectorAll('.quick-discount').forEach(d => d.classList.remove('active'));
                        UI.resetInvoice()
                    }
                },

                // في EventManager.setupModalEvents، تأكد من ربط الزر بالدالة

                // إضافة منتج للفاتورة مع حساب التكلفة
                // في InvoiceManager.addToInvoice استبدل الكود بـ:
                // async addToInvoice(productId, quantity, price) {
                //     const product = AppData.products.find(p => +p.id === productId);
                //     if (!product) return;

                //     // حساب الكمية الإجمالية للمنتج في الفاتورة (جميع البنود بنفس productId)
                //     const totalQuantityInInvoice = AppState.invoiceItems
                //         .filter(item => item.id === productId)
                //         .reduce((sum, item) => sum + item.quantity, 0);

                //     // البحث عن منتج موجود بنفس الـ ID ونفس نوع السعر
                //     const existingItemIndex = AppState.invoiceItems.findIndex(item =>
                //         item.id === productId
                //     );
                //     // const existingItemIndex = AppState.invoiceItems.findIndex(item => 
                //     //     item.id === productId && item.priceType === AppState.currentPriceType
                //     // );

                //     if (existingItemIndex !== -1) {
                //         // المنتج موجود - زيادة الكمية فقط
                //         const existingItem = AppState.invoiceItems[existingItemIndex];
                //         const newQuantity = existingItem.quantity + quantity;

                //         // التحقق من أن الكمية الإجمالية لا تتجاوز المخزون المتاح
                //         const totalWithoutCurrent = totalQuantityInInvoice - existingItem.quantity;
                //         const newTotal = totalWithoutCurrent + newQuantity;
                //         if (newTotal > product.remaining_active) {
                //             Helpers.showToast(`الكمية الإجمالية (${newTotal}) تتجاوز المخزون المتاح (${product.remaining_active})`, 'error');
                //             return;
                //         }

                //         // التحقق من المخزون المتاح للكمية الجديدة عبر FIFO
                //         const fifoResult = await ApiManager.simulateFIFO(productId, newQuantity);
                //         if (!fifoResult.ok || !fifoResult.sufficient) {
                //             Helpers.showToast(`الكمية الجديدة (${newQuantity}) غير متوفرة في المخزون. المتاح: ${fifoResult.allocations.reduce((sum, alloc) => sum + alloc.taken, 0)}`, 'error');
                //             return;
                //         }

                //         // تحديث الكمية والتكلفة
                //         existingItem.quantity = newQuantity;
                //         existingItem.cost = Helpers.calculateAverageCost(fifoResult.allocations);
                //         AppState.fifoData[existingItemIndex] = fifoResult;
                //         Helpers.showToast(`تم زيادة كمية ${product.name} إلى ${newQuantity}`, 'success');
                //     } else {
                //         // منتج جديد - إضافته كالمعتاد

                //         // التحقق من أن الكمية الإجمالية لا تتجاوز المخزون المتاح
                //         const newTotal = totalQuantityInInvoice + quantity;
                //         if (newTotal > product.remaining_active) {
                //             Helpers.showToast(`الكمية الإجمالية (${newTotal}) تتجاوز المخزون المتاح (${product.remaining_active})`, 'error');
                //             return;
                //         }

                //         const fifoResult = await ApiManager.simulateFIFO(productId, quantity);
                //         if (!fifoResult.ok || !fifoResult.sufficient) {
                //             const available = fifoResult.allocations.reduce((sum, alloc) => sum + alloc.taken, 0);
                //             Helpers.showToast(`الكمية غير متوفرة في المخزون. المتاح: ${available}`, 'error');
                //             return;
                //         }

                //         const cost = Helpers.calculateAverageCost(fifoResult.allocations);
                //         const newIndex = AppState.invoiceItems.length;

                //         AppState.invoiceItems.push({
                //             id: productId,
                //             name: product.name,
                //             price: price,
                //             quantity: quantity,
                //             cost: cost,
                //             priceType: AppState.currentPriceType,
                //             product_code: product.product_code,
                //         });

                //         AppState.fifoData[newIndex] = fifoResult;
                //         Helpers.showToast('تم إضافة المنتج إلى الفاتورة', 'success');
                //     }

                //     UI.update();
                //     UI.resetProductForm();
                // },

                // في Helpers - دالة لحساب خصم البند

           

                // في InvoiceManager، استبدل دالة addToInvoice بالكود التالي:

                async addToInvoice(productId, quantity, price) {
                    const product = AppData.products.find(p => +p.id === productId);
                    if (!product) return;

                    // حساب الكمية الإجمالية للمنتج في الفاتورة (جميع البنود بنفس productId بغض النظر عن نوع السعر)
                    const totalQuantityInInvoice = AppState.invoiceItems
                        .filter(item => item.id === productId)
                        .reduce((sum, item) => sum + item.quantity, 0);

                    // البحث عن منتج موجود بنفس الـ ID ونفس نوع السعر
                    const existingItemIndex = AppState.invoiceItems.findIndex(item =>
                        item.id === productId && item.priceType === AppState.currentPriceType
                    );

                    if (existingItemIndex !== -1) {
                        // المنتج موجود بنفس نوع السعر - زيادة الكمية فقط
                           const existingItem = AppState.invoiceItems[existingItemIndex];
                        const currentQty = existingItem.quantity;
                        const newQuantity = currentQty + quantity;
        existingItem.discount_type = existingItem.discount_type || 'amount';
        existingItem.discount_value = existingItem.discount_value || 0;
        existingItem.total_before_discount = newQuantity * existingItem.price;
        existingItem.total_after_discount = existingItem.total_before_discount;
        
        // إعادة حساب الخصم إذا كان موجوداً
        Helpers.calculateItemDiscount(existingItem);

                        // التحقق من أن الكمية الإجمالية لا تتجاوز المخزون المتاح
                        const totalWithoutCurrent = totalQuantityInInvoice - existingItem.quantity;
                        const newTotal = totalWithoutCurrent + newQuantity;

                        if (newTotal > product.remaining_active) {
                            Helpers.showToast(`الكمية الإجمالية (${newTotal}) تتجاوز المخزون المتاح (${product.remaining_active})`, 'error');
                            return;
                        }

                        // التحقق من المخزون المتاح للكمية الجديدة عبر FIFO
                        const fifoResult = await ApiManager.simulateFIFO(productId, newQuantity);
                        if (!fifoResult.ok || !fifoResult.sufficient) {
                            Helpers.showToast(`الكمية الجديدة (${newQuantity}) غير متوفرة في المخزون. المتاح: ${fifoResult.allocations.reduce((sum, alloc) => sum + alloc.taken, 0)}`, 'error');
                            return;
                        }

                        // تحديث الكمية والتكلفة
                        existingItem.quantity = newQuantity;
                        existingItem.cost = Helpers.calculateAverageCost(fifoResult.allocations);
                        AppState.fifoData[existingItemIndex] = fifoResult;
                        Helpers.showToast(`تم زيادة كمية ${product.name} (${AppState.currentPriceType === 'retail' ? 'قطاعي' : 'جملة'}) إلى ${newQuantity}`, 'success');
                    } else {
                        // منتج جديد بنوع سعر مختلف أو لم يضف من قبل

                        // التحقق من أن الكمية الإجمالية لا تتجاوز المخزون المتاح
                        const newTotal = totalQuantityInInvoice + quantity;
                        if (newTotal > product.remaining_active) {
                            Helpers.showToast(`الكمية الإجمالية (${newTotal}) تتجاوز المخزون المتاح (${product.remaining_active})`, 'error');
                            return;
                        }

                        const fifoResult = await ApiManager.simulateFIFO(productId, quantity);
                        if (!fifoResult.ok || !fifoResult.sufficient) {
                            const available = fifoResult.allocations.reduce((sum, alloc) => sum + alloc.taken, 0);
                            Helpers.showToast(`الكمية غير متوفرة في المخزون. المتاح: ${available}`, 'error');
                            return;
                        }

                        const cost = Helpers.calculateAverageCost(fifoResult.allocations);
                   const newIndex = AppState.invoiceItems.length;
AppState.invoiceItems.push({
    id: productId,
    name: product.name,
    price: price,
    quantity: quantity,
    cost: cost,
    priceType: AppState.currentPriceType,
    product_code: product.product_code,
    // الخصائص الجديدة للخصم
    discount_type: 'amount', // تغيير من 'percent' إلى 'amount'
    discount_value: 0,
    discount_amount: 0,
    total_before_discount: quantity * price,
    total_after_discount: quantity * price
});

                        AppState.fifoData[newIndex] = fifoResult;
                        Helpers.showToast(`تم إضافة المنتج ${product.name} (${AppState.currentPriceType === 'retail' ? 'قطاعي' : 'جملة'}) إلى الفاتورة`, 'success');
                    }

                    UI.update();
                    UI.resetProductForm();
                },

                // تأكيد الفاتورة
                async confirmInvoice() {
                    // التحقق من صحة البيانات
                    if (AppState.invoiceItems.length === 0) {
                        Helpers.showToast('يرجى إضافة منتجات إلى الفاتورة أولاً', 'error');
                        return;
                    }

                    if (!AppState.currentCustomer) {
                        Helpers.showToast('يرجى اختيار عميل', 'error');
                        return;
                    }

                    // التحقق من الأرباح
                    const profit = Helpers.calculateProfit();
                    if (profit.netProfit < 0) {
                        if (!confirm('هناك خسارة في هذه الفاتورة. هل تريد المتابعة؟')) {
                            return;
                        }
                    }

                    UI.showConfirmModal();
                },

                // معالجة الفاتورة
                // في InvoiceManager، استبدل دالة processInvoice بالكود التالي:
                // معالجة الفاتورة
                async processInvoice(shouldPrint = false) {
                    // التحقق من صحة البيانات أولاً
                    if (!this.validateInvoiceBeforeSave()) {
                        return;
                    }
                    // التحقق من المدفوعات
                    if (!this.validatePayments()) {
                        return;
                    }

                    // تأكيد التحويل إلى مدفوع بالكامل
                    const confirmed = await this.confirmFullPayment();
                    if (!confirmed) {
                        return;
                    }
                    Helpers.showToast('جاري إنشاء الفاتورة...', 'info');

                    const paymentStatus = document.querySelector('input[name="payment"]:checked').value;
                    const profit = Helpers.calculateProfit();




                    // في InvoiceManager.processInvoice() - تحديث invoiceData:
const invoiceData = {
    customer_id: AppState.currentCustomer.id,
    work_order_id: AppState.currentWorkOrder?.id || null,

    // في InvoiceManager.processInvoice() - تحديث الـ items map:
items: JSON.stringify(
    AppState.invoiceItems.map(item => ({
        product_id: item.id,
        qty: item.quantity,
        selling_price: item.price,
        price_type: item.priceType,
        cost_price_per_unit: item.cost || 0,
        // تأكد من إضافة discount_type
        discount_type: item.discount_type || 'amount',
        discount_value: item.discount_value || 0,
        discount_amount: item.discount_amount || 0,
        total_before_discount: item.total_before_discount || (item.quantity * item.price),
        total_after_discount: item.total_after_discount || (item.quantity * item.price)
    }))
),
    // إلغاء خصم الفاتورة لأن discount_scope = 'items'
    discount_type: 'amount',
    discount_value: 0,
    discount_scope: 'items', // تحديد scope
    
    notes: AppState.invoiceNotes,
    csrf_token: AppState.csrfToken,
    payments: JSON.stringify(AppState.payments || [])
};

                    try {
                        const result = await ApiManager.saveInvoice(invoiceData);

                        if (result.ok) {
                            // حفظ بيانات الفاتورة المنشأة
                            AppState.lastCreatedInvoice = {
                                id: result.invoice_id,
                                number: result.invoice_number,
                                total: result.total_revenue,
                                payment_status: result.payment_status,
                                paid_amount: result.paid_amount,
                                remaining_amount: result.remaining_amount
                            };

                            // عرض رسالة النجاح
                            UI.showSuccessMessage(result);



                            if (shouldPrint) {
                                this.printInvoice(result.invoice_id);
                            }

                            // إعادة تعيين الفاتورة بعد تأخير بسيط
                            setTimeout(() => {
                                UI.resetInvoice();
                            }, 2000);

                            await ApiManager.loadProducts()


                        } else {
                            Helpers.showToast(result.error || 'فشل إنشاء الفاتورة', 'error');
                        }
                    } catch (error) {
                        console.error('Error saving invoice:', error);
                        Helpers.showToast('حدث خطأ أثناء حفظ الفاتورة', 'error');
                    }
                },

                // دالة التحقق من صحة البيانات قبل الحفظ
                validateInvoiceBeforeSave() {
                    if (AppState.invoiceItems.length === 0) {
                        Helpers.showToast('يرجى إضافة منتجات إلى الفاتورة أولاً', 'error');
                        return false;
                    }

                    if (!AppState.currentCustomer) {
                        Helpers.showToast('يرجى اختيار عميل', 'error');
                        return false;
                    }

                    // التحقق من أن جميع البنود صالحة
                    for (let item of AppState.invoiceItems) {
                        if (!item.id || item.quantity <= 0 || item.price <= 0) {
                            Helpers.showToast('هناك بنود غير صالحة في الفاتورة', 'error');
                            return false;
                        }

                        // التحقق من المخزون المتاح
                        const product = AppData.products.find(p => p.id === item.id);
                        if (product && item.quantity > product.remaining_active) {
                            Helpers.showToast(`الكمية المطلوبة لـ ${item.name} تتجاوز المخزون المتاح`, 'error');
                            return false;
                        }
                    }

                    return true;
                },

                // دالة إعادة تعيين الفاتورة بعد الحفظ
                // دالة إعادة تعيين الفاتورة بعد الحفظ


                printInvoice(invoiceId = null) {
                    const idToPrint = AppState.lastCreatedInvoice?.id || AppState.currentInvoiceId || 'طباعه';
                    const invoiceData = this.prepareInvoiceDataForPrint(idToPrint);

                    // فتح نافذة الطباعة
                    const printWindow = window.open('', '_blank', 'width=300,height=600');

                    const receiptHTML = this.generateReceiptHTML(invoiceData);

                    printWindow.document.write(`
                    <!DOCTYPE html>
                    <html dir="rtl">
                    <head>
                        <meta charset="UTF-8">
                        <title>إيصال بيع</title>
                        <style>
                            body { 
                                font-family: 'Courier New', monospace; 
                                font-size: 12px; 
                                min-width: 70mm; 
                                margin: 0; 
                                padding: 5px;
                                line-height: 1.2;
                            }
                            .header { text-align: center; margin-bottom: 10px; }
                            .store-name { font-weight: bold; font-size: 14px; }
                            .receipt-info { margin: 5px 0; }
                            .items-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                            .items-table td { padding: 2px 0; border-bottom: 1px dashed #ccc; }
                            .total-section { margin-top: 10px; border-top: 2px solid #000; padding-top: 5px; }
                            .footer { text-align: center; margin-top: 15px; font-size: 10px; }
                            .text-left { text-align: left; }
                            .text-right { text-align: right; }
                            .text-center { text-align: center; }
                        </style>
                    </head>
                    <body>
                        ${receiptHTML}
                        <script>
                            window.onload = function() {
                                window.print();
                                setTimeout(function() {
                                    window.close();
                                }, 500);
                            }
                        <\/script>
                    </body>
                    </html>
                `);

                    printWindow.document.close();
                },

                // تحضير بيانات الفاتورة للطباعة
                prepareInvoiceDataForPrint(invoiceId) {
                    const profit = Helpers.calculateProfit();
                    const paymentStatus = document.querySelector('input[name="payment"]:checked').value;
                    const paidAmount = AppState.payments.reduce((sum, p) => sum + p.amount, 0);

                    return {
                        customer: AppState.currentCustomer,
                        items: AppState.invoiceItems,
                        subtotal: profit.totalRevenue,
                        discount: profit.discountAmount,
                        total: profit.revenueAfterDiscount,
                        paymentStatus: paymentStatus,
                        paidAmount: paidAmount,
                        remainingAmount: profit.revenueAfterDiscount - paidAmount,
                        date: new Date().toLocaleString('ar-EG'),
                        invoiceId: invoiceId
                    };
                },

                // توليد HTML للإيصال
                generateReceiptHTML(data) {
                    let itemsHTML = '';
                    data.items.forEach((item, index) => {
                        itemsHTML += `
                        <tr>
                            <td>${item.name}</td>
                            <td class="text-center">${item.quantity}</td>
                            <td class="text-right">${item.price.toFixed(2)}</td>
                            <td class="text-right">${(item.price * item.quantity).toFixed(2)}</td>
                        </tr>
                    `;
                    });

                    return `
                    <div class="header">
                        <div class="store-name">متجرنا</div>
                        <div>إيصال بيع</div>
                    </div>
                    
                    <div class="receipt-info">
                    الحاله: ${data.paymentStatus === 'paid' ? 'مدفوع بالكامل' : data.paymentStatus === 'partial' ? 'مدفوع جزئياً' : 'مؤجل'}<br>
                        <div>التاريخ: ${data.date}</div>
                        <div>الفاتورة: ${data.invoiceId}</div>
                        <div>العميل: ${data.customer ? data.customer.name : 'نقدي'}</div>
                    </div>
                    
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th class="text-left">الصنف</th>
                                <th class="text-center">الكمية</th>
                                <th class="text-right">السعر</th>
                                <th class="text-right">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHTML}
                        </tbody>
                    </table>
                    
                    <div class="total-section">
                        <div style="display: flex; justify-content: space-between;">
                            <span>الإجمالي:</span>
                            <span>${data.subtotal.toFixed(2)} ج.م</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>الخصم:</span>
                            <span>-${data.discount.toFixed(2)} ج.م</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-weight: bold;">
                            <span>المبلغ المستحق:</span>
                            <span>${data.total.toFixed(2)} ج.م</span>
                        </div>
                        ${data.paymentStatus === 'partial' ? `
                            <div style="display: flex; justify-content: space-between;">
                                <span>المدفوع:</span>
                                <span>${data.paidAmount.toFixed(2)} ج.م</span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span>المتبقي:</span>
                                <span>${data.remainingAmount.toFixed(2)} ج.م</span>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="footer">
                        <div>شكراً لزيارتكم</div>
                        <div>للاستفسار: 0123456789</div>
                    </div>
                `;
                },

                // دالة الطباعة فقط بدون حفظ
                printOnly() {
                    // if (UI.validateInvoiceBeforeConfirm()) {
                    //     return;
                    // }

                    Helpers.showToast('جاري الطباعة...', 'info');
                    this.printInvoice();

                    // إغلاق نافذة التأكيد
                    if (DOM.confirmModal) {
                        DOM.confirmModal.style.display = 'none';
                    }
                }
            };

            // ============================
            // إدارة الأحداث المحدثة
            // ============================
            // ============================
            // إدارة الأحداث المحدثة
            // ============================
            const EventManager = {
                setup() {
                    this.setupCustomerEvents();
                    this.setupProductEvents();
                    this.setupPaymentEvents();
                    this.setupDiscountEvents();
                    this.setupModalEvents();
                    this.setupNavigationEvents();
                    this.setupQuickCustomer();
                    this.setupAddCustomerEvents();
                    this.setupFIFOEvents();
                    this.setupNotesEvents(); // إضافة هذا السطر
                    this.setupSuccessModalEvents();
                    this.setupCreateWorkOrderEvents()
                    this.setupKeyboardShortcuts();

                },

                // في EventManager.setup() أضف:
                setupKeyboardShortcuts() {
                    document.addEventListener('keydown', (e) => {
                        // Ctrl + 1: فتح قائمة المنتجات
                        if (e.ctrlKey && e.key === 'F1') {
                            e.preventDefault();
                            UI.focusProductSearch()

                        }

                        // Ctrl + 2: فتح قائمة العملاء
                        if (e.ctrlKey && e.key === 'F2') {
                            e.preventDefault();
                            if (DOM.customersModal) DOM.customersModal.style.display = 'flex';
                            DOM.customerSearch.focus()

                        }

                        if (e.ctrlKey && e.key === 'F3') {
                            e.preventDefault();
                            EventManager.selectCustomer(8); // ID العميل النقدي
                        }

                        // F1: إضافة المنتج الحالي


                        // F2: تأكيد الفاتورة
                        if (e.key === 'F2') {
                            e.preventDefault();
                            InvoiceManager.confirmInvoice();
                        }

                        // F5: العمل النقدي مباشرة


                        // ESC: إغلاق النوافذ المفتوحة
                        if (e.key === 'Escape') {
                            document.querySelectorAll('.modal-backdrop').forEach(modal => {
                                modal.style.display = 'none';
                            });
                        }
                    });
                },
                setupCreateWorkOrderEvents() {
                    const submitBtn = document.getElementById("submit-create-work-order");
                    const closeWorkOrderModal = document.getElementById("cancel-create-work-order");

                    if (submitBtn) {
                        const spinner = submitBtn.querySelector(".spinner-border");
                        const btnText = submitBtn.querySelector(".btn-text");

                        submitBtn.addEventListener('click', async () => {
                            if (submitBtn.disabled) return;

                            // ✅ تعطيل الزر وإظهار spinner
                            submitBtn.disabled = true;
                            spinner.style.display = 'inline-block';
                            btnText.textContent = 'جاري الإنشاء...';

                            const title = document.getElementById('work-order-title')?.value.trim();
                            const description = document.getElementById('work-order-description')?.value.trim();
                            const startDate = document.getElementById('work-order-start-date')?.value;
                            const status = document.getElementById('work-order-status')?.value;
                            const notes = document.getElementById('work-order-notes')?.value.trim();

                            // -------------------- الفاليديشن --------------------
                            if (!AppState.currentCustomer) {
                                Helpers.showToast('يرجى اختيار عميل أولاً', 'error');
                                submitBtn.disabled = false;
                                spinner.style.display = 'none';
                                btnText.textContent = 'إنشاء الشغلانة';
                                return;
                            }

                            if (!title) {
                                Helpers.showToast('يرجى إدخال عنوان الشغلانة', 'error');
                                submitBtn.disabled = false;
                                spinner.style.display = 'none';
                                btnText.textContent = 'إنشاء الشغلانة';
                                return;
                            }

                            if (title.length < 3) {
                                Helpers.showToast('عنوان الشغلانة يجب ألا يقل عن 3 أحرف', 'error');
                                submitBtn.disabled = false;
                                spinner.style.display = 'none';
                                btnText.textContent = 'إنشاء الشغلانة';
                                return;
                            }

                            // التحقق من احتواء العنوان على حروف عربية أو إنجليزية
                            if (!/[A-Za-z\u0600-\u06FF]/.test(title)) {
                                Helpers.showToast('عنوان الشغلانة يجب أن يحتوي على حروف عربية أو إنجليزية', 'error');
                                submitBtn.disabled = false;
                                spinner.style.display = 'none';
                                btnText.textContent = 'إنشاء الشغلانة';
                                return;
                            }


                            if (!description || description.length < 4) {
                                Helpers.showToast('يرجى إدخال وصف صحيح للشغلانة', 'error');
                                submitBtn.disabled = false;
                                spinner.style.display = 'none';
                                btnText.textContent = 'إنشاء الشغلانة';
                                return;
                            }

                            if (!startDate) {
                                Helpers.showToast('يرجى إدخال تاريخ بدء الشغلانة', 'error');
                                submitBtn.disabled = false;
                                spinner.style.display = 'none';
                                btnText.textContent = 'إنشاء الشغلانة';
                                return;
                            }

                            // -------------------- إنشاء الشغلانة --------------------
                            try {
                                const result = await ApiManager.createWorkOrder({
                                    customer_id: AppState.currentCustomer.id,
                                    title,
                                    description,
                                    status,
                                    start_date: startDate,
                                    notes,
                                    created_by: <?php echo $_SESSION['id']; ?>
                                });

                                if (result.success) {
                                    Helpers.showToast('تم إنشاء الشغلانة بنجاح', 'success');

                                    // إغلاق النموذج
                                    const modal = document.getElementById('create-work-order-modal');
                                    if (modal) modal.style.display = 'none';

                                    // تحديث قائمة الشغلات
                                    await EventManager.loadWorkOrdersForCurrentCustomer();

                                    // اختيار الشغلانة الجديدة تلقائيًا
                                    AppState.currentWorkOrder = result.work_order;
                                    UI.updateWorkOrderSection();
                                } else {
                                    Helpers.showToast(result.message || 'فشل في إنشاء الشغلانة', 'error');
                                }
                            } catch (error) {
                                Helpers.showToast('❌ خطأ في إنشاء الشغلانة: ' + error.message, 'error');
                            } finally {
                                // ✅ إعادة الزر لوضعه الطبيعي
                                submitBtn.disabled = false;
                                spinner.style.display = 'none';
                                btnText.textContent = 'إنشاء الشغلانة';
                            }
                        });
                    }

                    if (closeWorkOrderModal) {
                        closeWorkOrderModal.addEventListener('click', () => {
                            const modal = document.getElementById('create-work-order-modal');
                            if (modal) modal.style.display = 'none';
                        });
                    }
                },
                // إعداد أحداث نموذج النجاح
                setupSuccessModalEvents() {
                    const stayAndCreate = document.getElementById('stay-and-create');
                    const goToInvoices = document.getElementById('go-to-invoices');

                    if (stayAndCreate) {
                        stayAndCreate.addEventListener('click', () => {
                            const successModal = document.getElementById('success-modal');
                            if (successModal) successModal.style.display = 'none';
                            UI.resetInvoice();
                        });
                    }

                    if (goToInvoices) {
                        goToInvoices.addEventListener('click', () => {
                            window.location.href = 'invoices_out.php'; // تغيير هذا الرابط حسب صفحة الفواتير لديك
                        });
                    }
                },
                setupFIFOEvents() {
                    if (DOM.closeBatchDetailBtn) {
                        DOM.closeBatchDetailBtn.addEventListener('click', () => {
                            if (DOM.batchDetailModal) DOM.batchDetailModal.style.display = 'none';
                        });
                    }
                },

                // إعداد أحداث العملاء
                setupCustomerEvents() {
                    // تغيير العميل
                    if (DOM.changeCustomerBtn) {
                        DOM.changeCustomerBtn.addEventListener('click', () => {
                            if (DOM.customersModal) DOM.customersModal.style.display = 'flex';
                        });
                    }

                    // اختيار عميل
                    document.addEventListener('click', async (e) => {
                        if (e.target.classList.contains('select-customer')) {
                            const customerCard = e.target.closest('.customer-card');
                            if (customerCard) {
                                const customerId = parseInt(customerCard.dataset.id);
                                await this.selectCustomer(customerId);
                                if (DOM.customersModal) DOM.customersModal.style.display = 'none';
                            }
                        }
                    });

                    // البحث في العملاء
                    if (DOM.customerSearch) {
                        DOM.customerSearch.addEventListener('input', (e) => {
                            DataManager.filterCustomers(e.target.value);
                        });
                    }
                },

                // إعداد أحداث إضافة العميل
                setupAddCustomerEvents() {
                    const addCustomerModal = document.getElementById('add-customer-modal');
                    const addCustomerBtn = document.getElementById('add-customer-btn');
                    const cancelAddCustomer = document.getElementById('cancel-add-customer');
                    const submitAddCustomer = document.getElementById('submit-add-customer');

                    if (addCustomerBtn) {
                        addCustomerBtn.addEventListener('click', () => {
                            if (addCustomerModal) addCustomerModal.style.display = 'flex';
                        });
                    }

                    if (cancelAddCustomer) {
                        cancelAddCustomer.addEventListener('click', () => {
                            if (addCustomerModal) addCustomerModal.style.display = 'none';
                            this.resetAddCustomerForm();
                        });
                    }

                    if (submitAddCustomer) {
                        submitAddCustomer.addEventListener('click', () => {
                            this.submitAddCustomer();
                        });
                    }
                },

                // إرسال نموذج إضافة عميل
                async submitAddCustomer() {
                    const name = document.getElementById('new-customer-name')?.value.trim();
                    const mobile = document.getElementById('new-customer-mobile')?.value.trim();
                    const city = document.getElementById('new-customer-city')?.value.trim();
                    const address = document.getElementById('new-customer-address')?.value.trim();
                    const notes = document.getElementById('new-customer-notes')?.value.trim();

                    if (!name) {
                        Helpers.showToast('يرجى إدخال اسم العميل', 'error');
                        return;
                    }

                    if (!mobile) {
                        Helpers.showToast('يرجى إدخال رقم الموبايل', 'error');
                        return;
                    }

                    if (!Helpers.validatePhone(mobile)) {
                        Helpers.showToast('رقم الموبايل غير صحيح', 'error');
                        return;
                    }

                    const result = await ApiManager.addCustomer({
                        name: name,
                        mobile: mobile,
                        city: city,
                        address: address,
                        notes: notes
                    });

                    if (result.ok) {
                        Helpers.showToast('تم إضافة العميل بنجاح', 'success');
                        const addCustomerModal = document.getElementById('add-customer-modal');
                        if (addCustomerModal) addCustomerModal.style.display = 'none';

                        // اختيار العميل الجديد تلقائياً
                        this.selectCustomer(result.customer.id);
                        this.resetAddCustomerForm();

                        // إعادة تحميل قائمة العملاء
                        DataManager.loadCustomersModal();
                    } else {
                        Helpers.showToast(result.error || 'فشل في إضافة العميل', 'error');
                    }
                }, // في EventManager.setupPaymentEvents، نضيف:

                setupPaymentEvents() {
                    // تبديل حالة الدفع
                    document.querySelectorAll('input[name="payment"]').forEach(radio => {
                        radio.addEventListener('change', (e) => {
                            //    (e.target);

                            UI.updatePaymentSection();
                        });
                    });


                    // طرق الدفع للدفع الكامل
                    document.querySelectorAll('#full-payment-section .payment-method').forEach(method => {
                        method.addEventListener('click', function() {
                            document.querySelectorAll('#full-payment-section .payment-method').forEach(m => m.classList.remove('selected'));
                            this.classList.add('selected');
                            AppState.currentPaymentMethod = this.dataset.method;

                            // تحديث الدفعة التلقائية بنوع الدفع الجديد
                            UI.updatePaymentSection();
                        });
                    });

                    // تحديث ملاحظات الدفع الكامل
                    const fullPaymentNotes = document.getElementById('full-payment-notes');
                    if (fullPaymentNotes) {
                        fullPaymentNotes.addEventListener('input', (e) => {
                            AppState.fullPaymentNotes = e.target.value;
                            // تحديث الدفعة التلقائية بالملاحظات الجديدة
                            // if (AppState.payments.length > 0) {
                            //     AppState.payments[0].notes = AppState.fullPaymentNotes;
                            // }
                        });
                    }

                    // إضافة دفعة
                    // إضافة دفعة
                    if (DOM.addPaymentBtn) {



                        DOM.addPaymentBtn.forEach(btn => {
                            const type = btn.dataset.type; // "temporary" أو "partial"

                            btn.addEventListener('click', () => {
                                InvoiceManager.addPayment(type); // تمرير النوع للمعالجة
                            });
                        });
                    }



                    // إضافة دفعة عند الضغط على Enter
                    if (DOM.currentPaymentFull) {
                        DOM.currentPaymentFull.focus()
                        DOM.currentPaymentFull.addEventListener('keypress', (e) => {
                            if (e.key === 'Enter') {
                                InvoiceManager.addPayment('full');
                            }
                        });
                    }
                    if (DOM.currentPaymentPartial) {
                        DOM.currentPaymentPartial.addEventListener('keypress', (e) => {
                            if (e.key === 'Enter') {
                                InvoiceManager.addPayment('partial');
                            }
                        });
                    }

                    // طرق الدفع
                    document.querySelectorAll('.payment-method').forEach(method => {
                        method.addEventListener('click', function() {
                            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                            this.classList.add('selected');
                            AppState.currentPaymentMethod = this.dataset.method;

                            // إظهار/إخفاء تفاصيل التحويل
                            if (DOM.transferDetails) {
                                DOM.transferDetails.style.display =
                                    (AppState.currentPaymentMethod === 'bank_transfer') ? 'block' : 'none';
                            }
                        });
                    });
                },

                // إعادة تعيين نموذج إضافة عميل
                resetAddCustomerForm() {
                    const nameInput = document.getElementById('new-customer-name');
                    const mobileInput = document.getElementById('new-customer-mobile');
                    const cityInput = document.getElementById('new-customer-city');
                    const addressInput = document.getElementById('new-customer-address');
                    const notesInput = document.getElementById('new-customer-notes');

                    if (nameInput) nameInput.value = '';
                    if (mobileInput) mobileInput.value = '';
                    if (cityInput) cityInput.value = '';
                    if (addressInput) addressInput.value = '';
                    if (notesInput) notesInput.value = '';
                },

                // إعداد أحداث المنتجات
                setupProductEvents() {
                    // مسح الباركود
                    if (DOM.scanBarcodeBtn) {
                        DOM.scanBarcodeBtn.addEventListener('click', () => {
                            const barcode = DOM.barcodeInput?.value.trim();
                            if (barcode) {

                                DataManager.findProductByBarcode(barcode);
                            } else {
                                if (DOM.productsModal) DOM.productsModal.style.display = 'flex';
                                DOM.productSearch.focus()

                            }
                        });
                    }

                    // البحث بالباركود عند الضغط على Enter
                    if (DOM.barcodeInput) {
                        DOM.barcodeInput.addEventListener('keydown', async (e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault(); // منع السلوك الافتراضي

                                const barcode = e.target.value.trim();
                                if (barcode) {
                                    const found = await DataManager.findProductByBarcode(barcode);
                                    if (found) {
                                        // إذا تم العثور على المنتج، انتقل لحقل الكمية
                                        if (DOM.productQty) {
                                            DOM.productQty.focus();
                                            DOM.productQty.select(); // اختيار النص للكتابة فوقه
                                        }
                                    } else {
                                        // إذا لم يتم العثور، ابق في نفس الحقل
                                        DOM.barcodeInput.select();
                                    }
                                }
                            }
                        });
                    }

                    // إضافة منتج للفاتورة
                    if (DOM.addProductBtn) {
                        DOM.addProductBtn.addEventListener('click', () => {
                            InvoiceManager.addProductToInvoice();
                        });
                    }

                    // إضافة منتج عند الضغط على Enter في حقل السعر
                    if (DOM.productPrice) {
                        DOM.productPrice.addEventListener('keypress', (e) => {
                            if (e.key === 'Enter') {
                                InvoiceManager.addProductToInvoice();
                            }
                        });
                    }

                    // اختيار منتج من النافذة
                    document.addEventListener('click', (e) => {
                        if (e.target.classList.contains('select-product')) {
                            const productCard = e.target.closest('.product-card');
                            if (productCard) {
                                const productId = parseInt(productCard.dataset.id);
                                const product = AppData.products.find(p => p.id === productId);

                                if (DOM.productSelect) DOM.productSelect.value = productId;
                                if (DOM.productsModal) DOM.productsModal.style.display = 'none';

                                // تعبئة السعر تلقائياً
                                UI.updatePriceField(product);
                                if (DOM.productQty) DOM.productQty.focus();
                            }
                        }

                        // اختيار سعر من الأزرار في كارد المنتج
                        if (e.target.classList.contains('select-price')) {
                            const productCard = e.target.closest('.product-card');
                            if (productCard) {
                                const productId = parseInt(productCard.dataset.id);
                                const priceType = e.target.dataset.type;
                                const price = parseFloat(e.target.dataset.price);

                                if (DOM.productSelect) DOM.productSelect.value = productId;
                                if (DOM.productsModal) DOM.productsModal.style.display = 'none';

                                // تحديث نوع السعر الحالي
                                AppState.currentPriceType = priceType;

                                // تحديث أزرار نوع السعر
                                UI.updatePriceButtons();

                                // تعبئة السعر
                                if (DOM.productPrice) DOM.productPrice.value = price;
                                if (DOM.productQty) DOM.productQty.focus();
                            }
                        }
                    });

                    // البحث في المنتجات
                    if (DOM.productSearch) {
                        DOM.productSearch.addEventListener('input', (e) => {
                            DataManager.filterProducts(e.target.value);
                        });
                    }

                    // تغيير نوع السعر

                    // تغيير نوع السعر عند النقر على الأزرار
                    if (DOM.priceRetailBtn) {


                        DOM.priceRetailBtn.addEventListener('click', () => {
                            AppState.currentPriceType = 'retail';
                            UI.updatePriceField();
                        });
                    }

                    if (DOM.priceWholesaleBtn) {


                        DOM.priceWholesaleBtn.addEventListener('click', () => {
                            AppState.currentPriceType = 'wholesale';
                            UI.updatePriceField();
                        });

                    }

                    // تحديث السعر عند تغيير المنتج في الـ select
                    // في قسم setupProductEvents داخل EventManager - أضف هذا الكود:
                    if (DOM.productSelect) {
                        DOM.productSelect.addEventListener('change', function() {


                            const productId = parseInt(this.value);
                            if (productId) {

                                const product = AppData.products.find(p => +p.id === productId);
                                if (product) {
                                    // تحديث السعر بناءً على نوع السعر الحالي
                                    UI.updatePriceField(product);

                                    // تحديث حالة الأزرار
                                    UI.updatePriceButtons();

                                    // التركيز على حقل الكمية
                                    if (DOM.productQty) DOM.productQty.focus();
                                }
                            } else {
                                // إذا لم يتم اختيار منتج، إعادة تعيين الحقول
                                if (DOM.productPrice) DOM.productPrice.value = '';
                            }
                        });
                    }


                    // تحديث السعر عند تغيير المنتج في الـ select


                },


                // إعداد أحداث الخصم
                setupDiscountEvents() {
                    const cancelDiscountBtn = document.getElementById('cancel-discount');
                    if (cancelDiscountBtn) {
                        cancelDiscountBtn.addEventListener('click', () => {
                            this.cancelDiscount();
                        });
                    }
                    // الخصم السريع
                    document.querySelectorAll('.quick-discount').forEach(discount => {
                        discount.addEventListener('click', function() {
                            document.querySelectorAll('.quick-discount').forEach(d => d.classList.remove('active'));
                            this.classList.add('active');

                            if (DOM.discountType) DOM.discountType.value = 'percent';
                            if (DOM.discountValue) DOM.discountValue.value = this.dataset.value;
                            EventManager.updateDiscount();
                        });
                    });

                    // تحديث الخصم
                    if (DOM.discountType) {
                        DOM.discountType.addEventListener('change', EventManager.updateDiscount);
                    }
                    if (DOM.discountValue) {
                        DOM.discountValue.addEventListener('input', EventManager.updateDiscount);
                    }
                },

                // إعداد أحداث النماذج
                setupModalEvents() {
                    // إغلاق النماذج
                    document.querySelectorAll('.modal-backdrop').forEach(modal => {
                        modal.addEventListener('click', (e) => {
                            if (e.target === modal) {
                                modal.style.display = 'none';
                            }
                        });
                    });

                    if (DOM.cancelConfirm) {
                        DOM.cancelConfirm.addEventListener('click', () => {
                            if (DOM.confirmModal) DOM.confirmModal.style.display = 'none';
                        });
                    }

                    // التأكيد النهائي
                    if (DOM.finalConfirm) {
                        DOM.finalConfirm.addEventListener('click', () => {
                            InvoiceManager.processInvoice();
                            if (DOM.confirmModal) DOM.confirmModal.style.display = 'none';
                        });
                    }

                    // زر تفريغ الفاتورة
                    if (DOM.clearBtn) {
                        DOM.clearBtn.addEventListener('click', () => {
                            InvoiceManager.clearInvoice();


                        });
                    }

                    // زر تأكيد الفاتورة
                    if (DOM.confirmBtn) {
                        DOM.confirmBtn.addEventListener('click', () => {
                            InvoiceManager.confirmInvoice();
                        });
                    }

                    const printOnlyBtn = document.getElementById('print-only');
                    if (printOnlyBtn) {
                        printOnlyBtn.addEventListener('click', () => {
                            InvoiceManager.printOnly();
                        });
                    }
                },

                // إعداد أحداث التنقل
                setupNavigationEvents() {
                    UI.setupFieldNavigation();
                },

                // إعداد زر العميل النقدي
                setupQuickCustomer() {
                    const quickCustomerBtn = document.createElement('button');

                    quickCustomerBtn.className = 'btn btn-success mt-1 mb-1 ';
                    quickCustomerBtn.innerHTML = '<i class="fas fa-user"></i>';
                    quickCustomerBtn.id = 'fixed_client_btn';
                    quickCustomerBtn.style.marginLeft = '10px';


                    quickCustomerBtn.addEventListener('click', async () => {
                        // منع الضغط المتكرر
                        if (quickCustomerBtn.disabled) return;
                        quickCustomerBtn.disabled = true;

                        try {
                            // اختيار العميل (مثال ID = 8)
                            await EventManager.selectCustomer(8);


                        } catch (error) {
                            Helpers.showToast('❌ خطأ أثناء اختيار العميل: ' + error.message, 'error');
                        } finally {
                            // إعادة تفعيل الزر
                            quickCustomerBtn.disabled = false;
                        }
                    });


                    if (DOM.changeCustomerBtn && DOM.changeCustomerBtn.parentNode) {
                        // DOM.changeCustomerBtn.parentNode.insertBefore(quickCustomerBtn, DOM.changeCustomerBtn.nextSibling);
                        DOM.customerActions.append(quickCustomerBtn)
                    }

                    // زر إضافة عميل جديد
                    const addCustomerBtn = document.createElement('button');
                    addCustomerBtn.id = 'add-customer-btn';
                    addCustomerBtn.className = 'btn btn-primary';
                    addCustomerBtn.innerHTML = '<i class="fas fa-plus"></i>';
                    addCustomerBtn.style.marginLeft = '10px';

                    if (DOM.changeCustomerBtn && DOM.changeCustomerBtn.parentNode) {
                        // DOM.changeCustomerBtn.parentNode.insertBefore(addCustomerBtn, quickCustomerBtn.nextSibling);
                        DOM.customerActions.append(addCustomerBtn)

                    }
                },

                // تحديث الخصم
                updateDiscount() {
                    if (DOM.discountType && DOM.discountValue) {
                        AppState.discount.type = DOM.discountType.value;
                        AppState.discount.value = parseFloat(DOM.discountValue.value) || 0;
                        UI.update();
                    }
                },
                // أضف دالة إلغاء الخصم:
                cancelDiscount() {
                    AppState.discount = {
                        type: "percent",
                        value: 0
                    };

                    if (DOM.discountType) DOM.discountType.value = 'percent';
                    if (DOM.discountValue) DOM.discountValue.value = '0';

                    // إزالة التحديد من الخصومات السريعة
                    document.querySelectorAll('.quick-discount').forEach(d => d.classList.remove('active'));

                    UI.update();
                    Helpers.showToast('تم إلغاء الخصم', 'success');
                },




                //  const 
                // إعداد أحداث الملاحظات
                setupNotesEvents() {
                    // ملاحظات الفاتورة
                    const invoiceNotes = document.getElementById('invoice-notes');
                    if (invoiceNotes) {
                        invoiceNotes.addEventListener('input', (e) => {
                            AppState.invoiceNotes = e.target.value;
                        });
                    }

                    // ملاحظات الدفع
                    const paymentNotes = document.getElementById('payment-notes-input');
                    if (paymentNotes) {
                        paymentNotes.addEventListener('input', (e) => {
                            AppState.paymentNotes = e.target.value;
                        });
                    }
                },
                // async selectCustomer(customerId) {
                //     const result = await ApiManager.selectCustomerApi(customerId);
                //     if (result.ok) {
                //         AppState.currentCustomer = result.customer;

                //         // تحميل الشغلات لهذا العميل
                //         if (customerId !== 8) {
                //             await this.loadWorkOrdersForCurrentCustomer();
                //             UI.updateWalletBalance()
                //         }

                //         UI.updateCustomerUI();
                //         Helpers.showToast(`تم اختيار العميل ${AppState.currentCustomer.name}`, 'success');
                //     } else {
                //         Helpers.showToast(result.error || 'فشل في اختيار العميل', 'error');
                //     }
                // },

                async selectCustomer(customerId) {
                    const result = await ApiManager.selectCustomerApi(customerId);
                    if (result.ok) {
                        AppState.currentCustomer = result.customer;

                        // تحميل الشغلات فقط إذا كان العميل ليس نقدي


                        if (customerId !== 8) {
                            await this.loadWorkOrdersForCurrentCustomer();
                            UI.updateWalletBalance()


                        } else {

                            // إخفاء قسم الشغلات للعميل النقدي
                            AppState.availableWorkOrders = [];
                            AppState.currentWorkOrder = null;
                            UI.updateWorkOrderSection();
                        }

                        UI.updateCustomerUI();
                        Helpers.showToast(`تم اختيار العميل ${AppState.currentCustomer.name}`, 'success');
                    } else {
                        Helpers.showToast(result.error || 'فشل في اختيار العميل', 'error');
                    }
                },
                async loadWorkOrdersForCurrentCustomer() {
                    if (AppState.currentCustomer) {
                        AppState.availableWorkOrders = await ApiManager.loadWorkOrders(AppState.currentCustomer.id);

                        AppState.currentWorkOrder = null; // إعادة تعيين الشغلانة المختارة
                        UI.updateWorkOrderSection();
                    }
                }




            };



            // ============================
            // تهيئة التطبيق
            // ============================
        </script>

        <?php
        require_once BASE_DIR . 'partials/footer.php';
        ?>