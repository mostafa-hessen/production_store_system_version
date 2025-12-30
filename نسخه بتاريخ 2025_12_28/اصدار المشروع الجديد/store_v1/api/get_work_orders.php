<?php
// get_work_orders.php
header('Content-Type: application/json; charset=utf-8');
    require_once dirname(__DIR__) . '/config.php';

try {
    // جمع الفلاتر
    $params = [];
    $paramTypes = "";
    $conditions = [];
    
    // فلتر حسب العميل (إذا تم تمريره)
    if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
        $conditions[] = "wo.customer_id = ?";
        $params[] = (int)$_GET['customer_id'];
        $paramTypes .= "i";
    }
    
    // باقي الفلاتر (البحث، الحالة، التاريخ) تبقى كما هي...
    // ... (نفس الكود السابق)
    
    // الاستعلام الأساسي
    $sql = "
        SELECT 
            wo.*,
            c.name as customer_name,
            c.mobile as customer_mobile,
            u.username as created_by_name,
            COUNT(DISTINCT io.id) as invoices_count
        FROM work_orders wo
        LEFT JOIN customers c ON wo.customer_id = c.id
        LEFT JOIN users u ON wo.created_by = u.id
        LEFT JOIN invoices_out io ON wo.id = io.work_order_id
    ";
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " GROUP BY wo.id ORDER BY wo.id DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $workOrders = [];
    
    while ($row = $result->fetch_assoc()) {
        $workOrders[] = [
            'id' => (int)$row['id'],
            'customer_id' => (int)$row['customer_id'],
            'customer_name' => $row['customer_name'],
            'title' => $row['title'],
            'description' => $row['description'],
            'status' => $row['status'],
            'status_text' => $row['status'] == 'pending' ? 'قيد التنفيذ' : 
                           ($row['status'] == 'in_progress' ? 'جاري العمل' : 
                           ($row['status'] == 'completed' ? 'مكتمل' : 'ملغي')),
            'start_date' => $row['start_date'],
            'total_invoice_amount' => (float)$row['total_invoice_amount'],
            'total_paid' => (float)$row['total_paid'],
            'total_remaining' => (float)$row['total_remaining'],
            'progress_percent' => $row['total_invoice_amount'] > 0 
                ? round(($row['total_paid'] / $row['total_invoice_amount']) * 100, 2) 
                : 0,
            'invoices_count' => (int)$row['invoices_count'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'work_orders' => $workOrders,
        'count' => count($workOrders)
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