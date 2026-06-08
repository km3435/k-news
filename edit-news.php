<?php
require_once 'db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// جلب تفاصيل الخبر الحالي المطلوب تعديله
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    header("Location: add-news.php?status=error&msg=article_not_found");
    exit;
}

// جلب الأقسام للقائمة المنسدلة
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// معالجة تحديث البيانات عند إرسال الفورم (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $slug        = trim($_POST['slug'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $author      = trim($_POST['author'] ?? 'كريم أحمد');
    $tags        = trim($_POST['tags'] ?? '');
    $status      = $_POST['status'] ?? 'Published';
    $is_breaking = isset($_POST['is_breaking']) ? 1 : 0;

    if (empty($title) || empty($content) || empty($category_id)) {
        $error_msg = "جميع الحقول الأساسية مطلوبة.";
    } else {
        $image_path = $article['image']; // الحفاظ على الصورة القديمة كافتراضية

        // معالجة الصورة الجديدة إذا تم رفعها
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['image']['tmp_name'];
            $file_name     = $_FILES['image']['name'];
            $file_ext      = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($file_ext, $allowed_extensions)) {
                $upload_dir = 'uploads/';
                $new_file_name = time() . '_' . uniqid() . '.' . $file_ext;
                $dest_path     = $upload_dir . $new_file_name;

                if (move_uploaded_path($file_tmp_path, $dest_path)) {
                    // حذف الصورة القديمة من المجلد قبل استبدالها
                    if (!empty($article['image']) && file_exists($article['image'])) {
                        unlink($article['image']);
                    }
                    $image_path = $dest_path;
                }
            }
        }

        try {
            $updateSql = "UPDATE news SET title = :title, slug = :slug, content = :content, category_id = :category_id, 
                          author = :author, tags = :tags, status = :status, is_breaking = :is_breaking, image = :image WHERE id = :id";
            
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':title'       => $title,
                ':slug'        => $slug,
                ':content'     => $content,
                ':category_id' => $category_id,
                ':author'      => $author,
                ':tags'        => $tags,
                ':status'      => $status,
                ':is_breaking' => $is_breaking,
                ':image'       => $image_path,
                ':id'          => $id
            ]);

            header("Location: add-news.php?status=success");
            exit;
        } catch (\PDOException $e) {
            $error_msg = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl" id="pageHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS - تعديل المقال</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic/build/ckeditor.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html[dir="rtl"] * { font-family: 'Cairo', sans-serif; }
        html[dir="ltr"] * { font-family: 'Inter', sans-serif; }
        body { background-color: #04060a; color: #ffffff; display: flex; min-height: 100vh; position: relative; overflow-x: hidden; }
        #worldBg { position: fixed; top: 0; right: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.15; }
        .sidebar, .main-content { position: relative; z-index: 1; }
        .sidebar { width: 280px; background-color: #090d16; padding: 30px 20px; display: flex; flex-direction: column; justify-content: space-between; }
        html[dir="rtl"] .sidebar { border-left: 1px solid #131a2a; }
        html[dir="ltr"] .sidebar { border-right: 1px solid #131a2a; }
        .logo-area { font-size: 26px; font-weight: 800; }
        html[dir="rtl"] .logo-area { text-align: right; }
        html[dir="ltr"] .logo-area { text-align: left; }
        .logo-area span { color: #3b82f6; }
        .menu-list { list-style: none; }
        .menu-item { padding: 14px 16px; border-radius: 10px; margin-bottom: 10px; color: #64748b; cursor: pointer; display: flex; align-items: center; text-decoration: none; font-size: 15px; font-weight: 600; }
        html[dir="rtl"] .menu-item i { margin-left: 16px; }
        html[dir="ltr"] .menu-item i { margin-right: 16px; }
        .menu-item:hover, .menu-item.active { background-color: #111827; color: #ffffff; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .btn-lang-toggle { background-color: #090d16; border: 1px solid #131a2a; color: #cbd5e1; padding: 10px 18px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; }
        .form-container { background-color: #090d16; border: 1px solid #131a2a; border-radius: 16px; padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        @media (max-width: 992px) { .form-grid { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 24px; }
        .form-group label { display: block; color: #94a3b8; font-size: 14px; font-weight: 600; margin-bottom: 10px; }
        .form-control { width: 100%; background-color: #04060a; border: 1px solid #131a2a; border-radius: 10px; padding: 14px; color: #ffffff; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #3b82f6; }
        .checkbox-group { display: flex; align-items: center; background-color: #04060a; border: 1px solid #131a2a; padding: 14px; border-radius: 10px; gap: 10px; cursor: pointer; }
        .ck-editor__editable_inline { background-color: #04060a !important; color: #ffffff !important; min-height: 250px; }
        html[dir="rtl"] .ck-editor__editable_inline { text-align: right; direction: rtl; }
        html[dir="ltr"] .ck-editor__editable_inline { text-align: left; direction: ltr; }
        .ck-toolbar { background-color: #090d16 !important; border: 1px solid #131a2a !important; }
        .ck-toolbar__items button span, .ck-icon { color: #ffffff !important; }
        .image-drop-zone { border: 2px dashed #131a2a; border-radius: 12px; padding: 25px; text-align: center; background-color: #04060a; cursor: pointer; }
        #imagePreview { max-width: 100%; max-height: 150px; border-radius: 8px; margin-top: 15px; display: block; margin-left: auto; margin-right: auto; }
        .btn-submit { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border: none; padding: 16px 30px; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; width: 100%; }
        .alert-error { background-color: rgba(244, 63, 94, 0.1); border: 1px solid #f43f5e; color: #f43f5e; padding: 16px; border-radius: 10px; margin-bottom: 25px; }
    </style>
</head>
<body>
    <canvas id="worldBg"></canvas>
    <div class="sidebar">
        <div>
            <div class="logo-area">K<span>·NEWS</span></div>
            <ul class="menu-list">
                <li><a href="dashboard.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i> <span class="lang-text" data-ar="لوحة التحكم" data-en="Dashboard">لوحة التحكم</span></a></li>
                <li><a href="add-news.php" class="menu-item"><i class="fa-solid fa-square-plus"></i> <span class="lang-text" data-ar="إضافة خبر" data-en="Add News">إضافة خبر</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h1 class="lang-text" data-ar="تعديل وتحديث المقال الإخباري" data-en="Edit & Update Article">تعديل وتحديث المقال الإخباري</h1>
            </div>
            <button class="btn-lang-toggle" onclick="toggleLanguage()">
                <i class="fa-solid fa-globe" style="color: #3b82f6;"></i> <span id="langBtnText">English</span>
            </button>
        </div>

        <?php if (isset($error_msg)): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="form-container">
            <div class="form-grid">
                <div>
                    <div class="form-group">
                        <label for="title" class="lang-text" data-ar="عنوان المقال الرئيسي" data-en="Article Title">عنوان المقال الرئيسي</label>
                        <input type="text" name="title" id="title" class="form-control" value="<?php echo htmlspecialchars($article['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="slug" class="lang-text" data-ar="رابط المقال (Slug)" data-en="URL Slug">رابط المقال (Slug)</label>
                        <input type="text" name="slug" id="slug" class="form-control" value="<?php echo htmlspecialchars($article['slug']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="content" class="lang-text" data-ar="محتوى الخبر" data-en="Content">محتوى الخبر</label>
                        <textarea name="content" id="editor"><?php echo htmlspecialchars($article['content']); ?></textarea>
                    </div>
                </div>
                <div>
                    <div class="form-group">
                        <label for="category_id" class="lang-text" data-ar="القسم" data-en="Category">القسم</label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $article['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="author" class="lang-text" data-ar="المحرر" data-en="Author">المحرر</label>
                        <input type="text" name="author" id="author" class="form-control" value="<?php echo htmlspecialchars($article['author'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="tags" class="lang-text" data-ar="الكلمات المفتاحية" data-en="Tags">الكلمات المفتاحية</label>
                        <input type="text" name="tags" id="tags" class="form-control" value="<?php echo htmlspecialchars($article['tags'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="lang-text" data-ar="الحالة" data-en="Status">الحالة</label>
                        <select name="status" id="status" class="form-control">
                            <option value="Published" <?php echo $article['status'] == 'Published' ? 'selected' : ''; ?>>Published</option>
                            <option value="Draft" <?php echo $article['status'] == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-group" for="is_breaking">
                            <input type="checkbox" name="is_breaking" id="is_breaking" value="1" <?php echo (isset($article['is_breaking']) && $article['is_breaking']) ? 'checked' : ''; ?>>
                            <span class="lang-text" data-ar="خبر عاجل" data-en="Breaking News">خبر عاجل</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="lang-text" data-ar="الصورة الحالية / الجديدة" data-en="Featured Image">الصورة الحالية / الجديدة</label>
                        <div class="image-drop-zone" onclick="document.getElementById('imageInput').click()">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <input type="file" name="image" id="imageInput" accept="image/*" style="display: none;" onchange="previewImage(event)">
                            <img id="imagePreview" src="<?php echo !empty($article['image']) ? htmlspecialchars($article['image']) : 'https://placehold.co/600x400/090d16/ffffff?text=No+Image'; ?>">
                        </div>
                    </div>
                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-rotate"></i> <span class="lang-text" data-ar="تحديث وحفظ التعديلات" data-en="Update Article">تحديث وحفظ التعديلات</span></button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        function toggleLanguage() {
            const htmlElement = document.getElementById('pageHtml');
            const currentDir = htmlElement.getAttribute('dir');
            const langTexts = document.querySelectorAll('.lang-text');
            const langBtnText = document.getElementById('langBtnText');
            if (currentDir === 'rtl') {
                htmlElement.setAttribute('dir', 'ltr');
                langBtnText.innerText = "العربية";
                langTexts.forEach(el => el.innerText = el.getAttribute('data-en'));
            } else {
                htmlElement.setAttribute('dir', 'rtl');
                langBtnText.innerText = "English";
                langTexts.forEach(el => el.innerText = el.getAttribute('data-ar'));
            }
        }

        let articleEditor;
        ClassicEditor.create(document.querySelector('#editor')).then(editor => {
            articleEditor = editor;
            editor.model.document.on('change:data', () => {
                document.querySelector('#editor').value = editor.getData();
            });
        });

        document.querySelector('form').addEventListener('submit', function() {
            if (articleEditor) { document.querySelector('#editor').value = articleEditor.getData(); }
        });

        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        titleInput.addEventListener('input', function() {
            slugInput.value = titleInput.value.toLowerCase().replace(/[^a-z0-9\u0600-\u06FF\s]/g, '').replace(/\s+/g, '-');
        });

        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() { document.getElementById('imagePreview').src = reader.result; }
            reader.readAsDataURL(event.target.files[0]);
        }
    </script>
</body>
</html>