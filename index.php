<?php
// index.php - المنصة التفاعلية الحية لـ K-NEWS المربوطة ديناميكياً بالكامل بقاعدة البيانات والتفاعلات والحماية
session_start();

$host = '127.0.0.1';
$db   = 'k_news_db';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // 🔐 معالجة طلب تسجيل الدخول الآمن عبر الخلفية
     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_action']) && $_POST['auth_action'] === 'login') {
         header('Content-Type: application/json');
         $identity = trim($_POST['identity'] ?? '');
         $password = $_POST['password'] ?? '';

         if (!empty($identity) && !empty($password)) {
             $stmtUser = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
             $stmtUser->execute([$identity, $identity]);
             $userData = $stmtUser->fetch();

             if ($userData && password_verify($password, $userData['password'])) {
                 $_SESSION['user_id'] = $userData['id'];
                 $_SESSION['username'] = $userData['username'];
                 
                 $pdo->prepare("UPDATE users SET status = 'Online' WHERE id = ?")->execute([$userData['id']]);
                 
                 echo json_encode(['success' => true, 'username' => $userData['username']]);
                 exit;
             }
         }
         echo json_encode(['success' => false, 'message' => 'بيانات الاعتماد المدخلة غير متطابقة!']);
         exit;
     }

     // 📝 [مطور حديثاً] آلية معالجة طلب إنشاء حساب جديد وضخه في الداتابيز
     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auth_action']) && $_POST['auth_action'] === 'signup') {
         header('Content-Type: application/json');
         $username = trim($_POST['username'] ?? '');
         $email    = trim($_POST['email'] ?? '');
         $password = $_POST['password'] ?? '';

         if (!empty($username) && !empty($email) && !empty($password)) {
             // فحص ما إذا كان المستخدم أو البريد الإلكتروني مسجلاً مسبقاً
             $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
             $stmtCheck->execute([$username, $email]);
             
             if ($stmtCheck->fetchColumn() > 0) {
                 echo json_encode(['success' => false, 'message' => 'اسم المستخدم أو البريد الإلكتروني مسجل مسبقاً بالنظام!']);
                 exit;
             } else {
                 // تشفير كلمة المرور بشكل آمن للغاية
                 $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                 $stmtInsert = $pdo->prepare("INSERT INTO users (username, email, password, role_id, status) VALUES (?, ?, ?, 2, 'Online')");
                 
                 if ($stmtInsert->execute([$username, $email, $hashedPassword])) {
                     $newUserId = $pdo->lastInsertId();
                     $_SESSION['user_id'] = $newUserId;
                     $_SESSION['username'] = $username;
                     echo json_encode(['success' => true, 'username' => $username]);
                     exit;
                 }
             }
         }
         echo json_encode(['success' => false, 'message' => 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح.']);
         exit;
     }

     // 📊 معالجة طلبات التفاعلات الحية عبر AJAX القادمة من المتصفح
     if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['interact_action'])) {
         header('Content-Type: application/json');
         $news_id = intval($_POST['news_id'] ?? 0);
         $action = $_POST['interact_action']; // 'like', 'view', 'share'
         
         if ($news_id > 0 && in_array($action, ['like', 'view', 'share'])) {
             $check = $pdo->prepare("SELECT COUNT(*) FROM news_interactions WHERE news_id = ?");
             $check->execute([$news_id]);
             if ($check->fetchColumn() == 0) {
                 $pdo->prepare("INSERT INTO news_interactions (news_id, likes, views, shares) VALUES (?, 0, 0, 0)")->execute([$news_id]);
             }
             
             $column = ($action === 'like') ? 'likes' : (($action === 'share') ? 'shares' : 'views');
             $update = $pdo->prepare("UPDATE news_interactions SET $column = $column + 1 WHERE news_id = ?");
             $update->execute([$news_id]);
             
             $get = $pdo->prepare("SELECT likes, views, shares FROM news_interactions WHERE news_id = ?");
             $get->execute([$news_id]);
             echo json_encode($get->fetch());
             exit;
         }
         echo json_encode(['error' => 'Invalid parameters']);
         exit;
     }

     $stmt_cats = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
     $db_categories = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);

     $stmt_slider = $pdo->query("SELECT * FROM hero_sliders WHERE is_active = 1 ORDER BY id DESC");
     $db_sliders = $stmt_slider->fetchAll(PDO::FETCH_ASSOC);

     // 🎬 [مطور حديثاً - مجلوب من قاعدة البيانات]: استعلام استخراج مرئيات رادار الفيديو النشط
     $stmt_videos = $pdo->query("SELECT * FROM videos ORDER BY id DESC");
     $db_videos = $stmt_videos->fetchAll(PDO::FETCH_ASSOC);

     $sql_news = "SELECT n.*, c.slug AS categorySlug, c.name AS category_name,
                  IFNULL(i.likes, 0) AS likes, IFNULL(i.views, 0) AS views, IFNULL(i.shares, 0) AS shares
                  FROM news n 
                  LEFT JOIN categories c ON n.category_id = c.id 
                  LEFT JOIN news_interactions i ON n.id = i.news_id
                  WHERE n.status = 'Published' 
                  ORDER BY n.id DESC";
     $stmt_news = $pdo->query($sql_news);
     $db_news = $stmt_news->fetchAll(PDO::FETCH_ASSOC);

     foreach ($db_news as &$item) {
          $item['timeText'] = ($item['is_breaking'] == 1) ? 'مباشر الآن' : 'تم النشر مؤخراً';
          if (empty($item['categorySlug'])) {
              $item['categorySlug'] = 'all';
          }
     }
} catch (\PDOException $e) {
     $db_categories = []; $db_sliders = []; $db_news = []; $db_videos = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" id="mainHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS | المنصة التفاعلية الرائدة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
   
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --bg-site: #05070f;
            --accent-blue: #3b82f6;
            --accent-purple: #8b5cf6;
            --accent-glow: rgba(59, 130, 246, 0.25);
            --text-dark: #f8fafc;
            --text-muted: #64748b;
            --radius-card: 20px;
            --border-color: rgba(255, 255, 255, 0.05);
            --card-bg: rgba(10, 15, 30, 0.45);
            --glass-blur: blur(20px) saturate(180%);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html[lang="ar"] * { font-family: 'Cairo', sans-serif; }
        html[lang="en"] * { font-family: 'Inter', sans-serif; }
        
        body { background-color: var(--bg-site); color: var(--text-dark); overflow-x: hidden; line-height: 1.5; position: relative; cursor: none; }
        a, button, input, select, .grid-card, .nav-icon-btn, .user-login-btn, .dot, .footer-links a, .action-trigger-btn { cursor: none !important; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg-site); }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-blue); }

        body::before, body::after {
            content: ''; position: fixed; width: 500px; height: 500px; border-radius: 50%; z-index: -2; opacity: 0.12; filter: blur(120px);
            animation: auroraMove 25s infinite alternate ease-in-out;
        }
        body::before { background: var(--accent-blue); top: -10%; right: -10%; }
        body::after { background: var(--accent-purple); bottom: -10%; left: -10%; animation-delay: -10s; }

        @keyframes auroraMove {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(80px, 50px) scale(1.1); }
            100% { transform: translate(-30px, 100px) scale(0.9); }
        }
        .fa, .fas, .fa-solid, .fa-brands, .fa-regular {
            font-family: "Font Awesome 6 Free" !important;
            display: inline-block;
            font-style: normal;
            font-variant: normal;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
        }
        #particleCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; pointer-events: none; }
        #progressBar { position: fixed; top: 0; right: 0; width: 0%; height: 3px; background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple)); z-index: 1002; transition: width 0.1s ease; box-shadow: 0 0 8px var(--accent-blue); }

        #scrollTopBtn {
            position: fixed; bottom: 24px; right: 24px; width: 45px; height: 45px;
            background: rgba(10, 15, 30, 0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 999;
        }
        #scrollTopBtn.show { opacity: 1; visibility: visible; }
        #scrollTopBtn:hover { background: var(--accent-blue); box-shadow: 0 0 15px var(--accent-glow); transform: translateY(-3px); }

        .custom-cursor-dot, .custom-cursor-circle { position: fixed; top: 0; left: 0; border-radius: 50%; pointer-events: none; z-index: 9999; transform: translate(-50%, -50%); display: none; }
        .custom-cursor-dot { width: 6px; height: 6px; background-color: var(--accent-blue); }
        .custom-cursor-circle { width: 30px; height: 30px; border: 1px solid rgba(139, 92, 246, 0.4); transition: transform 0.08s cubic-bezier(0.25, 1, 0.5, 1); }

        #preloader { position: fixed; inset: 0; background-color: var(--bg-site); z-index: 99999; display: flex; justify-content: center; align-items: center; transition: opacity 0.5s ease, visibility 0.5s ease; }
        .skeleton-loader-content { width: 100%; max-width: 1440px; padding: 24px; display: grid; grid-template-columns: 310px 1fr 350px; gap: 24px; }
        .skeleton-block { background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.03) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite linear; border-radius: 15px; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

        .toast-container { position: fixed; bottom: 24px; left: 24px; display: flex; flex-direction: column; gap: 10px; z-index: 6000; }
        .toast-item { background: rgba(10, 15, 30, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(59, 130, 246, 0.2); padding: 12px 18px; border-radius: 12px; color: #fff; font-size: 13px; font-weight: 600; box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 10px; transform: translateX(-120%); animation: toastIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        @keyframes toastIn { to { transform: translateX(0); } }
        .toast-item.hide { animation: toastOut 0.4s ease forwards; }
        @keyframes toastOut { to { transform: translateX(-120%); opacity: 0; } }

        header { background: rgba(5, 7, 15, 0.75); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border-bottom: 1px solid rgba(255, 255, 255, 0.06); padding: 12px 0; position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3); }
        .nav-container { max-width: 1440px; margin: 0 auto; padding: 0 24px; display: flex; justify-content: space-between; align-items: center; }
        .nav-right, .nav-left { display: flex; align-items: center; gap: 32px; }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        
        .logo-box { font-size: 20px; font-weight: 900; color: #fff; transition: transform 0.3s ease; text-decoration: none; }
        .logo-box span { background: linear-gradient(45deg, var(--accent-blue), var(--accent-purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        .main-menu { display: flex; list-style: none; gap: 8px; }
        .main-menu a { color: #94a3b8; font-size: 13.5px; font-weight: 600; padding: 8px 14px; border-radius: 8px; transition: all 0.25s; position: relative; text-decoration: none;}
        .main-menu a:hover, .main-menu a.active { color: #fff; }
        .main-menu a::after { content: ''; position: absolute; bottom: -12px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple)); transition: width 0.25s ease; }
        .main-menu a:hover::after, .main-menu a.active::after { width: 100%; }
        
        .nav-icon-btn { color: #94a3b8; width: 38px; height: 38px; border-radius: 10px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); font-size: 14px; position: relative; border: none; }
        .nav-icon-btn:hover { color: #fff; background: rgba(59, 130, 246, 0.1); border-color: var(--accent-blue); transform: translateY(-1px); }

        .search-container-box { display: flex; align-items: center; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 10px; padding: 2px 8px; width: 200px; transition: all 0.3s; }
        .search-container-box:focus-within { border-color: var(--accent-blue); width: 240px; box-shadow: 0 0 10px var(--accent-glow); }
        .search-container-box input { background: transparent; border: none; color: #fff; font-size: 12px; outline: none; padding: 6px; width: 100%; }

        .user-login-btn { display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1)); border: 1px solid rgba(59, 130, 246, 0.3); padding: 7px 16px; border-radius: 10px; color: #fff; font-size: 13px; font-weight: 700; transition: all 0.3s ease; text-decoration: none; box-shadow: 0 0 10px rgba(59, 130, 246, 0.05); }
        .user-login-btn:hover { border-color: var(--accent-purple); background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(139, 92, 246, 0.2)); box-shadow: 0 0 15px rgba(139, 92, 246, 0.25); transform: translateY(-1px); }
        .user-login-btn i { font-size: 14px; color: var(--accent-blue); }

        .btn-translate-toggle {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(59, 130, 246, 0.1));
            border: 1px solid rgba(139, 92, 246, 0.3); color: #fff; padding: 7px 14px; border-radius: 10px;
            font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 6px; transition: all 0.25s ease;
        }
        .btn-translate-toggle:hover { border-color: var(--accent-blue); box-shadow: 0 0 12px var(--accent-glow); transform: translateY(-1px); }

        .main-layout { max-width: 1440px; margin: 24px auto; padding: 0 24px; display: grid; grid-template-columns: 310px 1fr 350px; gap: 24px; min-height: 80vh; }
        .white-card { background: var(--card-bg); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border-radius: var(--radius-card); padding: 22px; border: 1px solid var(--border-color); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2); margin-bottom: 24px; position: relative; transition: all 0.3s; }
        .white-card:hover { border-color: rgba(255,255,255,0.08); }

        .block-title { font-size: 16px; font-weight: 900; margin-bottom: 18px; color: #fff; position: relative; padding-right: 12px; }
        html[dir="rtl"] .block-title { padding-right: 12px; padding-left: 0; }
        html[dir="ltr"] .block-title { padding-left: 12px; padding-right: 0; }
        html[dir="rtl"] .block-title::before { content: ''; position: absolute; right: 0; left: auto; top: 4px; bottom: 4px; width: 3.5px; background: linear-gradient(to bottom, var(--accent-blue), var(--accent-purple)); border-radius: 4px; }
        html[dir="ltr"] .block-title::before { content: ''; position: absolute; left: 0; right: auto; top: 4px; bottom: 4px; width: 3.5px; background: linear-gradient(to bottom, var(--accent-blue), var(--accent-purple)); border-radius: 4px; }

        .metric-percentage { font-size: 32px; font-weight: 900; color: #fff; text-shadow: 0 0 15px rgba(59, 130, 246, 0.4); }
        .chart-container { width: 100%; height: 110px; margin-bottom: 16px; filter: drop-shadow(0px 4px 8px rgba(5b, 130, 246, 0.25)); }
        
        .side-menu { list-style: none; display: flex; flex-direction: column; gap: 6px; }
        .side-menu li a { display: flex; align-items: center; justify-content: space-between; padding: 12px; font-size: 14px; font-weight: 700; border-radius: 12px; background: rgba(255,255,255,0.01); transition: all 0.25s; text-decoration: none; color: #cbd5e1; }
        .side-menu li a:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
        html[dir="rtl"] .side-menu li a:hover { transform: translateX(-4px); }
        html[dir="ltr"] .side-menu li a:hover { transform: translateX(4px); }

        .hero-banner { width: 100%; height: 440px; border-radius: var(--radius-card); position: relative; display: flex; flex-direction: column; justify-content: flex-end; padding: 35px; color: #ffffff; margin-bottom: 24px; overflow: hidden; border: 1px solid rgba(255,255,255,0.06); transition: all 0.5s ease; }
        .hero-banner::after { content: ''; position: absolute; inset: 0; background: linear-gradient(to top, rgba(5,7,15,1) 10%, rgba(5,7,15,0.2) 60%, transparent 100%); z-index: 1; }
        
        .hero-badge { position: relative; z-index: 2; background: linear-gradient(135deg, #ef4444, #f43f5e); color: #fff; font-size: 11px; font-weight: 900; padding: 5px 12px; border-radius: 6px; width: fit-content; margin-bottom: 12px; box-shadow: 0 0 12px rgba(239, 68, 68, 0.3); }
        .hero-title { position: relative; z-index: 2; font-size: 26px; font-weight: 900; line-height: 1.4; margin-bottom: 10px; text-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .hero-desc { position: relative; z-index: 2; color: #cbd5e1; font-size: 15px; max-width: 85%; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .hero-bottom-row { display: flex; justify-content: space-between; align-items: center; z-index: 5; }

        .slider-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); width: 42px; height: 42px; background-color: rgba(5, 7, 15, 0.6); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; z-index: 10; font-size: 16px; transition: all 0.25s; border: none; }
        .slider-nav-btn:hover { background-color: var(--accent-blue); box-shadow: 0 0 12px var(--accent-glow); }
        html[dir="rtl"] .btn-prev { right: 15px; left: auto; } html[dir="rtl"] .btn-next { left: 15px; right: auto; }
        html[dir="ltr"] .btn-prev { left: 15px; right: auto; } html[dir="ltr"] .btn-next { right: 15px; left: auto; }

        .slider-dots { display: flex; gap: 6px; align-items: center;}
        .dot { width: 7px; height: 7px; background-color: rgba(255,255,255,0.3); border-radius: 50%; transition: all 0.25s; }
        .dot.active { background-color: var(--accent-blue); width: 22px; border-radius: 5px; box-shadow: 0 0 8px var(--accent-blue); }

        .btn-read-more { border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05); backdrop-filter: blur(8px); color: #fff; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 700; transition: all 0.25s; text-decoration: none;}
        .btn-read-more:hover { background: #ffffff; color: var(--bg-site); transform: scale(1.03); }

        .filter-tabs-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; }
        .tabs-right { display: flex; gap: 6px; list-style: none; }
        .tabs-right li a { font-size: 14px; font-weight: 700; color: #94a3b8; padding: 8px 16px; border-radius: 12px; transition: all 0.25s; text-decoration: none; }
        .tabs-right li a.active, .tabs-right li a:hover { color: #fff; background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(139, 92, 246, 0.15)); border: 1px solid rgba(59, 130, 246, 0.25); }

        .grid-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px; perspective: 1000px; }
        .grid-card { background-color: var(--card-bg); backdrop-filter: var(--glass-blur); border-radius: var(--radius-card); overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--border-color); transform-style: preserve-3d; transform: perspective(1000px); transition: box-shadow 0.3s, border-color 0.3s; }
        .grid-card:hover { border-color: rgba(139, 92, 246, 0.25); box-shadow: 0 25px 45px rgba(0,0,0,0.35); }
        
        .card-img-wrapper { width: 100%; height: 200px; position: relative; overflow: hidden; transform: translateZ(15px); }
        .card-img-wrapper img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
        .grid-card:hover .card-img-wrapper img { transform: scale(1.05); }
        
        html[dir="rtl"] .card-time-badge { position: absolute; bottom: 12px; left: 12px; right: auto; }
        html[dir="ltr"] .card-time-badge { position: absolute; bottom: 12px; right: 12px; left: auto; }
        .card-time-badge { background: rgba(5, 7, 15, 0.75); backdrop-filter: blur(6px); color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); }
        
        .card-inner-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; transform: translateZ(10px); }
        .card-inner-title { font-size: 15px; font-weight: 800; color: #fff; margin-bottom: 10px; line-height: 1.5; }
        .card-inner-desc { font-size: 13px; color: #94a3b8; line-height: 1.6; margin-bottom: 18px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-footer-meta { display: flex; justify-content: space-between; align-items: center; color: #64748b; font-size: 12px; border-top: 1px solid var(--border-color); padding-top: 14px; }
        .author-tag { color: var(--accent-blue); font-weight: bold; }

        .card-interaction-bar { display: flex; gap: 14px; color: var(--text-muted); font-size: 12px; margin-top: 8px; border-top: 1px dashed rgba(255,255,255,0.03); padding-top: 8px; }
        .card-interaction-bar span i { margin-left: 4px; color: var(--accent-blue); }

        /* 🎬 [مطور حديثاً - UI لوحة عروض الفيديو]: الاستايلات البرمجية لنظام شبكة رادار الفيديو */
        .video-quantum-section { width: 100%; margin-bottom: 35px; border: 1px solid rgba(139, 92, 246, 0.15); padding: 24px; border-radius: var(--radius-card); background: rgba(13, 20, 41, 0.4); backdrop-filter: blur(15px); }
        .video-quantum-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 15px; }
        .video-quantum-card { background: rgba(5, 7, 15, 0.6); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; transition: all 0.3s; }
        .video-quantum-card:hover { border-color: var(--accent-blue); box-shadow: 0 15px 30px rgba(59,130,246,0.15); transform: translateY(-3px); }
        .video-iframe-wrapper { position: relative; width: 100%; padding-top: 56.25%; background: #000; }
        .video-iframe-wrapper iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
        .video-card-body { padding: 16px; }
        .video-card-title { font-size: 14px; font-weight: 800; color: #fff; line-height: 1.5; margin-bottom: 8px; }
        .video-card-meta { display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: var(--text-muted); }

        .side-news-row { display: flex; gap: 14px; padding-bottom: 14px; border-bottom: 1px solid var(--border-color); transition: transform 0.25s; align-items: center; }
        html[dir="rtl"] .side-news-row:hover { transform: scale(1.02) translateX(-4px); }
        html[dir="ltr"] .side-news-row:hover { transform: scale(1.02) translateX(4px); }
        .side-news-thumb { width: 90px; height: 72px; border-radius: 12px; object-fit: cover; }
        .side-news-row-title { font-size: 13.5px; font-weight: 700; color: #fff; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .opinion-card { background: rgba(255,255,255,0.01); border: 1px solid var(--border-color); padding: 14px; border-radius: 14px; transition: all 0.25s; margin-bottom: 10px; }
        .opinion-card:hover { background: rgba(139, 92, 246, 0.04); border-color: rgba(139, 92, 246, 0.15); }
        .opinion-title { font-size: 13.5px; font-weight: 700; color: #f1f5f9; line-height: 1.4; }

        .top-read-item { display: flex; align-items: center; gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
        .rank-number { font-size: 38px; font-weight: 900; color: rgba(255,255,255,0.04); transition: color 0.25s; }
        .top-read-item:hover .rank-number { color: var(--accent-purple); }
        .top-read-title { font-size: 13.5px; font-weight: 700; color: #e2e8f0; }

        footer { background: rgba(5, 7, 15, 0.85); backdrop-filter: var(--glass-blur); -webkit-backdrop-filter: var(--glass-blur); border-top: 1px solid rgba(255, 255, 255, 0.05); padding: 60px 0 20px 0; margin-top: 60px; position: relative; }
        .footer-container { max-width: 1440px; margin: 0 auto; padding: 0 24px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 40px; }
        .footer-col h4 { font-size: 16px; font-weight: 900; color: #fff; margin-bottom: 20px; position: relative; padding-bottom: 8px; }
        html[dir="rtl"] .footer-col h4::after { content: ''; position: absolute; bottom: 0; right: 0; left: auto; width: 35px; height: 2px; background: var(--accent-blue); }
        html[dir="ltr"] .footer-col h4::after { content: ''; position: absolute; bottom: 0; left: 0; right: auto; width: 35px; height: 2px; background: var(--accent-blue); }
        .footer-col p { color: #94a3b8; font-size: 13.5px; line-height: 1.7; }
        .footer-links { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .footer-links a { color: #94a3b8; font-size: 13.5px; text-decoration: none; transition: all 0.25s; display: inline-block; }
        html[dir="rtl"] .footer-links a:hover { transform: translateX(-4px); }
        html[dir="ltr"] .footer-links a:hover { transform: translateX(4px); }
        .footer-socials { display: flex; gap: 12px; margin-top: 18px; }
        .footer-socials a { width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); color: #94a3b8; display: flex; align-items: center; justify-content: center; transition: all 0.25s; text-decoration: none; }
        .footer-socials a:hover { background: var(--accent-blue); color: #fff; transform: translateY(-2px); box-shadow: 0 0 10px var(--accent-glow); }

        .newsletter-form { display: flex; gap: 8px; margin-top: 15px; }
        .newsletter-form input { flex: 1; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: 8px; color: #fff; font-size: 13px; outline: none; }
        .newsletter-form input:focus { border-color: var(--accent-blue); }
        .newsletter-form button { background: var(--accent-blue); color: #fff; border: none; padding: 0 16px; border-radius: 8px; font-weight: 700; transition: all 0.25s; }
        .newsletter-form button:hover { background: #2563eb; }

        .footer-bottom { max-width: 1440px; margin: 40px auto 0 auto; padding: 20px 24px 0 24px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; color: #64748b; font-size: 12.5px; }
        
        .goog-te-banner-frame, .skiptranslate, #goog-gt-tt { display: none !important; }
        body { top: 0 !important; }

        .tech-modal-overlay {
            position: fixed; inset: 0; background: rgba(3, 7, 18, 0.4); backdrop-filter: blur(25px) saturate(200%); -webkit-backdrop-filter: blur(25px) saturate(200%); z-index: 5000; display: flex; align-items: center; justify-content: center; padding: 30px; opacity: 0; visibility: hidden; transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tech-modal-overlay.active { opacity: 1; visibility: visible; }
        .tech-modal-container {
            background: rgba(11, 19, 41, 0.65); border: 1px solid rgba(59, 130, 246, 0.2); box-shadow: 0 30px 70px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255,255,255,0.05), 0 0 40px rgba(59, 130, 246, 0.1); border-radius: 28px; width: 100%; max-width: 1150px; height: 85vh; overflow: hidden; position: relative; transform: scale(0.9) translateY(30px); transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .tech-modal-overlay.active .tech-modal-container { transform: scale(1) translateY(0); }
        .modal-close-btn {
            position: absolute; top: 20px; left: 20px; z-index: 5010; width: 40px; height: 40px; border-radius: 50%; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); color: #94a3b8; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.3s; border: none;
        }
        html[dir="ltr"] .modal-close-btn { left: auto; right: 20px; }
        .modal-close-btn:hover { background: #ef4444; color: #fff; border-color: #ef4444; transform: rotate(90deg); box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); }
        .modal-grid-layout { display: grid; grid-template-columns: 1.1fr 1.3fr; height: 100%; }
        .modal-image-panel { position: relative; width: 100%; height: 100%; background-color: #070a13; overflow: hidden; }
        .modal-image-panel img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.8s ease; }
        .tech-modal-container:hover .modal-image-panel img { transform: scale(1.04); }
        .modal-image-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(11, 19, 41, 0.95) 5%, rgba(11, 19, 41, 0.1) 50%, transparent 100%); display: flex; align-items: flex-end; gap: 10px; padding: 30px; }
        .modal-badge-cat { background: rgba(59, 130, 246, 0.2); border: 1px solid var(--accent-blue); color: #fff; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 8px; backdrop-filter: blur(4px); }
        .modal-badge-breaking { background: rgba(244, 63, 94, 0.2); border: 1px solid #f43f5e; color: #f43f5e; font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 8px; display: none; }
        .modal-content-panel { padding: 45px; display: flex; flex-direction: column; overflow-y: auto; height: 100%; background: linear-gradient(135deg, rgba(11, 19, 41, 0.4) 0%, rgba(7, 10, 19, 0.8) 100%); }
        .modal-content-panel::-webkit-scrollbar { width: 4px; }
        .modal-content-panel::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.05); border-radius: 10px; }
        .modal-meta-header { display: flex; gap: 20px; color: var(--text-muted); font-size: 13px; font-weight: 600; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .meta-item { display: flex; align-items: center; gap: 8px; }
        .meta-item i { color: var(--accent-blue); }
        .modal-core-title { font-size: 24px; font-weight: 900; color: #ffffff; line-height: 1.5; margin-bottom: 24px; }
        .modal-article-body { color: #cbd5e1; font-size: 15.5px; line-height: 2; flex-grow: 1; margin-bottom: 30px; text-align: justify; }
        .modal-article-body p { margin-bottom: 16px; }
        .modal-meta-footer { border-top: 1px solid var(--border-color); padding-top: 20px; display: flex; justify-content: space-between; align-items: center; gap: 20px; }
        .modal-tags-container { display: flex; flex-wrap: wrap; gap: 8px; }
        .modal-tag-item { font-size: 11px; font-weight: 600; color: #94a3b8; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 6px; }
        .modal-share-btn { background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1)); border: 1px solid rgba(59, 130, 246, 0.3); color: #fff; padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; transition: all 0.3s; white-space: nowrap; border: none; }
        .modal-share-btn:hover { border-color: var(--accent-purple); box-shadow: 0 0 15px rgba(139, 92, 246, 0.2); transform: translateY(-2px); }

        .btn-audio-control { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #fff; padding: 5px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; transition: all 0.3s; outline: none; border: none; }
        .btn-audio-control:hover { background: rgba(59, 130, 246, 0.2); border-color: var(--accent-blue); box-shadow: 0 0 10px var(--accent-glow); }
        .btn-audio-stop { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fff; padding: 5px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; transition: all 0.3s; display: none; outline: none; border: none; }
        .btn-audio-stop:hover { background: rgba(239, 68, 68, 0.2); border-color: #ef4444; box-shadow: 0 0 10px rgba(239, 68, 68, 0.2); }

        .modal-action-bar-ai { display: flex; gap: 15px; margin-bottom: 20px; background: rgba(255,255,255,0.02); padding: 12px 20px; border-radius: 14px; border: 1px solid var(--border-color); width: fit-content; }
        .action-trigger-btn { background: transparent; border: none; color: #94a3b8; font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .action-trigger-btn:hover, .action-trigger-btn.liked { color: #fff; }
        .action-trigger-btn.liked i { color: #ef4444; animation: heartBeat 0.3s ease-in-out; }
        @keyframes heartBeat { 0% { transform: scale(1); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }
        .action-trigger-btn i { font-size: 15px; color: var(--accent-blue); }

        /* 🔮 لوحة الإشعارات ونموذج التوثيق السحابي المدمج */
        .notify-badge-count { position: absolute; top: 4px; right: 4px; background: #ef4444; color: #fff; font-size: 9px; padding: 2px 5px; border-radius: 50%; font-weight: 900; box-shadow: 0 0 8px #ef4444; }
        .cyber-panel-modal { position: fixed; inset: 0; background: rgba(3,7,18,0.5); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); z-index: 5500; display: flex; align-items: center; justify-content: center; padding: 30px; opacity: 0; visibility: hidden; transition: all 0.4s ease; }
        .cyber-panel-modal.active { opacity: 1; visibility: visible; }
        .cyber-panel-container { background: rgba(10,15,30,0.85); border: 1px solid rgba(59,130,246,0.25); border-radius: 20px; width: 100%; max-width: 420px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); transform: translateY(-20px); transition: all 0.4s; }
        .cyber-panel-modal.active .cyber-panel-container { transform: translateY(0); }
        .cyber-panel-title { font-size: 18px; font-weight: 900; margin-bottom: 20px; color: #fff; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 12px; }
        .cyber-input-group { margin-bottom: 16px; position: relative; }
        .cyber-input-group label { display: block; font-size: 12px; font-weight: 700; color: #cbd5e1; margin-bottom: 8px; }
        .cyber-input-group input { width: 100%; background: rgba(5,7,15,0.6); border: 1px solid var(--border-color); padding: 12px 16px; border-radius: 10px; color: #fff; font-size: 13px; outline: none; transition: all 0.3s; }
        .cyber-input-group input:focus { border-color: var(--accent-blue); box-shadow: 0 0 10px var(--accent-glow); }
        .cyber-form-btn { background: linear-gradient(135deg, #3b82f6, #8b5cf6); border: none; color: #fff; padding: 12px; width: 100%; border-radius: 10px; font-weight: 700; font-size: 14px; transition: all 0.3s; }
        .cyber-form-btn:hover { box-shadow: 0 0 15px var(--accent-glow); transform: scale(1.02); }
        .notification-list-box { display: flex; flex-direction: column; gap: 12px; max-height: 300px; overflow-y: auto; padding-right: 4px; }
        .notification-item { background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 12px; border-radius: 10px; display: flex; gap: 12px; align-items: flex-start; }
        .notification-item i { color: var(--accent-blue); margin-top: 3px; }
        .notification-text { font-size: 12.5px; color: #e2e8f0; line-height: 1.5; }

        /* 📋 [مطور حديثاً] تبديل التابات الزجاجية الفخمة داخل بوابة الدخول والتسجيل */
        .auth-tab-row { display: flex; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 20px; gap: 15px; }
        .auth-tab-btn { background: transparent; border: none; color: var(--text-muted); font-size: 14px; font-weight: 700; padding-bottom: 10px; position: relative; padding-right: 4px; }
        .auth-tab-btn.active { color: #fff; }
        .auth-tab-btn.active::after { content: ''; position: absolute; bottom: -1px; right: 0; width: 100%; height: 2px; background: var(--accent-blue); box-shadow: 0 0 8px var(--accent-blue); }
    </style>
</head>
<body>

    <div id="progressBar"></div>
    <div class="custom-cursor-dot" id="cursorDot"></div>
    <div class="custom-cursor-circle" id="cursorCircle"></div>
    <div class="toast-container" id="toastContainer"></div>
    <div id="scrollTopBtn" onclick="scrollToTop()"><i class="fa-solid fa-arrow-up"></i></div>

    <div id="preloader">
        <div class="skeleton-loader-content">
            <div class="skeleton-block" style="height: 500px;"></div>
            <div>
                <div class="skeleton-block" style="height: 300px; margin-bottom: 24px;"></div>
                <div class="skeleton-block" style="height: 200px;"></div>
            </div>
            <div class="skeleton-block" style="height: 500px;"></div>
        </div>
    </div>

    <canvas id="particleCanvas"></canvas>
    <div id="google_translate_element" style="display:none;"></div>

    <header>
        <div class="nav-container">
            <div class="nav-right">
                <a href="#" class="logo-box">K<span>·NEWS</span></a>
                <ul class="main-menu" id="mainMenu"></ul>
            </div>
            <div class="nav-left">
                <div class="header-actions">
                    <button class="btn-translate-toggle" onclick="togglePlatformLanguage()">
                        <i class="fa-solid fa-language" style="color: var(--accent-blue); font-size:16px;"></i>
                        <span id="langBtnText">English</span>
                    </button>
                    
                    <div class="search-container-box">
                        <i class="fa-solid fa-magnifying-glass" style="color: var(--text-muted); font-size: 13px; margin-right: 4px;"></i>
                        <input type="text" id="siteSearchBar" placeholder="ابحث في رادار الأخبار الحية..." oninput="executeLivePipelineSearch(this.value)">
                    </div>

                    <div class="nav-icon-btn" onclick="openCyberPanel('notificationsModal')">
                        <i class="fa-regular fa-bell"></i>
                        <div class="notify-badge-count">3</div>
                    </div>

                    <a href="#" class="user-login-btn" id="navLoginBtn" onclick="openCyberPanel('authModal')">
                        <i class="fa-solid fa-user-astronaut"></i>
                        <span id="txtLoginBtnLabel">تسجيل الدخول</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="main-layout">
        <aside class="left-sidebar">
            <div class="white-card">
                <div class="block-title" id="lblLiveInteraction">التفاعل المباشر للبث</div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <div class="metric-percentage" id="livePercentage">78.4%</div>
                    <div style="color: #34d399; font-size:12px; font-weight:700;" id="lblAscending"><i class="fa-solid fa-chart-line"></i> متصاعد</div>
                </div>
                <div class="chart-container">
                    <canvas id="liveMetricChart"></canvas>
                </div>
                <div class="stat-list" id="statsContainer"></div>
            </div>

            <div class="white-card">
                <div class="block-title" id="lblSegments">الأقسام</div>
                <ul class="side-menu" id="sideCategories"></ul>
            </div>
        </aside>

        <main class="center-main">
            <div class="hero-banner" id="heroBanner">
                <button class="slider-nav-btn btn-prev" onclick="prevSlide()"><i class="fa-solid fa-chevron-right"></i></button>
                <button class="slider-nav-btn btn-next" onclick="nextSlide()"><i class="fa-solid fa-chevron-left"></i></button>
                
                <span class="hero-badge" id="heroBadge">--</span>
                <h2 class="hero-title" id="heroTitle">--</h2>
                <p class="hero-desc" id="heroDesc">--</p>
                
                <div class="hero-bottom-row">
                    <a class="btn-read-more" id="heroLink" target="_blank">استعراض التحليل الفوري</a>
                    <div class="slider-dots" id="sliderDots"></div>
                </div>
            </div>

            <section class="video-quantum-section">
                <div class="block-title" id="lblVideoRadar"><i class="fa-solid fa-circle-play" style="color:var(--accent-blue); margin-left:8px;"></i> رادار البث المرئي والفيديوهات</div>
                <div class="video-quantum-grid" id="videoQuantumGrid"></div>
            </section>

            <div class="filter-tabs-container">
                <h3 style="font-size: 17px; font-weight: 900; color: #fff;" id="lblRadar">الرادار الإخباري</h3>
                <ul class="tabs-right" id="filterTabs"></ul>
            </div>

            <div class="grid-container" id="newsGrid"></div>
        </main>

        <aside class="right-sidebar">
            <div class="white-card">
                <div class="block-title" id="lblExclusive">الموجز المباشر الحصري</div>
                <div class="side-news-list" id="latestNewsContainer"></div>
            </div>

            <div class="white-card">
                <div class="block-title" id="lblOpinions">غرفة مقالات الرأي</div>
                <div class="opinions-grid" id="opinionsContainer"></div>
            </div>

            <div class="white-card">
                <div class="block-title" id="lblMostInteracted">الأكثر تفاعلاً الآن</div>
                <div id="topReadContainer"></div>
            </div>
        </aside>
    </div>

    <div id="authModal" class="cyber-panel-modal" onclick="closeCyberPanel('authModal')">
        <div class="cyber-panel-container" onclick="event.stopPropagation()">
            <div class="auth-tab-row">
                <button class="auth-tab-btn active" id="tabLoginHead" onclick="switchAuthTabMode('login')">تسجيل الدخول</button>
                <button class="auth-tab-btn" id="tabSignupHead" onclick="switchAuthTabMode('signup')">إنشاء حساب جديد</button>
            </div>

            <form id="authLoginForm" onsubmit="executeSecureAuthPipeline(event, 'login')">
                <div class="cyber-input-group">
                    <label id="lblFormUser">اسم المستخدم أو البريد السحابي</label>
                    <input type="text" id="loginIdentity" required placeholder="كريم أو الإيميل...">
                </div>
                <div class="cyber-input-group">
                    <label id="lblFormPass">شفرة العبور والتحقق (Password)</label>
                    <input type="password" id="loginPassword" required placeholder="••••••••">
                </div>
                <button type="submit" class="cyber-form-btn" id="lblFormBtnLogin">بث وإذن الدخول</button>
            </form>

            <form id="authSignupForm" onsubmit="executeSecureAuthPipeline(event, 'signup')" style="display: none;">
                <div class="cyber-input-group">
                    <label id="lblFormSignUser">اسم المستخدم الجديد</label>
                    <input type="text" id="signupUsername" required placeholder="مثال: Kareem99">
                </div>
                <div class="cyber-input-group">
                    <label id="lblFormSignEmail">البريد الإلكتروني السحابي</label>
                    <input type="email" id="signupEmail" required placeholder="name@domain.com">
                </div>
                <div class="cyber-input-group">
                    <label id="lblFormSignPass">تعيين كلمة المرور المشفرة</label>
                    <input type="password" id="signupPassword" required placeholder="••••••••">
                </div>
                <button type="submit" class="cyber-form-btn" id="lblFormBtnSignup" style="background: linear-gradient(135deg, #10b981, #3b82f6);">إنشاء وتأمين الحساب</button>
            </form>
        </div>
    </div>

    <div id="notificationsModal" class="cyber-panel-modal" onclick="closeCyberPanel('notificationsModal')">
        <div class="cyber-panel-container" onclick="event.stopPropagation()">
            <div class="cyber-panel-title">
                <i class="fa-solid fa-satellite" style="color: var(--accent-purple);"></i>
                <span id="lblModalNotifyTitle">مركز التحديثات السحابي الحركي</span>
            </div>
            <div class="notification-list-box">
                <div class="notification-item">
                    <i class="fa-solid fa-circle-radiation"></i>
                    <div class="notification-text"><b>رادار التحليلات:</b> تم مزامنة إحصائيات التفاعلات الإخبارية الحية بنجاح كلي.</div>
                </div>
                <div class="notification-item">
                    <i class="fa-solid fa-user-gear"></i>
                    <div class="notification-text"><b>الآدمن كريم أحمد:</b> بوابة إدارة بث الأخبار `add-news.php` مستقرة الآن ومربوطة.</div>
                </div>
                <div class="notification-item">
                    <i class="fa-solid fa-bolt"></i>
                    <div class="notification-text"><b>تنبيه عاجل:</b> محرك القراءة الصوتية بالذكاء الاصطناعي (AI) مهيأ بشكل متزن لكافة اللغات.</div>
                </div>
            </div>
        </div>
    </div>

    <div id="newsDetailModal" class="tech-modal-overlay" onclick="closeNewsModal(event)">
        <div class="tech-modal-container" onclick="event.stopPropagation()">
            <button class="modal-close-btn" onclick="closeNewsModal(event)">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="modal-grid-layout">
                <div class="modal-image-panel">
                    <img id="modalNewsImg" src="" alt="News Cover">
                    <div class="modal-image-overlay">
                        <span id="modalNewsCategory" class="modal-badge-cat">--</span>
                        <span id="modalNewsBreaking" class="modal-badge-breaking">عاجل</span>
                    </div>
                </div>
                <div class="modal-content-panel">
                    <div class="modal-meta-header">
                        <div class="meta-item">
                            <i class="fa-solid fa-user-feather"></i>
                            <span id="modalNewsAuthor">--</span>
                        </div>
                        <div class="meta-item">
                            <i class="fa-regular fa-clock"></i>
                            <span id="modalNewsTime">--</span>
                        </div>
                        
                        <div class="meta-item" style="margin-right: auto; margin-left: 0; display: flex; gap: 8px;">
                            <button id="btnPlayAudio" class="btn-audio-control" onclick="speakNewsArticle()">
                                <i class="fa-solid fa-volume-high" style="margin-left: 4px;"></i> <span id="lblAudioPlay">استمع للخبر (AI)</span>
                            </button>
                            <button id="btnStopAudio" class="btn-audio-stop" onclick="stopSpeaking()">
                                <i class="fa-solid fa-stop" style="margin-left: 4px;"></i> <span id="lblAudioStop">إيقاف</span>
                            </button>
                        </div>
                    </div>
                    
                    <h2 id="modalNewsTitle" class="modal-core-title">--</h2>
                    
                    <div class="modal-action-bar-ai">
                        <button class="action-trigger-btn" id="modalBtnLike" onclick="sendInteraction('like')">
                            <i class="fa-regular fa-heart"></i> <span id="modalLikeCount">0</span>
                        </button>
                        <button class="action-trigger-btn" style="pointer-events:none;">
                            <i class="fa-regular fa-eye"></i> <span id="modalViewCount">0</span>
                        </button>
                        <button class="action-trigger-btn" onclick="sendInteraction('share'); shareCurrentNews()">
                            <i class="fa-regular fa-share-from-square"></i> <span id="modalShareCount">0</span>
                        </button>
                    </div>

                    <div id="modalNewsBody" class="modal-article-body">--</div>
                    <div class="modal-meta-footer">
                        <div id="modalNewsTags" class="modal-tags-container"></div>
                        <button class="modal-share-btn" onclick="sendInteraction('share'); shareCurrentNews()"><i class="fa-solid fa-share-nodes"></i> مشاركة الخبر</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-container">
            <div class="footer-col">
                <h4 id="lblAboutTitle">حول K-NEWS</h4>
                <p id="lblAboutDesc">المنصة الإخبارية التفاعلية الأولى المدعومة بأنظمة الذكاء الاصطناعي السحابي، لتقديم التحليلات الفورية والموجز الحصري على مدار الساعة بقوة الابتكار الصاعد.</p>
                <div class="footer-socials">
                    <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
                    <a href="#"><i class="fa-brands fa-telegram"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4 id="lblQuickLinks">روابط سريعة</h4>
                <ul class="footer-links">
                    <li><a href="#" id="fLink1">التحليل الفوري الفوقي</a></li>
                    <li><a href="#" id="fLink2">غرفة البث الحي المباشر</a></li>
                    <li><a href="#" id="fLink3">مركز الصحافة الاستقصائية</a></li>
                    <li><a href="#" id="fLink4">شروط وأحكام الاستخدام</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4 id="lblRadarFooter">رادار التصنيفات</h4>
                <ul class="footer-links" id="footerCategories"></ul>
            </div>
            <div class="footer-col">
                <h4 id="lblNewsletter">النشرة السحابية</h4>
                <p id="lblNewsletterDesc">اشترك في رادار الموجز الإخباري لتلقي التحليلات والبيانات الفورية الحصرية مباشرة ببريدك السحابي.</p>
                <form class="newsletter-form" onsubmit="handleSubscribe(event)">
                    <input type="email" placeholder="بريدك الإلكتروني المستهدف..." required id="inputNewsletter">
                    <button type="submit" id="btnSubscribe">إرسال</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom">
            <div id="lblCopyright">&copy; 2026 K-NEWS. جميع الحقوق والتحليلات محفوظة للمنصة الهيكلية.</div>
            <div style="display: flex; gap: 20px;">
                <a href="#" style="color: #64748b; text-decoration: none;" id="lblPrivacy">سياسة الخصوصية</a>
                <a href="#" style="color: #64748b; text-decoration: none;" id="lblSupport">الدعم الفني</a>
            </div>
        </div>
    </footer>

    <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <script>
        const categories = [
            { id: 'all', name: 'الرئيسية', slug: 'all', icon: 'fa-home', color: '#3b82f6' },
            <?php foreach($db_categories as $cat): ?>
            { 
                id: <?php echo $cat['id']; ?>, 
                name: '<?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>', 
                slug: '<?php echo htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8'); ?>',
                icon: '<?php 
                    if($cat['slug'] == 'technology') echo "fa-laptop-code";
                    elseif($cat['slug'] == 'sport' || $cat['slug'] == 'football') echo "fa-futbol";
                    elseif($cat['slug'] == 'world' || $cat['slug'] == 'سياسه') echo "fa-building-columns";
                    else echo "fa-newspaper"; 
                ?>',
                color: '<?php
                    if($cat['slug'] == 'technology') echo "#8b5cf6";
                    elseif($cat['slug'] == 'sport' || $cat['slug'] == 'football') echo "#f97316";
                    elseif($cat['slug'] == 'world') echo "#60a5fa";
                    else echo "#10b981";
                ?>'
            },
            <?php endforeach; ?>
        ];

        const heroItems = <?php echo json_encode($db_sliders, JSON_UNESCAPED_UNICODE); ?>;
        const newsData = <?php echo json_encode($db_news, JSON_UNESCAPED_UNICODE); ?>;
        
        // 🎬 [مطور حديثاً - ضخ بيانات الباك إند]: ضخ مصفوفة الفيديوهات المجلوبة من الداتابيز مباشرة
        const videoData = <?php echo json_encode($db_videos, JSON_UNESCAPED_UNICODE); ?>;

        const translationDictionary = {
            en: {
                langBtn: "العربية", toastWelcome: "Welcome! Database synchronized successfully.",
                lblLiveInteraction: "Live Interaction Pool", lblAscending: "Ascending", lblSegments: "Segments",
                lblRadar: "News Radar Network", lblExclusive: "Exclusive Live Feed", lblOpinions: "Op-Ed Column Room",
                lblMostInteracted: "Trending Interactions", lblAboutTitle: "About K-NEWS",
                lblAboutDesc: "The premier AI-driven interactive broadcasting hub, deploying real-time data pipelines and structural reports 24/7.",
                lblQuickLinks: "Quick Links", fLink1: "Meta Analytics", fLink2: "Live Streaming Hub", fLink3: "Investigative Journalism", fLink4: "Terms of Deployment",
                lblRadarFooter: "Segments Radar", lblNewsletter: "Cloud Newsletter", lblNewsletterDesc: "Subscribe to radar briefing for instantaneous intelligence dashboards directly to your cloud directory.",
                lblCopyright: "© 2026 K-NEWS. All operational rights and algorithmic structures reserved.", lblPrivacy: "Privacy Protocols", lblSupport: "Tech Ops",
                lblAudioPlay: "Listen (AI)", lblAudioStop: "Stop",
                lblFormUser: "Username or Cloud Email", lblFormPass: "Verification Code (Password)",
                lblModalNotifyTitle: "Cloud Dynamic Update Center",
                tabLoginHead: "Sign In", tabSignupHead: "Create Account", lblFormSignUser: "New Username", lblFormSignEmail: "Cloud Email Address", lblFormSignPass: "Set Secure Password",
                lblVideoRadar: "Visual Stream & Video Radar"
            },
            ar: {
                langBtn: "English", toastWelcome: "أهلاً بك! تم مزامنة قاعدة البيانات والشبكات بنجاح.",
                lblLiveInteraction: "التفاعل المباشر للبث", lblAscending: "متصاعد", lblSegments: "الأقسام",
                lblRadar: "الرادار الإخباري", lblExclusive: "الموجز المباشر الحصري", lblOpinions: "غرفة مقالات الرأي",
                lblMostInteracted: "الأكثر تفاعلاً الآن", lblAboutTitle: "حول K-NEWS",
                lblAboutDesc: "المنصة الإخبارية التفاعلية الأولى المدعومة بأنظمة الذكاء الاصطناعي السحابي، لتقديم التحليلات الفورية والموجز الحصري على مدار الساعة بقوة الابتكار الصاعد.",
                lblQuickLinks: "روابط سريعة", fLink1: "التحليل الفوري الفوقي", fLink2: "غرفة البث الحي المباشر", fLink3: "مركز الصحافة الاستقصائية", fLink4: "شروط وأحكام الاستخدام",
                lblRadarFooter: "رادار التصنيفات", lblNewsletter: "النشرة السحابية", lblNewsletterDesc: "اشترك في رادار الموجز الإخباري لتلقي التحليلات والبيانات الفورية الحصرية مباشرة ببريدك السحابي.",
                lblCopyright: "© 2026 K-NEWS. جميع الحقوق والتحليلات محفوظة للمنصة الهيكلية.", lblPrivacy: "سياسة الخصوصية", lblSupport: "الدعم الفني",
                lblAudioPlay: "استمع للخبر (AI)", lblAudioStop: "إيقاف",
                lblFormUser: "اسم المستخدم أو البريد السحابي", lblFormPass: "شفرة العبور والتحقق (Password)",
                lblModalNotifyTitle: "مركز التحديثات السحابي الحركي",
                tabLoginHead: "تسجيل الدخول", tabSignupHead: "إنشاء حساب جديد", lblFormSignUser: "اسم المستخدم الجديد", lblFormSignEmail: "البريد الإلكتروني السحابي", lblFormSignPass: "تعيين كلمة المرور المشفرة",
                lblVideoRadar: "رادار البث المرئي والفيديوهات"
            }
        };

        let currentLang = 'ar';
        let currentSliderIndex = 0;
        let cryptoSpeechSynth = window.speechSynthesis;
        let currentSpeechUtterance = null;
        let activeNewsId = null;

        window.addEventListener('DOMContentLoaded', () => {
            initCursor();
            initParticles();
            buildUI();
            initChart();
            
            setTimeout(() => {
                const loader = document.getElementById('preloader');
                if(loader) {
                    loader.style.opacity = '0';
                    loader.style.visibility = 'hidden';
                }
                showToast(translationDictionary[currentLang].toastWelcome);
            }, 800);
        });

        window.addEventListener('scroll', () => {
            const winScroll = document.documentElement.scrollTop || document.body.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            document.getElementById("progressBar").style.width = scrolled + "%";

            const scrollTopBtn = document.getElementById('scrollTopBtn');
            if (winScroll > 300) scrollTopBtn.classList.add('show');
            else scrollTopBtn.classList.remove('show');
        });

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // 🎬 [مطور حديثاً - فلترة روابط يوتيوب]: دالة ذكية تقوم بتحويل روابط يوتيوب (بما فيها روابط الشير والمختصرة) إلى صيغة تضمين صالحة للـ Iframe
        function parseYoutubeEmbedUrl(url) {
            let regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            let match = url.match(regExp);
            if (match && match[2].length === 11) {
                return "https://www.youtube.com/embed/" + match[2];
            }
            return url; 
        }

        function buildUI() {
            const menu = document.getElementById('mainMenu');
            const sideMenu = document.getElementById('sideCategories');
            const footerMenu = document.getElementById('footerCategories');
            const filterTabs = document.getElementById('filterTabs');

            menu.innerHTML = ''; sideMenu.innerHTML = ''; footerMenu.innerHTML = ''; filterTabs.innerHTML = '';

            categories.forEach((cat, index) => {
                if(index < 5) {
                    menu.innerHTML += `<li><a href="#" class="${cat.id==='all'?'active':''}" onclick="filterByCategory('${cat.slug}', this)">${cat.name}</a></li>`;
                }
                sideMenu.innerHTML += `<li><a href="#" onclick="filterByCategory('${cat.slug}', this)"><span class="meta-item"><i class="fa-solid ${cat.icon}" style="color:${cat.color}"></i> ${cat.name}</span> <i class="fa-solid fa-chevron-left" style="font-size:10px; opacity:0.5;"></i></a></li>`;
                footerMenu.innerHTML += `<li><a href="#">${cat.name}</a></li>`;
                filterTabs.innerHTML += `<li><a href="#" class="${cat.id==='all'?'active':''}" onclick="filterByCategory('${cat.slug}', this)">${cat.name}</a></li>`;
            });

            updateSlider();
            renderNewsGrid(newsData);
            
            // 🎬 [مطور حديثاً - رندرة مقاطع الفيديو]: رندرة محتوى قسم الفيديوهات ديناميكياً وبناء الـ HTML الخاص بكل فيديو
            const videoGrid = document.getElementById('videoQuantumGrid');
            videoGrid.innerHTML = '';
            if(videoData.length === 0) {
                videoGrid.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding:20px; width:100%;">لا توجد مقاطع فيديو متوفرة حالياً في رادار البث.</div>`;
            } else {
                videoData.forEach(video => {
                    let embedUrl = parseYoutubeEmbedUrl(video.video_url);
                    videoGrid.innerHTML += `
                        <div class="video-quantum-card">
                            <div class="video-iframe-wrapper">
                                <iframe src="${embedUrl}" allowfullscreen loading="lazy"></iframe>
                            </div>
                            <div class="video-card-body">
                                <h4 class="video-card-title">${video.title}</h4>
                                <p style="font-size:11.5px; color:var(--text-muted); margin-bottom:10px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${video.description}</p>
                                <div class="video-card-meta">
                                    <span><i class="fa-solid fa-fire" style="color:var(--accent-blue); margin-left:4px;"></i> ${video.views} مشاهدة</span>
                                    <span style="color:var(--accent-purple); font-weight:700;"><i class="fa-solid fa-hashtag"></i> ${video.category}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }

            const latestContainer = document.getElementById('latestNewsContainer');
            latestContainer.innerHTML = '';
            newsData.slice(0, 4).forEach(news => {
                latestContainer.innerHTML += `
                    <div class="side-news-row" onclick="openNewsDetail(${news.id})">
                        <img src="${news.image ? news.image : 'https://placehold.co/100x80'}" class="side-news-thumb" alt="thumb">
                        <div>
                            <div class="side-news-row-title">${news.title}</div>
                            <span style="font-size:11px; color:var(--accent-blue); font-weight:700;">${news.category_name || 'عام'}</span>
                        </div>
                    </div>`;
            });

            const opinions = document.getElementById('opinionsContainer');
            opinions.innerHTML = '';
            newsData.slice(2, 5).forEach(news => {
                opinions.innerHTML += `
                    <div class="opinion-card" onclick="openNewsDetail(${news.id})">
                        <div class="opinion-title">"${news.title}"</div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:6px;"><i class="fa-solid fa-feather"></i> كاتب المقال الإستراتيجي</div>
                    </div>`;
            });

            const topRead = document.getElementById('topReadContainer');
            topRead.innerHTML = '';
            newsData.slice(0, 3).forEach((news, idx) => {
                topRead.innerHTML += `
                    <div class="top-read-item" onclick="openNewsDetail(${news.id})">
                        <div class="rank-number">0${idx+1}</div>
                        <div class="top-read-title">${news.title}</div>
                    </div>`;
            });
        }

        function filterByCategory(slug, element) {
            document.querySelectorAll('.main-menu a, .tabs-right li a').forEach(a => a.classList.remove('active'));
            element.classList.add('active');

            if(slug === 'all') {
                renderNewsGrid(newsData);
            } else {
                const filtered = newsData.filter(item => item.categorySlug === slug);
                renderNewsGrid(filtered);
            }
        }

        function renderNewsGrid(data) {
            const grid = document.getElementById('newsGrid');
            grid.innerHTML = '';
            if(data.length === 0) {
                grid.innerHTML = `<div style="grid-column: span 2; text-align:center; padding:40px; color:var(--text-muted);">لا توجد تحليلات إخبارية متوفرة حالياً ضمن هذا التصنيف.</div>`;
                return;
            }
            data.forEach(item => {
                grid.innerHTML += `
                    <div class="grid-card" onclick="openNewsDetail(${item.id})">
                        <div class="card-img-wrapper">
                            <img src="${item.image ? item.image : 'https://placehold.co/600x400'}" alt="News Image">
                            <div class="card-time-badge">${item.timeText}</div>
                        </div>
                        <div class="card-inner-body">
                            <div>
                                <h3 class="card-inner-title">${item.title}</h3>
                                <p class="card-inner-desc">${item.summary || 'لمشاهدة محتويات هذا التقرير الإستراتيجي الفوري وباقي البنى التحليلية المفصلة، تفضل بالدخول لوصف لوحة البيانات كاملة من هنا...'}</p>
                            </div>
                            
                            <div class="card-interaction-bar">
                                <span id="gridCardViews-${item.id}"><i class="fa-regular fa-eye"></i> ${item.views}</span>
                                <span id="gridCardLikes-${item.id}"><i class="fa-regular fa-heart"></i> ${item.likes}</span>
                                <span id="gridCardShares-${item.id}"><i class="fa-regular fa-share-from-square"></i> ${item.shares}</span>
                            </div>

                            <div class="card-footer-meta" style="margin-top:10px;">
                                <span class="author-tag"><i class="fa-solid fa-user-shield"></i> ${item.author || 'كريم أحمد'}</span>
                                <span><i class="fa-solid fa-folder-open"></i> ${item.category_name || 'الرئيسية'}</span>
                            </div>
                        </div>
                    </div>`;
            });
            attachCursorHoverListeners();
        }

        function updateSlider() {
            if(heroItems.length === 0) return;
            const item = heroItems[currentSliderIndex];
            const banner = document.getElementById('heroBanner');
            
            banner.style.backgroundImage = `url('${item.image_url}')`;
            banner.style.backgroundSize = 'cover';
            banner.style.backgroundPosition = 'center';

            document.getElementById('heroBadge').textContent = item.badge || 'تحليل مباشر صاعد';
            document.getElementById('heroTitle').textContent = item.title;
            document.getElementById('heroDesc').textContent = item.description;
            document.getElementById('heroLink').href = item.action_url || '#';

            const dotsContainer = document.getElementById('sliderDots');
            dotsContainer.innerHTML = '';
            heroItems.forEach((_, idx) => {
                dotsContainer.innerHTML += `<div class="dot ${idx===currentSliderIndex?'active':''}"></div>`;
            });
        }

        function nextSlide() {
            currentSliderIndex = (currentSliderIndex + 1) % heroItems.length;
            updateSlider();
        }
        function prevSlide() {
            currentSliderIndex = (currentSliderIndex - 1 + heroItems.length) % heroItems.length;
            updateSlider();
        }

        function openNewsDetail(newsId) {
            const news = newsData.find(item => parseInt(item.id) === parseInt(newsId));
            if (!news) return;

            activeNewsId = news.id;

            document.getElementById('modalNewsTitle').textContent = news.title;
            document.getElementById('modalNewsBody').innerHTML = news.content; 
            document.getElementById('modalNewsAuthor').textContent = news.author || " ";
            document.getElementById('modalNewsTime').textContent = news.timeText || "تم النشر مؤخراً";
            document.getElementById('modalNewsCategory').textContent = news.category_name || "عام";

            document.getElementById('modalLikeCount').textContent = news.likes;
            document.getElementById('modalViewCount').textContent = parseInt(news.views) + 1;
            document.getElementById('modalShareCount').textContent = news.shares;
            
            document.getElementById('modalBtnLike').classList.remove('liked');
            document.getElementById('modalBtnLike').querySelector('i').className = 'fa-regular fa-heart';

            const fallbackImg = 'https://placehold.co/600x400/0b1329/ffffff?text=K-NEWS';
            document.getElementById('modalNewsImg').src = news.image ? news.image : fallbackImg;

            const breakingBadge = document.getElementById('modalNewsBreaking');
            if (parseInt(news.is_breaking) === 1) {
                breakingBadge.style.display = 'inline-block';
            } else {
                breakingBadge.style.display = 'none';
            }

            const tagsContainer = document.getElementById('modalNewsTags');
            tagsContainer.innerHTML = ''; 
            if (news.tags) {
                const tagsArray = news.tags.split(',');
                tagsArray.forEach(tag => {
                    if(tag.trim() !== '') {
                        const tagSpan = document.createElement('span');
                        tagSpan.className = 'modal-tag-item';
                        tagSpan.textContent = '#' + tag.trim();
                        tagsContainer.appendChild(tagSpan);
                    }
                });
            } else {
                tagsContainer.innerHTML = '<span class="modal-tag-item">#تحليل_فوري</span><span class="modal-tag-item">#K_NEWS</span>';
            }

            document.getElementById('newsDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';

            sendInteraction('view');
        }

        function closeNewsModal(event) {
            stopSpeaking(); 
            document.getElementById('newsDetailModal').classList.remove('active');
            document.body.style.overflow = 'auto';
            activeNewsId = null;
        }

        function sendInteraction(type) {
            if (!activeNewsId) return;

            if (type === 'like' && document.getElementById('modalBtnLike').classList.contains('liked')) {
                return;
            }

            const formData = new FormData();
            formData.append('news_id', activeNewsId);
            formData.append('interact_action', type);

            fetch('index.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.likes !== undefined) {
                    const newsIdx = newsData.findIndex(item => parseInt(item.id) === parseInt(activeNewsId));
                    if (newsIdx !== -1) {
                        newsData[newsIdx].likes = data.likes;
                        newsData[newsIdx].views = data.views;
                        newsData[newsIdx].shares = data.shares;
                    }

                    document.getElementById('modalLikeCount').textContent = data.likes;
                    document.getElementById('modalViewCount').textContent = data.views;
                    document.getElementById('modalShareCount').textContent = data.shares;

                    const gridLike = document.getElementById(`gridCardLikes-${activeNewsId}`);
                    const gridView = document.getElementById(`gridCardViews-${activeNewsId}`);
                    const gridShare = document.getElementById(`gridCardShares-${activeNewsId}`);
                    if(gridLike) gridLike.innerHTML = `<i class="fa-regular fa-heart"></i> ${data.likes}`;
                    if(gridView) gridView.innerHTML = `<i class="fa-regular fa-eye"></i> ${data.views}`;
                    if(gridShare) gridShare.innerHTML = `<i class="fa-regular fa-share-from-square"></i> ${data.shares}`;

                    if (type === 'like') {
                        const likeBtn = document.getElementById('modalBtnLike');
                        likeBtn.classList.add('liked');
                        likeBtn.querySelector('i').className = 'fa-solid fa-heart';
                        showToast("شكراً لك على تفاعلك مع التقرير!");
                    }
                }
            })
            .catch(error => console.error('Error broadcasting interaction:', error));
        }

        function executeLivePipelineSearch(query) {
            const cleanQuery = query.toLowerCase().trim();
            if(cleanQuery === '') {
                renderNewsGrid(newsData);
                return;
            }
            const filteredNews = newsData.filter(news => {
                return news.title.toLowerCase().includes(cleanQuery) || 
                       news.content.toLowerCase().includes(cleanQuery) ||
                       (news.author && news.author.toLowerCase().includes(cleanQuery));
            });
            renderNewsGrid(filteredNews);
        }

        function openCyberPanel(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeCyberPanel(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        function switchAuthTabMode(mode) {
            document.querySelectorAll('.auth-tab-btn').forEach(btn => btn.classList.remove('active'));
            if(mode === 'login') {
                document.getElementById('tabLoginHead').classList.add('active');
                document.getElementById('authLoginForm').style.display = 'block';
                document.getElementById('authSignupForm').style.display = 'none';
            } else {
                document.getElementById('tabSignupHead').classList.add('active');
                document.getElementById('authLoginForm').style.display = 'none';
                document.getElementById('authSignupForm').style.display = 'block';
            }
        }

        function executeSecureAuthPipeline(e, type) {
            e.preventDefault();
            const authData = new FormData();
            
            if(type === 'login') {
                authData.append('auth_action', 'login');
                authData.append('identity', document.getElementById('loginIdentity').value);
                authData.append('password', document.getElementById('loginPassword').value);
            } else {
                authData.append('auth_action', 'signup');
                authData.append('username', document.getElementById('signupUsername').value);
                authData.append('email', document.getElementById('signupEmail').value);
                authData.append('password', document.getElementById('signupPassword').value);
            }

            fetch('index.php', { method: 'POST', body: authData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    showToast(type === 'login' ? `مرحباً بعودتك ${data.username}!` : `تم إنشاء وتأمين حسابك، مرحباً ${data.username}!`);
                    closeCyberPanel('authModal');
                    document.getElementById('txtLoginBtnLabel').textContent = data.username;
                    document.getElementById('navLoginBtn').style.background = 'linear-gradient(135deg, rgba(16,185,129,0.1), rgba(52,211,153,0.1))';
                } else {
                    alert(data.message);
                }
            })
            .catch(err => console.error('Auth Pipe Fault:', err));
        }

        function speakNewsArticle() {
            const titleText = document.getElementById('modalNewsTitle').textContent;
            const bodyHTML = document.getElementById('modalNewsBody').innerHTML;
            
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = bodyHTML;
            const cleanBodyText = tempDiv.textContent || tempDiv.innerText || "";
            const fullTextToSpeak = titleText + ". " + cleanBodyText;

            if (!cryptoSpeechSynth) {
                showToast("عذراً، متصفحك الحالي لا يدعم ميزة البث الصوتي الفوري.");
                return;
            }
            cryptoSpeechSynth.cancel(); 

            currentSpeechUtterance = new SpeechSynthesisUtterance(fullTextToSpeak);
            currentSpeechUtterance.lang = currentLang === 'ar' ? 'ar-SA' : 'en-US';
            currentSpeechUtterance.rate = 0.95;  
            currentSpeechUtterance.pitch = 1.0; 

            currentSpeechUtterance.onstart = () => {
                document.getElementById('btnPlayAudio').style.display = 'none';
                document.getElementById('btnStopAudio').style.display = 'inline-block';
                showToast("تم تشغيل القارئ الصوتي الذكي (AI) بنجاح.");
            };

            currentSpeechUtterance.onend = () => { resetAudioButtons(); };
            currentSpeechUtterance.onerror = () => { resetAudioButtons(); };
            cryptoSpeechSynth.speak(currentSpeechUtterance);
        }

        function stopSpeaking() {
            if (cryptoSpeechSynth) {
                cryptoSpeechSynth.cancel();
                resetAudioButtons();
                showToast("تم إيقاف القراءة الصوتية.");
            }
        }

        function resetAudioButtons() {
            document.getElementById('btnPlayAudio').style.display = 'inline-block';
            document.getElementById('btnStopAudio').style.display = 'none';
        }

        function shareCurrentNews() {
            const title = document.getElementById('modalNewsTitle').textContent;
            if (navigator.share) {
                navigator.share({ title: title, url: window.location.href }).catch(console.error);
            } else {
                navigator.clipboard.writeText(window.location.href);
                showToast("تم نسخ رابط التقرير الإخباري لمشاركته عبر بريدك!");
            }
        }

        function togglePlatformLanguage() {
            currentLang = currentLang === 'ar' ? 'en' : 'ar';
            const htmlNode = document.getElementById('mainHtml');
            
            if(currentLang === 'en') {
                htmlNode.setAttribute('lang', 'en'); htmlNode.setAttribute('dir', 'ltr');
                document.getElementById('langBtnText').textContent = 'العربية';
            } else {
                htmlNode.setAttribute('lang', 'ar'); htmlNode.setAttribute('dir', 'rtl');
                document.getElementById('langBtnText').textContent = 'English';
            }

            const dict = translationDictionary[currentLang];
            for (const key in dict) {
                const el = document.getElementById(key);
                if(el) el.textContent = dict[key];
            }
            buildUI();
        }

        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'ar', includedLanguages: 'ar,en', layout: google.translate.TranslateElement.InlineLayout.SIMPLE}, 'google_translate_element');
        }

        function showToast(message) {
            const container = document.getElementById('toastContainer');
            const item = document.createElement('div');
            item.className = 'toast-item';
            item.innerHTML = `<i class="fa-solid fa-satellite-dish" style="color:var(--accent-blue);"></i> <span>${message}</span>`;
            container.appendChild(item);
            setTimeout(() => { item.classList.add('hide'); setTimeout(() => item.remove(), 400); }, 4000);
        }

        function handleSubscribe(e) {
            e.preventDefault();
            showToast("تم ربط بريدك الإلكتروني بنظام الرادار بنجاح!");
            document.getElementById('inputNewsletter').value = '';
        }

        function triggerLogin(e) { e.preventDefault(); }

        function initCursor() {
            const dot = document.getElementById('cursorDot'); const circle = document.getElementById('cursorCircle');
            if(!dot || !circle) return;
            document.addEventListener('mousemove', (e) => {
                dot.style.display = 'block'; circle.style.display = 'block';
                dot.style.top = e.clientY + 'px'; dot.style.left = e.clientX + 'px';
                circle.style.top = e.clientY + 'px'; circle.style.left = e.clientX + 'px';
            });
            document.addEventListener('mouseenter', () => { dot.style.opacity = '1'; circle.style.opacity = '1'; });
            document.addEventListener('mouseleave', () => { dot.style.opacity = '0'; circle.style.opacity = '0'; });
        }

        function attachCursorHoverListeners() {
            if (window.innerWidth <= 768) return;
            const circle = document.getElementById('cursorCircle');
            if(!circle) return;
            const targets = document.querySelectorAll('a, button, .grid-card, .nav-icon-btn, .user-login-btn, .dot, .search-container-box, .auth-tab-btn, .video-quantum-card');
            targets.forEach(el => {
                el.addEventListener('mouseenter', () => { circle.style.width = '50px'; circle.style.height = '50px'; circle.style.borderColor = 'var(--accent-blue)'; circle.style.backgroundColor = 'rgba(59, 130, 246, 0.05)'; });
                el.addEventListener('mouseleave', () => { circle.style.width = '30px'; circle.style.height = '30px'; circle.style.borderColor = 'rgba(139, 92, 246, 0.4)'; circle.style.backgroundColor = 'transparent'; });
            });
        }

        function initParticles() {
            const canvas = document.getElementById('particleCanvas'); if(!canvas) return;
            const ctx = canvas.getContext('2d'); let particles = [];
            function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
            window.addEventListener('resize', resize); resize();
            class Particle {
                constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = Math.random() * 1.5 + 0.5; this.speedX = Math.random() * 0.2 - 0.1; this.speedY = Math.random() * 0.2 - 0.1; }
                update() { this.x += this.speedX; this.y += this.speedY; if (this.x > canvas.width) this.x = 0; else if (this.x < 0) this.x = canvas.width; if (this.y > canvas.height) this.y = 0; else if (this.y < 0) this.y = canvas.height; }
                draw() { ctx.fillStyle = 'rgba(59, 130, 246, 0.2)'; ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); ctx.fill(); }
            }
            for (let i = 0; i < 65; i++) { particles.push(new Particle()); }
            function animate() { ctx.clearRect(0, 0, canvas.width, canvas.height); particles.forEach(p => { p.update(); p.draw(); }); requestAnimationFrame(animate); }
            animate();
        }

        function initChart() {
            const chartCanvas = document.getElementById('liveMetricChart'); if(!chartCanvas) return;
            const ctx = chartCanvas.getContext('2d');
            const liveChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['10s', '8s', '6s', '4s', '2s', '0s'],
                    datasets: [{
                        label: 'Interaction Stream', data: [65, 70, 68, 74, 72, 78.4], borderColor: '#3b82f6', borderWidth: 2, pointRadius: 0, fill: true,
                        backgroundColor: (context) => { const gradient = context.chart.ctx.createLinearGradient(0, 0, 0, 100); gradient.addColorStop(0, 'rgba(59, 130, 246, 0.25)'); gradient.addColorStop(1, 'rgba(59, 130, 246, 0)'); return gradient; },
                        tension: 0.4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
            });
            setInterval(() => {
                let currentVal = parseFloat(document.getElementById('livePercentage').textContent);
                let change = (Math.random() * 4 - 2); let newVal = Math.min(Math.max(currentVal + change, 50), 99).toFixed(1);
                document.getElementById('livePercentage').textContent = newVal + '%';
                liveChart.data.datasets[0].data.shift(); liveChart.data.datasets[0].data.push(parseFloat(newVal)); liveChart.update();
            }, 2500);
        }
    </script>
</body>
</html>