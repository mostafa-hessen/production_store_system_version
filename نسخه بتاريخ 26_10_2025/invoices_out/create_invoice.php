<?php
// create_invoice.php (mysqli version)
// إنشاء فاتورة — يدعم FIFO allocations, CSRF (meta + JS), اختيار عميل مثبت، إضافة عميل، created_by tracking.

// ========== BOOT (config + session) ==========
$page_title = "إنشاء فاتورة بيع";
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
      $stmt = $conn->prepare("SELECT id,name,mobile,city,address FROM customers WHERE id = ?");
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

  // 6) save_invoice (POST)
  if ($action === 'save_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
      jsonOut(['ok' => false, 'error' => 'رمز التحقق (CSRF) غير صالح. أعد تحميل الصفحة وحاول مجدداً.']);
    }

    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $status = ($_POST['status'] ?? 'pending') === 'paid' ? 'paid' : 'pending';
    $items_json = $_POST['items'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $created_by_i = (int)($_SESSION['id'] ?? 0);

    if ($customer_id <= 0) jsonOut(['ok' => false, 'error' => 'الرجاء اختيار عميل.']);
    if (empty($items_json)) jsonOut(['ok' => false, 'error' => 'لا توجد بنود لإضافة الفاتورة.']);

    $items = json_decode($items_json, true);
    if (!is_array($items) || count($items) === 0) jsonOut(['ok' => false, 'error' => 'بنود الفاتورة غير صالحة.']);

    try {
      // begin transaction
      $conn->begin_transaction();

      // insert invoice header
      $delivered = ($status === 'paid') ? 'yes' : 'no';
      $invoice_group = 'group1';
      $stmt = $conn->prepare("INSERT INTO invoices_out (customer_id, delivered, invoice_group, created_by, created_at, notes) VALUES (?, ?, ?, ?, NOW(), ?)");
      if (!$stmt) throw new Exception($conn->error);
      $stmt->bind_param('issis', $customer_id, $delivered, $invoice_group, $created_by_i, $notes);
      // Note: bind types: i(customer_id), s(delivered), s(invoice_group) but I used 'issis' for ordering
      // To ensure correct binding, reorder: customer_id (i), delivered (s), invoice_group (s), created_by (i), notes (s) => 'issis'
      $stmt->execute();
      if ($stmt->errno) {
        $e = $stmt->error;
        $stmt->close();
        throw new Exception($e);
      }
      $invoice_id = (int)$conn->insert_id;
      $stmt->close();

      $totalRevenue = 0.0;
      $totalCOGS = 0.0;

      // prepare commonly used statements
      $insertItemStmt = $conn->prepare("INSERT INTO invoice_out_items (invoice_out_id, product_id, quantity, total_price, cost_price_per_unit, selling_price, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
      if (!$insertItemStmt) throw new Exception($conn->error);

      $insertAllocStmt = $conn->prepare("INSERT INTO sale_item_allocations (sale_item_id, batch_id, qty, unit_cost, line_cost, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
      if (!$insertAllocStmt) throw new Exception($conn->error);

      $updateBatchStmt = $conn->prepare("UPDATE batches SET remaining = ?, status = ?, adjusted_at = NOW(), adjusted_by = ? WHERE id = ?");
      if (!$updateBatchStmt) throw new Exception($conn->error);

      $selectBatchesStmt = $conn->prepare("SELECT id, remaining, unit_cost FROM batches WHERE product_id = ? AND status = 'active' AND remaining > 0 ORDER BY received_at ASC, created_at ASC, id ASC FOR UPDATE");
      if (!$selectBatchesStmt) throw new Exception($conn->error);

      foreach ($items as $it) {
        $product_id = (int)($it['product_id'] ?? 0);
        $qty = (float)($it['qty'] ?? 0);
        $selling_price = (float)($it['selling_price'] ?? 0);
        if ($product_id <= 0 || $qty <= 0) {
          $conn->rollback();
          jsonOut(['ok' => false, 'error' => "بند غير صالح (معرف/كمية)."]);
        }

        // --- احصل على اسم المنتج لاستخدامه في رسائل الخطأ (إن وُجد) ---
        $product_name = null;
        $pnameStmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
        if ($pnameStmt) {
          $pnameStmt->bind_param('i', $product_id);
          $pnameStmt->execute();
          $pres = $pnameStmt->get_result();
          $prow = $pres ? $pres->fetch_assoc() : null;
          $product_name = $prow ? $prow['name'] : null;
          $pnameStmt->close();
        }

        // allocate FIFO
        $selectBatchesStmt->bind_param('i', $product_id);
        $selectBatchesStmt->execute();
        $bres = $selectBatchesStmt->get_result();
        $availableBatches = $bres->fetch_all(MYSQLI_ASSOC);

        $need = $qty;
        $allocations = [];
        foreach ($availableBatches as $b) {
          if ($need <= 0) break;
          $avail = (float)$b['remaining'];
          if ($avail <= 0) continue;
          $take = min($avail, $need);
          $allocations[] = ['batch_id' => (int)$b['id'], 'take' => $take, 'unit_cost' => (float)$b['unit_cost']];
          $need -= $take;
          // ======= start update unit cost of invoice_out_items
          // ----------------------
          // بعد الانتهاء من تعديل sale_item_allocations للـ $invoiceItemId
          // احسب المتوسط الجديد من التخصيصات المتبقية
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
            $sumCost = (float) ($row['sum_cost'] ?? 0.0);
            $sumQty  = (float) ($row['sum_qty'] ?? 0.0);
          } else {
            // fallback bind_result
            $sumStmt->bind_result($sum_cost_f, $sum_qty_f);
            if ($sumStmt->fetch()) {
              $sumCost = (float)$sum_cost_f;
              $sumQty  = (float)$sum_qty_f;
            }
          }
          $sumStmt->close();

          // احسب المتوسط الجديد (unit cost)
          if ($sumQty > 1e-9) {
            // دقة داخلية: 4 منازل عشرية
            $new_unit_cost = round($sumCost / $sumQty, 4);
          } else {
            // لا تخصيصات متبقية
            $new_unit_cost = 0.0;
          }

          // الآن حدث cost_price_per_unit في invoice_out_items (إذا أردت تغييره)
          // تأكد من أن prepared statement موجود أو حضّره الآن
          $updateCostStmt = $conn->prepare("UPDATE invoice_out_items SET cost_price_per_unit = ? WHERE id = ?");
          if ($updateCostStmt === false) throw new Exception("prepare failed: " . $conn->error);
          $updateCostStmt->bind_param('di', $new_unit_cost, $invoiceItemId);
          $updateCostStmt->execute();
          $updateCostStmt->close();

          // خيار إضافي: احسب cost_total مؤقتًا لاستخدامه لاحقًا (لا تخزّنه إن لم يكن موجود عمود)
          // $new_cost_total = round($new_unit_cost * ($currentItems[$invoiceItemId]['qty'] - $info['qty']), 4);

          //======== end  update unit cost of invoice_out_items

        }
        if ($need > 0.00001) {
          // jsonOut(['ok' => false, 'error' => "الرصيد غير كافٍ للمنتج (ID: {$product_id})."]);

          $conn->rollback();
          jsonOut([
            'ok' => false,
            'error' => "الرصيد غير كافٍ للمنتج.(: {$product_name}).   (ID: {$product_id}).  ",

          ]);
        }

        $itemTotalCost = 0.0;
        foreach ($allocations as $a) $itemTotalCost += $a['take'] * $a['unit_cost'];
        $cost_price_per_unit = ($qty > 0) ? ($itemTotalCost / $qty) : 0.0;
        $lineTotalPrice = $qty * $selling_price;

        // insert invoice item
        // types: invoice_id(i), product_id(i), quantity(d), total_price(d), cost_price_per_unit(d), selling_price(d) => 'iidddd'
        $invoice_id_i = $invoice_id;
        $prod_id_i = $product_id;
        $insertItemStmt->bind_param('iidddd', $invoice_id_i, $prod_id_i, $qty, $lineTotalPrice, $cost_price_per_unit, $selling_price);
        $insertItemStmt->execute();
        if ($insertItemStmt->errno) {
          $err = $insertItemStmt->error;
          $insertItemStmt->close();
          throw new Exception($err);
        }
        $invoice_item_id = (int)$conn->insert_id;

        // apply allocations and update batches
        foreach ($allocations as $a) {
          // lock & get current remaining (FOR UPDATE)
          $stmtCur = $conn->prepare("SELECT remaining FROM batches WHERE id = ? FOR UPDATE");
          if (!$stmtCur) {
            $conn->rollback();
            throw new Exception($conn->error);
          }
          $batch_id_i = $a['batch_id'];
          $stmtCur->bind_param('i', $batch_id_i);
          $stmtCur->execute();
          $cres = $stmtCur->get_result();
          $curRow = $cres->fetch_assoc();
          $stmtCur->close();

          $curRem = $curRow ? (float)$curRow['remaining'] : 0.0;
          $newRem = max(0.0, $curRem - $a['take']);
          $newStatus = ($newRem <= 0) ? 'consumed' : 'active';

          // update batch: remaining (d), status (s), adjusted_by (i), id (i)
          $updateBatchStmt->bind_param('dsii', $newRem, $newStatus, $created_by_i, $batch_id_i);
          $updateBatchStmt->execute();
          if ($updateBatchStmt->errno) {
            $err = $updateBatchStmt->error;
            $updateBatchStmt->close();
            throw new Exception($err);
          }

          $lineCost = $a['take'] * $a['unit_cost'];

          // insert allocation: sale_item_id(i), batch_id(i), qty(d), unit_cost(d), line_cost(d), created_by(i) => 'iidddi'
          $sale_item_id_i = $invoice_item_id;
          $batch_i = $batch_id_i;
          $qty_d = $a['take'];
          $unit_cost_d = $a['unit_cost'];
          $line_cost_d = $lineCost;
          $insertAllocStmt->bind_param('iidddi', $sale_item_id_i, $batch_i, $qty_d, $unit_cost_d, $line_cost_d, $created_by_i);
          $insertAllocStmt->execute();
          if ($insertAllocStmt->errno) {
            $err = $insertAllocStmt->error;
            $insertAllocStmt->close();
            throw new Exception($err);
          }
        }

        $totalRevenue += $lineTotalPrice;
        $totalCOGS += $itemTotalCost;
      } // end foreach items

      // commit
      // $conn->commit();

      // jsonOut([
      //     'ok' => true,
      //     'msg' => 'تم إنشاء الفاتورة بنجاح.',
      //     'invoice_id' => $invoice_id,
      //     'invoice_number' => $invoice_id,
      //     'total_revenue' => round($totalRevenue, 2),
      //     'total_cogs' => round($totalCOGS, 2)
      // ]);

      // commit
      $conn->commit();

      // --- إضافة: مسح العميل المختار من الجلسة بعد إنشاء الفاتورة ---
      if (isset($_SESSION['selected_customer'])) {
        unset($_SESSION['selected_customer']);
      }
      // --- نهاية الإضافة ---

      jsonOut([
        'ok' => true,
        'msg' => 'تم إنشاء الفاتورة بنجاح.',
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_id,
        'total_revenue' => round($totalRevenue, 2),
        'total_cogs' => round($totalCOGS, 2)
      ]);
    } catch (Exception $e) {
      if ($conn->in_transaction) {
        // procedural property check fallback
        @$conn->rollback();
      } else {
        // mysqli has method rollback()
        @$conn->rollback();
      }
      error_log("save_invoice error: " . $e->getMessage());
      jsonOut(['ok' => false, 'error' => 'حدث خطأ أثناء حفظ الفاتورة.', 'detail' => $e->getMessage()]);
    }
  }

  // NEW: return next invoice number (approx)
  if ($action === 'next_invoice_number') {
    try {
      $res = $conn->query("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM invoices_out");
      if (!$res) throw new Exception($conn->error);
      $row = $res->fetch_assoc();
      jsonOut(['ok' => true, 'next' => (int)$row['next_id']]);
    } catch (Exception $e) {
      jsonOut(['ok' => false, 'error' => 'فشل جلب رقم الفاتورة التالي.', 'detail' => $e->getMessage()]);
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

<!-- ========================= HTML / UI ========================= -->
<style>
  .invoice-out .container-inv {
    padding: 18px;
    font-family: Inter, 'Noto Naskh Arabic', Tahoma, Arial;
  }

  .invoice-out .grid {
    display: grid;
    grid-template-columns: 280px 1fr 260px;
    gap: 16px;
    height: calc(100vh - 160px);
  }

  .invoice-out .panel {
    background: var(--surface);
    padding: 12px;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(2, 6, 23, 0.06);
    overflow: auto;
  }

  .invoice-out .prod-card {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 10px;
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 10px;
    background: var(--surface);
  }

  .invoice-out .badge {
    padding: 6px 10px;
    border-radius: 999px;
    font-weight: 700;
  }

  .invoice-out .badge.warn {
    background: rgba(250, 204, 21, 0.12);
    color: #7a4f00;
  }

  /* تحذير */
  .invoice-out .badge.green {
    background: rgba(16, 185, 129, 0.12);
    color: var(--teal);
  }

  /* فعال */
  .invoice-out .badge.red {
    background: rgba(239, 68, 68, 0.13);
    color: #b91c1c;
  }

  /* ملغي */
  .invoice-out .badge.gray {
    background: rgba(120, 120, 120, 0.13);
    color: #374151;
  }

  /* مستهلك */
  .invoice-out .badge.purple {
    background: rgba(168, 85, 247, 0.13);
    color: #7c3aed;
  }

  /* مرتجع */
  .invoice-out .btn {
    padding: 8px 10px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
  }

  .invoice-out .btn.primary {
    background: linear-gradient(90deg, var(--primary), var(--accent));
    color: #fff;
  }

  .invoice-out .btn.ghost {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text);
  }

  .invoice-out .table {
    width: 100%;
    border-collapse: separate;
  }

  .invoice-out .table th,
  .invoice-out .table td {
    padding: 8px;
    border-bottom: 1px solid var(--border);
    text-align: center;
  }

  .invoice-out .safe-hidden {
    display: none;
  }

  .invoice-out .modal-backdrop {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(2, 6, 23, 0.55);
    /* z-index: 1200; */
  }

  .invoice-out .mymodal {
    width: 100%;
    max-width: 1000px;
    background: var(--surface);
    padding: 16px;
    border-radius: 12px;
    max-height: 86vh;
    overflow: auto;
  }

  .invoice-out .toast-wrap {
    position: fixed;
    top: 50px;
    left: 30%;
    /* transform: translateX(-30%); */
    z-index: 2000;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .invoice-out .toast {
    display: flex !important;
    padding: 10px 14px;
    border-radius: 8px;
    color: #fff;
    box-shadow: 0 8px 20px rgba(2, 6, 23, 0.12);
  }

  .invoice-out .toast.success {
    background: linear-gradient(90deg, #10b981, #06b6d4);
  }

  .invoice-out .toast.error {
    background: linear-gradient(90deg, #ef4444, #f97316);
  }

  .invoice-out .cust-card {
    border: 1px solid var(--border);
    padding: 8px;
    border-radius: 8px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .invoice-out .small-muted {
    font-size: 13px;
    color: var(--muted);
  }

  @media (max-width:1100px) {
    .invoice-out .grid {
      grid-template-columns: 1fr;
      height: auto
    }
  }

  .invoice-out .invoice-table.custom-table-wrapper {
    max-height: 50vh;
  }

  .invoice-out #productSearchInput {
    background-color: var(--bg);
    color: var(--text);
  }

  /* reuse your classes but make them subtle and non-invasive */
  .invoice-out .line-error {
    position: relative;
    overflow: visible;
  }

  .invoice-out .tooltip-warning {
    position: absolute;
    left: 50%;
    transform: translateX(-55%);
    bottom: calc(100% + -15px);
    padding: 6px 10px;
    border-radius: 6px;
    box-shadow: 0 6px 18px rgba(2, 6, 23, 0.08);
    font-size: 12px;
    white-space: nowrap;
    z-index: 1200;
    opacity: 0.98;
    transition: transform .15s ease, opacity .15s ease;
    pointer-events: none;
  }

  /* subtle pointer */
  .invoice-out .tooltip-warning::after {
    content: '';
    position: absolute;
    bottom: -6px;
    left: 50%;
    transform: translateX(-50%);
    border-width: 6px 6px 0 6px;
    border-style: solid;
    border-color: inherit transparent transparent transparent;
    opacity: 0.95;
  }

  /* light / dark variants */
  .invoice-out .tooltip-warning.light {
    background: #fff7f7;
    color: #7f1d1d;
    border: 1px solid #fca5a5;
  }

  .invoice-out .tooltip-warning.dark {
    background: #2b0b0b;
    color: #fecaca;
    border: 1px solid #4c1d1d;
  }

  /* left red marker (small and subtle) */
  /* .invoice-out .line-error::before {
  content: '';
  position: absolute;
  left: 0;
  top: 8px;
  bottom: 8px;
  width: 4px;
  background: linear-gradient(180deg,#fb7185,#ef4444);
  border-radius: 2px 0 0 2px;
  opacity: 0.95;
} */

  /* disabled btn look */
  .btn.disabled,
  button[disabled] {
    opacity: 0.55;
    pointer-events: none;
    filter: grayscale(.1);
  }

  .invoice-out .fifo-table td,
  .invoice-out .fifo-table th {
    padding: 6px;
    border-bottom: 1px solid #eee
  }

  .invoice-out .confirm-actions {
    display: flex;
    gap: 8px
  }


  /* Result modal (replaces alert) */
  /* .invoice-out .resultModal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.35);
  } */

  .invoice-out .resultModal .card {
    background: #fff;
    padding: 18px;
    border-radius: 10px;
    min-width: 320px;
    max-width: 520px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25)
  }

  .invoice-out .resultModal .title {
    font-weight: 700;
    margin-bottom: 6px
  }

  .invoice-out .resultModal .msg {
    margin-bottom: 12px
  }

  .invoice-out .btn {
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    border: 1px solid #ddd;
    background: #f7f7f7
  }

  .invoice-out .btn.primary {
    background: #2563eb;
    color: #fff;
    border-color: transparent
  }

  .invoice-out .btn.success {
    background: #10b981;
    color: #fff;
    border-color: transparent
  }

  .invoice-out .btn.ghost {
    background: transparent
  }

  /* زر الطباعة في confirm modal */
  .invoice-out .confirm-actions {
    display: flex;
    gap: 8px
  }

  /* خاصين عند اختيار عميل جعله selected */
  .invoice-out .customer-card {
    transition: all .15s ease;
  }

  .invoice-out .customer-card.selected {
    border-color: var(--primary);
    background: rgba(59, 130, 246, 0.06);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.06);
  }

  .invoice-out .customer-card.dim {
    opacity: 0.45;
    pointer-events: none;
  }

  .invoice-out #resultModal_backdrop .mymodal {
    max-width: 300px !important;



  }

  .invoice-out #addCustomer_backdrop .mymodal input {
    background: var(--bg);
    color: var(--text);



  }

  .invoice-out .confirm_invoice th {
    text-align: start !important;
  }

  /* إضافة: احرص أن تضيف هذا إلى CSS العام */
  #resultMsg {
    white-space: pre-wrap;
  }

  /* يحترم الأسطر في رسالة النتيجة */

  .invoice-status-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    background: #eee;
    color: #222;
    vertical-align: middle;
  }

  /* استخدام كلاس ثابت (stateKey) */
  .invoice-status-badge.paid,
  .invoice-status-badge.delivered {
    background: #dff0d8;
    color: #2a6b2a;
  }

  .invoice-status-badge.pending {
    background: #fff3cd;
    color: #7a5b00;
  }

  .invoice-status-badge.draft {
    background: #e2e3e5;
    color: #3b3b3b;
  }

  /* زر معطل */
  .btn.disabled,
  .btn[disabled] {
    opacity: 0.55;
    cursor: not-allowed;
  }


  /* زر معطل */
  .btn.disabled,
  .btn[disabled] {
    opacity: 0.55;
    cursor: not-allowed;
  }

  .prod-card .price {
    border-radius: 5px;
    padding: 3px 10px;
    background-color: var(--accent);
    background-color: var(--primary-700);
    background-color: #347737;


    color: white;
    margin: 10px 0px;
    font-weight: bold;

  }

  .prod-card .code {
    font-weight: bold;
    color: #e4840faa;
    /* color: var(--primary); */
  }
