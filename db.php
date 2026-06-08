<?php
// db.php - ملف الاتصال بقاعدة البيانات

$host    = 'localhost';
$db      = 'k_news_db';
$user    = 'root'; 
$pass    = '';     // اتركه فارغاً لويندوز XAMPP، واجعله 'root' لو تستخدم الماك MAMP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("<div style='color: #f43f5e; background: #090d16; padding: 20px; font-family: sans-serif; border: 1px solid #131a2a; border-radius: 8px; text-align: center; margin: 50px auto; max-width: 600px;'>
            <strong style='font-size: 18px;'>فشل الاتصال بقاعدة البيانات:</strong><br><span style='color: #94a3b8; font-size: 14px;'>" . htmlspecialchars($e->getMessage()) . "</span>
          </div>");
}
?>