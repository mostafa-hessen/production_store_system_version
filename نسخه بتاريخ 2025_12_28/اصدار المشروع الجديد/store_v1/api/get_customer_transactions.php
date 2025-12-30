<?php
// get_customer_transactions.php
header('Content-Type: application/json; charset=utf-8');
    require_once dirname(__DIR__) . '/config.php';

// التحقق من وجود customer_id
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'customer_id مطلوب ويجب أن يكون رقماً'
    ]);
    exit;
}

$customerId = (int)$_GET['customer_id'];

// تعريف الدوال المساعدة أولاً
function getTransactionTypeText($type) {
    $typeMap = [
        'invoice' => 'فاتورة',
        'payment' => 'سداد',
        'return' => 'مرتجع',
        'deposit' => 'إيداع',
        'adjustment' => 'تسوية',
        'withdraw' => 'سحب'
    ];
    return $typeMap[$type] ?? $type;
}

function getTransactionBadgeClass($type) {
    $classMap = [
        'invoice' => 'bg-primary',
        'payment' => 'bg-success',
        'return' => 'bg-warning',
        'deposit' => 'bg-info',
        'adjustment' => 'bg-secondary',
        'withdraw' => 'bg-danger'
    ];
    return $classMap[$type] ?? 'bg-secondary';
}

function generateTransactionDescription($transaction) {
    $type = $transaction['transaction_type'];
    $amount = number_format(abs($transaction['amount']), 2);
    $invoiceNum = isset($transaction['invoice_number']) ? "#{$transaction['invoice_number']}" : "";
    
    switch ($type) {
        case 'invoice':
            return "فاتورة {$invoiceNum} - مبلغ {$amount} ج.م";
        case 'payment':
            return "سداد فاتورة {$invoiceNum} - مبلغ {$amount} ج.م";
        case 'return':
            $reason = isset($transaction['return_reason']) ? " ({$transaction['return_reason']})" : "";
            return "مرتجع فاتورة {$invoiceNum}{$reason} - مبلغ {$amount} ج.م";
        case 'deposit':
            return "إيداع محفظة - مبلغ {$amount} ج.م";
        case 'adjustment':
            return "تسوية رصيد - مبلغ {$amount} ج.م";
            case 'withdraw':
            return "سحب من المحفظة - مبلغ {$amount} ج.م";
        default:
            return "حركة مالية - مبلغ {$amount} ج.م";
    }
}

function formatTransactionRow($row) {
    $amount = (float)$row['amount'];
    $isPositive = $amount >= 0;
    
    $typeText = getTransactionTypeText($row['transaction_type']);
    $description = $row['description'];
    
    if (empty($description)) {
        $description = generateTransactionDescription($row);
    }

    // استخدم transaction_date إذا موجود وإلا fallback على created_at
    $transactionDatetime = $row['transaction_date'] ?? $row['created_at'] ?? null;

    $dateOnly = $row['date'] ?? ($transactionDatetime ? date('Y-m-d', strtotime($transactionDatetime)) : null);
    $timeOnly = $row['time'] ?? ($transactionDatetime ? date('H:i:s', strtotime($transactionDatetime)) : null);
    
    return [
        'id' => (int)$row['id'],
        // حقول التاريخ/الوقت المتعلقة بالمعاملة (إن وُجدت)
        'transaction_date' => $row['transaction_date'] ?? null, // datetime كما هو مخزن في DB (أو null)
        'transaction_datetime' => $transactionDatetime, // نفس الشيء (fallback)
        'date' => $dateOnly,
        'time' => $timeOnly,

        'type' => $row['transaction_type'],
        'type_text' => $typeText,
        'description' => $description,
        'amount' => $amount,
        'formatted_amount' => number_format(abs($amount), 2) . ' ج.م',
        'amount_sign' => $isPositive ? '+' : '-',
        'amount_class' => $isPositive ? 'text-success' : 'text-danger',
        'badge_class' => getTransactionBadgeClass($row['transaction_type']),
        
        // الأرصدة
        'balance_before' => (float)($row['balance_before'] ?? 0),
        'balance_after' => (float)($row['balance_after'] ?? 0),
        'wallet_before' => (float)($row['wallet_before'] ?? 0),
        'wallet_after' => (float)($row['wallet_after'] ?? 0),
        
        // بيانات مرجعية
        'invoice_id' => $row['invoice_id'],
        'invoice_number' => $row['invoice_number'] ?? null,
        'work_order_id' => $row['work_order_id'],
        'work_order_name' => $row['work_order_name'] ?? null,
        'return_reason' => $row['return_reason'] ?? null,
        
        // معلومات النظام
        'created_by' => $row['created_by_name'] ?? 'النظام',
        'created_at' => $row['created_at'] ?? null
    ];
}

