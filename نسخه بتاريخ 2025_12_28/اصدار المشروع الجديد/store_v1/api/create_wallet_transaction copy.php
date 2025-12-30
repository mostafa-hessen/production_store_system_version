<?php
// create_wallet_transaction.php (modified, timezone + transaction_date fixes)

header('Content-Type: application/json; charset=utf-8');
    require_once dirname(__DIR__) . '/config.php';


// تمكين CORS للتعامل مع الطلبات من النطاقات المختلفة
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// التعامل مع طلبات OPTIONS (لـ CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// تأكد من ضبط المنطقة الزمنية إلى Africa/Cairo لتفادي فارق الساعتين
date_default_timezone_set('Africa/Cairo');

// بدء الجلسة (مهم للحصول على user id من الجلسة عند الحاجة)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Parse incoming transaction_date string into MySQL DATETIME (Y-m-d H:i:s) in Cairo timezone.
 * Accepts formats: 'Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d', ISO8601 etc.
 * Returns formatted string or null.
 */
function parseTransactionDate($dateStr) {
    if (empty($dateStr)) return null;

    $dateStr = trim($dateStr);

    // Try common formats
    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $dateStr, new DateTimeZone('Africa/Cairo'));
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    // Try generic parser (handles ISO8601 and timezone-aware strings)
    try {
        $dt = new DateTime($dateStr);
        // convert to Cairo
        $dt->setTimezone(new DateTimeZone('Africa/Cairo'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log('parseTransactionDate error: ' . $e->getMessage());
        return null;
    }
}

/**
 * تسجيل حركة محفظة في جدول wallet_transactions
 */
function recordWalletTransaction($conn, $transactionData) {
    $customerId = (int)$transactionData['customer_id'];
    $type = $conn->real_escape_string($transactionData['type']);
    $amount = (float)$transactionData['amount'];
    $description = isset($transactionData['description']) ? $conn->real_escape_string($transactionData['description']) : '';
    $walletBefore = array_key_exists('wallet_before', $transactionData) && !is_null($transactionData['wallet_before']) ? (float)$transactionData['wallet_before'] : null;
    $walletAfter = array_key_exists('wallet_after', $transactionData) && !is_null($transactionData['wallet_after']) ? (float)$transactionData['wallet_after'] : null;

    // transaction_date should be passed in transactionData (already parsed). If null, use current time.
    $transactionDate = array_key_exists('transaction_date', $transactionData) && !empty($transactionData['transaction_date'])
        ? $transactionData['transaction_date']
        : date('Y-m-d H:i:s');

    $createdBy = (int)$transactionData['created_by'];

    $sql = "INSERT INTO wallet_transactions (
        customer_id, `type`, amount, description, wallet_before, wallet_after, transaction_date, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("خطأ في إعداد استعلام wallet_transactions: " . $conn->error);
    }

    // bind_param requires variables; NULL values are acceptable if variable === null
    $stmt->bind_param("isdsddsi",
        $customerId,
        $type,
        $amount,
        $description,
        $walletBefore,
        $walletAfter,
        $transactionDate,
        $createdBy
    );

    if ($stmt->execute()) {
        $transactionId = $stmt->insert_id;
        $stmt->close();
        return $transactionId;
    } else {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception("فشل في تسجيل الحركة في wallet_transactions: " . $error);
    }
}

/**
 * تسجيل سجل في customer_transactions مرتبط بـ wallet_transaction_id
 */