</style>
<div class="invoice-out mt-2">

  <div class="container-fluid ">


    <div style="display:flex; gap:20px;align-items:center;margin-bottom:12px;">
      <div style="font-weight:900;font-size:20px">إنشاء فاتورة </div>
      <div id="top"> <strong id="currentInvoiceNumber">رقم الفاتورة: -</strong> </div>

    </div>

    <div class="grid" role="main">
      <!-- Products Column -->
      <div class="panel" aria-label="Products">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <div style="font-weight:800">المنتجات</div>
          <input id="productSearchInput" placeholder="بحث باسم أو كود أو id..." style="padding:6px;border-radius:8px;border:1px solid var(--border);min-width:160px">
        </div>
        <div id="productsList" style="padding-bottom:12px"></div>
      </div>

      <!-- Invoice Column -->
      <div class="panel" aria-label="Invoice">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <div>
            <label><input type="radio" name="invoice_state" value="pending" checked> مؤجل</label>
            <label style="margin-left:10px"><input type="radio" name="invoice_state" value="paid"> تم الدفع</label>
          </div>
          <strong>فاتورة جديدة</strong>
        </div>

        <div class="custom-table-wrapper invoice-table">
          <table class="tabl custom-table" id="invoiceTable" aria-label="Invoice items">
            <thead class="center">
              <tr>
                <th>المنتج</th>
                <th>كمية</th>
                <th>سعر بيع</th>
                <th>تفاصيل FIFO</th>
                <th>الإجمالي</th>
                <th>حذف</th>
              </tr>
            </thead>
            <tbody id="invoiceTbody"></tbody>
          </table>
        </div>

        <div style="margin-top:10px;display:flex;gap:8px;align-items:center">
          <textarea id="invoiceNotes" placeholder="ملاحظات (لن تُطبع)" style="flex:1;padding:8px;border-radius:8px;border:1px solid var(--border)"></textarea>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
          <div><strong>إجمالي الكمية:</strong> <span id="sumQty">0</span></div>
          <div><strong>إجمالي البيع:</strong> <span id="sumSell">0.00</span> ج</div>
          <div style="display:flex;gap:8px">
            <button id="clearBtn" class="btn ghost">تفريغ</button>
            <button id="previewBtn" class="btn ghost">معاينة</button>
            <button id="confirmBtn" class="btn primary">تأكيد الفاتورة</button>
          </div>
        </div>
      </div>

      <!-- Customers Column -->
      <div class="panel" aria-label="Customers">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <strong>العملاء</strong>
          <div style="display:flex;gap:6px">
            <button id="openAddCustomerBtn" class="btn ghost" type="button">إضافة</button>
          </div>
        </div>

        <div style="margin-bottom:8px;display:flex;gap:6px;align-items:center">
          <input type="text" id="customerSearchInput" placeholder="ابحث باسم أو موبايل..." style="padding:6px;border:1px solid var(--border);border-radius:6px;width:100%">
        </div>

        <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
          <button id="cashCustomerBtn" class="btn primary" type="button">نقدي (ثابت)</button>
          <div id="selectedCustomerBox" class="small-muted" style="padding:8px;border:1px solid var(--border);border-radius:8px;">
            <div>العميل الحالي: <strong id="selectedCustomerName">لم يتم الاختيار</strong></div>
            <div id="selectedCustomerDetails" class="small-muted"></div>
            <div style="margin-top:6px"><button id="btnUnselectCustomer" type="button" class="btn ghost">إلغاء اختيار العميل</button></div>

          </div>
        </div>

        <div id="customersList" style="margin-top:12px"></div>
      </div>
    </div>
  </div>

  <!-- Batches list modal (renamed not to conflict) -->
  <div id="batchesModal_backdrop" class="modal-backdrop">
    <div class="mymodal">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div><strong id="batchesTitle">دفعات</strong>
          <div class="small" id="batchesInfo"></div>
        </div>
        <div><button id="closeBatchesBtn" class="btn ghost">إغلاق</button></div>
      </div>
      <div id="batchesTable" class=" custom-table-wrapper" style="margin-top:10px"></div>
    </div>
  </div>

  <!-- Batch detail modal -->
  <div id="batchDetailModal_backdrop" class="modal-backdrop">
    <div class="mymodal">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div><strong id="batchTitle">تفاصيل الدفعة</strong></div>
        <div><button id="closeBatchDetailBtn" class="btn ghost">إغلاق</button></div>
      </div>
      <div id="batchDetailBody" class="custom-table-wrapper" style="margin-top:10px"></div>
    </div>
  </div>

  <!-- <div id="batchDetailModal_backdrop" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);z-index:110">
