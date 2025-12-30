<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

if (!isset($_GET['return_id']) || !is_numeric($_GET['return_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'return_id مطلوب']);
    exit;
}

$return_id = (int)$_GET['return_id'];

try {

    // جلب بيانات المرتجع مع اسم العميل، اسم المستخدم، ورقم الفاتورة
    $stmt = $conn->prepare("
        SELECT 
            r.id AS return_id,
            r.return_date,
            r.return_type,
            r.status,
            r.total_amount,
            r.reason,
            r.created_at,
            c.name AS customer_name,
            u.username AS created_by_name,
            r.invoice_id
        FROM returns r
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $return_id);
    $stmt->execute();
    $return = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // جلب بنود المرتجع
    $stmt = $conn->prepare("
        SELECT 
            ri.product_id,
            p.name AS product_name,
            ri.quantity,
            ri.return_price,
            ri.total_amount,
            ri.status
        FROM return_items ri
        JOIN products p ON ri.product_id = p.id
        WHERE ri.return_id = ?
        ORDER BY ri.id ASC
    ");
    $stmt->bind_param("i", $return_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'data' => [
            'items' => $items,
            'return' => $return
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في السيرفر',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
