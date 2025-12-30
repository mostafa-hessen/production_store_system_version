<?php
    require_once dirname(__DIR__) . '/config.php';


require_once __DIR__ . './helper/payment_functions.php';

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set('Africa/Cairo');

// ==============================================
// الدوال المساعدة
// ==============================================

/**
 * تحويل طريقة الدفع إلى عربي
 */
function getPaymentMethodArabic($method) {
    $map = [
        'cash' => 'نقدي',
        'wallet' => 'محفظة', 
        'bank_transfer' => 'تحويل بنكي',
        'check' => 'شيك',
        'card' => 'بطاقة',
        'mixed' => 'مختلط'
    ];
    return $map[$method] ?? $method;
}

/**
 * حساب wallet_before و wallet_after لكل دفعة - مصححة
 */
function calculateWalletForPayment($allocations, &$currentWallet) {
    $hasWallet = false;
    $walletInThisPayment = 0;
    
    // البحث عن تخصيص المحفظة
    foreach ($allocations as $allocation) {
        if ($allocation['method'] === 'wallet') {
            $hasWallet = true;
            $walletInThisPayment = $allocation['amount'];
            break;
        }
    }
    
    if ($hasWallet) {
        $paymentWalletBefore = $currentWallet;
        $paymentWalletAfter = $currentWallet - $walletInThisPayment;
        $currentWallet = $paymentWalletAfter;
        
        return [
            'wallet_before' => $paymentWalletBefore,
            'wallet_after' => $paymentWalletAfter,
            'wallet_amount' => $walletInThisPayment,
            'has_wallet' => true
        ];
    }
    
    // ✅ الإصلاح: إرجاع نفس القيمة إذا لم تكن المحفظة مستخدمة
    return [
        'wallet_before' => $currentWallet, // نفس القيمة، لا تغيير
        'wallet_after' => $currentWallet,  // نفس القيمة، لا تغيير
        'wallet_amount' => 0,
        'has_wallet' => false
    ];
}
/**
 * تحديث إجماليات الشغلانة بعد السداد
 */
