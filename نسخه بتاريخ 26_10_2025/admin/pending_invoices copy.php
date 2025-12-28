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

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

// ------------------ AJAX endpoint (يجب أن يكون قبل أي إخراج HTML) ------------------
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

/// handel- delete - invoice 

// الاستماع للـ POST action cancel_invoice


// debug config - تفعيل لبيئة التطوير فقط





// Only handle AJAX POST cancel action
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

// --- end handler ---


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
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['message'] = "<div class='alert alert-danger'>خطأ: طلب غير صالح (CSRF).</div>";
    } elseif (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        $_SESSION['message'] = "<div class='alert alert-danger'>ليس لديك صلاحية لتنفيذ هذه العملية.</div>";
    } else {
        $invoice_id_to_deliver = intval($_POST['invoice_id_to_deliver'] ?? 0);
        if ($invoice_id_to_deliver > 0) {
            $updated_by = intval($_SESSION['id'] ?? 0);
            $sql_update_delivery = "UPDATE invoices_out SET delivered = 'yes', updated_by = ?, updated_at = NOW() WHERE id = ?";
            if ($stmt_update = $conn->prepare($sql_update_delivery)) {
                $stmt_update->bind_param("ii", $updated_by, $invoice_id_to_deliver);
                if ($stmt_update->execute()) {
                    $_SESSION['message'] = ($stmt_update->affected_rows > 0) ? "<div class='alert alert-success'>تم تحديث حالة الفاتورة رقم #{$invoice_id_to_deliver} إلى مستلمة.</div>" : "<div class='alert alert-warning'>لم يتم تعديل الحالة — ربما كانت الفاتورة مستلمة سابقاً.</div>";
                } else {
                    $_SESSION['message'] = "<div class='alert alert-danger'>خطأ أثناء التحديث: " . e($stmt_update->error) . "</div>";
                }
                $stmt_update->close();
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger'>خطأ في تحضير استعلام التحديث: " . e($conn->error) . "</div>";
            }
        } else {
            $_SESSION['message'] = "<div class='alert alert-warning'>رقم فاتورة غير صالح.</div>";
        }
    }

    // إعادة توجيه للحفاظ على GET params (PRG)
    $redirect = htmlspecialchars($_SERVER['PHP_SELF']);
    $params = [];
    if (!empty($_GET['invoice_q'])) $params[] = 'invoice_q=' . urlencode($_GET['invoice_q']);
    if (!empty($_GET['mobile_q'])) $params[] = 'mobile_q=' . urlencode($_GET['mobile_q']);
    if (!empty($_GET['filter_group_val'])) $params[] = 'filter_group_val=' . urlencode($_GET['filter_group_val']);
    if (!empty($_GET['customer_id'])) $params[] = 'customer_id=' . urlencode($_GET['customer_id']);
    // notes
    if (!empty($_GET['notes_q'])) $params[] = 'notes_q=' . urlencode($_GET['notes_q']);

    if (!empty($params)) $redirect .= '?' . implode('&', $params);
    header("Location: " . $redirect);
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
                if ($conn->in_transaction) $conn->rollback();
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
// إجمالي الفواتير غير المستلمة (بدون تطبيق البحث) لتلخيص
$sql_grand_total = "SELECT COALESCE(SUM(ioi.total_price),0) AS grand_total
                    FROM invoice_out_items ioi
                    JOIN invoices_out io ON ioi.invoice_out_id = io.id
                    WHERE io.delivered = 'no'";
$res_gt = $conn->query($sql_grand_total);
if ($res_gt) {
    $grand_total_all_pending = floatval($res_gt->fetch_assoc()['grand_total'] ?? 0);
    $res_gt->free();
}