<div style="background:#fff;padding:12px;border-radius:8px;max-width:900px;width:90%">
<h4 id="batchTitle">تفاصيل FIFO</h4>
<div id="batchDetailBody"></div>
<div style="margin-top:10px;text-align:right"><button onclick="onId('batchDetailModal_backdrop',el=>el.style.display='none')" class="btn">إغلاق</button></div>
</div>
</div> -->

  <!-- Add Customer modal (avoid bootstrap name) -->
  <div id="addCustomer_backdrop" class="modal-backdrop">
    <div class="mymodal">
      <h3>إضافة عميل جديد</h3>
      <div id="addCustMsg"></div>
      <div style="display:grid;gap:8px;margin-top:8px">
        <input id="new_name" placeholder="الاسم" class="note-box" style="padding:8px;border:1px solid var(--border);border-radius:8px">
        <input id="new_mobile" placeholder="رقم الموبايل (11 رقم)" class="note-box" style="padding:8px;border:1px solid var(--border);border-radius:8px">
        <input id="new_city" placeholder="المدينة" class="note-box" style="padding:8px;border:1px solid var(--border);border-radius:8px">
        <input id="new_address" placeholder="العنوان" class="note-box" style="padding:8px;border:1px solid var(--border);border-radius:8px">
        <textarea id="new_notes" placeholder="ملاحظات عن العميل (اختياري)" class="note-box" rows="3" style="padding:8px;border:1px solid var(--border);border-radius:8px"></textarea>
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button id="closeAddCust" type="button" class="btn ghost">إلغاء</button>
          <button id="submitAddCust" type="button" class="btn primary">حفظ وإختيار</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirm modal -->
  <!-- <div id="confirmModal_backdrop" class="modal-backdrop">
  <div class="mymodal">
    <h3>تأكيد إتمام الفاتورة</h3>
    <div id="confirmClientPreview"></div>
    <div id="confirmItemsPreview" style="margin-top:8px"></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
      <div><button id="confirmCancel" type="button" class="btn ghost">إلغاء</button><button id="confirmSend" type="button" class="btn primary" style="margin-left:8px">تأكيد وإرسال</button></div>
      <div><strong>الإجمالي:</strong> <span id="confirm_total_before">0.00</span></div>
    </div>
  </div>
