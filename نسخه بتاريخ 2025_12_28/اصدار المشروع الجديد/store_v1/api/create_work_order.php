<?php
// create_work_order.php
header('Content-Type: application/json; charset=utf-8');
    require_once dirname(__DIR__) . '/config.php';

/**
 * التحقق من صحة البيانات المدخلة
 */
function validateWorkOrderData($data) {

    $errors = [];
    
    // التحقق من وجود العميل
    if (empty($data['customer_id'])) {
        $errors[] = 'معرف العميل مطلوب';
    } elseif (!is_numeric($data['customer_id'])) {
        $errors[] = 'معرف العميل يجب أن يكون رقماً';
    }
    
    // التحقق من العنوان
    if (empty($data['title'])) {
        $errors[] = 'عنوان الشغلانة مطلوب';
    } elseif (strlen($data['title']) > 255) {
        $errors[] = 'عنوان الشغلانة يجب ألا يتجاوز 255 حرفاً';
    }
    
    // التحقق من التاريخ
    if (empty($data['start_date'])) {
        $errors[] = 'تاريخ البدء مطلوب';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date'])) {
        $errors[] = 'صيغة التاريخ غير صحيحة (يجب أن تكون YYYY-MM-DD)';
    }
    
    // التحقق من الحالة
    if (!empty($data['status'])) {
        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($data['status'], $validStatuses)) {
            $errors[] = 'حالة الشغلانة غير صحيحة';
        }
    }
    
    return $errors;
}

/**
 * التحقق من وجود العميل
 */
function customerExists($customerId, $conn) {
    $stmt = $conn->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'الطريقة غير مسموحة. استخدم POST'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// الحصول على البيانات المدخلة
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

// التحقق من وجود البيانات الأساسية
if (empty($input)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'لا توجد بيانات مرفوعة'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // التحقق من صحة البيانات
    $validationErrors = validateWorkOrderData($input);
    if (!empty($validationErrors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'خطأ في التحقق من البيانات',
            'errors' => $validationErrors
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // التحقق من وجود العميل
    $customerId = (int)$input['customer_id'];
    if (!customerExists($customerId, $conn)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'العميل غير موجود'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // الحصول على معرف المستخدم الحالي (من الجلسة أو التوكن)
    // يمكنك تعديل هذا الجزء حسب نظام المصادقة الخاص بك
    $currentUserId = isset($_SESSION['id']) ? $_SESSION['id'] : 5;
    
    // إعداد البيانات للادخال
    $title = $conn->real_escape_string($input['title']);
    $description = isset($input['description']) ? $conn->real_escape_string($input['description']) : null;
    $status = isset($input['status']) ? $input['status'] : 'pending';
    $startDate = $input['start_date'];
    $notes = isset($input['notes']) ? $conn->real_escape_string($input['notes']) : null;
    
    // إعداد الاستعلام
    $sql = "INSERT INTO work_orders (
        customer_id, 
        title, 
        description, 
        status, 
        start_date, 
        notes, 
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("خطأ في إعداد الاستعلام: " . $conn->error);
    }
    
    $stmt->bind_param(
        "isssssi",
        $customerId,
        $title,
        $description,
        $status,
        $startDate,
        $notes,
        $currentUserId
    );
    
    if ($stmt->execute()) {
        $newWorkOrderId = $stmt->insert_id;
        $stmt->close();
        
        // جلب بيانات الشغلانة المضافة
        $stmt2 = $conn->prepare("
            SELECT wo.*, c.name as customer_name, u.username as created_by_name
            FROM work_orders wo
            LEFT JOIN customers c ON wo.customer_id = c.id
            LEFT JOIN users u ON wo.created_by = u.id
            WHERE wo.id = ?
        ");
        $stmt2->bind_param("i", $newWorkOrderId);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $newWorkOrder = $result->fetch_assoc();
        $stmt2->close();
        
        // تنسيق البيانات للإرجاع
        $formattedWorkOrder = [
            'id' => (int)$newWorkOrder['id'],
            'customer_id' => (int)$newWorkOrder['customer_id'],
            'customer_name' => $newWorkOrder['customer_name'],
            'title' => $newWorkOrder['title'],
            'description' => $newWorkOrder['description'],
            'status' => $newWorkOrder['status'],
            'status_text' => $newWorkOrder['status'] == 'pending' ? 'قيد التنفيذ' : 
                            ($newWorkOrder['status'] == 'in_progress' ? 'جاري العمل' : 
                            ($newWorkOrder['status'] == 'completed' ? 'مكتمل' : 'ملغي')),
            'start_date' => $newWorkOrder['start_date'],
            'notes' => $newWorkOrder['notes'],
            'total_invoice_amount' => (float)$newWorkOrder['total_invoice_amount'],
            'total_paid' => (float)$newWorkOrder['total_paid'],
            'total_remaining' => (float)$newWorkOrder['total_remaining'],
            'created_by' => (int)$newWorkOrder['created_by'],
            'created_by_name' => $newWorkOrder['created_by_name'],
            'created_at' => $newWorkOrder['created_at']
        ];
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'تم إنشاء الشغلانة بنجاح',
            'work_order' => $formattedWorkOrder,
            'work_order_id' => $newWorkOrderId
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception("فشل في إنشاء الشغلانة: " . $stmt->error);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في الخادم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>