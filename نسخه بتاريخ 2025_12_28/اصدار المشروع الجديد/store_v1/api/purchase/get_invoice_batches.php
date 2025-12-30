<?php
// api_get_invoice_batches.php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config.php';

// التحقق من CSRF للأمان
if (empty($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'طلب غير مصرح به']);
    exit;
}

// التحقق من وجود invoice_id
if (!isset($_GET['invoice_id']) || !is_numeric($_GET['invoice_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'invoice_id مطلوب']);
    exit;
}

$invoice_id = (int)$_GET['invoice_id'];

try {
    // جلب بيانات الفاتورة
    $sql_invoice = "SELECT pi.*, s.name AS supplier_name 
                    FROM purchase_invoices pi
                    JOIN suppliers s ON s.id = pi.supplier_id
                    WHERE pi.id = ? AND pi.status = 'fully_received'";
    
    $stmt = $conn->prepare($sql_invoice);
    if (!$stmt) throw new Exception($conn->error);
    
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الفاتورة غير موجودة أو لم تستلم بعد']);
        exit;
    }
    
    // جلب الدفعات المتاحة للإرجاع
    $sql_batches = "SELECT b.*, p.name AS product_name, p.product_code,
                           pii.quantity AS invoice_quantity,
                           pii.cost_price_per_unit AS invoice_cost,
                           (b.remaining - b.returned_quantity) AS available_for_return
                    FROM batches b
                    JOIN products p ON p.id = b.product_id
                    LEFT JOIN purchase_invoice_items pii ON pii.batch_id = b.id
                    WHERE b.source_invoice_id = ? 
                    AND b.status = 'active'
                    AND (b.remaining - b.returned_quantity) > 0
                    ORDER BY b.product_id, b.received_at";
    
    $stmt = $conn->prepare($sql_batches);
    if (!$stmt) throw new Exception($conn->error);
    
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $batches = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format numbers
        $row['qty'] = (float)$row['qty'];
        $row['remaining'] = (float)$row['remaining'];
        $row['available_for_return'] = (float)$row['available_for_return'];
        $row['returned_quantity'] = (float)$row['returned_quantity'];
        $row['unit_cost'] = (float)$row['unit_cost'];
        $row['sale_price'] = $row['sale_price'] ? (float)$row['sale_price'] : null;
        
        $batches[] = $row;
    }
    $stmt->close();
    
    // إرجاع النتيجة
    echo json_encode([
        'success' => true,
        'data' => [
            'invoice' => $invoice,
            'batches' => $batches,
            'batches_count' => count($batches)
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>