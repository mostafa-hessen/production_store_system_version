<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

    require_once dirname(__DIR__) . '/config.php';

// =============================
// helper: check prepare errors
// =============================
function checkStmt($stmt, $conn, $label) {
    if (!$stmt) {
        die("SQL ERROR in $label: " . $conn->error);
    }
}

// =============================
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

if ($invoice_id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "معرف الفاتورة غير صالح"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================
// 1) check invoice exists
// =============================
$check = $conn->prepare("SELECT id FROM invoices_out WHERE id = ? LIMIT 1");
checkStmt($check, $conn, "check invoice");
$check->bind_param("i", $invoice_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows == 0) {
    echo json_encode([
        "success" => false,
        "message" => "الفاتورة غير موجودة"
    ]);
    exit;
}
$check->close();


// =============================
// 2) invoice main info
// =============================
$query = "
    SELECT 
        i.*,
        DATE(i.created_at) AS date,
        TIME(i.created_at) AS time,
        c.name AS customer_name,
        u.username AS created_by_name,
    w.title AS workOrderName
,

        CASE 
            WHEN i.delivered = 'reverted' THEN 'returned'
            WHEN i.remaining_amount = 0 THEN 'paid'
            WHEN i.paid_amount > 0 AND i.remaining_amount > 0 THEN 'partial'
            ELSE 'pending'
        END AS status
    FROM invoices_out i
    LEFT JOIN work_orders w ON w.id = i.work_order_id
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN users u ON i.created_by = u.id
    WHERE i.id = ?
";

$stmt = $conn->prepare($query);
checkStmt($stmt, $conn, "invoice query");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();


// =============================
// 3) invoice items
// =============================
$itemsQuery = "
    SELECT 
        ii.*,
        p.name AS product_name,
  

        (ii.quantity - ii.returned_quantity) AS current_quantity,
        ((ii.quantity - ii.returned_quantity) * ii.selling_price) AS current_total,

        CASE 
            WHEN ii.returned_quantity = ii.quantity THEN 1 
            ELSE 0 
        END AS fullyreturned

    FROM invoice_out_items ii
    LEFT JOIN products p ON ii.product_id = p.id
    WHERE ii.invoice_out_id = ?
    ORDER BY ii.id
";

$stmtItems = $conn->prepare($itemsQuery);
checkStmt($stmtItems, $conn, "items query");
$stmtItems->bind_param("i", $invoice_id);
$stmtItems->execute();
$resItems = $stmtItems->get_result();

$items = [];
while ($row = $resItems->fetch_assoc()) {
    $row["current_quantity"] = floatval($row["current_quantity"]);
    $row["current_total"] = floatval($row["current_total"]);
    $items[] = $row;
}
$stmtItems->close();


// =============================
$invoice["items"] = $items;

$invoice["formatted_total"] = number_format($invoice["total_after_discount"], 2);
$invoice["formatted_paid"] = number_format($invoice["paid_amount"], 2);
$invoice["formatted_remaining"] = number_format($invoice["remaining_amount"], 2);


// =============================
echo json_encode([
    "success" => true,
    "invoice" => $invoice
], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

?>
