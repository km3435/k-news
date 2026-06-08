<?php
// manage-news.php - واجهة فرز وتطهير وإدارة المقالات الإخبارية مدعومة بمؤشرات الصعود والتريند الذكية
require_once 'db.php';

// --- نظام معالجة عمليات الحذف (Purge) عبر POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $news_id = intval($_POST['id'] ?? 0);
    if ($news_id > 0) {
        try {
            $img_stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
            $img_stmt->execute([$news_id]);
            $img_data = $img_stmt->fetch();
            if (!empty($img_data['image']) && file_exists($img_data['image'])) {
                @unlink($img_data['image']);
            }

            $del_stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
            $del_stmt->execute([$news_id]);
            
            header("Location: manage-news.php?status=success&msg=delete_success");
            exit;
        } catch (PDOException $e) {
            header("Location: manage-news.php?status=error&msg=delete_error");
            exit;
        }
    }
}

// 1. حساب متوسط المشاهدات العام في الموقع لمعرفة الأخبار التي تتخطى المتوسط (التريند)
$global_avg_views = 0;
try {
    $avg_stmt = $pdo->query("SELECT AVG(views) as avg_v FROM news");
    $avg_res = $avg_stmt->fetch();
    $global_avg_views = floatval($avg_res['avg_v'] ?? 0);
} catch (PDOException $e) { }

// 2. جلب جميع الأقسام
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = []; 
}

