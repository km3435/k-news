<?php
// add-news.php - واجهة إدارة وبث المقالات الإخبارية الاحترافية المتقدمة المدمجة
require_once 'db.php';

// --- 🛠️ أ. معالجة عمليات التحديث والتجاوز الفوري (Update) للمقالات عبر POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $news_id     = intval($_POST['news_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $slug        = trim($_POST['slug'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $author      = trim($_POST['author'] ?? 'كريم أحمد');
    $tags        = trim($_POST['tags'] ?? '');
    $status      = trim($_POST['status'] ?? 'Published');
    $is_breaking = isset($_POST['is_breaking']) ? 1 : 0;

    if ($news_id > 0 && !empty($title) && !empty($content) && $category_id > 0) {
        try {
            // جلب الصورة الحالية
            $img_stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
            $img_stmt->execute([$news_id]);
            $current_image_url = $img_stmt->fetchColumn();
            $image_path = $current_image_url;

            // إذا رفع صورة جديدة
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp   = $_FILES['image']['tmp_name'];
                $file_name  = $_FILES['image']['name'];
                $file_ext   = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_file_name = md5(time() . $file_name) . '.' . $file_ext;
                
                $uploadFileDir = '../uploads/';
                if (move_uploaded_file($file_tmp, $uploadFileDir . $new_file_name)) {
                    $image_path = 'http://127.0.0.1/All Projects/k-news/uploads/' . $new_file_name;
                    
                    // حذف الملف القديم محلياً لو كان رابط سيرفر محلي
                    if (!empty($current_image_url) && strpos($current_image_url, 'http://127.0.0.1') === 0) {
                        $old_path = str_replace('http://127.0.0.1/All Projects/k-news/', '../', $current_image_url);
                        if(file_exists($old_path)) @unlink($old_path);
                    }
                }
            }

            $update_stmt = $pdo->prepare("UPDATE news SET title = ?, slug = ?, content = ?, image = ?, status = ?, category_id = ?, author = ?, tags = ?, is_breaking = ? WHERE id = ?");
            $update_stmt->execute([$title, $slug, $content, $image_path, $status, $category_id, $author, $tags, $is_breaking, $news_id]);
            
            header("Location: add-news.php?status=success");
            exit;
        } catch (PDOException $e) {
            header("Location: add-news.php?status=error&msg=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

// --- 🗑️ ب. معالجة عمليات الحذف (Delete) للمقالات عبر POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $news_id = intval($_POST['id'] ?? 0);
    if ($news_id > 0) {
        try {
            $img_stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
            $img_stmt->execute([$news_id]);
            $img_url = $img_stmt->fetchColumn();
            
            if (!empty($img_url) && strpos($img_url, 'http://127.0.0.1') === 0) {
                $file_path = str_replace('http://127.0.0.1/All Projects/k-news/', '../', $img_url);
                if(file_exists($file_path)) @unlink($file_path);
            }

            $del_stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
            $del_stmt->execute([$news_id]);
            
            header("Location: add-news.php?status=success&msg=delete_success");
            exit;
        } catch (PDOException $e) {
            header("Location: add-news.php?status=error&msg=delete_error");
            exit;
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$latest_news = $pdo->query("SELECT n.*, c.name as category_name FROM news n LEFT JOIN categories c ON n.category_id = c.id ORDER BY n.id DESC LIMIT 6")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" id="pageHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS - غرفة معالجة وبث الأخبار</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic/build/ckeditor.js"></script>
    <style>
        :root { 
            --bg-primary: #030712; 
            --bg-secondary: #0b1329; 
            --bg-input: #070a13; 
            --border-color: #1e293b; 
            --accent-blue: #3b82f6; 
            --accent-glow: rgba(59, 130, 246, 0.5); 
            --text-main: #f8fafc; 
            --text-muted: #64748b; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; transition: background-color 0.3s, border-color 0.3s; }
        html[dir="rtl"] * { font-family: 'Cairo', sans-serif; }
        html[dir="ltr"] * { font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-primary); color: var(--text-main); display: flex; min-height: 100vh; position: relative; overflow-x: hidden; }
        #worldBg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.25; }
        /* 💎 التنسيق الاحترافي المتكامل لـ حقل المحتوى التفصيلي */
.form-group {
    margin-bottom: 30px;
    display: flex;
    flex-direction: column;
}

/* 1. تنسيق العنوان (Label) مع الأيقونة */
.form-group label {
    display: flex;
    align-items: center;
    gap: 12px; /* مسافة مريحة بين الأيقونة والنص */
    color: #94a3b8; /* لون افتراضي هادئ */
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 14px;
    cursor: pointer;
    user-select: none;
    transition: color 0.3s ease;
}

.form-group label i {
    color: var(--text-muted);
    font-size: 16px;
    width: 26px; /* توحيد العرض لضمان محاذاة النص */
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.03); /* خلفية دائرية خفيفة */
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* 2. الاستهداف المباشر لـ CKEditor وجعله بمساحة ضخمة ومريحة */
.form-group .ck.ck-editor {
    width: 100% !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    overflow: hidden;
}

/* شريط الأدوات العلوي للمحرر */
.form-group .ck.ck-toolbar {
    background-color: #0d1527 !important; /* أغمق بقليل لفصل الأزرار */
    border: 1px solid var(--border-color) !important;
    border-bottom: none !important;
    border-radius: 12px 12px 0 0 !important;
    padding: 8px !important;
}

/* منطقة الكتابة النصية (Editable Area) */
.form-group .ck-editor__main .ck-blur, 
.form-group .ck-editor__main .ck-focused {
    min-height: 950px !important; /* 👈 مساحة كتابة واسعة جداً للمقالات الطويلة */
    background-color: var(--bg-input) !important;
    color: #e2e8f0 !important;
    border-radius: 0 0 12px 12px !important;
    border: 1px solid var(--border-color) !important;
    font-size: 15px;
    line-height: 1.8;
    padding: 22px !important;
    transition: border-color 0.3s ease, box-shadow 0.3s ease !important;
}

/* 3. تأثيرات الحركة (UX) عند التفاعل والتركيز داخل المحرر */
.form-group:hover label,
.form-group:focus-within label {
    color: #ffffff; /* النص ينور بالأبيض */
}

.form-group:hover label i,
.form-group:focus-within label i {
    color: var(--accent-blue); /* الأيقونة تنور بالأزرق المحايد */
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.25);
    transform: translateY(-2px) scale(1.05); /* حركة صعود مجهرية سلسة */
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    height: 100px;
}

/* تأثير التوهج الأزرق حول صندوق الكتابة عند العمل بداخله */
.form-group .ck-editor__main .ck-focused {
    border-color: var(--accent-blue) !important;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.4), 0 0 15px rgba(59, 130, 246, 0.15) !important;
    outline: none !important;
}

/* تنسيق القوائم المنسدلة وأزرار المحرر داخلياً لتبدو مدمجة بالكامل */
.form-group .ck.ck-button { color: #94a3b8 !important; cursor: pointer !important; }
.form-group .ck.ck-button:hover { background-color: rgba(255, 255, 255, 0.05) !important; color: #fff !important; }
.form-group .ck.ck-button.ck-on { background-color: var(--accent-blue) !important; color: #fff !important; }
.form-group .ck.ck-placeholder { color: var(--text-muted) !important; }
        /* Layout Structure */
        .sidebar, .main-content { position: relative; z-index: 1; }
        .sidebar { width: 290px; background: linear-gradient(180deg, #070c1b 0%, #0c1428 100%); padding: 35px 24px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 4px 0 25px rgba(0,0,0,0.5); border-left: 1px solid var(--border-color); flex-shrink: 0; }
        .logo-area { font-size: 28px; font-weight: 800; letter-spacing: 1px; margin-bottom: 40px; display: flex; align-items: center; gap: 10px; }
        .logo-area span { color: var(--accent-blue); text-shadow: 0 0 20px var(--accent-glow); }
        .menu-list { list-style: none; }
        .menu-item { padding: 14px 18px; border-radius: 12px; margin-bottom: 12px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; transition: all 0.3s; font-size: 15px; font-weight: 600; text-decoration: none; }
        .menu-item svg { margin-left: 16px; }
        html[dir="ltr"] .menu-item svg { margin-left: 0; margin-right: 16px; }
        .menu-item:hover { color: var(--text-main); background-color: rgba(255,255,255,0.02); }
        .menu-item.active { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff; box-shadow: 0 4px 25px rgba(29, 78, 216, 0.45); }
        .user-profile-footer { display: flex; align-items: center; padding-top: 25px; border-top: 1px solid var(--border-color); }
        .user-profile-footer img { margin-left: 14px; width: 46px; height: 46px; border-radius: 50%; border: 2px solid var(--accent-blue); }
        html[dir="ltr"] .user-profile-footer img { margin-left: 0; margin-right: 14px; }
        
        .main-content { flex: 1; padding: 45px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; flex-wrap: wrap; }
        .top-header h1 { font-size: 32px; font-weight: 800; background: linear-gradient(to right, #ffffff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .btn-lang-toggle { background: rgba(11, 19, 41, 0.6); backdrop-filter: blur(8px); border: 1px solid var(--border-color); color: #cbd5e1; padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        
        /* Stats Dashboard */
        .system-status-panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .status-card { background: rgba(11, 19, 41, 0.6); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 20px; }
        .status-circle-container { position: relative; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; }
        .rotating-ring { position: absolute; width: 100%; height: 100%; border: 3px dashed transparent; border-radius: 50%; animation: rotateRing 4s linear infinite; }
        .ring-blue { border-top-color: #3b82f6; border-bottom-color: #1d4ed8; }
        .ring-green { border-left-color: #10b981; border-right-color: #059669; }
        .core-orb { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; animation: pulseOrb 2s ease-in-out infinite alternate; }
        .orb-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .orb-green { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .status-info h5 { font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 4px; }
        .status-info p { font-size: 16px; font-weight: 700; color: #ffffff; }
        
        @keyframes rotateRing { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes pulseOrb { 0% { transform: scale(0.92); opacity: 0.8; } 100% { transform: scale(1.05); opacity: 1; } }
        
        /* Advanced Form Elements */
        .form-container, .management-container { background: rgba(11, 19, 41, 0.75); backdrop-filter: blur(12px); border: 1px solid var(--border-color); border-radius: 20px; padding: 40px; box-shadow: 0 20px 50px rgba(0,0,0,0.6); margin-top: 20px; }
        .form-grid { display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 35px; }
        .form-group { margin-bottom: 30px; }
        .form-group label { display: block; color: #94a3b8; font-size: 14px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .form-group label i { color: var(--accent-blue); }
        .input-icon-wrapper { position: relative; display: flex; align-items: center; }
        
        /* RTL / LTR Field Icons Alignment */
        html[dir="rtl"] .input-icon-wrapper i.field-icon { position: absolute; color: var(--text-muted); font-size: 16px; pointer-events: none; z-index: 5; right: 16px; }
        html[dir="rtl"] .form-control { padding: 15px 48px 15px 15px; }
        html[dir="ltr"] .input-icon-wrapper i.field-icon { position: absolute; color: var(--text-muted); font-size: 16px; pointer-events: none; z-index: 5; left: 16px; }
        html[dir="ltr"] .form-control { padding: 15px 15px 15px 48px; }
        
        .form-control { width: 100%; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 12px; color: #ffffff; font-size: 14px; outline: none; transition: all 0.3s; }
        .input-icon-wrapper:focus-within .form-control { border-color: var(--accent-blue); box-shadow: 0 0 15px rgba(59, 130, 246, 0.2); }
        
        .select-wrapper { position: relative; width: 100%; }
        html[dir="rtl"] .select-chevron { position: absolute; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; left: 18px; z-index: 5; }
        html[dir="ltr"] .select-chevron { position: absolute; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; right: 18px; z-index: 5; }
        
        .checkbox-group { display: flex; align-items: center; justify-content: space-between; background-color: var(--bg-input); border: 1px solid var(--border-color); padding: 16px; border-radius: 12px; cursor: pointer; }
        .checkbox-group div { display: flex; align-items: center; gap: 10px; color: #cbd5e1; font-size: 14px; font-weight: 600; }
        .checkbox-group input { width: 20px; height: 20px; accent-color: var(--accent-blue); cursor: pointer; }
        
        .image-drop-zone { border: 2px dashed #2e3f5b; border-radius: 16px; padding: 30px; text-align: center; background-color: var(--bg-input); cursor: pointer; }
        #imagePreview { max-width: 100%; max-height: 180px; border-radius: 10px; margin-top: 15px; display: none; border: 1px solid var(--border-color); object-fit: cover; }
        
        .btn-submit { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; padding: 18px 30px; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3); }
        .btn-submit:hover { opacity: 0.95; }
        .btn-cancel-edit { background: var(--bg-primary); border: 1px solid var(--border-color); color: #f43f5e; margin-top: 12px; display: none; box-shadow: none; }
        .btn-cancel-edit:hover { background: rgba(244, 63, 94, 0.05); }

        /* 👑 الاستهداف المباشر والصحيح لـ CKEditor لتكبير منطقة الكتابة وإعطائها النمط الداكن */
        .ck.ck-editor {
            width: 100% !important;
        }
        .ck-editor__main .ck-blur, 
        .ck-editor__main .ck-focused {
            min-height: 450px !important; /* 👈 تم التكبير الإجباري هنا لـ 450 بكسل لتوفير مساحة ضخمة للكتابة */
            background-color: var(--bg-input) !important;
            color: #e2e8f0 !important;
            border-radius: 0 0 12px 12px !important;
            border: 1px solid var(--border-color) !important;
            font-size: 15px;
            line-height: 1.8;
            padding: 20px !important;
        }
        .ck-editor__main .ck-focused {
            border: 1px solid var(--accent-blue) !important;
            outline: none !important;
        }
        /* شريط أدوات المحرر العلوي */
        .ck.ck-toolbar {
            background-color: #0d1527 !important;
            border: 1px solid var(--border-color) !important;
            border-bottom: none !important;
            border-radius: 12px 12px 0 0 !important;
            padding: 6px !important;
        }
        .ck.ck-toolbar__items { flex-wrap: wrap; }
        .ck.ck-button {
            color: #94a3b8 !important;
            cursor: pointer !important;
        }
        .ck.ck-button:hover {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: #fff !important;
        }
        .ck.ck-button.ck-on {
            background-color: var(--accent-blue) !important;
            color: #fff !important;
        }
        .ck.ck-dropdown .ck-dropdown__panel { background-color: #0b1329 !important; border-color: var(--border-color) !important; }
        .ck.ck-list__item .ck-button:hover { background-color: rgba(59, 130, 246, 0.2) !important; }
        .ck.ck-placeholder { color: var(--text-muted) !important; }

        /* News Management Grid */
        .news-manage-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-top: 25px; }
        .news-card { background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }
        .news-card-img { width: 100%; height: 180px; object-fit: cover; }
        .news-card-body { padding: 20px; flex-grow: 1; }
        .news-card-tag { font-size: 11px; background-color: rgba(59, 130, 246, 0.12); color: #3b82f6; padding: 5px 10px; border-radius: 6px; display: inline-block; margin-bottom: 12px; font-weight: 700; }
        .news-card-breaking { font-size: 11px; background-color: rgba(244, 63, 94, 0.15); color: #f43f5e; padding: 5px 10px; border-radius: 6px; display: inline-block; margin-bottom: 12px; font-weight: 700; margin-inline-start: 6px; }
        .news-card-title { font-size: 16px; font-weight: 700; margin-bottom: 12px; line-height: 1.6; height: 50px; overflow: hidden; color: #e2e8f0; }
        .news-card-actions { display: flex; border-top: 1px solid var(--border-color); background-color: rgba(7, 12, 27, 0.5); }
        .action-btn { flex: 1; text-align: center; padding: 14px; font-size: 14px; font-weight: 600; background: transparent; border: none; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; }
        .btn-edit { color: var(--accent-blue); border-inline-end: 1px solid var(--border-color); }
        .btn-delete { color: #f43f5e; width: 100%; }
        .btn-delete:hover { background-color: rgba(244, 63, 94, 0.05); }
        .btn-edit:hover { background-color: rgba(59, 130, 246, 0.05); }
        
        .alert { padding: 18px; border-radius: 14px; margin-bottom: 30px; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background-color: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-error { background-color: rgba(244, 63, 94, 0.1); border: 1px solid #f43f5e; color: #f43f5e; }

        /* Responsive Layout Updates */
        @media (max-width: 1100px) {
            .form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; border-left: none; border-bottom: 1px solid var(--border-color); padding: 20px; }
            .logo-area { margin-bottom: 20px; }
            .main-content { padding: 20px; }
            .form-container { padding: 20px; }
        }
    </style>
</head>
<body>
    <canvas id="worldBg"></canvas>
    <div class="sidebar">
        <div>
            <div class="logo-area"><i class="fa-solid fa-satellite-dish" style="color:#3b82f6;"></i> K<span>·NEWS</span></div>
            <ul class="menu-list">
                <li><a href="dashboard.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i> <span class="lang-text" data-ar="لوحة التحكم" data-en="Dashboard">لوحة التحكم</span></a></li>
                <li><a href="add-news.php" class="menu-item active"><i class="fa-solid fa-square-plus"></i> <span class="lang-text" data-ar="إضافة خبر" data-en="Add News">إضافة خبر</span></a></li>
                <li><a href="admin_slider.php" class="menu-item active"><i class="fa-solid fa-square-plus"></i> <span class="lang-text" data-ar="إضافة خبرالرئيسي" data-en="Add News">إضافة خبرالرئيسي</span></a></li>
                <li><a href="manage-news.php" class="menu-item"><i class="fa-solid fa-newspaper"></i> <span class="lang-text" data-ar="إدارة الأخبار" data-en="Manage News">إدارة الأخبار</span></a></li>
                <li><a href="categories.php" class="menu-item"><i class="fa-solid fa-layer-group"></i> <span class="lang-text" data-ar="الأقسام" data-en="Categories">الأقسام</span></a></li>
            </ul>
        </div>
        <div class="user-profile-footer">
            <img src="https://ui-avatars.com/api/?name=Kareem+Ahmed&background=3b82f6&color=fff" alt="Kareem">
            <div>
                <h4 style="font-size: 15px; font-weight:700;">كريم أحمد</h4>
                <p style="font-size: 12px; color: var(--text-muted);" class="lang-text" data-ar="مدير النظام العام" data-en="Root Administrator">مدير النظام العام</p>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h1 class="lang-text" data-ar="غرفة معالجة وبث الأخبار" data-en="Global News Broadcasting System">غرفة معالجة وبث الأخبار</h1>
                <p style="color: var(--text-muted); margin-top: 6px;" class="lang-text" data-ar="بث ونشر التحديثات الإخبارية فوراً إلى خادم البيانات الرئيسي" data-en="Deploy fresh content instantly to core database infrastructure">بث ونشر التحديثات الإخبارية فوراً إلى خادم البيانات الرئيسي</p>
            </div>
            <button class="btn-lang-toggle" onclick="toggleLanguage()">
                <i class="fa-solid fa-globe" style="color: var(--accent-blue);"></i>
                <span id="langBtnText">English</span>
            </button>
        </div>

        <div class="system-status-panel">
            <div class="status-card">
                <div class="status-circle-container"><div class="rotating-ring ring-blue"></div><div class="core-orb orb-blue"><i class="fa-solid fa-server"></i></div></div>
                <div class="status-info"><h5>حالة السيرفر</h5><p>مستقر وآمن</p></div>
            </div>
            <div class="status-card">
                <div class="status-circle-container"><div class="rotating-ring ring-green"></div><div class="core-orb orb-green"><i class="fa-solid fa-database"></i></div></div>
                <div class="status-info"><h5>مزامنة البيانات</h5><p>مستمرة (0.02ms)</p></div>
            </div>
        </div>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <span class="lang-text" data-ar="تم تنفيذ العملية ومزامنة قاعدة البيانات بنجاح الكلي." data-en="Operation execution completed flawlessly.">تم تنفيذ العملية ومزامنة قاعدة البيانات بنجاح الكلي.</span></div>
        <?php elseif (isset($_GET['status']) && $_GET['status'] == 'error'): ?>
            <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <strong>خطأ في التنفيذ: <?php echo htmlspecialchars($_GET['msg'] ?? 'الحقول غير مكتملة'); ?></strong></div>
        <?php endif; ?>

        <form action="insert-news.php" method="POST" enctype="multipart/form-data" id="newsForm" class="form-container">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="news_id" id="newsFormId" value="">

            <h3 id="formPanelTitle" style="font-size:18px; font-weight:700; margin-bottom:25px; color:#fff;">تحضير مصفوفة مقال جديد</h3>

            <div class="form-grid">
                <div>
                    <div class="form-group">
                        <label for="title"><i class="fa-solid fa-heading"></i> <span>عنوان الخبر الرئيسي</span></label>
                        <div class="input-icon-wrapper"><i class="fa-solid fa-pen-nib field-icon"></i><input type="text" name="title" id="title" class="form-control" required oninput="generateAutomatedSlug(this.value)"></div>
                    </div>
                    <div class="form-group">
                        <label for="slug"><i class="fa-solid fa-link"></i> <span>الرابط المؤرشف (Slug)</span></label>
                        <div class="input-icon-wrapper"><i class="fa-solid fa-fingerprint field-icon"></i><input type="text" name="slug" id="slug" class="form-control" readonly></div>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-align-right"></i> <span>المحتوى التفصيلي</span></label>
                        <textarea name="content" id="editor"></textarea>
                    </div>
                </div>
                <div>
                    <div class="form-group">
                        <label for="category_id"><i class="fa-solid fa-tags"></i> <span>القسم الهيكلي</span></label>
                        <div class="select-wrapper input-icon-wrapper">
                            <i class="fa-solid fa-layer-group field-icon"></i><i class="fa-solid fa-chevron-down select-chevron"></i>
                            <select name="category_id" id="category_id" class="form-control" required>
                                <option value="">اختر القسم المستهدف...</option>
                                <?php foreach($categories as $cat): ?><option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="author"><i class="fa-solid fa-user-feather"></i> <span>اسم الكاتب</span></label>
                        <div class="input-icon-wrapper"><i class="fa-solid fa-user field-icon"></i><input type="text" name="author" id="author" class="form-control" value="كريم أحمد"></div>
                    </div>
                    <div class="form-group">
                        <label for="tags"><i class="fa-solid fa-hashtag"></i> <span>الوسوم</span></label>
                        <div class="input-icon-wrapper"><i class="fa-solid fa-key field-icon"></i><input type="text" name="tags" id="tags" class="form-control"></div>
                    </div>
                    <div class="form-group">
                        <label for="status"><i class="fa-solid fa-eye"></i> <span>حالة الرؤية</span></label>
                        <div class="select-wrapper input-icon-wrapper">
                            <i class="fa-solid fa-server field-icon"></i><i class="fa-solid fa-chevron-down select-chevron"></i>
                            <select name="status" id="status" class="form-control">
                                <option value="Published">بث ونشر فوري</option>
                                <option value="Draft">تجميد كمسودة</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-group" for="is_breaking">
                            <div><i class="fa-solid fa-bolt" style="color:#f43f5e;"></i><span>تثبيت كخبر عاجل</span></div>
                            <input type="checkbox" name="is_breaking" id="is_breaking" value="1">
                        </label>
                    </div>
                    <div class="form-group">
                        <label><i class="fa-solid fa-image"></i> <span>صورة الغلاف</span></label>
                        <div class="image-drop-zone" onclick="document.getElementById('imageInput').click()">
                            <i class="fa-solid fa-photo-film"></i><p style="color:var(--text-muted); font-size:14px;">اضغط هنا لرفع ملف الصورة</p>
                            <input type="file" name="image" id="imageInput" accept="image/*" style="display:none;" onchange="previewImage(event)">
                            <img id="imagePreview" alt="Preview">
                        </div>
                    </div>
                    <div style="margin-top:30px;">
                        <button type="submit" class="btn-submit" id="mainSubmitBtn"><i class="fa-solid fa-circle-nodes"></i> <span>مزامنة ونشر البيانات</span></button>
                        <button type="button" class="btn-submit btn-cancel-edit" id="cancelEditBtn" onclick="resetFormToNormal()">إلغاء وضع التعديل</button>
                    </div>
                </div>
            </div>
        </form>

        <div class="management-container">
            <h3><i class="fa-solid fa-database" style="color:var(--accent-blue);"></i> <span>المقالات النشطة حالياً</span></h3>
            <div class="news-manage-grid">
                <?php foreach($latest_news as $news): ?>
                    <div class="news-card">
                        <img src="<?php echo !empty($news['image']) ? htmlspecialchars($news['image']) : 'https://placehold.co/600x400/0b1329/ffffff?text=K-NEWS'; ?>" class="news-card-img" alt="Cover">
                        <div class="news-card-body">
                            <span class="news-card-tag"><?php echo htmlspecialchars($news['category_name'] ?? 'عام'); ?></span>
                            <?php if(!empty($news['is_breaking'])): ?><span class="news-card-breaking">عاجل</span><?php endif; ?>
                            <h4 class="news-card-title"><?php echo htmlspecialchars($news['title']); ?></h4>
                        </div>
                        <div class="news-card-actions">
                            <button type="button" class="action-btn btn-edit" onclick="triggerNewsEdit(<?php echo htmlspecialchars(json_encode($news, JSON_HEX_QUOT)); ?>)"><i class="fa-solid fa-pen-to-square"></i> تعديل</button>
                            <form action="add-news.php" method="POST" style="flex:1;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
                                <button type="submit" class="action-btn btn-delete"><i class="fa-solid fa-trash-can"></i> حذف</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        let globalEditorInstance;
        ClassicEditor.create(document.querySelector('#editor'), { 
            language: 'ar'
        }).then(editor => { 
            globalEditorInstance = editor; 
        }).catch(error => {
            console.error(error);
        });

        function generateAutomatedSlug(text) {
            if (document.getElementById('formAction').value === 'add') {
                document.getElementById('slug').value = text.toLowerCase().replace(/[^a-zA-Z0-9\u0621-\u064A\s]/g, '').replace(/\s+/g, '-');
            }
        }

        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const preview = document.getElementById('imagePreview');
                preview.src = reader.result; preview.style.display = 'block';
            }
            if(event.target.files[0]) reader.readAsDataURL(event.target.files[0]);
        }

        function triggerNewsEdit(newsObject) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('newsFormId').value = newsObject.id;
            document.getElementById('newsForm').action = 'add-news.php';
            document.getElementById('title').value = newsObject.title;
            document.getElementById('slug').value = newsObject.slug;
            document.getElementById('category_id').value = newsObject.category_id;
            document.getElementById('author').value = newsObject.author;
            document.getElementById('tags').value = newsObject.tags;
            document.getElementById('status').value = newsObject.status;
            document.getElementById('is_breaking').checked = parseInt(newsObject.is_breaking) === 1;

            if (globalEditorInstance) globalEditorInstance.setData(newsObject.content);
            if(newsObject.image) {
                const preview = document.getElementById('imagePreview');
                preview.src = newsObject.image; preview.style.display = 'block';
            }
            document.getElementById('cancelEditBtn').style.display = 'block';
            window.scrollTo({ top: document.getElementById('newsForm').offsetTop - 30, behavior: 'smooth' });
        }

        function resetFormToNormal() {
            document.getElementById('newsForm').reset();
            document.getElementById('newsForm').action = 'insert-news.php';
            document.getElementById('formAction').value = 'add';
            document.getElementById('imagePreview').style.display = 'none';
            if (globalEditorInstance) globalEditorInstance.setData('');
            document.getElementById('cancelEditBtn').style.display = 'none';
        }

        let currentLang = 'ar';
        function toggleLanguage() {
            currentLang = currentLang === 'ar' ? 'en' : 'ar';
            document.getElementById('pageHtml').setAttribute('dir', currentLang === 'ar' ? 'rtl' : 'ltr');
            document.getElementById('langBtnText').textContent = currentLang === 'ar' ? 'English' : 'العربية';
        }

        window.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('worldBg'); const ctx = canvas.getContext('2d');
            let width = canvas.width = window.innerWidth; let height = canvas.height = window.innerHeight;
            let particles = [];
            for (let i = 0; i < 30; i++) { particles.push({ x: Math.random() * width, y: Math.random() * height, radius: Math.random() * 2 + 1, vx: Math.random() * 0.4 - 0.2, vy: Math.random() * 0.4 - 0.2 }); }
            function drawParticles() {
                ctx.clearRect(0, 0, width, height); ctx.fillStyle = "rgba(59, 130, 246, 0.2)";
                particles.forEach(p => { p.x += p.vx; p.y += p.vy; if (p.x < 0 || p.x > width) p.vx = -p.vx; if (p.y < 0 || p.y > height) p.vy = -p.vy; ctx.beginPath(); ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2); ctx.fill(); });
                requestAnimationFrame(drawParticles);
            }
            drawParticles();
        });
    </script>
</body>
</html>