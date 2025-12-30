<?php
// require_once di/rname(__DIR__) . '/config.php';
require_once dirname(__DIR__, 2) . '/config.php';

$conn->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");

echo json_encode(['status' => 'success']);
