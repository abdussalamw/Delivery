<?php
// إعدادات الاتصال بقاعدة بيانات PostgreSQL
$host = 'localhost';
$db   = 'delivery_db';     // اسم قاعدة البيانات
$user = 'postgres';        // اسم المستخدم
$pass = '123456';          // كلمة المرور (قم بتغييرها إن لزم الأمر)
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
