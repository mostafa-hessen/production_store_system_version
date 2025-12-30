<?php
// api_create_purchase_return.php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config.php';

// التحقق من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الطريقة غير مسموحة']);
    exit;
}

// التحقق من CSRF
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'طلب غير مصرح به']);
    exit;
}

// التحقق من البيانات الأساسية
$required_fields = ['invoice_id', 'supplier_id', 'return_type', 'reason', 'return_items'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "حقل {$field} مطلوب"]);
        exit;
    }
}

$invoice_id = (int)$_POST['invoice_id'];
$supplier_id = (int)$_POST['supplier_id'];
$return_type = trim($_POST['return_type']);
$reason = trim($_POST['reason']);
$notes = trim($_POST['notes'] ?? '');
$return_items = json_decode($_POST['return_items'], true);

if (!is_array($return_items) || empty($return_items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بنود الإرجاع غير صالحة']);
    exit;
}

// Valid return types
$valid_types = ['full_return', 'partial_defective', 'partial_excess', 'wrong_item'];
if (!in_array($return_type, $valid_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'نوع الإرجاع غير صالح']);
    exit;
}

try {
    $conn->begin_transaction();
    $current_user_id = (int)$_SESSION['id'];
    
    // 1. إنشاء رقم مرتجع تلقائي
    $return_number = 'RET-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // 2. إدخال رأس المرتجع
    $sql_insert = "INSERT INTO purchase_returns 
                  (return_number, supplier_id, purchase_invoice_id, return_date, 
                   return_type, reason, notes, total_quantity, total_value, 
                   status, created_by, created_at)
                  VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 0, 0, 'pending', ?, NOW())";
    
    $stmt = $conn->prepare($sql_insert);
    if (!$stmt) throw new Exception($conn->error);
    
    $stmt->bind_param("siisssi", 
        $return_number, $supplier_id, $invoice_id, 
        $return_type, $reason, $notes, $current_user_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception('فشل إنشاء المرتجع: ' . $stmt->error);
    }
    
    $return_id = $stmt->insert_id;
    $stmt->close();
    
    // 3. معالجة بنود الإرجاع
    $total_quantity = 0;
    $total_value = 0;
    
    $sql_item = "INSERT INTO purchase_return_items 
                (purchase_return_id, batch_id, product_id, quantity, 
                 unit_cost, product_condition, return_reason, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    
    $stmt_item = $conn->prepare($sql_item);
    if (!$stmt_item) throw new Exception($conn->error);
    
    foreach ($return_items as $item) {
        // التحقق من البيانات
        if (empty($item['batch_id']) || empty($item['product_id']) || empty($item['quantity'])) {
            throw new Exception('بيانات البند غير مكتملة');
        }
        
        $batch_id = (int)$item['batch_id'];
        $product_id = (int)$item['product_id'];
        $quantity = (float)$item['quantity'];
        $unit_cost = (float)($item['unit_cost'] ?? 0);
        $condition = $item['condition'] ?? 'defective';
        $item_reason = $item['item_reason'] ?? $reason;
        
        // التحقق من توفر الكمية
        $sql_check = "SELECT remaining, returned_quantity, unit_cost 
                      FROM batches 
                      WHERE id = ? AND status = 'active' 
                      FOR UPDATE";
        
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $batch_id);
        $stmt_check->execute();
        $batch = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();
        
        if (!$batch) {
            throw new Exception("الدفعة #{$batch_id} غير موجودة أو غير نشطة");
        }
        
        $available = (float)$batch['remaining'] - (float)$batch['returned_quantity'];
        if ($quantity > $available) {
            throw new Exception("الكمية المطلوبة ({$quantity}) تتجاوز المتاح ({$available}) للدفعة #{$batch_id}");
        }
        
        // استخدام تكلفة الدفعة إذا لم يتم توفيرها
        if ($unit_cost <= 0) {
            $unit_cost = (float)$batch['unit_cost'];
        }
        
        // إدخال بند المرتجع
        $stmt_item->bind_param("iiiddss", 
            $return_id, $batch_id, $product_id, 
            $quantity, $unit_cost, $condition, $item_reason
        );
        
        if (!$stmt_item->execute()) {
            throw new Exception('فشل إضافة بند المرتجع: ' . $stmt_item->error);
        }
        
        $total_quantity += $quantity;
        $total_value += ($quantity * $unit_cost);
    }
    
    $stmt_item->close();
    
    // 4. تحديث إجماليات المرتجع
    $sql_update = "UPDATE purchase_returns 
                   SET total_quantity = ?, total_value = ?
                   WHERE id = ?";
    
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ddi", $total_quantity, $total_value, $return_id);
    $stmt_update->execute();
    $stmt_update->close();
    
    // 5. إضافة ملاحظة للفاتورة الأصلية
    $note = "[" . date('Y-m-d H:i:s') . "] تم إنشاء مرتجع #{$return_number} ({$return_type}) للكمية: {$total_quantity}";
    $sql_note = "UPDATE purchase_invoices 
                 SET notes = CONCAT(IFNULL(notes, ''), ?)
                 WHERE id = ?";
    
    $stmt_note = $conn->prepare($sql_note);
    $stmt_note->bind_param("si", $note, $invoice_id);
    $stmt_note->execute();
    $stmt_note->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء طلب الإرجاع بنجاح',
        'data' => [
            'return_id' => $return_id,
            'return_number' => $return_number,
            'total_quantity' => $total_quantity,
            'total_value' => $total_value
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'فشل إنشاء المرتجع: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>