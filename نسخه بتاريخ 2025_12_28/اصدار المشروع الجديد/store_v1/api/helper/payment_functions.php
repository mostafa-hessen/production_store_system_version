<?php
/**
 * دوال مساعدة لعمليات السداد مع استخدام session للـ created_by
 */

/**
 * جلب بيانات العميل
 */
function getCustomerData($conn, $customerId) {
    $stmt = $conn->prepare("SELECT id, name, wallet, balance FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("العميل غير موجود");
    }
    
    $customer = $result->fetch_assoc();
    $stmt->close();
    
    return $customer;
}

/**
 * جلب بيانات الفاتورة
 */
function getInvoiceData($conn, $invoiceId) {
    $stmt = $conn->prepare("
        SELECT id, total_after_discount, paid_amount, remaining_amount 
        FROM invoices_out 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("الفاتورة غير موجودة");
    }
    
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    return $invoice;
}

/**
 * تحديث الفاتورة
 */
function updateInvoice($conn, $invoiceId, $amount) {
    $updatedBy = $_SESSION['id'] ?? 5;

    $sql = "UPDATE invoices_out 
            SET paid_amount = paid_amount + ?, 
                remaining_amount = remaining_amount - ?,
                updated_at = NOW(),
                updated_by = ?
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ddii", $amount, $amount, $updatedBy, $invoiceId);
    
    if (!$stmt->execute()) {
        throw new Exception("فشل تحديث الفاتورة #$invoiceId: " . $stmt->error);
    }
    
    $stmt->close();
    return true;
}

/**
 * تحديث رصيد المحفظة
 */
function updateCustomerWallet($conn, $customerId, $amount) {
    $sql = "UPDATE customers SET wallet = wallet + ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $amount, $customerId);
    
    if (!$stmt->execute()) {
        throw new Exception("فشل تحديث رصيد المحفظة: " . $stmt->error);
    }
    
    $stmt->close();
    return true;
}

/**
 * تحديث رصيد العميل
 */
function updateCustomerBalance($conn, $customerId, $amount) {
    $sql = "UPDATE customers SET balance = balance + ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $amount, $customerId);
    
    if (!$stmt->execute()) {
        throw new Exception("فشل تحديث رصيد العميل: " . $stmt->error);
    }
    
    $stmt->close();
    return true;
}

/**
 * إنشاء سجل دفع
 */
function createInvoicePayment($conn, $data) {
    if (!isset($data['invoice_id'], $data['payment_amount'])) {
        throw new Exception("بيانات غير كافية لإنشاء سجل دفع");
    }
    
    $invoiceId = (int)$data['invoice_id'];
    $paymentAmount = (float)$data['payment_amount'];
    $paymentMethod = isset($data['payment_method']) ? $conn->real_escape_string($data['payment_method']) : 'cash';
    $notes = isset($data['notes']) ? $conn->real_escape_string($data['notes']) : '';
    $createdBy = $_SESSION['id'] ?? 5;
    $walletBefore = isset($data['wallet_before']) ? (float)$data['wallet_before'] : null;
    $walletAfter = isset($data['wallet_after']) ? (float)$data['wallet_after'] : null;
    $workOrderId = isset($data['work_order_id']) ? (int)$data['work_order_id'] : null;
    
    $sql = "INSERT INTO invoice_payments 
            (invoice_id, payment_amount, payment_method, notes,
             created_by, wallet_before, wallet_after, work_order_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("فشل إعداد استعلام الدفع: " . $conn->error);
    }
    
    $stmt->bind_param(
    "idsssddi", 
    $invoiceId,     // i
    $paymentAmount, // d
    $paymentMethod, // s
    $notes,         // s
    $createdBy,     // i
    $walletBefore,  // d
    $walletAfter,   // d
    $workOrderId    // i

    );
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("فشل إنشاء سجل الدفع: " . $stmt->error);
    }
    
    $paymentId = $stmt->insert_id;
    $stmt->close();
    
    return $paymentId;
}

/**
 * إنشاء حركة محفظة
 */
function createWalletTransaction($conn, $data) {
    $createdBy = $_SESSION['id'] ?? 5;

    $sql = "INSERT INTO wallet_transactions 
            (customer_id, type, amount, description, 
             wallet_before, wallet_after, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdsddi",
        $data['customer_id'],
        $data['type'],
        $data['amount'],
        $data['description'],
        $data['wallet_before'],
        $data['wallet_after'],
        $createdBy
    );
    
    if (!$stmt->execute()) {
        throw new Exception("فشل تسجيل حركة المحفظة: " . $stmt->error);
    }
    
    $walletTxId = $stmt->insert_id;
    $stmt->close();
    
    return $walletTxId;
}

/**
 * إنشاء سجل معاملة العميل
 */
function createCustomerTransaction($conn, $data) {
    $createdBy = $_SESSION['id'] ?? 5;

    $sql = "INSERT INTO customer_transactions 
            (customer_id, transaction_type, amount, description,
             invoice_id, payment_id, return_id, wallet_transaction_id, work_order_id,
             balance_before, balance_after, wallet_before, wallet_after,
             transaction_date, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("فشل إعداد استعلام العميل: " . $conn->error);
    }

    $stmt->bind_param(
        "isdsiiiiiddddii",  // تحقق من عدد الأحرف
        $data['customer_id'],
        $data['transaction_type'],
        $data['amount'],
        $data['description'],
        $data['invoice_id'],
        $data['payment_id'],
        $data['return_id'],          // يمكن أن يكون null
        $data['wallet_transaction_id'],
        $data['work_order_id'],
        $data['balance_before'],
        $data['balance_after'],
        $data['wallet_before'],
        $data['wallet_after'],
        $data['transaction_date'],   // أو استخدم CURDATE() في SQL بدلًا من bind_param
        $createdBy
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("فشل تسجيل معاملة العميل: " . $stmt->error);
    }

    $transactionId = $stmt->insert_id;
    $stmt->close();

    return $transactionId;
}


/**
 * حساب توزيع المدفوعات
 */
function calculateDistribution($invoices, $paymentMethods, $strategy = 'smallest_first') {
    if ($strategy === 'smallest_first') {
        usort($invoices, fn($a, $b) => $a['amount'] <=> $b['amount']);
    } elseif ($strategy === 'largest_first') {
        usort($invoices, fn($a, $b) => $b['amount'] <=> $a['amount']);
    }
    
    $distribution = [];
    $remainingMethods = $paymentMethods;
    
    foreach ($invoices as $invoice) {
        $invoiceAmount = $invoice['amount'];
        $allocations = [];
        
        foreach ($remainingMethods as $index => &$method) {
            if ($invoiceAmount <= 0) break;
            if ($method['amount'] <= 0) continue;
            
            $allocated = min($invoiceAmount, $method['amount']);
            
            $allocations[] = [
                'method' => $method['method'],
                'amount' => $allocated
            ];
            
            $invoiceAmount -= $allocated;
            $method['amount'] -= $allocated;
        }
        
        $distribution[] = [
            'invoice_id' => $invoice['id'],
            'total_amount' => $invoice['amount'],
            'allocations' => $allocations
        ];
    }
    
    return $distribution;
}
?>
