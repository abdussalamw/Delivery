<?php
// إعدادات الاتصال بقاعدة بيانات PostgreSQL
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'delivery_db';
$user = getenv('DB_USER') ?: 'postgres';
$pass = getenv('DB_PASS') ?: '123456';
$port = '5432';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;options='--client_encoding=UTF8'";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("خطأ حرج: تعذر الاتصال بقاعدة بيانات PostgreSQL. تأكد من تشغيل الخدمة وصحة البيانات في ملف config.php. التفاصيل: " . $e->getMessage());
}
?>