</div> -->

  <div id="confirmModal_backdrop" class="modal-backdrop">
    <div class="mymodal">
      <h3>تأكيد إنشاء الفاتورة</h3>
      <!-- <div style="margin-top:8px">
        <label style="margin-left:8px"><input type="radio" name="invoice_state" value="pending" checke> مؤجل</label>
        <label><input type="radio" name="invoice_state" value="paid"> تم الدفع</label>
      </div> -->
      <div id="confirmClientPreview"></div>

      <div id="confirmItemsPreview" style="max-height:320px;overflow:auto;margin-bottom:8px"></div>
      <div><strong>الإجمالي:</strong> <span id="confirm_total_before">0.00</span></div>

      <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end">
        <button id="confirmPrintBtn" class="btn primary">طباعة (بنود + إجمالي + الحالة)</button>
        <button id="confirmSend" class="btn success">إرسال وإنشاء</button>
        <button id="confirmCancel" class="btn ghost">إلغاء</button>
      </div>
    </div>
  </div>


  <!-- result modal replaces alerts -->
  <div id="resultModal_backdrop" class="resultModal modal-backdrop">
    <div class="mymodal">
      <div class="title" id="resultTitle">تم إنشاء الفاتورة</div>
      <div class="msg" id="resultMsg">تمت العملية بنجاح.</div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button id="goToInvoiceBtn" class="btn primary">الذهاب إلى الفاتورة</button>
        <button id="createNewInvoiceBtn" class="btn success">إنشاء فاتورة جديدة</button>
      </div>
    </div>
  </div>
  <!-- Toasts -->
  <div class="toast-wrap" id="toastWrap" aria-live="polite" aria-atomic="true"></div>
</div>


