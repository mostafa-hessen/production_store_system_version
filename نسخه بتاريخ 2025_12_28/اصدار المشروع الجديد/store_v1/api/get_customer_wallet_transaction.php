<?php
header('Content-Type: application/json; charset=utf-8');
    require_once dirname(__DIR__) . '/config.php';

// -------------------------
// 1) Validate customer_id
// -------------------------
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'customer_id مطلوب ويجب أن يكون رقماً'
    ]);
    exit;
}

$customerId = (int)$_GET['customer_id'];

// -------------------------
// Helper Functions
// -------------------------
function getWalletTypeText($type) {
    return [
        'deposit' => 'إيداع',
        'withdraw' => 'سحب',
        'refund' => 'مرتجع للمحفظة',
        'invoice_payment' => 'سداد فاتورة من المحفظة'
    ][$type] ?? $type;
}

function getWalletBadgeClass($type) {
    return [
        'deposit' => 'bg-success',
        'withdraw' => 'bg-danger',
        'refund' => 'bg-warning',
        'invoice_payment' => 'bg-primary',
    ][$type] ?? 'bg-secondary';
}

function formatWalletRow($row) {
    $amount = (float)$row['amount'];
    $isPositive = $amount >= 0;

    // التاريخ + الوقت من transaction_date
    $dateOnly = date('Y-m-d', strtotime($row['transaction_date']));
    $timeOnly = date('H:i:s', strtotime($row['transaction_date']));

    return [
        'id' => (int)$row['id'],
        'transaction_datetime' => $row['transaction_date'],
        'date' => $dateOnly,
        'time' => $timeOnly,

        'type' => $row['type'],
        'type_text' => getWalletTypeText($row['type']),
        'badge_class' => getWalletBadgeClass($row['type']),

        'description' => $row['description'],

        'amount' => $amount,
        'formatted_amount' => number_format(abs($amount), 2) . ' ج.م',
        'amount_sign' => $isPositive ? '+' : '-',
        'amount_class' => $isPositive ? 'text-success' : 'text-danger',

        'wallet_before' => (float)$row['wallet_before'],
        'wallet_after' => (float)$row['wallet_after'],

        'created_by' => $row['created_by_name'] ?? 'النظام',
        'created_at' => $row['created_at']
    ];
}


// -------------------------
// 2) Check if customer exists
// -------------------------
$check = $conn->prepare("SELECT id FROM customers WHERE id = ?");
$check->bind_param("i", $customerId);
$check->execute();
$resultCheck = $check->get_result();

if ($resultCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'العميل غير موجود'
    ]);
    exit;
}
$check->close();


// -------------------------
// 3) Build filters
// -------------------------
$params = [$customerId];
$paramTypes = "i";
$conditions = [];

if (!empty($_GET['type'])) {
    $conditions[] = "wt.type = ?";
    $params[] = $_GET['type'];
    $paramTypes .= "s";
}

if (!empty($_GET['date_from'])) {
    $conditions[] = "DATE(wt.transaction_date) >= ?";
    $params[] = $_GET['date_from'];
    $paramTypes .= "s";
}

if (!empty($_GET['date_to'])) {
    $conditions[] = "DATE(wt.transaction_date) <= ?";
    $params[] = $_GET['date_to'];
    $paramTypes .= "s";
}

if (!empty($_GET['transaction_date'])) {
    $conditions[] = "DATE(wt.transaction_date) = ?";
    $params[] = $_GET['transaction_date'];
    $paramTypes .= "s";
}


// -------------------------
// 4) Main query
// -------------------------
$sql = "
    SELECT 
        wt.*, 
        u.username AS created_by_name
    FROM wallet_transactions wt
    LEFT JOIN users u ON wt.created_by = u.id
    WHERE wt.customer_id = ?
";

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY wt.id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success'=>false,'message'=>'خطأ: '.$conn->error]);
    exit;
}

// bind dynamic params
$stmt->bind_param($paramTypes, ...$params);

$stmt->execute();
$query = $stmt->get_result();

$transactions = [];
while ($row = $query->fetch_assoc()) {
    $transactions[] = formatWalletRow($row);
}

$stmt->close();


// -------------------------
// 5) Response
// -------------------------
echo json_encode([
    'success' => true,
    'count' => count($transactions),
    'transactions' => $transactions
], JSON_UNESCAPED_UNICODE);

$conn->close();
