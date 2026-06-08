<?php
// categories.php - واجهة المعالجة الهيكلية وإدارة الأقسام العالمية والتحكم الذكي
require_once 'db.php';

// 1. معالجة العمليات الخلفية (حذف - تعديل - إضافة)
$alert_status = null;
$alert_msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- عملية إضافة قسم جديد ---
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        
        if (empty($name)) {
            $alert_status = 'error';
            $alert_msg = 'name_required';
        } else {
            if (empty($slug)) {
                $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
            }
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                $stmt->execute([$name, $slug]);
                $alert_status = 'success';
                $alert_msg = 'add_success';
            } catch (PDOException $e) {
                $alert_status = 'error';
                $alert_msg = 'duplicate_or_error';
            }
        }
    }
    
    // --- عملية تعديل قسم حالي ---
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        
        if ($id > 0 && !empty($name)) {
            if (empty($slug)) {
                $slug = strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $name));
            }
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $id]);
                $alert_status = 'success';
                $alert_msg = 'update_success';
            } catch (PDOException $e) {
                $alert_status = 'error';
                $alert_msg = 'update_error';
            }
        }
    }

    // --- عملية حذف قسم ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $alert_status = 'success';
                $alert_msg = 'delete_success';
            } catch (PDOException $e) {
                $alert_status = 'error';
                $alert_msg = 'delete_error';
            }
        }
    }
}

