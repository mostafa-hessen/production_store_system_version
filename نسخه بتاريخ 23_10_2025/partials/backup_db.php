<?php
// backup_ajax.php
header('Content-Type: application/json; charset=utf-8');

// ---- إعدادات ----
$host = 'localhost';
$dbUser = 'root';
$dbPass = 'your_db_password';
$dbName = 'store_v1_db.sql';
$backupDir = __DIR__ . '/../backups'; // ضبّط المسار هنا (خارج الويب أفضل)
$mysqldumpPath = '/usr/bin/mysqldump'; // ممكن نتحقق أدناه

// تأكد من وجود المجلد
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0775, true)) {
        echo json_encode(['success' => false, 'message' => 'فشل في إنشاء مجلد النسخ الاحتياطي', 'detail'=>null]);
        exit;
    }
}

// اسم ملف النسخ
$filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$fullpath = $backupDir . DIRECTORY_SEPARATOR . $filename;

// حاول تحديد مسار mysqldump إن لم يكن موجودًا
if (!is_executable($mysqldumpPath)) {
    // حاول البحث عنه عبر `which` (ربما غير مسموح)
    $which = trim(shell_exec('which mysqldump 2>/dev/null'));
    if ($which) $mysqldumpPath = $which;
}

// تأكد موجود
if (!is_executable($mysqldumpPath)) {
    $msg = "mysqldump غير موجود أو غير قابل للتنفيذ. المسار الحالي: $mysqldumpPath";
    echo json_encode(['success' => false, 'message' => 'خطأ: mysqldump غير متاح', 'detail'=>$msg]);
    exit;
}

// نبني الأمر بأمان مع escapeshellarg
$hostArg = escapeshellarg($host);
$userArg = escapeshellarg($dbUser);
$passArg = escapeshellarg($dbPass);
$dbArg   = escapeshellarg($dbName);
$outArg  = escapeshellarg($fullpath);

// Redirect stderr to stdout to capture كل شيء
$cmd = "{$mysqldumpPath} --host={$hostArg} --user={$userArg} --password={$passArg} {$dbArg} 2>&1 > {$outArg}";

// نفّذ
$output = [];
$returnVar = null;
exec($cmd, $output, $returnVar);

// بعض متصفحات وبيئات PHP لا تسمح بإعادة التوجيه لملف عند استخدام password param، إذا وجدت مشكلة جرب حفظ الإخراج في متغير ثم fwrite.
// تحقق من الملف فعليًا
$fileCreated = file_exists($fullpath) && filesize($fullpath) > 0;

$response = [
    'success' => ($returnVar === 0 && $fileCreated),
    'exit_code' => $returnVar,
    'file' => $fileCreated ? $filename : null,
    'path' => $fileCreated ? $fullpath : null,
    'output' => implode("\n", $output)
];

echo json_encode($response);
exit;
?>