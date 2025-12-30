<?php
require_once dirname(__DIR__) . '/config.php'; // للوصول إلى config.php من داخل مجلد admin

$data = json_decode(file_get_contents("php://input"), true);



// التحقق من CSRF
if (!isset($data['csrf']) || $data['csrf'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'CSRF غير صالح']);
    exit;
}

// مسارات النسخ الاحتياطي
$paths = [
    'local'   => 'C:/xampp/htdocs/store_v1/backups/',
    'driv' => 'D:/db/',
    // 'monthly' => 'C:/xampp/htdocs/store_v1/backups/monthly/',
    // 'drive'   => 'G:/My Drive/store_backups/' // مجلد مزامن مع Google Drive
];

if (!isset($paths[$data['path_key']])) {
    echo json_encode(['status' => 'error', 'message' => 'مسار غير مسموح']);
    exit;
}

$backupDir = $paths[$data['path_key']];

// إنشاء المجلد لو مش موجود
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'فشل إنشاء المجلد']);
        exit;
    }
}

// اسم الملف
$date = date('Y-m-d_H-i-s');
$sqlFile = $backupDir . "store_v2_{$date}.sql";

// مسار mysqldump
$mysqldump = 'C:/xampp/mysql/bin/mysqldump.exe';

// أمر النسخ الاحتياطي
$command = "\"$mysqldump\" -u root store_v2_db > \"$sqlFile\"";

// تنفيذ الأمر
exec($command, $out, $result);

if ($result !== 0 || !file_exists($sqlFile)) {
    echo json_encode(['status'=>'error','message'=>'فشل النسخ الاحتياطي أو لم يتم إنشاء الملف']);
    exit;
}

// ضغط الملف إذا طلب
if (!empty($data['zip'])) {
    $zipFile = $sqlFile . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($sqlFile, basename($sqlFile));
        $zip->close();
        unlink($sqlFile); // حذف الملف الأصلي بعد الضغط
        $finalFile = $zipFile;
    } else {
        echo json_encode(['status'=>'error','message'=>'فشل إنشاء ملف ZIP']);
        exit;
    }
} else {
    $finalFile = $sqlFile;
}

echo json_encode([
    'status' => 'success',
    'message' => 'تم إنشاء النسخة الاحتياطية بنجاح',
    'file' => $finalFile,
    'path_key' => $data['path_key']
]);
