<?php
// api_get_purchase_returns.php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config.php';

// فلترة
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

try {
    // بناء الاستعلام
    $sql = "SELECT pr.*, 
                   s.name AS supplier_name,
                   pi.supplier_invoice_number,
                   u.username AS creator_name,
                   COUNT(pri.id) AS items_count,
                   SUM(pri.quantity) AS items_qty,
                   SUM(pri.quantity * pri.unit_cost) AS items_value
            FROM purchase_returns pr
            JOIN suppliers s ON s.id = pr.supplier_id
            JOIN purchase_invoices pi ON pi.id = pr.purchase_invoice_id
            LEFT JOIN users u ON u.id = pr.created_by
            LEFT JOIN purchase_return_items pri ON pri.purchase_return_id = pr.id
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($supplier_id > 0) {
        $sql .= " AND pr.supplier_id = ?";
        $params[] = $supplier_id;
        $types .= 'i';
    }
    
    if ($invoice_id > 0) {
        $sql .= " AND pr.purchase_invoice_id = ?";
        $params[] = $invoice_id;
        $types .= 'i';
    }
    
    if (!empty($status)) {
        $sql .= " AND pr.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if (!empty($date_from)) {
        $sql .= " AND pr.return_date >= ?";
        $params[] = $date_from;
        $types .= 's';
    }
    
    if (!empty($date_to)) {
        $sql .= " AND pr.return_date <= ?";
        $params[] = $date_to;
        $types .= 's';
    }
    
    $sql .= " GROUP BY pr.id 
              ORDER BY pr.created_at DESC 
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception($conn->error);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $returns = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format data
        $row['total_quantity'] = (float)$row['total_quantity'];
        $row['total_value'] = (float)$row['total_value'];
        $row['items_count'] = (int)$row['items_count'];
        $row['items_qty'] = (float)$row['items_qty'];
        $row['items_value'] = (float)$row['items_value'];
        
        // Status labels
        $status_labels = [
            'pending' => 'قيد الانتظار',
            'approved' => 'معتمد',
            'completed' => 'مكتمل',
            'cancelled' => 'ملغى'
        ];
        
        $row['status_label'] = $status_labels[$row['status']] ?? $row['status'];
        
        $returns[] = $row;
    }
    
    $stmt->close();
    
    // جلب العدد الإجمالي
    $sql_count = "SELECT COUNT(DISTINCT pr.id) as total_count 
                  FROM purchase_returns pr 
                  WHERE 1=1";
    
    // نفس الشروط
    $conditions = [];
    $count_params = [];
    $count_types = '';
    
    if ($supplier_id > 0) {
        $conditions[] = "pr.supplier_id = ?";
        $count_params[] = $supplier_id;
        $count_types .= 'i';
    }
    
    // ... باقي الشروط
    
    if (!empty($conditions)) {
        $sql_count .= " AND " . implode(" AND ", $conditions);
    }
    
    $stmt_count = $conn->prepare($sql_count);
    if ($stmt_count) {
        if (!empty($count_params)) {
            $stmt_count->bind_param($count_types, ...$count_params);
        }
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        $total_count = $count_result->fetch_assoc()['total_count'] ?? 0;
        $stmt_count->close();
    } else {
        $total_count = count($returns);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $returns,
        'pagination' => [
            'total' => (int)$total_count,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + count($returns)) < $total_count
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