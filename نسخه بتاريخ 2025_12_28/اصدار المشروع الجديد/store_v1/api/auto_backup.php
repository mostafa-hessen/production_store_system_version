<?php
require_once dirname(__DIR__) . '/config.php';
file_put_contents(
    __DIR__ . '/test_cron.log',
    "Cron worked at " . date('Y-m-d H:i:s') . PHP_EOL,
    FILE_APPEND
);
$backupDir = 'D:/db/';
if (!is_dir($backupDir)) mkdir($backupDir, 0777, true);

$date = date('Y-m-d_H-i-s');
$sqlFile = $backupDir . "db_$date.sql";

$mysqldump = 'C:/xampp/mysql/bin/mysqldump.exe';
$command = "\"$mysqldump\" -u root store_v2_db > \"$sqlFile\"";

exec($command, $out, $result);

if ($result !== 0 || !file_exists($sqlFile)) {
    saveNotification($conn,
        '❌ فشل النسخ الاحتياطي',
        'حدث خطأ أثناء إنشاء النسخة الاحتياطية'
    );
    exit;
}

// ضغط
$zipFile = $sqlFile . '.zip';
$zip = new ZipArchive();
$zip->open($zipFile, ZipArchive::CREATE);
$zip->addFile($sqlFile, basename($sqlFile));
$zip->close();
unlink($sqlFile);

// إشعار نجاح
saveNotification(
    $conn,
    '✅ تم إنشاء نسخة احتياطية',
    "تم إنشاء نسخة بتاريخ $date"
);

function saveNotification($conn, $title, $message) {
    $stmt = $conn->prepare(
        "INSERT INTO notifications (title, message) VALUES (?, ?)"
    );
    $stmt->bind_param("ss", $title, $message);
    $stmt->execute();
}


