ركز معايا كويس قوي تصرف كمحترف باك اند وفرونت
html css js php mysql
عندي صفحه انشاء فاتوره 
عادي وليها api 
وليها طرق دفع وهكذا مؤجل جوئي مدفوع 
واقدر احدد كل طريقه بنفسي
كنت شغال علي api
قديم هبعتوا ليك
   if ($action === 'save_invoice' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $token = $_POST['csrf_token'] ?? '';
                if (!hash_equals($_SESSION['csrf_token'], (string)$token)) {
                    jsonOut(['ok' => false, 'error' => 'رمز التحقق (CSRF) غير صالح. أعد تحميل الصفحة وحاول مجدداً.']);
                }

                $customer_id = (int)($_POST['customer_id'] ?? 0);
                $status = ($_POST['status'] ?? 'pending') === 'paid' ? 'paid' : (($_POST['status'] ?? 'pending') === 'partial' ? 'partial' : 'pending');
                $items_json = $_POST['items'] ?? '';
                $notes = trim($_POST['notes'] ?? '');

                $payments_json = $_POST['payments'] ?? '[]';
                $created_by_i = (int)($_SESSION['id'] ?? 0);

                if ($customer_id <= 0) jsonOut(['ok' => false, 'error' => 'الرجاء اختيار عميل.']);
                if (empty($items_json)) jsonOut(['ok' => false, 'error' => 'لا توجد بنود لإضافة الفاتورة.']);

                $items = json_decode($items_json, true);
                $payments = json_decode($payments_json, true);

                if (!is_array($items) || count($items) === 0) jsonOut(['ok' => false, 'error' => 'بنود الفاتورة غير صالحة.']);

                // ===== حساب الإجماليات على السيرفر =====
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

                // قراءة بيانات الخصم
                $discount_type = in_array($_POST['discount_type'] ?? 'percent', ['percent', 'amount']) ? $_POST['discount_type'] : 'percent';
                $discount_value = (float)($_POST['discount_value'] ?? 0.0);

                // حساب مبلغ الخصم فعلياً
                if ($discount_type === 'percent') {
                    $discount_amount = round($total_before * ($discount_value / 100.0), 2);
                } else {
                    $discount_amount = round($discount_value, 2);
                }
                if ($discount_amount > $total_before) $discount_amount = $total_before;

                $total_after = round($total_before - $discount_amount, 2);

                // حفظ الإحصائيات المؤقتة للربح
                $profit_before = round($total_before - $total_cost, 2);
                $profit_after = round($total_after - $total_cost, 2);

                if (!is_array($items) || count($items) === 0) jsonOut(['ok' => false, 'error' => 'بنود الفاتورة غير صالحة.']);

                try {
                    // begin transaction
                    $conn->begin_transaction();

                    // ===== تحديد حالة الدفع والمبالغ =====
                    if ($status === 'paid') {
                        $delivered = 'yes';
                        $paid_amount = $total_after;
                        $remaining_amount = 0;

                        // إذا لم توجد مدفوعات، ننشئ دفعة واحدة بالقيمة الكاملة
                        if (!is_array($payments) || count($payments) === 0) {
                            $payments = [[
                                'amount' => $total_after,
                                'method' => 'cash',
                                'notes' => 'دفعة كاملة تلقائية'
                            ]];
                        }
                    } elseif ($status === 'partial') {
                        $delivered = 'partial';
                        $paid_amount = 0;

                        // حساب المبلغ المدفوع من المدفوعات المرسلة
                        if (is_array($payments)) {
                            foreach ($payments as $payment) {
                                $paid_amount += (float)($payment['amount'] ?? 0);
                            }
                        }

                        // التحقق من أن المدفوعات لا تتجاوز الإجمالي
                        if ($paid_amount > $total_after) {
                            throw new Exception('مجموع المدفوعات يتجاوز إجمالي الفاتورة.');
                        }

                        // التحقق من وجود مدفوعات للفاتورة الجزئية
                        if ($paid_amount <= 0) {
                            throw new Exception('الفاتورة الجزئية تحتاج إلى مدفوعات.');
                        }

                        $remaining_amount = max(0, $total_after - $paid_amount);
                    } else {
                        // pending
                        $delivered = 'no';
                        $paid_amount = 0;
                        $remaining_amount = $total_after;
                        $payments = []; // لا مدفوعات للفواتير المؤجلة
                    }

                    // ===== إدراج رأس الفاتورة =====
                    $invoice_group = 'group1';
                    $stmt = $conn->prepare("
                    INSERT INTO invoices_out
                    (customer_id, delivered, invoice_group, created_by, created_at, notes,
                    total_before_discount, discount_type, discount_value, discount_amount, 
                    total_after_discount, total_cost, profit_amount, paid_amount, remaining_amount)
                    VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                    if (!$stmt) throw new Exception($conn->error);

                    $stmt->bind_param(
                        'issisdsddddddd',
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
                        $remaining_amount
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

                    // ===== إعداد الـ statements الشائعة الاستخدام =====
                    $insertItemStmt = $conn->prepare("
                    INSERT INTO invoice_out_items
                    (invoice_out_id, product_id, quantity, total_price, cost_price_per_unit, selling_price, price_type, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                    if (!$insertItemStmt) throw new Exception($conn->error);

                    $insertAllocStmt = $conn->prepare("INSERT INTO sale_item_allocations (sale_item_id, batch_id, qty, unit_cost, line_cost, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    if (!$insertAllocStmt) throw new Exception($conn->error);

                    $updateBatchStmt = $conn->prepare("UPDATE batches SET remaining = ?, status = ?, adjusted_at = NOW(), adjusted_by = ? WHERE id = ?");
                    if (!$updateBatchStmt) throw new Exception($conn->error);

                    $selectBatchesStmt = $conn->prepare("SELECT id, remaining, unit_cost FROM batches WHERE product_id = ? AND status = 'active' AND remaining > 0 ORDER BY received_at ASC, created_at ASC, id ASC FOR UPDATE");
                    if (!$selectBatchesStmt) throw new Exception($conn->error);

                    // ===== معالجة بنود الفاتورة =====
                    foreach ($items as $it) {
                        $product_id = (int)($it['product_id'] ?? 0);
                        $qty = (float)($it['qty'] ?? 0);
                        $selling_price = (float)($it['selling_price'] ?? 0);
                        if ($product_id <= 0 || $qty <= 0) {
                            $conn->rollback();
                            jsonOut(['ok' => false, 'error' => "بند غير صالح (معرف/كمية)."]);
                        }

                        // --- احصل على اسم المنتج لاستخدامه في رسائل الخطأ ---
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

                        // --- تخصيص FIFO ---
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
                                'error' => "الرصيد غير كافٍ للمنتج '{$product_name}'. (ID: {$product_id})"
                            ]);
                        }

                        $itemTotalCost = 0.0;
                        foreach ($allocations as $a) $itemTotalCost += $a['take'] * $a['unit_cost'];
                        $cost_price_per_unit = ($qty > 0) ? ($itemTotalCost / $qty) : 0.0;

                        // --- قراءة price_type وتحديد selling_price إن لزم ---
                        $price_type = strtolower(trim((string)($it['price_type'] ?? 'wholesale')));
                        if (!in_array($price_type, ['retail', 'wholesale'])) $price_type = 'wholesale';

                        // optional: fallback to product prices if selling_price not provided
                        if ($selling_price <= 0) {
                            $ppriceStmt = $conn->prepare("SELECT retail_price, selling_price FROM products WHERE id = ?");
                            if ($ppriceStmt) {
                                $ppriceStmt->bind_param('i', $product_id);
                                $ppriceStmt->execute();
                                $pres = $ppriceStmt->get_result();
                                $prow = $pres ? $pres->fetch_assoc() : null;
                                if ($prow) {
                                    $selling_price = ($price_type === 'retail') ? (float)($prow['retail_price'] ?? 0) : (float)($prow['selling_price'] ?? 0);
                                }
                                $ppriceStmt->close();
                            }
                        }

                        $lineTotalPrice = $qty * $selling_price;

                        // --- إدراج بند الفاتورة ---
                        $invoice_id_i = $invoice_id;
                        $prod_id_i = $product_id;
                        $insertItemStmt->bind_param('iidddds', $invoice_id_i, $prod_id_i, $qty, $lineTotalPrice, $cost_price_per_unit, $selling_price, $price_type);
                        $insertItemStmt->execute();
                        if ($insertItemStmt->errno) {
                            $err = $insertItemStmt->error;
                            $insertItemStmt->close();
                            throw new Exception($err);
                        }

                        $invoice_item_id = (int)$conn->insert_id;

                        // --- تطبيق التخصيصات وتحديث الدفعات ---
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
                        }

                        $totalRevenue += $lineTotalPrice;
                        $totalCOGS += $itemTotalCost;
                    }

                    // ===== حفظ المدفوعات =====
                    if (($status === 'partial' || $status === 'paid') && is_array($payments) && count($payments) > 0) {
                        $stmtPayment = $conn->prepare("
                        INSERT INTO invoice_payments 
                        (invoice_id, payment_amount, payment_method, notes, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                        if (!$stmtPayment) throw new Exception($conn->error);

                        foreach ($payments as $payment) {
                            $amount = (float)($payment['amount'] ?? 0);
                            $method = in_array($payment['method'] ?? 'cash', ['cash', 'bank_transfer', 'check', 'card']) ?
                                $payment['method'] : 'cash';
                            $payment_notes = $payment['notes'] ?? '';

                            if ($amount <= 0) {
                                continue; // تخطي المدفوعات غير 
                            }
                            $stmtPayment->bind_param('idssi', $invoice_id, $amount, $method, $payment_notes, $created_by_i);
                            $stmtPayment->execute();
                            if ($stmtPayment->errno) {
                                $err = $stmtPayment->error;
                                $stmtPayment->close();
                                throw new Exception($err);
                            }
                        }
                        $stmtPayment->close();
                    }

                    // ===== commit العملية =====
                    $conn->commit();

                    // --- مسح العميل المختار من الجلسة بعد إنشاء الفاتورة ---
                    if (isset($_SESSION['selected_customer'])) {
                        unset($_SESSION['selected_customer']);
                    }

                    jsonOut([
                        'ok' => true,
                        'msg' => 'تم إنشاء الفاتورة بنجاح.',
                        'invoice_id' => $invoice_id,
                        'invoice_number' => $invoice_id,
                        'total_revenue' => round($totalRevenue, 2),
                        'total_cogs' => round($totalCOGS, 2),
                        'paid_amount' => $paid_amount,
                        'remaining_amount' => $remaining_amount,
                        'payment_status' => $status,
                        'discount_type' => $discount_type,        // أضف هذا
                        'discount_value' => $discount_value,      // أضف هذا
                        'discount_amount' => $discount_amount,    // أضف هذا
                        'total_before' => $total_before,          // أضف هذا
                        'total_after' => $total_after            // أضف هذا
                    ]);
                } catch (Exception $e) {
                    // rollback في حالة الخطأ

                    error_log("save_invoice error: " . $e->getMessage());
                    jsonOut(['ok' => false, 'error' => 'حدث خطأ أثناء حفظ الفاتورة.', 'detail' => $e->getMessage()]);
                }
            }


تقدر تذاكروا وتفهموا كويس قوي وده الrquest
 const invoiceData = {
                        work_order_id: AppState.currentWorkOrder?.id || null,
                        customer_id: AppState.currentCustomer.id,
                        status: paymentStatus,
                        items: JSON.stringify(AppState.invoiceItems.map(item => ({
                            product_id: item.id,
                            qty: item.quantity,
                            selling_price: item.price,
                            price_type: item.priceType,
                            cost_price_per_unit: item.cost || 0
                        }))),
                        discount_type: AppState.discount.type,
                        discount_value: AppState.discount.value,
                        notes: AppState.invoiceNotes,
                        payments: JSON.stringify(AppState.payments), // إرسال المدفوعات
                        csrf_token: AppState.csrfToken
                    };



الميزات الجديده اللي ضيفتها هي تحركات العميل وحركات المحفظه 
وان الفاتوره ممكن تبقي تبع 
شغلانه معينه 
دي الجداول

CREATE TABLE `invoices_out` (
  `id` int(11) NOT NULL COMMENT 'المعرف التلقائي للفاتورة',
  `customer_id` int(11) NOT NULL COMMENT 'معرف العميل المرتبط بالفاتورة',
  `delivered` enum('yes','no','canceled','reverted','partial') NOT NULL DEFAULT 'no',
  `invoice_group` enum('group1','group2','group3','group4','group5','group6','group7','group8','group9','group10','group11') NOT NULL COMMENT 'مجموعة الفاتورة (من 1 إلى 11)',
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أنشأ الفاتورة',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ ووقت الإنشاء',
  `updated_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي آخر من عدل الفاتورة',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp() COMMENT 'تاريخ ووقت آخر تعديل',
  `notes` text DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `revert_reason` varchar(255) DEFAULT NULL,
  `total_before_discount` decimal(12,2) DEFAULT 0.00 COMMENT 'مجموع البيع قبل أي خصم',
  `discount_type` enum('percent','amount') DEFAULT 'percent' COMMENT 'نوع الخصم',
  `discount_value` decimal(10,2) DEFAULT 0.00 COMMENT 'قيمة الخصم: إذا percent -> تخزن النسبة (مثال: 10) وإلا قيمة المبلغ',
  `discount_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'مبلغ الخصم المحسوب بالعملة',
  `total_after_discount` decimal(12,2) DEFAULT 0.00 COMMENT 'المجموع النهائي بعد الخصم',
  `total_cost` decimal(12,2) DEFAULT 0.00 COMMENT 'اجمالي التكلفة (مخزن للتقارير)',
  `profit_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'اجمالي الربح = total_before_discount - total_cost',
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `remaining_amount` decimal(12,2) DEFAULT 0.00,
  `work_order_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول فواتير العملاء الصادرة';

--

CREATE TABLE `work_orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'عنوان الشغلانة',
  `description` text DEFAULT NULL COMMENT 'وصف تفصيلي',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `start_date` date NOT NULL COMMENT 'تاريخ البدء',
  `notes` text DEFAULT NULL COMMENT 'ملاحظات إضافية',
  `total_invoice_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي فواتير الشغلانة',
  `total_paid` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي المدفوع',
  `total_remaining` decimal(12,2) DEFAULT 0.00 COMMENT 'إجمالي المتبقي',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_transactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `transaction_type` enum('invoice','payment','return','deposit','adjustment','withdraw') NOT NULL,
  `amount` decimal(12,2) NOT NULL COMMENT 'موجب للزيادة، سالب للنقصان',
  `description` varchar(255) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `return_id` int(11) DEFAULT NULL,
  `wallet_transaction` int(11) DEFAULT NULL,
  `work_order_id` int(11) DEFAULT NULL,
  `balance_before` decimal(12,2) DEFAULT 0.00,
  `balance_after` decimal(12,2) DEFAULT 0.00,
  `wallet_before` decimal(12,2) DEFAULT 0.00,
  `wallet_after` decimal(12,2) DEFAULT 0.00,
  `transaction_date` date NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--

الجدول الاتي
لو في سحب من محفظه 
CREATE TABLE `wallet_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id` INT(11) NOT NULL,
    
    -- نوع الحركة
    `type` ENUM('deposit', 'withdraw', 'refund', 'invoice_payment') NOT NULL,
    
    `amount` DECIMAL(12,2) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    
    -- رصيد المحفظة قبل وبعد العملية
    `wallet_before` DECIMAL(12,2) DEFAULT 0.00,
    `wallet_after` DECIMAL(12,2) DEFAULT 0.00,
    
    -- تاريخ الحركة
    `transaction_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    `created_by` INT(11) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    
    -- العلاقات
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    
    -- فهرسة لتسريع البحث
    INDEX idx_customer_date (`customer_id`, `transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `invoice_payments` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('cash','bank_transfer','check','card','wallet','mixed') DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `wallet_before` decimal(12,2) DEFAULT 0.00 COMMENT 'رصيد المحفظة قبل الدفع',
  `wallet_after` decimal(12,2) DEFAULT 0.00 COMMENT 'رصيد المحفظة بعد الدفع',
  `work_order_id` int(11) DEFAULT NULL COMMENT 'ربط بالشغلانة'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL COMMENT 'اسم العميل',
  `mobile` varchar(11) NOT NULL COMMENT 'رقم الموبايل (11 رقم)',
  `city` varchar(100) NOT NULL COMMENT 'المدينة',
  `address` varchar(255) DEFAULT NULL COMMENT 'العنوان التفصيلي',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'معرف المستخدم الذي أضاف العميل',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'تاريخ الإضافة',
  `balance` decimal(12,2) DEFAULT 0.00 COMMENT 'الرصيد الحالي (مدين + / دائن -)',
  `wallet` decimal(12,2) DEFAULT 0.00 COMMENT 'رصيد المحفظة',
  `join_date` date DEFAULT curdate() COMMENT 'تاريخ الانضمام'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




انا كده عندي 2api 
save_invoice 
القديم 
و
payment
اعمل اي