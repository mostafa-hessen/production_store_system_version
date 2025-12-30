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


// حدد الوضع بدايه
$mode = $_GET['mode'] ?? 'create';
$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$invoice = [];
$invoiceItems = [];

if ($mode === 'edit' && $invoiceId > 0) {
  // جلب بيانات الفاتورة
  // جلب الفاتورة (mysqli)
  $stmt = $conn->prepare("SELECT * FROM invoices_out WHERE id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $res = $stmt->get_result();
    $invoice = $res ? $res->fetch_assoc() : [];
    if (!$invoice) $invoice = [];
    $stmt->close();
  } else {
    $invoice = [];
  }

  // جلب بنود الفاتورة (mysqli)
  $stmt = $conn->prepare("
      SELECT ioi.*, p.name AS product_name
      FROM invoice_out_items ioi
      JOIN products p ON p.id = ioi.product_id
      WHERE ioi.invoice_out_id = ?
  ");
  if ($stmt) {
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
    $res = $stmt->get_result();
    $invoiceItems = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
  } else {
    $invoiceItems = [];
  }
}

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
 try {
  $conn->begin_transaction();

  // insert invoice header - تصحيح سلسلة الأنواع (12 params)
  $delivered = ($status === 'paid') ? 'yes' : 'no';
  $invoice_group = 'group1';

  $stmt = $conn->prepare("
    INSERT INTO invoices_out
      (customer_id, delivered, invoice_group, created_by, created_at, notes,
       total_before_discount, discount_type, discount_value, discount_amount, total_after_discount, total_cost, profit_amount)
    VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
  ");
  if (!$stmt) throw new Exception($conn->error);

  // types: i (customer_id), s (delivered), s (invoice_group), i (created_by), s (notes),
  // d (total_before), s (discount_type), d (discount_value), d (discount_amount),
  // d (total_after), d (total_cost), d (profit_before)
  $stmt->bind_param(
    'issisdsddddd',
    $customer_id,
    $delivered,
    $invoice_group,
    $created_by_i,
    $notes,
    $total_before,
    $discount_type,
    $discount_value,
    $discount_amount,
    $total_after,
    $total_cost,
    $profit_before
  );
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

  // prepare commonly used statements (adjusted to include unit_price & price_mode)
  $insertItemStmt = $conn->prepare("
    INSERT INTO invoice_out_items
      (invoice_out_id, product_id, quantity, total_price, cost_price_per_unit, selling_price, unit_price, price_mode, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");
  if (!$insertItemStmt) throw new Exception($conn->error);

  // allocations table (unchanged)
  $insertAllocStmt = $conn->prepare("
    INSERT INTO sale_item_allocations (sale_item_id, batch_id, qty, unit_cost, line_cost, created_by, created_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
  ");
  if (!$insertAllocStmt) throw new Exception($conn->error);

  $updateBatchStmt = $conn->prepare("
    UPDATE batches SET remaining = ?, status = ?, adjusted_at = NOW(), adjusted_by = ? WHERE id = ?
  ");
  if (!$updateBatchStmt) throw new Exception($conn->error);

  // select batches for FIFO (FOR UPDATE)
  $selectBatchesStmt = $conn->prepare("
    SELECT id, remaining, unit_cost FROM batches
    WHERE product_id = ? AND status = 'active' AND remaining > 0
    ORDER BY received_at ASC, created_at ASC, id ASC
    FOR UPDATE
  ");
  if (!$selectBatchesStmt) throw new Exception($conn->error);

  // statement to lookup product_prices (optional table). will fallback if fails/no rows.
  $selectPricesStmt = $conn->prepare("
    SELECT price_mode, min_quantity, unit_price, label, starts_at, ends_at
    FROM product_prices
    WHERE product_id = ?
    ORDER BY COALESCE(min_quantity, 0) DESC, id ASC
  ");
  // don't throw if not exists; we'll handle null later
  $selectPricesAvailable = $selectPricesStmt !== false;

  // get product default price (fallback)
  $selectProductPriceStmt = $conn->prepare("SELECT selling_price, name FROM products WHERE id = ?");
  if (!$selectProductPriceStmt) throw new Exception($conn->error);

  foreach ($items as $it) {
    $product_id = (int)($it['product_id'] ?? 0);
    $qty = (float)($it['qty'] ?? 0);
    // client may pass a suggested selling_price; we'll ignore it for trust — but use fallback if no product price config
    $client_selling_price = isset($it['selling_price']) ? (float)$it['selling_price'] : null;

    if ($product_id <= 0 || $qty <= 0) {
      $conn->rollback();
      jsonOut(['ok' => false, 'error' => "بند غير صالح (معرف/كمية)."]);
    }

    // get product name & default price
    $selectProductPriceStmt->bind_param('i', $product_id);
    $selectProductPriceStmt->execute();
    $pres = $selectProductPriceStmt->get_result();
    $prow = $pres ? $pres->fetch_assoc() : null;
    $default_selling_price = $prow ? (float)($prow['selling_price'] ?? 0.0) : 0.0;
    $product_name = $prow ? ($prow['name'] ?? null) : null;

    // --- determine unit_price and price_mode ---
    $unit_price = $default_selling_price;
    $price_mode = 'retail';
    $price_label = null;

    if ($selectPricesAvailable) {
      $selectPricesStmt->bind_param('i', $product_id);
      $selectPricesStmt->execute();
      $pres2 = $selectPricesStmt->get_result();
      if ($pres2 && $pres2->num_rows > 0) {
        // choose first row where min_quantity is null or <= $qty and date range (if present)
        $nowDate = date('Y-m-d');
        while ($rowp = $pres2->fetch_assoc()) {
          $min_q = isset($rowp['min_quantity']) ? (float)$rowp['min_quantity'] : null;
          $starts = $rowp['starts_at'];
          $ends = $rowp['ends_at'];
          $okQty = ($min_q === null) || ($qty >= $min_q);
          $okDate = true;
          if ($starts && $ends) {
            $okDate = ($nowDate >= $starts && $nowDate <= $ends);
          } elseif ($starts) {
            $okDate = ($nowDate >= $starts);
          } elseif ($ends) {
            $okDate = ($nowDate <= $ends);
          }
          if ($okQty && $okDate) {
            $unit_price = (float)$rowp['unit_price'];
            $price_mode = $rowp['price_mode'] ?? 'retail';
            $price_label = $rowp['label'] ?? null;
            break;
          }
        }
      }
    } else {
      // no product_prices table — optionally use client_selling_price if provided, otherwise default
      if ($client_selling_price !== null && $client_selling_price > 0) {
        $unit_price = $client_selling_price;
      }
    }

    // --- FIFO allocations (select batches FOR UPDATE) ---
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
    }
    if ($need > 0.00001) {
      $conn->rollback();
      jsonOut([
        'ok' => false,
        'error' => "الرصيد غير كافٍ للمنتج: " . ($product_name ?: "ID: {$product_id}")
      ]);
    }

    // calculate item total cost from allocations
    $itemTotalCost = 0.0;
    foreach ($allocations as $a) $itemTotalCost += $a['take'] * $a['unit_cost'];
    $cost_price_per_unit = ($qty > 0) ? round($itemTotalCost / $qty, 4) : 0.0;
    $lineTotalPrice = round($qty * $unit_price, 2); // use determined unit_price

    // insert invoice item (store selling_price as the displayed price and unit_price as the chosen price)
    // types: i invoice_id, i product_id, d qty, d total_price, d cost_price_per_unit, d selling_price, d unit_price, s price_mode
    $insertItemStmt->bind_param('iiddddds',
      $invoice_id,
      $product_id,
      $qty,
      $lineTotalPrice,
      $cost_price_per_unit,
      $unit_price,   // selling_price column: we store the used unit price for clarity (you may want to keep separate display price)
      $unit_price,   // unit_price (duplicate if you don't have separate column — but we've included unit_price column)
      $price_mode
    );
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

      $updateBatchStmt->bind_param('dsii', $newRem, $newStatus, $created_by_i, $batch_id_i);
      $updateBatchStmt->execute();
      if ($updateBatchStmt->errno) {
        $err = $updateBatchStmt->error;
        $updateBatchStmt->close();
        throw new Exception($err);
      }

      $lineCost = round($a['take'] * $a['unit_cost'], 4);

      // insert allocation: sale_item_id(i), batch_id(i), qty(d), unit_cost(d), line_cost(d), created_by(i)
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
    } // end allocations loop

    // AFTER inserting allocations, recompute accurate cost per unit (from sale_item_allocations) and update invoice_out_items
    $sumStmt = $conn->prepare("
      SELECT COALESCE(SUM(qty * unit_cost), 0) AS sum_cost, COALESCE(SUM(qty), 0) AS sum_qty
      FROM sale_item_allocations
      WHERE sale_item_id = ?
      FOR UPDATE
    ");
    if ($sumStmt === false) throw new Exception("prepare failed: " . $conn->error);
    $sumStmt->bind_param('i', $invoice_item_id);
    $sumStmt->execute();
    $sumRes = $sumStmt->get_result();
    $sumCost = 0.0;
    $sumQty = 0.0;
    if ($sumRes) {
      $row = $sumRes->fetch_assoc();
      $sumCost = (float)($row['sum_cost'] ?? 0.0);
      $sumQty  = (float)($row['sum_qty'] ?? 0.0);
    }
    $sumStmt->close();

    $new_unit_cost = ($sumQty > 1e-9) ? round($sumCost / $sumQty, 4) : 0.0;
    $updateCostStmt = $conn->prepare("UPDATE invoice_out_items SET cost_price_per_unit = ? WHERE id = ?");
    if ($updateCostStmt === false) throw new Exception("prepare failed: " . $conn->error);
    $updateCostStmt->bind_param('di', $new_unit_cost, $invoice_item_id);
    $updateCostStmt->execute();
    $updateCostStmt->close();

    // update totals
    $totalRevenue += $lineTotalPrice;
    $totalCOGS += $itemTotalCost;

  } // end foreach items

  // commit
  $conn->commit();

  if (isset($_SESSION['selected_customer'])) {
    unset($_SESSION['selected_customer']);
  }

  jsonOut([
    'ok' => true,
    'msg' => 'تم إنشاء الفاتورة بنجاح.',
    'invoice_id' => $invoice_id,
    'invoice_number' => $invoice_id,
    'total_revenue' => round($totalRevenue, 2),
    'total_cogs' => round($totalCOGS, 2)
  ]);

} catch (Exception $e) {
  @$conn->rollback();
  error_log("save_invoice error: " . $e->getMessage());
  jsonOut(['ok' => false, 'error' => 'حدث خطأ أثناء حفظ الفاتورة.', 'detail' => $e->getMessage()]);
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


    <style>
  
        .invoice-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            height: calc(100vh - 40px);
            max-width: 1400px;
            margin: 0 auto;
        }

        .invoice-main {
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: 100%;
            overflow: hidden;
        }

        .invoice-panel {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-1);
            padding: 25px;
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .invoice-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: 100%;
            overflow-y: auto;
        }

        .panel {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-1);
            padding: 25px;
        }

        .panel-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .panel-title i {
            color: var(--primary);
        }

        /* قسم العميل */
        .customer-section {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            background: var(--surface-2);
        }

        .customer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }

        .customer-info {
            flex: 1;
        }

        .customer-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .customer-details {
            font-size: 14px;
            color: var(--muted);
        }

        .change-customer {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            padding: 8px 15px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .change-customer:hover {
            background: var(--primary-600);
        }

        /* مسح الباركود */
        .barcode-section {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }

        .barcode-input {
            flex: 1;
            display: flex;
            gap: 10px;
        }

        .barcode-input input {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-600);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            background: var(--surface-2);
        }

        .btn-success {
            background: var(--teal);
            color: white;
        }

        .btn-success:hover {
            opacity: 0.9;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 13px;
        }

        /* إضافة المنتجات */
        .add-product-section {
            display: grid;
            grid-template-columns: 1fr 100px 120px auto;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            border: 1px dashed var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface-2);
        }

        .add-product-section input, .add-product-section select {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
        }

        .price-type-buttons {
            display: flex;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }

        .price-type-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            background: var(--surface);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
        }

        .price-type-buttons button.active {
            background: var(--primary);
            color: white;
        }

        /* جدول البنود */
        .invoice-table-container {
            overflow-y: auto;
            margin-bottom: 20px;
            flex: 1;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table th {
            text-align: right;
            padding: 15px;
            background: var(--surface-2);
            border-bottom: 2px solid var(--border);
            font-weight: 600;
            color: var(--text);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .invoice-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }

        .invoice-table tr:last-child td {
            border-bottom: none;
        }

        .invoice-table tr:hover {
            background: var(--surface-2);
        }

        .input-qty, .input-price {
            width: 100px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            text-align: center;
            font-size: 15px;
            background: var(--surface);
            color: var(--text);
        }

        .input-qty:focus, .input-price:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: var(--ring);
        }

        .price-type-toggle {
            display: flex;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            overflow: hidden;
            width: 120px;
        }

        .price-type-toggle label {
            flex: 1;
            padding: 8px;
            text-align: center;
            cursor: pointer;
            background: var(--surface);
            transition: all 0.2s ease;
            font-size: 12px;
        }

        .price-type-toggle input {
            display: none;
        }

        .price-type-toggle input:checked + label {
            background: var(--primary);
            color: white;
        }

        .remove-item {
            color: var(--rose);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .remove-item:hover {
            color: #d00000;
            transform: scale(1.1);
        }

        /* الخصم */
        .discount-section {
            margin-bottom: 20px;
        }

        .discount-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        .discount-inputs select, .discount-inputs input {
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
        }

        .quick-discounts {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .quick-discount {
            padding: 8px 15px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .quick-discount:hover {
            border-color: var(--primary);
        }

        .quick-discount.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* الإجمالي */
        .summary-section {
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-total {
            font-weight: 700;
            font-size: 18px;
            color: var(--primary);
            margin-top: 10px;
            padding-top: 15px;
        }

        /* الدفع */
        .payment-section {
            margin-bottom: 20px;
        }

        .payment-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .payment-option {
            flex: 1;
            text-align: center;
        }

        .payment-option input {
            display: none;
        }

        .payment-label {
            display: block;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
        }

        .payment-option input:checked + .payment-label {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .partial-payment {
            background: var(--surface-2);
            border-radius: var(--radius-sm);
            padding: 20px;
            margin-top: 20px;
        }

        .payment-amounts {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .payment-amount {
            background: var(--surface);
            border-radius: var(--radius-sm);
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .payment-amount .label {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .payment-amount .value {
            font-size: 18px;
            font-weight: 700;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }

        .payment-method {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .payment-method:hover {
            border-color: var(--primary);
        }

        .payment-method.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .payment-method i {
            font-size: 20px;
        }

        .payment-method div {
            font-size: 12px;
        }

        .payment-input {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .payment-input input {
            flex: 1;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
        }

        .transfer-details {
            margin-top: 15px;
            padding: 15px;
            background: var(--surface);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        .transfer-details textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
            resize: vertical;
            min-height: 80px;
        }

        .payments-list {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 15px;
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }

        .payment-item:last-child {
            border-bottom: none;
        }

        .payment-details {
            display: flex;
            flex-direction: column;
        }

        .payment-amount-display {
            font-weight: 700;
            color: var(--teal);
        }

        .payment-meta {
            font-size: 13px;
            color: var(--muted);
        }

        /* الإجراءات */
        .actions {
            display: flex;
            gap: 10px;
        }

        .actions .btn {
            flex: 1;
            justify-content: center;
        }

        /* النماذج */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .mymodal {
            background: var(--surface);
            border-radius: var(--radius);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 25px;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .msg {
            margin-bottom: 20px;
            color: var(--text-soft);
        }

        .search-box {
            position: relative;
            margin-bottom: 15px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 15px;
            transition: all 0.2s ease;
            background: var(--surface);
            color: var(--text);
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: var(--ring);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .products-list, .customers-list {
            max-height: 400px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .product-card, .customer-card {
            display: flex;
            flex-direction: column;
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
            cursor: pointer;
            background: var(--surface);
        }

        .product-card:hover, .customer-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-1);
        }

        .product-info h3, .customer-info h3 {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .product-meta, .customer-meta {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .product-prices {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .price-wholesale, .price-retail {
            font-size: 14px;
        }

        .price-wholesale {
            color: var(--teal);
        }

        .price-retail {
            color: var(--amber);
        }

        .product-price {
            font-weight: 700;
            color: var(--primary);
            margin-top: auto;
        }

        /* Toast Notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: var(--radius-sm);
            color: white;
            font-weight: 600;
            box-shadow: var(--shadow-2);
            z-index: 1100;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(150%);
            transition: transform 0.3s ease;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background: var(--teal);
        }

        .toast.error {
            background: var(--rose);
        }

        .toast.warning {
            background: var(--amber);
        }

        /* نماذج التأكيد */
        .confirm-modal-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .confirm-section {
            margin-bottom: 20px;
        }

        .confirm-section h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .confirm-items {
            max-height: 200px;
            overflow-y: auto;
        }

        .confirm-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }

        .confirm-item:last-child {
            border-bottom: none;
        }

        /* الطباعة */
        @media print {
            body * {
                visibility: hidden;
            }
            .print-section, .print-section * {
                visibility: visible;
            }
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 8cm;
                font-size: 12px;
                padding: 10px;
            }
        }

        /* التجاوب مع الشاشات الصغيرة */
        @media (max-width: 1200px) {
            .invoice-container {
                grid-template-columns: 1fr;
                height: auto;
                overflow-y: auto;
            }
            
            .invoice-main, .invoice-sidebar {
                height: auto;
                overflow: visible;
            }
            
            .invoice-table-container {
                overflow: visible;
            }
            
            .invoice-panel {
                min-height: 500px;
            }
        }

        @media (max-width: 768px) {
            .add-product-section {
                grid-template-columns: 1fr;
            }
            
            .payment-amounts {
                grid-template-columns: 1fr;
            }
            
            .discount-inputs {
                grid-template-columns: 1fr;
            }
            
            .products-list, .customers-list {
                grid-template-columns: 1fr;
            }
            
            .barcode-section {
                flex-direction: column;
            }
            
            .invoice-table {
                font-size: 14px;
            }
            
            .invoice-table th, .invoice-table td {
                padding: 10px 5px;
            }
            
            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .confirm-modal-content {
                grid-template-columns: 1fr;
            }
        }
        /* ألوان الـ badges والـ toast */
.toast.success {
    background: var(--teal);
}

.toast.error {
    background: var(--rose);
}

.toast.warning {
    background: var(--amber);
}

.toast.info {
    background: var(--primary);
}

/* تصميم الكروت */
.product-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 15px;
    background: var(--surface);
    transition: all var(--fast);
}

.product-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-1);
}

.product-card.out-of-stock {
    background: var(--surface-2);
    border-color: var(--rose);
}

/* أزرار الأسعار */
.price-type-buttons button.active {
    background: var(--primary);
    color: white;
}

.btn-success {
    background: var(--teal);
}

.btn-warning {
    background: var(--amber);
}

/* أقسام الربح */
.profit-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 15px;
    margin-bottom: 15px;
}

.profit-row.profit {
    color: var(--teal);
    font-weight: 600;
}

.profit-row.loss {
    color: var(--rose);
    font-weight: 600;
}
    </style>

    <div class="invoice-container">
        <!-- الجزء الأيسر (الفاتورة والمنتجات) -->
        <div class="invoice-main">
            <div class="invoice-panel">
                <div class="panel-title">
                    <i class="fas fa-receipt"></i>
                    الفاتورة
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
                
                <!-- إضافة منتج جديد -->
                <div class="add-product-section">
                    <select id="product-select">
                        <option value="">اختر منتج للإضافة</option>
                    </select>
                    <input type="number" id="product-qty" min="1" value="1" placeholder="الكمية">
                    <div class="price-type-buttons">
                        <button id="price-retail-btn" class="active">قطاعي</button>
                        <button id="price-wholesale-btn">جملة</button>
                    </div>
                    <input type="number" id="product-price" step="0.01" placeholder="السعر" readonly>
                    <button class="btn btn-primary" id="add-product-btn">
                        <i class="fas fa-plus"></i> إضافة
                    </button>
                </div>
                
                <!-- جدول البنود -->
                <div class="invoice-table-container">
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th width="30%">المنتج</th>
                                <th width="10%">الكمية</th>
                                <th width="15%">سعر الوحدة</th>
                                <th width="15%">نوع السعر</th>
                                <th width="15%">الإجمالي</th>
                                <th width="15%">خيارات</th>
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
                <div class="customer-section">
                    <div class="customer-avatar">?</div>
                    <div class="customer-info">
                        <div class="customer-name">لم يتم اختيار عميل</div>
                        <div class="customer-details">يرجى اختيار عميل</div>
                    </div>
                    <button class="change-customer" id="change-customer">اختيار</button>
                </div>
                
                <!-- الخصم -->
                <div class="discount-section">
                    <div class="panel-title">
                        <i class="fas fa-tag"></i>
                        الخصم
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
                    <div class="summary-row">
                        <span>الضريبة (15%):</span>
                        <span id="tax-amount">٠٫٠٠ ج.م</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span>الإجمالي النهائي:</span>
                        <span id="total-amount">٠٫٠٠ ج.م</span>
                    </div>
                </div>
                
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
                    
                    <!-- الدفع الجزئي -->
                    <div class="partial-payment" id="partial-payment-section" style="display: none;">
                        <div class="payment-amounts">
                            <div class="payment-amount">
                                <div class="label">الإجمالي</div>
                                <div class="value" id="payment-total">٠٫٠٠ ج.م</div>
                            </div>
                            <div class="payment-amount">
                                <div class="label">المدفوع</div>
                                <div class="value" id="payment-paid">٠٫٠٠ ج.م</div>
                            </div>
                            <div class="payment-amount">
                                <div class="label">المتبقي</div>
                                <div class="value" id="payment-remaining">٠٫٠٠ ج.م</div>
                            </div>
                        </div>
                        
                        <div class="payment-methods">
                            <div class="payment-method selected" data-method="cash">
                                <i class="fas fa-money-bill-wave"></i>
                                <div>نقدي</div>
                            </div>
                            <div class="payment-method" data-method="bank">
                                <i class="fas fa-university"></i>
                                <div>تحويل</div>
                            </div>
                            <div class="payment-method" data-method="card">
                                <i class="fas fa-credit-card"></i>
                                <div>بطاقة</div>
                            </div>
                            <div class="payment-method" data-method="other">
                                <i class="fas fa-ellipsis-h"></i>
                                <div>أخرى</div>
                            </div>
                        </div>
                        
                        <div class="transfer-details" id="transfer-details" style="display: none;">
                            <label>بيانات التحويل:</label>
                            <textarea id="transfer-info" placeholder="أدخل بيانات التحويل (رقم الحساب، البنك، إلخ)"></textarea>
                        </div>
                        
                        <div class="payment-input">
                            <input type="number" placeholder="المبلغ المدفوع" step="0.01" min="0" id="current-payment">
                            <button class="btn btn-primary" id="add-payment-btn">إضافة دفعة</button>
                        </div>
                        
                        <div class="payments-list" id="payments-list">
                            <!-- سيتم تعبئتها بالمدفوعات -->
                        </div>
                    </div>
                </div>
                
                <!-- الإجراءات -->
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
    <div class="modal-backdrop" id="confirm-modal">
        <div class="mymodal">
            <div class="title">تأكيد إنشاء الفاتورة</div>
            
            <div id="confirm-content">
                <!-- سيتم تعبئته بالبيانات -->
            </div>
            
            <div style="display:flex;gap:8px;justify-content:flex-end; margin-top: 20px;">
                <button class="btn btn-outline" id="cancel-confirm">إلغاء</button>
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
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toast-message">تمت العملية بنجاح</span>
    </div>

    <!-- <script>
        // ============================
        // بيانات التطبيق
        // ============================
        const AppData = {
            products: [
                { id: 1, name: "آيفون 14 برو", code: "PROD-001", price: 42999, wholesalePrice: 39999, stock: 15, barcode: "1234567890123" },
                { id: 2, name: "سامسونج جالكسي S23", code: "PROD-002", price: 35999, wholesalePrice: 32999, stock: 22, barcode: "1234567890124" },
                { id: 3, name: "ماك بوك برو M2", code: "PROD-003", price: 74999, wholesalePrice: 69999, stock: 8, barcode: "1234567890125" },
                { id: 4, name: "آيباد برو 12.9", code: "PROD-004", price: 41999, wholesalePrice: 38999, stock: 12, barcode: "1234567890126" },
                { id: 5, name: "ساعة أبل واتش", code: "PROD-005", price: 15999, wholesalePrice: 13999, stock: 25, barcode: "1234567890127" },
                { id: 6, name: "آيربودز برو", code: "PROD-006", price: 10999, wholesalePrice: 9999, stock: 30, barcode: "1234567890128" },
                { id: 7, name: "شاحن لاسلكي", code: "PROD-007", price: 1999, wholesalePrice: 1599, stock: 50, barcode: "1234567890129" },
                { id: 8, name: "حافظة سليكون", code: "PROD-008", price: 899, wholesalePrice: 699, stock: 100, barcode: "1234567890130" }
            ],
            
            customers: [
                { id: 1, name: "محمد أحمد", phone: "+20 100 123 4567", city: "القاهرة" },
                { id: 2, name: "سارة الخالد", phone: "+20 101 987 6543", city: "الإسكندرية" },
                { id: 3, name: "عبدالله السعد", phone: "+20 102 555 1234", city: "الجيزة" },
                { id: 4, name: "نورة القحطاني", phone: "+20 103 777 8888", city: "القاهرة" },
                { id: 5, name: "خالد الفهد", phone: "+20 104 444 3333", city: "الإسكندرية" }
            ]
        };

        // ============================
        // حالة التطبيق
        // ============================
        const AppState = {
            invoiceItems: [],
            currentCustomer: null,
            payments: [],
            discount: { type: "percent", value: 0 },
            currentPriceType: 'retail',
            currentPaymentMethod: 'cash'
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
            currentPayment: document.getElementById('current-payment'),
            transferInfo: document.getElementById('transfer-info'),
            
            // الأزرار
            addProductBtn: document.getElementById('add-product-btn'),
            scanBarcodeBtn: document.getElementById('scan-barcode'),
            changeCustomerBtn: document.getElementById('change-customer'),
            clearBtn: document.getElementById('clear-btn'),
            confirmBtn: document.getElementById('confirm-btn'),
            addPaymentBtn: document.getElementById('add-payment-btn'),
            priceRetailBtn: document.getElementById('price-retail-btn'),
            priceWholesaleBtn: document.getElementById('price-wholesale-btn'),
            cancelConfirm: document.getElementById('cancel-confirm'),
            finalConfirm: document.getElementById('final-confirm'),
            
            // العناصر المعروضة
            invoiceItems: document.getElementById('invoice-items'),
            subtotal: document.getElementById('subtotal'),
            discountAmount: document.getElementById('discount-amount'),
            taxAmount: document.getElementById('tax-amount'),
            totalAmount: document.getElementById('total-amount'),
            paymentTotal: document.getElementById('payment-total'),
            paymentPaid: document.getElementById('payment-paid'),
            paymentRemaining: document.getElementById('payment-remaining'),
            paymentsList: document.getElementById('payments-list'),
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
            productSearch: document.getElementById('product-search')
        };

        // ============================
        // دوال المساعدة
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
                    'other': 'أخرى'
                };
                return methods[method] || method;
            },

            // حساب الإجمالي
            calculateTotal() {
                const subtotal = AppState.invoiceItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                
                let discountAmount = 0;
                if (AppState.discount.type === 'percent') {
                    discountAmount = subtotal * (AppState.discount.value / 100);
                } else {
                    discountAmount = AppState.discount.value;
                }
                
                const afterDiscount = subtotal - discountAmount;
                const tax = afterDiscount * 0.15;
                return afterDiscount + tax;
            },

            // إظهار Toast Notification
            showToast(message, type) {
                DOM.toastMessage.textContent = message;
                DOM.toast.className = `toast ${type} show`;
                
                setTimeout(() => {
                    DOM.toast.classList.remove('show');
                }, 3000);
            }
        };

        // ============================
        // دوال إدارة البيانات
        // ============================
        const DataManager = {
            // تحميل قائمة المنتجات في الـ select
            loadProductSelect() {
                AppData.products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = `${product.name} - ${Helpers.formatCurrency(product.price)}`;
                    DOM.productSelect.appendChild(option);
                });
            },

            // تحميل المنتجات في النافذة
            loadProductsModal() {
                DOM.productsContainer.innerHTML = '';
                
                AppData.products.forEach(product => {
                    const card = document.createElement('div');
                    card.className = 'product-card';
                    card.dataset.id = product.id;
                    card.innerHTML = `
                        <div class="product-info">
                            <h3>${product.name}</h3>
                            <div class="product-prices">
                                <div class="price-wholesale">جملة: ${Helpers.formatCurrency(product.wholesalePrice)}</div>
                                <div class="price-retail">قطاعي: ${Helpers.formatCurrency(product.price)}</div>
                            </div>
                            <div class="product-meta">
                                <span>${product.code} • ID:${product.id}</span>
                                <span>متبقي: ${product.stock}</span>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm select-product" style="margin-top: 10px;">اختيار</button>
                    `;
                    DOM.productsContainer.appendChild(card);
                });
            },

            // تحميل العملاء في النافذة
            loadCustomersModal() {
                DOM.customersContainer.innerHTML = '';
                
                AppData.customers.forEach(customer => {
                    const card = document.createElement('div');
                    card.className = 'customer-card';
                    card.dataset.id = customer.id;
                    card.innerHTML = `
                        <div class="customer-info">
                            <h3>${customer.name}</h3>
                            <div class="customer-meta">
                                <span>${customer.phone}</span>
                                <span>${customer.city}</span>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-sm select-customer" style="margin-top: 10px;">اختيار</button>
                    `;
                    DOM.customersContainer.appendChild(card);
                });
            },

            // البحث عن المنتج باستخدام الباركود
            findProductByBarcode(barcode) {
                const product = AppData.products.find(p => p.barcode === barcode);
                if (product) {
                    DOM.productSelect.value = product.id;
                    UI.updatePriceField(product);
                    DOM.productQty.focus();
                    Helpers.showToast(`تم العثور على ${product.name}`, 'success');
                    return true;
                } else {
                    Helpers.showToast('لم يتم العثور على المنتج بهذا الباركود', 'error');
                    return false;
                }
            },

            // تصفية المنتجات
            filterProducts(query) {
                const products = document.querySelectorAll('.product-card');
                
                products.forEach(product => {
                    const name = product.querySelector('h3').textContent.toLowerCase();
                    if (name.includes(query.toLowerCase())) {
                        product.style.display = 'flex';
                    } else {
                        product.style.display = 'none';
                    }
                });
            },

            // تصفية العملاء
            filterCustomers(query) {
                const customers = document.querySelectorAll('.customer-card');
                customers.forEach(customer => {
                    const name = customer.querySelector('h3').textContent.toLowerCase();
                    if (name.includes(query.toLowerCase())) {
                        customer.style.display = 'flex';
                    } else {
                        customer.style.display = 'none';
                    }
                });
            }
        };

        // ============================
        // دوال واجهة المستخدم
        // ============================
        const UI = {
            // تحديث واجهة المستخدم بالكامل
            update() {
                this.updateInvoiceDisplay();
                this.updateSummary();
                this.updatePaymentSection();
                this.updateCustomerUI();
            },

            // تحديث واجهة العميل
            updateCustomerUI() {
                const customerSection = document.querySelector('.customer-section');
                const customerAvatar = customerSection.querySelector('.customer-avatar');
                const customerName = customerSection.querySelector('.customer-name');
                const customerDetails = customerSection.querySelector('.customer-details');
                
                if (AppState.currentCustomer) {
                    customerAvatar.textContent = AppState.currentCustomer.name.charAt(0);
                    customerName.textContent = AppState.currentCustomer.name;
                    customerDetails.textContent = `${AppState.currentCustomer.phone} - ${AppState.currentCustomer.city}`;
                } else {
                    customerAvatar.textContent = '?';
                    customerName.textContent = 'لم يتم اختيار عميل';
                    customerDetails.textContent = 'يرجى اختيار عميل';
                }
            },

            // تحديث حقل السعر بناءً على نوع السعر المختار
            updatePriceField(product = null) {
                if (!product) {
                    const productId = parseInt(DOM.productSelect.value);
                    if (productId) {
                        product = AppData.products.find(p => p.id === productId);
                    }
                }
                
                if (product) {
                    const price = AppState.currentPriceType === 'retail' ? product.price : product.wholesalePrice;
                    DOM.productPrice.value = price;
                }
            },

            // تحديث عرض الفاتورة
            updateInvoiceDisplay() {
                DOM.invoiceItems.innerHTML = '';
                
                AppState.invoiceItems.forEach((item, index) => {
                    const subtotal = item.price * item.quantity;
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div>${item.name}</div>
                        </td>
                        <td>
                            <input type="number" class="input-qty" value="${item.quantity}" min="1" data-index="${index}">
                        </td>
                        <td>
                            <input type="number" class="input-price" value="${item.price}" step="0.01" min="0" data-index="${index}">
                        </td>
                        <td>
                            <div class="price-type-toggle">
                                <input type="radio" id="price-retail-${index}" name="price-type-${index}" value="retail" ${item.priceType === 'retail' ? 'checked' : ''}>
                                <label for="price-retail-${index}">قطاعي</label>
                                <input type="radio" id="price-wholesale-${index}" name="price-type-${index}" value="wholesale" ${item.priceType === 'wholesale' ? 'checked' : ''}>
                                <label for="price-wholesale-${index}">جملة</label>
                            </div>
                        </td>
                        <td>${Helpers.formatCurrency(subtotal)}</td>
                        <td>
                            <button class="remove-item" data-index="${index}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    `;
                    DOM.invoiceItems.appendChild(row);
                });
                
                // إضافة معالجات الأحداث للحقول الجديدة
                this.setupTableEventListeners();
            },

            // إعداد معالجات الأحداث للجدول
            setupTableEventListeners() {
                document.querySelectorAll('.input-qty').forEach(input => {
                    input.addEventListener('change', function() {
                        const index = parseInt(this.dataset.index);
                        const item = AppState.invoiceItems[index];
                        if (item) {
                            item.quantity = parseInt(this.value) || 1;
                            UI.update();
                        }
                    });
                    
                    // التركيز على الحقل وتحديد النص عند النقر
                    input.addEventListener('click', function() {
                        this.select();
                    });
                });
                
                document.querySelectorAll('.input-price').forEach(input => {
                    input.addEventListener('change', function() {
                        const index = parseInt(this.dataset.index);
                        const item = AppState.invoiceItems[index];
                        if (item) {
                            item.price = parseFloat(this.value) || 0;
                            // تحديث نوع السعر بناءً على السعر الجديد
                            if (item.price === item.wholesalePrice) {
                                item.priceType = 'wholesale';
                            } else {
                                item.priceType = 'retail';
                            }
                            UI.update();
                        }
                    });
                    
                    // التركيز على الحقل وتحديد النص عند النقر
                    input.addEventListener('click', function() {
                        this.select();
                    });
                });
                
                document.querySelectorAll('.price-type-toggle input').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const index = parseInt(this.name.split('-')[2]);
                        const item = AppState.invoiceItems[index];
                        if (item) {
                            item.priceType = this.value;
                            // تحديث السعر بناءً على النوع المختار
                            if (this.value === 'wholesale') {
                                item.price = item.wholesalePrice;
                            } else {
                                item.price = item.retailPrice;
                            }
                            UI.update();
                        }
                    });
                });
                
                document.querySelectorAll('.remove-item').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.dataset.index);
                        const item = AppState.invoiceItems[index];
                        AppState.invoiceItems.splice(index, 1);
                        UI.update();
                        Helpers.showToast(`تم حذف ${item.name} من الفاتورة`, 'success');
                    });
                });
            },

            // تحديث الملخص
            updateSummary() {
                const subtotal = AppState.invoiceItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                
                let discountAmount = 0;
                if (AppState.discount.type === 'percent') {
                    discountAmount = subtotal * (AppState.discount.value / 100);
                } else {
                    discountAmount = AppState.discount.value;
                }
                
                const afterDiscount = subtotal - discountAmount;
                const tax = afterDiscount * 0.15;
                const total = afterDiscount + tax;
                
                DOM.subtotal.textContent = Helpers.formatCurrency(subtotal);
                DOM.discountAmount.textContent = Helpers.formatCurrency(discountAmount);
                DOM.taxAmount.textContent = Helpers.formatCurrency(tax);
                DOM.totalAmount.textContent = Helpers.formatCurrency(total);
            },

            // تحديث قسم الدفع
            updatePaymentSection() {
                const total = Helpers.calculateTotal();
                const paidAmount = AppState.payments.reduce((sum, payment) => sum + payment.amount, 0);
                const remainingAmount = total - paidAmount;
                
                DOM.paymentTotal.textContent = Helpers.formatCurrency(total);
                DOM.paymentPaid.textContent = Helpers.formatCurrency(paidAmount);
                DOM.paymentRemaining.textContent = Helpers.formatCurrency(remainingAmount);
                
                const paymentStatus = document.querySelector('input[name="payment"]:checked').value;
                
                if (paymentStatus === 'partial') {
                    DOM.partialPaymentSection.style.display = 'block';
                    this.renderPaymentsList();
                    
                    // إذا كان المتبقي صفر، تحويل إلى مدفوع بالكامل
                    if (remainingAmount <= 0) {
                        document.getElementById('payment-paid').checked = true;
                        DOM.partialPaymentSection.style.display = 'none';
                    }
                } else if (paymentStatus === 'paid') {
                    DOM.partialPaymentSection.style.display = 'none';
                    // إذا تم اختيار مدفوع بالكامل، إضافة المبلغ المتبقي كدفعة
                    if (remainingAmount > 0) {
                        AppState.payments.push({
                            id: Date.now(),
                            amount: remainingAmount,
                            method: AppState.currentPaymentMethod,
                            transferInfo: AppState.currentPaymentMethod === 'bank' ? DOM.transferInfo.value : '',
                            date: new Date().toLocaleString('ar-EG')
                        });
                        UI.update();
                    }
                } else {
                    DOM.partialPaymentSection.style.display = 'none';
                }
            },

            // عرض قائمة المدفوعات
            renderPaymentsList() {
                DOM.paymentsList.innerHTML = '';
                
                if (AppState.payments.length === 0) {
                    DOM.paymentsList.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--muted);">لا توجد مدفوعات مضافة</div>';
                    return;
                }
                
                AppState.payments.forEach(payment => {
                    const div = document.createElement('div');
                    div.className = 'payment-item';
                    div.innerHTML = `
                        <div class="payment-details">
                            <div class="payment-amount-display">${Helpers.formatCurrency(payment.amount)}</div>
                            <div class="payment-meta">${payment.date} - ${Helpers.getPaymentMethodText(payment.method)}</div>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm remove-payment" data-id="${payment.id}">حذف</button>
                    `;
                    DOM.paymentsList.appendChild(div);
                });
                
                // إضافة مستمعين لأزرار الحذف
                document.querySelectorAll('.remove-payment').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const paymentId = parseInt(this.dataset.id);
                        AppState.payments = AppState.payments.filter(p => p.id !== paymentId);
                        UI.update();
                        Helpers.showToast('تم حذف الدفعة', 'success');
                    });
                });
            },

            // إعادة تعيين نموذج المنتج
            resetProductForm() {
                DOM.productSelect.value = '';
                DOM.productQty.value = 1;
                DOM.productPrice.value = '';
                DOM.barcodeInput.value = '';
                DOM.productSelect.focus();
            },

            // إعداد التنقل بين الحقول
            setupFieldNavigation() {
                const fields = [
                    DOM.barcodeInput,
                    DOM.productSelect,
                    DOM.productQty,
                    DOM.productPrice,
                    DOM.addProductBtn
                ];
                
                fields.forEach((field, index) => {
                    field.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const nextField = fields[index + 1];
                            if (nextField) {
                                nextField.focus();
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
            showConfirmModal() {
                const paymentStatus = document.querySelector('input[name="payment"]:checked').value;
                const statusText = {
                    'pending': 'مؤجل',
                    'partial': 'مدفوع جزئياً', 
                    'paid': 'مدفوع بالكامل'
                }[paymentStatus];
                
                const paidAmount = AppState.payments.reduce((sum, p) => sum + p.amount, 0);
                const total = Helpers.calculateTotal();
                const remainingAmount = total - paidAmount;
                
                let html = `
                    <div class="confirm-modal-content">
                        <div>
                            <div class="confirm-section">
                                <h3>العميل</h3>
                                <div>${AppState.currentCustomer.name}</div>
                                <div>${AppState.currentCustomer.phone}</div>
                                <div>${AppState.currentCustomer.city}</div>
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
                
                const subtotal = AppState.invoiceItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                let discountAmount = 0;
                if (AppState.discount.type === 'percent') {
                    discountAmount = subtotal * (AppState.discount.value / 100);
                } else {
                    discountAmount = AppState.discount.value;
                }
                const afterDiscount = subtotal - discountAmount;
                const tax = afterDiscount * 0.15;
                const finalTotal = afterDiscount + tax;
                
                html += `
                                </div>
                            </div>
                            
                            <div class="confirm-section">
                                <h3>الإجماليات</h3>
                                <div class="confirm-item">
                                    <div>الإجمالي:</div>
                                    <div>${Helpers.formatCurrency(subtotal)}</div>
                                </div>
                                <div class="confirm-item">
                                    <div>الخصم:</div>
                                    <div>${Helpers.formatCurrency(discountAmount)}</div>
                                </div>
                                <div class="confirm-item">
                                    <div>الضريبة:</div>
                                    <div>${Helpers.formatCurrency(tax)}</div>
                                </div>
                                <div class="confirm-item" style="font-weight: bold;">
                                    <div>الإجمالي النهائي:</div>
                                    <div>${Helpers.formatCurrency(finalTotal)}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                DOM.confirmContent.innerHTML = html;
                DOM.confirmModal.style.display = 'flex';
            }
        };

        // ============================
        // دوال إدارة الفواتير
        // ============================
        const InvoiceManager = {
            // إضافة منتج إلى الفاتورة
            addProductToInvoice() {
                const productId = parseInt(DOM.productSelect.value);
                const quantity = parseInt(DOM.productQty.value) || 1;
                const price = parseFloat(DOM.productPrice.value);
                
                if (productId && price > 0) {
                    this.addToInvoice(productId, quantity, price);
                    UI.resetProductForm();
                    Helpers.showToast('تم إضافة المنتج إلى الفاتورة', 'success');
                } else {
                    Helpers.showToast('الرجاء اختيار منتج وإدخال سعر صحيح', 'error');
                }
            },

            // إضافة منتج للفاتورة
            addToInvoice(productId, quantity, price) {
                const product = AppData.products.find(p => p.id === productId);
                if (!product) return;
                
                // تحديد نوع السعر بناءً على السعر المدخل
                let priceType = 'retail';
                if (price === product.wholesalePrice) {
                    priceType = 'wholesale';
                }
                
                const existingItem = AppState.invoiceItems.find(item => item.id === productId && item.price === price);
                
                if (existingItem) {
                    existingItem.quantity += quantity;
                } else {
                    AppState.invoiceItems.push({
                        id: productId,
                        name: product.name,
                        price: price,
                        quantity: quantity,
                        priceType: priceType,
                        wholesalePrice: product.wholesalePrice,
                        retailPrice: product.price
                    });
                }
                
                UI.update();
            },

            // إضافة دفعة
            addPayment() {
                const amount = parseFloat(DOM.currentPayment.value) || 0;
                
                if (amount <= 0) {
                    Helpers.showToast('الرجاء إدخال مبلغ صحيح', 'error');
                    return;
                }
                
                const total = Helpers.calculateTotal();
                const paidAmount = AppState.payments.reduce((sum, payment) => sum + payment.amount, 0);
                const remainingAmount = total - paidAmount;
                
                if (amount > remainingAmount) {
                    Helpers.showToast('المبلغ المدخل أكبر من المبلغ المتبقي', 'error');
                    return;
                }
                
                const payment = {
                    id: Date.now(),
                    amount: amount,
                    method: AppState.currentPaymentMethod,
                    transferInfo: AppState.currentPaymentMethod === 'bank' ? DOM.transferInfo.value : '',
                    date: new Date().toLocaleString('ar-EG')
                };
                
                AppState.payments.push(payment);
                UI.update();
                
                // مسح الحقول
                DOM.currentPayment.value = '';
                DOM.transferInfo.value = '';
                
                Helpers.showToast('تم إضافة الدفعة بنجاح', 'success');
            },

            // تأكيد الفاتورة
            confirmInvoice() {
                // التحقق من صحة البيانات
                if (AppState.invoiceItems.length === 0) {
                    Helpers.showToast('يرجى إضافة منتجات إلى الفاتورة أولاً', 'error');
                    return;
                }
                
                if (!AppState.currentCustomer) {
                    Helpers.showToast('يرجى اختيار عميل', 'error');
                    return;
                }
                
                UI.showConfirmModal();
            },

            // معالجة الفاتورة
            processInvoice() {
                // محاكاة عملية حفظ الفاتورة
                Helpers.showToast('جاري إنشاء الفاتورة...', 'success');
                
                setTimeout(() => {
                    Helpers.showToast('تم إنشاء الفاتورة بنجاح!', 'success');
                    
                    // طباعة الفاتورة
                    this.printInvoice();
                    
                    // إعادة تعيين الفاتورة
                    this.resetInvoice();
                }, 1500);
            },

            // تفريغ الفاتورة
            clearInvoice() {
                if (confirm('هل أنت متأكد من تفريغ الفاتورة؟ سيتم حذف جميع البنود.')) {
                    this.resetInvoice();
                    Helpers.showToast('تم تفريغ الفاتورة', 'success');
                }
            },

            // إعادة تعيين الفاتورة
            resetInvoice() {
                AppState.invoiceItems = [];
                AppState.payments = [];
                AppState.discount = { type: "percent", value: 0 };
                DOM.discountType.value = 'percent';
                DOM.discountValue.value = '0';
                document.querySelectorAll('.quick-discount').forEach(d => d.classList.remove('active'));
                
                UI.update();
            },

            // طباعة الفاتورة
            printInvoice() {
                const printContent = document.createElement('div');
                printContent.className = 'print-section';
                
                let html = `
                    <div style="text-align: center; margin-bottom: 10px;">
                        <h2>فاتورة مبيعات</h2>
                        <div>${new Date().toLocaleDateString('ar-SA')}</div>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <div><strong>العميل:</strong> ${AppState.currentCustomer.name}</div>
                        <div><strong>الهاتف:</strong> ${AppState.currentCustomer.phone}</div>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                        <thead>
                            <tr>
                                <th style="border-bottom: 1px solid #000; padding: 5px; text-align: right;">المنتج</th>
                                <th style="border-bottom: 1px solid #000; padding: 5px; text-align: center;">الكمية</th>
                                <th style="border-bottom: 1px solid #000; padding: 5px; text-align: center;">السعر</th>
                                <th style="border-bottom: 1px solid #000; padding: 5px; text-align: center;">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                AppState.invoiceItems.forEach(item => {
                    html += `
                        <tr>
                            <td style="border-bottom: 1px dashed #ccc; padding: 5px;">${item.name}</td>
                            <td style="border-bottom: 1px dashed #ccc; padding: 5px; text-align: center;">${item.quantity}</td>
                            <td style="border-bottom: 1px dashed #ccc; padding: 5px; text-align: center;">${Helpers.formatCurrency(item.price)}</td>
                            <td style="border-bottom: 1px dashed #ccc; padding: 5px; text-align: center;">${Helpers.formatCurrency(item.price * item.quantity)}</td>
                        </tr>
                    `;
                });
                
                const subtotal = AppState.invoiceItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                let discountAmount = 0;
                if (AppState.discount.type === 'percent') {
                    discountAmount = subtotal * (AppState.discount.value / 100);
                } else {
                    discountAmount = AppState.discount.value;
                }
                const afterDiscount = subtotal - discountAmount;
                const tax = afterDiscount * 0.15;
                const finalTotal = afterDiscount + tax;
                
                html += `
                        </tbody>
                    </table>
                    
                    <div style="border-top: 1px solid #000; padding-top: 10px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>الإجمالي:</span>
                            <span>${Helpers.formatCurrency(subtotal)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>الخصم:</span>
                            <span>${Helpers.formatCurrency(discountAmount)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>الضريبة (15%):</span>
                            <span>${Helpers.formatCurrency(tax)}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-weight: bold;">
                            <span>الإجمالي النهائي:</span>
                            <span>${Helpers.formatCurrency(finalTotal)}</span>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <div>شكراً لزيارتكم</div>
                        <div>نتمنى لكم يوماً سعيداً</div>
                    </div>
                `;
                
                printContent.innerHTML = html;
                document.body.appendChild(printContent);
                
                window.print();
                
                document.body.removeChild(printContent);
            }
        };

        // ============================
        // إدارة الأحداث
        // ============================
        const EventManager = {
            // إعداد جميع معالجات الأحداث
            setup() {
                this.setupCustomerEvents();
                this.setupProductEvents();
                this.setupPaymentEvents();
                this.setupDiscountEvents();
                this.setupModalEvents();
                this.setupNavigationEvents();
            },

            // أحداث العملاء
            setupCustomerEvents() {
                // تغيير العميل
                DOM.changeCustomerBtn.addEventListener('click', () => {
                    DOM.customersModal.style.display = 'flex';
                });
                
                // اختيار عميل
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('select-customer')) {
                        const customerCard = e.target.closest('.customer-card');
                        const customerId = parseInt(customerCard.dataset.id);
                        this.selectCustomer(customerId);
                        DOM.customersModal.style.display = 'none';
                    }
                });
                
                // البحث في العملاء
                DOM.customerSearch.addEventListener('input', (e) => {
                    DataManager.filterCustomers(e.target.value);
                });
            },

            // أحداث المنتجات
            setupProductEvents() {
                // مسح الباركود
                DOM.scanBarcodeBtn.addEventListener('click', () => {
                    const barcode = DOM.barcodeInput.value.trim();
                    if (barcode) {
                        DataManager.findProductByBarcode(barcode);
                    } else {
                        DOM.productsModal.style.display = 'flex';
                    }
                });
                
                // البحث بالباركود عند الضغط على Enter
                DOM.barcodeInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        const barcode = e.target.value.trim();
                        if (barcode) {
                            DataManager.findProductByBarcode(barcode);
                        } else {
                            DOM.productsModal.style.display = 'flex';
                        }
                    }
                });
                
                // إضافة منتج للفاتورة
                DOM.addProductBtn.addEventListener('click', () => {
                    InvoiceManager.addProductToInvoice();
                });
                
                // إضافة منتج عند الضغط على Enter في حقل السعر
                DOM.productPrice.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        InvoiceManager.addProductToInvoice();
                    }
                });
                
                // اختيار منتج من النافذة
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('select-product')) {
                        const productCard = e.target.closest('.product-card');
                        const productId = parseInt(productCard.dataset.id);
                        const product = AppData.products.find(p => p.id === productId);
                        
                        DOM.productSelect.value = productId;
                        DOM.productsModal.style.display = 'none';
                        
                        // تعبئة السعر تلقائياً
                        UI.updatePriceField(product);
                        DOM.productQty.focus();
                    }
                });
                
                // البحث في المنتجات
                DOM.productSearch.addEventListener('input', (e) => {
                    DataManager.filterProducts(e.target.value);
                });
                
                // تغيير نوع السعر
                DOM.priceRetailBtn.addEventListener('click', () => {
                    DOM.priceRetailBtn.classList.add('active');
                    DOM.priceWholesaleBtn.classList.remove('active');
                    AppState.currentPriceType = 'retail';
                    UI.updatePriceField();
                });
                
                DOM.priceWholesaleBtn.addEventListener('click', () => {
                    DOM.priceWholesaleBtn.classList.add('active');
                    DOM.priceRetailBtn.classList.remove('active');
                    AppState.currentPriceType = 'wholesale';
                    UI.updatePriceField();
                });
                
                // تحديث السعر عند تغيير المنتج
                DOM.productSelect.addEventListener('change', () => {
                    const productId = parseInt(DOM.productSelect.value);
                    if (productId) {
                        const product = AppData.products.find(p => p.id === productId);
                        UI.updatePriceField(product);
                    }
                });
            },

            // أحداث الدفع
            setupPaymentEvents() {
                // تبديل حالة الدفع
                document.querySelectorAll('input[name="payment"]').forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        UI.updatePaymentSection();
                    });
                });
                
                // إضافة دفعة
                DOM.addPaymentBtn.addEventListener('click', () => {
                    InvoiceManager.addPayment();
                });
                
                // إضافة دفعة عند الضغط على Enter
                DOM.currentPayment.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        InvoiceManager.addPayment();
                    }
                });
                
                // طرق الدفع
                document.querySelectorAll('.payment-method').forEach(method => {
                    method.addEventListener('click', function() {
                        document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                        this.classList.add('selected');
                        AppState.currentPaymentMethod = this.dataset.method;
                        
                        // إظهار/إخفاء تفاصيل التحويل
                        if (AppState.currentPaymentMethod === 'bank') {
                            DOM.transferDetails.style.display = 'block';
                        } else {
                            DOM.transferDetails.style.display = 'none';
                        }
                    });
                });
            },

            // أحداث الخصم
            setupDiscountEvents() {
                // الخصم السريع
                document.querySelectorAll('.quick-discount').forEach(discount => {
                    discount.addEventListener('click', function() {
                        document.querySelectorAll('.quick-discount').forEach(d => d.classList.remove('active'));
                        this.classList.add('active');
                        
                        DOM.discountType.value = 'percent';
                        DOM.discountValue.value = this.dataset.value;
                        this.updateDiscount();
                    });
                });
                
                // تحديث الخصم
                DOM.discountType.addEventListener('change', this.updateDiscount);
                DOM.discountValue.addEventListener('input', this.updateDiscount);
            },

            // أحداث النماذج
            setupModalEvents() {
                // إغلاق النماذج
                document.querySelectorAll('.modal-backdrop').forEach(modal => {
                    modal.addEventListener('click', (e) => {
                        if (e.target === modal) {
                            modal.style.display = 'none';
                        }
                    });
                });
                
                DOM.cancelConfirm.addEventListener('click', () => {
                    DOM.confirmModal.style.display = 'none';
                });
                
                // التأكيد النهائي
                DOM.finalConfirm.addEventListener('click', () => {
                    InvoiceManager.processInvoice();
                    DOM.confirmModal.style.display = 'none';
                });
            },

            // أحداث التنقل
            setupNavigationEvents() {
                UI.setupFieldNavigation();
            },

            // تحديث الخصم
            updateDiscount() {
                AppState.discount.type = DOM.discountType.value;
                AppState.discount.value = parseFloat(DOM.discountValue.value) || 0;
                UI.update();
            },

            // اختيار عميل
            selectCustomer(customerId) {
                AppState.currentCustomer = AppData.customers.find(c => c.id === customerId);
                UI.updateCustomerUI();
                Helpers.showToast(`تم اختيار العميل ${AppState.currentCustomer.name}`, 'success');
            }
        };

        // ============================
        // تهيئة التطبيق
        // ============================
        const App = {
            // تهيئة التطبيق
            init() {
                DataManager.loadProductSelect();
                DataManager.loadCustomersModal();
                DataManager.loadProductsModal();
                EventManager.setup();
                
                // إضافة منتجات تجريبية
                InvoiceManager.addToInvoice(1, 1);
                InvoiceManager.addToInvoice(3, 1);
                
                UI.update();
            }
        };

        // بدء التطبيق عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', () => {
            App.init();
            
            // إعداد أحداث الأزرار العامة
            DOM.clearBtn.addEventListener('click', () => {
                InvoiceManager.clearInvoice();
            });
            
            DOM.confirmBtn.addEventListener('click', () => {
                InvoiceManager.confirmInvoice();
            });
        });
    </script> -->

    <script>
        // ============================
// بيانات التطبيق - سيتم تعبئتها ديناميكياً
// ============================
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
    payments: [],
    discount: { type: "percent", value: 0 },
    currentPriceType: 'retail',
    currentPaymentMethod: 'cash',
    csrfToken: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
};

// ============================
// دوال التواصل مع الخادم
// ============================
const ApiManager = {
    async request(endpoint, data = null) {
        const url = `?action=${endpoint}`;
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
            return { ok: false, error: 'خطأ في الاتصال' };
        }
    },

    // جلب المنتجات
    async loadProducts(search = '') {
        const result = await this.request('products', { q: search });
        if (result.ok) {
            AppData.products = result.products;
            return result.products;
        }
        return [];
    },

    // جلب العملاء
    async loadCustomers(search = '') {
        const result = await this.request('customers', { q: search });
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
    async selectCustomer(customerId) {
        const result = await this.request('select_customer', {
            customer_id: customerId,
            csrf_token: AppState.csrfToken
        });
        return result;
    },

    // حفظ الفاتورة
    async saveInvoice(invoiceData) {
        const result = await this.request('save_invoice', {
            ...invoiceData,
            csrf_token: AppState.csrfToken
        });
        return result;
    },

    // جلب رقم الفاتورة التالي
    async getNextInvoiceNumber() {
        const result = await this.request('next_invoice_number');
        return result.ok ? result.next : 1;
    }
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
            'other': 'أخرى'
        };
        return methods[method] || method;
    },

    // حساب الإجمالي
    calculateTotal() {
        const subtotal = AppState.invoiceItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        
        let discountAmount = 0;
        if (AppState.discount.type === 'percent') {
            discountAmount = subtotal * (AppState.discount.value / 100);
        } else {
            discountAmount = AppState.discount.value;
        }
        
        const afterDiscount = Math.max(0, subtotal - discountAmount);
        const tax = afterDiscount * 0.15;
        return afterDiscount + tax;
    },

    // حساب الربح
    calculateProfit() {
        const totalRevenue = AppState.invoiceItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const totalCost = AppState.invoiceItems.reduce((sum, item) => sum + (item.cost * item.quantity), 0);
        
        let discountAmount = 0;
        if (AppState.discount.type === 'percent') {
            discountAmount = totalRevenue * (AppState.discount.value / 100);
        } else {
            discountAmount = AppState.discount.value;
        }
        
        const revenueAfterDiscount = Math.max(0, totalRevenue - discountAmount);
        const profitBeforeTax = revenueAfterDiscount - totalCost;
        const tax = revenueAfterDiscount * 0.15;
        const netProfit = profitBeforeTax - tax;
        
        return {
            totalRevenue,
            totalCost,
            discountAmount,
            revenueAfterDiscount,
            profitBeforeTax,
            tax,
            netProfit
        };
    },

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
            const option = document.createElement('option');
            option.value = product.id;
            option.textContent = `${product.name} - ${Helpers.formatCurrency(product.selling_price)}`;
            option.dataset.product = JSON.stringify(product);
            DOM.productSelect.appendChild(option);
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
            const stockStatus = isOutOfStock ? 'مستهلك' : `متبقي: ${availableStock}`;
            const cardClass = isOutOfStock ? 'product-card out-of-stock' : 'product-card';
            
            const card = document.createElement('div');
            card.className = cardClass;
            card.dataset.id = product.id;
            card.innerHTML = `
                <div class="product-info">
                    <h3>${product.name}</h3>
                    <div class="product-prices">
                        <div class="price-wholesale">جملة: ${Helpers.formatCurrency(product.retail_price || product.selling_price)}</div>
                        <div class="price-retail">قطاعي: ${Helpers.formatCurrency(product.selling_price)}</div>
                    </div>
                    <div class="product-meta">
                        <span>${product.product_code} • ID:${product.id}</span>
                        <span class="stock-status ${isOutOfStock ? 'out-of-stock' : ''}">${stockStatus}</span>
                    </div>
                </div>
                <div class="product-actions">
                    <button class="btn btn-primary btn-sm select-price" data-type="retail" data-price="${product.selling_price}">قطاعي</button>
                    <button class="btn btn-success btn-sm select-price" data-type="wholesale" data-price="${product.retail_price || product.selling_price}">جملة</button>
                </div>
            `;
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
                <button class="btn btn-primary btn-sm select-customer" style="margin-top: 10px;">اختيار</button>
            `;
            DOM.customersContainer.appendChild(card);
        });
    },

    // البحث عن المنتج باستخدام الباركود
    async findProductByBarcode(barcode) {
        const products = await ApiManager.loadProducts(barcode);
        if (products.length > 0) {
            const product = products[0];
            DOM.productSelect.value = product.id;
            UI.updatePriceField(product);
            DOM.productQty.focus();
            Helpers.showToast(`تم العثور على ${product.name}`, 'success');
            return true;
        } else {
            Helpers.showToast('لم يتم العثور على المنتج بهذا الباركود', 'error');
            return false;
        }
    }
};

// ============================
// دوال واجهة المستخدم المحدثة
// ============================
const UI = {
    // تحديث واجهة المستخدم بالكامل
    update() {
        this.updateInvoiceDisplay();
        this.updateSummary();
        this.updatePaymentSection();
        this.updateCustomerUI();
        this.updateProfitDisplay();
    },

    // تحديث عرض الأرباح
    updateProfitDisplay() {
        const profit = Helpers.calculateProfit();
        const profitElement = document.getElementById('profit-display') || this.createProfitDisplay();
        
        profitElement.innerHTML = `
            <div class="profit-section">
                <div class="panel-title">
                    <i class="fas fa-chart-line"></i>
                    الأرباح
                </div>
                <div class="profit-row">
                    <span>إجمالي المبيعات:</span>
                    <span>${Helpers.formatCurrency(profit.totalRevenue)}</span>
                </div>
                <div class="profit-row">
                    <span>تكلفة البضاعة:</span>
                    <span>${Helpers.formatCurrency(profit.totalCost)}</span>
                </div>
                <div class="profit-row">
                    <span>الربح قبل الخصم:</span>
                    <span>${Helpers.formatCurrency(profit.totalRevenue - profit.totalCost)}</span>
                </div>
                <div class="profit-row">
                    <span>الخصم:</span>
                    <span>-${Helpers.formatCurrency(profit.discountAmount)}</span>
                </div>
                <div class="profit-row ${profit.netProfit < 0 ? 'loss' : 'profit'}">
                    <span>صافي الربح:</span>
                    <span>${Helpers.formatCurrency(profit.netProfit)}</span>
                </div>
            </div>
        `;
    },

    // إنشاء عرض الأرباح
    createProfitDisplay() {
        const profitSection = document.createElement('div');
        profitSection.id = 'profit-display';
        document.querySelector('.invoice-sidebar').insertBefore(profitSection, document.querySelector('.summary-section'));
        return profitSection;
    },

    // باقي الدوال تبقى كما هي مع التحديثات اللازمة...
    // [يتبع باقي الدوال مع التحديثات]
};

// ============================
// دوال إدارة الفواتير المحدثة
// ============================
const InvoiceManager = {
    // إضافة منتج إلى الفاتورة
    async addProductToInvoice() {
        const productId = parseInt(DOM.productSelect.value);
        const quantity = parseFloat(DOM.productQty.value) || 1;
        const price = parseFloat(DOM.productPrice.value);
        
        if (productId && price > 0) {
            const product = AppData.products.find(p => p.id === productId);
            if (product) {
                // التحقق من الكمية المتاحة
                const availableStock = product.remaining_active || 0;
                if (quantity > availableStock) {
                    Helpers.showToast(`الكمية المطلوبة (${quantity}) تتجاوز الكمية المتاحة (${availableStock})`, 'error');
                    return;
                }
                
                await this.addToInvoice(productId, quantity, price);
                UI.resetProductForm();
                Helpers.showToast('تم إضافة المنتج إلى الفاتورة', 'success');
            }
        } else {
            Helpers.showToast('الرجاء اختيار منتج وإدخال سعر صحيح', 'error');
        }
    },

    // إضافة منتج للفاتورة مع حساب التكلفة
    async addToInvoice(productId, quantity, price) {
        const product = AppData.products.find(p => p.id === productId);
        if (!product) return;
        
        // هنا يمكن إضافة منطق حساب التكلفة من الدفعات باستخدام FIFO
        const cost = product.last_purchase_price || product.cost_price || 0;
        
        const existingItem = AppState.invoiceItems.find(item => item.id === productId && item.price === price);
        
        if (existingItem) {
            // التحقق من الكمية الإجمالية
            const totalQuantity = existingItem.quantity + quantity;
            const availableStock = product.remaining_active || 0;
            if (totalQuantity > availableStock) {
                Helpers.showToast(`الكمية الإجمالية (${totalQuantity}) تتجاوز الكمية المتاحة (${availableStock})`, 'error');
                return;
            }
            existingItem.quantity += quantity;
        } else {
            AppState.invoiceItems.push({
                id: productId,
                name: product.name,
                price: price,
                quantity: quantity,
                cost: cost,
                priceType: AppState.currentPriceType,
                product_code: product.product_code
            });
        }
        
        UI.update();
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
    async processInvoice() {
        Helpers.showToast('جاري إنشاء الفاتورة...', 'success');
        
        const paymentStatus = document.querySelector('input[name="payment"]:checked').value;
        const profit = Helpers.calculateProfit();
        
        const invoiceData = {
            customer_id: AppState.currentCustomer.id,
            status: paymentStatus,
            items: AppState.invoiceItems,
            discount_type: AppState.discount.type,
            discount_value: AppState.discount.value,
            total_before: profit.totalRevenue,
            total_after: profit.revenueAfterDiscount,
            total_cost: profit.totalCost,
            profit_before: profit.netProfit
        };
        
        const result = await ApiManager.saveInvoice(invoiceData);
        
        if (result.ok) {
            Helpers.showToast('تم إنشاء الفاتورة بنجاح!', 'success');
            this.printInvoice(result.invoice_id);
            this.resetInvoice();
        } else {
            Helpers.showToast(result.error || 'فشل إنشاء الفاتورة', 'error');
        }
    }
};

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
    },

    // إعداد زر العميل النقدي
    setupQuickCustomer() {
        const quickCustomerBtn = document.createElement('button');
        quickCustomerBtn.className = 'btn btn-success';
        quickCustomerBtn.innerHTML = '<i class="fas fa-user"></i> عميل نقدي';
        quickCustomerBtn.style.marginLeft = '10px';
        
        quickCustomerBtn.addEventListener('click', async () => {
            const result = await ApiManager.selectCustomer(8); // ID 8 للعميل النقدي
            if (result.ok) {
                this.selectCustomer(8);
            } else {
                Helpers.showToast('لم يتم العثور على العميل النقدي', 'error');
            }
        });
        
        DOM.changeCustomerBtn.parentNode.insertBefore(quickCustomerBtn, DOM.changeCustomerBtn.nextSibling);
    },

    // أحداث المنتجات المحدثة
    setupProductEvents() {
        // [يتبع مع التحديثات المماثلة للأحداث الأخرى]
    }
};

// إضافة CSS إضافي
const additionalCSS = `
    .profit-section {
        margin-bottom: 20px;
        background: var(--surface);
        padding: 15px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
    }

    .profit-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
    }

    .profit-row:last-child {
        border-bottom: none;
        font-weight: bold;
        margin-top: 5px;
        padding-top: 10px;
    }

    .profit-row.profit {
        color: var(--teal);
    }

    .profit-row.loss {
        color: var(--rose);
    }

    .product-card.out-of-stock {
        opacity: 0.6;
        background: var(--surface-2);
    }

    .stock-status.out-of-stock {
        color: var(--rose);
        font-weight: bold;
    }

    .product-actions {
        display: flex;
        gap: 5px;
        margin-top: 10px;
    }

    .customer-section {
        position: relative;
    }

    .quick-customer-btn {
        margin-right: 10px;
    }
`;

// إضافة CSS إلى الصفحة
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalCSS;
document.head.appendChild(styleSheet);

// ============================
// تهيئة التطبيق
// ============================
const App = {
    async init() {
        await DataManager.loadProductSelect();
        await DataManager.loadCustomersModal();
        EventManager.setup();
        UI.update();
        
        // جلب رقم الفاتورة التالي
        const nextInvoice = await ApiManager.getNextInvoiceNumber();

    }
};

// بدء التطبيق عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
    </script>

<?php
require_once BASE_DIR . 'partials/footer.php';
?>