// 3. جلب كافة الأخبار مجمعة ومفرزة حسب القسم والأحدث أولاً
try {
    $all_news = $pdo->query("
        SELECT n.*, c.name as category_name 
        FROM news n 
        LEFT JOIN categories c ON n.category_id = c.id 
        ORDER BY n.category_id ASC, n.id DESC
    ")->fetchAll();
    
    // تقسيم الأخبار وإحصائيات الأقسام ديناميكياً في الـ PHP
    $news_by_category = [];
    $category_total_views = [];
    $global_total_views = 0;

    foreach ($all_news as $news) {
        $cat_id = $news['category_id'] ?? 0;
        $views = intval($news['views'] ?? 0);
        
        $news_by_category[$cat_id][] = $news;
        
        // تجميع مشاهدات كل قسم
        if (!isset($category_total_views[$cat_id])) {
            $category_total_views[$cat_id] = 0;
        }
        $category_total_views[$cat_id] += $views;
        $global_total_views += $views;
    }
} catch (PDOException $e) {
    $news_by_category = [];
    $category_total_views = [];
    $global_total_views = 0;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl" id="pageHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS - إدارة الأخبار والتحليلات الذكية</title>
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
            
            /* ألوان النيون الجديدة للمؤشرات والتفاعل */
            --trend-up: #10b981;
            --trend-up-glow: rgba(16, 185, 129, 0.3);
            --trend-mid: #f59e0b;
            --trend-mid-glow: rgba(245, 158, 11, 0.3);
            --trend-down: #64748b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; transition: background-color 0.3s, border-color 0.3s; }
        
        html[dir="rtl"] * { font-family: 'Cairo', sans-serif; }
        html[dir="ltr"] * { font-family: 'Inter', sans-serif; }
        
        body { 
            background-color: var(--bg-primary); 
            color: var(--text-main); 
            display: flex; 
            min-height: 100vh; 
            position: relative; 
            overflow-x: hidden; 
        }

        #worldBg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.25; }
        .sidebar, .main-content { position: relative; z-index: 1; }
        
        /* السايد بار */
        .sidebar { 
            width: 290px; 
            background: linear-gradient(180deg, #070c1b 0%, #0c1428 100%); 
            padding: 35px 24px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
            box-shadow: 4px 0 25px rgba(0,0,0,0.5);
        }
        html[dir="rtl"] .sidebar { border-left: 1px solid var(--border-color); }
        html[dir="ltr"] .sidebar { border-right: 1px solid var(--border-color); }

        .logo-area { font-size: 28px; font-weight: 800; letter-spacing: 1px; margin-bottom: 40px; display: flex; align-items: center; gap: 10px; }
        .logo-area span { color: var(--accent-blue); text-shadow: 0 0 20px var(--accent-glow); }
        
        .menu-list { list-style: none; }
        .menu-item { padding: 14px 18px; border-radius: 12px; margin-bottom: 12px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; transition: all 0.3s; font-size: 15px; font-weight: 600; text-decoration: none; }
        html[dir="rtl"] .menu-item i { margin-left: 16px; }
        html[dir="ltr"] .menu-item i { margin-right: 16px; }
        
        .menu-item:hover { color: var(--text-main); background-color: rgba(255,255,255,0.02); }
        .menu-item.active { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff; box-shadow: 0 4px 25px rgba(29, 78, 216, 0.45); }

        .user-profile-footer { display: flex; align-items: center; padding-top: 25px; border-top: 1px solid var(--border-color); }
        html[dir="rtl"] .user-profile-footer img { margin-left: 14px; }
        html[dir="ltr"] .user-profile-footer img { margin-right: 14px; }
        .user-profile-footer img { width: 46px; height: 46px; border-radius: 50%; border: 2px solid var(--accent-blue); }

        /* حاوية المحتوى الرئيسي */
        .main-content { flex: 1; padding: 45px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .top-header h1 { font-size: 32px; font-weight: 800; background: linear-gradient(to right, #ffffff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .btn-lang-toggle { background: rgba(11, 19, 41, 0.6); backdrop-filter: blur(8px); border: 1px solid var(--border-color); color: #cbd5e1; padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .btn-lang-toggle:hover { border-color: var(--accent-blue); box-shadow: 0 0 20px rgba(59, 130, 246, 0.25); }

        /* نظام المؤشرات الحية العلوية */
        .system-status-panel { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 35px; }
        .status-card { background: rgba(11, 19, 41, 0.6); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 20px; }
        
        .status-circle-container { position: relative; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; }
        .rotating-ring { position: absolute; width: 100%; height: 100%; border: 3px dashed transparent; border-radius: 50%; animation: rotateRing 4s linear infinite; }
        .ring-blue { border-top-color: #3b82f6; border-bottom-color: #1d4ed8; }
        .ring-green { border-top-color: var(--trend-up); border-left-color: #059669; animation-duration: 5s; }
        
        .core-orb { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; animation: pulseOrb 2s ease-in-out infinite alternate; }
        .orb-blue { background: rgba(59, 130, 246, 0.2); color: #3b82f6; box-shadow: 0 0 15px var(--accent-glow); }
        .orb-green { background: rgba(16, 185, 129, 0.2); color: var(--trend-up); box-shadow: 0 0 15px var(--trend-up-glow); }

        .status-info h5 { font-size: 13px; color: var(--text-muted); font-weight: 600; margin-bottom: 4px; }
        .status-info p { font-size: 16px; font-weight: 700; color: #ffffff; }

        @keyframes rotateRing { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes pulseOrb { 0% { transform: scale(0.92); opacity: 0.8; } 100% { transform: scale(1.05); opacity: 1; } }

        /* ==================== 🗂️ نظام التبويبات المطور بالمؤشرات الحية 🗂️ ==================== */
        .category-tabs-container {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .category-tabs-container::-webkit-scrollbar { height: 5px; }
        .category-tabs-container::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }

        .tab-btn {
            background: rgba(11, 19, 41, 0.6);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tab-btn:hover { color: #fff; border-color: rgba(59, 130, 246, 0.5); }
        .tab-btn.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(29, 78, 216, 0.05));
            border-color: var(--accent-blue);
            color: var(--accent-blue);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.15);
        }
        
        /* شارات العدادات والتفاعل الإحصائي داخل زر التبويب */
        .tab-badges-group { display: flex; align-items: center; gap: 6px; }
        .tab-btn .badge-count { background: var(--bg-primary); color: #fff; padding: 2px 8px; border-radius: 6px; font-size: 11px; border: 1px solid var(--border-color); }
        .tab-btn .badge-views { background: rgba(59, 130, 246, 0.1); color: #93c5fd; padding: 2px 8px; border-radius: 6px; font-size: 11px; border: 1px solid rgba(59, 130, 246, 0.2); display: flex; align-items: center; gap: 4px; }
        
        .tab-btn.active .badge-count { background: var(--accent-blue); border-color: var(--accent-blue); }
        .tab-btn.active .badge-views { background: rgba(255,255,255,0.15); color: #fff; border-color: transparent; }

        .category-content-panel { display: none; animation: fadeInPane 0.4s ease; }
        .category-content-panel.active { display: block; }

        @keyframes fadeInPane { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* شبكة الكروت والمقالات التابعة للقسم */
        .management-container { background: rgba(11, 19, 41, 0.75); backdrop-filter: blur(12px); border: 1px solid var(--border-color); border-radius: 20px; padding: 40px; box-shadow: 0 20px 50px rgba(0,0,0,0.6); }
        .news-manage-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; margin-top: 15px; }
        
        /* تصميم الكارت والـ Badges الاحترافية */
        .news-card { background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 10px 20px rgba(0,0,0,0.2); transition: all 0.3s; position: relative; }
        .news-card:hover { border-color: var(--accent-blue); transform: translateY(-5px); box-shadow: 0 15px 30px rgba(59, 130, 246, 0.15); }
        
        /* مؤشر السهم العلوي الاحترافي للتريند */
        .trend-indicator-ribbon {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 2;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(8px);
        }
        html[dir="ltr"] .trend-indicator-ribbon { left: auto; right: 12px; }
        
        .ribbon-up { background: rgba(16, 185, 129, 0.15); border: 1px solid var(--trend-up); color: var(--trend-up); box-shadow: 0 0 15px var(--trend-up-glow); }
        .ribbon-mid { background: rgba(245, 158, 11, 0.15); border: 1px solid var(--trend-mid); color: var(--trend-mid); box-shadow: 0 0 15px var(--trend-mid-glow); }
        .ribbon-down { background: rgba(30, 41, 59, 0.7); border: 1px solid var(--border-color); color: var(--text-muted); }

        .news-card-img { width: 100%; height: 180px; object-fit: cover; background-color: var(--bg-secondary); }
        .news-card-body { padding: 20px; flex-grow: 1; }
        
        .badge-wrapper { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .news-card-tag { font-size: 11px; background-color: rgba(59, 130, 246, 0.12); color: #3b82f6; padding: 5px 10px; border-radius: 6px; font-weight: 700; }
        .news-card-breaking { font-size: 11px; background-color: rgba(244, 63, 94, 0.15); color: #f43f5e; padding: 5px 10px; border-radius: 6px; font-weight: 700; }
        
        .news-card-title { font-size: 16px; font-weight: 700; margin-bottom: 12px; line-height: 1.6; height: 50px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; color: #e2e8f0; }
        .news-card-meta { font-size: 13px; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 12px; }
        
        /* تفاعل المشاهدات بالألوان داخل الكارت */
        .meta-views-count { font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .views-up { color: var(--trend-up); }
        .views-mid { color: var(--trend-mid); }
        .views-down { color: var(--text-muted); }

        .news-card-actions { display: flex; border-top: 1px solid var(--border-color); background-color: rgba(7, 12, 27, 0.5); }
        .action-btn { flex: 1; text-align: center; padding: 14px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s; cursor: pointer; border: none; background: transparent; display: flex; align-items: center; justify-content: center; gap: 8px; }
        
        html[dir="rtl"] .btn-edit { border-left: 1px solid var(--border-color); }
        html[dir="ltr"] .btn-edit { border-right: 1px solid var(--border-color); }
        .btn-edit { color: var(--accent-blue); } .btn-edit:hover { background-color: rgba(59, 130, 246, 0.08); }
        .btn-delete { color: #f43f5e; } .btn-delete:hover { background-color: rgba(244, 63, 94, 0.08); }
        
        .alert { padding: 18px; border-radius: 14px; margin-bottom: 30px; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background-color: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
    </style>
</head>
<body>

    <canvas id="worldBg"></canvas>

    <div class="sidebar">
        <div>
            <div class="logo-area"><i class="fa-solid fa-satellite-dish" style="color:#3b82f6;"></i> K<span>·NEWS</span></div>
            <ul class="menu-list">
                <li><a href="dashboard.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i> <span class="lang-text" data-ar="لوحة التحكم" data-en="Dashboard">لوحة التحكم</span></a></li>
                <li><a href="add-news.php" class="menu-item"><i class="fa-solid fa-square-plus"></i> <span class="lang-text" data-ar="إضافة خبر" data-en="Add News">إضافة خبر</span></a></li>
                <li><a href="manage-news.php" class="menu-item active"><i class="fa-solid fa-newspaper"></i> <span class="lang-text" data-ar="إدارة الأخبار" data-en="Manage News">إدارة الأخبار</span></a></li>
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
                <h1 class="lang-text" data-ar="إدارة وتحليلات تريند الأخبار" data-en="News Trend & Management Matrix">إدارة وتحليلات تريند الأخبار</h1>
                <p style="color: var(--text-muted); margin-top: 6px;" class="lang-text" data-ar="تتبع تفاعل القراء، فرز الأقسام، ومعرفة الأخبار الأكثر صعوداً وميكانيكية المشاهدات الحية" data-en="Track reader engagement, filter segments, and pinpoint trending articles organically">تتبع تفاعل القراء، فرز الأقسام، ومعرفة الأخبار الأكثر صعوداً وميكانيكية المشاهدات الحية</p>
            </div>
            <button class="btn-lang-toggle" onclick="toggleLanguage()">
                <i class="fa-solid fa-globe" style="color: var(--accent-blue);"></i>
                <span id="langBtnText">English</span>
            </button>
        </div>

        <div class="system-status-panel">
            <div class="status-card">
                <div class="status-circle-container">
                    <div class="rotating-ring ring-blue"></div>
                    <div class="core-orb orb-blue"><i class="fa-solid fa-globe"></i></div>
                </div>
                <div class="status-info">
                    <span class="lang-text" style="font-size:12px; color:var(--text-muted);" data-ar="إجمالي الزيارات والتحليلات للشبكة" data-en="Total Pipeline Hits">إجمالي الزيارات للشبكة</span>
                    <p><?php echo number_format($global_total_views); ?> مشاهدة / Hits</p>
                </div>
            </div>

            <div class="status-card">
                <div class="status-circle-container">
                    <div class="rotating-ring ring-green"></div>
                    <div class="core-orb orb-green"><i class="fa-solid fa-arrow-trend-up"></i></div>
                </div>
                <div class="status-info">
                    <span class="lang-text" style="font-size:12px; color:var(--text-muted);" data-ar="مؤشر خط التوازن (المتوسط العام)" data-en="Global Balance Line">خط توازن المشاهدات (المتوسط)</span>
                    <p>≈ <?php echo number_format($global_avg_views, 1); ?> لكل خبر</p>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i> 
                <span class="lang-text" data-ar="تم بنجاح بتر المقال وتحديث مصفوفة البيانات." data-en="Registry node wiped out successfully.">تم بنجاح بتر المقال وتحديث مصفوفة البيانات.</span>
            </div>
        <?php endif; ?>

        <div class="category-tabs-container">
            <button class="tab-btn active" onclick="switchCategoryTab('all')">
                <i class="fa-solid fa-border-all"></i>
                <span class="lang-text" data-ar="كل المقالات" data-en="All Clusters">كل المقالات</span>
                <div class="tab-badges-group">
                    <span class="badge-count"><?php echo count($all_news); ?></span>
                    <span class="badge-views"><i class="fa-regular fa-eye"></i> <?php echo number_format($global_total_views); ?></span>
                </div>
            </button>

            <?php foreach($categories as $cat): 
                $count_in_cat = isset($news_by_category[$cat['id']]) ? count($news_by_category[$cat['id']]) : 0;
                $views_in_cat = $category_total_views[$cat['id']] ?? 0;
            ?>
                <button class="tab-btn" onclick="switchCategoryTab('cat_<?php echo $cat['id']; ?>')">
                    <i class="fa-regular fa-folder-open"></i>
                    <span><?php echo htmlspecialchars($cat['name']); ?></span>
                    <div class="tab-badges-group">
                        <span class="badge-count"><?php echo $count_in_cat; ?></span>
                        <span class="badge-views" style="background:rgba(16, 185, 129, 0.08); color:#34d399; border-color:rgba(16,185,129,0.15);"><i class="fa-solid fa-chart-line"></i> <?php echo number_format($views_in_cat); ?></span>
                    </div>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="management-container">
            
            <div id="panel_all" class="category-content-panel active">
                <h3 style="font-size:18px; color:#fff; margin-bottom:15px;"><i class="fa-solid fa-network-wired"></i> <span class="lang-text" data-ar="المستودع العام الشامل" data-en="Global Master Pipeline">المستودع العام الشامل</span></h3>
                <?php if(empty($all_news)): ?>
                    <p style="color:var(--text-muted);" class="lang-text" data-ar="لا توجد أخبار في النظام حالياً." data-en="No logs recorded inside database.">لا توجد أخبار في النظام حالياً.</p>
                <?php else: ?>
                    <div class="news-manage-grid">
                        <?php foreach($all_news as $news) { renderNewsCardMarkup($news, $global_avg_views); } ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php foreach($categories as $cat): 
                $cat_panel_id = "cat_" . $cat['id'];
                $cat_news_list = $news_by_category[$cat['id']] ?? [];
            ?>
                <div id="panel_<?php echo $cat_panel_id; ?>" class="category-content-panel">
                    <h3 style="font-size:18px; color:var(--accent-blue); margin-bottom:15px;">
                        <i class="fa-solid fa-folder-closed"></i> 
                        <span class="lang-text" data-ar="قسم: " data-en="Segment: ">قسم: </span><?php echo htmlspecialchars($cat['name']); ?>
                    </h3>
                    
                    <?php if(empty($cat_news_list)): ?>
                        <p style="color:var(--text-muted); padding:20px 0;" class="lang-text" data-ar="هذا القسم فارغ تماماً حالياً." data-en="This specific segment data pool is vacant.">هذا القسم فارغ تماماً حالياً.</p>
                    <?php else: ?>
                        <div class="news-manage-grid">
                            <?php foreach($cat_news_list as $news) { renderNewsCardMarkup($news, $global_avg_views); } ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

    <script>
        function switchCategoryTab(targetId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.category-content-panel').forEach(panel => panel.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById('panel_' + targetId).classList.add('active');
        }

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

        window.addEventListener('DOMContentLoaded', () => {
            applyCurrentLanguageStrings();
            
            // محرك جزيئات الخلفية
            const canvas = document.getElementById('worldBg');
            const ctx = canvas.getContext('2d');
            let width = canvas.width = window.innerWidth;
            let height = canvas.height = window.innerHeight;
            
            let particles = [];
            for (let i = 0; i < 40; i++) {
                particles.push({
                    x: Math.random() * width, y: Math.random() * height,
                    radius: Math.random() * 2 + 1,
                    vx: Math.random() * 0.4 - 0.2, vy: Math.random() * 0.4 - 0.2
                });
            }
            function drawParticles() {
                ctx.clearRect(0, 0, width, height);
                ctx.fillStyle = "rgba(168, 85, 247, 0.25)";
                particles.forEach(p => {
                    p.x += p.vx; p.y += p.vy;
                    if (p.x < 0 || p.x > width) p.vx = -p.vx;
                    if (p.y < 0 || p.y > height) p.vy = -p.vy;
                    ctx.beginPath(); ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2); ctx.fill();
                });
                requestAnimationFrame(drawParticles);
            }
            drawParticles();
        });
    </script>
</body>
</html>

<?php
// وظيفة بناء كارت المقال الذكي مع معالجة معادلة الـ Trend (مقارنة المشاهدات بالمتوسط العام)
function renderNewsCardMarkup($news, $global_avg) {
    $views = intval($news['views'] ?? 0);
    
    // معادلة الذكاء التحليلي لتحديد اتجاه السهم والتفاعل
    if ($views > ($global_avg * 1.5)) {
        // إذا كانت المشاهدات أعلى من المتوسط بـ 50% فما فوق (تريند ناري صاعد)
        $trend_class = "ribbon-up";
        $trend_icon = "fa-solid fa-arrow-trend-up";
        $trend_text_ar = "رائج جداً";
        $trend_text_en = "Viral Trend";
        $view_color_class = "views-up";
    } elseif ($views >= ($global_avg * 0.6)) {
        // مشاهدات متزنة قريبة من المتوسط العام (تفاعل مستقر)
        $trend_class = "ribbon-mid";
        $trend_icon = "fa-solid fa-arrow-right";
        $trend_text_ar = "مستقر";
        $trend_text_en = "Stable Feed";
        $view_color_class = "views-mid";
    } else {
        // تفاعل منخفض أقل من خط التوازن العام
        $trend_class = "ribbon-down";
        $trend_icon = "fa-solid fa-arrow-trend-down";
        $trend_text_ar = "هادئ";
        $trend_text_en = "Cooling Down";
        $view_color_class = "views-down";
    }
    ?>
    <div class="news-card">
        <div class="trend-indicator-ribbon <?php echo $trend_class; ?>">
            <i class="<?php echo $trend_icon; ?>"></i>
            <span class="lang-text" data-ar="<?php echo $trend_text_ar; ?>" data-en="<?php echo $trend_text_en; ?>"><?php echo $trend_text_ar; ?></span>
        </div>

        <div>
            <img src="<?php echo !empty($news['image']) ? htmlspecialchars($news['image']) : 'https://placehold.co/600x400/0b1329/ffffff?text=K-NEWS'; ?>" class="news-card-img" alt="Cover Node">
            <div class="news-card-body">
                <div class="badge-wrapper">
                    <span class="news-card-tag"><?php echo htmlspecialchars($news['category_name'] ?? 'General'); ?></span>
                    <?php if(!empty($news['is_breaking'])): ?>
                        <span class="news-card-breaking"><i class="fa-solid fa-bolt"></i> عاجل</span>
                    <?php endif; ?>
                </div>
                
                <h4 class="news-card-title"><?php echo htmlspecialchars($news['title']); ?></h4>
                
                <div class="news-card-meta">
                    <span><i class="fa-regular fa-clock"></i> <?php echo date('Y-m-d', strtotime($news['created_at'])); ?></span>
                    <span class="meta-views-count <?php echo $view_color_class; ?>">
                        <i class="fa-regular fa-eye"></i> <?php echo number_format($views); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="news-card-actions">
            <a href="add-news.php?action=edit&id=<?php echo $news['id']; ?>" class="action-btn btn-edit">
                <i class="fa-solid fa-gears"></i> 
                <span class="lang-text" data-ar="تعديل" data-en="Patch">تعديل</span>
            </a>
            
            <form action="manage-news.php" method="POST" style="display:inline; flex:1;" onsubmit="return confirm('هل أنت متأكد من حذف هذا الخبر نهائياً؟');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $news['id']; ?>">
                <button type="submit" class="action-btn btn-delete" style="width:100%;">
                    <i class="fa-solid fa-trash-can"></i> 
                    <span class="lang-text" data-ar="حذف" data-en="Purge">حذف</span>
                </button>
            </form>
        </div>
    </div>
    <?php
}
?>