function updateWorkOrderTotals($conn, $workOrderId) {
    // حساب المجاميع من الفواتير المرتبطة بالشغلانة
    $calculateQuery = "
        SELECT 
            COALESCE(SUM(total_after_discount), 0) as total_invoice_amount,
            COALESCE(SUM(paid_amount), 0) as total_paid,
            COALESCE(SUM(remaining_amount), 0) as total_remaining
        FROM invoices_out 
        WHERE work_order_id = ? 
          AND delivered NOT IN ('canceled', 'reverted')
    ";
    
    $stmt = $conn->prepare($calculateQuery);
    $stmt->bind_param("i", $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totals = $result->fetch_assoc();
    $stmt->close();
    
    // تحديث الشغلانة
    $updateQuery = "
        UPDATE work_orders 
        SET total_invoice_amount = ?,
            total_paid = ?,
            total_remaining = ?,
            updated_at = NOW()
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("dddi", 
        $totals['total_invoice_amount'],
        $totals['total_paid'],
        $totals['total_remaining'],
        $workOrderId
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * الحصول على بيانات الشغلانة
 */
function getWorkOrderData($conn, $workOrderId) {
    $query = "
        SELECT id, title, total_invoice_amount, total_paid, total_remaining
        FROM work_orders 
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    return $data ?: null;
}
// ==============================================
// الدوال الرئيسية
// ==============================================

/**
 * معالجة سداد فاتورة واحدة
 */
function processSinglePayment($conn, $input) {
    // التحقق من البيانات
    $required = ['customer_id', 'invoice_id', 'amount', 'payment_method'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("الحقل '$field' مطلوب للسداد الفردي");
        }
    }
    
    $customerId = (int)$input['customer_id'];
    $invoiceId = (int)$input['invoice_id'];
    $amount = (float)$input['amount'];
    $paymentMethod = $input['payment_method'];
    $createdBy = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 1;
    $notes = $input['notes'] ?? '';
    $workOrderId = isset($input['work_order_id']) ? (int)$input['work_order_id'] : null;
    
    // التحقق من وجود الفاتورة
    $invoiceStmt = $conn->prepare("
        SELECT id, customer_id, total_after_discount, paid_amount, remaining_amount 
        FROM invoices_out 
        WHERE id = ? AND customer_id = ?
    ");
    $invoiceStmt->bind_param("ii", $invoiceId, $customerId);
    $invoiceStmt->execute();
    $invoiceResult = $invoiceStmt->get_result();
    
    if ($invoiceResult->num_rows === 0) {
        throw new Exception("الفاتورة غير موجودة أو لا تنتمي للعميل");
    }
    
    $invoice = $invoiceResult->fetch_assoc();
        $workOrderId = isset($invoice['work_order_id']) ? $invoice['work_order_id'] : null;

    $invoiceStmt->close();
    
    // التحقق من المبلغ
    if ($amount <= 0) {
        throw new Exception("المبلغ يجب أن يكون أكبر من صفر");
    }
    
    if ($amount > $invoice['remaining_amount']) {
        throw new Exception("المبلغ يتجاوز المتبقي للفاتورة. المتبقي: " . $invoice['remaining_amount']);
    }
    
    // جلب بيانات العميل
    $customer = getCustomerData($conn, $customerId);
    
    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        $walletBefore = (float)$customer['wallet'];
        $balanceBefore = (float)$customer['balance'];
        $walletDeduction = 0;
        $walletTransactionId = null;
        $walletAfter = $walletBefore;
        
        // 1. معالجة السحب من المحفظة إذا كانت طريقة الدفع wallet
        if ($paymentMethod === 'wallet') {
            if ($walletBefore < $amount) {
                throw new Exception("رصيد المحفظة غير كافي. المتوفر: $walletBefore, المطلوب: $amount");
            }
            
            $walletDeduction = $amount;
            $walletAfter = $walletBefore - $walletDeduction;
            
            // تحديث رصيد المحفظة
            updateCustomerWallet($conn, $customerId, -$walletDeduction);
            
            // ✅ إصلاح: وصف مفصل لحركة المحفظة
            $description = "سحب من المحفظة لسداد فاتورة #$invoiceId - مبلغ " . number_format($amount, 2) . " ج.م";
            
            // تسجيل حركة المحفظة
            $walletTransactionId = createWalletTransaction($conn, [
                'customer_id' => $customerId,
                'type' => 'invoice_payment',
                'amount' => -$walletDeduction,
                'description' => $description,
                'wallet_before' => $walletBefore,
                'wallet_after' => $walletAfter,
                'created_by' => $createdBy
            ]);
        }
        
        // 2. تحديث الفاتورة
        updateInvoice($conn, $invoiceId, $amount, $createdBy);
        
        // 3. إنشاء سجل الدفع
        $paymentMethodArabic = getPaymentMethodArabic($paymentMethod);
        $paymentDescription = "سداد فاتورة #$invoiceId - " . number_format($amount, 2) . " ج.م ($paymentMethodArabic)";
        
        $paymentId = createInvoicePayment($conn, [
            'invoice_id' => $invoiceId,
            'payment_amount' => $amount,
            'payment_method' => $paymentMethod,
            'notes' => $notes . " | " . $paymentDescription,
            'created_by' => $createdBy,
            'wallet_before' => $walletBefore,  // ✅ إصلاح: دائماً قيمة، ليست null
            'wallet_after' => $walletAfter,    // ✅ إصلاح: دائماً قيمة، ليست null
            'work_order_id' => $workOrderId
        ]);
        
        // 4. تحديث رصيد العميل
        updateCustomerBalance($conn, $customerId, -$amount);
        $balanceAfter = $balanceBefore - $amount;
        
        // 5. إنشاء سجل في customer_transactions
        $description = "سداد فاتورة #$invoiceId - " . number_format($amount, 2) . " ج.م ($paymentMethodArabic)";
        
        $transactionId = createCustomerTransaction($conn, [
            'customer_id' => $customerId,
            'transaction_type' => 'payment',
            'amount' => -$amount,
            'description' => $description,
            'invoice_id' => $invoiceId,
            'payment_id' => $paymentId,
            'wallet_transaction' => $walletTransactionId,
            'work_order_id' => $workOrderId,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'wallet_before' => $walletBefore,
            'wallet_after' => $walletAfter,
            'created_by' => $createdBy
        ]);
        
        $conn->commit();
        
        // جلب البيانات المحدثة
        $updatedCustomer = getCustomerData($conn, $customerId);
        $updatedInvoice = getInvoiceData($conn, $invoiceId);
        
        return [
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'type' => 'single',
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'amount_paid' => $amount,
            'payment_method' => $paymentMethod,
            'payment_method_arabic' => $paymentMethodArabic,
            'wallet_deduction' => $walletDeduction,
            'customer' => [
                'new_balance' => (float)$updatedCustomer['balance'],
                'new_wallet' => (float)$updatedCustomer['wallet'],
                'balance_change' => -$amount,
                'wallet_change' => -$walletDeduction
            ],
            'invoice' => [
                'new_paid_amount' => (float)$updatedInvoice['paid_amount'],
                'new_remaining_amount' => (float)$updatedInvoice['remaining_amount'],
                'is_fully_paid' => $updatedInvoice['remaining_amount'] == 0
            ]
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * معالجة سداد دفعة متعددة (Batch Payment) - مصححة بالكامل
 */
function processBatchPayment($conn, $input) {
    // التحقق من البيانات
    $required = ['customer_id', 'invoices', 'payment_methods'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("الحقل '$field' مطلوب للسداد المتعدد");
        }
    }
    
    $customerId = (int)$input['customer_id'];
    $invoices = $input['invoices'];
    $paymentMethods = $input['payment_methods'];
    $createdBy = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 1;
    $notes = $input['notes'] ?? '';
    $workOrderId = isset($input['work_order_id']) ? (int)$input['work_order_id'] : null;
    $strategy = $input['distribution_strategy'] ?? 'smallest_first';
    
    // جلب بيانات العميل
    $customer = getCustomerData($conn, $customerId);
    
    // التحقق من المبالغ
    $totalInvoices = array_sum(array_column($invoices, 'amount'));
    $totalPayment = array_sum(array_column($paymentMethods, 'amount'));
    
    if (abs($totalInvoices - $totalPayment) > 0.01) {
        throw new Exception("مجموع الفواتير لا يساوي مجموع طرق الدفع. الفواتير: $totalInvoices, الدفع: $totalPayment");
    }
    
    // حساب التوزيع
    $distribution = calculateDistribution($invoices, $paymentMethods, $strategy);
    
    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        $walletBefore = (float)$customer['wallet'];
        $balanceBefore = (float)$customer['balance'];
        $currentWallet = $walletBefore;  // ✅ لتتبع المحفظة لكل دفعة
        
        // حساب إجمالي السحب من المحفظة
        $walletDeduction = 0;
        foreach ($paymentMethods as $method) {
            if ($method['method'] === 'wallet') {
                $walletDeduction += $method['amount'];
            }
        }
        
        $walletTransactionId = null;
        
        // 1. معالجة السحب من المحفظة إذا كان موجوداً
        if ($walletDeduction > 0) {
            if ($walletBefore < $walletDeduction) {
                throw new Exception("رصيد المحفظة غير كافي. المتوفر: $walletBefore, المطلوب: $walletDeduction");
            }
            
            $walletAfter = $walletBefore - $walletDeduction;
            
            // تحديث رصيد المحفظة
            updateCustomerWallet($conn, $customerId, -$walletDeduction);
            
            // ✅ إصلاح: وصف مفصل لحركة المحفظة مع أرقام الفواتير
            $walletInvoiceIds = [];
            $walletInvoiceAmounts = [];
            
            foreach ($distribution as $item) {
                foreach ($item['allocations'] as $allocation) {
                    if ($allocation['method'] === 'wallet') {
                        $walletInvoiceIds[] = $item['invoice_id'];
                        $walletInvoiceAmounts[] = number_format($allocation['amount'], 2);
                        break;
                    }
                }
            }
            
            $description = "سحب من المحفظة لسداد ";
            if (!empty($walletInvoiceIds)) {
                $description .= "من " . count($walletInvoiceIds) . " فواتير";
                if (count($walletInvoiceIds) <= 5) { // إذا كانت قليلة، عرض الأرقام
                    $description .= " (#" . implode(', #', $walletInvoiceIds) . ")";
                }
            } else {
                $description .= count($distribution) . " فواتير";
            }
            $description .= " - مبلغ " . number_format($walletDeduction, 2) . " ج.م";
            
            // تسجيل حركة المحفظة
            $walletTransactionId = createWalletTransaction($conn, [
                'customer_id' => $customerId,
                'type' => 'invoice_payment',
                'amount' => -$walletDeduction,
                'description' => $description,
                'wallet_before' => $walletBefore,
                'wallet_after' => $walletAfter,
                'created_by' => $createdBy
            ]);
        } else {
            $walletAfter = $walletBefore;
        }
        
        // 2. معالجة كل فاتورة
        $totalPaid = 0;
        $paymentIds = [];
        $invoiceSummaries = [];
        $invoiceDetailsForDescription = []; // ✅ لتخزين تفاصيل كل فاتورة
        
        foreach ($distribution as $item) {
            $invoiceId = $item['invoice_id'];
            $invoiceAmount = $item['total_amount'];
            
            // تحديث الفاتورة
            updateInvoice($conn, $invoiceId, $invoiceAmount, $createdBy);
            $totalPaid += $invoiceAmount;
            
            // تحديد طريقة الدفع
            $allocations = $item['allocations'];
            $paymentMethod = count($allocations) > 1 ? 'mixed' : $allocations[0]['method'];
            
            // ✅ إصلاح: حساب wallet_before و wallet_after لكل دفعة
            $walletInfo = calculateWalletForPayment($allocations, $currentWallet);
            
            // ✅ إصلاح: إنشاء وصف تفصيلي بالعربي لكل فاتورة
            $methodDetails = [];
            foreach ($allocations as $allocation) {
                $methodArabic = getPaymentMethodArabic($allocation['method']);
                $methodDetails[] = number_format($allocation['amount'], 2) . ' ج.م ' . $methodArabic;
            }
            
            $invoiceDescription = "فاتورة #$invoiceId: " . implode(' + ', $methodDetails);
            $invoiceSummaries[] = "#$invoiceId: " . number_format($invoiceAmount, 2) . " ج.م";
            
            // ✅ تخزين التفاصيل للوصف الرئيسي
            $invoiceDetailsForDescription[] = $invoiceDescription;
            
            // تحويل payment_method إلى عربي للـ notes
            $paymentMethodArabic = getPaymentMethodArabic($paymentMethod);
            
            // إنشاء سجل الدفع
            $paymentId = createInvoicePayment($conn, [
                'invoice_id' => $invoiceId,
                'payment_amount' => $invoiceAmount,
                'payment_method' => $paymentMethod,
                'notes' => $notes . " | " . $invoiceDescription,
                'created_by' => $createdBy,
                'wallet_before' => $walletInfo['wallet_before'],  // ✅ إصلاح: القيمة الصحيحة لكل دفعة
                'wallet_after' => $walletInfo['wallet_after'],    // ✅ إصلاح: القيمة الصحيحة لكل دفعة
                'work_order_id' => $workOrderId
            ]);
            
            $paymentIds[] = $paymentId;
        }
        
        // 3. تحديث رصيد العميل
        updateCustomerBalance($conn, $customerId, -$totalPaid);
        $balanceAfter = $balanceBefore - $totalPaid;
        
        // 4. إنشاء سجل شامل في customer_transactions
        // ✅ إصلاح: وصف مفصل يظهر كل فاتورة وطرق دفعها
        if (count($invoiceDetailsForDescription) <= 3) {
            // إذا كانت الفواتير قليلة، عرض تفاصيل كل واحدة
            $description = "سداد دفعة متعددة (" . implode('، ', $invoiceDetailsForDescription) . ")";
        } else {
            // إذا كانت كثيرة، عرض ملخص
            $description = "سداد " . count($distribution) . " فواتير (مجموع: " . number_format($totalPaid, 2) . " ج.م)";
            
            // ✅ إضافة تفاصيل طرق الدفع بالعربي
            $paymentMethodDetails = [];
            foreach ($paymentMethods as $method) {
                $methodArabic = getPaymentMethodArabic($method['method']);
                $paymentMethodDetails[] = number_format($method['amount'], 2) . ' ج.م ' . $methodArabic;
            }
            $description .= " | طرق الدفع: " . implode(' + ', $paymentMethodDetails);
            
            // ✅ إضافة ملخص الفواتير إذا كانت هناك محفظة
            if ($walletDeduction > 0) {
                $description .= " | منها " . number_format($walletDeduction, 2) . " ج.م من المحفظة";
            }
        }
        
        // ✅ التأكد من أن الوصف لا يتجاوز 255 حرف (حدود قاعدة البيانات)
        if (strlen($description) > 255) {
            $description = substr($description, 0, 252) . '...';
        }
        
        $transactionId = createCustomerTransaction($conn, [
            'customer_id' => $customerId,
            'transaction_type' => 'payment',
            'amount' => -$totalPaid,
            'description' => $description,
            'invoice_id' => null,
            'payment_id' => null,
            'wallet_transaction' => $walletTransactionId,
            'work_order_id' => $workOrderId,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'wallet_before' => $walletBefore,
            'wallet_after' => $walletAfter,
            'created_by' => $createdBy
        ]);
        
        $conn->commit();
        
        // جلب البيانات المحدثة
        $updatedCustomer = getCustomerData($conn, $customerId);
        
        return [
            'transaction_id' => $transactionId,
            'payment_ids' => $paymentIds,
            'type' => 'batch',
            'customer_id' => $customerId,
            'total_paid' => $totalPaid,
            'invoices_count' => count($distribution),
            'wallet_deduction' => $walletDeduction,
            'customer' => [
                'new_balance' => (float)$updatedCustomer['balance'],
                'new_wallet' => (float)$updatedCustomer['wallet'],
                'balance_change' => -$totalPaid,
                'wallet_change' => -$walletDeduction
            ],
            'invoices_summary' => $invoiceSummaries,
            'payment_methods_summary' => $paymentMethods,
            'description' => $description,
            'distribution' => $distribution
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * معالجة سداد فاتورة واحدة بطرق متعددة (جديدة) - مصححة
 */
function processMixedSingleInvoice($conn, $input) {
    // التحقق من البيانات
    $required = ['customer_id', 'invoice_id', 'payment_methods', 'total_amount'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("الحقل '$field' مطلوب للسداد المختلط");
        }
    }
    
    $customerId = (int)$input['customer_id'];
    $invoiceId = (int)$input['invoice_id'];
    $paymentMethods = $input['payment_methods'];
    $totalAmount = (float)$input['total_amount'];
    $createdBy = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 1;
    $notes = $input['notes'] ?? '';
    $workOrderId = isset($input['work_order_id']) ? (int)$input['work_order_id'] : null;
    
    // التحقق من وجود الفاتورة
    $invoice = getInvoiceData($conn, $invoiceId);
    
    // التحقق من المبلغ
    if ($totalAmount <= 0) {
        throw new Exception("المبلغ يجب أن يكون أكبر من صفر");
    }
    
    if ($totalAmount > $invoice['remaining_amount']) {
        throw new Exception("المبلغ يتجاوز المتبقي للفاتورة. المتبقي: " . $invoice['remaining_amount']);
    }
    
    // التحقق من مجموع طرق الدفع
    $sumPaymentMethods = array_sum(array_column($paymentMethods, 'amount'));
    if (abs($totalAmount - $sumPaymentMethods) > 0.01) {
        throw new Exception("مجموع طرق الدفع لا يساوي المبلغ الكلي");
    }
    
    // جلب بيانات العميل
    $customer = getCustomerData($conn, $customerId);
    
    // بدء المعاملة
    $conn->begin_transaction();
    
    try {
        $walletBefore = (float)$customer['wallet'];
        $balanceBefore = (float)$customer['balance'];
        $walletDeduction = 0;
        $walletTransactionId = null;
        
        // 1. حساب إجمالي السحب من المحفظة
        foreach ($paymentMethods as $method) {
            if ($method['method'] === 'wallet') {
                $walletDeduction += $method['amount'];
            }
        }
        
        $walletAfter = $walletBefore - $walletDeduction;
        
        // التحقق من رصيد المحفظة إذا كان هناك سحب
        if ($walletDeduction > 0 && $walletBefore < $walletDeduction) {
            throw new Exception("رصيد المحفظة غير كافي. المتوفر: $walletBefore, المطلوب: $walletDeduction");
        }
        
        // تحديث رصيد المحفظة إذا كان هناك سحب
        if ($walletDeduction > 0) {
            updateCustomerWallet($conn, $customerId, -$walletDeduction);
            
            // ✅ إصلاح: وصف مفصل لحركة المحفظة
            $description = "سحب من المحفظة لسداد جزء من فاتورة #$invoiceId - مبلغ " . 
                          number_format($walletDeduction, 2) . " ج.م";
            
            // تسجيل حركة المحفظة
            $walletTransactionId = createWalletTransaction($conn, [
                'customer_id' => $customerId,
                'type' => 'invoice_payment',
                'amount' => -$walletDeduction,
                'description' => $description,
                'wallet_before' => $walletBefore,
                'wallet_after' => $walletAfter,
                'created_by' => $createdBy
            ]);
        }
        
        // 2. تحديث الفاتورة
        updateInvoice($conn, $invoiceId, $totalAmount, $createdBy);
        
        // 3. إنشاء وصف تفصيلي للدفع
        $methodDetailsArabic = [];
        $cashAmount = 0;
        $cardAmount = 0;
        $walletAmount = 0;
        
        foreach ($paymentMethods as $method) {
            $methodArabic = getPaymentMethodArabic($method['method']);
            $amount = number_format($method['amount'], 2);
            
            if ($method['method'] === 'wallet') {
                $methodDetailsArabic[] = "$amount ج.م محفظة";
                $walletAmount = $method['amount'];
            } elseif ($method['method'] === 'cash') {
                $methodDetailsArabic[] = "$amount ج.م نقدي";
                $cashAmount = $method['amount'];
            } elseif ($method['method'] === 'card') {
                $methodDetailsArabic[] = "$amount ج.م بطاقة";
                $cardAmount = $method['amount'];
            } else {
                $methodDetailsArabic[] = "$amount ج.م $methodArabic";
            }
        }
        
        $paymentDescription = "فاتورة #$invoiceId: " . implode(' + ', $methodDetailsArabic);
        
        // ✅ إصلاح: تحديد قيم wallet_before و wallet_after بشكل صحيح
        $paymentWalletBefore = $walletBefore;
        $paymentWalletAfter = $walletAfter;
        
        // إذا لم يكن هناك سحب من المحفظة، تكون القيم متساوية
        if ($walletDeduction == 0) {
            $paymentWalletBefore = $walletBefore;
            $paymentWalletAfter = $walletBefore; // نفس القيمة
        }
        
        // 4. إنشاء سجل الدفع
        $paymentId = createInvoicePayment($conn, [
            'invoice_id' => $invoiceId,
            'payment_amount' => $totalAmount,
            'payment_method' => 'mixed',
            'notes' => $notes . " | " . $paymentDescription,
            'created_by' => $createdBy,
            'wallet_before' => $paymentWalletBefore,  // ✅ إصلاح: ليست null
            'wallet_after' => $paymentWalletAfter,    // ✅ إصلاح: ليست null
            'work_order_id' => $workOrderId
        ]);
        
        // 5. تحديث رصيد العميل
        updateCustomerBalance($conn, $customerId, -$totalAmount);
        $balanceAfter = $balanceBefore - $totalAmount;
        
        // // 6. إنشاء سجل في customer_transactions
        // $description = "سداد فاتورة #$invoiceId - " . number_format($totalAmount, 2) . " ج.م (مختلط: " . 
        //               implode(' + ', array_column($methodDetailsArabic, 'ج.م')) . ")";

                      $description = "سداد " . $paymentDescription;

        
        $transactionId = createCustomerTransaction($conn, [
            'customer_id' => $customerId,
            'transaction_type' => 'payment',
            'amount' => -$totalAmount,
            'description' => $description,
            'invoice_id' => $invoiceId,
            'payment_id' => $paymentId,
            'wallet_transaction' => $walletTransactionId,
            'work_order_id' => $workOrderId,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'wallet_before' => $walletBefore,
            'wallet_after' => $walletAfter,
            'created_by' => $createdBy
        ]);
        
        $conn->commit();
        
        // جلب البيانات المحدثة
        $updatedCustomer = getCustomerData($conn, $customerId);
        $updatedInvoice = getInvoiceData($conn, $invoiceId);
        
        return [
            'transaction_id' => $transactionId,
            'payment_id' => $paymentId,
            'type' => 'mixed_single',
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'amount_paid' => $totalAmount,
            'payment_methods' => $paymentMethods,
            'payment_description' => $paymentDescription,
            'wallet_deduction' => $walletDeduction,
            'customer' => [
                'new_balance' => (float)$updatedCustomer['balance'],
                'new_wallet' => (float)$updatedCustomer['wallet'],
                'balance_change' => -$totalAmount,
                'wallet_change' => -$walletDeduction
            ],
            'invoice' => [
                'new_paid_amount' => (float)$updatedInvoice['paid_amount'],
                'new_remaining_amount' => (float)$updatedInvoice['remaining_amount'],
                'is_fully_paid' => $updatedInvoice['remaining_amount'] == 0
            ]
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// ==============================================
// المعالجة الرئيسية
// ==============================================

try {
    // قراءة البيانات
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input && !empty($_POST)) {
        $input = $_POST;
    }
    
    if (empty($input)) {
        throw new Exception('لم يتم إرسال أي بيانات');
    }
    
    // تحديد نوع السداد
    $paymentType = 'single';
    
   // تحديد نوع السداد (منطقي وصريح)
if (
    isset($input['invoice_id']) &&
    isset($input['payment_methods']) &&
    is_array($input['payment_methods']) &&
    count($input['payment_methods']) > 1
) {
    // فاتورة واحدة + طرق متعددة
    $paymentType = 'mixed_single';

} elseif (
    isset($input['invoices']) &&
    is_array($input['invoices']) &&
    count($input['invoices']) > 1
) {
    // عدة فواتير
    $paymentType = 'batch';

} elseif (isset($input['invoice_id'])) {
    // فاتورة واحدة + طريقة واحدة
    $paymentType = 'single';

} else {
    throw new Exception('يجب تحديد بيانات السداد بشكل صحيح');
}

    
    // معالجة حسب النوع
    switch ($paymentType) {
        case 'single':
            $result = processSinglePayment($conn, $input);
            break;
        case 'batch':
            $result = processBatchPayment($conn, $input);
            break;
        case 'mixed_single':
            $result = processMixedSingleInvoice($conn, $input);
            break;
        default:
            throw new Exception('نوع السداد غير معروف');
    }
    
    // الرد الناجح
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'تم السداد بنجاح',
        'payment_type' => $paymentType,
        'data' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>