function calculateTransactionsSummary($transactions) {
    $summary = [
        'total_count' => 0,
        'total_amount' => 0,
        'deposit_count' => 0,
        'deposit_amount' => 0,
        'payment_count' => 0,
        'payment_amount' => 0,
        'return_count' => 0,
        'return_amount' => 0,
        'invoice_count' => 0,
        'invoice_amount' => 0,
        'adjustment_count' => 0,
        'adjustment_amount' => 0
    ];
    
    foreach ($transactions as $transaction) {
        $amount = (float)$transaction['amount'];
        $type = $transaction['type'];
        
        $summary['total_count']++;
        $summary['total_amount'] += $amount;
        
        switch ($type) {
            case 'deposit':
                $summary['deposit_count']++;
                $summary['deposit_amount'] += $amount;
                break;
            case 'payment':
                $summary['payment_count']++;
                $summary['payment_amount'] += abs($amount);
                break;
            case 'return':
                $summary['return_count']++;
                $summary['return_amount'] += abs($amount);
                break;
            case 'invoice':
                $summary['invoice_count']++;
                $summary['invoice_amount'] += $amount;
                break;
            case 'adjustment':
                $summary['adjustment_count']++;
                $summary['adjustment_amount'] += $amount;
                break;
        }
    }
    
    // تنسيق المبالغ
    $summary['formatted_total_amount'] = number_format($summary['total_amount'], 2) . ' ج.م';
    $summary['formatted_deposit_amount'] = number_format($summary['deposit_amount'], 2) . ' ج.م';
    $summary['formatted_payment_amount'] = number_format($summary['payment_amount'], 2) . ' ج.م';
    $summary['formatted_return_amount'] = number_format($summary['return_amount'], 2) . ' ج.م';
    $summary['formatted_invoice_amount'] = number_format($summary['invoice_amount'], 2) . ' ج.م';
    
    return $summary;
}

try {
    // جلب بيانات العميل للتأكد من وجوده
    $checkCustomer = $conn->prepare("SELECT id FROM customers WHERE id = ?");
    $checkCustomer->bind_param("i", $customerId);
    $checkCustomer->execute();
    $customerExists = $checkCustomer->get_result();
    
    if ($customerExists->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'العميل غير موجود'
        ]);
        exit;
    }
    $checkCustomer->close();

    // جمع الفلاتر
    $params = [$customerId];
    $paramTypes = "i";
    $conditions = [];
    
    // فلتر النوع
    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $conditions[] = "ct.transaction_type = ?";
        $params[] = $_GET['type'];
        $paramTypes .= "s";
    }
    
    // فلتر التاريخ من (يستخدم transaction_date إذا متوفر)
    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
        $conditions[] = "DATE(COALESCE(ct.transaction_date, ct.created_at)) >= ?";
        $params[] = $_GET['date_from'];
        $paramTypes .= "s";
    }
    
    // فلتر التاريخ إلى (يستخدم transaction_date إذا متوفر)
    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $conditions[] = "DATE(COALESCE(ct.transaction_date, ct.created_at)) <= ?";
        $params[] = $_GET['date_to'];
        $paramTypes .= "s";
    }

    // فلتر تاريخ المعاملة باليوم (exact date) - parameter: transaction_date=YYYY-MM-DD
    if (isset($_GET['transaction_date']) && !empty($_GET['transaction_date'])) {
        $conditions[] = "DATE(ct.transaction_date) = ?";
        $params[] = $_GET['transaction_date'];
        $paramTypes .= "s";
    }

    // الاستعلام الأساسي (مبسط - بدون JOINs معقدة)
    $sql = "
        SELECT 
            ct.*,
            ct.transaction_date,
            DATE(COALESCE(ct.transaction_date, ct.created_at)) as date,
            TIME(COALESCE(ct.transaction_date, ct.created_at)) as time,
            u.username as created_by_name,
            i.id as invoice_number
        FROM customer_transactions ct
        LEFT JOIN users u ON ct.created_by = u.id
        LEFT JOIN invoices_out i ON ct.invoice_id = i.id
        WHERE ct.customer_id = ?
    ";
    
    // إضافة الشروط
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    // $sql .= " ORDER BY COALESCE(ct.transaction_date, ct.created_at) DESC";
    $sql .= " ORDER BY   ct.id DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
    }
    
    // ربط الباراميترات ديناميكياً
    if (count($params) > 1) {
        $stmt->bind_param($paramTypes, ...$params);
    } else {
        $stmt->bind_param("i", $customerId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = [];
    
    // تنسيق البيانات
    while ($row = $result->fetch_assoc()) {
        $transactions[] = formatTransactionRow($row);
    }
    $stmt->close();
    
    // إذا طلبنا summary (اختياري)
    $summary = [];
    if (isset($_GET['include_summary']) && $_GET['include_summary'] == '1') {
        $summary = calculateTransactionsSummary($transactions);
    }
    
    // إرجاع النتيجة
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'summary' => $summary,
        'count' => count($transactions)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conn->close();
?>
