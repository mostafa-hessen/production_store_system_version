<?php
// admin/download.php
session_start();

// تحقق صلاحية الوصول
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$backupDir = 'D:\\backups';
if (!isset($_GET['file'])) {
    http_response_code(400);
    exit('File required');
}

$filename = basename($_GET['file']); // تقليل خطر path traversal
$full = $backupDir . DIRECTORY_SEPARATOR . $filename;
if (!file_exists($full) || !is_file($full)) {
    http_response_code(404);
    exit('Not found');
}

// أهلية MIME + تنزيل
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($full));
readfile($full);
exit;
?>