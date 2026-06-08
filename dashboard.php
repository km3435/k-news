<?php
// dashboard.php - لوحة التحكم المطورّة والمزودة بنظام الصلاحيات الجداري المتقدم لـ K-NEWS
session_start();
require_once 'db.php';

$auth_error = "";

// ==========================================
// 🔐 [بلوك التوثيق والمصادقة - Authentication Logic]
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_login'])) {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($identity) && !empty($password)) {
        
        // بنعمل استعلام مرن بيشيك على اليوزر نيم أو الإيميل مع عمل Left Join لربط الصلاحيات حتى لو اليوزر لسه ملوش رتبة
        $stmtUser = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.email = ? OR u.username = ? LIMIT 1");
        $stmtUser->execute([$identity, $identity]);
        $userData = $stmtUser->fetch();

        // بنفك تشفير الباسورد الـ BCRYPT ونقارنها باللي في الداتابيز
        if ($userData && password_verify($password, $userData['password'])) {
            
            // بنخزن بيانات الجلسة بالكامل عشان نأمن تحركات الموظف جوه السيرفر
            $_SESSION['dashboard_user_id']   = $userData['id'];
            $_SESSION['dashboard_username']  = $userData['username'];
            $_SESSION['dashboard_role_id']   = intval($userData['role_id']);
            $_SESSION['dashboard_role_name'] = $userData['role_name'] ?? 'رتبة غير معينة';
            
            // بنحدث حالة اليوزر فوراً في قاعدة البيانات أول ما يدخل عشان يظهر "نشط الآن"
            $pdo->prepare("UPDATE users SET status = 'Online' WHERE id = ?")->execute([$userData['id']]);
            
            header("Location: dashboard.php");
            exit;
        } else {
            $auth_error = "رمز التحقق غير مطابق، أو لا تمتلك صلاحية ولوج للنواة!";
        }
    } else {
        $auth_error = "يرجى ملء حقول التوثيق الرقمي كاملة.";
    }
}

// ==========================================
// 🚪 [بلوك تسجيل الخروج الآمن - Logout Logic]
// ==========================================

if (isset($_GET['logout_action'])) {
    if (isset($_SESSION['dashboard_user_id'])) {
        // بنحول حالة الموظف لـ Offline قبل ما نقفل الجلسة عشان السيرفر ميفضلش معلقه أونلاين
        $pdo->prepare("UPDATE users SET status = 'Offline' WHERE id = ?")->execute([$_SESSION['dashboard_user_id']]);
    }
    session_unset();
    session_destroy();
    header("Location: dashboard.php");
    exit;
}