function recordCustomerTransaction($conn, $data) {
    $sql = "INSERT INTO customer_transactions (
        customer_id, transaction_type, amount, description,
        invoice_id, payment_id, return_id, wallet_transaction_id, work_order_id,
        balance_before, balance_after, wallet_before, wallet_after,
        transaction_date, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("خطأ في إعداد استعلام customer_transactions: " . $conn->error);
    }

    // قيم الإدخال
    $customerId = (int)$data['customer_id'];
    $transactionType = (string)$data['transaction_type'];
    $amount = (float)$data['amount'];
    $description = isset($data['description']) ? (string)$data['description'] : '';

    // الحقول التي يمكن أن تكون NULL — نستخدم array_key_exists للتمييز بين عدم الوجود ووجود NULL
    $invoiceId = (array_key_exists('invoice_id', $data) && $data['invoice_id'] !== '' && $data['invoice_id'] !== null) ? (int)$data['invoice_id'] : null;
    $paymentId = (array_key_exists('payment_id', $data) && $data['payment_id'] !== '' && $data['payment_id'] !== null) ? (int)$data['payment_id'] : null;
    $returnId  = (array_key_exists('return_id', $data) && $data['return_id'] !== '' && $data['return_id'] !== null) ? (int)$data['return_id'] : null;
    $walletTxId = (array_key_exists('wallet_transaction_id', $data) && $data['wallet_transaction_id'] !== '' && $data['wallet_transaction_id'] !== null) ? (int)$data['wallet_transaction_id'] : null;
    $workOrderId = (array_key_exists('work_order_id', $data) && $data['work_order_id'] !== '' && $data['work_order_id'] !== null) ? (int)$data['work_order_id'] : null;

    // الحقول الرقمية — نُتيح NULL بدل 0.00 لتجنب تخزين أصفار افتراضية
    $balanceBefore = array_key_exists('balance_before', $data) ? (is_null($data['balance_before']) ? null : (float)$data['balance_before']) : null;
    $balanceAfter  = array_key_exists('balance_after', $data)  ? (is_null($data['balance_after'])  ? null : (float)$data['balance_after'])  : null;
    $walletBefore  = array_key_exists('wallet_before', $data)  ? (is_null($data['wallet_before'])  ? null : (float)$data['wallet_before'])  : null;
    $walletAfter   = array_key_exists('wallet_after', $data)   ? (is_null($data['wallet_after'])   ? null : (float)$data['wallet_after'])   : null;

    $transactionDate = isset($data['transaction_date']) && $data['transaction_date']
        ? $data['transaction_date']
        : date('Y-m-d H:i:s');
    $createdBy = (int)$data['created_by'];

    // إعداد سلسلة الأنواع — حتى لو كانت بعض القيم NULL نستخدم نفس الأنواع
    $types = "isds"; // customer_id, transaction_type, amount, description
    $types .= "iii"; // invoice_id, payment_id, return_id
    $types .= "i";   // wallet_transaction_id
    $types .= "i";   // work_order_id
    $types .= "dddd"; // balance_before, balance_after, wallet_before, wallet_after
    $types .= "s";    // transaction_date
    $types .= "i";    // created_by

    // bind_param يتطلب متغيرات — NULL مقبولة إذا كانت المتغيرات === null
    $stmt->bind_param(
        $types,
        $customerId,
        $transactionType,
        $amount,
        $description,
        $invoiceId,
        $paymentId,
        $returnId,
        $walletTxId,
        $workOrderId,
        $balanceBefore,
        $balanceAfter,
        $walletBefore,
        $walletAfter,
        $transactionDate,
        $createdBy
    );

    if ($stmt->execute()) {
        $insertId = $stmt->insert_id;
        $stmt->close();
        return $insertId;
    } else {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception("فشل في تسجيل customer_transactions: " . $err);
    }
}

/**
 * تحديث رصيد محفظة العميل في جدول customers
 */
function updateCustomerWalletBalance($conn, $customerId, $amount) {
    $checkStmt = $conn->prepare("SELECT id, wallet, balance FROM customers WHERE id = ?");
    $checkStmt->bind_param("i", $customerId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        $checkStmt->close();
        throw new Exception("العميل غير موجود");
    }

    $customer = $result->fetch_assoc();
    $currentWallet = (float)$customer['wallet'];
    $newWallet = $currentWallet + $amount;

    $currentBalance = isset($customer['balance']) ? (float)$customer['balance'] : null;

    $checkStmt->close();

    $updateStmt = $conn->prepare("UPDATE customers SET wallet = ? WHERE id = ?");
    $updateStmt->bind_param("di", $newWallet, $customerId);

    if ($updateStmt->execute()) {
        $updateStmt->close();
        return [
            'wallet_before' => $currentWallet,
            'wallet_after' => $newWallet,
            'amount' => $amount,
            'balance_before' => $currentBalance,
            'balance_after' => $currentBalance // بشكل افتراضي لا نغير balance هنا
        ];
    } else {
        $error = $updateStmt->error;
        $updateStmt->close();
        throw new Exception("فشل تحديث رصيد المحفظة: " . $error);
    }
}

/**
 * Validation and helpers
 */
