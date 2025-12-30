<?php
// require_once __DIR__ . '/config.php';
require_once dirname(__DIR__, 2) . '/config.php';

$result = $conn->query("
    SELECT id, title, message, created_at
    FROM notifications
    WHERE is_read = 0
    ORDER BY id DESC
");

if ($result->num_rows === 0) {
    echo json_encode([
        'status' => 'empty',
        'notifications' => []
    ]);
    exit;
}

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode([
    'status' => 'success',
    'notifications' => $notifications
]);