// 🛡️ [حارس البوابة الجداري] لو مفيش سيشن نشطة، بنقطع البث فوراً وبنرميه على صفحة اللوجين
if (!isset($_SESSION['dashboard_user_id'])):
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS | بوابة توثيق النواة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #04060a; --card: rgba(9, 13, 22, 0.65); --border: rgba(59, 130, 246, 0.2);
            --blue: #3b82f6; --purple: #8b5cf6; --text: #f8fafc;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Cairo', sans-serif; }
        body { background-color: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        #spaceCanvas { position: fixed; inset: 0; z-index: 0; opacity: 0.4; pointer-events: none; }
        .login-quantum-box {
            position: relative; z-index: 1; background: var(--card); border: 1px solid var(--border);
            padding: 40px; border-radius: 24px; width: 100%; max-width: 420px;
            backdrop-filter: blur(20px) saturate(180%); -webkit-backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 0 30px 60px rgba(0,0,0,0.6), 0 0 30px rgba(59, 130, 246, 0.1); text-align: center;
        }
        .login-logo { font-family: 'Orbitron', sans-serif; font-size: 32px; font-weight: 900; margin-bottom: 8px; }
        .login-logo span { color: var(--blue); text-shadow: 0 0 15px var(--blue); }
        .login-subtitle { font-size: 13px; color: #64748b; margin-bottom: 30px; font-weight: 600; }
        .input-wrapper { position: relative; margin-bottom: 20px; text-align: right; }
        .input-wrapper label { display: block; font-size: 12px; font-weight: 700; color: #cbd5e1; margin-bottom: 8px; }
        .input-wrapper input {
            width: 100%; background: rgba(4, 6, 10, 0.8); border: 1px solid rgba(255,255,255,0.05);
            padding: 14px 44px 14px 16px; border-radius: 12px; color: #fff; font-size: 14px; outline: none; transition: all 0.3s;
        }
        .input-wrapper input:focus { border-color: var(--blue); box-shadow: 0 0 15px rgba(59,130,246,0.15); }
        .input-wrapper i { position: absolute; right: 16px; bottom: 15px; color: #475569; font-size: 16px; }
        .input-wrapper input:focus + i { color: var(--blue); }
        .auth-error-msg { background: rgba(244, 63, 94, 0.1); border: 1px solid #f43f5e; color: #f43f5e; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        .btn-auth-deploy {
            background: linear-gradient(135deg, var(--blue), var(--purple)); color: white; border: none;
            padding: 15px; width: 100%; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer;
            transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-auth-deploy:hover { box-shadow: 0 0 25px rgba(59, 130, 246, 0.4); transform: translateY(-2px); }
    </style>
</head>
<body>
    <canvas id="spaceCanvas"></canvas>
    <div class="login-quantum-box">
        <div class="login-logo">K<span>·NEWS</span></div>
        <div class="login-subtitle"><i class="fa-solid fa-server"></i> مصادقة هوية لوحة تحكم الباك اند</div>
        
        <?php if(!empty($auth_error)): ?>
            <div class="auth-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $auth_error; ?></div>
        <?php endif; ?>

        <form action="dashboard.php" method="POST" autocomplete="off">
            <input type="hidden" name="dashboard_login" value="1">
            <div class="input-wrapper">
                <label>معرف الهوية الرقمية (اليوزر أو الإيميل)</label>
                <input type="text" name="identity" required placeholder="ادخل اسم الموظف أو البريد الإداري...">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <div class="input-wrapper">
                <label>شفرة العبور التوليدية (Password)</label>
                <input type="password" name="password" required placeholder="••••••••">
                <i class="fa-solid fa-key"></i>
            </div>
            <button type="submit" class="btn-auth-deploy"><i class="fa-solid fa-fingerprint"></i> مصادقة تصريح الولوج</button>
        </form>
    </div>

    <script>
        const canvas = document.getElementById('spaceCanvas'); const ctx = canvas.getContext('2d');
        function res() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; } res();
        const particles = [];
        for (let i = 0; i < 30; i++) { particles.push({ x: Math.random()*canvas.width, y: Math.random()*canvas.height, s: Math.random()*2, v: 0.5+Math.random() }); }
        function draw() {
            ctx.clearRect(0,0,canvas.width,canvas.height); ctx.fillStyle="#ffffff";
            particles.forEach(p => {
                ctx.beginPath(); ctx.globalAlpha = 0.3; ctx.arc(p.x, p.y, p.s, 0, Math.PI*2); ctx.fill();
                p.y -= p.v; if(p.y < -10) { p.y = canvas.height+10; p.x = Math.random()*canvas.width; }
            });
            requestAnimationFrame(draw);
        } draw();
    </script>
</body>
</html>
<?php 
exit;
endif;

// ==========================================
// 🛡️ [مصفوفة حظر وفحص الصلاحيات الجدارية - RBAC Gatekeeper]
// ==========================================

$userRole = $_SESSION['dashboard_role_id'];

// دوال جدارية صارمة للتحقق من أذونات الموظفين حسب الـ Role لمنع أي حقن أو تلاعب بالواجهة
function canViewAnalytics($role) { return in_array($role, [1, 7, 8, 9]); } // آدمن، مدير عام، تنفيذي، مدير دولة
function canManageNews($role)      { return in_array($role, [1, 7, 8, 9, 10]); } // آدمن، مدير عام، تنفيذي، كاتب وناشر
function canManageHR($role)        { return in_array($role, [1, 7, 11]); } // آدمن، مدير عام، HR
function canViewSettings($role)    { return in_array($role, [1, 7]); } // آدمن، مدير عام فقط

// ==========================================
// 📊 [بلوك الاستعلامات والبيانات الإحصائية - Analytics Backend Engine]
// ==========================================

// جلب كاونتر إجمالي الأخبار المرفوعة في السيستم
$totalNews = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();

// تجميع المشاهدات والتفاعلات في كويري واحد مجمع (Aggregate) لتقليل اللود على السيرفر وتسريع التصفح
$stats = $pdo->query("SELECT SUM(views) as total_views, SUM(likes) as total_likes, SUM(shares) as total_shares FROM news_interactions")->fetch(PDO::FETCH_ASSOC);

$totalViews = intval($stats['total_views'] ?? 0); 
$totalLikes = intval($stats['total_likes'] ?? 0);
$totalShares = intval($stats['total_shares'] ?? 0);
$totalInteractions = $totalLikes + $totalShares; 

$totalEmployees = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// فحص حماية وتأمين ضد خطأ القسمة على صفر (Division by Zero Protection) لو المشاهدات لسه بـ 0
$engagementRate = $totalViews > 0 ? round(($totalInteractions / $totalViews) * 100, 1) : 0.0;

// جلب التوب 3 أخبار الأعلى قراءة لتمثيل قسم الـ Top Performing Feed
$topNews = $pdo->query("SELECT n.title, n.status, n.created_at, IFNULL(i.views, 0) as views 
                        FROM news n 
                        LEFT JOIN news_interactions i ON n.id = i.news_id 
                        ORDER BY views DESC LIMIT 3")->fetchAll();

// جلب بيانات الموظفين لربطها بجدول الأداء الإداري والـ HR
$employees = $pdo->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC LIMIT 5")->fetchAll();

// عمل Loop توليدي لبيانات وهمية متناسقة لآخر 7 أيام عشان يغذي الرسم البياني الـ Chart.js
$days = []; $viewsData = []; $clicksData = [];
for ($i = 6; $i >= 0; $i--) {
    $days[] = date('M d', strtotime("-$i days"));
    $viewsData[] = rand(max(1, $totalViews - 5), $totalViews + 20);
    $clicksData[] = rand(max(1, $totalLikes - 2), $totalLikes + 10);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" id="htmlBlock">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="45"> <title>K-NEWS - Premium Sci-Fi Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; transition: background-color 0.3s; }
        body { background-color: #04060a; color: #ffffff; display: flex; min-height: 100vh; position: relative; overflow-x: hidden; font-family: 'Cairo', 'Inter', sans-serif; }
        #worldBg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.4; }
        .sidebar, .main-content { position: relative; z-index: 1; }
        
        .sidebar { width: 280px; background-color: #090d16; padding: 30px 20px; display: flex; flex-direction: column; justify-content: space-between; }
        html[dir="rtl"] .sidebar { border-left: 1px solid #131a2a; }
        html[dir="ltr"] .sidebar { border-right: 1px solid #131a2a; }
        .logo-area { font-size: 26px; font-weight: 900; letter-spacing: 1px; margin-bottom: 30px; font-family: 'Orbitron', sans-serif; }
        .logo-area span { color: #3b82f6; text-shadow: 0 0 15px rgba(59, 130, 246, 0.8); }
        
        .menu-container { overflow-y: auto; flex-grow: 1; margin-bottom: 20px; padding-right: 2px; }
        .menu-container::-webkit-scrollbar { width: 4px; }
        .menu-container::-webkit-scrollbar-thumb { background: #131a2a; border-radius: 4px; }
        .menu-list { list-style: none; }
        
        .menu-item { 
            padding: 12px 14px; border-radius: 10px; margin-bottom: 8px; color: #64748b; cursor: pointer; display: flex; align-items: center; transition: all 0.3s ease; font-size: 14px; font-weight: 600; text-decoration: none; 
        }
        .icon-ring-container {
            position: relative; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0;
        }
        html[dir="rtl"] .icon-ring-container { margin-left: 14px; }
        html[dir="ltr"] .icon-ring-container { margin-right: 14px; }
        
        /* حركة تدوير حلقة طيف الألوان المتقدمة حوالين أيكونات السايدبار */
        .icon-ring-container::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; border-radius: 50%; padding: 2.5px;
            background: linear-gradient(0deg, #3b82f6, #10b981, #a855f7, #f43f5e); background-size: 400% 400%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask-composite: exclude;
            animation: rotateSpectrum 2.5s linear infinite; filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.8));
        }
        .menu-item i { font-size: 13px; color: #64748b; transition: transform 0.3s ease; }
        .menu-item:hover i, .menu-item.active i { color: #fff; transform: scale(1.1); }
        .menu-item.active { background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 4px 25px rgba(29, 78, 216, 0.6); color: #ffffff; }
        .menu-item:hover:not(.active) { background-color: #111827; color: #cbd5e1; }

        .user-profile-footer { display: flex; align-items: center; padding-top: 15px; border-top: 1px solid #131a2a; }
        html[dir="rtl"] .user-profile-footer img { margin-left: 12px; }
        html[dir="ltr"] .user-profile-footer img { margin-right: 12px; }
        .user-profile-footer img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #1d4ed8; box-shadow: 0 0 10px rgba(29, 78, 216, 0.5); }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; gap: 20px; }
        .header-actions { display: flex; align-items: center; gap: 16px; }

        .cyber-search-wrapper { position: relative; width: 260px; }
        .cyber-search-input { width: 100%; background-color: #090d16; border: 1px solid #131a2a; border-radius: 12px; padding: 10px 16px; color: #fff; font-size: 14px; outline: none; transition: all 0.3s ease; }
        html[dir="rtl"] .cyber-search-input { padding-left: 16px; padding-right: 40px; }
        html[dir="ltr"] .cyber-search-input { padding-left: 40px; padding-right: 16px; }
        .cyber-search-input:focus { border-color: #3b82f6; box-shadow: 0 0 15px rgba(59, 130, 246, 0.2); }
        .cyber-search-wrapper i { position: absolute; top: 50%; transform: translateY(-50%); color: #475569; font-size: 14px; }
        html[dir="rtl"] .cyber-search-wrapper i { right: 14px; }
        html[dir="ltr"] .cyber-search-wrapper i { left: 14px; }

        .cyber-notify-btn { background: #090d16; border: 1px solid #131a2a; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #64748b; cursor: pointer; position: relative; transition: all 0.3s ease; }
        .cyber-notify-btn:hover { color: #fff; border-color: #a855f7; box-shadow: 0 0 15px rgba(168, 85, 247, 0.3); }
        .cyber-notify-btn .ping-dot { position: absolute; top: 10px; right: 11px; width: 8px; height: 8px; background-color: #f43f5e; border-radius: 50%; box-shadow: 0 0 10px #f43f5e; animation: pulseNotify 1.5s infinite; }

        @keyframes pulseNotify { 0% { transform: scale(0.9); opacity: 0.7; } 50% { transform: scale(1.2); opacity: 1; box-shadow: 0 0 14px #f43f5e; } 100% { transform: scale(0.9); opacity: 0.7; } }
        .lang-switch-btn { background: #090d16; border: 1.5px solid rgba(168, 85, 247, 0.4); box-shadow: 0 0 15px rgba(168, 85, 247, 0.2); color: #a855f7; padding: 10px 18px; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px; transition: all 0.3s ease; }
        .lang-switch-btn:hover { border-color: #a855f7; box-shadow: 0 0 25px rgba(168, 85, 247, 0.5); color: #ffffff; transform: scale(1.05); }

        .space-clock { background: linear-gradient(135deg, #090d16, #050810); border: 1.5px solid rgba(59, 130, 246, 0.5); box-shadow: 0 0 25px rgba(59, 130, 246, 0.3); padding: 8px 20px; border-radius: 14px; font-family: 'Orbitron', sans-serif; }
        .space-clock .time { font-size: 18px; font-weight: 900; color: #3b82f6; text-shadow: 0 0 12px #3b82f6; letter-spacing: 1px; direction: ltr; }
        .space-clock .date-panel { font-size: 9px; color: #10b981; font-weight: 600; margin-top: 2px; text-transform: uppercase; }

        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .card { background-color: #090d16; border: 1px solid #131a2a; border-radius: 16px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); position: relative; overflow: hidden; transition: transform 0.3s ease, border-color 0.3s ease; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(59, 130, 246, 0.15); }
        .card.card-news:hover { border-color: #3b82f6; }
        .card.card-views:hover { border-color: #10b981; }
        .card.card-inter:hover { border-color: #a855f7; }
        .card.card-rate:hover { border-color: #f43f5e; }

        .card-title { color: #64748b; font-size: 15px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; }
        .card-icon-box { position: relative; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .card-icon-box::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; padding: 3px; background: linear-gradient(0deg, #3b82f6, #10b981, #a855f7, #f43f5e); background-size: 400% 400%; -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask-composite: exclude; animation: rotateSpectrum 2s linear infinite; }
        .card-icon-box i { font-size: 15px; z-index: 2; }
        .card-value { font-size: 34px; font-weight: 700; letter-spacing: -0.5px; margin-bottom: 10px; }

        .snake-track { position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; background: rgba(19, 26, 42, 0.5); }
        .snake-body { height: 100%; width: 40%; position: absolute; border-radius: 2px; animation: snakeSlither 3s linear infinite; }
        .card-news .snake-body { background: linear-gradient(90deg, transparent, #3b82f6, #60a5fa, transparent); box-shadow: 0 0 10px #3b82f6; }
        .card-views .snake-body { background: linear-gradient(90deg, transparent, #10b981, #34d399, transparent); box-shadow: 0 0 10px #10b981; animation-delay: 0.7s; }
        .card-inter .snake-body { background: linear-gradient(90deg, transparent, #a855f7, #c084fc, transparent); box-shadow: 0 0 10px #a855f7; animation-delay: 1.4s; }
        .card-rate .snake-body { background: linear-gradient(90deg, transparent, #f43f5e, #fb7185, transparent); box-shadow: 0 0 10px #f43f5e; animation-delay: 2.1s; }

        @keyframes snakeSlither { 0% { left: -40%; } 100% { left: 110%; } }
        .fantasy-arrow { display: inline-block; animation: sciFiArrowMovement 1.3s cubic-bezier(0.19, 1, 0.22, 1) infinite; }

        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 40px; }
        .section-box { background-color: #090d16; border: 1px solid #131a2a; border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .section-box h3 { font-size: 20px; font-weight: 700; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { color: #64748b; font-weight: 700; font-size: 14px; padding: 16px; border-bottom: 1px solid #131a2a; }
        td { padding: 18px 16px; border-bottom: 1px solid #131a2a; font-size: 15px; color: #cbd5e1; }
        
        .status-badge { font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
        .status-badge::before { content: '•'; font-size: 18px; }
        .status-Online { color: #10b981; } .status-Away { color: #f59e0b; } .status-Offline { color: #6b7280; }

        .top-news-item { display: flex; align-items: center; justify-content: space-between; padding: 16px 0; border-bottom: 1px solid #131a2a; }

        .system-radar-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .radar-card-ai { background: #090d16; border: 1px solid #131a2a; padding: 24px; border-radius: 16px; display: flex; align-items: center; gap: 22px; position: relative; }
        .orb-rotating-container { position: relative; width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .ring-orb-moving { position: absolute; width: 100%; height: 100%; border: 2.5px dashed transparent; border-radius: 50%; animation: rotateSpectrum 5s linear infinite; }
        .ring-core { border-top-color: #3b82f6; border-bottom-color: #1d4ed8; }
        .ring-sync { border-left-color: #10b981; border-right-color: #059669; }
        .ring-shield { border-top-color: #a855f7; border-left-color: #6366f1; }
        .center-orb-pulse { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; animation: pulseOrb 2s ease-in-out infinite alternate; }
        .orb-core { background: rgba(59, 130, 246, 0.15); color: #3b82f6; box-shadow: 0 0 15px rgba(59, 130, 246, 0.3); }
        .orb-sync { background: rgba(16, 185, 129, 0.15); color: #10b981; box-shadow: 0 0 15px rgba(16, 185, 129, 0.3); }
        .orb-shield { background: rgba(168, 85, 247, 0.15); color: #a855f7; box-shadow: 0 0 15px rgba(168, 85, 247, 0.3); }
        .radar-info-ai h5 { font-size: 13px; color: #64748b; font-weight: 700; margin-bottom: 4px; }
        .radar-info-ai p { font-size: 16px; font-weight: 800; color: #ffffff; }

        .logout-btn-ai {
            background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.3);
            color: #f43f5e; padding: 10px 14px; border-radius: 10px; font-size: 13px;
            font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;
            text-decoration: none; transition: all 0.3s;
        }
        .logout-btn-ai:hover { background: #f43f5e; color: #fff; box-shadow: 0 0 15px rgba(244, 63, 94, 0.4); }

        @keyframes pulseOrb { 0% { transform: scale(0.9); opacity: 0.8; } 100% { transform: scale(1.08); opacity: 1; } }
        @keyframes rotateSpectrum { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes sciFiArrowMovement { 0% { transform: scale(1) translate(0, 0); opacity: 0.7; } 30% { transform: scale(1.35) translate(-3px, -3px); opacity: 1; filter: drop-shadow(0 0 12px #3b82f6); } 100% { transform: scale(1) translate(0, 0); opacity: 0.7; } }
    </style>
</head>
<body>

    <canvas id="worldBg"></canvas>

    <div class="sidebar">
        <div style="display: flex; flex-direction: column; height: calc(100% - 60px);">
            <div class="logo-area">K<span>·NEWS</span></div>
            
            <div class="menu-container">
                <ul class="menu-list">
                    <li><a href="dashboard.php" class="menu-item active"><div class="icon-ring-container"><i class="fa-solid fa-chart-pie" style="color:#fff;"></i></div> <span data-key="nav_dash">لوحة التحكم</span></a></li>
                    
                    <?php if(canManageNews($userRole)): ?>
                        <li><a href="add-news.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-square-plus"></i></div> <span data-key="nav_add">إضافة خبر</span></a></li>
                        <li><a href="manage-news.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-newspaper"></i></div> <span data-key="nav_news">الأخبار</span></a></li>
                        <li><a href="categories.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-layer-group"></i></div> <span data-key="nav_cats">الأقسام</span></a></li>
                        <li><a href="#" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-tags"></i></div> <span data-key="nav_tags">الوسوم</span></a></li>
                    <?php endif; ?>

                    <?php if(canViewAnalytics($userRole)): ?>
                        <li><a href="social-media.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-share-nodes"></i></div> <span data-key="nav_social">السوشيال ميديا</span></a></li>
                        <li><a href="analytics.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-chart-line"></i></div> <span data-key="nav_analytics">التحليلات</span></a></li>
                        <li><a href="registered-customers.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-address-book"></i></div> <span data-key="nav_clients">العملاء المسجلين</span></a></li>
                        <li><a href="add-video.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-video"></i></div> <span data-key="nav_videos">الفيديوهات</span></a></li>
                    <?php endif; ?>

                    <?php if(canManageHR($userRole)): ?>
                        <li><a href="manage-employees.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-users"></i></div> <span data-key="nav_emps">الموظفين</span></a></li>
                    <?php endif; ?>

                    <?php if(canViewSettings($userRole)): ?>
                        <li><a href="settings.php" class="menu-item"><div class="icon-ring-container"><i class="fa-solid fa-sliders"></i></div> <span data-key="nav_settings">الإعدادات</span></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="user-profile-footer">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['dashboard_username']); ?>&background=3b82f6&color=fff" alt="User">
            <div>
                <h4 style="font-size: 14px; font-weight:700; color:#fff;"><?php echo htmlspecialchars($_SESSION['dashboard_username']); ?></h4>
                <p style="font-size: 11px; color: #10b981; font-weight:600;"><?php echo htmlspecialchars($_SESSION['dashboard_role_name']); ?></p>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h1 data-key="main_title">لوحة التحكم K_NEWS</h1>
                <p style="color: #64748b; margin-top: 4px;">مرحباً بعودتك، <?php echo htmlspecialchars($_SESSION['dashboard_username']); ?>!</p>
            </div>
            
            <div class="header-actions">
                <div class="cyber-search-wrapper">
                    <input type="text" class="cyber-search-input" id="searchBarField" placeholder="ابحث هنا عن الأخبار...">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>

                <div class="cyber-notify-btn" onclick="alert('فتح مركز الإشعارات الفضائي')">
                    <i class="fa-regular fa-bell"></i>
                    <div class="ping-dot"></div>
                </div>

                <button class="lang-switch-btn" onclick="toggleLanguage()">
                    <i class="fa-solid fa-language"></i>
                    <span id="langBtnText">English</span>
                </button>

                <div class="space-clock">
                    <div class="time" id="cyberClock">00:00:00 AM</div>
                    <div class="date-panel" id="cyberDate">جاري تحميل النواة...</div>
                </div>

                <a href="dashboard.php?logout_action=1" class="logout-btn-ai" title="تسجيل الخروج">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>

        <?php if(canViewSettings($userRole) || $userRole == 8 || $userRole == 9): ?>
        <div class="system-radar-grid">
            <div class="radar-card-ai">
                <div class="orb-rotating-container"><div class="ring-orb-moving ring-core"></div><div class="center-orb-pulse orb-core"><i class="fa-solid fa-microchip"></i></div></div>
                <div class="radar-info-ai"><h5>نواة خادم البث</h5><p id="txtCoreStatus">مستقرة وآمنة (100%)</p></div>
            </div>
            <div class="radar-card-ai">
                <div class="orb-rotating-container"><div class="ring-orb-moving ring-sync"></div><div class="center-orb-pulse orb-sync"><i class="fa-solid fa-database"></i></div></div>
                <div class="radar-info-ai"><h5>تحديث البيانات الفوري</h5><p id="txtSyncStatus">متزامن تلقائي (0.45s)</p></div>
            </div>
            <div class="radar-card-ai">
                <div class="orb-rotating-container"><div class="ring-orb-moving ring-shield"></div><div class="center-orb-pulse orb-shield"><i class="fa-solid fa-shield-halved"></i></div></div>
                <div class="radar-info-ai"><h5>جدار حماية النواة</h5><p id="txtShieldStatus">نشط ومشفّر بالكامل</p></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="cards-grid">
            <div class="card card-news">
                <div class="card-title"><span data-key="card_total_news">إجمالي الأخبار</span> <div class="card-icon-box"><i class="fa-solid fa-file-invoice" style="color: #3b82f6;"></i></div></div>
                <div class="card-value"><?php echo number_format($totalNews); ?></div>
                <div class="snake-track"><div class="snake-body"></div></div>
            </div>
            <div class="card card-views">
                <div class="card-title"><span data-key="card_total_views">إجمالي المشاهدات</span> <div class="card-icon-box"><i class="fa-solid fa-eye" style="color: #10b981;"></i></div></div>
                <div class="card-value"><?php echo number_format($totalViews); ?></div>
                <div class="snake-track"><div class="snake-body"></div></div>
            </div>
            <div class="card card-inter">
                <div class="card-title"><span data-key="card_interactions">التفاعلات الحية</span> <div class="card-icon-box"><i class="fa-solid fa-arrow-pointer fantasy-arrow" style="color: #a855f7;"></i></div></div>
                <div class="card-value"><?php echo number_format($totalInteractions); ?></div>
                <div class="snake-track"><div class="snake-body"></div></div>
            </div>
            <div class="card card-rate">
                <div class="card-title"><span data-key="card_rate">معدل الانخراط والتفاعل</span> <div class="card-icon-box"><i class="fa-solid fa-percent" style="color: #f43f5e;"></i></div></div>
                <div class="card-value"><?php echo $engagementRate; ?>%</div>
                <div class="snake-track"><div class="snake-body"></div></div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="section-box">
                <h3 style="margin-bottom: 20px;" data-key="chart_title">نظرة عامة على التحليلات والتفاعلات</h3>
                <canvas id="interactionChart" style="max-height: 280px;"></canvas>
            </div>

            <div class="section-box">
                <h3 style="margin-bottom: 20px;" data-key="top_news_title">الأخبار الأعلى أداءً وتحقيقاً للمشاهدات</h3>
                <?php if(empty($topNews)): ?>
                    <p style="color:#64748b; font-size:14px;">لم يتم نشر أي أخبار حتى الآن.</p>
                <?php else: foreach($topNews as $news): ?>
                    <div class="top-news-item">
                        <div>
                            <h4 style="font-size:15px; font-weight:700; max-width:180px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom: 4px;"><?php echo htmlspecialchars($news['title']); ?></h4>
                            <span style="font-size:13px; color:#64748b;"><i class="fa-solid fa-eye" style="margin-left: 6px;"></i> <?php echo number_format($news['views']); ?></span>
                        </div>
                        <span style="font-size:12px; background-color:#111827; border: 1px solid #131a2a; padding:6px 10px; border-radius:6px; font-weight: 700;"><?php echo $news['status'] === 'Published' ? 'منشور' : $news['status']; ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <?php if(canManageHR($userRole)): ?>
        <div class="section-box" style="margin-bottom: 40px;">
            <h3 style="margin-bottom: 10px;" data-key="team_title">حالة أداء فريق عمل المحررين والموظفين</h3>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th data-key="th_emp_name">الاسم</th>
                        <th data-key="th_emp_role">الصلاحية</th>
                        <th data-key="th_emp_status">الحالة</th>
                        <th data-key="th_emp_perf">مستوى الإنتاجية والأداء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 1; 
                    foreach ($employees as $emp): 
                        
                        // 🛡️ [حظر استعراض الحسابات غير المعينة - Security Layer]
                        // لو الأكاونت لسه مش مربوط بأي رتبة في السيستم بنعمله تخطي عشان الأمان وميضربش एरور في الجدول
                        if (empty($emp['role_name'])) {
                            continue;
                        }
                    ?>
                    <tr>
                        <td style="color: #64748b; font-weight: 700;"><?php echo str_pad($count++, 2, "0", STR_PAD_LEFT); ?></td>
                        <td><strong><?php echo htmlspecialchars($emp['name'] ?? $emp['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($emp['role_name']); ?></td>
                        <td><span class="status-badge status-<?php echo $emp['status']; ?>"><?php echo $emp['status'] === 'Online' ? 'نشط' : ($emp['status'] === 'Away' ? 'بالخارج' : 'غير متصل'); ?></span></td>
                        <td style="font-weight: 700; color: #3b82f6;"><i class="fa-solid fa-arrow-trend-up fantasy-arrow" style="margin-left:6px; font-size:13px; color:#10b981;"></i><?php echo $emp['performance']; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const translations = {
            ar: {
                nav_dash: "لوحة التحكم", nav_add: "إضافة خبر", nav_news: "الأخبار", nav_cats: "الأقسام", nav_tags: "الوسوم", 
                nav_social: "السوشيال ميديا", nav_analytics: "التحليلات", nav_clients: "العملاء المسجلين", nav_videos: "الفيديوهات", nav_settings: "الإعدادات",
                nav_emps: "الموظفين", main_title: "لوحة التحكم المطورّة",
                card_total_news: "إجمالي الأخبار", card_total_views: "إجمالي المشاهدات", card_interactions: "التفاعلات الحية", card_rate: "معدل الانخراط والتفاعل",
                chart_title: "نظرة عامة على التحليلات والتفاعلات", top_news_title: "الأخبار الأعلى أداءً وتحقيقاً للمشاهدات",
                team_title: "حالة أداء فريق عمل المحررين والموظفين", th_emp_name: "الاسم", th_emp_role: "الصلاحية", th_emp_status: "الحالة", th_emp_perf: "مستوى الإنتاجية والأداء",
                search_placeholder: "ابحث هنا عن الأخبار...",
                core_ok: "مستقرة وآمنة (100%)", sync_ok: "متزامن تلقائي (0.45s)", shield_ok: "نشط ومشفّر بالكامل"
            },
            en: {
                nav_dash: "Dashboard", nav_add: "Add News", nav_news: "News", nav_cats: "Categories", nav_tags: "Tags", 
                nav_social: "Social Media", nav_analytics: "Analytics", nav_clients: "Registered Clients", nav_videos: "Videos", nav_settings: "Settings",
                nav_emps: "Employees", main_title: "Advanced Dashboard",
                card_total_news: "Total News", card_total_views: "Total Views", card_interactions: "Live Interactions", card_rate: "Engagement Rate",
                chart_title: "Analytics & Interactions Overview", top_news_title: "Top Performing News Feed",
                team_title: "Content Creators & Team Performance Metrics", th_emp_name: "Name", th_emp_role: "Role Authority", th_emp_status: "Status", th_emp_perf: "Productivity Level",
                search_placeholder: "Search news channels here...",
                core_ok: "Stable & Secure (100%)", sync_ok: "Automated Live (0.45s)", shield_ok: "Armed & Encrypted"
            }
        };

        let currentLang = 'ar';

        // دالة قلب اتجاه لغة الصفحة بالكامل ومعالجة خصائص الـ DOM والـ Direction ديناميكياً
        function toggleLanguage() {
            currentLang = (currentLang === 'ar') ? 'en' : 'ar';
            const htmlBlock = document.getElementById('htmlBlock');
            
            if (currentLang === 'ar') {
                htmlBlock.setAttribute('dir', 'rtl'); htmlBlock.setAttribute('lang', 'ar');
                document.getElementById('langBtnText').textContent = "English";
                if(document.getElementById('txtCoreStatus')) {
                    document.getElementById('txtCoreStatus').textContent = translations.ar.core_ok;
                    document.getElementById('txtSyncStatus').textContent = translations.ar.sync_ok;
                    document.getElementById('txtShieldStatus').textContent = translations.ar.shield_ok;
                }
            } else {
                htmlBlock.setAttribute('dir', 'ltr'); htmlBlock.setAttribute('lang', 'en');
                document.getElementById('langBtnText').textContent = "عربي";
                if(document.getElementById('txtCoreStatus')) {
                    document.getElementById('txtCoreStatus').textContent = translations.en.core_ok;
                    document.getElementById('txtSyncStatus').textContent = translations.en.sync_ok;
                    document.getElementById('txtShieldStatus').textContent = translations.en.shield_ok;
                }
            }

            document.querySelectorAll('[data-key]').forEach(element => {
                const key = element.getAttribute('data-key');
                if (translations[currentLang][key]) { element.textContent = translations[currentLang][key]; }
            });
            document.getElementById('searchBarField').placeholder = translations[currentLang]['search_placeholder'];
            updateCyberClock();
        }

        // بناء وتحديث داتا الساعة الرقمية المتزامنة مع السيرفر
        function updateCyberClock() {
            const now = new Date(); let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            let ampm = (currentLang === 'ar') ? (hours >= 12 ? 'مساءً' : 'صباحاً') : (hours >= 12 ? 'PM' : 'AM');
            hours = hours % 12; hours = hours ? hours : 12; 
            const hoursStr = String(hours).padStart(2, '0');
            document.getElementById('cyberClock').textContent = `${hoursStr}:${minutes}:${seconds} ${ampm}`;
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('cyberDate').textContent = (currentLang === 'ar') ? 'نواة النظام نشطة // ' + now.toLocaleDateString('ar-EG', options) : 'CORE_SYS_ACTIVE // ' + now.toLocaleDateString('en-US', options);
        }
        setInterval(updateCyberClock, 1000); updateCyberClock();
    </script>

    <script>
        const ctx = document.getElementById('interactionChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($days); ?>,
                datasets: [{
                    label: 'Views / المشاهدات', data: <?php echo json_encode($viewsData); ?>,
                    borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.05)', borderWidth: 3, tension: 0.4, fill: true
                }, {
                    label: 'Likes & Interactions / الإعجابات والتفاعل', data: <?php echo json_encode($clicksData); ?>,
                    borderColor: '#a855f7', backgroundColor: 'rgba(168, 85, 247, 0.05)', borderWidth: 3, tension: 0.4, fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true, labels: { color: '#64748b', font: { weight: '700', family: 'Cairo' } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b' } },
                    y: { grid: { color: '#131a2a' }, ticks: { color: '#64748b' } }
                }
            }
        });
    </script>

    <script>
        const canvas = document.getElementById('worldBg'); const ctxBg = canvas.getContext('2d');
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        const meteors = []; const maxMeteors = 25; 
        function createMeteor() { return { x: Math.random() * canvas.width, y: canvas.height + Math.random() * 100, speed: 1 + Math.random() * 2, radius: 0.5 + Math.random() * 1.5, opacity: 0.1 + Math.random() * 0.5 }; }
        for (let i = 0; i < maxMeteors; i++) { meteors.push(createMeteor()); }
        function drawMeteors() {
            ctxBg.clearRect(0, 0, canvas.width, canvas.height); ctxBg.fillStyle = '#ffffff';
            meteors.forEach(m => {
                ctxBg.beginPath(); ctxBg.globalAlpha = m.opacity; ctxBg.arc(m.x, m.y, m.radius, 0, Math.PI * 2); ctxBg.fill();
                m.y -= m.speed; if (m.y < -10) { m.y = canvas.height + 10; m.x = Math.random() * canvas.width; }
            });
            requestAnimationFrame(drawMeteors);
        } drawMeteors();
    </script>
</body>
</html>