<?php
// returns.php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit;
}

// قراءة وتحليل JSON المدخلات
$input = json_decode(file_get_contents('php://input'), true);

// التحقق من صحة المدخلات
$errors = validateInput($input);
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input',
        'errors' => $errors
    ]);
    exit;
}

try {
    // بدء معاملة قاعدة البيانات
    $conn->begin_transaction();
    
    // الحصول على معرف المستخدم من الجلسة (يجب تعديله حسب نظامك)
    $userId = getCurrentUserId();
    
    // 1. التحقق من الفاتورة والعميل
    $invoice = validateInvoiceAndCustomer($conn, $input['invoice_id'], $input['customer_id']);
    
    // 2. التحقق من البنود المراد إرجاعها
    $validatedItems = validateReturnItems($conn, $input['invoice_id'], $input['items']);
    
    // 3. تطبيق FIFO لاسترجاع الكميات من الدفعات (batches)
    $batchAllocations = processFifoReturns($conn, $validatedItems, $userId);
    
    // 4. حساب إجمالي قيمة المرتجع
    $totalReturnAmount = calculateTotalReturnAmount($validatedItems);
    
    // 5. إنشاء سجل المرتجع في جدول returns
    $returnId = createReturnRecord($conn, [
        'invoice_id' => $input['invoice_id'],
        'customer_id' => $input['customer_id'],
        'return_type' => $input['return_type'],
        'reason' => $input['reason'],
        'total_amount' => $totalReturnAmount,
        'created_by' => $userId
    ]);
    
    // 6. إنشاء بنود المرتجع في جدول return_items
    createReturnItems($conn, $returnId, $validatedItems, $batchAllocations);
    
    // 7. تحديث كميات البنود الأصلية في invoice_out_items
    updateInvoiceItems($conn, $validatedItems);
    
    // 8. تحديث إجماليات الفاتورة في invoices_out
    updateInvoiceTotals($conn, $input['invoice_id'], $totalReturnAmount);
    
    // 9. معالجة الدفعات المالية حسب حالة الفاتورة
    processFinancialTransactions($conn, $invoice, $returnId, $totalReturnAmount, $validatedItems, $userId);
    
    // 10. تأكيد المعاملة
    $conn->commit();
    
    // إرجاع النتيجة الناجحة
    echo json_encode([
        'status' => 'success',
        'return_id' => $returnId,
        'message' => 'Return processed successfully',
        'batch_allocations' => $batchAllocations
    ]);
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة حدوث خطأ
    if ($conn) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to process return: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * التحقق من صحة المدخلات
 */
function validateInput($input) {
    $errors = [];
    
    if (empty($input['invoice_id']) || !is_numeric($input['invoice_id'])) {
        $errors[] = 'invoice_id is required and must be numeric';
    }
    
    if (empty($input['customer_id']) || !is_numeric($input['customer_id'])) {
        $errors[] = 'customer_id is required and must be numeric';
    }
    
    if (empty($input['return_type']) || !in_array($input['return_type'], ['full', 'partial', 'exchange'])) {
        $errors[] = 'return_type is required and must be one of: full, partial, exchange';
    }
    
    if (empty($input['reason'])) {
        $errors[] = 'reason is required';
    }
    
    if (empty($input['items']) || !is_array($input['items']) || count($input['items']) === 0) {
        $errors[] = 'items array is required and must not be empty';
    } else {
        foreach ($input['items'] as $index => $item) {
            if (empty($item['invoice_item_id']) || !is_numeric($item['invoice_item_id'])) {
                $errors[] = "items[$index].invoice_item_id is required and must be numeric";
            }
            if (empty($item['product_id']) || !is_numeric($item['product_id'])) {
                $errors[] = "items[$index].product_id is required and must be numeric";
            }
            if (empty($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                $errors[] = "items[$index].quantity is required and must be greater than 0";
            }
            if (!isset($item['return_price']) || !is_numeric($item['return_price'])) {
                $errors[] = "items[$index].return_price is required and must be numeric";
            }
            if (empty($item['refund_method']) || !in_array($item['refund_method'], ['cash', 'wallet', 'none'])) {
                $errors[] = "items[$index].refund_method is required and must be one of: cash, wallet, none";
            }
        }
    }
    
    return $errors;
}

/**
 * الحصول على معرف المستخدم الحالي (يجب تعديله حسب نظام المصادقة)
 */
function getCurrentUserId() {
    // يمكنك تعديل هذه الدالة حسب نظام المصادقة الخاص بك
    // مثال: إذا كنت تستخدم الجلسات
    session_start();
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    
    // أو إذا كنت تستخدم JWT
    // return getUserIdFromToken();
    
    throw new Exception('User not authenticated');
}

/**
 * التحقق من صحة الفاتورة والعميل
 */
function validateInvoiceAndCustomer($conn, $invoiceId, $customerId) {
    $sql = "SELECT i.*, c.balance, c.wallet
            FROM invoices_out i
            INNER JOIN customers c ON i.customer_id = c.id
            WHERE i.id = ? AND i.customer_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $invoiceId, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        throw new Exception('Invoice not found or does not belong to the specified customer');
    }
    
    // حساب الحالة المالية للفاتورة
    $paidAmount = (float)$invoice['paid_amount'];
    $totalAfterDiscount = (float)$invoice['total_after_discount'];
    $remainingAmount = (float)$invoice['remaining_amount'];
    
    // تحديد حالة الفاتورة
    if ($remainingAmount == $totalAfterDiscount) {
        $invoice['payment_status'] = 'pending'; // مؤجلة
    } elseif ($remainingAmount == 0) {
        $invoice['payment_status'] = 'fully_paid'; // مدفوعة كليًا
    } else {
        $invoice['payment_status'] = 'partially_paid'; // مدفوعة جزئيًا
    }
    
    return $invoice;
}

/**
 * التحقق من صحة البنود المراد إرجاعها
 */
function validateReturnItems($conn, $invoiceId, $items) {
    $validatedItems = [];
    
    foreach ($items as $item) {
        $invoiceItemId = (int)$item['invoice_item_id'];
        $productId = (int)$item['product_id'];
        $requestedQty = (float)$item['quantity'];
        
        // التحقق من وجود البند في الفاتورة
        $sql = "SELECT id, product_id, quantity, returned_quantity, available_for_return, 
                       selling_price, total_after_discount
                FROM invoice_out_items 
                WHERE id = ? AND invoice_out_id = ? AND product_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $stmt->bind_param("iii", $invoiceItemId, $invoiceId, $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        $invoiceItem = $result->fetch_assoc();
        $stmt->close();
        
        if (!$invoiceItem) {
            throw new Exception("Item not found in invoice: item_id=$invoiceItemId, product_id=$productId");
        }
        
        // التحقق من أن الكمية المطلوبة للإرجاع متاحة
        $availableForReturn = (float)$invoiceItem['available_for_return'];
        if ($requestedQty > $availableForReturn) {
            throw new Exception("Requested return quantity ($requestedQty) exceeds available quantity ($availableForReturn) for item ID $invoiceItemId");
        }
        
        // التحقق من سعر الإرجاع
        $returnPrice = (float)$item['return_price'];
        $sellingPrice = (float)$invoiceItem['selling_price'];
        
        if ($returnPrice > $sellingPrice) {
            throw new Exception("Return price ($returnPrice) cannot be greater than selling price ($sellingPrice) for item ID $invoiceItemId");
        }
        
        // إضافة البيانات المطلوبة إلى المصفوفة
        $validatedItems[] = [
            'invoice_item_id' => $invoiceItemId,
            'product_id' => $productId,
            'quantity' => $requestedQty,
            'return_price' => $returnPrice,
            'refund_method' => $item['refund_method'],
            'available_for_return' => $availableForReturn,
            'total_amount' => $requestedQty * $returnPrice,
            'original_item' => $invoiceItem
        ];
    }
    
    return $validatedItems;
}

/**
 * تطبيق FIFO لاسترجاع الكميات من الدفعات
 */
function processFifoReturns($conn, $validatedItems, $userId) {
    $batchAllocations = [];
    $currentTime = date('Y-m-d H:i:s');
    
    foreach ($validatedItems as $item) {
        $invoiceItemId = $item['invoice_item_id'];
        $productId = $item['product_id'];
        $quantityToReturn = $item['quantity'];
        
        // الحصول على توزيعات البيع الأصلية من sale_item_allocations
        $sql = "SELECT sia.batch_id, sia.qty as allocated_qty, sia.unit_cost,
                       b.remaining as batch_remaining, b.sale_price
                FROM sale_item_allocations sia
                INNER JOIN batches b ON sia.batch_id = b.id
                WHERE sia.sale_item_id = ?
                ORDER BY b.received_at ASC, b.id ASC"; // FIFO
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $stmt->bind_param("i", $invoiceItemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $allocations = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($allocations)) {
            throw new Exception("No batch allocations found for item ID $invoiceItemId");
        }
        
        $itemAllocations = [];
        $remainingQty = $quantityToReturn;
        
        foreach ($allocations as $allocation) {
            if ($remainingQty <= 0) break;
            
            $batchId = (int)$allocation['batch_id'];
            $allocatedQty = (float)$allocation['allocated_qty'];
            $batchRemaining = (float)$allocation['batch_remaining'];
            
            // تحديد الكمية المسترجعة من هذه الدفعة
            $returnFromThisBatch = min($remainingQty, $allocatedQty);
            
            if ($returnFromThisBatch > 0) {
                // تحديث الكمية المتبقية في الدفعة
                $newRemaining = $batchRemaining + $returnFromThisBatch;
                
                $updateSql = "UPDATE batches 
                             SET remaining = ?, updated_at = ?, adjusted_by = ?, adjusted_at = ?
                             WHERE id = ? AND product_id = ?";
                
                $updateStmt = $conn->prepare($updateSql);
                if (!$updateStmt) {
                    throw new Exception("Failed to prepare update statement: " . $conn->error);
                }
                
                $updateStmt->bind_param("sssiii", 
                    $newRemaining, $currentTime, $userId, $currentTime,
                    $batchId, $productId
                );
                $updateStmt->execute();
                $updateStmt->close();
                
                // تسجيل التوزيع
                $itemAllocations[] = [
                    'batch_id' => $batchId,
                    'quantity' => $returnFromThisBatch,
                    'unit_cost' => (float)$allocation['unit_cost'],
                    'batch_remaining_before' => $batchRemaining,
                    'batch_remaining_after' => $newRemaining
                ];
                
                $remainingQty -= $returnFromThisBatch;
            }
        }
        
        if ($remainingQty > 0) {
            throw new Exception("Insufficient allocated quantity in batches for item ID $invoiceItemId");
        }
        
        $batchAllocations[$invoiceItemId] = $itemAllocations;
    }
    
    return $batchAllocations;
}

/**
 * حساب إجمالي قيمة المرتجع
 */
function calculateTotalReturnAmount($validatedItems) {
    $total = 0;
    
    foreach ($validatedItems as $item) {
        $total += $item['total_amount'];
    }
    
    return $total;
}

/**
 * إنشاء سجل المرتجع في جدول returns
 */
function createReturnRecord($conn, $data) {
    $sql = "INSERT INTO returns 
            (invoice_id, customer_id, return_type, reason, total_amount, created_by, return_date, status)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'completed')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("iissdi", 
        $data['invoice_id'],
        $data['customer_id'],
        $data['return_type'],
        $data['reason'],
        $data['total_amount'],
        $data['created_by']
    );
    
    $stmt->execute();
    $returnId = $stmt->insert_id;
    $stmt->close();
    
    if (!$returnId) {
        throw new Exception("Failed to create return record");
    }
    
    return $returnId;
}

/**
 * إنشاء بنود المرتجع
 */
function createReturnItems($conn, $returnId, $validatedItems, $batchAllocations) {
    foreach ($validatedItems as $item) {
        $invoiceItemId = $item['invoice_item_id'];
        $allocations = isset($batchAllocations[$invoiceItemId]) ? $batchAllocations[$invoiceItemId] : [];
        
        $sql = "INSERT INTO return_items 
                (return_id, invoice_item_id, product_id, quantity, return_price, 
                 total_amount, batch_allocations, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'restocked')";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $batchAllocationsJson = json_encode($allocations);
        
        $stmt->bind_param("iiiddds", 
            $returnId,
            $invoiceItemId,
            $item['product_id'],
            $item['quantity'],
            $item['return_price'],
            $item['total_amount'],
            $batchAllocationsJson
        );
        
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * تحديث كميات البنود الأصلية
 */
function updateInvoiceItems($conn, $validatedItems) {
    foreach ($validatedItems as $item) {
        $invoiceItemId = $item['invoice_item_id'];
        $returnedQty = $item['quantity'];
        
        $sql = "UPDATE invoice_out_items 
                SET returned_quantity = returned_quantity + ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }
        
        $stmt->bind_param("di", $returnedQty, $invoiceItemId);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * تحديث إجماليات الفاتورة
 */
function updateInvoiceTotals($conn, $invoiceId, $totalReturnAmount) {
    // تحديث المبالغ المدفوعة والمتبقية
    $sql = "UPDATE invoices_out 
            SET remaining_amount = remaining_amount - ?,
                updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("di", $totalReturnAmount, $invoiceId);
    $stmt->execute();
    $stmt->close();
}

/**
 * معالجة المعاملات المالية حسب حالة الفاتورة
 */
function processFinancialTransactions($conn, $invoice, $returnId, $totalReturnAmount, $validatedItems, $userId) {
    $customerId = (int)$invoice['customer_id'];
    $invoiceId = (int)$invoice['id'];
    $paymentStatus = $invoice['payment_status'];
    
    // الحصول على أرصدة العميل الحالية
    $balanceBefore = (float)$invoice['balance'];
    $walletBefore = (float)$invoice['wallet'];
    
    // تحضير مصفوفة للطرق المستخدمة في الإرجاع
    $refundMethods = [];
    foreach ($validatedItems as $item) {
        $method = $item['refund_method'];
        if ($method !== 'none') {
            if (!isset($refundMethods[$method])) {
                $refundMethods[$method] = 0;
            }
            $refundMethods[$method] += $item['total_amount'];
        }
    }
    
    // حالة 1: فاتورة مؤجلة (المتبقي = الإجمالي)
    if ($paymentStatus === 'pending') {
        // فقط نقوم بتخفيض رصيد العميل (المدين)
        $newBalance = $balanceBefore - $totalReturnAmount;
        
        // تحديث رصيد العميل
        updateCustomerBalance($conn, $customerId, $newBalance);
        
        // تسجيل حركة العميل
        createCustomerTransaction($conn, [
            'customer_id' => $customerId,
            'transaction_type' => 'return',
            'amount' => -$totalReturnAmount, // سالبة لأنها تخفيض من الدين
            'description' => "Return for invoice #$invoiceId",
            'invoice_id' => $invoiceId,
            'return_id' => $returnId,
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance,
            'wallet_before' => $walletBefore,
            'wallet_after' => $walletBefore,
            'created_by' => $userId
        ]);
    }
    // حالة 2: فاتورة مدفوعة كليًا
    elseif ($paymentStatus === 'fully_paid') {
        // معالجة كل طريقة دفع في مرتجع البنود
        foreach ($refundMethods as $method => $amount) {
            if ($method === 'cash') {
                // لا حاجة لتسجيل أي شيء للنقدي، فقط الإرجاع الفعلي
                // يمكنك إضافة تسجيل نقدي هنا إذا كان نظامك يتطلب ذلك
            } elseif ($method === 'wallet') {
                // إضافة المبلغ إلى محفظة العميل
                $newWallet = $walletBefore + $amount;
                updateCustomerWallet($conn, $customerId, $newWallet);
                
                // تسجيل حركة المحفظة
                createWalletTransaction($conn, [
                    'customer_id' => $customerId,
                    'type' => 'refund',
                    'amount' => $amount,
                    'description' => "Refund for return #$returnId from invoice #$invoiceId",
                    'wallet_before' => $walletBefore,
                    'wallet_after' => $newWallet,
                    'created_by' => $userId
                ]);
                
                $walletBefore = $newWallet; // تحديث للمرحلة التالية
            }
        }
        
        // تسجيل حركة العميل
        $newBalance = $balanceBefore; // لا يتغير الرصيد في حالة الدفع الكامل
        createCustomerTransaction($conn, [
            'customer_id' => $customerId,
            'transaction_type' => 'return',
            'amount' => -$totalReturnAmount,
            'description' => "Return for invoice #$invoiceId (fully paid invoice)",
            'invoice_id' => $invoiceId,
            'return_id' => $returnId,
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance,
            'wallet_before' => (float)$invoice['wallet'],
            'wallet_after' => $walletBefore,
            'created_by' => $userId
        ]);
    }
    // حالة 3: فاتورة مدفوعة جزئيًا
    elseif ($paymentStatus === 'partially_paid') {
        // حساب المبلغ المتبقي على العميل قبل المرتجع
        $remainingBefore = (float)$invoice['remaining_amount'];
        
        // إذا كان المرتجع أقل من أو يساوي المتبقي
        if ($totalReturnAmount <= $remainingBefore) {
            // يتم تخفيض المتبقي فقط
            $newBalance = $balanceBefore - $totalReturnAmount;
            updateCustomerBalance($conn, $customerId, $newBalance);
        } else {
            // المرتجع أكبر من المتبقي
            $excessAmount = $totalReturnAmount - $remainingBefore;
            
            // تخفيض المتبقي إلى الصفر
            $newBalance = $balanceBefore - $remainingBefore;
            updateCustomerBalance($conn, $customerId, $newBalance);
            
            // معالجة الزيادة حسب طريقة الإرجاع
            foreach ($refundMethods as $method => $amount) {
                if ($method === 'cash') {
                    // لا حاجة لتسجيل أي شيء للنقدي
                } elseif ($method === 'wallet') {
                    // إضافة المبلغ الزائد إلى المحفظة
                    $newWallet = $walletBefore + $excessAmount;
                    updateCustomerWallet($conn, $customerId, $newWallet);
                    
                    // تسجيل حركة المحفظة
                    createWalletTransaction($conn, [
                        'customer_id' => $customerId,
                        'type' => 'refund',
                        'amount' => $excessAmount,
                        'description' => "Excess refund for return #$returnId from invoice #$invoiceId",
                        'wallet_before' => $walletBefore,
                        'wallet_after' => $newWallet,
                        'created_by' => $userId
                    ]);
                    
                    $walletBefore = $newWallet;
                }
            }
        }
        
        // تسجيل حركة العميل
        createCustomerTransaction($conn, [
            'customer_id' => $customerId,
            'transaction_type' => 'return',
            'amount' => -$totalReturnAmount,
            'description' => "Return for partially paid invoice #$invoiceId",
            'invoice_id' => $invoiceId,
            'return_id' => $returnId,
            'balance_before' => $balanceBefore,
            'balance_after' => $newBalance,
            'wallet_before' => (float)$invoice['wallet'],
            'wallet_after' => $walletBefore,
            'created_by' => $userId
        ]);
    }
}

/**
 * تحديث رصيد العميل
 */
function updateCustomerBalance($conn, $customerId, $newBalance) {
    $sql = "UPDATE customers SET balance = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    $stmt->bind_param("di", $newBalance, $customerId);
    $stmt->execute();
    $stmt->close();
}

/**
 * تحديث محفظة العميل
 */
function updateCustomerWallet($conn, $customerId, $newWallet) {
    $sql = "UPDATE customers SET wallet = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    $stmt->bind_param("di", $newWallet, $customerId);
    $stmt->execute();
    $stmt->close();
}

/**
 * إنشاء حركة للعميل
 */
function createCustomerTransaction($conn, $data) {
    $sql = "INSERT INTO customer_transactions 
            (customer_id, transaction_type, amount, description, 
             invoice_id, return_id, balance_before, balance_after,
             wallet_before, wallet_after, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("isdssiddddi",
        $data['customer_id'],
        $data['transaction_type'],
        $data['amount'],
        $data['description'],
        $data['invoice_id'],
        $data['return_id'],
        $data['balance_before'],
        $data['balance_after'],
        $data['wallet_before'],
        $data['wallet_after'],
        $data['created_by']
    );
    
    $stmt->execute();
    $stmt->close();
}

/**
 * إنشاء حركة للمحفظة
 */
function createWalletTransaction($conn, $data) {
    $sql = "INSERT INTO wallet_transactions 
            (customer_id, type, amount, description, 
             wallet_before, wallet_after, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param("isddddi",
        $data['customer_id'],
        $data['type'],
        $data['amount'],
        $data['description'],
        $data['wallet_before'],
        $data['wallet_after'],
        $data['created_by']
    );
    
    $stmt->execute();
    $stmt->close();
}

// إغلاق الاتصال
if ($conn) {
    $conn->close();
}
?>