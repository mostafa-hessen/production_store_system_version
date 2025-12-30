<?php

function checkReturnPermissions($user_role)
{
    // السماح لأي مستخدم مسجل بإنشاء طلب إرجاع كـ pending.
    // المسؤول (admin) يستطيع إنشاؤه والموافقة عليه مباشرة.
    // لو عندك أدوار أخرى وتريد تقييدها - ضيفها هنا.
    $allowed_roles = ['admin', 'staff', 'user']; // عدّل حسب أدوارك
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('ليس لديك صلاحية لإنشاء عمليات إرجاع');
    }
    return true;
}
// أضف هذا في بداية الدالة:
function logCustomerTransaction($conn, $invoice, $return_id, $amount, $user_id, $payment_details = []) {
  
    
    // بناء الوصف التفصيلي
    $description = "مرتجع   فاتورة رقم {$invoice['id']} ";
    
    // إذا كانت مرتبطة بشغلانة
    if ($invoice['work_order_id']) {
        $stmt = $conn->prepare("SELECT title, id FROM work_orders WHERE id = ?");
        $stmt->bind_param("i", $invoice['work_order_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $wo = $result->fetch_assoc();
            $description .= " - شغلانة رقم: {$wo['id']} باسم: {$wo['title']}";
        }
        $stmt->close();
    }
    
    // إضافة تفاصيل المدفوعات
    if (!empty($payment_details)) {
        $description .= " - تفاصيل الاسترداد: ";
        
        if ($payment_details['method'] === 'cash') {
            $description .= "تم الرد نقدي بمبلغ {$payment_details['amount']} جنيه";
        } elseif ($payment_details['method'] === 'wallet') {
            $description .= "تم الإضافة للمحفظة بمبلغ {$payment_details['amount']} جنيه";
            if (isset($payment_details['wallet_before']) && isset($payment_details['wallet_after'])) {
                $description .= " (قبل: {$payment_details['wallet_before']}، بعد: {$payment_details['wallet_after']})";
            }
        } elseif ($payment_details['method'] === 'credit_adjustment') {
            $description .= "تم التعديل على الرصيد";
            if (isset($payment_details['from_remaining'])) {
                $description .= " - خصم من المتبقي: {$payment_details['from_remaining']} جنيه";
            }
            if (isset($payment_details['added_to_balance'])) {
                $description .= " - إضافة للرصيد: {$payment_details['added_to_balance']} جنيه";
            }
        }
        
        // إذا كان هناك جزء من المدفوع وجزء من المتبقي
        if (isset($payment_details['from_remaining']) && $payment_details['from_remaining'] > 0) {
            $description .= " - خصم من المتبقي: {$payment_details['from_remaining']} جنيه";
        }
        // if (isset($payment_details['amount']) && $payment_details['amount'] > 0 && 
        //     $payment_details['method'] !== 'credit_adjustment') {
        //     $description .= " - استرداد مباشر: {$payment_details['amount']} جنيه";
        // }


        $description .= ". رقم فاتورة المرتجع: {$return_id}";
    }
    
    // المبلغ الإجمالي للإرجاع
   
        $description .= " - إجمالي المرتجع: {$amount} جنيه";

$stmt = $conn->prepare("
    INSERT INTO customer_transactions
    (customer_id, transaction_type, amount, description,
     invoice_id, return_id, balance_before, balance_after,
     wallet_before, wallet_after, transaction_date, created_by)
    VALUES (?, 'return', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
");

$balance_before = (float)$invoice['balance'];
$balance_after  = $balance_before - $amount;

$wallet_before  = (float)$invoice['wallet'];
$wallet_after   = isset($payment_details['wallet_after'])
    ? (float)$payment_details['wallet_after']
    : $wallet_before;

$finalAmount = -abs($amount); // تأكيد إنه سالب

$stmt->bind_param(
    "idsiiddddi",
    $invoice['customer_id'], // i
    $finalAmount,            // d
    $description,            // s
    $invoice['id'],          // i
    $return_id,              // i
    $balance_before,         // d
    $balance_after,          // d
    $wallet_before,          // d
    $wallet_after,           // d
    $user_id                 // i
);

$stmt->execute();
$stmt->close();
}

/**
 * تسجيل حركة المحفظة مع وصف واضح
 */

function updateWorkOrderTotals($conn, $invoice_id) {
    $stmt = $conn->prepare("SELECT work_order_id FROM invoices_out WHERE id=? AND delivered NOT IN ('canceled')");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $work_order_id = $result['work_order_id'] ?? null;
    if (!$work_order_id) return;

    // حساب المجاميع مع استثناء الفواتير الملغاة
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN delivered NOT IN ('canceled') THEN total_after_discount ELSE 0 END) AS total_invoice_amount,
            SUM(paid_amount) AS total_paid,
            SUM(CASE WHEN delivered NOT IN ('canceled') THEN total_after_discount - paid_amount ELSE 0 END) AS total_remaining_calculated
        FROM invoices_out
        WHERE work_order_id=? 
    ");
    $stmt->bind_param("i", $work_order_id);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $totalInvoiceAmount = $totals['total_invoice_amount'] ?? 0;
    $totalPaid = $totals['total_paid'] ?? 0;
    $totalRemaining = $totals['total_remaining_calculated'] ?? 0;
    $workOrderId = $work_order_id;

    // تحديث جدول work_orders
    $stmt = $conn->prepare("
        UPDATE work_orders 
        SET 
            total_invoice_amount = ?,
            total_paid = ?,
            total_remaining = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("dddi", $totalInvoiceAmount, $totalPaid, $totalRemaining, $workOrderId);
    $stmt->execute();
    $stmt->close();
}

function buildReturnDescriptionWithWorkOrderSimple($conn, $invoice_id) {
    $stmt = $conn->prepare("
        SELECT i.id, i.work_order_id, w.title 
        FROM invoices_out i
        LEFT JOIN work_orders w ON i.work_order_id = w.id
        WHERE i.id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return "مرتجع فاتورة رقم {$invoice_id}";
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['work_order_id']) {
        $work_order_title = $row['title'] ? "شغلانة: {$row['title']}" : "شغلانة رقم: {$row['work_order_id']}";
        return "مرتجع فاتورة رقم {$invoice_id} - {$work_order_title}";
    }
    
    return "مرتجع فاتورة رقم {$invoice_id}";
}

/**
 * التحقق من وجود المتغيرات المطلوبة في عنصر الإرجاع
 */
function validateReturnItemStructure($item)
{
    $required_fields = ['invoice_item_id', 'return_qty', 'unit_price_after_discount', 'product_id'];

    foreach ($required_fields as $field) {
        if (!isset($item[$field])) {
            throw new Exception("الحقل المطلوب '{$field}' غير موجود في بيانات البند");
        }
    }

    if ($item['return_qty'] <= 0) {
        throw new Exception("كمية الإرجاع يجب أن تكون أكبر من صفر");
    }
   


    return true;
}
function applyReturnAllocations($conn, $allocation, $qty_to_return, $sale_item_id, $user_id, $return_id)
{
    // تحديث سجل التخصيص الأصلي
    $new_qty = $allocation['qty'] - $qty_to_return;

    $stmt = $conn->prepare("
        UPDATE sale_item_allocations 
        SET qty = ?, 
            line_cost = qty * unit_cost,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("di", $new_qty, $allocation['id']);

    if (!$stmt->execute()) {
        throw new Exception("فشل في تحديث تخصيص البيع: " . $stmt->error);
    }
    $stmt->close();

    // إذا كان البند كاملاً مرتجعاً، نحتاج إلى تسجيل أنه مرتجع
    if ($new_qty == 0) {
        $stmt = $conn->prepare("
            UPDATE sale_item_allocations 
            SET is_return = 1,
                return_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $return_id, $allocation['id']);
        $stmt->execute();
        $stmt->close();
    }

    return [
        'allocation_id' => $allocation['id'],
        'returned_qty' => $qty_to_return,
        'unit_cost' => $allocation['unit_cost'],
        'new_qty' => $new_qty
    ];
}

function recalcInvoiceTotals($conn, $invoice_id)
{
    $stmt = $conn->prepare("
        SELECT id, quantity, returned_quantity, unit_price_after_discount, cost_price_per_unit
        FROM invoice_out_items
        WHERE invoice_out_id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_cost = 0;
    $total_after_discount = 0;

    foreach ($items as $item) {
        $effective_qty = $item['quantity'] - $item['returned_quantity'];
        $total_cost += $item['cost_price_per_unit'] * $effective_qty;
        $total_after_discount += $item['unit_price_after_discount'] * $effective_qty;
    }

    $profit_amount = $total_after_discount - $total_cost;

    $stmt = $conn->prepare("
        UPDATE invoices_out
        SET total_cost=?, total_after_discount=?, profit_amount=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->bind_param("dddi", $total_cost, $total_after_discount, $profit_amount, $invoice_id);
    $stmt->execute();
    $stmt->close();
}



/**
 * التحقق من البيانات المدخلة
 */
function validateReturnInput($data)
{
    if (!isset($data['invoice_id']) || !isset($data['customer_id'])) {
        throw new Exception('بيانات غير صحيحة. يرجى التأكد من إدخال invoice_id و customer_id');
    }

    if (!isset($data['return_type']) || !in_array($data['return_type'], ['partial', 'full', 'exchange'])) {
        throw new Exception('نوع الإرجاع غير صحيح. يجب أن يكون partial أو full أو exchange');
    }

    // ✅ إضافة التحقق من refund_preference
    if (!isset($data['refund_preference']) || !in_array($data['refund_preference'], ['cash', 'wallet', 'credit_adjustment', 'auto'])) {
        throw new Exception('طريقة الاسترداد غير صحيحة. يجب أن تكون cash أو wallet أو credit_adjustment أو auto');
    }

    if (empty($data['items']) && $data['return_type'] !== 'full') {
        throw new Exception('يجب تحديد البنود المراد إرجاعها');
    }

    return true;
}
/**
 * قفل الفاتورة والعميل
 */
function lockInvoiceAndCustomer($conn, $invoice_id, $customer_id)
{
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
function getAllReturnableItems($conn, $invoice_id)
{
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
                'return_qty' => (float)$row['available_for_return'],
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
function validateReturnItems($conn, $invoice_id, $items)
{
    $return_items = [];

    foreach ($items as $item) {
        if (!isset($item['invoice_item_id']) || !isset($item['return_qty']) || $item['return_qty'] <= 0) {
            throw new Exception('بيانات البند غير صحيحة');
        }

        $invoice_item_id = (int)$item['invoice_item_id'];
        $return_qty = (float)$item['return_qty'];

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
  


        $return_items[] = [
            'invoice_item' => $invoice_item,
            'invoice_item_id' => $invoice_item_id,
            'product_id' => (int)$invoice_item['product_id'],
            'return_qty' => $return_qty,
            'unit_price_after_discount' => (float)($invoice_item['unit_price_after_discount']),
            'reason' => isset($item['reason']) ? $item['reason'] : '',
            'batch_allocations' => []
        ];
    }

    return $return_items;
}

/**
 * حساب المبالغ الإجمالية للإرجاع
 */
function calculateReturnAmounts(&$return_items)
{
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
function createReturnRecord($conn, $data, $total_amount, $user_id, $user_role)
{
    $stmt = $conn->prepare("
        INSERT INTO returns 
        (invoice_id, customer_id, return_date, total_amount, return_type, 
         status, reason, approved_by, approved_at, created_by)
        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, NOW(), ?)
    ");

    if (!$stmt) {
        throw new Exception("فشل في إعداد الاستعلام: " . $conn->error);
    }

    // تحديد القيم
    $status = $user_role === 'admin' ? 'approved' : 'pending';
    $approved_by_value = $user_role === 'admin' ? $user_id : 0; // لو مش admin نخليها 0
    $reason = isset($data['reason']) ? $data['reason'] : '';

    // bind_param: i = integer, d = double, s = string
    // التصحيح:
$stmt->bind_param(
    "iidsssii",  // ✅ تصحيح: iidsssii بدلاً من iidssiii
    $data['invoice_id'],   // i
    $data['customer_id'],  // i
    $total_amount,         // d
    $data['return_type'],  // s
    $status,               // s
    $reason,               // s  ← السبب هنا
    $approved_by_value,    // i
    $user_id               // i  ← هذا صحيح الآن
);

    if (!$stmt->execute()) {
        throw new Exception("فشل في إنشاء سجل الإرجاع: " . $stmt->error);
    }

    return $stmt->insert_id; // ترجع id للسجل الجديد
}


/**
 * الحصول على تخصيصات البيع للبند
 */
function getSaleItemAllocations($conn, $sale_item_id)
{
    $stmt = $conn->prepare("
        SELECT sia.*, b.status as batch_status, b.remaining as batch_remaining
        FROM sale_item_allocations sia
        JOIN batches b ON sia.batch_id = b.id
        WHERE sia.sale_item_id = ? AND sia.qty > 0 AND sia.is_return = 0
        ORDER BY sia.id DESC
    ");

    if (!$stmt) {
        throw new Exception("فشل في تحضير الاستعلام: " . $conn->error);
    }

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
 * 
 * 
 * تحديث الدفعة
 */
function updateBatch($conn, $batch_id, $batch_status, $batch_remaining, $qty_to_return, $reason)
{
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


/**
 * إنشاء سجل return_item
 */
function createReturnItem($conn, $return_id, $return_item, $batch_allocations_data, $return_status = 'pending')
{
    // التحقق من بنية البيانات
    validateReturnItemStructure($return_item);

    // تحديد حالة البند ووقت التخزين إذا تمت الموافقة
    $status = 'pending';
    $restocked_at = null;
    $restocked_qty = 0;

    if ($return_status === 'approved') {
        $status = 'restocked';
        $restocked_at = date('Y-m-d H:i:s');
        $restocked_qty = $return_item['return_qty']; // ⬅ هنا
    }

    $stmt = $conn->prepare("
        INSERT INTO return_items
        (return_id, invoice_item_id, product_id, quantity, 
         return_price, total_amount, batch_allocations, status, restocked_at, restocked_qty)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $batch_allocations_json = json_encode($batch_allocations_data, JSON_UNESCAPED_UNICODE);
    $total_amount = $return_item['return_qty'] * $return_item['unit_price_after_discount'];

    $stmt->bind_param(
        "iiiddssssd",  // ⬅ تغيير هنا: sssd بدلاً من ssss
        $return_id,
        $return_item['invoice_item_id'],
        $return_item['product_id'],
        $return_item['return_qty'],
        $return_item['unit_price_after_discount'],
        $total_amount,
        $batch_allocations_json,
        $status,
        $restocked_at,
        $restocked_qty  // ⬅ إضافة المعلمة
    );

    if (!$stmt->execute()) {
        throw new Exception("فشل في إنشاء سجل البند المرتجع: " . $stmt->error);
    }
    $stmt->close();
}
/**
 * معالجة إرجاع الدفعات (FIFO العكسي)
 */
function processBatchReturns($conn, $return_id, &$return_items, $user_id, $return_status = 'pending')
{
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

            // تعديل التخصيص الأصلي
            applyReturnAllocations($conn, $allocation, $qty_to_return, $invoice_item_id, $user_id, $return_id);

            // تخزين بيانات التخصيص
            $batch_allocations_data[] = [
                'batch_id' => $batch_id,
                'qty' => $qty_to_return,
                'unit_cost' => $unit_cost,
                'allocation_id' => $allocation['id']
            ];

            // حساب التكلفة الإجمالية المرتجعة
            $total_return_cost += ($qty_to_return * $unit_cost);

            $remaining_qty -= $qty_to_return;
        }

        // تحديث تكلفة الإرجاع للبند
        $return_item['total_return_cost'] = $total_return_cost;
        $return_item['batch_allocations'] = $batch_allocations_data;

        // إنشاء سجل return_item
        createReturnItem($conn, $return_id, $return_item, $batch_allocations_data, $return_status);
    }
}
/**
 * معالجة إرجاع الدفعات حسب الاستراتيجية المطلوبة
 */


/**
 * تحديث بنود الفاتورة
 */
function updateInvoiceItems($conn, $return_items)
{
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

        //  checkIfInvoiceFullyReturned($conn, $item['invoice_item_id']);
        // تحديث تكلفة الوحدة (cost_price_per_unit) إذا لزم
        updateCostPricePerUnit($conn, $item['invoice_item_id']);
    }
}

/**
 * تحديث تكلفة الوحدة للبند
 */
function updateCostPricePerUnit($conn, $invoice_item_id)
{
    $stmt = $conn->prepare("
        SELECT SUM(qty) as total_qty, SUM(qty * unit_cost) as total_cost
        FROM sale_item_allocations
        WHERE sale_item_id = ? AND qty > 0
    ");
    $stmt->bind_param("i", $invoice_item_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($data['total_qty'] > 0) {
        $new_cost = $data['total_cost'] / $data['total_qty'];
        $stmt = $conn->prepare("UPDATE invoice_out_items SET cost_price_per_unit=? WHERE id=?");
        $stmt->bind_param("di", $new_cost, $invoice_item_id);
        $stmt->execute();
        $stmt->close();
    }
}


/**
 * تحديث الفاتورة الرئيسية
 */


/**
 * التحقق إذا كانت الفاتورة مرتجعة بالكامل
 */
function checkIfInvoiceFullyReturned($conn, $invoice_id)
{
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
function handleDeferredInvoice($conn, $invoice, $invoice_id, $total_return_amount)
{
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

function handleFinancialTransactions($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $items, $user_id, $refund_preference) {
    $paid_amount = (float)$invoice['paid_amount'];
    $remaining_amount = (float)$invoice['remaining_amount'];
    $total_after_discount = (float)$invoice['total_after_discount'];
    
    $payment_details = [];
    
    if ($paid_amount == 0) {
        // فاتورة مؤجلة
        handleDeferredInvoice($conn, $invoice, $invoice_id, $total_return_amount);
        $payment_details = [
            'method' => 'credit_adjustment',
            'from_remaining' => $total_return_amount
        ];
    } elseif ($remaining_amount == 0 && $paid_amount == $total_after_discount) {
        // فاتورة مدفوعة كلياً
        $payment_details = handleFullyPaidInvoice($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $refund_preference, $user_id);
    } else {
        // فاتورة مدفوعة جزئياً
        $payment_details = handlePartiallyPaidInvoice($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $refund_preference, $user_id);
    }
    
    return $payment_details;
}

/**
 * تعديل handleFullyPaidInvoice لقبول refund_preference مباشرة
 */
function handleFullyPaidInvoice($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $refund_preference, $user_id) {
    $payment_details = [];
    
    if ($refund_preference === 'cash') {
        createNegativePayment($conn, $invoice_id, $total_return_amount, $return_id, $user_id, $payment_wallet=false);
        $payment_details = [
            'method' => 'cash',
            'amount' => $total_return_amount
        ];
    } elseif ($refund_preference === 'wallet') {
        createNegativePayment($conn, $invoice_id, $total_return_amount, $return_id, $user_id, $payment_wallet=true);

        $new_wallet = $invoice['wallet'] + $total_return_amount;
        updateCustomerWallet($conn, $invoice['customer_id'], $new_wallet, $return_id, $total_return_amount, $user_id);
        $payment_details = [
            'method' => 'wallet',
            'amount' => $total_return_amount,
            'wallet_before' => $invoice['wallet'],
            'wallet_after' => $new_wallet
        ];
    } elseif ($refund_preference === 'credit_adjustment') {
        // ❗ ملاحظة: فاتورة مدفوعة كلياً، لا يوجد متبقي لخصمه
        // في هذه الحالة يجب أن نضيف للرصيد كرصيد إضافي للعميل
        $new_balance = $invoice['balance'] + $total_return_amount;
        updateCustomerBalance($conn, $invoice['customer_id'], $new_balance);
        $payment_details = [
            'method' => 'credit_adjustment',
            'added_to_balance' => $total_return_amount
        ];
    } elseif ($refund_preference === 'auto') {
        // الافتراضي: نقدي إذا لم يكن هناك خيار آخر محدد
        createNegativePayment($conn, $invoice_id, $total_return_amount, $return_id, $user_id, $payment_wallet=false);
        $payment_details = [
            'method' => 'cash',
            'amount' => $total_return_amount,
            'note' => 'تم اختيار النقدي تلقائياً (auto mode)'
        ];
    }
    
    // تحديث المدفوع
    $new_paid = max(0, $invoice['paid_amount'] - $total_return_amount);
    $stmt = $conn->prepare("UPDATE invoices_out SET paid_amount=? WHERE id=?");
    $stmt->bind_param("di", $new_paid, $invoice_id);
    $stmt->execute();
    $stmt->close();
    
    return $payment_details;
}
/**
 * تعديل handlePartiallyPaidInvoice
 */
function handlePartiallyPaidInvoice($conn, $invoice, $invoice_id, $return_id, $total_return_amount, $refund_preference, $user_id) {
    $paid_amount = (float)$invoice['paid_amount'];
    $remaining_amount = (float)$invoice['remaining_amount'];
    $total_after_discount = (float)$invoice['total_after_discount'];
    
    // السيناريو 1: مرتجع من المتبقي فقط
    if ($total_return_amount <= $remaining_amount) {
        $new_remaining = $remaining_amount - $total_return_amount;
        
        $stmt = $conn->prepare("UPDATE invoices_out SET remaining_amount=? WHERE id=?");
        $stmt->bind_param("di", $new_remaining, $invoice_id);
        $stmt->execute();
        $stmt->close();
        
        // تحديث رصيد العميل فقط
        $new_balance = $invoice['balance'] - $total_return_amount;
        updateCustomerBalance($conn, $invoice['customer_id'], $new_balance);
        
        // ✅ إضافة payment_details للـ log
        $payment_details = [
            'method' => 'credit_adjustment',
            'from_remaining' => $total_return_amount
        ];
        
    } else {
        // السيناريو 2: مرتجع أكبر من المتبقي
        $amount_from_remaining = $remaining_amount;
        $amount_from_paid = $total_return_amount - $amount_from_remaining;
        
        // تحديث المدفوع والمتبقي
        $new_paid = max(0, $paid_amount - $amount_from_paid);
        $new_remaining = 0;
        
        $stmt = $conn->prepare("UPDATE invoices_out SET paid_amount=?, remaining_amount=? WHERE id=?");
        $stmt->bind_param("ddi", $new_paid, $new_remaining, $invoice_id);
        $stmt->execute();
        $stmt->close();
        
        // معالجة جزء المدفوع المرتجع
        if ($amount_from_paid > 0) {
            if ($refund_preference === 'cash') {
                createNegativePayment($conn, $invoice_id, $amount_from_paid, $return_id, $user_id , $payment_wallet=false);
            } elseif ($refund_preference === 'wallet') {
                $new_wallet = $invoice['wallet'] + $amount_from_paid;
                createNegativePayment($conn, $invoice_id, $amount_from_paid, $return_id, $user_id , $payment_wallet=true);
                updateCustomerWallet($conn, $invoice['customer_id'], $new_wallet, $return_id, $amount_from_paid, $user_id);
            }
        }
        
        // تحديث رصيد العميل للجزء المخصوم من المتبقي
        if ($amount_from_remaining > 0) {
            $new_balance = $invoice['balance'] - $amount_from_remaining;
            updateCustomerBalance($conn, $invoice['customer_id'], $new_balance);
        }
        
        // ✅ إضافة payment_details للـ log
        $payment_details = [
            'method' => $refund_preference,
            'amount' => $amount_from_paid,
            'from_remaining' => $amount_from_remaining,
            'wallet_before' => $invoice['wallet'],
            'wallet_after' => ($refund_preference === 'wallet') ? $invoice['wallet'] + $amount_from_paid : $invoice['wallet']
        ];
    }
    
    return $payment_details ?? [];
}

/**
 * إنشاء دفع سالب (للاسترداد النقدي)
 */
// function createNegativePayment($conn, $invoice_id, $refund_amount, $return_id, $user_id, $payment_wallet = false)
// {

//     $stmt = $conn->prepare("
//         INSERT INTO invoice_payments
//         (invoice_id, payment_amount, payment_date, payment_method, 
//          notes, created_by, created_at)
//         VALUES (?, ?, NOW(), ?, ?, ?, NOW())
//     ");

//     $payment_method = $payment_wallet ? 'wallet':'cash' ;

//     $negative_amount = -$refund_amount;
//  $notes = $payment_wallet 
//         ? "استرداد إلى المحفظة - مرتجع #{$return_id}" 
//         : "استرداد نقدي - مرتجع #{$return_id}";

//     $stmt->bind_param(
//         "idssi",
//         $invoice_id,
//         $negative_amount,
//         $notes,
//         $payment_method,
//         $user_id
//     );

//     $stmt->execute();
//     $stmt->close();
// }


function createNegativePayment(
    $conn,
    $invoice_id,
    $refund_amount,
    $return_id,
    $user_id,
    $payment_wallet = false
) {
    // جلب معلومات إضافية
    $stmt = $conn->prepare("
        SELECT 
            i.customer_id,
            i.work_order_id,
            c.wallet AS current_wallet,
            w.title AS work_order_title
        FROM invoices_out i
        JOIN customers c ON i.customer_id = c.id
        LEFT JOIN work_orders w ON i.work_order_id = w.id
        WHERE i.id = ?
    ");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $customer_id     = $info['customer_id'] ?? 0;
    $work_order_id   = $info['work_order_id'] ?? null;
    $work_order_title = $info['work_order_title'] ?? '';

    $wallet_before = (float)($info['current_wallet'] ?? 0);
    $wallet_after  = $payment_wallet
        ? $wallet_before + $refund_amount
        : $wallet_before;

    $payment_method = $payment_wallet ? 'wallet' : 'cash';
    $negative_amount = -abs($refund_amount);

    // بناء الوصف
    if ($payment_wallet) {
        $notes = "إضافة للمحفظة من مرتجع فاتورة #{$invoice_id}";
    } else {
        $notes = "استرداد نقدي من مرتجع فاتورة #{$invoice_id}";
    }

    // إضافة اسم الشغلانة لو موجودة
    if ($work_order_id) {
        $notes .= $work_order_title
            ? " 📋 ({$work_order_title})"
            : " 📋 (#{$work_order_id})";
    }

    // إدراج حركة الدفع السالبة
    $stmt = $conn->prepare("
        INSERT INTO invoice_payments
        (
            invoice_id,
            payment_amount,
            payment_date,
            payment_method,
            notes,
            created_by,
            created_at,
            return_id,
            work_order_id,
            wallet_before,
            wallet_after
        )
        VALUES (?, ?, NOW(), ?, ?, ?, NOW(), ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "idssiiidd",
        $invoice_id,     // i
        $negative_amount,// d
        $payment_method, // s
        $notes,          // s
        $user_id,        // i
        $return_id,      // i
        $work_order_id,  // i
        $wallet_before, // d
        $wallet_after   // d
    );

    $stmt->execute();
    $stmt->close();
}


/**
 * تحديث محفظة العميل
 */
function updateCustomerWallet($conn, $customer_id, $new_wallet, $return_id, $amount, $user_id) {
    // التحقق من وجود العميل أولاً
    $stmt = $conn->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("العميل غير موجود");
    }
    $stmt->close();
    
    // تحديث المحفظة
    $stmt = $conn->prepare("UPDATE customers SET wallet = ? WHERE id = ?");
    $stmt->bind_param("di", $new_wallet, $customer_id);
    
    if (!$stmt->execute()) {
        throw new Exception("فشل في تحديث محفظة العميل: " . $stmt->error);
    }
    $stmt->close();

    // تسجيل حركة المحفظة
    logWalletTransaction($conn, $customer_id, $new_wallet - $amount, $new_wallet, $amount, $return_id, $user_id);
}

/**
 * تحديث رصيد العميل
 */
function updateCustomerBalance($conn, $customer_id, $new_balance)
{
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
// function logWalletTransaction($conn, $customer_id, $wallet_before, $wallet_after, $amount, $return_id, $user_id)
// {
//     $stmt = $conn->prepare("
//         INSERT INTO wallet_transactions
//         (customer_id, type, amount, description, 
//          wallet_before, wallet_after, transaction_date, created_by)
//         VALUES (?, 'refund', ?, ?, ?, ?, NOW(), ?)
//     ");

//     $description = "استرداد إلى المحفظة للمرتجع #{$return_id}";

//     $stmt->bind_param(
//         "idsddi",
//         $customer_id,
//         $amount,
//         $description,
//         $wallet_before,
//         $wallet_after,
//         $user_id
//     );

//     $stmt->execute();
//     $stmt->close();
// }

function logWalletTransaction($conn, $customer_id, $wallet_before, $wallet_after, $amount, $return_id, $user_id) {
    // جلب معلومات المرتجع والفاتورة والشغلانة
    $stmt = $conn->prepare("
        SELECT 
            r.invoice_id,
            r.total_amount,
            i.work_order_id,
            w.title as work_order_title
        FROM returns r
        JOIN invoices_out i ON r.invoice_id = i.id
        LEFT JOIN work_orders w ON i.work_order_id = w.id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $return_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $info = $result->fetch_assoc();
    $stmt->close();

    $invoice_id = $info['invoice_id'] ?? 0;
    $work_order_id = $info['work_order_id'] ?? null;
    $work_order_title = $info['work_order_title'] ?? '';


    // بناء الوصف الواضح
    $description = "💰 إضافة للمحفظة - ";
    
    // إضافة اسم العميل
  
    
    // إضافة معلومات الفاتورة والمرتجع
    $description .= "فاتورة #{$invoice_id} -  رقم فاتوره المرتجع #{$return_id}";
    
    // إضافة معلومات الشغلانة إذا وجدت
    if ($work_order_id) {
             
      
            $description .= "  شغلانه بأسم:📋 ({$work_order_title})";
       
            $description .= " رقم الشغلانه:📋 (#{$work_order_id})";
        
    }
    
    // إضافة المبلغ
    $description .= " - المبلغ: {$amount} جنيه";

    // إدخال حركة المحفظة
    $stmt = $conn->prepare("
        INSERT INTO wallet_transactions
        (customer_id, type, amount, description, 
         wallet_before, wallet_after, transaction_date, created_by)
        VALUES (?, 'refund', ?, ?, ?, ?, NOW(), ?)
    ");

    $stmt->bind_param(
        "idsddi",
        $customer_id,
        $amount,
        $description,
        $wallet_before,
        $wallet_after,
        $user_id,
    );

    $stmt->execute();
    $stmt->close();
}

function createReturn($conn, $data, $user_id, $user_role)
{
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

        $return_status = ($user_role === 'admin') ? 'approved' : 'pending';

        // لو المستخدم **مش أدمن**: بس نضيف return_items بدون أي معالجة مالية أو مخزنية
        if ($user_role !== 'admin') {
            foreach ($return_items as $ri) {
                // التأكد من وجود المتغيرات المطلوبة
                if (!isset($ri['invoice_item_id']) || !isset($ri['return_qty']) || !isset($ri['unit_price_after_discount'])) {
                    throw new Exception('بيانات البند غير مكتملة');
                }

                $empty_allocations = []; // allocations فارغ
                createReturnItem($conn, $return_id, $ri, $empty_allocations);
            }

            $conn->commit();

            return [
                'success' => true,
                'return_id' => $return_id,
                'total_amount' => $totals['total_return_amount'],
                'status' => 'pending',
                'message' => 'تم إنشاء الإرجاع بانتظار مراجعة الأدمن والموافقة'
            ];
        }

        // لو أدمن: نتابع كما في الكود الأصلي
        processBatchReturns($conn, $return_id, $return_items, $user_id, $return_status);
        updateInvoiceItems($conn, $return_items);
        checkIfInvoiceFullyReturned($conn, $data['invoice_id']);
     recalcInvoiceTotals($conn, $data['invoice_id']);

     $payment_details = handleFinancialTransactions(
        $conn, 
        $invoice, 
        $data['invoice_id'], 
        $return_id, 
        $totals['total_return_amount'], 
        $data['items'], 
        $user_id,
        $data['refund_preference']
    );
     recalcInvoiceTotals($conn, $data['invoice_id']);
        updateWorkOrderTotals($conn, $data['invoice_id']); 
    // تمرير payment_details للدالة
    logCustomerTransaction($conn, $invoice, $return_id, $totals['total_return_amount'], $user_id, $payment_details);
        $conn->commit();

        return [
            'success' => true,
            'return_id' => $return_id,
            'total_amount' => $totals['total_return_amount'],
            'status' => 'approved',
            'message' => 'تم إنشاء عملية الإرجاع بنجاح'
        ];
    } catch (Exception $e) {
        // محاولة التراجع عن transaction إذا كانت نشطة
        if ($conn && !$conn->connect_error) {
            try {
                // التحقق من أننا داخل transaction
                if (method_exists($conn, 'in_transaction') && $conn->in_transaction) {
                    $conn->rollback();
                } else {
                    // محاولة التراجع يدوياً
                    $conn->query("ROLLBACK");
                }
            } catch (Exception $rollbackEx) {
                // تجاهل خطأ التراجع
                error_log("خطأ في التراجع: " . $rollbackEx->getMessage());
            }
        }

        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}


function approveReturn($conn, $return_id, $admin_user_id)
{
    try {
        // بدء transaction
        $conn->begin_transaction();

        // جلب سجل المرتجع مع قفل السطر
        $stmt = $conn->prepare("SELECT * FROM returns WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $return_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $ret = $result->fetch_assoc();
        $stmt->close();

        if (!$ret) throw new Exception("المرتجع غير موجود");
        if ($ret['status'] !== 'pending') throw new Exception("هذا المرتجع تمت معالجته مسبقًا");

        $invoice_id = $ret['invoice_id'];
        $customer_id = $ret['customer_id'];

        // جلب البنود المرتجعة
        $stmt = $conn->prepare("SELECT * FROM return_items WHERE return_id = ?");
        $stmt->bind_param("i", $return_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $return_items = [];
        while ($row = $result->fetch_assoc()) {
            $return_items[] = $row;
        }
        $stmt->close();

        $total_return_cost = 0;
        $total_return_amount = 0;

        // --- تحديث المخزون والدفعات ---
        foreach ($return_items as $item) {
            $invoice_item_id = $item['invoice_item_id'];
            $return_qty = (float)$item['quantity'];
            $remaining_qty = $return_qty;

            // جلب التخصيصات المرتبطة بالبند
            $allocations = getSaleItemAllocations($conn, $invoice_item_id);

            $batch_allocations_data = [];

            foreach ($allocations as $allocation) {
                if ($remaining_qty <= 0) break;

                $qty_to_return = min((float)$allocation['qty'], $remaining_qty);

                // تحديث المخزون
                updateBatch($conn, $allocation['batch_id'], $allocation['batch_status'], $allocation['batch_remaining'], $qty_to_return, $item['reason']);

                // إنشاء تخصيص عكسي
                applyReturnAllocations($conn, $allocation, $qty_to_return, $invoice_item_id, $admin_user_id, $return_id);

                $batch_allocations_data[] = [
                    'batch_id' => $allocation['batch_id'],
                    'qty' => $qty_to_return,
                    'unit_cost' => $allocation['unit_cost']
                ];

                $total_return_cost += $qty_to_return * $allocation['unit_cost'];
                $remaining_qty -= $qty_to_return;
            }

            // تحديث سجل return_item
            $stmt = $conn->prepare("UPDATE return_items SET status='restocked', restocked_qty=?, restocked_at=NOW(), batch_allocations=? WHERE id=?");
            $batch_allocations_json = json_encode($batch_allocations_data, JSON_UNESCAPED_UNICODE);
            $stmt->bind_param("dsi", $return_qty, $batch_allocations_json, $item['id']);
            $stmt->execute();
            $stmt->close();

            $total_return_amount += $item['unit_price_after_discount'] * $return_qty;
        }

        // --- تحديث الفاتورة الرئيسية: التكلفة والربح والمدفوع والمتبقي ---
        $stmt = $conn->prepare("SELECT id, quantity, returned_quantity, unit_price_after_discount FROM invoice_out_items WHERE invoice_out_id = ?");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $total_cost = 0;
        $total_after_discount = 0;

        foreach ($items as $item) {
            // جلب تكلفة التخصيصات
            $stmt = $conn->prepare("SELECT SUM(qty * unit_cost) as line_cost FROM sale_item_allocations WHERE sale_item_id = ? AND qty > 0");
            $stmt->bind_param("i", $item['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_assoc();
            $stmt->close();

            $line_cost = $res['line_cost'] ?? 0;
            $total_cost += $line_cost;

            $effective_qty = $item['quantity'] - $item['returned_quantity'];
            $total_after_discount += $item['unit_price_after_discount'] * $effective_qty;
        }

        $profit_amount = $total_after_discount - $total_cost;

        // تحديث الفاتورة
        $stmt = $conn->prepare("UPDATE invoices_out SET total_cost=?, total_after_discount=?, profit_amount=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("dddi", $total_cost, $total_after_discount, $profit_amount, $invoice_id);
        $stmt->execute();
        $stmt->close();

        // --- تحديث حالة المرتجع ---
        $stmt = $conn->prepare("UPDATE returns SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?");
        $stmt->bind_param("ii", $admin_user_id, $return_id);
        $stmt->execute();
        $stmt->close();

        // --- تحديث المدفوع والمتبقي للفاتورة ---
        $stmt = $conn->prepare("SELECT SUM(payment_amount) as total_paid FROM invoice_payments WHERE invoice_id=?");
        $stmt->bind_param("i", $invoice_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $paid_data = $result->fetch_assoc();
        $paid = $paid_data['total_paid'] ?? 0;
        $stmt->close();

        $remaining = $total_after_discount - $paid;

        $stmt = $conn->prepare("UPDATE invoices_out SET paid_amount=?, remaining_amount=? WHERE id=?");
        $stmt->bind_param("ddi", $paid, $remaining, $invoice_id);
        $stmt->execute();
        $stmt->close();

        // --- تحديث رصيد العميل / المحفظة ---
        $stmt = $conn->prepare("UPDATE customers SET balance = balance - ? WHERE id=?");
        $stmt->bind_param("di", $total_return_amount, $customer_id);
        $stmt->execute();
        $stmt->close();

        // --- تسجيل حركة العميل ---
        $stmt = $conn->prepare("INSERT INTO customer_transactions (customer_id, type, amount, related_id, note, created_at, created_by) VALUES (?, 'return', ?, ?, ?, NOW(), ?)");
        $note = "إرجاع فاتورة #{$invoice_id}";
        $stmt->bind_param("iddsi", $customer_id, $total_return_amount, $return_id, $note, $admin_user_id);
        $stmt->execute();
        $stmt->close();

        // --- تسجيل حركة المحفظة إذا مستخدم ---
        $stmt = $conn->prepare("INSERT INTO wallet_transactions (customer_id, type, amount, related_id, note, created_at, created_by) VALUES (?, 'return', ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("iddsi", $customer_id, $total_return_amount, $return_id, $note, $admin_user_id);
        $stmt->execute();
        $stmt->close();

        // --- تحديث الشغلانة ---
        updateWorkOrderTotals($conn, $invoice_id);

        $conn->commit();

        return [
            'success' => true,
            'message' => 'تمت الموافقة على المرتجع بنجاح'
        ];
    } catch (Exception $e) {
        if ($conn && method_exists($conn, 'in_transaction') && $conn->in_transaction) {
            $conn->rollback();
        }

        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
