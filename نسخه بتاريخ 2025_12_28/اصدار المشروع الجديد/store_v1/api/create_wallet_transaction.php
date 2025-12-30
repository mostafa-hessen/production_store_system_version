<?php
// create_wallet_transaction.php (full corrected version)
// UTF-8
header('Content-Type: application/json; charset=utf-8');
    require_once dirname(__DIR__) . '/config.php';


// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// timezone Cairo
date_default_timezone_set('Africa/Cairo');

// start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Parse incoming transaction_date into 'Y-m-d H:i:s' Cairo.
 */
function parseTransactionDate($dateStr) {
    if (empty($dateStr)) return null;
    $dateStr = trim($dateStr);
    $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $dateStr, new DateTimeZone('Africa/Cairo'));
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    try {
        $dt = new DateTime($dateStr); // may parse ISO8601
        $dt->setTimezone(new DateTimeZone('Africa/Cairo'));
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        error_log('parseTransactionDate error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Validation
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

/**
 * Format wallet transaction for response
 */
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
        'transaction_date' => isset($transaction['transaction_date']) ? $transaction['transaction_date'] : date('Y-m-d H:i:s'),
        'created_by' => isset($transaction['created_by']) ? (int)$transaction['created_by'] : null,
        'created_by_name' => isset($transaction['created_by_name']) ? $transaction['created_by_name'] : 'النظام',
        'created_at' => isset($transaction['created_at']) ? $transaction['created_at'] : (isset($transaction['transaction_date']) ? $transaction['transaction_date'] : date('Y-m-d H:i:s'))
    ];
}

