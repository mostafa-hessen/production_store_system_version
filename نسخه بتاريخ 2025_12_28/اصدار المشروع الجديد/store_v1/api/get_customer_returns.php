<?php
// api/returns/get_customer_returns.php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// التحقق من جلسة المستخدم
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'غير مصرح بالدخول. يرجى تسجيل الدخول أولاً.'
    ]);
    exit;
}

// الحصول على customer_id من الطلب
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'customer_id مطلوب'
    ]);
    exit;
}

$customer_id = (int)$_GET['customer_id'];

// الحصول على المعلمات الاختيارية
$status = isset($_GET['status']) ? $_GET['status'] : null;
$return_type = isset($_GET['return_type']) ? $_GET['return_type'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

try {
    // بناء الاستعلام الأساسي
    $sql = "SELECT 
                r.*,
                r.id AS return_id,
                 i.id AS invoice_id,
                i.total_before_discount as invoice_total_before_discount,
                i.total_after_discount as invoice_total_after_discount,
                i.paid_amount as invoice_paid_amount,
                i.remaining_amount as invoice_remaining_amount,
                c.name as customer_name,
                c.mobile as customer_mobile,
                u.username as created_by_name,
                a.username as approved_by_name
            FROM returns r
            LEFT JOIN invoices_out i ON r.invoice_id = i.id
            LEFT JOIN customers c ON r.customer_id = c.id
            LEFT JOIN users u ON r.created_by = u.id
            LEFT JOIN users a ON r.approved_by = a.id
            WHERE r.customer_id = ?";
    
    $params = [$customer_id];
    $types = "i";
    
    // إضافة شروط التصفية الاختيارية
    if ($status !== null) {
        $sql .= " AND r.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($return_type !== null) {
        $sql .= " AND r.return_type = ?";
        $params[] = $return_type;
        $types .= "s";
    }
    
    if ($start_date !== null) {
        $sql .= " AND DATE(r.return_date) >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    
    if ($end_date !== null) {
        $sql .= " AND DATE(r.return_date) <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    
    // ترتيب النتائج
    $sql .= " ORDER BY r.return_date DESC, r.id DESC";
    
    // إضافة التحديد والصفحة
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // إعداد وتنفيذ الاستعلام
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $returns = [];
    while ($row = $result->fetch_assoc()) {
        // تنسيق التواريخ
        $row['return_date_formatted'] = date('Y-m-d H:i', strtotime($row['return_date']));
        $row['created_at_formatted'] = date('Y-m-d H:i', strtotime($row['created_at']));
        $row['approved_at_formatted'] = $row['approved_at'] ? date('Y-m-d H:i', strtotime($row['approved_at'])) : null;
        
        // جلب بنود المرتجع
        $row['items'] = getReturnItems($conn, $row['id']);
        
        // إضافة معلومات إضافية
        $row['invoice_info'] = [
            'id' => $row['invoice_id'],
            'invoice_id' => $row['invoice_id'],
            'total_before_discount' => $row['invoice_total_before_discount'],
            'total_after_discount' => $row['invoice_total_after_discount'],
            'paid_amount' => $row['invoice_paid_amount'],
            'remaining_amount' => $row['invoice_remaining_amount']
        ];
        
        $row['customer_info'] = [
            'id' => $row['customer_id'],
            'name' => $row['customer_name'],
            'mobile' => $row['customer_mobile']
        ];
        
        // تنظيف البيانات
        unset(
            $row['invoice_id'],
            $row['invoice_total_before_discount'],
            $row['invoice_total_after_discount'],
            $row['invoice_paid_amount'],
            $row['invoice_remaining_amount'],
            $row['customer_name'],
            $row['customer_mobile']
        );
        
        $returns[] = $row;
    }
    
    $stmt->close();
    
    // جلب العدد الإجمالي للسجلات
    $total_count = getTotalCustomerReturnsCount($conn, $customer_id, $status, $return_type, $start_date, $end_date);
    
    // جلب الإحصائيات
    $stats = getCustomerReturnsStats($conn, $customer_id);
    
    echo json_encode([
        'success' => true,
        'data' => $returns,
        'stats' => $stats,
        'pagination' => [
            'total' => $total_count,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total_count / $limit)
        ],
        'message' => 'تم جلب مرتجعات العميل بنجاح'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في السيرفر: ' . $e->getMessage()
    ]);
}

$conn->close();

/**
 * جلب بنود المرتجع
 */


/**
 * جلب العدد الإجمالي لمرتجعات العميل
 */
function getTotalCustomerReturnsCount($conn, $customer_id, $status, $return_type, $start_date, $end_date) {
    $sql = "SELECT COUNT(*) as total FROM returns WHERE customer_id = ?";
    
    $params = [$customer_id];
    $types = "i";
    
    if ($status !== null) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($return_type !== null) {
        $sql .= " AND return_type = ?";
        $params[] = $return_type;
        $types .= "s";
    }
    
    if ($start_date !== null) {
        $sql .= " AND DATE(return_date) >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    
    if ($end_date !== null) {
        $sql .= " AND DATE(return_date) <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['total'] ?? 0;
}

/**
 * جلب إحصائيات مرتجعات العميل
 */
function getCustomerReturnsStats($conn, $customer_id) {
    $stats = [
        'total_returns' => 0,
        'total_amount' => 0,
        'by_status' => [],
        'by_type' => [],
        'recent_returns' => []
    ];
    
    // إجمالي المرتجعات والمبلغ
    $sql = "SELECT 
                COUNT(*) as total_returns,
                COALESCE(SUM(total_amount), 0) as total_amount
            FROM returns 
            WHERE customer_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $stats['total_returns'] = $row['total_returns'] ?? 0;
    $stats['total_amount'] = $row['total_amount'] ?? 0;
    
    // المرتجعات حسب الحالة
    $sql = "SELECT 
                status,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as amount
            FROM returns 
            WHERE customer_id = ?
            GROUP BY status";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $stats['by_status'][] = $row;
    }
    $stmt->close();
    
    // المرتجعات حسب النوع
    $sql = "SELECT 
                return_type,
                COUNT(*) as count,
                COALESCE(SUM(total_amount), 0) as amount
            FROM returns 
            WHERE customer_id = ?
            GROUP BY return_type";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $stats['by_type'][] = $row;
    }
    $stmt->close();
    
    // المرتجعات الأخيرة (آخر 5)
    $sql = "SELECT 
                id,
                invoice_id,
                return_date,
                total_amount,
                status,
                return_type
            FROM returns 
            WHERE customer_id = ?
            ORDER BY return_date DESC
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['return_date_formatted'] = date('Y-m-d', strtotime($row['return_date']));
        $stats['recent_returns'][] = $row;
    }
    $stmt->close();
    
    return $stats;
}
/**
 * جلب كمية البنود المرتجعة فقط
 */
function getReturnItems($conn, $return_id) {
    $sql = "SELECT 
                ri.product_id,
                p.name as product_name,
                ri.quantity as returned_quantity
            FROM return_items ri
            LEFT JOIN products p ON ri.product_id = p.id
            WHERE ri.return_id = ?
            ORDER BY ri.id ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("خطأ في إعداد استعلام بنود المرتجع: " . $conn->error);
    }
    
    $stmt->bind_param("i", $return_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'returned_quantity' => (float)$row['returned_quantity']  // فقط الكمية المرتجعة
        ];
    }
    
    $stmt->close();
    return $items;
}

?>