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

    // E.g. payments can be sent as JSON string in POST
    $payments_json = $_POST['payments'] ?? ($_POST['payments_json'] ?? '');
    $payments = [];
    if (!empty($payments_json)) {
        $payments = json_decode($payments_json, true);
        if (!is_array($payments)) $payments = [];
    }

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

    // ==== القيم الافتراضية للفاتورة الجديدة ====
    $status = 'pending';
    $delivered = 'no';
    $paid_amount = 0.0;
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

        // ===== قفل صف العميل للحصول على balance/wallet قبل أي تعديل =====
        $balanceStmt = $conn->prepare("SELECT balance, wallet FROM customers WHERE id = ? FOR UPDATE");
        $balanceStmt->bind_param('i', $customer_id);
        $balanceStmt->execute();
        $balanceRow = $balanceStmt->get_result()->fetch_assoc();
        $balance_before = (float)($balanceRow['balance'] ?? 0);
        $wallet_before  = (float)($balanceRow['wallet'] ?? 0);
        $balanceStmt->close();

        // ===== تسجيل حركة الفاتورة (تزيد رصيد العميل) =====
        $balance_after = $balance_before + $total_after;
        $wallet_after = $wallet_before;

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

        if (!$transactionStmt) throw new Exception($conn->error);

        $transactionStmt->bind_param(
            'idsiiddddi',
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
        if ($transactionStmt->errno) {
            $err = $transactionStmt->error;
            $transactionStmt->close();
            throw new Exception($err);
        }
        $transactionStmt->close();

        // ===== تحديث رصيد العميل (نضيف دين الفاتورة) =====
        $updateBalanceStmt = $conn->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?");
        $updateBalanceStmt->bind_param('di', $total_after, $customer_id);
        $updateBalanceStmt->execute();
        if ($updateBalanceStmt->errno) {
            $err = $updateBalanceStmt->error;
            $updateBalanceStmt->close();
            throw new Exception($err);
        }
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

        // -------------------------------------------
        // ===== معالجة الدفع المباشر داخل نفس الترانزاكشن =====
        // -------------------------------------------
        $payments_processed = [];
        $total_paid = 0.0;

        if (is_array($payments) && count($payments) > 0) {
            // تحقق سريع: لا ندع المدفوع أكبر من الإجمالي
            $sumPayments = 0.0;
            foreach ($payments as $p) {
                $sumPayments += (float)($p['amount'] ?? 0);
            }
            if ($sumPayments > $total_after + 0.0001) {
                $conn->rollback();
                jsonOut(['ok' => false, 'error' => 'مجموع المدفوعات أكبر من إجمالي الفاتورة.']);
            }

            // سنستخدم المتغيرات المحفوظة: $balance_before, $balance_after, $wallet_before, $wallet_after
            $current_wallet = $wallet_before;
            $current_balance = $balance_after; // لأننا أضفنا دين الفاتورة بالفعل

            // تحضير إدراج في جدول invoice_payments (إن وجد)
            $insertPaymentStmt = $conn->prepare("
                INSERT INTO invoice_payments
                (invoice_id, payment_amount, payment_method, notes, created_by, created_at, wallet_before, wallet_after, work_order_id)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)
            ");
            if (!$insertPaymentStmt) {
                // قد لا يوجد جدول بهذا الاسم في مشروعك — إذا كان كذلك، تعطيل هذا الجزء أو استبداله بدالة مساعدة
                // سنكمل برمي استثناء لنعرف الحاجة للتعديل
                throw new Exception("لم يتم العثور على invoice_payments table أو خطأ في التحضير: " . $conn->error);
            }

            $insertPaymentStmt_errno = false;

            foreach ($payments as $p) {
                $method = strtolower(trim($p['method'] ?? ''));
                $amount = round((float)($p['amount'] ?? 0), 2);
                if ($amount <= 0) continue;

                // تعامل خاص بالمحفظة
                if ($method === 'wallet') {
                    if ($current_wallet < $amount - 0.0001) {
                        $conn->rollback();
                        jsonOut(['ok' => false, 'error' => 'رصيد المحفظة غير كافي للسداد.']);
                    }
                    $wallet_before_payment = $current_wallet;
                    $current_wallet -= $amount;
                    $wallet_after_payment = $current_wallet;

                    // حدّث جدول العملاء للمحفظة مؤقتًا (سيتم commit لاحقًا)
                    $updateWalletStmt = $conn->prepare("UPDATE customers SET wallet = ? WHERE id = ?");
                    $updateWalletStmt->bind_param('di', $current_wallet, $customer_id);
                    $updateWalletStmt->execute();
                    if ($updateWalletStmt->errno) {
                        $err = $updateWalletStmt->error;
                        $updateWalletStmt->close();
                        throw new Exception($err);
                    }
                    $updateWalletStmt->close();
                } else {
                    $wallet_before_payment = $current_wallet;
                    $wallet_after_payment = $current_wallet;
                }

                // إدراج صف في invoice_payments
                $payment_notes = "سداد لفاتورة #{$invoice_id} - طريقة: {$method}";
                $created_by = $created_by_i;
                $workOrderParam = $work_order_id ?: null;

                $insertPaymentStmt->bind_param('idssiddi',
                    $invoice_id,
                    $amount,
                    $method,
                    $payment_notes,
                    $created_by,
                    $wallet_before_payment,
                    $wallet_after_payment,
                    $workOrderParam
                );
                $insertPaymentStmt->execute();
                if ($insertPaymentStmt->errno) {
                    $insertPaymentStmt_errno = true;
                    $err = $insertPaymentStmt->error;
                    $insertPaymentStmt->close();
                    throw new Exception($err);
                }

                $payment_row_id = (int)$conn->insert_id;

                // إنشاء سجل في customer_transactions من نوع payment (نقص في رصيد)
                $balance_before_payment = $current_balance;
                $current_balance -= $amount;
                $balance_after_payment = $current_balance;
                $transaction_amount = -1 * $amount;

                $paymentDesc = "سداد فاتورة #{$invoice_id} - " . number_format($amount, 2) . " ج.م (" . $method . ")";

                $paymentTransStmt = $conn->prepare("
                    INSERT INTO customer_transactions
                    (customer_id, transaction_type, amount, description, invoice_id, payment_id, balance_before, balance_after, wallet_before, wallet_after, transaction_date, created_by)
                    VALUES (?, 'payment', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                if (!$paymentTransStmt) throw new Exception($conn->error);

                $paymentTransStmt->bind_param(
                    'idsiiddddi',
                    $customer_id,
                      $transaction_amount,                 // negative amount for payment in transactions log
                    $paymentDesc,
                    $invoice_id,
                    $payment_row_id,
                    $balance_before_payment,
                    $balance_after_payment,
                    $wallet_before_payment,
                    $wallet_after_payment,
                    $created_by_i
                );
                $paymentTransStmt->execute();
                if ($paymentTransStmt->errno) {
                    $err = $paymentTransStmt->error;
                    $paymentTransStmt->close();
                    throw new Exception($err);
                }
                $paymentTransStmt->close();

                $payments_processed[] = [
                    'payment_row_id' => $payment_row_id,
                    'method' => $method,
                    'amount' => $amount,
                    'wallet_before' => $wallet_before_payment,
                    'wallet_after' => $wallet_after_payment
                ];

                $total_paid += $amount;
            } // end foreach payments

            $insertPaymentStmt->close();

            // ===== تحديث رصيد العميل بعد الدفعات (نخصم المدفوع من الرصيد الذي زدناه سابقًا) =====
            if ($total_paid > 0) {
                $updateBalanceAfterPaymentsStmt = $conn->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?");
                $updateBalanceAfterPaymentsStmt->bind_param('di', $total_paid, $customer_id);
                $updateBalanceAfterPaymentsStmt->execute();
                if ($updateBalanceAfterPaymentsStmt->errno) {
                    $err = $updateBalanceAfterPaymentsStmt->error;
                    $updateBalanceAfterPaymentsStmt->close();
                    throw new Exception($err);
                }
                $updateBalanceAfterPaymentsStmt->close();
            }

            // ===== تحديث حقل paid_amount و remaining_amount و status في الفاتورة =====
            $new_paid_amount = $paid_amount + $total_paid;
            $new_remaining = max(0.0, $total_after - $new_paid_amount);
            if ($new_paid_amount <= 0) {
                $new_status = 'pending';
            } elseif ($new_paid_amount < $total_after) {
                $new_status = 'partial';
            } else {
                $new_status = 'paid';
            }

            $updateInvoiceStatusStmt = $conn->prepare("UPDATE invoices_out SET paid_amount = ?, remaining_amount = ?, delivered = ?  WHERE id = ?");
            // Note: If you have a 'status' column replace delivered logic above — adjust SQL accordingly.
            $updateInvoiceStatusStmt->bind_param('ddsi', $new_paid_amount, $new_remaining, $delivered, $invoice_id);
            $updateInvoiceStatusStmt->execute();
            if ($updateInvoiceStatusStmt->errno) {
                $err = $updateInvoiceStatusStmt->error;
                $updateInvoiceStatusStmt->close();
                throw new Exception($err);
            }
            $updateInvoiceStatusStmt->close();
        } // end if payments provided

        // تحديث totals بالشغلانة إذا لزم (كما عندك)
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

        if ($work_order_id) {
            updateWorkOrderTotals($conn, $work_order_id);
        }

        $conn->commit();

        // جلب البيانات المحدثة للرد النهائي
        $updatedCustomer = null;
        $updatedInvoice = null;
        // Use your existing helper functions if available:
        if (function_exists('getCustomerData')) $updatedCustomer = getCustomerData($conn, $customer_id);
        else {
            $rs = $conn->query("SELECT balance, wallet FROM customers WHERE id = " . intval($customer_id));
            $updatedCustomer = $rs->fetch_assoc();
        }
        if (function_exists('getInvoiceData')) $updatedInvoice = getInvoiceData($conn, $invoice_id);
        else {
            $rs = $conn->query("SELECT paid_amount, remaining_amount FROM invoices_out WHERE id = " . intval($invoice_id));
            $updatedInvoice = $rs->fetch_assoc();
        }

        jsonOut([
            'ok' => true,
            'msg' => 'تم إنشاء الفاتورة بنجاح.',
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_id,
            'total_revenue' => round($totalRevenue, 2),
            'total_cogs' => round($totalCOGS, 2),
            'paid_amount' => isset($updatedInvoice['paid_amount']) ? (float)$updatedInvoice['paid_amount'] : $new_paid_amount,
            'remaining_amount' => isset($updatedInvoice['remaining_amount']) ? (float)$updatedInvoice['remaining_amount'] : $new_remaining,
            'payment_status' => (isset($new_status) ? $new_status : 'pending'),
            'payments_processed' => $payments_processed,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'discount_amount' => $discount_amount,
            'total_before' => $total_before,
            'total_after' => $total_after,
            'work_order_id' => $work_order_id,
            'customer_balance_after' => isset($updatedCustomer['balance']) ? (float)$updatedCustomer['balance'] : $balance_after - $total_paid,
            'customer_wallet_after' => isset($updatedCustomer['wallet']) ? (float)$updatedCustomer['wallet'] : $wallet_after
        ]);

    } catch (Exception $e) {
        if (isset($conn)) $conn->rollback();
        error_log("save_invoice error: " . $e->getMessage());
        jsonOut(['ok' => false, 'error' => 'حدث خطأ أثناء حفظ الفاتورة.', 'detail' => $e->getMessage()]);
    }
} // end save_invoice