// بناء استعلام جلب
$sql_select = "SELECT i.id, i.invoice_group, i.created_at,
                      COALESCE(c.name,'(عميل نقدي)') AS customer_name,
                      COALESCE(c.mobile,'-') AS customer_mobile,
                      u.username AS creator_name,
                      COALESCE(i.notes,'') AS notes,
                      COALESCE((SELECT SUM(item.total_price) FROM invoice_out_items item WHERE item.invoice_out_id = i.id),0) AS invoice_total
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
    #invoiceModal .mymodal {
        /* min-width: 60%; */
        /* min-height: fit-content; */
    }

    /* max-height: 570px; */
    .ipc-toast {
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

    .ipc-toast.show {
        opacity: 1;
        transform: translateY(0);
    }


    @media print {
        .no-print {
            display: none !important;
        }
    }

    .swal2-container {
        z-index: 10000 !important;
    }
</style>

<div class="container mt-5 pt-3 ">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1><i class="fas fa-truck-loading"></i> الفواتير غير المستلمة</h1>
            <?php if ($customer_filter_id > 0): ?>
                <div class="small text-muted">عرض فواتير العميل رقم: <strong>#<?php echo e($customer_filter_id); ?></strong></div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2">
            <!-- زر العودة بسيط -->
            <button type="button" class="btn btn-outline-secondary" onclick="history.back();"><i class="fas fa-arrow-left"></i> عودة</button>
            <a href="<?php echo $delivered_invoices_link; ?>" class="btn btn-success"><i class="fas fa-check-double"></i> عرض الفواتير المستلمة</a>
        </div>
    </div>

    <?php echo $message; ?>

    <!-- نموذج البحث: مضاف حقل بحث عن customer id -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body align-items-center">
            <form method="get" action="<?php echo $current_page_link; ?>" class="row gx-3 gy-2 align-items-center">
                <div class="col-md-2 mt-2">
                    <label class="form-label small mb-1">بحث برقم الفاتورة</label>
                    <input type="text" name="invoice_q" value="<?php echo e($invoice_q); ?>" class="form-control" placeholder="مثال: 123">
                </div>

                <div class="col-md-3 mt-2">
                    <label class="form-label small mb-1">بحث برقم هاتف العميل</label>
                    <input type="text" name="mobile_q" value="<?php echo e($mobile_q); ?>" class="form-control" placeholder="مثال: 01157787113">
                </div>

                <div class="col-md-3 mt-2">
                    <label class="form-label small mb-1">بحث حسب معرف العميل (ID)</label>
                    <input type="number" name="customer_id" value="<?php echo ($customer_filter_id > 0) ? e($customer_filter_id) : ''; ?>" class="form-control" placeholder="مثال: 8">
                </div>

                <!-- حقل بحث الملاحظات -->
                <div class="col-md-4 mt-2">
                    <label class="form-label small mb-1">بحث حسب الملاحظات</label>
                    <input type="text" name="notes_q" value="<?php echo e(isset($_GET['notes_q']) ? trim((string)$_GET['notes_q']) : ''); ?>" class="form-control" placeholder="ابحث في ملاحظات الفاتورة أو العميل">
                </div>
                <div class="col-md-5 mt-2">
                    <label class="form-label small mb-1">من تاريخ</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                </div>
                <div class="col-md-5 mt-2">
                    <label class="form-label small mb-1">إلى تاريخ</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                </div>


                <div class="col-md-2 mt-4 d-flex gap-2 align-items-center">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> بحث</button>
                    <a href="<?php echo $current_page_link; ?>" class="btn btn-outline-secondary w-100"><i class="fas fa-times"></i> مسح</a>
                </div>
            </form>
            <div class="note-text mt-3">يمكنك البحث بالرقم الدقيق للفاتورة، أو رقم هاتف العميل، أو رقم معرف العميل (ID).</div>
        </div>
    </div>

    <!-- جدول الفواتير -->
    <div class="card shadow">
        <div class="card-header">
            قائمة الفواتير التي لم يتم تسليمها
            <?php if ($invoice_q !== '' || $mobile_q !== '' || $customer_filter_id > 0): ?>
                <span class="badge bg-info ms-2">نتائج البحث</span>
            <?php endif; ?>
        </div>

        <!-- <div class="card-body"> -->
        <div class="table-responsive custom-table-wrapper">
            <table class="tabl custom-table">
                <thead class="table-dark center">
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>اسم العميل</th>
                        <th>الموبايل</th>
                        <th>مجموعة الفاتورة</th>
                        <th class="d-none d-md-table-cell">أنشئت بواسطة</th>
                        <th class="d-none d-md-table-cell">تاريخ الإنشاء</th>
                        <th class="d-none d-md-table-cell">الملاحظات</th>

                        <th class="text-end">إجمالي الفاتورة</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()):
                            $current_invoice_total_for_row = floatval($row["invoice_total"] ?? 0);
                            $displayed_invoices_sum += $current_invoice_total_for_row;
                        ?>
                            <tr>
                                <td>#<?php echo e($row["id"]); ?></td>
                                <td><?php echo e($row["customer_name"]); ?></td>
                                <td><?php echo e($row["customer_mobile"]); ?></td>
                                <td><span class="badge bg-info"><?php echo e($row["invoice_group"]); ?></span></td>
                                <td class="d-none d-md-table-cell"><?php echo e($row["creator_name"] ?? 'غير معروف'); ?></td>
                                <td class="d-none d-md-table-cell"><?php echo e(date('Y-m-d H:i A', strtotime($row["created_at"]))); ?></td>
                                <?php
                                $noteText = trim((string)($row['notes'] ?? ''));
                                $noteDisplay = $noteText === '' ? '-' : (mb_strlen($noteText) > 70 ? mb_substr($noteText, 0, 15) . '...' : $noteText);
                                ?>
                                <td class="d-none d-md-table-cell" title="<?php echo e($noteText); ?>">
                                    <?php echo e($noteDisplay); ?>
                                </td>

                                <td class="text-end fw-bold"><?php echo number_format($current_invoice_total_for_row, 2); ?> ج.م</td>
                                <td class="text-center">
                                    <!-- زر عرض يفتح المودال -->
                                    <button type="button" class="btn btn-info btn-sm btn-open-modal" data-invoice-id="<?php echo e($row["id"]); ?>" title="عرض التفاصيل"><i class="fas fa-eye"></i></button>

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                                        <!-- تسليم سريع -->
                                        <form action="<?php echo $current_page_link; ?>?<?php echo http_build_query($_GET); ?>" method="post" class="d-inline ms-1">
                                            <input type="hidden" name="invoice_id_to_deliver" value="<?php echo e($row["id"]); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <button type="submit" name="mark_delivered" class="btn btn-success btn-sm" title="تحديد كمستلمة"><i class="fas fa-check-circle"></i></button>
                                        </form>
                                        <!-- مثال زر داخل كل صف -->
                                        <button class="btn btn-sm btn-danger btn-cancel-invoice" data-invoice-id=<?php echo e($row['id']); ?>" title="إلغاء الفاتورة">
                                            إلغاء
                                        </button>
                                        <!-- <button type="button" class="btn btn-warning btn-sm btn-cancel-invoice" data-invoice-id="<?php echo e($row['id']); ?>" title="إلغاء الفاتورة"><i class="fas fa-ban"></i></button> -->

                                        <!-- حذف -->
                                        <form action="<?php echo $current_page_link; ?>?<?php echo http_build_query($_GET); ?>" method="post" class="d-inline ms-1" onsubmit="return confirm('هل أنت متأكد من حذف الفاتورة #<?php echo e($row['id']); ?> وكل بنودها؟ سيتم إعادة الكميات إذا كانت الفاتورة مستلمة.');">
                                            <input type="hidden" name="invoice_out_id_to_delete" value="<?php echo e($row["id"]); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="redirect_to" value="pending">
                                            <!-- <button type="submit" name="delete_sales_invoice" class="btn btn-danger btn-sm" title="حذف"><i class="fas fa-trash"></i></button> -->
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">لا توجد فواتير غير مستلمة حالياً.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- </div> -->
    </div>

    <!-- ملخص الإجماليات -->
    <div class="row mt-4">
        <div class="col-md-6 offset-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-center mb-3 note-text">ملخص الإجماليات</h5>
                    <ul class="list-group list-group-flush rounded">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>إجمالي الفواتير المعروضة حالياً:</strong>
                            <span class="badge bg-primary rounded-pill fs-6"><?php echo number_format($displayed_invoices_sum, 2); ?> ج.م</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <strong>الإجمالي الكلي لجميع الفواتير غير المستلمة:</strong>
                            <span class="badge bg-danger rounded-pill fs-6"><?php echo number_format($grand_total_all_pending, 2); ?> ج.م</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
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

