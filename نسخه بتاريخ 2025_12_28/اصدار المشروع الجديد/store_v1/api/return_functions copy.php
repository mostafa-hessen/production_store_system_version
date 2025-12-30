<?php

function checkReturnPermissions($user_role) {
    if ($user_role !== 'admin') {
        throw new Exception('ليس لديك صلاحية لإنشاء عمليات إرجاع');
    }
    return true;
}

/**
 * التحقق من البيانات المدخلة
 */
function validateReturnInput($data) {
    if (!isset($data['invoice_id']) || !isset($data['customer_id'])) {
        throw new Exception('بيانات غير صحيحة. يرجى التأكد من إدخال invoice_id و customer_id');
    }
    
    if (!isset($data['return_type']) || !in_array($data['return_type'], ['partial', 'full', 'exchange'])) {
        throw new Exception('نوع الإرجاع غير صحيح. يجب أن يكون partial أو full أو exchange');
    }
    
    if (empty($data['items']) && $data['return_type'] !== 'full') {
        throw new Exception('يجب تحديد البنود المراد إرجاعها');
    }
    
    return true;
}

/**
 * قفل الفاتورة والعميل
 */
function lockInvoiceAndCustomer($conn, $invoice_id, $customer_id) {
    $stmt = $conn->prepare("
        SELECT i.*, c.balance, c.wallet 
        FROM invoices_out i 
        JOIN customers c ON i.customer_id = c.id 
        WHERE i.id = ? AND i.customer_id = ? 
        FOR UPDATE
    ");
    $stmt->bind_param("ii", $invoice_id, $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    $stmt->close();
    
    if (!$invoice) {
        throw new Exception('الفاتورة غير موجودة أو لا تنتمي لهذا العميل');
    }
    
    return $invoice;
}

/**
 * جلب كل البنود المتاحة للارجاع (للإرجاع الكامل)
 */
function getAllReturnableItems($conn, $invoice_id) {
    $stmt = $conn->prepare("
        SELECT id, product_id, quantity, returned_quantity, 
               available_for_return, total_after_discount
        FROM invoice_out_items 
        WHERE invoice_out_id = ? AND available_for_return > 0
        FOR UPDATE
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['available_for_return'] > 0) {
            $items[] = [
                'invoice_item_id' => $row['id'],
                'product_id' => $row['product_id'],
                'quantity' => (float)$row['available_for_return'],
                'reason' => 'إرجاع كامل للفاتورة',
                'refund_preference' => 'wallet'
            ];
        }
    }
    $stmt->close();
    
    if (empty($items)) {
        throw new Exception('لا توجد بنود متاحة للإرجاع في هذه الفاتورة');
    }
    
    return $items;
}

/**
 * التحقق من صحة بنود الإرجاع
 */
function validateReturnItems($conn, $invoice_id, $items) {
    $return_items = [];
    
    foreach ($items as $item) {
        if (!isset($item['invoice_item_id']) || !isset($item['quantity']) || $item['quantity'] <= 0) {
            throw new Exception('بيانات البند غير صحيحة');
        }
        
        $invoice_item_id = (int)$item['invoice_item_id'];
        $return_qty = (float)$item['quantity'];
        
        // التحقق من وجود البند والكمية المتاحة
        $stmt = $conn->prepare("
            SELECT ioi.*, 
                   (ioi.quantity - ioi.returned_quantity) as available_for_return,
                   ioi.total_after_discount / ioi.quantity as unit_price_after_discount
            FROM invoice_out_items ioi
            WHERE ioi.id = ? AND ioi.invoice_out_id = ?
            FOR UPDATE
        ");
        $stmt->bind_param("ii", $invoice_item_id, $invoice_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $invoice_item = $result->fetch_assoc();
        $stmt->close();
        
        if (!$invoice_item) {
            throw new Exception("بند الفاتورة غير موجود: {$invoice_item_id}");
        }
        
        $available_for_return = (float)$invoice_item['available_for_return'];
        
        if ($return_qty > $available_for_return) {
            throw new Exception("الكمية المطلوبة للبند {$invoice_item_id} تتجاوز الكمية المتاحة للإرجاع. المتاحة: {$available_for_return}, المطلوبة: {$return_qty}");
        }
        
        // التحقق من طريقة الاسترداد
        $refund_preference = isset($item['refund_preference']) ? $item['refund_preference'] : 'wallet';
        if (!in_array($refund_preference, ['cash', 'wallet', 'خصم من المتبقي'])) {
            throw new Exception("طريقة الاسترداد غير صحيحة للبند: {$invoice_item_id}");
        }
        
        $return_items[] = [
            'invoice_item' => $invoice_item,
            'invoice_item_id' => $invoice_item_id,
            'product_id' => (int)$invoice_item['product_id'],
            'return_qty' => $return_qty,
            'unit_price_after_discount' => (float)($invoice_item['unit_price_after_discount']),
            'reason' => isset($item['reason']) ? $item['reason'] : '',
            'refund_preference' => $refund_preference,
            'batch_allocations' => []
        ];
    }
    
    return $return_items;
}

/**
 * حساب المبالغ الإجمالية للإرجاع
 */
function calculateReturnAmounts(&$return_items) {
    $total_return_amount = 0;
    
    foreach ($return_items as &$item) {
        $item_return_amount = $item['return_qty'] * $item['unit_price_after_discount'];
        $item['item_return_amount'] = $item_return_amount;
        $total_return_amount += $item_return_amount;
    }
    
    return [
        'total_return_amount' => $total_return_amount
    ];
}

/**
 * إنشاء سجل الإرجاع الرئيسي
 */
function createReturnRecord($conn, $data, $total_amount, $user_id, $user_role) {
    $stmt = $conn->prepare("
        INSERT INTO returns 
        (invoice_id, customer_id, return_date, total_amount, return_type, 
         status, reason, approved_by, approved_at, created_by)
        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("فشل في إعداد الاستعلام: " . $conn->error);
    }

    // تحديد القيم
    $status = $user_role === 'admin' ? 'approved' : 'pending';
    $approved_by_value = $user_role === 'admin' ? $user_id : 0; // لو مش admin نخليها 0
    $reason = isset($data['reason']) ? $data['reason'] : '';
  

    // bind_param: i = integer, d = double, s = string
    $stmt->bind_param(
        "iidssiii", 
        $data['invoice_id'],   // i
        $data['customer_id'],  // i
        $total_amount,         // d
        $data['return_type'],  // s
        $status,               // s
        $reason,               // s
        $approved_by_value,    // i
        $user_id,              // i
                
    );

    if (!$stmt->execute()) {
        throw new Exception("فشل في إنشاء سجل الإرجاع: " . $stmt->error);
    }

    return $stmt->insert_id; // ترجع id للسجل الجديد
}


/**
 * الحصول على تخصيصات البيع للبند
 */
function getSaleItemAllocations($conn, $sale_item_id) {
    $stmt = $conn->prepare("
        SELECT sia.*, b.status as batch_status, b.remaining as batch_remaining
        FROM sale_item_allocations sia
        JOIN batches b ON sia.batch_id = b.id
        WHERE sia.sale_item_id = ? AND sia.qty > 0
        ORDER BY sia.id DESC
    ");
    $stmt->bind_param("i", $sale_item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $allocations = [];
    while ($row = $result->fetch_assoc()) {
        $allocations[] = $row;
    }
    $stmt->close();
    
    return $allocations;
}

/**
 * تحديث الدفعة
 */
function updateBatch($conn, $batch_id, $batch_status, $batch_remaining, $qty_to_return, $reason) {
    $new_remaining = $batch_remaining + $qty_to_return;
    
    if ($batch_status === 'consumed' && $new_remaining > 0) {
        // إذا كانت الدفعة consumed وأصبح لديها رصيد، نعيدها active
        $stmt = $conn->prepare("
            UPDATE batches 
            SET remaining = ?, status = 'active', 
                revert_reason = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("dsi", $new_remaining, $reason, $batch_id);
    } else {
        // تحديث الرصيد فقط
        $stmt = $conn->prepare("
            UPDATE batches 
            SET remaining = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("di", $new_remaining, $batch_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("فشل في تحديث الدفعة: " . $stmt->error);
    }
    $stmt->close();
}

/**
 * إنشاء تخصيص عكسي
 */
function createReverseAllocation($conn, $allocation, $qty_to_return, $sale_item_id, $user_id, $return_id) {
    $stmt = $conn->prepare("
        INSERT INTO sale_item_allocations
        (sale_item_id, batch_id, qty, unit_cost, line_cost, created_by, return_id, is_return)
        VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
    ");
    
    $reverse_qty = -$qty_to_return;
    $line_cost = $reverse_qty * $allocation['unit_cost'];
    
    $stmt->bind_param(
        "iidddii",
        $sale_item_id,
        $allocation['batch_id'],
        $reverse_qty,
        $allocation['unit_cost'],
        $line_cost,
        $user_id,
        $return_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception("فشل في إنشاء التخصيص العكسي: " . $stmt->error);
    }
    $stmt->close();
}

/**
 * إنشاء سجل return_item
 */
function createReturnItem($conn, $return_id, $return_item, $batch_allocations_data) {
    $stmt = $conn->prepare("
        INSERT INTO return_items
        (return_id, invoice_item_id, product_id, quantity, 
         return_price, total_amount, batch_allocations, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $batch_allocations_json = json_encode($batch_allocations_data, JSON_UNESCAPED_UNICODE);
    $total_amount = $return_item['return_qty'] * $return_item['unit_price_after_discount'];
    
    $stmt->bind_param(
        "iiiddss",
        $return_id,
        $return_item['invoice_item_id'],
        $return_item['product_id'],
        $return_item['return_qty'],
        $return_item['unit_price_after_discount'],
        $total_amount,
        $batch_allocations_json
    );
    
    if (!$stmt->execute()) {
        throw new Exception("فشل في إنشاء سجل البند المرتجع: " . $stmt->error);
    }
    $stmt->close();
}

/**
 * معالجة إرجاع الدفعات (FIFO العكسي)
 */
function processBatchReturns($conn, $return_id, &$return_items, $user_id) {
    foreach ($return_items as &$return_item) {
        $invoice_item_id = $return_item['invoice_item_id'];
        $return_qty = $return_item['return_qty'];
        $remaining_qty = $return_qty;
        
        // الحصول على تخصيصات البيع لهذا البند مرتبة تنازلياً (الأحدث أولاً)
        $allocations = getSaleItemAllocations($conn, $invoice_item_id);
        
        $batch_allocations_data = [];
        $total_return_cost = 0;
        
        // إرجاع الكمية من كل تخصيص (الأحدث أولاً)
        foreach ($allocations as $allocation) {
            if ($remaining_qty <= 0) break;
            
            $batch_id = $allocation['batch_id'];
            $allocated_qty = (float)$allocation['qty'];
            $unit_cost = (float)$allocation['unit_cost'];
            $batch_status = $allocation['batch_status'];
            $batch_remaining = (float)$allocation['batch_remaining'];
            
            // الكمية التي يمكن إرجاعها من هذا التخصيص
            $qty_to_return = min($allocated_qty, $remaining_qty);
            
            // تحديث الدفعة
            updateBatch($conn, $batch_id, $batch_status, $batch_remaining, $qty_to_return, $return_item['reason']);
            
            // إنشاء تخصيص عكسي
            createReverseAllocation($conn, $allocation, $qty_to_return, $invoice_item_id, $user_id, $return_id);
            
            // تخزين بيانات التخصيص
            $batch_allocations_data[] = [
                'batch_id' => $batch_id,
                'qty' => $qty_to_return,
                'unit_cost' => $unit_cost
            ];
            
            // حساب التكلفة الإجمالية المرتجعة
            $total_return_cost += ($qty_to_return * $unit_cost);
            
            $remaining_qty -= $qty_to_return;
        }
        
        // تحديث تكلفة الإرجاع للبند
        $return_item['total_return_cost'] = $total_return_cost;
        $return_item['batch_allocations'] = $batch_allocations_data;
        
        // إنشاء سجل return_item
        createReturnItem($conn, $return_id, $return_item, $batch_allocations_data);
    }
}
/**
 * معالجة إرجاع الدفعات حسب الاستراتيجية المطلوبة
 */


/**
 * تحديث بنود الفاتورة
 */
function updateInvoiceItems($conn, $return_items) {
    foreach ($return_items as $item) {
        // تحديث الكمية المرتجعة
        $stmt = $conn->prepare("
            UPDATE invoice_out_items 
            SET returned_quantity = returned_quantity + ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("di", $item['return_qty'], $item['invoice_item_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("فشل في تحديث بند الفاتورة: " . $stmt->error);
        }
        $stmt->close();
        
        // تحديث تكلفة الوحدة (cost_price_per_unit) إذا لزم
        updateCostPricePerUnit($conn, $item['invoice_item_id']);
    }
}

/**
 * تحديث تكلفة الوحدة للبند
 */
function updateCostPricePerUnit($conn, $invoice_item_id) {
    // حساب متوسط التكلفة بناءً على التخصيصات المتبقية
    $stmt = $conn->prepare("
        SELECT SUM(sia.qty) as total_qty, 
               SUM(sia.line_cost) as total_cost
        FROM sale_item_allocations sia
        WHERE sia.sale_item_id = ? AND sia.qty > 0
    ");
    $stmt->bind_param("i", $invoice_item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if ($data && $data['total_qty'] > 0) {
        $new_cost_per_unit = $data['total_cost'] / $data['total_qty'];
        
        $stmt = $conn->prepare("
            UPDATE invoice_out_items 
            SET cost_price_per_unit = ?
            WHERE id = ?
        ");
        $stmt->bind_param("di", $new_cost_per_unit, $invoice_item_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * تحديث الفاتورة الرئيسية
 */
function updateMainInvoice($conn, $invoice_id, $total_return_amount) {
    // حساب التكلفة الإجمالية المرتجعة
    $stmt = $conn->prepare("
        SELECT SUM(sia.line_cost) as total_return_cost
        FROM sale_item_allocations sia
        WHERE sia.return_id IN (
            SELECT id FROM returns WHERE invoice_id = ?
        ) AND sia.is_return = TRUE
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cost_data = $result->fetch_assoc();
    $stmt->close();
    
    $total_return_cost = $cost_data['total_return_cost'] ?? 0;
    
    // تحديث التكلفة الإجمالية والربح
    $stmt = $conn->prepare("
        UPDATE invoices_out 
        SET total_cost = total_cost - ?,
            total_after_discount = total_after_discount - ?,
            profit_amount = (total_after_discount - ?) - (total_cost - ?),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("ddddi", 
        $total_return_cost, 
        $total_return_amount,
        $total_return_amount,
        $total_return_cost,
        $invoice_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception("فشل في تحديث الفاتورة: " . $stmt->error);
    }
    $stmt->close();
    
    // التحقق إذا كانت الفاتورة أصبحت مرتجعة بالكامل
    checkIfInvoiceFullyReturned($conn, $invoice_id);
}

/**
 * التحقق إذا كانت الفاتورة مرتجعة بالكامل
 */
function checkIfInvoiceFullyReturned($conn, $invoice_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_items,
               SUM(CASE WHEN returned_quantity = quantity THEN 1 ELSE 0 END) as fully_returned
        FROM invoice_out_items
        WHERE invoice_out_id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if ($data['total_items'] > 0 && $data['fully_returned'] == $data['total_items']) {
        // تحديث حالة الفاتورة إلى reverted
        $stmt = $conn->prepare("
            UPDATE invoices_out 
            SET delivered = 'reverted',
                revert_reason = 'إرجاع كامل للفاتورة',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * معالجة الفاتورة المؤجلة
 */
function handleDeferredInvoice($conn, $invoice, $invoice_id, $total_return_amount) {
    // تحديث المتبقي في الفاتورة
    $new_remaining = max(0, $invoice['remaining_amount'] - $total_return_amount);
    
    $stmt = $conn->prepare("
        UPDATE invoices_out 
        SET remaining_amount = ?
        WHERE id = ?
    ");
    $stmt->bind_param("di", $new_remaining, $invoice_id);
    $stmt->execute();
    $stmt->close();
    
    // تحديث رصيد العميل
    $new_balance = $invoice['balance'] - $total_return_amount;
    updateCustomerBalance($conn, $invoice['customer_id'], $new_balance);
}

/**
 * معالجة الفاتورة المدفوعة كلياً
 */
function handleFullyPaidInvoice($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $items, $user_id) {
    // تحديد طريقة الاسترداد (نأخذ الأولى من البنود)
    $refund_preference = isset($items[0]['refund_preference']) ? $items[0]['refund_preference'] : 'wallet';
    
    if ($refund_preference === 'cash') {
        // إنشاء سجل دفع سالب
        createNegativePayment($conn, $invoice_id, $total_return_amount, $return_id, $user_id);
    } elseif ($refund_preference === 'wallet') {
        // إضافة إلى محفظة العميل
        $new_wallet = $invoice['wallet'] + $total_return_amount;
        updateCustomerWallet($conn, $invoice['customer_id'], $new_wallet, $return_id, $total_return_amount, $user_id);
    }
    
    // تحديث المدفوع في الفاتورة
    $new_paid = max(0, $invoice['paid_amount'] - $total_return_amount);
    
    $stmt = $conn->prepare("
        UPDATE invoices_out 
        SET paid_amount = ?
        WHERE id = ?
    ");
    $stmt->bind_param("di", $new_paid, $invoice_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * معالجة الفاتورة المدفوعة جزئياً
 */
function handlePartiallyPaidInvoice($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $items, $user_id) {
    $paid_amount = (float)$invoice['paid_amount'];
    $remaining_amount = (float)$invoice['remaining_amount'];
    
    // الجزء الذي سيتم استرداده من المدفوع
    $refund_from_paid = min($total_return_amount, $paid_amount);
    $credit_to_remaining = max(0, $total_return_amount - $refund_from_paid);
    
    // تحديث المدفوع والمتبقي
    $new_paid = max(0, $paid_amount - $refund_from_paid);
    $new_remaining = max(0, $remaining_amount - $credit_to_remaining);
    
    $stmt = $conn->prepare("
        UPDATE invoices_out 
        SET paid_amount = ?,
            remaining_amount = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ddi", $new_paid, $new_remaining, $invoice_id);
    $stmt->execute();
    $stmt->close();
    
    // إذا كان هناك جزء مسترد من المدفوع
    if ($refund_from_paid > 0) {
        $refund_preference = isset($items[0]['refund_preference']) ? $items[0]['refund_preference'] : 'wallet';
        
        if ($refund_preference === 'cash') {
            createNegativePayment($conn, $invoice_id, $refund_from_paid, $return_id, $user_id);
        } elseif ($refund_preference === 'wallet') {
            $new_wallet = $invoice['wallet'] + $refund_from_paid;
            updateCustomerWallet($conn, $invoice['customer_id'], $new_wallet, $return_id, $refund_from_paid, $user_id);
        }
    }
    
    // تحديث رصيد العميل إذا كان هناك تخفيض من المتبقي
    if ($credit_to_remaining > 0) {
        $new_balance = $invoice['balance'] - $credit_to_remaining;
        updateCustomerBalance($conn, $invoice['customer_id'], $new_balance);
    }
}

/**
 * المعالجة المالية
 */
function handleFinancialTransactions($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $items, $user_id) {
    $paid_amount = (float)$invoice['paid_amount'];
    $remaining_amount = (float)$invoice['remaining_amount'];
    
    if ($paid_amount == 0) {
        // فاتورة مؤجلة: خصم من المتبقي فقط
        handleDeferredInvoice($conn, $invoice, $invoice_id, $total_return_amount);
    } elseif ($remaining_amount == 0) {
        // فاتورة مدفوعة كلياً
        handleFullyPaidInvoice($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $items, $user_id);
    } else {
        // فاتورة مدفوعة جزئياً
        handlePartiallyPaidInvoice($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $items, $user_id);
    }
}

/**
 * إنشاء دفع سالب (للاسترداد النقدي)
 */
function createNegativePayment($conn, $invoice_id, $refund_amount, $return_id, $user_id) {
    $stmt = $conn->prepare("
        INSERT INTO invoice_payments
        (invoice_id, payment_amount, payment_date, payment_method, 
         notes, created_by, created_at)
        VALUES (?, ?, NOW(), 'cash', ?, ?, NOW())
    ");
    
    $negative_amount = -$refund_amount;
    $notes = "استرداد نقدي للمرتجع #{$return_id}";
    
    $stmt->bind_param("idsi", 
        $invoice_id, 
        $negative_amount, 
        $notes, 
        $user_id
    );
    
    $stmt->execute();
    $stmt->close();
}

/**
 * تحديث محفظة العميل
 */
function updateCustomerWallet($conn, $customer_id, $new_wallet, $return_id, $amount, $user_id) {
    $stmt = $conn->prepare("
        UPDATE customers 
        SET wallet = ?
        WHERE id = ?
    ");
    $stmt->bind_param("di", $new_wallet, $customer_id);
    $stmt->execute();
    $stmt->close();
    
    // تسجيل حركة المحفظة
    logWalletTransaction($conn, $customer_id, $new_wallet - $amount, $new_wallet, $amount, $return_id, $user_id);
}

/**
 * تحديث رصيد العميل
 */
function updateCustomerBalance($conn, $customer_id, $new_balance) {
    $stmt = $conn->prepare("
        UPDATE customers 
        SET balance = ?
        WHERE id = ?
    ");
    $stmt->bind_param("di", $new_balance, $customer_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * تسجيل حركة المحفظة
 */
function logWalletTransaction($conn, $customer_id, $wallet_before, $wallet_after, $amount, $return_id, $user_id) {
    $stmt = $conn->prepare("
        INSERT INTO wallet_transactions
        (customer_id, type, amount, description, 
         wallet_before, wallet_after, transaction_date, created_by)
        VALUES (?, 'refund', ?, ?, ?, ?, NOW(), ?)
    ");
    
    $description = "استرداد إلى المحفظة للمرتجع #{$return_id}";
    
    $stmt->bind_param("idsddi",
        $customer_id,
        $amount,
        $description,
        $wallet_before,
        $wallet_after,
        $user_id
    );
    
    $stmt->execute();
    $stmt->close();
}

/**
 * تسجيل حركة العميل
 */
function logCustomerTransaction($conn, $invoice, $invoice_id, $return_id, $amount, $type, $user_id) {
    $stmt = $conn->prepare("
        INSERT INTO customer_transactions
        (customer_id, transaction_type, amount, description,
         invoice_id, return_id, balance_before, balance_after,
         wallet_before, wallet_after, transaction_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    $description = "إرجاع فاتورة #{$invoice_id} - مرتجع #{$return_id}";
    $balance_before = $invoice['balance'];
    $balance_after = $invoice['balance'] - $amount;
    $wallet_before = $invoice['wallet'];
    $wallet_after = $invoice['wallet'];
    
    $stmt->bind_param("isdiiiddddi",
        $invoice['customer_id'],
        $type,
        $amount,
        $description,
        $invoice_id,
        $return_id,
        $balance_before,
        $balance_after,
        $wallet_before,
        $wallet_after,
        $user_id
    );
    
    $stmt->execute();
    $stmt->close();
}

/**
 * إنشاء عملية إرجاع جديدة (الدالة الرئيسية)
 */
function createReturn($conn, $data, $user_id, $user_role) {
    try {
        // التحقق من الصلاحيات
        checkReturnPermissions($user_role);
        
        // التحقق من البيانات المدخلة
        validateReturnInput($data);
        
        // بدء transaction
        $conn->begin_transaction();
        
        // قفل الفاتورة والعميل
        $invoice = lockInvoiceAndCustomer($conn, $data['invoice_id'], $data['customer_id']);
        
        // إذا كان إرجاع كامل، جلب كل البنود المتاحة
        if ($data['return_type'] === 'full') {
            $data['items'] = getAllReturnableItems($conn, $data['invoice_id']);
        }
        
        // التحقق من صحة البنود
        $return_items = validateReturnItems($conn, $data['invoice_id'], $data['items']);
        
        // حساب المبالغ الإجمالية
        $totals = calculateReturnAmounts($return_items);
        
        // إنشاء سجل الإرجاع
        $return_id = createReturnRecord($conn, $data, $totals['total_return_amount'], $user_id, $user_role);
        
        // معالجة كل بند وإرجاع الدفعات
        processBatchReturns($conn, $return_id, $return_items, $user_id);
        
        // تحديث بنود الفاتورة
        updateInvoiceItems($conn, $return_items);
        
        // تحديث الفاتورة الرئيسية
        updateMainInvoice($conn, $data['invoice_id'], $totals['total_return_amount']);
        
        // المعالجة المالية
        handleFinancialTransactions($conn, $invoice, $data['invoice_id'], $return_id, $totals['total_return_amount'], $data['items'], $user_id);
        
        // تسجيل حركة العميل
        logCustomerTransaction($conn, $invoice, $data['invoice_id'], $return_id, $totals['total_return_amount'], 'return', $user_id);
        
        // التأكد من أن كل شيء تم بنجاح
        $conn->commit();
        
        return [
            'success' => true,
            'return_id' => $return_id,
            'total_amount' => $totals['total_return_amount'],
            'message' => 'تم إنشاء عملية الإرجاع بنجاح'
        ];
        
    } catch (Exception $e) {
        // التراجع عن العملية في حالة الخطأ
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?>