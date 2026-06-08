<?php
require_once 'db.php';

// التأكد من إرسال المعرف ID بشكل رقمي صحيح وحمايته
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    try {
        // جلب مسار الصورة أولاً لحذف الملف الفيزيائي من السيرفر لتوفير المساحة
        $stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $news = $stmt->fetch();

        if ($news) {
            // مسح الصورة من مجلد uploads لو كانت موجودة فعلياً
            if (!empty($news['image']) && file_exists($news['image'])) {
                unlink($news['image']);
            }

            // تنفيذ استعلام الحذف النهائي من جدول news
            $deleteStmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
            $deleteStmt->execute([$id]);

            header("Location: add-news.php?status=success");
            exit;
        } else {
            header("Location: add-news.php?status=error&msg=article_not_found");
            exit;
        }
    } catch (\PDOException $e) {
        header("Location: add-news.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: add-news.php");
    exit;
}
?>