function validateWalletTransactionData($data) {
    $errors = [];

    if (empty($data['customer_id'])) {
        $errors[] = 'معرف العميل مطلوب';
    } elseif (!is_numeric($data['customer_id'])) {
        $errors[] = 'معرف العميل يجب أن يكون رقماً';
    }

    $validTypes = ['deposit', 'withdraw'];
    if (empty($data['type'])) {
        $errors[] = 'نوع الحركة مطلوب';
    } elseif (!in_array($data['type'], $validTypes)) {
        $errors[] = 'نوع الحركة غير صحيح. الأنواع المسموحة: ' . implode(', ', $validTypes);
    }

    if (!isset($data['amount'])) {
        $errors[] = 'المبلغ مطلوب';
    } elseif (!is_numeric($data['amount'])) {
        $errors[] = 'المبلغ يجب أن يكون رقماً';
    } elseif ((float)$data['amount'] <= 0) {
        $errors[] = 'المبلغ يجب أن يكون أكبر من صفر';
    }

    return $errors;
}

function getWalletTransactionDescription($type, $amount, $data) {
    $amountFormatted = number_format(abs($amount), 2);
    $prefix = $amount >= 0 ? '+' : '';

    switch ($type) {
        case 'deposit':
            return "إيداع محفظة - مبلغ {$prefix}{$amountFormatted} ج.م";
        case 'withdraw':
            return "سحب من المحفظة - مبلغ {$amountFormatted} ج.م";
        default:
            return "حركة محفظة - مبلغ {$prefix}{$amountFormatted} ج.م";
    }
}

function formatWalletTransactionForResponse($transaction) {
    if (!$transaction) return null;

    $amount = (float)$transaction['amount'];
    $isPositive = $amount >= 0;

    $typeMap = ['deposit' => 'إيداع', 'withdraw' => 'سحب'];
    $badgeClassMap = ['deposit' => 'bg-success', 'withdraw' => 'bg-danger'];

    return [
        'id' => (int)$transaction['id'],
        'customer_id' => (int)$transaction['customer_id'],
        'type' => $transaction['type'],
        'type_text' => $typeMap[$transaction['type']] ?? $transaction['type'],
        'badge_class' => $badgeClassMap[$transaction['type']] ?? 'bg-secondary',
        'amount' => $amount,
        'formatted_amount' => ($isPositive ? '+' : '') . number_format(abs($amount), 2) . ' ج.م',
        'amount_sign' => $isPositive ? '+' : '-',
        'amount_class' => $isPositive ? 'text-success' : 'text-danger',
        'description' => $transaction['description'],
        'wallet_before' => is_null($transaction['wallet_before']) ? null : (float)$transaction['wallet_before'],
        'wallet_after' => is_null($transaction['wallet_after']) ? null : (float)$transaction['wallet_after'],
        // استخدم الحقل transaction_date من الجدول وليس created_at
        'transaction_date' => isset($transaction['transaction_date']) ? $transaction['transaction_date'] : date('Y-m-d H:i:s'),
        'created_by' => (int)$transaction['created_by'],
        'created_by_name' => isset($transaction['created_by_name']) ? $transaction['created_by_name'] : 'النظام',
        'created_at' => isset($transaction['transaction_date']) ? $transaction['transaction_date'] : date('Y-m-d H:i:s')
    ];
}

// ===== التنفيذ الرئيسي =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الطريقة غير مسموحة. استخدم POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

error_log("Received data: " . print_r($input, true));

