<?php
// get_work_order_details.php
header('Content-Type: application/json; charset=utf-8');
    require_once dirname(__DIR__) . '/config.php';

// التحقق من وجود work_order_id
if (!isset($_GET['work_order_id']) || !is_numeric($_GET['work_order_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'work_order_id مطلوب ويجب أن يكون رقماً'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$workOrderId = (int)$_GET['work_order_id'];

try {
    // جلب بيانات الشغلانة
  // جلب بيانات الشغلانة
$sql = "
    SELECT 
        wo.*,
        c.name AS customer_name,
        c.mobile AS customer_mobile,
        c.city AS customer_city,
        c.address AS customer_address,
        u.username AS created_by_name
    FROM work_orders wo
    LEFT JOIN customers c ON wo.customer_id = c.id
    LEFT JOIN users u ON wo.created_by = u.id
    
    WHERE wo.id = ?
";


    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
    }
    
    $stmt->bind_param("i", $workOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'الشغلانة غير موجودة'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $workOrder = $result->fetch_assoc();
    $stmt->close();
    
    // تنسيق بيانات الشغلانة
    $statusMap = [
        'pending' => 'قيد التنفيذ',
        'in_progress' => 'جاري العمل',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي'
    ];
    
    $badgeClassMap = [
        'pending' => 'bg-warning',
        'in_progress' => 'bg-info',
        'completed' => 'bg-success',
        'cancelled' => 'bg-danger'
    ];
    
    $formattedWorkOrder = [
        'id' => (int)$workOrder['id'],
        'customer_id' => (int)$workOrder['customer_id'],
        'customer_name' => $workOrder['customer_name'],
        'customer_mobile' => $workOrder['customer_mobile'],
        'customer_city' => $workOrder['customer_city'],
        'customer_address' => $workOrder['customer_address'],
        'title' => $workOrder['title'],
        'description' => $workOrder['description'],
        'status' => $workOrder['status'],
        'status_text' => $statusMap[$workOrder['status']] ?? $workOrder['status'],
        'status_badge' => $badgeClassMap[$workOrder['status']] ?? 'bg-secondary',
        'start_date' => $workOrder['start_date'],
        'notes' => $workOrder['notes'],
        'total_invoice_amount' => (float)$workOrder['total_invoice_amount'],
        'total_paid' => (float)$workOrder['total_paid'],
        'total_remaining' => (float)$workOrder['total_remaining'],
        'progress_percent' => $workOrder['total_invoice_amount'] > 0 
            ? round(($workOrder['total_paid'] / $workOrder['total_invoice_amount']) * 100, 2) 
            : 0,
        'created_by' => (int)$workOrder['created_by'],
        'created_by_name' => $workOrder['created_by_name'],
        'created_at' => $workOrder['created_at'],
        'updated_at' => $workOrder['updated_at']
    ];
    
    // جلب الفواتير المرتبطة إذا طلبناها
    $invoices = [];
    // if (!isset($_GET['exclude_invoices']) || $_GET['exclude_invoices'] != '1') {
        $invoicesSql = "
        SELECT 
            io.id,
            DATE(io.created_at) AS date,
            io.total_after_discount AS total,
            io.paid_amount AS paid,
            io.remaining_amount AS remaining,
            io.notes,
        FROM invoices_out io
        WHERE io.work_order_id = ?
        ORDER BY io.id DESC
    ";
        
        $stmt2 = $conn->prepare($invoicesSql);
        if ($stmt2) {
            $stmt2->bind_param("i", $workOrderId);
            $stmt2->execute();
            $invoicesResult = $stmt2->get_result();
            
            while ($invoice = $invoicesResult->fetch_assoc()) {
                $total = (float)$invoice['total'];
                $totalPayments = (float)$invoice['total_payments'];
                $remaining = $total - $totalPayments;
                
                // تحديد حالة الفاتورة
                $status = 'pending';
                if ($remaining <= 0) {
                    $status = 'paid';
                } elseif ($totalPayments > 0) {
                    $status = 'partial';
                }
                
                // جلب بنود الفاتورة إذا طلبناها
                $items = [];
                if (isset($_GET['include_invoice_items']) && $_GET['include_invoice_items'] == '1') {
                    $itemsSql = "SELECT * FROM invoice_items WHERE invoice_id = ?";
                    $stmt3 = $conn->prepare($itemsSql);
                    if ($stmt3) {
                        $stmt3->bind_param("i", $invoice['id']);
                        $stmt3->execute();
                        $itemsResult = $stmt3->get_result();
                        
                        while ($item = $itemsResult->fetch_assoc()) {
                            $items[] = [
                                'id' => (int)$item['id'],
                                'product_name' => $item['product_name'],
                                'quantity' => (float)$item['quantity'],
                                'price' => (float)$item['price'],
                                'total' => (float)$item['total'],
                                'notes' => $item['notes']
                            ];
                        }
                        $stmt3->close();
                    }
                }
                
                $invoices[] = [
                    'id' => (int)$invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'date' => $invoice['date'],
                    'total' => $total,
                    'paid' => $totalPayments,
                    'remaining' => $remaining,
                    'status' => $status,
                    'status_text' => $status == 'pending' ? 'مؤجل' : 
                                   ($status == 'partial' ? 'جزئي' : 'مسلم'),
                    'items' => $items
                ];
            }
            $stmt2->close();
        }
        
        $formattedWorkOrder['invoices'] = $invoices;
        $formattedWorkOrder['invoices_count'] = count($invoices);
    // }
    
    // إرجاع النتيجة
    echo json_encode([
        'success' => true,
        'work_order' => $formattedWorkOrder,
        'invoices_count' => count($invoices)
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