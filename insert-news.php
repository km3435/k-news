<?php
// insert-news.php - معالجة مدخلات جدول news وحفظ المسار المطلق الذكي لـ Localhost لـ XAMPP
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add-news.php?status=error&msg=invalid_request");
    exit();
}

$title       = isset($_POST['title']) ? trim($_POST['title']) : '';
$content     = isset($_POST['content']) ? trim($_POST['content']) : '';
$category_id = isset($_POST['category_id']) ? trim($_POST['category_id']) : '';

if (empty($title) || empty($content) || empty($category_id)) {
    header("Location: add-news.php?status=error&msg=all_fields_required");
    exit();
}

$slug        = !empty($_POST['slug']) ? trim($_POST['slug']) : preg_replace('/\s+/u', '-', trim(mb_strtolower($title)));
$author      = !empty($_POST['author']) ? trim($_POST['author']) : 'كريم أحمد';
$tags        = !empty($_POST['tags']) ? trim($_POST['tags']) : null;
$status      = !empty($_POST['status']) ? trim($_POST['status']) : 'Published';
$is_breaking = isset($_POST['is_breaking']) ? 1 : 0;

$views = 0; $clicks = 0; $shares = 0; $user_id = 1; 

$image_url = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath   = $_FILES['image']['tmp_name'];
    $fileName      = $_FILES['image']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    if (in_array($fileExtension, $allowedExtensions)) {
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        
        // الخروج الآمن خطوة للخلف لرمي الصورة في المجلد الرئيسي بره
        $uploadFileDir = '../uploads/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }
        
        if (move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
            // 🌟 حفظ المسار المطلق لمنع مشاكل العرض والاختفاء نهائياً
            $image_url = 'http://127.0.0.1/All Projects/k-news/uploads/' . $newFileName;
        } else {
            header("Location: add-news.php?status=error&msg=image_upload_failed");
            exit();
        }
    } else {
        header("Location: add-news.php?status=error&msg=invalid_image_type");
        exit();
    }
} else {
    header("Location: add-news.php?status=error&msg=image_is_required");
    exit();
}

try {
    $sql = "INSERT INTO news (title, slug, content, image, views, clicks, shares, status, user_id, category_id, published_at, created_at, author, tags, is_breaking) 
            VALUES (:title, :slug, :content, :image, :views, :clicks, :shares, :status, :user_id, :category_id, NOW(), NOW(), :author, :tags, :is_breaking)";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':title'       => $title,
        ':slug'        => $slug,
        ':content'     => $content,
        ':image'       => $image_url,
        ':views'       => $views,
        ':clicks'      => $clicks,
        ':shares'      => $shares,
        ':status'      => $status,
        ':user_id'     => $user_id,
        ':category_id' => $category_id,
        ':author'      => $author,
        ':tags'        => $tags,
        ':is_breaking' => $is_breaking
    ]);

    header("Location: add-news.php?status=success");
    exit();
} catch (PDOException $e) {
    header("Location: add-news.php?status=error&msg=" . urlencode($e->getMessage()));
    exit();
}
?>