<div id="ipc_toast_holder"></div>

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
            let itemsHtml = `<div 
            class="custom-table-wrapper"
            ><table class="custom-table"><thead class="center"><tr><th style="padding:10px;width:40px">#</th><th style="padding:10px;text-align:right">اسم / كود</th><th style="padding:10px;width:100px;text-align:center">كمية</th><th style="padding:10px;width:120px;text-align:right">سعر البيع</th><th style="padding:10px;width:120px;text-align:right">الإجمالي</th></tr></thead><tbody>`;
            let total = 0;
            if (items && items.length) {
                items.forEach((it, idx) => {
                    const name = it.product_name ? (it.product_name + ' — ' + (it.product_code || '')) : ('#' + it.product_id);
                    const qty = parseFloat(it.quantity || 0).toFixed(2);
                    const price = parseFloat(it.selling_price || it.cost_price_per_unit || 0).toFixed(2);
                    const line = parseFloat(it.total_price || 0).toFixed(2);
                    total += parseFloat(line);
                    itemsHtml += `<tr><td style="padding:10px">${idx+1}</td><td style="padding:10px;text-align:right">${escapeHtml(name)}</td><td style="padding:10px;text-align:center">${qty}</td><td style="padding:10px;text-align:right">${price} ج.م</td><td style="padding:10px;text-align:right;font-weight:700">${line} ج.م</td></tr>`;
                });
            } else {
                itemsHtml += `<tr><td colspan="5" style="padding:12px;text-align:center">لا توجد بنود.</td></tr>`;
            }
            itemsHtml += `</tbody><tfoot><tr><td colspan="4" style="padding:12px;text-align:right;font-weight:700">الإجمالي الكلي</td><td style="padding:12px;text-align:right;font-weight:800">${total.toFixed(2)} ج.م</td></tr></tfoot></table></div>`;

            // notes
            let notesHtml = '';
            if (inv.notes && inv.notes.trim() !== '') {
                notesHtml = `<div style="margin-top:12px;padding:12px;border-radius:8px;background:rgba(0,0,0,0.02)"  class="no-print">
            <div style="font-weight:700;margin-bottom:8px ">ملاحظات</div><div style="white-space:pre-wrap;">${escapeHtml(inv.notes).replace(/\n/g,'<br>')}</div><div style="margin-top:8px"><button class="btn-copy-notes btn btn-outline-secondary btn-sm" data-notes="${escapeHtml(inv.notes)}">نسخ الملاحظات</button></div></div>`;
            }

            modalContent.innerHTML = titleHtml + infoHtml + itemsHtml + notesHtml;

            // set modal forms values
            deliverIdInput.value = inv.id;
            deleteIdInput.value = inv.id;
            modalTotal.innerText = 'الإجمالي: ' + total.toFixed(2) + ' ج.م';

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
        printBtn.addEventListener('click', function() {
            try {
                const clone = modalContent.cloneNode(true);
                // remove all no-print elements from clone
                clone.querySelectorAll('.no-print').forEach(n => n.remove());
                const html = modalContent.innerHTML;
                const printHtml = `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
                <title>طباعة فاتورة</title><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font-family:Arial,Helvetica,sans-serif;direction:rtl;padding:18px;} table{width:100%;border-collapse:collapse;} th,td{border:1px solid #ddd;padding:8px;text-align:right;} th{background:#f3f4f6;font-weight:700;} tfoot td{font-weight:800;}</style></head><body>${clone.innerHTML}</body></html>`;
                const iframe = document.createElement('iframe');
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '0';
                iframe.style.height = '0';
                iframe.style.border = '0';
                document.body.appendChild(iframe);
                const d = iframe.contentWindow.document;
                d.open();
                d.write(printHtml);
                d.close();
                iframe.onload = function() {
                    iframe.contentWindow.focus();
                    setTimeout(() => {
                        iframe.contentWindow.print();
                        setTimeout(() => document.body.removeChild(iframe), 500);
                    }, 200);
                };
                setTimeout(() => {
                    if (document.body.contains(iframe)) {
                        try {
                            iframe.contentWindow.print();
                            document.body.removeChild(iframe);
                        } catch (e) {
                            document.body.removeChild(iframe);
                        }
                    }
                }, 1500);
            } catch (e) {
                console.error('print error', e);
                alert('حدث خطأ أثناء الطباعة');
            }
        });

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
    });
</script>

<?php
// تحرير الموارد
if ($result && is_object($result)) $result->free();
$conn->close();
require_once BASE_DIR . 'partials/footer.php';
?>