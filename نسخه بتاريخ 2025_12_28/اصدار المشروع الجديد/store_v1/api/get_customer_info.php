<?php
// get_customer_info.php
header('Content-Type: application/json');
// require_once __DIR__ . '../config.php';
    require_once dirname(__DIR__) . '/config.php';

// التحقق من وجود customer_id
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'customer_id is required and must be numeric'
    ]);
    exit;
}

// if (!$customer_id) {
//     // ربما نعيد توجيه المستخدم إلى صفحة إدارة العملاء
//     header("Location: manage_customers.php");
//     exit;
// }
$customerId = (int)$_GET['customer_id'];

try {
    // جلب بيانات العميل باستخدام MySQLi
    $sql = "SELECT id, name, mobile, city, address, notes, join_date, wallet, balance
            FROM customers
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();
    $stmt->close();

    if (!$customer) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found'
        ]);
        exit;
    }

    // إعادة البيانات
    echo json_encode([
        'success' => true,
        'data' => $customer
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}

$conn->close();
