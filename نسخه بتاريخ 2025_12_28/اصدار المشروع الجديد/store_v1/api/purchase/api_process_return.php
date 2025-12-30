<?php
// api_process_return.php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 2) . '/config.php';

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

// التحقق من البيانات
$return_id = (int)($_POST['return_id'] ?? 0);
$action = trim($_POST['action'] ?? ''); // approve, reject, complete
$notes = trim($_POST['notes'] ?? '');

if ($return_id <= 0 || !in_array($action, ['approve', 'reject', 'complete'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
    exit;
}

try {
    $conn->begin_transaction();
    $current_user_id = (int)$_SESSION['id'];
    
    // جلب بيانات المرتجع
    $sql_get = "SELECT pr.*, pi.supplier_invoice_number 
                FROM purchase_returns pr
                JOIN purchase_invoices pi ON pi.id = pr.purchase_invoice_id
                WHERE pr.id = ? FOR UPDATE";
    
    $stmt = $conn->prepare($sql_get);
    $stmt->bind_param("i", $return_id);
    $stmt->execute();
    $return_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$return_data) {
        throw new Exception('المرتجع غير موجود');
    }
    
    $current_status = $return_data['status'];
    $invoice_id = $return_data['purchase_invoice_id'];
    
    // التحقق من حالة المرتجع الحالية
    if ($action === 'approve' && $current_status !== 'pending') {
        throw new Exception('لا يمكن الموافقة على مرتجع غير معلق');
    }
    
    if ($action === 'complete' && $current_status !== 'approved') {
        throw new Exception('لا يمكن إكمال مرتجع غير معتمد');
    }
    
    // معالجة حسب الإجراء
    switch ($action) {
        case 'approve':
            $new_status = 'approved';
            $sql_update = "UPDATE purchase_returns 
                           SET status = ?, approved_by = ?, approved_at = NOW(),
                               notes = CONCAT(IFNULL(notes, ''), ?)
                           WHERE id = ?";
            
            $action_note = "\n[" . date('Y-m-d H:i:s') . "] تمت الموافقة على المرتجع بواسطة المستخدم #{$current_user_id}";
            if ($notes) $action_note .= " - ملاحظة: {$notes}";
            
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("siss", $new_status, $current_user_id, $action_note, $return_id);
            break;
            
        case 'reject':
            $new_status = 'cancelled';
            $sql_update = "UPDATE purchase_returns 
                           SET status = ?, notes = CONCAT(IFNULL(notes, ''), ?)
                           WHERE id = ?";
            
            $action_note = "\n[" . date('Y-m-d H:i:s') . "] تم رفض المرتجع بواسطة المستخدم #{$current_user_id}";
            if ($notes) $action_note .= " - السبب: {$notes}";
            
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("ssi", $new_status, $action_note, $return_id);
            break;
            
        case 'complete':
            $new_status = 'completed';
            
            // 1. تحديث حالة المرتجع
            $sql_update = "UPDATE purchase_returns 
                           SET status = ?, completed_by = ?, completed_at = NOW(),
                               notes = CONCAT(IFNULL(notes, ''), ?)
                           WHERE id = ?";
            
            $action_note = "\n[" . date('Y-m-d H:i:s') . "] تم إكمال المرتجع بواسطة المستخدم #{$current_user_id}";
            if ($notes) $action_note .= " - ملاحظة: {$notes}";
            
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("siss", $new_status, $current_user_id, $action_note, $return_id);
            
            if (!$stmt->execute()) {
                throw new Exception('فشل تحديث حالة المرتجع: ' . $stmt->error);
            }
            $stmt->close();
            
            // 2. معالجة بنود المرتجع وتحديث المخزون
            $sql_items = "SELECT pri.*, b.remaining, b.returned_quantity 
                          FROM purchase_return_items pri
                          JOIN batches b ON b.id = pri.batch_id
                          WHERE pri.purchase_return_id = ? AND pri.status = 'pending' 
                          FOR UPDATE";
            
            $stmt_items = $conn->prepare($sql_items);
            $stmt_items->bind_param("i", $return_id);
            $stmt_items->execute();
            $items_result = $stmt_items->get_result();
            
            while ($item = $items_result->fetch_assoc()) {
                $batch_id = $item['batch_id'];
                $quantity = (float)$item['quantity'];
                $batch_before = (float)$item['remaining'];
                $batch_after = $batch_before - $quantity;
                
                // تحديث الدفعة
                $sql_batch = "UPDATE batches 
                              SET remaining = ?,
                                  returned_quantity = returned_quantity + ?,
                                  last_return_reason = ?,
                                  last_return_date = NOW()
                              WHERE id = ?";
                
                $stmt_batch = $conn->prepare($sql_batch);
                $reason = $item['return_reason'] ?: $return_data['reason'];
                $stmt_batch->bind_param("ddsi", $batch_after, $quantity, $reason, $batch_id);
                
                if (!$stmt_batch->execute()) {
                    throw new Exception('فشل تحديث الدفعة: ' . $stmt_batch->error);
                }
                $stmt_batch->close();
                
                // تحديث مخزون المنتج
                $sql_product = "UPDATE products 
                                SET current_stock = current_stock - ? 
                                WHERE id = ?";
                
                $stmt_product = $conn->prepare($sql_product);
                $stmt_product->bind_param("di", $quantity, $item['product_id']);
                
                if (!$stmt_product->execute()) {
                    throw new Exception('فشل تحديث المخزون: ' . $stmt_product->error);
                }
                $stmt_product->close();
                
                // تحديث حالة بند المرتجع
                $sql_item_update = "UPDATE purchase_return_items 
                                    SET status = 'completed',
                                        batch_qty_before = ?,
                                        batch_qty_after = ?
                                    WHERE id = ?";
                
                $stmt_item_update = $conn->prepare($sql_item_update);
                $stmt_item_update->bind_param("ddi", $batch_before, $batch_after, $item['id']);
                $stmt_item_update->execute();
                $stmt_item_update->close();
            }
            
            $stmt_items->close();
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'تم إكمال المرتجع وتحديث المخزون بنجاح'
            ], JSON_UNESCAPED_UNICODE);
            exit;
    }
    
    // تنفيذ التحديث للعمليات approve/reject
    if (!$stmt->execute()) {
        throw new Exception('فشل تحديث حالة المرتجع: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "تم {$action} المرتجع بنجاح"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'فشل المعالجة: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>