// 2. جلب جميع الأقسام المتاحة ومزامنتها مرتبة أبجدياً
try {
    $all_categories = $pdo->query("SELECT c.*, COUNT(n.id) as news_count FROM categories c LEFT JOIN news n ON c.id = n.category_id GROUP BY c.id ORDER BY c.name ASC")->fetchAll();
} catch (PDOException $e) {
    $all_categories = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl" id="pageHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS - إدارة الهياكل والأقسام العالمية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    
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
            
            /* ألوان حالات النظام الافتراضية للدوائر */
            --status-color: #10b981; /* أخضر افتراضي */
            --status-glow: rgba(16, 185, 129, 0.4);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; transition: background-color 0.3s, border-color 0.3s; }
        html[dir="rtl"] * { font-family: 'Cairo', sans-serif; }
        html[dir="ltr"] * { font-family: 'Inter', sans-serif; }
        
        body { background-color: var(--bg-primary); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; }

        #worldBg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.6; }
        .sidebar, .main-content { position: relative; z-index: 1; }
        
        /* السايد بار الاحترافي المطور */
        .sidebar { width: 290px; background: linear-gradient(180deg, #070c1b 0%, #0c1428 100%); padding: 35px 24px; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 4px 0 25px rgba(0,0,0,0.5); }
        html[dir="rtl"] .sidebar { border-left: 1px solid var(--border-color); }
        html[dir="ltr"] .sidebar { border-right: 1px solid var(--border-color); }

        .logo-area { font-size: 28px; font-weight: 800; letter-spacing: 1px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .logo-area span { color: var(--accent-blue); text-shadow: 0 0 20px var(--accent-glow); }

        /* ==================== نظام دوائر حالة النظام الاحترافي المطور ==================== */
        .system-status-wrapper {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
        }
        .status-rings-container {
            position: relative;
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        /* الدائرة المركزية الثابتة والنابضة */
        .status-dot-center {
            width: 14px;
            height: 14px;
            background-color: var(--status-color);
            border-radius: 50%;
            box-shadow: 0 0 15px 5px var(--status-glow);
            animation: pulseCore 1.8s infinite ease-in-out;
            z-index: 3;
        }
        /* الحلقة الكبيرة الخارجية (تلف لليمين) */
        .status-ring-outer {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 2px dashed var(--status-color);
            border-radius: 50%;
            opacity: 0.5;
            animation: rotateClockwise 12s linear infinite;
            z-index: 2;
        }
        /* الحلقة الداخلية الوسطى (تلف لليسار) */
        .status-ring-inner {
            position: absolute;
            width: 75%;
            height: 75%;
            border: 2px dotted var(--status-color);
            border-radius: 50%;
            opacity: 0.7;
            animation: rotateCounterClockwise 7s linear infinite;
            z-index: 1;
        }
        /* الحركات الدائرية والنبض */
        @keyframes rotateClockwise {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes rotateCounterClockwise {
            0% { transform: rotate(360deg); }
            100% { transform: rotate(0deg); }
        }
        @keyframes pulseCore {
            0%, 100% { transform: scale(1); opacity: 1; box-shadow: 0 0 12px 3px var(--status-glow); }
            50% { transform: scale(1.2); opacity: 0.8; box-shadow: 0 0 18px 6px var(--status-glow); }
        }
        /* نصوص الحالة بجانب الدوائر */
        .status-info { display: flex; flex-direction: column; }
        .status-label { font-size: 11px; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-text { font-size: 13px; font-weight: 700; color: #fff; transition: color 0.3s; }
        /* ========================================================================= */

        .menu-list { list-style: none; }
        .menu-item { padding: 14px 18px; border-radius: 12px; margin-bottom: 12px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-size: 15px; font-weight: 600; text-decoration: none; }
        
        /* حاوي الأيقونة مع الدائرة المتحركة المضيئة الصغيرة داخل المنيو */
        .icon-container {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        html[dir="rtl"] .menu-item .icon-container { margin-left: 16px; }
        html[dir="ltr"] .menu-item .icon-container { margin-right: 16px; }

        .icon-container::before {
            content: '';
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            border-radius: 50%;
            border: 1px dashed rgba(59, 130, 246, 0.4);
            animation: rotateGlow 8s linear infinite;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .menu-item:hover .icon-container::before,
        .menu-item.active .icon-container::before {
            opacity: 1;
            border-color: rgba(255, 255, 255, 0.6);
        }
        .menu-item.active .icon-container {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.2);
        }

        @keyframes rotateGlow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .menu-item:hover { color: var(--text-main); background-color: rgba(255,255,255,0.02); }
        .menu-item.active { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff; box-shadow: 0 4px 25px rgba(29, 78, 216, 0.45); }
        .menu-item.active i { color: #fff !important; }

        .user-profile-footer { display: flex; align-items: center; padding-top: 25px; border-top: 1px solid var(--border-color); }
        html[dir="rtl"] .user-profile-footer img { margin-left: 14px; }
        html[dir="ltr"] .user-profile-footer img { margin-right: 14px; }
        .user-profile-footer img { width: 46px; height: 46px; border-radius: 50%; border: 2px solid var(--accent-blue); }

        /* المحتوى الرئيسي والفورم */
        .main-content { flex: 1; padding: 45px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .top-header h1 { font-size: 32px; font-weight: 800; background: linear-gradient(to right, #ffffff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .btn-lang-toggle { background: rgba(11, 19, 41, 0.6); backdrop-filter: blur(8px); border: 1px solid var(--border-color); color: #cbd5e1; padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        
        /* تصميم نظام كروت التقسيم الهيكلي */
        .form-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 35px; }
        @media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } }

        .form-container, .management-container { background: rgba(11, 19, 41, 0.75); backdrop-filter: blur(12px); border: 1px solid var(--border-color); border-radius: 20px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.6); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #94a3b8; font-size: 14px; font-weight: 700; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        
        .title-icon-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .title-icon-wrapper::after {
            content: '';
            position: absolute;
            width: 100%; height: 100%;
            border-radius: 50%;
            border: 1px solid var(--accent-blue);
            animation: pulseGlow 2s infinite;
        }
        @keyframes pulseGlow {
            0% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(1.3); opacity: 0; }
        }

        .form-group label i { color: var(--accent-blue); }

        .input-icon-wrapper { position: relative; display: flex; align-items: center; }
        .input-icon-wrapper i.field-icon { position: absolute; color: var(--text-muted); font-size: 16px; pointer-events: none; z-index: 5; }
        html[dir="rtl"] .input-icon-wrapper i.field-icon { right: 16px; }
        html[dir="ltr"] .input-icon-wrapper i.field-icon { left: 16px; }
        html[dir="rtl"] .input-icon-wrapper .form-control { padding-right: 48px; }
        html[dir="ltr"] .input-icon-wrapper .form-control { padding-left: 48px; }

        .form-control { width: 100%; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; color: #ffffff; font-size: 14px; outline: none; }
        .input-icon-wrapper:focus-within .form-control { border-color: var(--accent-blue); box-shadow: 0 0 15px rgba(59, 130, 246, 0.2); }

        .btn-submit { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; border: none; padding: 15px 25px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 25px rgba(29, 78, 216, 0.3); }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(29, 78, 216, 0.5); }

        /* ستايل جدول وجريد الأقسام */
        .category-table-wrapper { width: 100%; overflow-x: auto; margin-top: 15px; }
        .custom-table { width: 100%; border-collapse: collapse; text-align: right; color: #e2e8f0; }
        html[dir="ltr"] .custom-table { text-align: left; }
        .custom-table th { background-color: rgba(7, 12, 27, 0.6); padding: 16px; font-size: 14px; font-weight: 700; color: #94a3b8; border-bottom: 2px solid var(--border-color); }
        .custom-table td { padding: 16px; font-size: 14px; border-bottom: 1px solid var(--border-color); }
        .custom-table tr:hover { background-color: rgba(255, 255, 255, 0.02); }

        .badge-count { background: rgba(59, 130, 246, 0.12); color: #3b82f6; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; }
        
        /* أزرار العمليات الديناميكية */
        .action-btns-group { display: flex; gap: 8px; }
        .mini-btn { border: none; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; }
        .btn-mini-edit { color: var(--accent-blue); } .btn-mini-edit:hover { background: rgba(59, 130, 246, 0.1); border-color: var(--accent-blue); }
        .btn-mini-delete { color: #f43f5e; } .btn-mini-delete:hover { background: rgba(244, 63, 94, 0.1); border-color: #f43f5e; }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-error { background: rgba(244, 63, 94, 0.1); border: 1px solid #f43f5e; color: #f43f5e; }
    </style>
</head>
<body>

    <canvas id="worldBg"></canvas>

    <div class="sidebar">
        <div>
            <div class="logo-area">
                <div class="title-icon-wrapper" style="background:none; border:none; width:40px; height:40px;">
                    <i class="fa-solid fa-satellite-dish" style="color:#3b82f6; position:relative; z-index:2;"></i>
                </div>
                K<span>·NEWS</span>
            </div>
            <!-- مكون الدوائر الاحترافية لحالة النظام بـ 4 ألوان (تتغير عند الضغط عليها كمثال تفاعلي) -->
            <div class="system-status-wrapper" onclick="rotateSystemStatus()" title="اضغط لتبديل حالة النظام يدوياً للتحقق">
                <div class="status-rings-container">
                    <div class="status-ring-outer" id="ringOuter"></div>
                    <div class="status-ring-inner" id="ringInner"></div>
                    <div class="status-dot-center" id="dotCenter"></div>
                </div>
                <div class="status-info">
                    <span class="status-label lang-text" data-ar="حالة الخادم" data-en="CORE STATUS">حالة الخادم</span>
                    <span class="status-text" id="statusText" data-ar="مستقر وآمن" data-en="Optimal">مستقر وآمن</span>
                </div>
            </div>

            <ul class="menu-list">
                <li><a href="dashboard.php" class="menu-item">
                    <div class="icon-container"><i class="fa-solid fa-chart-pie" style="color:var(--text-muted);"></i></div>
                    <span class="lang-text" data-ar="لوحة التحكم" data-en="Dashboard">لوحة التحكم</span>
                </a></li>
                <li><a href="add-news.php" class="menu-item">
                    <div class="icon-container"><i class="fa-solid fa-square-plus" style="color:var(--text-muted);"></i></div>
                    <span class="lang-text" data-ar="إضافة خبر" data-en="Add News">إضافة خبر</span>
                </a></li>
                <li><a href="manage-news.php" class="menu-item">
                    <div class="icon-container"><i class="fa-solid fa-newspaper" style="color:var(--text-muted);"></i></div>
                    <span class="lang-text" data-ar="إدارة الأخبار" data-en="Manage News">إدارة الأخبار</span>
                </a></li>
                <li><a href="categories.php" class="menu-item active">
                    <div class="icon-container"><i class="fa-solid fa-layer-group" style="color:#fff;"></i></div>
                    <span class="lang-text" data-ar="الأقسام" data-en="Categories">الأقسام</span>
                </a></li>
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
                <h1 id="panelTitle" class="lang-text" data-ar="هيكلة وتصنيف المجموعات الإخبارية" data-en="Grid Partition & Category Infrastructure">هيكلة وتصنيف المجموعات الإخبارية</h1>
                <p style="color: var(--text-muted); margin-top: 6px;" class="lang-text" data-ar="تعديل، حذف، وإنشاء الفروع الهيكلية لبث البيانات" data-en="Configure, purge and deploy dynamic segments for the network core">تعديل، حذف، وإنشاء الفروع الهيكلية لبث البيانات</p>
            </div>
            <button class="btn-lang-toggle" onclick="toggleLanguage()">
                <i class="fa-solid fa-globe" style="color: var(--accent-blue);"></i>
                <span id="langBtnText">English</span>
            </button>
        </div>

        <?php if ($alert_status === 'success'): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span class="lang-text" 
                      data-ar="<?php echo $alert_msg == 'add_success' ? 'تمت إضافة القسم بنجاح للمصفوفة.' : ($alert_msg == 'update_success' ? 'تم تحديث بيانات ومسار القسم بنجاح.' : 'تم تطهير وحذف القسم النهائي من الخادم الكلي.'); ?>" 
                      data-en="Data synchronization executed successfully across database architecture.">
                      <?php echo $alert_msg == 'add_success' ? 'تمت إضافة القسم بنجاح للمصفوفة.' : ($alert_msg == 'update_success' ? 'تم تحديث بيانات ومسار القسم بنجاح.' : 'تم تطهير وحذف القسم النهائي من الخادم الكلي.'); ?>
                </span>
            </div>
        <?php elseif ($alert_status === 'error'): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span class="lang-text" data-ar="فشلت العملية المحددة: خطأ في البنية أو البيانات مكررة." data-en="Operation aborted: Structure conflict or duplicate resource identifier entry.">فشلت العملية المحددة: خطأ في البنية أو البيانات مكررة.</span>
            </div>
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-container">
                <h3 id="formModeTitle" style="font-size: 18px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:12px;">
                    <span class="title-icon-wrapper"><i class="fa-solid fa-folder-plus" style="font-size:14px;"></i></span>
                    <span class="lang-text" data-ar="إنشاء قسم جديد" data-en="Deploy New Category">إنشاء قسم جديد</span>
                </h3>
                
                <form action="categories.php" method="POST" id="categoryForm">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="categoryId" value="">

                    <div class="form-group">
                        <label for="catName"><i class="fa-solid fa-signature"></i> <span class="lang-text" data-ar="اسم القسم الحصري" data-en="Exclusive Segment Name">اسم القسم الحصري</span></label>
                        <div class="input-icon-wrapper">
                            <i class="fa-solid fa-paragraph field-icon"></i>
                            <input type="text" name="name" id="catName" class="form-control" placeholder="أخبار الاقتصاد، العلوم..." required oninput="generateSlug(this.value)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="catSlug"><i class="fa-solid fa-link"></i> <span class="lang-text" data-ar="مسار الأرشفة الذكي (Slug)" data-en="SEO URL String (Slug)">مسار الأرشفة الذكي (Slug)</span></label>
                        <div class="input-icon-wrapper">
                            <i class="fa-solid fa-fingerprint field-icon"></i>
                            <input type="text" name="slug" id="catSlug" class="form-control" placeholder="economy-news">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fa-solid fa-circle-nodes"></i>
                        <span class="lang-text" data-ar="حفظ وتثبيت القسم" data-en="Commit To Network Data">حفظ وتثبيت القسم</span>
                    </button>
                    
                    <button type="button" class="btn-submit" id="cancelEditBtn" style="background:var(--bg-primary); border:1px solid var(--border-color); color:var(--text-muted); margin-top:10px; display:none;" onclick="resetFormState()">
                        <span class="lang-text" data-ar="إلغاء التعديل" data-en="Cancel Override">إلغاء التعديل</span>
                    </button>
                </form>
            </div>

            <div class="management-container">
                <h3 style="font-size: 18px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:12px;">
                    <span class="title-icon-wrapper"><i class="fa-solid fa-database" style="font-size:14px;"></i></span>
                    <span class="lang-text" data-ar="الأقسام المتاحة بالشبكة الأساسية" data-en="Active Pipeline Directory Nodes">الأقسام المتاحة بالشبكة الأساسية</span>
                </h3>

                <div class="category-table-wrapper">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th class="lang-text" data-ar="اسم التقسيم" data-en="Node Title">اسم التقسيم</th>
                                <th class="lang-text" data-ar="مسار الرابط" data-en="Routing Slug">مسار الرابط</th>
                                <th class="lang-text" data-ar="حجم المقالات" data-en="Data Density">حجم المقالات</th>
                                <th class="lang-text" data-ar="التحكم والتحوير" data-en="System Actions">التحكم والتحوير</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_categories)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; color:var(--text-muted);" class="lang-text" data-ar="لا توجد عقد تصنيفية نشطة حالياً." data-en="Zero segment nodes detected inside the registry cluster.">لا توجد عقد تصنيفية نشطة حالياً.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($all_categories as $cat): ?>
                                    <tr>
                                        <td>#<?php echo $cat['id']; ?></td>
                                        <td style="font-weight:600; color:#fff;"><?php echo htmlspecialchars($cat['name']); ?></td>
                                        <td style="color:var(--accent-blue); font-family:'Inter', sans-serif; font-size:13px;">/category/<?php echo htmlspecialchars($cat['slug']); ?></td>
                                        <td><span class="badge-count"><?php echo $cat['news_count']; ?> <span class="lang-text" data-ar="خبر" data-en="News">خبر</span></span></td>
                                        <td>
                                            <div class="action-btns-group">
                                                <button class="mini-btn btn-mini-edit" onclick="triggerEditState(<?php echo $cat['id']; ?>, '<?php echo addslashes($cat['name']); ?>', '<?php echo addslashes($cat['slug']); ?>')">
                                                    <i class="fa-solid fa-sliders"></i> <span class="lang-text" data-ar="تعديل" data-en="Patch">تعديل</span>
                                                </button>
                                                
                                                <form action="categories.php" method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من رغبتك في بتر هذا القسم نهائياً من قاعدة البيانات الإخبارية؟');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                    <button type="submit" class="mini-btn btn-mini-delete">
                                                        <i class="fa-solid fa-trash-can"></i> <span class="lang-text" data-ar="حذف" data-en="Purge">حذف</span>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // دالة تحويل الاسم لرابط بشكل صديق للسيو تلقائياً
        function generateSlug(text) {
            if (document.getElementById('formAction').value === 'add') {
                let slug = text.toLowerCase()
                               .replace(/[^a-zA-Z0-9\u0621-\u064A\s]/g, '')
                               .replace(/\s+/g, '-');
                document.getElementById('catSlug').value = slug;
            }
        }

        // تحويل الفورم إلى حالة التعديل الديناميكي وحقن البيانات فوراً
        function triggerEditState(id, name, slug) {
            document.getElementById('formAction').value = 'update';
            document.getElementById('categoryId').value = id;
            document.getElementById('catName').value = name;
            document.getElementById('catSlug').value = slug;
            
            document.getElementById('formModeTitle').querySelector('.lang-text').setAttribute('data-ar', 'تعديل وتحديث القسم الحالي');
            document.getElementById('formModeTitle').querySelector('.lang-text').setAttribute('data-en', 'Override Category Configuration');
            document.getElementById('submitBtn').querySelector('span').setAttribute('data-ar', 'تحديث وتثبيت التعديل');
            document.getElementById('submitBtn').querySelector('span').setAttribute('data-en', 'Apply Pipeline Override');
            
            document.getElementById('cancelEditBtn').style.display = 'block';
            applyCurrentLanguageStrings();
            
            document.getElementById('catName').focus();
        }

        // إرجاع الفورم لوضع الإضافة الطبيعي
        function resetFormState() {
            document.getElementById('categoryForm').reset();
            document.getElementById('formAction').value = 'add';
            document.getElementById('categoryId').value = '';
            
            document.getElementById('formModeTitle').querySelector('.lang-text').setAttribute('data-ar', 'إنشاء قسم جديد');
            document.getElementById('formModeTitle').querySelector('.lang-text').setAttribute('data-en', 'Deploy New Category');
            document.getElementById('submitBtn').querySelector('span').setAttribute('data-ar', 'حفظ وتثبيت القسم');
            document.getElementById('submitBtn').querySelector('span').setAttribute('data-en', 'Commit To Network Data');
            
            document.getElementById('cancelEditBtn').style.display = 'none';
            applyCurrentLanguageStrings();
        }

        // نظام لغات اللوحة
        let currentLang = localStorage.getItem('k_news_lang') || 'ar';
        
        function applyCurrentLanguageStrings() {
            const html = document.getElementById('pageHtml');
            html.setAttribute('dir', currentLang === 'ar' ? 'rtl' : 'ltr');
            html.setAttribute('lang', currentLang);
            
            document.querySelectorAll('.lang-text').forEach(el => {
                let text = el.getAttribute('data-' + currentLang);
                if(text) el.textContent = text;
            });
            
            document.getElementById('langBtnText').textContent = currentLang === 'ar' ? 'English' : 'العربية';
        }

        function toggleLanguage() {
            currentLang = currentLang === 'ar' ? 'en' : 'ar';
            localStorage.setItem('k_news_lang', currentLang);
            applyCurrentLanguageStrings();
        }

        // ==================== محاكي حالات النظام التفاعلي بـ 4 ألوان ====================
        const systemStates = [
            { color: '#10b981', glow: 'rgba(16, 185, 129, 0.4)', ar: 'مستقر وآمن', en: 'Optimal' },       // 1. أخضر: مستقر
            { color: '#3b82f6', glow: 'rgba(59, 130, 246, 0.4)', ar: 'جاري التحديث', en: 'Syncing' },    // 2. أزرق: تحديث ومزامنة
            { color: '#f59e0b', glow: 'rgba(245, 158, 11, 0.4)', ar: 'ضغط مرتفع', en: 'High Load' },     // 3. برتقالي: ضغط خفيف
            { color: '#f43f5e', glow: 'rgba(244, 63, 94, 0.4)', ar: 'خطر غير مستقر', en: 'Critical' }   // 4. أحمر: خطر
        ];
        let currentStateIndex = 0;

        function updateSystemVisuals() {
            const state = systemStates[currentStateIndex];
            
            // حقن الألوان الجديدة لمتغيرات الـ CSS الخاصة بالدوائر المضيئة
            document.documentElement.style.setProperty('--status-color', state.color);
            document.documentElement.style.setProperty('--status-glow', state.glow);
            
            // تحديث النصوص والترجمات فوراً
            const textEl = document.getElementById('statusText');
            textEl.setAttribute('data-ar', state.ar);
            textEl.setAttribute('data-en', state.en);
            textEl.textContent = currentLang === 'ar' ? state.ar : state.en;
        }

        function rotateSystemStatus() {
            currentStateIndex = (currentStateIndex + 1) % systemStates.length;
            updateSystemVisuals();
        }
        // =========================================================================

        window.addEventListener('DOMContentLoaded', () => {
            applyCurrentLanguageStrings();
            updateSystemVisuals(); // تشغيل حالة النظام عند الإقلاع
            
            // --- نظام محاكاة النيازك المتقدم والنجوم المتلألئة (Canvas) ---
            const canvas = document.getElementById('worldBg');
            const ctx = canvas.getContext('2d');
            let width = canvas.width = window.innerWidth;
            let height = canvas.height = window.innerHeight;
            
            window.addEventListener('resize', () => {
                width = canvas.width = window.innerWidth;
                height = canvas.height = window.innerHeight;
            });

            // النجوم الثابتة المتلألئة
            let stars = [];
            for (let i = 0; i < 60; i++) {
                stars.push({
                    x: Math.random() * width,
                    y: Math.random() * height,
                    size: Math.random() * 1.5,
                    alpha: Math.random(),
                    speed: Math.random() * 0.02 + 0.005
                });
            }

            // النيازك (الشهب) المتحركة
            let meteors = [];
            function createMeteor() {
                meteors.push({
                    x: Math.random() * (width * 0.8),
                    y: -20,
                    length: Math.random() * 80 + 50,
                    speed: Math.random() * 6 + 4,
                    thick: Math.random() * 1.5 + 0.5,
                    alpha: 1
                });
            }

            setInterval(() => {
                if(meteors.length < 5) { createMeteor(); }
            }, 1200);
            
            function animate() {
                ctx.clearRect(0, 0, width, height);
                
                // 1. رسم وتحديث النجوم
                stars.forEach(s => {
                    s.alpha += s.speed;
                    if (s.alpha > 1 || s.alpha < 0) s.speed = -s.speed;
                    ctx.fillStyle = `rgba(255, 255, 255, ${Math.max(0, s.alpha)})`;
                    ctx.beginPath();
                    ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
                    ctx.fill();
                });

                // 2. رسم وتحديث النيازك المتحركة
                meteors.forEach((m, index) => {
                    m.x += m.speed;
                    m.y += m.speed * 0.75;
                    m.alpha -= 0.005;

                    if (m.alpha <= 0 || m.x > width || m.y > height) {
                        meteors.splice(index, 1);
                    } else {
                        ctx.save();
                        ctx.strokeStyle = `rgba(59, 130, 246, ${m.alpha})`;
                        ctx.lineWidth = m.thick;
                        ctx.shadowBlur = 10;
                        ctx.shadowColor = '#3b82f6';
                        
                        ctx.beginPath();
                        ctx.moveTo(m.x, m.y);
                        ctx.lineTo(m.x - m.length, m.y - (m.length * 0.75));
                        ctx.stroke();
                        ctx.restore();
                    }
                });
                
                requestAnimationFrame(animate);
            }
            animate();
        });
    </script>
</body>
</html>