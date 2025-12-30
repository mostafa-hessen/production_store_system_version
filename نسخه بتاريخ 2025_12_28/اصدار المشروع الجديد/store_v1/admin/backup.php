<?php
// admin/backup.php
session_start();
header('Content-Type: application/json; charset=utf-8');

// ==================== إعدادات - عدّل هنا ====================
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';             // ضع كلمة المرور هنا
$dbName = 'store_v1_db';  // اسم القاعدة الصحيح
$backupDir = 'D:\\backups'; // مسار حفظ النسخ (خارج webroot)
// مسار mysqldump على ويندوز (ضع المسار الصحيح)
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$retentionDays = 14; // أيام الاحتفاظ بالنسخ
// ===========================================================

// تحقق صلاحية الوصول: مثال بسيط (عدّله حسب نظامك)
$allowed = false;
if (isset($_SESSION['user']) && $_SESSION['user'] === 'admin') $allowed = true;
// fallback مؤقت (غير آمن) — إحذف أو عطل في الإنتاج
if (!$allowed && defined('APP_ALLOW_INSECURE_ADMIN') ) {
    $allowed = true;
}
if (!$allowed) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'غير مسموح. يجب تسجيل الدخول كمشرف.']);
    exit;
}

// فقط POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'اطلب POST فقط.']);
    exit;
}

// تأكد أن المجلد موجود وقابل للكتابة
if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
    echo json_encode(['success' => false, 'message' => "لا يمكن إنشاء مجلد الباكاب: $backupDir"]);
    exit;
}
if (!is_writable($backupDir)) {
    echo json_encode(['success' => false, 'message' => "المجلد غير قابل للكتابة: $backupDir"]);
    exit;
}

// تحقق من وجود القاعدة أولاً
$mysqli = @new mysqli($dbHost, $dbUser, $dbPass);
if ($mysqli->connect_errno) {
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات: ' . $mysqli->connect_error]);
    exit;
}
$dbExists = false;
$res = $mysqli->query("SHOW DATABASES LIKE " . $mysqli->real_escape_string($dbName));
if ($res && $res->num_rows > 0) $dbExists = true;
$res && $res->close();
$mysqli->close();

if (!$dbExists) {
    // طباعة القواعد لسهولة التشخيص
    $mysqli = @new mysqli($dbHost, $dbUser, $dbPass);
    $rows = [];
    if ($mysqli && !$mysqli->connect_errno) {
        $res2 = $mysqli->query("SHOW DATABASES");
        while ($r = $res2->fetch_row()) $rows[] = $r[0];
        $res2->close();
        $mysqli->close();
    }
    echo json_encode([
        'success' => false,
        'message' => "قاعدة البيانات '{$dbName}' غير موجودة على الخادم.",
        'existing_databases' => $rows
    ]);
    exit;
}

// أنشئ ملف defaults-extra-file مؤقت
$tmpIni = tempnam(sys_get_temp_dir(), 'mycnf_');
file_put_contents($tmpIni, "[client]\nuser={$dbUser}\npassword={$dbPass}\nhost={$dbHost}\n");
@chmod($tmpIni, 0600);

// اسم ملف الإخراج
$ts = date('Ymd_His');
$base = "{$dbName}_{$ts}.sql";
$outFile = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $base;

// بناء الأمر (مُقتبس بطريقة آمنة)
$cmd = "\"{$mysqldump}\" --defaults-extra-file=" . escapeshellarg($tmpIni) .
       " --single-transaction --quick --lock-tables=false --routines --events " . escapeshellarg($dbName);

// نستخدم proc_open ونكتب stdout في ملف مباشرة (بدون >)
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $outFile, 'w'],
    2 => ['pipe', 'w']
];

$process = proc_open($cmd, $descriptors, $pipes, null, null);
if (!is_resource($process)) {
    @unlink($tmpIni);
    echo json_encode(['success' => false, 'message' => 'فشل بدء عملية mysqldump.']);
    exit;
}
fclose($pipes[0]); // stdin
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($process);
@unlink($tmpIni);

if ($exitCode !== 0) {
    // امسح ملف الإخراج لو اتخلق لكن فاضي أو خاطئ
    if (file_exists($outFile) && filesize($outFile) === 0) @unlink($outFile);
    echo json_encode([
        'success' => false,
        'message' => "mysqldump فشل. exit={$exitCode}",
        'stderr' => $stderr
    ]);
    exit;
}

// ضغط الملف
$gzFile = $outFile . '.gz';
$in = fopen($outFile, 'rb');
$gz = gzopen($gzFile, 'wb9');
if (!$in || !$gz) {
    @unlink($outFile);
    echo json_encode(['success' => false, 'message' => 'فشل إنشاء الملف المضغوط.']);
    exit;
}
stream_copy_to_stream($in, $gz);
fclose($in);
gzclose($gz);
@unlink($outFile);

// تنظيف النسخ القديمة
$now = time();
$files = glob(rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dbName . '_*.sql.gz');
foreach ($files as $f) {
    if (is_file($f) && ($now - filemtime($f)) > ($retentionDays * 86400)) {
        @unlink($f);
    }
}

// نجاح - أعد اسم الملف (basename) فقط
echo json_encode([
    'success' => true,
    'message' => 'تم إنشاء النسخة الاحتياطية.',
    'file' => basename($gzFile)
]);
exit;
?>