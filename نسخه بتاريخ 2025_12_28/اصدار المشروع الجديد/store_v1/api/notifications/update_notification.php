<?php
// require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__, 2) . '/config.php';

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error']);
    exit;
}

$conn->query("UPDATE notifications SET is_read = 1 WHERE id = $id");

echo json_encode(['status' => 'success']);