<script>
  document.addEventListener('DOMContentLoaded', function() {
    const CREATED_BY_NAME = "<?php echo $created_by_name_js; ?>"; // قد يكون '' لو لم يُعثر

    // ---------- helpers ----------
    const $ = id => document.getElementById(id);

    function onId(id, fn) {
      const el = document.getElementById(id);
      if (el) fn(el);
      return el;
    }

    function getCsrfToken() {
      const m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.getAttribute('content') : '';
    }

    function showToast(msg, type = 'success', timeout = 2000) {
      const wrap = $('toastWrap');
      if (!wrap) return console.warn('no toastWrap');
      const el = document.createElement('div');
      el.className = 'toast ' + (type === 'error' ? 'error' : 'success');
      el.textContent = msg;
      wrap.appendChild(el);
      setTimeout(() => {
        el.style.opacity = 0;
        setTimeout(() => el.remove(), 500);
      }, timeout);
    }

    function esc(s) {
      return (s == null) ? '' : String(s).replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      })[m]);
    }

    function fmt(n) {
      return Number(n || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });
    }

    function debounce(fn, t = 250) {
      let to;
      return (...a) => {
        clearTimeout(to);
        to = setTimeout(() => fn.apply(this, a), t);
      }
    }

    async function fetchJson(url, opts) {
      const res = await fetch(url, opts);
      const txt = await res.text();
      try {
        return JSON.parse(txt);
      } catch (e) {
        console.error('Invalid JSON response:', txt);
        throw new Error('Invalid JSON from server');
      }
    }

    // ---------- state ----------
    let products = [],
      customers = [],
      invoiceItems = [];
    let selectedCustomer = <?php echo $selected_customer_js; ?> || null;



    // خارطة لحالة ثابتة -> نص عربي
    function humanizeInvoiceState(stateKey) {
      if (!stateKey) return '';
      switch (String(stateKey)) {
        case 'paid':
          return 'مدفوعة';
        case 'delivered':
          return 'تم التسليم';
        case 'pending':
          return 'قيد الانتظار';
        case 'draft':
          return 'مسودة';
        default:
          return String(stateKey); // fallback
      }
    }


    // يعين حالة تمكين/تعطيل زر إنشاء الفاتورة بطريقة نظيفة
    function setCreateBtnDisabled(flag, reasonText = '') {
      const createBtn = $('createNewInvoiceBtn');
      if (!createBtn) return;
      createBtn.disabled = !!flag;
      createBtn.setAttribute('aria-disabled', flag ? 'true' : 'false');
      if (flag) {
        createBtn.classList.add('disabled');
        createBtn.title = reasonText || 'معطّل — أصلح الأخطاء قبل إنشاء فاتورة جديدة';
        // Reload the page when disabled (refresh)
      } else {
        createBtn.classList.remove('disabled');
        createBtn.title = '';
      }
    }

    // إضافة/تمييز حالة الفاتورة بجانب العنصر (نستخدم stateKey للأسماء)
    function markInvoiceStatus(invoiceId, stateKey) {
      if (!invoiceId || !stateKey) return;
      const human = humanizeInvoiceState(stateKey);

      const el = document.querySelector(`[data-invoice-id="${invoiceId}"]`) ||
        document.getElementById('invoice-row-' + invoiceId) ||
        document.querySelector(`a[href*="view.php?id=${invoiceId}"]`);

      if (!el) return;

      if (el.querySelector && el.querySelector('.invoice-status-badge')) return;

      const badge = document.createElement('span');
      badge.className = 'invoice-status-badge ' + String(stateKey); // <-- استخدم stateKey هنا
      badge.textContent = human;

      if (el.tagName === 'A') el.insertAdjacentElement('afterend', badge);
      else if (el.tagName === 'TR') {
        let firstTd = el.querySelector('td');
        if (!firstTd) firstTd = el.appendChild(document.createElement('td'));
        firstTd.appendChild(badge);
      } else el.appendChild(badge);
    }

    // الدالة الرئيسية بعد التعديل
    function showResultModal(title, message, success = true, invoiceId = null, rawServerJson = null) {
      const modal = $('resultModal_backdrop');
      if (!modal) {
        alert(title + '\n\n' + message);
        return;
      }

      // ======= تحديد ما إذا يجب تعطيل زر الإنشاء =========
      let disableCreate = false;
      let disableReason = '';

      if (rawServerJson) {
        if (Array.isArray(rawServerJson.allocation_errors) && rawServerJson.allocation_errors.length > 0) {
          disableCreate = true;
          disableReason = 'أخطاء في التخصيص — أصلحها قبل إنشاء فاتورة جديدة';
          // اجعل رسالة المستخدم توضح الأخطاء في سطر واحد لكل خطأ
          const mapped = rawServerJson.allocation_errors.map(a => {
            const pid = a.product_id || a.id || 'unknown';
            return `${pid}: ${a.msg || a.error || 'مشكلة في التخصيص'}`;
          }).join('\n');
          // أضف mapped إلى message (ابقِ على الأسطر)
          message = mapped + (message ? '\n\n' + message : '');
        }

        if (rawServerJson.disable_create === true || rawServerJson.block_create === true ||
          rawServerJson.error_code === 'EXCEED_LIMIT' || rawServerJson.error === true) {
          disableCreate = true;
          disableReason = disableReason || 'الخادم منع إنشاء فاتورة جديدة (قيد التحقق)';
        }
      }

      setCreateBtnDisabled(disableCreate, disableReason);

      // ======= عرض العنوان والرسالة (مع المحافظة على أسطر) =======
      $('resultTitle').textContent = title || 'نتيجة العملية';

      // نحصل على stateKey من الخادم أو من الراديو إن وُجد
      const stateFromServer = rawServerJson && rawServerJson.invoice_state ? rawServerJson.invoice_state : null;
      const radioEl = document.querySelector('input[name="invoice_state"]:checked');
      const stateFromRadio = radioEl ? radioEl.value : null;
      const stateKey = stateFromServer || stateFromRadio || null;
      const humanState = humanizeInvoiceState(stateKey);

      let finalMsg = (message || '').trim();
      if (invoiceId) finalMsg = `رقم الفاتورة: ${invoiceId}` + (finalMsg ? ' — ' + finalMsg : '');
      if (humanState) finalMsg += `\nحالة الفاتورة: ${humanState}`;

      $('resultMsg').textContent = finalMsg;

      modal.style.display = 'flex';

      // أضف badge في الصفحة إن نجحت العملية وكان لدينا stateKey
      if (success && invoiceId && stateKey) {
        setTimeout(() => markInvoiceStatus(invoiceId, stateKey), 50);
      }

      // ======= زر الذهاب إلى الفاتورة (يتصرف حسب stateKey) =======
      const goBtn = $('goToInvoiceBtn');
      if (goBtn) {
        goBtn.onclick = () => {
          if (!invoiceId) {
            modal.style.display = 'none';
            return;
          }
          const st = stateFromServer || (document.querySelector('input[name="invoice_state"]:checked') ? document.querySelector('input[name="invoice_state"]:checked').value : null);
          if (st) {
            const base = location.pathname.replace(/\/invoices_out\/create_invoice\.php.*$/, '/admin');
            if (st === 'paid' || st === 'delivered') window.location.href = base + '/delivered_invoices.php';
            else window.location.href = base + '/pending_invoices.php';
          } else {
            window.location.href = '/invoices/view.php?id=' + encodeURIComponent(invoiceId);
          }
        };
      }

      // ======= زر إنشاء فاتورة جديدة =======
      const createBtn = $('createNewInvoiceBtn');
      if (createBtn) {
        createBtn.onclick = () => {
          if (createBtn.disabled) return;

          modal.style.display = 'none';
          invoiceItems = [];
          if (typeof renderInvoice === 'function') renderInvoice();
          selectedCustomer = null;
          if (typeof renderSelectedCustomer === 'function') renderSelectedCustomer();
          if (typeof loadProducts === 'function') loadProducts();
          if (typeof loadNextInvoiceNumber === 'function') loadNextInvoiceNumber();

          setTimeout(() => location.reload(), 100);

        };
      }
    }



    // ---------- updateTotalsAndValidation (keeps tooltip behavior) ----------
    function updateTotalsAndValidation() {
      let sumQ = 0,
        sumS = 0;
      invoiceItems.forEach((it, idx) => {
        sumQ += Number(it.qty || 0);
        sumS += Number(it.qty || 0) * Number(it.selling_price || 0);

        const tr = document.querySelector('tr[data-idx="' + idx + '"]');
        if (!tr) return;
        const lt = tr.querySelector('.line-total');
        if (lt) lt.textContent = fmt((it.qty || 0) * (it.selling_price || 0));

        const existingTip = tr.querySelector('.tooltip-warning');
        if (existingTip) existingTip.remove();
        tr.classList.remove('line-error');

        const remaining = (typeof it.remaining_active !== 'undefined' && it.remaining_active !== null) ? Number(it.remaining_active) : null;
        if (remaining !== null && Number(it.qty) > remaining + 0.00001) {
          tr.classList.add('line-error');
          const qtyInput = tr.querySelector('.qty');
          if (qtyInput) {
            const cell = qtyInput.parentElement;
            cell.style.position = 'relative';
            const tip = document.createElement('div');
            tip.className = 'tooltip-warning';
            const isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            tip.classList.add(isDark ? 'dark' : 'light');
            tip.textContent = `تحذير: الكمية المطلوبة (${fmt(it.qty)}) أكبر من المتبقي (${fmt(remaining)}).`;
            // subtle positioning — CSS should handle look
            cell.appendChild(tip);
          }
        }
      });

      onId('sumQty', el => el.textContent = sumQ);
      onId('sumSell', el => el.textContent = fmt(sumS));
    }

    // ---------- loadNextInvoiceNumber ----------
    async function loadNextInvoiceNumber() {
      try {
        const j = await fetchJson(location.pathname + '?action=next_invoice_number');
        if (j && j.ok) {
          const el = document.getElementById('currentInvoiceNumber') || document.getElementById('invoice_number') || null;
          if (el) el.textContent = 'رقم الفاتورة: #' + j.next;
        }
      } catch (e) {
        console.warn('failed to load next invoice number', e);
      }
    }
    loadNextInvoiceNumber();

    // ---------- products ----------
    async function loadProducts(q = '') {
      try {
        const json = await fetchJson(location.pathname + '?action=products' + (q ? '&q=' + encodeURIComponent(q) : ''), {
          credentials: 'same-origin'
        });
        if (!json.ok) {
          showToast(json.error || 'فشل جلب المنتجات', 'error');
          return;
        }
        products = json.products || [];
        renderProducts();
      } catch (e) {
        console.error(e);
        showToast('تعذر جلب المنتجات', 'error');
      }
    }

    function renderProducts() {
      const wrap = $('productsList');
      if (!wrap) return;
      wrap.innerHTML = '';
      products.forEach(p => {
        const rem = parseFloat(p.remaining_active || 0);
        const consumed = rem <= 0;
        const div = document.createElement('div');
        div.className = 'prod-card';
        // <div class="small-muted">رصيد دخل: ${fmt(p.current_stock)}</div>
        div.innerHTML = `<div>
          <div style="font-weight:800">${esc(p.name)}</div>
          <div class="small-muted price"> سعر البيع: ${fmt(p.last_sale_price||0)} جنيه</div>
          <div class="small-muted code " >كود • #${esc(p.product_code)} • ID:${p.id}</div>
          <div class="small-muted">متبقي (Active): ${fmt(rem)}</div>
          <div class="small-muted">آخر شراء:${fmt(p.last_purchase_price||0)} جنيه</div>

        </div>
        <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end">
          ${consumed ? '<div class="badge warn">مستهلك</div>' : `<button class="btn primary add-btn" data-id="${p.id}" data-name="${esc(p.name)}" data-sale="${p.last_sale_price||0}">أضف</button>`}
          <button class="btn ghost batches-btn" data-id="${p.id}">دفعات</button>
        </div>`;
        wrap.appendChild(div);
      });

      // attach handlers (these handle dynamic buttons)
      document.querySelectorAll('.add-btn').forEach(b => b.addEventListener('click', e => {
        const id = b.dataset.id;
        const name = b.dataset.name;
        const sale = parseFloat(b.dataset.sale || 0);
        addInvoiceItem({
          product_id: id,
          product_name: name,
          qty: 1,
          selling_price: sale
        });
      }));
      document.querySelectorAll('.batches-btn').forEach(b => b.addEventListener('click', e => openBatchesModal(parseInt(b.dataset.id))));
    }

    // ---------- addInvoiceItem ----------
    function addInvoiceItem(item) {
      const prod = products.find(p => String(p.id) === String(item.product_id));
      const remaining = prod ? parseFloat(prod.remaining_active || 0) : null;
      item.remaining_active = remaining;
      const idx = invoiceItems.findIndex(x => String(x.product_id) === String(item.product_id));
      if (idx >= 0) invoiceItems[idx].qty = Number(invoiceItems[idx].qty) + Number(item.qty);
      else invoiceItems.push({
        ...item
      });
      renderInvoice();
    }

    // ---------- renderInvoice ----------
    function renderInvoice() {
      const tbody = document.getElementById('invoiceTbody');
      if (!tbody) return;
      tbody.innerHTML = '';
      invoiceItems.forEach((it, i) => {
        const tr = document.createElement('tr');
        tr.dataset.idx = i;
        tr.innerHTML = `
<td style="text-align:right">${esc(it.product_name)}</td>
<td><input type="number" class="qty" data-idx="${i}" value="${it.qty}" step="0.0001" style="width:100px"></td>
<td><input type="number" class="price" data-idx="${i}" value="${Number(it.selling_price).toFixed(2)}" step="0.01" style="width:110px"></td>
<td><button class="btn ghost fifo-btn" data-idx="${i}">تفاصيل FIFO</button></td>
<td class="line-total">${fmt(it.qty * it.selling_price)}</td>
<td><button class="btn ghost remove-btn" data-idx="${i}">حذف</button></td>
`;
        tbody.appendChild(tr);
      });

      const debouncedQtyUpdate = debounce(function(e) {
        const idx = Number(e.target.dataset.idx);
        invoiceItems[idx].qty = parseFloat(e.target.value || 0);
        updateTotalsAndValidation();
      }, 200);

      const debouncedPriceUpdate = debounce(function(e) {
        const idx = Number(e.target.dataset.idx);
        invoiceItems[idx].selling_price = parseFloat(e.target.value || 0);
        updateTotalsAndValidation();
      }, 200);

      document.querySelectorAll('.qty').forEach(el => el.addEventListener('input', debouncedQtyUpdate));
      document.querySelectorAll('.price').forEach(el => el.addEventListener('input', debouncedPriceUpdate));

      document.querySelectorAll('.remove-btn').forEach(b => b.addEventListener('click', e => {
        const idx = Number(b.dataset.idx);
        invoiceItems.splice(idx, 1);
        renderInvoice();
      }));
      document.querySelectorAll('.fifo-btn').forEach(b => b.addEventListener('click', e => openFifoPreview(parseInt(b.dataset.idx))));

      updateTotalsAndValidation();
    }

    // ---------- confirm modal open ----------
    onId('confirmBtn', el => el.addEventListener('click', () => {
      if (!selectedCustomer) return showToast('الرجاء اختيار عميل', 'error');
      if (invoiceItems.length === 0) return showToast('لا توجد بنود لحفظ الفاتورة', 'error');

      onId('confirmClientPreview', el => el.innerHTML = `<div class="cust-card"><div><strong>👤 ${esc(selectedCustomer.name)}</strong><div class="small-muted">📞 ${esc(selectedCustomer.mobile)} • ${esc(selectedCustomer.city)}</div><div class="small-muted">📍 ${esc(selectedCustomer.address)}</div></div></div>`);

      let html = `<div class="custom-table-wrapper confirm_invoice" style="max-height:360px;overflow:auto"><table class="custom-table" style="width:100%"><thead  ><tr><th>المنتج</th><th>الكمية</th><th>سعر البيع</th><th>الإجمالي</th></tr></thead><tbody>`;
      let total = 0;
      invoiceItems.forEach(it => {
        const line = (it.qty || 0) * (it.selling_price || 0);
        total += line;
        html += `<tr><td>${esc(it.product_name)}</td><td>${fmt(it.qty)}</td><td>${fmt(it.selling_price)}</td><td>${fmt(line)}</td></tr>`;
      });
      html += `</tbody></table></div>`;
      onId('confirmItemsPreview', el => el.innerHTML = html);
      onId('confirm_total_before', el => el.textContent = fmt(total));
      // rename radios inside modal if exist to avoid conflict
      (function renameModalRadios() {
        const modal = $('confirmModal_backdrop');
        if (!modal) return;
        modal.querySelectorAll('input[type="radio"][name="invoice_state"]').forEach(r => r.name = 'confirm_invoice_state');
      })();
      onId('confirmModal_backdrop', el => el.style.display = 'flex');
    }));

    // ---------- confirmSend (wrapped) ----------
    // wrapper ensures raw server json passed to showResultModal for mapping allocation_errors
    (function attachConfirmSend() {
      const btn = $('confirmSend');
      if (!btn) return;
      btn.addEventListener('click', async (ev) => {
        ev.preventDefault();
        if (!Array.isArray(invoiceItems) || invoiceItems.length === 0) return showToast('لا توجد بنود في الفاتورة', 'error');
        if (!selectedCustomer) return showToast('الرجاء اختيار عميل', 'error');

        const payload = invoiceItems.map(it => ({
          product_id: it.product_id,
          qty: Number(it.qty),
          selling_price: Number(it.selling_price)
        }));
        const fd = new FormData();
        fd.append('action', 'save_invoice');
        fd.append('csrf_token', getCsrfToken());
        fd.append('customer_id', selectedCustomer ? selectedCustomer.id : '');
        // try modal radio name first to avoid conflict, fallback to page radio
        let status = document.querySelector('input[name="confirm_invoice_state"]:checked')?.value ||
          document.querySelector('input[name="invoice_state"]:checked')?.value ||
          'pending';
        fd.append('status', status);
        fd.append('notes', $('invoiceNotes') ? $('invoiceNotes').value : '');
        fd.append('items', JSON.stringify(payload));
        try {
          const res = await fetch(location.pathname + '?action=save_invoice', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
          });
          const txt = await res.text();
          let json;
          try {
            json = JSON.parse(txt);
          } catch (e) {
            throw new Error('Invalid JSON');
          }

          if (!json.ok) {
            // pass raw json to showResultModal so it can map allocation_errors to names
            $('confirmModal_backdrop') && ($('confirmModal_backdrop').style.display = 'none');

            return showResultModal('خطأ', json.error || (json.msg ? json.msg : 'فشل الحفظ'), false, null, json);
          }

          const invNum = json.invoice_number || json.invoice_id || json.invoice || null;
          showResultModal('تم الإنشاء', `تمت إنشاء الفاتورة ${invNum ? ('#' + invNum) : ''}`, true, invNum, json);

          // reset UI (do not keep customer)
          invoiceItems = [];
          renderInvoice();
          $('invoiceNotes') && ($('invoiceNotes').value = '');
          selectedCustomer = null;
          renderSelectedCustomer();
          loadProducts();
          $('confirmModal_backdrop') && ($('confirmModal_backdrop').style.display = 'none');
          loadNextInvoiceNumber();
        } catch (e) {
          console.error(e);
          showResultModal('خطأ', 'تعذر الاتصال أو استجابة الخادم', false, null, null);
        }
      });
    })();

    // ---------- print handler (unchanged) ----------
    // onId('confirmPrintBtn', btn => btn.addEventListener('click', () => {
    //   let status = document.querySelector('input[name="invoice_state"]:checked').value === 'paid' ? 'تم الدفع' : 'مؤجل';
    //   let html = `<html><head><meta charset="utf-8"><title>فاتورة للطباعة</title></head><body><h3>فاتورة — حالة: ${status}</h3><table style="width:100%;border-collapse:collapse">`;
    //   html += document.getElementById('confirmItemsPreview').innerHTML;
    //   html += `<div style="margin-top:10px"><strong>الإجمالي: </strong>${document.getElementById('confirm_total_before').textContent}</div>`;
    //   html += `</body></html>`;
    //   const w = window.open('', '_blank');
    //   w.document.write(html);
    //   w.document.close();
    //   w.focus();
    //   w.print();
    // }));
    // replace old confirmPrintBtn handler with this
    onId('confirmPrintBtn', btn => btn && btn.addEventListener('click', async () => {
      // --- helper to get created_by name ---
      // Option A: if server injected CREATED_BY_NAME (preferred)
      let adminName = (typeof CREATED_BY_NAME !== 'undefined' && CREATED_BY_NAME) ? CREATED_BY_NAME : '';

      // Option B fallback: if CREATED_BY_NAME empty but you have created_by id in JS (server can echo it)
      // you can set CREATED_BY_ID in PHP similarly: const CREATED_BY_ID = "<?php echo $created_by; ?>";
      const createdById = (typeof CREATED_BY_ID !== 'undefined') ? String(CREATED_BY_ID) : '';

      async function resolveAdminName() {
        if (adminName && adminName.trim()) return adminName;
        if (!createdById) return '—';
        // try AJAX endpoint ?action=get_user&id=...
        try {
          const j = await fetchJson(location.pathname + '?action=get_user&id=' + encodeURIComponent(createdById), {
            credentials: 'same-origin'
          });
          if (j && j.ok && j.user && j.user.username) return j.user.username;
        } catch (e) {
          /* ignore */ }
        return '—';
      }

      // --- build printable HTML content (simple, uses existing esc() and fmt()) ---
      const status = document.querySelector('input[name="invoice_state"]:checked')?.value === 'paid' ? 'تم الدفع' : 'مؤجل';
      const cust = selectedCustomer || {};
      const isCash = String(cust.id) === '8' || (cust.name && String(cust.name).includes('نقد'));
      const custName = esc(cust.name || '-');
      const custMobile = isCash ? '' : esc(cust.mobile || '-');

      // resolve admin name (may be async)
      adminName = await resolveAdminName();

      const now = new Date().toLocaleString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
      });

      let rows = '';
      let total = 0;
      (invoiceItems || []).forEach((it, i) => {
        const qty = Number(it.qty || 0);
        const price = Number(it.selling_price || 0);
        const line = qty * price;
        total += line;
        rows += `<tr>
      <td style="width:40px;text-align:center">${i+1}</td>
      <td style="text-align:right">${esc(it.product_name || '-')}</td>
      <td style="text-align:center">${fmt(qty)}</td>
      <td style="text-align:center">${fmt(price)}</td>
      <td style="text-align:center">${fmt(line)}</td>
    </tr>`;
      });
      if (!rows) rows = `<tr><td colspan="5" style="text-align:center;padding:10px">لا توجد بنود</td></tr>`;

      const invoiceNumberEl = document.getElementById('currentInvoiceNumber') || document.getElementById('invoice_number');
      const invoiceNumberText = invoiceNumberEl ? invoiceNumberEl.textContent.replace(/^\s*رقم الفاتورة:\s*/i, '').trim() : '';

      const printHtml = `
  <div id="__print_area" style="direction:rtl;font-family:Tahoma,Arial,sans-serif;color:#111;padding:18px;">
    <div style="max-width:900px;margin:0 auto;border:1px solid #eee;padding:14px;border-radius:6px;background:#fff">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div>
          <div style="font-weight:800;font-size:18px">فاتورة مبيعات ${ invoiceNumberText ? ('#' + esc(invoiceNumberText)) : '' }</div>
          <div style="color:#666;font-size:13px">التاريخ: ${esc(now)}</div>
        </div>
        <div style="text-align:left;font-size:13px;color:#333">
          <div>المسؤول: <strong>${esc(adminName)}</strong></div>
          <div>الحالة: <strong>${esc(status)}</strong></div>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-bottom:12px">
        <div style="flex:1;border:1px solid #f1f1f1;padding:8px;border-radius:6px">
          <div style="font-weight:700;margin-bottom:6px">بيانات العميل</div>
          <div>الاسم: <strong>${custName}</strong></div>
          ${custMobile ? `<div>الهاتف: <strong>${custMobile}</strong></div>` : ''}
        </div>
      </div>

      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr>
            <th style="border:1px solid #ddd;padding:8px;background:#fafafa">م</th>
            <th style="border:1px solid #ddd;padding:8px;background:#fafafa">المنتج</th>
            <th style="border:1px solid #ddd;padding:8px;background:#fafafa">الكمية</th>
            <th style="border:1px solid #ddd;padding:8px;background:#fafafa">سعر الوحدة</th>
            <th style="border:1px solid #ddd;padding:8px;background:#fafafa">الإجمالي</th>
          </tr>
        </thead>
        <tbody>
          ${rows}
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4" style="text-align:right;border:1px solid #ddd;padding:8px;font-weight:700">الإجمالي الكلي</td>
            <td style="border:1px solid #ddd;padding:8px;font-weight:700;text-align:center">${fmt(total)}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>`;

      // --- print in same page workflow ---
      // 1) create print wrapper (hidden by default in screen) and append to body
      let wrapper = document.getElementById('__print_wrapper');
      if (!wrapper) {
        wrapper = document.createElement('div');
        wrapper.id = '__print_wrapper';
        document.body.appendChild(wrapper);
      }
      wrapper.innerHTML = printHtml;

      // 2) add temporary print-only stylesheet to hide rest of page and show only wrapper
      let styleEl = document.getElementById('__print_style');
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = '__print_style';
        document.head.appendChild(styleEl);
      }
      styleEl.textContent = `
    @media screen {
      #__print_wrapper { display: none; }
    }
    @media print {
      body * { visibility: hidden !important; }
      #__print_wrapper, #__print_wrapper * { visibility: visible !important; }
      #__print_wrapper { position: absolute; left: 0; top: 0; width: 100%; }
    }
  `;

      // 3) trigger print (no new window) and cleanup after
      window.print();

      // optional cleanup: remove wrapper and style after a short delay (so print dialog finishes)
      setTimeout(() => {
        // keep wrapper in DOM but hide it (so user can print again without rebuild) — or fully remove:
        // wrapper.remove(); styleEl.remove();
        wrapper.style.display = 'none';
      }, 800);
    }));



    // ---------- FIFO preview ----------
    async function openFifoPreview(idx) {
      const it = invoiceItems[idx];
      if (!it) return;
      try {
        const json = await fetchJson(location.pathname + '?action=batches&product_id=' + encodeURIComponent(it.product_id));
        if (!json.ok) return showToast(json.error || 'خطأ في جلب الدفعات', 'error');
        const batches = (json.batches || []).slice().sort((a, b) => (a.received_at || a.created_at || '') > (b.received_at || b.created_at || '') ? 1 : -1);
        let need = Number(it.qty || 0);
        let html = `<h4>تفاصيل FIFO — ${esc(it.product_name)}</h4><table class="fifo-table" style="width:100%"><thead><tr><th>رقم الدفعة</th><th>التاريخ</th><th>المتبقي قبل السحب</th><th>المتبقي بعد السحب المطلوب</th><th>سعر الشراء</th><th>مأخوذ</th><th>تكلفة</th></tr></thead><tbody>`;
        let totalCost = 0;
        for (const b of batches) {
          if (b.status !== 'active' || (parseFloat(b.remaining || 0) <= 0)) continue;
          const avail = parseFloat(b.remaining || 0);
          const take = Math.min(avail, need);
          const after = (avail - take);
          const cost = take * parseFloat(b.unit_cost || 0);
          totalCost += cost;
          html += `<tr><td class="monos">${b.id}</td><td>${esc(b.received_at||b.created_at||'-')}</td><td>${fmt(avail)}</td><td>${fmt(after)}</td><td>${fmt(b.unit_cost)}</td><td>${fmt(take)}</td><td>${fmt(cost)}</td></tr>`;
          need -= take;
          if (need <= 0) break;
        }
        if (need > 0) html += `<tr><td colspan="7" style="color:#b91c1c">تحذير: الرصيد غير كافٍ. تبقى ${fmt(need)} وحدة لم تُغطَّى.</td></tr>`;
        html += `</tbody></table><div style="margin-top:8px"><strong>إجمالي تكلفة البند:</strong> ${fmt(totalCost)} ج</div>`;
        onId('batchDetailBody', el => el.innerHTML = html);
        onId('batchTitle', el => el.textContent = 'تفاصيل FIFO');
        onId('batchDetailModal_backdrop', el => el.style.display = 'flex');
      } catch (e) {
        console.error(e);
        showToast('تعذر جلب الدفعات', 'error');
      }
    }

    // ---------- openBatchesModal (unchanged logic) ----------
    async function openBatchesModal(productId) {
      try {
        await fetchJson(location.pathname + '?action=sync_consumed').catch(() => {});
        const json = await fetchJson(location.pathname + '?action=batches&product_id=' + productId);
        if (!json.ok) return showToast(json.error || 'خطأ في جلب الدفعات', 'error');
        const p = json.product || {};
        onId('batchesTitle', el => el.textContent = `دفعات — ${p.name || ''}`);
        onId('batchesInfo', el => el.textContent = `${p.product_code || ''}`);
        const rows = json.batches || [];
        if (!rows.length) {
          onId('batchesTable', el => el.innerHTML = '<div class="small-muted">لا توجد دفعات.</div>');
          onId('batchesModal_backdrop', m => m.style.display = 'flex');
          return;
        }
        let html = `<table class="custom-table" style="width:100%;border-collapse:collapse"><thead class="center"><tr><th>رقم الدفعة</th><th>التاريخ</th><th>كمية</th><th>المتبقي</th><th>سعر الشراء</th><th>سعر البيع</th><th>رقم الفاتورة</th><th>ملاحظات</th><th>الحالة</th><th>عرض</th></tr></thead><tbody>`;
        rows.forEach(b => {
          const st = b.status === 'active' ? '<span class="badge green">فعال</span>' : (b.status === 'consumed' ? '<span class="badge warn">مستهلك</span>' : (b.status === 'reverted' ? '<span class="badge purple">مرجع</span>' : '<span class="badge red">ملغى</span>'));
          html += `<tr><td class="monos">${b.id}</td><td class="small monos">${b.received_at||b.created_at||'-'}</td><td>${fmt(b.qty)}</td><td>${fmt(b.remaining)}</td><td>${fmt(b.unit_cost)}</td><td>${fmt(b.sale_price)}</td><td class="monos">${b.source_invoice_id||'-'}</td><td class="small">${esc(b.notes||'-')}</td><td>${st}</td><td><button class="btn ghost view-batch" data-id="${b.id}">عرض</button></td></tr>`;
        });
        html += `</tbody></table>`;
        onId('batchesTable', el => el.innerHTML = html);

        // delegated view-batch handlers (attach after render)
        document.querySelectorAll('.view-batch').forEach(btn => btn.addEventListener('click', () => {
          const id = btn.dataset.id;
          const row = rows.find(r => r.id == id);
          if (!row) return;
          const st = row.status === 'active' ? 'فعال' : (row.status === 'consumed' ? 'مستهلك' : (row.status === 'reverted' ? 'مرجع' : 'ملغى'));
          let html = `<table style="width:100%"><tbody>
            <tr><td>رقم الدفعة</td><td class="monos">${row.id}</td></tr>
            <tr><td>الكمية الأصلية</td><td>${fmt(row.qty)}</td></tr>
            <tr><td>المتبقي</td><td>${fmt(row.remaining)}</td></tr>
            <tr><td>سعر الشراء</td><td>${fmt(row.unit_cost)}</td></tr>
            <tr><td>سعر البيع</td><td>${fmt(row.sale_price)}</td></tr>
            <tr><td>تاريخ الاستلام</td><td>${esc(row.received_at||row.created_at||'-')}</td></tr>
            <tr><td>رقم الفاتورة المرتبطة</td><td>${row.source_invoice_id||'-'}</td></tr>
            <tr><td>ملاحظات</td><td>${esc(row.notes||'-')}</td></tr>
            <tr><td>حالة</td><td>${esc(st)}</td></tr>
            <tr><td>سبب الإلغاء</td><td>${row.status==='cancelled'?esc(row.cancel_reason||'-'):'-'}</td></tr>
            <tr><td>سبب الإرجاع</td><td>${row.status==='reverted'?esc(row.revert_reason||'-'):'-'}</td></tr>
          </tbody></table>`;
          onId('batchDetailBody', el => el.innerHTML = html);
          onId('batchTitle', el => el.textContent = 'تفاصيل الدفعة');
          onId('batchDetailModal_backdrop', m => m.style.display = 'flex');
        }));
        onId('batchesModal_backdrop', m => m.style.display = 'flex');
      } catch (e) {
        console.error(e);
        showToast('خطأ في فتح الدفعات', 'error');
      }
    }

    // ---------- customers ----------
    async function loadCustomers(q = '') {
      try {
        const json = await fetchJson(location.pathname + '?action=customers' + (q ? ('&q=' + encodeURIComponent(q)) : ''), {
          credentials: 'same-origin'
        });
        if (!json.ok) {
          console.warn(json.error);
          return;
        }
        customers = json.customers || [];
        const wrap = $('customersList');
        if (!wrap) return;
        wrap.innerHTML = '';
        customers.forEach(c => {
          const d = document.createElement('div');
          d.className = 'cust-card';
          d.innerHTML = `<div><strong>${esc(c.name)}</strong><div class="small-muted">${esc(c.mobile)} — ${esc(c.city||'')}</div></div><div><button class="btn ghost choose-cust" data-id="${c.id}">اختر</button></div>`;
          wrap.appendChild(d);
        });

        document.querySelectorAll('.choose-cust').forEach(btn => btn.addEventListener('click', async () => {
          const cid = btn.dataset.id;
          try {
            const fd = new FormData();
            fd.append('action', 'select_customer');
            fd.append('csrf_token', getCsrfToken());
            fd.append('customer_id', cid);
            const res = await fetch(location.pathname + '?action=select_customer', {
              method: 'POST',
              body: fd,
              credentials: 'same-origin'
            });
            const txt = await res.text();
            let json;
            try {
              json = JSON.parse(txt);
            } catch (e) {
              showToast('استجابة غير متوقعة', 'error');
              return;
            }
            if (!json.ok) {
              showToast(json.error || 'فشل اختيار العميل', 'error');
              return;
            }
            selectedCustomer = json.customer;
            renderSelectedCustomer();
            showToast('تم اختيار العميل', 'success');
          } catch (e) {
            console.error(e);
            showToast('خطأ في الاتصال', 'error');
          }
        }));
      } catch (e) {
        console.error(e);
      }
    }

    function renderSelectedCustomer() {
      if (!selectedCustomer) {
        onId('selectedCustomerName', el => el.textContent = 'لم يتم الاختيار');
        onId('selectedCustomerDetails', el => el.innerHTML = '');
        return;
      }
      onId('selectedCustomerName', el => el.textContent = selectedCustomer.name || '—');
      onId('selectedCustomerDetails', el => el.innerHTML = `📞 ${esc(selectedCustomer.mobile||'-')} <br> 🏙️ ${esc(selectedCustomer.city||'-')} <div class="small-muted">📍 ${esc(selectedCustomer.address||'-')}</div>`);
    }

    // ---------- Delegated click handlers (fix buttons not working) ----------
    document.addEventListener('click', async (ev) => {
      const t = ev.target;

      // open add-customer modal
      if (t.matches('#openAddCustomerBtn, .open-add-customer')) {
        ev.preventDefault();
        $('addCustomer_backdrop') && ($('addCustomer_backdrop').style.display = 'flex');
        return;
      }



      // close add-customer
      if (t.matches('#closeAddCust, .close-add-customer')) {
        ev.preventDefault();
        $('addCustomer_backdrop') && ($('addCustomer_backdrop').style.display = 'none');
        return;
      }

      onId('closeBatchesBtn', btn => btn.addEventListener('click', () => onId('batchesModal_backdrop', m => m.style.display = 'none')));
      onId('closeBatchDetailBtn', btn => btn.addEventListener('click', () => onId('batchDetailModal_backdrop', m => m.style.display = 'none')));
      onId('confirmCancel', btn => btn.addEventListener('click', () => onId('confirmModal_backdrop', m => m.style.display = 'none')));

      // submit add-customer (delegation fallback)
      if (t.matches('#submitAddCust, .submit-add-customer')) {
        ev.preventDefault();
        // call internal function if exists
        if (typeof window.submitAddCustomer === 'function') return window.submitAddCustomer();
        // else fallback to clicking actual button
        $('submitAddCust') && $('submitAddCust').click();
        return;
      }

      // select cash fixed
      // اختيار عميل نقدي مثبت (ID=8 مثلاً)
      onId('cashCustomerBtn', btn => btn.addEventListener('click', async () => {
        // لو العميل الحالي بالفعل هو النقدي، تجاهل
        if (selectedCustomer && (String(selectedCustomer.id) === '8' || (selectedCustomer.name || '').includes('نقد'))) {
          return;
        }

        try {
          const json = await fetchJson(location.pathname + '?action=customers&q=عميل نقدي');
          if (!json.ok) {
            showToast('خطأ في جلب العملاء', 'error');
            return;
          }

          const found = (json.customers || []).find(
            c => (String(c.id) === '8') || (c.name && (c.name.includes('نقد') || c.name === 'عميل نقدي'))
          ) || null;

          if (found) {
            const fd = new FormData();
            fd.append('action', 'select_customer');
            fd.append('csrf_token', getCsrfToken());
            fd.append('customer_id', found.id);

            const res = await fetch(location.pathname + '?action=select_customer', {
              method: 'POST',
              body: fd,
              credentials: 'same-origin'
            });
            const txt = await res.text();
            let sel;
            try {
              sel = JSON.parse(txt);
            } catch (e) {
              showToast('استجابة غير متوقعة', 'error');
              return;
            }

            if (!sel.ok) {
              showToast(sel.error || 'تعذر اختيار العميل', 'error');
              return;
            }

            selectedCustomer = sel.customer;
            renderSelectedCustomer();
            showToast('تم اختيار العميل النقدي', 'success');
          } else {
            showToast('لم يتم العثور على حساب نقدي', 'error');
          }
        } catch (e) {
          console.error(e);
          showToast('خطأ في الاتصال', 'error');
        }
      }));



      // unselect customer
      if (t.matches('#btnUnselectCustomer')) {
        ev.preventDefault();
        selectedCustomer = null;
        renderSelectedCustomer();
        showToast('تم إلغاء اختيار العميل', 'success');
        // optionally clear server session selection
        try {
          const fd = new FormData();
          fd.append('action', 'select_customer');
          fd.append('csrf_token', getCsrfToken());
          fd.append('customer_id', '');
          await fetch(location.pathname + '?action=select_customer', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
          });
        } catch (e) {}
        return;
      }

      // open batches (if dynamically rendered button)
      if (t.matches('.batches-btn, .open-batches, .show-batches')) {
        ev.preventDefault();
        const pid = t.dataset.id || t.closest('[data-id]')?.dataset?.id || t.closest('[data-product-id]')?.dataset?.productId;
        if (pid) openBatchesModal(parseInt(pid));
        return;
      }

      // fifo preview button (delegated)
      if (t.matches('.fifo-btn, .view-fifo, .preview-item')) {
        ev.preventDefault();
        const idx = t.dataset.idx || t.closest('[data-idx]')?.dataset?.idx;
        if (typeof openFifoPreview === 'function' && typeof idx !== 'undefined') openFifoPreview(Number(idx));
        return;
      }

      // close batches modal/backdrops by clicking backdrop
      if (t.matches('.modal-backdrop')) {
        const id = t.id;
        if (id) $(id).style.display = 'none';
      }
    });

    // ---------- submitAddCustomer helper (exposed for delegation) ----------
    window.submitAddCustomer = async function submitAddCustomer() {
      const name = $('new_name') ? $('new_name').value.trim() : '';
      if (!name) return showToast('الرجاء إدخال اسم العميل', 'error');
      const fd = new FormData();
      fd.append('action', 'add_customer');
      fd.append('csrf_token', getCsrfToken());
      fd.append('name', name);
      fd.append('mobile', $('new_mobile') ? $('new_mobile').value.trim() : '');
      fd.append('city', $('new_city') ? $('new_city').value.trim() : '');
      fd.append('address', $('new_address') ? $('new_address').value.trim() : '');
      fd.append('notes', $('new_notes') ? $('new_notes').value.trim() : '');
      try {
        const res = await fetch(location.pathname + '?action=add_customer', {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        });
        const txt = await res.text();
        const json = JSON.parse(txt);
        if (!json.ok) return showToast(json.error || 'فشل إضافة العميل', 'error');
        selectedCustomer = json.customer;
        renderSelectedCustomer();
        $('addCustomer_backdrop') && ($('addCustomer_backdrop').style.display = 'none');
        showToast('تم إضافة العميل واختياره', 'success');
        loadCustomers();
      } catch (e) {
        console.error(e);
        showToast('خطأ في الاتصال', 'error');
      }
    };

    // ---------- sync, search, clear, theme toggle ----------
    onId('syncBtn', btn => btn.addEventListener('click', async () => {
      try {
        const json = await fetchJson(location.pathname + '?action=sync_consumed');
        if (json.ok) showToast('تم مزامنة الدفعات', 'success');
        loadProducts();
      } catch (e) {
        showToast('خطأ في المزامنة', 'error');
      }
    }));

    onId('customerSearchInput', el => el.addEventListener('input', debounce(() => loadCustomers(el.value.trim()), 250)));
    onId('productSearchInput', el => el.addEventListener('input', debounce(() => loadProducts(el.value.trim()), 400)));

    onId('clearBtn', el => el.addEventListener('click', () => {
      if (!confirm('هل تريد تفريغ بنود الفاتورة؟')) return;
      invoiceItems = [];
      renderInvoice();
      selectedCustomer = null;
      renderSelectedCustomer();
    }));
    onId('previewBtn', el => el.addEventListener('click', () => {
      if (invoiceItems.length === 0) return showToast('لا توجد بنود للمعاينة', 'error');
      let html = `<h3>معاينة الفاتورة</h3><table   ><thead class="text-start"><tr><th>المنتج</th><th>الكمية</th><th>سعر البيع</th><th>الإجمالي</th></tr></thead><tbody>`;
      let total = 0;
      invoiceItems.forEach(it => {
        const line = (it.qty || 0) * (it.selling_price || 0);
        total += line;
        html += `<tr><td>${esc(it.product_name)}</td><td>${fmt(it.qty)}</td><td>${fmt(it.selling_price)}</td><td>${fmt(line)}</td></tr>`
      });
      html += `</tbody></table><div style="margin-top:8px"><strong>الإجمالي: ${fmt(total)}</strong></div>`;
      onId('batchDetailBody', el => el.innerHTML = html);
      onId('batchTitle', el => el.textContent = 'معاينة الفاتورة');
      onId('batchDetailModal_backdrop', el => el.style.display = 'flex');
    }));
    // theme toggle — fix to actually set the toggled value
    onId('toggleThemeBtn', btn => btn.addEventListener('click', () => {
      const el = document.documentElement;
      const cur = el.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      el.setAttribute('data-theme', cur);
    }));

    // initial load
    (async function init() {
      try {
        await fetchJson(location.pathname + '?action=sync_consumed').catch(() => {});
      } catch (e) {}
      loadProducts();
      loadCustomers();
      renderSelectedCustomer();
    })();

    // ---------- accessibility: close result modal on backdrop click ----------
    ['resultModal_backdrop', 'confirmModal_backdrop', 'batchDetailModal_backdrop', 'batchesModal_backdrop'].forEach(id => {
      const el = $(id);
      if (!el) return;
      el.addEventListener('click', (ev) => {
        if (ev.target === el) el.style.display = 'none';
      });
    });

  }); // DOMContentLoaded
</script>


<?php
require_once BASE_DIR . 'partials/footer.php';
?>