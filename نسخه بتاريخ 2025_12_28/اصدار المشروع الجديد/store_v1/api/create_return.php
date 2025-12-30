<?php
// api/returns/create.php
header('Content-Type: application/json');
    require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/helper/return_functions.php';

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// التحقق من جلسة المستخدم
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح بالدخول. يرجى تسجيل الدخول أولاً.'
    ]);
    exit;
}

// قراءة البيانات المدخلة
$input = json_decode(file_get_contents('php://input'), true);

// تنفيذ عملية الإرجاع
$result = createReturn($conn, $input, $_SESSION['id'], $_SESSION['role']);

// إرجاع النتيجة
if ($result['success']) {
    echo json_encode([
        'success' => true,
        'return_id' => $result['return_id'],
        'total_amount' => $result['total_amount'],
        'message' => $result['message'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
}

$conn->close();
?>