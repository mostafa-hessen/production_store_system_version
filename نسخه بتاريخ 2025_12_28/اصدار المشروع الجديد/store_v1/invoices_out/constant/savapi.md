     if ($action === 'save_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {

                $token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
                    jsonOut(['ok' => false, 'error' => 'رمز التحقق (CSRF) غير صالح. أعد تحميل الصفحة وحاول مجدداً.']);
                }

                $customer_id = (int)($_POST['customer_id'] ?? 0);
$work_order_id = (!isset($_POST['work_order_id']) || $_POST['work_order_id'] === '' || $_POST['work_order_id'] === 'null')
    ? null
    : (int)$_POST['work_order_id'];
                $items_json = $_POST['items'] ?? '';
                $notes = trim($_POST['notes'] ?? '');
                $discount_type = in_array($_POST['discount_type'] ?? 'percent', ['percent', 'amount']) ? $_POST['discount_type'] : 'percent';
                $discount_value = (float)($_POST['discount_value'] ?? 0.0);
                $created_by_i = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

                if ($customer_id <= 0) jsonOut(['ok' => false, 'error' => 'الرجاء اختيار عميل.']);
                if (empty($items_json)) jsonOut(['ok' => false, 'error' => 'لا توجد بنود لإضافة الفاتورة.']);

                $items = json_decode($items_json, true);
                if (!is_array($items) || count($items) === 0) jsonOut(['ok' => false, 'error' => 'بنود الفاتورة غير صالحة.']);

                // ===== التحقق من الشغلانة إذا أُرسلت =====
                if ($work_order_id) {
                    $checkWorkOrderStmt = $conn->prepare("
            SELECT id, customer_id, status, title 
            FROM work_orders 
            WHERE id = ? AND customer_id = ? AND status != 'cancelled'
        ");
                    $checkWorkOrderStmt->bind_param('ii', $work_order_id, $customer_id);
                    $checkWorkOrderStmt->execute();
                    $workOrderResult = $checkWorkOrderStmt->get_result();

                    if ($workOrderResult->num_rows === 0) {
                        $checkWorkOrderStmt->close();
                        jsonOut(['ok' => false, 'error' => 'الشغلانة غير موجودة أو لا تنتمي لهذا العميل أو ملغية.']);
                    }
                    $workOrderRow = $workOrderResult->fetch_assoc();
                    $workOrderName = $workOrderRow['title'] ?? '';
                    $checkWorkOrderStmt->close();
                } else {
                    $workOrderName = '';
                }

                // ===== حساب الإجماليات =====
                $total_before = 0.0;
                $total_cost = 0.0;
                foreach ($items as $it) {
                    $qty = (float)($it['qty'] ?? $it['quantity'] ?? 0);
                    $sp = (float)($it['selling_price'] ?? $it['price'] ?? 0);
                    $cp = (float)($it['cost_price_per_unit'] ?? $it['cost_price'] ?? 0);

                    $total_before += round($qty * $sp, 2);
                    $total_cost += round($qty * $cp, 2);
                }
                $total_before = round($total_before, 2);
                $total_cost = round($total_cost, 2);

                // حساب الخصم
                if ($discount_type === 'percent') {
                    $discount_amount = round($total_before * ($discount_value / 100.0), 2);
                } else {
                    $discount_amount = round($discount_value, 2);
                }
                if ($discount_amount > $total_before) $discount_amount = $total_before;

                $total_after = round($total_before - $discount_amount, 2);
                $profit_after = round($total_after - $total_cost, 2);

                // ==== القيم الثابتة للفاتورة الجديدة ====
                $status = 'pending';
                $delivered = 'no';
                $paid_amount = 0;
                $remaining_amount = $total_after;

                try {
                    $conn->begin_transaction();

                    // ===== إدراج رأس الفاتورة =====
                    $invoice_group = 'group1';
                    $stmt = $conn->prepare("
            INSERT INTO invoices_out
            (customer_id, delivered, invoice_group, created_by, created_at, notes,
            total_before_discount, discount_type, discount_value, discount_amount, 
            total_after_discount, total_cost, profit_amount, paid_amount, remaining_amount, work_order_id)
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
                    if (!$stmt) throw new Exception($conn->error);

                    // bind_param مع التحقق من null لـ work_order_id
                  $stmt->bind_param(
    'issisdsdddddddi',
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
    $profit_after,
    $paid_amount,
    $remaining_amount,
    $work_order_id   // ← null مسموح
);


                    $stmt->execute();
                    if ($stmt->errno) {
                        $e = $stmt->error;
                        $stmt->close();
                        throw new Exception($e);
                    }

                    $invoice_id = (int)$conn->insert_id;
                    $stmt->close();

                    // ===== تسجيل حركة العميل =====
                    $balanceStmt = $conn->prepare("SELECT balance ,wallet FROM customers WHERE id = ? FOR UPDATE");
                    $balanceStmt->bind_param('i', $customer_id);
                    $balanceStmt->execute();
                    $balanceRow = $balanceStmt->get_result()->fetch_assoc();
                    $balance_before = (float)($balanceRow['balance'] ?? 0);
                    $balance_after = $balance_before + $total_after;
                    
$wallet_before  = (float)$balanceRow['wallet'];
$work_order_id = $work_order_id ?: null;
$wallet_after  = $wallet_before; 
                    $balanceStmt->close();

                    $description = "فاتورة مبيعات جديدة #{$invoice_id}";
                    if ($work_order_id) {
                        $description .= " (الشغلانة: \"{$workOrderName}\" رقم #{$work_order_id})";
                    }

                  $transactionStmt = $conn->prepare("
    INSERT INTO customer_transactions 
    (
        customer_id,
        transaction_type,
        amount,
        description,
        invoice_id,
        work_order_id,
        balance_before,
        balance_after,
        wallet_before,
        wallet_after,
        transaction_date,
        created_by
    )
    VALUES (?, 'invoice', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
");

$transactionStmt->bind_param(
    'idsiiddddi' ,
    $customer_id,
    $total_after,
    $description,
    $invoice_id,
    $work_order_id,
    $balance_before,
    $balance_after,
    $wallet_before,
    $wallet_after,
    $created_by_i
);

$transactionStmt->execute();
$transactionStmt->close();

                  

                    // ===== تحديث رصيد العميل =====
                    $updateBalanceStmt = $conn->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?");
                    $updateBalanceStmt->bind_param('di', $total_after, $customer_id);
                    $updateBalanceStmt->execute();
                    $updateBalanceStmt->close();

                    // ===== إدراج البنود وتخصيص FIFO =====
                    $totalRevenue = 0.0;
                    $totalCOGS = 0.0;

                    $insertItemStmt = $conn->prepare("
            INSERT INTO invoice_out_items
            (invoice_out_id, product_id, quantity, total_price, cost_price_per_unit, selling_price, price_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
                    $insertAllocStmt = $conn->prepare("INSERT INTO sale_item_allocations (sale_item_id, batch_id, qty, unit_cost, line_cost, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $updateBatchStmt = $conn->prepare("UPDATE batches SET remaining = ?, status = ?, adjusted_at = NOW(), adjusted_by = ? WHERE id = ?");
                    $selectBatchesStmt = $conn->prepare("SELECT id, remaining, unit_cost FROM batches WHERE product_id = ? AND status = 'active' AND remaining > 0 ORDER BY received_at ASC, created_at ASC, id ASC FOR UPDATE");

                    foreach ($items as $it) {
                        $product_id = (int)($it['product_id'] ?? 0);
                        $qty = (float)($it['qty'] ?? 0);
                        $selling_price = (float)($it['selling_price'] ?? 0);
                        $price_type = strtolower(trim((string)($it['price_type'] ?? 'wholesale')));

                        if ($product_id <= 0 || $qty <= 0) {
                            $conn->rollback();
                            jsonOut(['ok' => false, 'error' => "بند غير صالح (معرف/كمية)."]);
                        }

                        // جلب اسم المنتج
                        $product_name = null;
                        $pnameStmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
                        if ($pnameStmt) {
                            $pnameStmt->bind_param('i', $product_id);
                            $pnameStmt->execute();
                            $prow = $pnameStmt->get_result()->fetch_assoc();
                            $product_name = $prow['name'] ?? '';
                            $pnameStmt->close();
                        }

                        // تخصيص FIFO
                        $selectBatchesStmt->bind_param('i', $product_id);
                        $selectBatchesStmt->execute();
                        $availableBatches = $selectBatchesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
                                'error' => "الرصيد غير كافٍ للمنتج '{$product_name}'. (ID: {$product_id})"
                            ]);
                        }

                        $itemTotalCost = 0.0;
                        foreach ($allocations as $a) $itemTotalCost += $a['take'] * $a['unit_cost'];
                        $cost_price_per_unit = ($qty > 0) ? ($itemTotalCost / $qty) : 0.0;

                        // جلب سعر البيع إذا لم يُرسل
                        if ($selling_price <= 0) {
                            $ppriceStmt = $conn->prepare("SELECT retail_price, selling_price FROM products WHERE id = ?");
                            $ppriceStmt->bind_param('i', $product_id);
                            $ppriceStmt->execute();
                            $prow = $ppriceStmt->get_result()->fetch_assoc();
                            if ($prow) {
                                $selling_price = ($price_type === 'retail') ? (float)($prow['retail_price'] ?? 0) : (float)($prow['selling_price'] ?? 0);
                            }
                            $ppriceStmt->close();
                        }

                        $lineTotalPrice = $qty * $selling_price;

                        // إدراج البند
                        $invoice_item_id = $invoice_id;
                        $insertItemStmt->bind_param('iidddds', $invoice_item_id, $product_id, $qty, $lineTotalPrice, $cost_price_per_unit, $selling_price, $price_type);
                        $insertItemStmt->execute();
                        if ($insertItemStmt->errno) {
                            $err = $insertItemStmt->error;
                            $insertItemStmt->close();
                            throw new Exception($err);
                        }
                        $invoice_item_id = (int)$conn->insert_id;

                        // تطبيق التخصيصات على الـ batches
                        foreach ($allocations as $a) {
                            $stmtCur = $conn->prepare("SELECT remaining FROM batches WHERE id = ? FOR UPDATE");
                            $stmtCur->bind_param('i', $a['batch_id']);
                            $stmtCur->execute();
                            $curRow = $stmtCur->get_result()->fetch_assoc();
                            $stmtCur->close();

                            $newRem = max(0.0, ((float)$curRow['remaining']) - $a['take']);
                            $newStatus = ($newRem <= 0) ? 'consumed' : 'active';

                            $updateBatchStmt->bind_param('dsii', $newRem, $newStatus, $created_by_i, $a['batch_id']);
                            $updateBatchStmt->execute();

                            $lineCost = $a['take'] * $a['unit_cost'];
                            $insertAllocStmt->bind_param('iidddi', $invoice_item_id, $a['batch_id'], $a['take'], $a['unit_cost'], $lineCost, $created_by_i);
                            $insertAllocStmt->execute();
                        }

                        $totalRevenue += $lineTotalPrice;
                        $totalCOGS += $itemTotalCost;
                    }

                    $insertItemStmt->close();
                    $insertAllocStmt->close();
                    $updateBatchStmt->close();
                    $selectBatchesStmt->close();

                    function updateWorkOrderTotals($conn, $work_order_id)
                    {
                        $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total_after_discount), 0) as total_invoices,
            COALESCE(SUM(paid_amount), 0) as total_paid,
            COALESCE(SUM(remaining_amount), 0) as total_remaining
        FROM invoices_out 
        WHERE work_order_id = ?   AND delivered NOT IN ('reverted', 'cancelled')

    ");
                        $stmt->bind_param('i', $work_order_id);
                        $stmt->execute();
                        $result = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        $stmt = $conn->prepare("
        UPDATE work_orders 
        SET total_invoice_amount = ?, total_paid = ?, total_remaining = ?, updated_at = NOW() 
        WHERE id = ?
    ");
                        $stmt->bind_param(
                            'dddi',
                            $result['total_invoices'],
                            $result['total_paid'],
                            $result['total_remaining'],
                            $work_order_id
                        );
                        $stmt->execute();
                        $stmt->close();
                    }
                    // تحديث الشغلانة
                    if ($work_order_id) {
                        updateWorkOrderTotals($conn, $work_order_id);
                    }

                    $conn->commit();

                    jsonOut([
                        'ok' => true,
                        'msg' => 'تم إنشاء الفاتورة بنجاح.',
                        'invoice_id' => $invoice_id,
                        'invoice_number' => $invoice_id,
                        'total_revenue' => round($totalRevenue, 2),
                        'total_cogs' => round($totalCOGS, 2),
                        'paid_amount' => 0,
                        'remaining_amount' => $total_after,
                        'payment_status' => 'pending',
                        'discount_type' => $discount_type,
                        'discount_value' => $discount_value,
                        'discount_amount' => $discount_amount,
                        'total_before' => $total_before,
                        'total_after' => $total_after,
                        'work_order_id' => $work_order_id,
                        'customer_balance_after' => $balance_after
                    ]);
                } catch (Exception $e) {
                    if (isset($conn)) $conn->rollback();
                    error_log("save_invoice error: " . $e->getMessage());
                    jsonOut(['ok' => false, 'error' => 'حدث خطأ أثناء حفظ الفاتورة.', 'detail' => $e->getMessage()]);
                }
            }