if (empty($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'لا توجد بيانات مرفوعة'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $validationErrors = validateWalletTransactionData($input);
    if (!empty($validationErrors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'خطأ في التحقق من البيانات', 'errors' => $validationErrors], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $customerId = (int)$input['customer_id'];
    $type = $input['type'];
    $amount = (float)$input['amount'];

    // جلب بيانات العميل (wallet و balance إذا موجود)
    $checkCustomer = $conn->prepare("SELECT id, name, wallet, balance FROM customers WHERE id = ?");
    $checkCustomer->bind_param("i", $customerId);
    $checkCustomer->execute();
    $customerResult = $checkCustomer->get_result();

    if ($customerResult->num_rows === 0) {
        $checkCustomer->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'العميل غير موجود'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $customer = $customerResult->fetch_assoc();
    $checkCustomer->close();

    // التحقق من رصيد المحفظة للسحب
    if ($type == 'withdraw') {
        $currentWallet = (float)$customer['wallet'];
        if ($amount > $currentWallet) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'رصيد المحفظة غير كافي', 'current_wallet' => $currentWallet, 'required_amount' => $amount], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $amount = -abs($amount);
    }

    $currentWallet = (float)$customer['wallet'];
    $currentBalance = isset($customer['balance']) ? (float)$customer['balance'] : null;
    $newWallet = $currentWallet + $amount;

    // إذا كان هناك منطق يغير balance العام فعلّله هنا
    $newBalance = $currentBalance; // بشكل افتراضي لا يتغير

    $description = isset($input['description']) && !empty($input['description']) ? $input['description'] : getWalletTransactionDescription($type, $amount, $input);

    $createdBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['id']) ? (int)$_SESSION['id'] : 1);

    // معالجة التاريخ: استخدم الدالة parseTransactionDate لضمان تنسيق صحيح وبمنطقة القاهرة
    $transactionDate = null;
    if (isset($input['transaction_date']) && !empty($input['transaction_date'])) {
        $transactionDate = parseTransactionDate($input['transaction_date']);
    }

    // إذا لم يرسل المستخدم تاريخاً صالحاً، خذ الوقت الحالي
    if (empty($transactionDate)) {
        $transactionDate = date('Y-m-d H:i:s');
    }

    $transactionData = [
        'customer_id' => $customerId,
        'type' => $type,
        'amount' => $amount,
        'description' => $description,
        'wallet_before' => $currentWallet,
        'wallet_after' => $newWallet,
        'transaction_date' => $transactionDate,
        'created_by' => $createdBy
    ];

    // بدء المعاملة
    $conn->begin_transaction();

    try {
        // 1. تسجيل حركة المحفظة (سيستخدم transaction_date المرسل)
        $walletTxId = recordWalletTransaction($conn, $transactionData);

        // 2. تحديث رصيد المحفظة
        $walletUpdate = updateCustomerWalletBalance($conn, $customerId, $amount);

        // 3. تسجيل حركة العميل بنفس transaction_date
        $customerTxData = [
            'customer_id' => $customerId,
            'transaction_type' => $type,
            'amount' => $amount,
            'description' => $description,
            'wallet_transaction_id' => $walletTxId,
            'wallet_before' => $walletUpdate['wallet_before'],
            'wallet_after' => $walletUpdate['wallet_after'],
            'balance_before' => $walletUpdate['balance_before'],
            'balance_after' => $walletUpdate['balance_after'],
            'transaction_date' => $transactionDate,
            'created_by' => $createdBy
        ];

        $optionalFields = ['invoice_id', 'payment_id', 'return_id', 'work_order_id'];
        foreach ($optionalFields as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $customerTxData[$field] = $input[$field];
            }
        }

        $customerTxId = recordCustomerTransaction($conn, $customerTxData);

        // تأكيد المعاملة
        $conn->commit();

        // جلب بيانات الحركة المسجلة مع اسم المستخدم
        $stmt = $conn->prepare("SELECT wt.*, u.username as created_by_name FROM wallet_transactions wt LEFT JOIN users u ON wt.created_by = u.id WHERE wt.id = ?");
        $stmt->bind_param("i", $walletTxId);
        $stmt->execute();
        $result = $stmt->get_result();
        $newTransaction = $result->fetch_assoc();
        $stmt->close();

        $formattedTransaction = formatWalletTransactionForResponse($newTransaction);

        // إضافة بيانات العميل المحدثة
        $updatedCustomerStmt = $conn->prepare("SELECT wallet FROM customers WHERE id = ?");
        $updatedCustomerStmt->bind_param("i", $customerId);
        $updatedCustomerStmt->execute();
        $updatedCustomerResult = $updatedCustomerStmt->get_result();
        $updatedCustomer = $updatedCustomerResult->fetch_assoc();
        $updatedCustomerStmt->close();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'تم تسجيل حركة المحفظة بنجاح',
            'transaction' => $formattedTransaction,
            'transaction_id' => $walletTxId,
            'wallet_update' => $walletUpdate,
            'customer_transaction_id' => $customerTxId,
            'customer' => [
                'id' => $customerId,
                'name' => $customer['name'],
                'wallet' => (float)$updatedCustomer['wallet']
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في الخادم: ' . $e->getMessage(),
        'error_details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

if (isset($conn)) {
    $conn->close();
}

?>