/**
 * Format customer transaction for response
 */function getTransactionTypeText($type) {
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
function formatCustomerTransactionForResponse($ct) {
    $isPositive = (float)$ct['amount'] >= 0;

    $typeText = getTransactionTypeText($ct['transaction_type']);

    if (!$ct) return null;
    return [
        'id' => (int)$ct['id'],
        'customer_id' => (int)$ct['customer_id'],
        'transaction_type' => $ct['transaction_type'],
        'date' => $ct['date'] ?? ($ct['transaction_date'] ?? $row['created_at'] ?? null ? date('Y-m-d', strtotime($ct['transaction_date'] ?? $ct['created_at'] ?? null)) : null),
        'type_text' => $typeText,
        'amount_sign' => $isPositive ? '+' : '-',
        'amount_class' => $isPositive ? 'text-success' : 'text-danger',
        'badge_class' => getTransactionBadgeClass($ct['transaction_type']),
        
        'amount' => (float)$ct['amount'],
        'formatted_amount' => ((float)$ct['amount'] >= 0 ? '+' : '') . number_format(abs((float)$ct['amount']), 2) . ' ج.م',
        'description' => $ct['description'],
        'invoice_id' => isset($ct['invoice_id']) ? (int)$ct['invoice_id'] : null,
        'payment_id' => isset($ct['payment_id']) ? (int)$ct['payment_id'] : null,
        'return_id' => isset($ct['return_id']) ? (int)$ct['return_id'] : null,
        'wallet_transaction_id' => isset($ct['wallet_transaction_id']) ? (int)$ct['wallet_transaction_id'] : null,
        'work_order_id' => isset($ct['work_order_id']) ? (int)$ct['work_order_id'] : null,
        'wallet_before' => isset($ct['wallet_before']) ? (float)$ct['wallet_before'] : null,
        'wallet_after' => isset($ct['wallet_after']) ? (float)$ct['wallet_after'] : null,
        'balance_before' => isset($ct['balance_before']) ? (float)$ct['balance_before'] : null,
        'balance_after' => isset($ct['balance_after']) ? (float)$ct['balance_after'] : null,
        'transaction_date' => isset($ct['transaction_date']) ? $ct['transaction_date'] : date('Y-m-d H:i:s'),
        'created_by' => isset($ct['created_by']) ? (int)$ct['created_by'] : null,
        'created_by_name' => isset($ct['created_by_name']) ? $ct['created_by_name'] : 'النظام',
        'created_at' => isset($ct['created_at']) ? $ct['created_at'] : null
    ];
}

/**
 * Record wallet transaction
 */
function recordWalletTransaction($conn, $transactionData) {
    $customerId = (int)$transactionData['customer_id'];
    $type = $conn->real_escape_string($transactionData['type']);
    $amount = (float)$transactionData['amount'];
    $description = isset($transactionData['description']) ? $conn->real_escape_string($transactionData['description']) : '';
    $walletBefore = array_key_exists('wallet_before', $transactionData) ? $transactionData['wallet_before'] : null;
    $walletAfter  = array_key_exists('wallet_after', $transactionData) ? $transactionData['wallet_after'] : null;
    $transactionDate = $transactionData['transaction_date'] ?? date('Y-m-d H:i:s');
    $createdBy = isset($transactionData['created_by']) ? (int)$transactionData['created_by'] : null;

    $sql = "INSERT INTO wallet_transactions (
        customer_id, `type`, amount, description, wallet_before, wallet_after, transaction_date, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("خطأ في إعداد استعلام wallet_transactions: " . $conn->error);
    }

    // ensure variables exist for bind_param (NULL allowed)
    $wb = is_null($walletBefore) ? null : (float)$walletBefore;
    $wa = is_null($walletAfter) ? null : (float)$walletAfter;
    $td = $transactionDate;
    $cb = is_null($createdBy) ? null : (int)$createdBy;

    // types: i (customer_id), s (type), d (amount), s (description), d (wallet_before), d (wallet_after), s (transaction_date), i (created_by)
    $stmt->bind_param("isdsddsi",
        $customerId,
        $type,
        $amount,
        $description,
        $wb,
        $wa,
        $td,
        $cb
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
 * Record customer transaction
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

    // inputs
    $customerId = (int)$data['customer_id'];
    $transactionType = (string)$data['transaction_type'];
    $amount = (float)$data['amount'];
    $description = isset($data['description']) ? (string)$data['description'] : '';

    $invoiceId = (array_key_exists('invoice_id', $data) && $data['invoice_id'] !== '' && $data['invoice_id'] !== null) ? (int)$data['invoice_id'] : null;
    $paymentId = (array_key_exists('payment_id', $data) && $data['payment_id'] !== '' && $data['payment_id'] !== null) ? (int)$data['payment_id'] : null;
    $returnId  = (array_key_exists('return_id', $data) && $data['return_id'] !== '' && $data['return_id'] !== null) ? (int)$data['return_id'] : null;
    $walletTxId = (array_key_exists('wallet_transaction_id', $data) && $data['wallet_transaction_id'] !== '' && $data['wallet_transaction_id'] !== null) ? (int)$data['wallet_transaction_id'] : null;
    $workOrderId = (array_key_exists('work_order_id', $data) && $data['work_order_id'] !== '' && $data['work_order_id'] !== null) ? (int)$data['work_order_id'] : null;

    $balanceBefore = array_key_exists('balance_before', $data) ? (is_null($data['balance_before']) ? null : (float)$data['balance_before']) : null;
    $balanceAfter  = array_key_exists('balance_after', $data)  ? (is_null($data['balance_after'])  ? null : (float)$data['balance_after'])  : null;
    $walletBefore  = array_key_exists('wallet_before', $data)  ? (is_null($data['wallet_before'])  ? null : (float)$data['wallet_before'])  : null;
    $walletAfter   = array_key_exists('wallet_after', $data)   ? (is_null($data['wallet_after'])   ? null : (float)$data['wallet_after'])   : null;

    $transactionDate = isset($data['transaction_date']) && $data['transaction_date'] ? $data['transaction_date'] : date('Y-m-d H:i:s');
    $createdBy = isset($data['created_by']) ? (int)$data['created_by'] : null;

    // types string: i s d s i i i i i d d d d s i
    $types = "isdsiiiiiddddsi";

    // ensure variables for bind
    $inv = $invoiceId;
    $pay = $paymentId;
    $ret = $returnId;
    $wtx = $walletTxId;
    $work = $workOrderId;
    $bb = $balanceBefore;
    $ba = $balanceAfter;
    $wb = $walletBefore;
    $wa = $walletAfter;
    $td = $transactionDate;
    $cb = $createdBy;

    $stmt->bind_param(
        $types,
        $customerId,
        $transactionType,
        $amount,
        $description,
        $inv,
        $pay,
        $ret,
        $wtx,
        $work,
        $bb,
        $ba,
        $wb,
        $wa,
        $td,
        $cb
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
 * Update customer's wallet balance field
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
    $checkStmt->close();

    $currentWallet = (float)$customer['wallet'];
    $newWallet = $currentWallet + (float)$amount;
    $currentBalance = isset($customer['balance']) ? (float)$customer['balance'] : null;

    $updateStmt = $conn->prepare("UPDATE customers SET wallet = ? WHERE id = ?");
    $updateStmt->bind_param("di", $newWallet, $customerId);

    if ($updateStmt->execute()) {
        $updateStmt->close();
        return [
            'wallet_before' => $currentWallet,
            'wallet_after' => $newWallet,
            'amount' => $amount,
            'balance_before' => $currentBalance,
            'balance_after' => $currentBalance
        ];
    } else {
        $error = $updateStmt->error;
        $updateStmt->close();
        throw new Exception("فشل تحديث رصيد المحفظة: " . $error);
    }
}

/* ====== Main ====== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الطريقة غير مسموحة. استخدم POST'], JSON_UNESCAPED_UNICODE);
    exit;
}

// read input
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

    // fetch customer
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

    // withdrawal check
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
    $newBalance = $currentBalance; // default no change

    $description = isset($input['description']) && !empty($input['description']) ? $input['description'] : ( ($type=='deposit') ? ("إيداع محفظة - مبلغ " . number_format(abs($amount),2) . " ج.م") : ("سحب من المحفظة - مبلغ " . number_format(abs($amount),2) . " ج.م") );

    $createdBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['id']) ? (int)$_SESSION['id'] : null);

    // transaction_date parsing
    $transactionDate = null;
    if (isset($input['transaction_date']) && !empty($input['transaction_date'])) {
        $transactionDate = parseTransactionDate($input['transaction_date']);
    }
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

    // begin transaction
    $conn->begin_transaction();

    try {
        // 1. insert wallet transaction
        $walletTxId = recordWalletTransaction($conn, $transactionData);

        // 2. update customer wallet
        $walletUpdate = updateCustomerWalletBalance($conn, $customerId, $amount);

        // 3. create customer transaction
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

        // optional fields from input
        $optionalFields = ['invoice_id', 'payment_id', 'return_id', 'work_order_id'];
        foreach ($optionalFields as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $customerTxData[$field] = $input[$field];
            }
        }

        $customerTxId = recordCustomerTransaction($conn, $customerTxData);

        // commit
        $conn->commit();

        // fetch wallet transaction with created_by name
        $stmt = $conn->prepare("SELECT wt.*, u.username as created_by_name FROM wallet_transactions wt LEFT JOIN users u ON wt.created_by = u.id WHERE wt.id = ?");
        $stmt->bind_param("i", $walletTxId);
        $stmt->execute();
        $result = $stmt->get_result();
        $newTransaction = $result->fetch_assoc();
        $stmt->close();

        $formattedTransaction = formatWalletTransactionForResponse($newTransaction);

        // fetch customer transaction with created_by name
        $stmt2 = $conn->prepare("SELECT ct.*, u.username as created_by_name FROM customer_transactions ct LEFT JOIN users u ON ct.created_by = u.id WHERE ct.id = ?");
        $stmt2->bind_param("i", $customerTxId);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $newCustomerTx = $res2->fetch_assoc();
        $stmt2->close();

        $formattedCustomerTx = formatCustomerTransactionForResponse($newCustomerTx);

        // fetch updated customer wallet
        $updatedCustomerStmt = $conn->prepare("SELECT id, name, wallet FROM customers WHERE id = ?");
        $updatedCustomerStmt->bind_param("i", $customerId);
        $updatedCustomerStmt->execute();
        $updatedCustomerResult = $updatedCustomerStmt->get_result();
        $updatedCustomer = $updatedCustomerResult->fetch_assoc();
        $updatedCustomerStmt->close();

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'تم تسجيل حركة المحفظة بنجاح',
            'wallet_transaction' => $formattedTransaction,
            'customer_transaction' => $formattedCustomerTx,
            'transaction_id' => $walletTxId,
            'customer_transaction_id' => $customerTxId,
            'wallet_update' => $walletUpdate,
            'customer' => [
                'id' => $customerId,
                'name' => $customer['name'],
                'wallet' => isset($updatedCustomer['wallet']) ? (float)$updatedCustomer['wallet'] : $walletUpdate['wallet_after']
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

if (isset($conn)) $conn->close();
