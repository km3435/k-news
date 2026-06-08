<?php
// settings.php - غرفة التحكم والتهيئة السحابية وإدارة الموظفين والامتيازات لـ K-NEWS
require_once 'db.php';

$message = "";
$messageType = "";

// 🛠️ معالجة إرسال نموذج إنشاء موظف جديد بامتياز وظيفي محدد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_employee') {
    $emp_username = trim($_POST['username'] ?? '');
    $emp_email    = trim($_POST['email'] ?? '');
    $emp_password = $_POST['password'] ?? '';
    $emp_role     = intval($_POST['role_id'] ?? 0);
    $emp_perf     = rand(85, 100); // توليد مستوى أداء افتراضي ذكي للموظف الجديد

    if (!empty($emp_username) && !empty($emp_email) && !empty($emp_password) && $emp_role > 0) {
        try {
            // فحص عدم تكرار الهوية الرقمية
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmtCheck->execute([$emp_username, $emp_email]);

            if ($stmtCheck->fetchColumn() > 0) {
                $message = "خطأ: اسم المستخدم أو البريد السحابي مسجل مسبقاً بالنظام!";
                $messageType = "error";
            } else {
                // تشفير كلمة المرور بحماية داتا التشفير العالية
                $hashedPassword = password_hash($emp_password, PASSWORD_BCRYPT);
                
                $stmtInsert = $pdo->prepare("INSERT INTO users (username, email, password, role_id, status, performance) VALUES (?, ?, ?, ?, 'Offline', ?)");
                if ($stmtInsert->execute([$emp_username, $emp_email, $hashedPassword, $emp_role, $emp_perf])) {
                    $message = "تم تشفير ونشر بيانات الموظف وتعيين الرتبة الوظيفية بنجاح كلي.";
                    $messageType = "success";
                }
            }
        } catch (PDOException $e) {
            $message = "خطأ في النواة: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "يرجى ملء كافة حقول التوثيق الرقمي المطلوبة.";
        $messageType = "error";
    }
}

// 🗑️ معالجة حذف الموظفين
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_employee') {
    $user_id = intval($_POST['user_id'] ?? 0);
    if ($user_id > 1) { // حماية حساب الآدمن الرئيسي رقم 1 من الحذف
        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmtDel->execute([$user_id]);
        $message = "تم قطع اتصال الموظف وسحب صلاحياته من خادم البيانات.";
        $messageType = "success";
    }
}

// جلب قائمة الأدوار والموظفين ديناميكياً من قاعدة البيانات
$roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
$employees = $pdo->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl" id="htmlBlock">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS - النواة البرمجية وإعدادات النظام</title>
    <link href="https://fonts.googleapis.com/css2 family=Cairo:wght@300;400;600;700;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-primary: #04060a; --bg-secondary: #090d16; --bg-input: #04060a;
            --border-color: #131a2a; --accent-blue: #3b82f6; --accent-purple: #8b5cf6;
            --text-main: #ffffff; --text-muted: #64748b; --accent-glow: rgba(59, 130, 246, 0.4);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: var(--bg-primary); color: var(--text-main); display: flex; min-height: 100vh; font-family: 'Cairo', sans-serif; overflow-x: hidden; }
        
        #worldBg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.3; }
        .sidebar, .main-content { position: relative; z-index: 1; }

        /* ستايل السايدبار الجانبي */
        .sidebar { width: 280px; background-color: var(--bg-secondary); padding: 30px 20px; display: flex; flex-direction: column; justify-content: space-between; border-left: 1px solid var(--border-color); }
        .logo-area { font-size: 26px; font-weight: 900; letter-spacing: 1px; margin-bottom: 30px; font-family: 'Orbitron', sans-serif; text-align: right; }
        .logo-area span { color: #3b82f6; text-shadow: 0 0 15px rgba(59, 130, 246, 0.8); }
        .menu-list { list-style: none; }
        .menu-item { padding: 12px 14px; border-radius: 10px; margin-bottom: 8px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 14px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.3s; }
        .menu-item.active, .menu-item:hover { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; box-shadow: 0 4px 25px rgba(29, 78, 216, 0.4); }

        /* المحتوى الرئيسي */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .top-header h1 { font-size: 28px; font-weight: 900; background: linear-gradient(to left, #ffffff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        .settings-grid { display: grid; grid-template-columns: 1.2fr 1.8fr; gap: 30px; margin-bottom: 40px; }
        .section-box { background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .section-box h3 { font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #fff; display: flex; align-items: center; gap: 10px; }
        .section-box h3 i { color: var(--accent-blue); }

        /* التنسيق الخاص بالنماذج والمداخل */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; color: #cbd5e1; font-weight: 600; margin-bottom: 8px; }
        .form-control { width: 100%; background-color: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 16px; color: #fff; font-size: 14px; outline: none; transition: all 0.3s; }
        .form-control:focus { border-color: var(--accent-blue); box-shadow: 0 0 10px var(--accent-glow); }
        select.form-control { cursor: pointer; color: #cbd5e1; }

        .btn-submit { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; border: none; padding: 14px 20px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2); transition: all 0.3s; }
        .btn-submit:hover { box-shadow: 0 0 20px var(--accent-glow); transform: translateY(-2px); }

        /* جداول العرض المتقاطعة */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: right; }
        th { color: var(--text-muted); font-weight: 700; font-size: 13px; padding: 14px; border-bottom: 1px solid var(--border-color); }
        td { padding: 16px 14px; border-bottom: 1px solid var(--border-color); font-size: 14px; color: #cbd5e1; }
        
        .role-tag { font-size: 11px; background: rgba(139, 92, 246, 0.15); color: #c084fc; border: 1px solid rgba(139, 92, 246, 0.3); padding: 4px 8px; border-radius: 6px; font-weight: 700; }
        .status-badge { font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; }
        .status-badge::before { content: '•'; font-size: 16px; }
        .status-Online { color: #10b981; } .status-Away { color: #f59e0b; } .status-Offline { color: #64748b; }

        .btn-delete-action { background: transparent; border: none; color: #f43f5e; cursor: pointer; font-size: 14px; transition: color 0.2s; }
        .btn-delete-action:hover { color: #ef4444; }

        /* التنبيهات */
        .alert-panel { padding: 12px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 600; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-error { background: rgba(244, 63, 94, 0.1); border: 1px solid #f43f5e; color: #f43f5e; }
    </style>
</head>
<body>

    <canvas id="worldBg"></canvas>

    <!-- الهيكل الجانبي (Sidebar) -->
    <div class="sidebar">
        <div style="display: flex; flex-direction: column;">
            <div class="logo-area">K<span>·NEWS</span></div>
            <ul class="menu-list">
                <li><a href="dashboard.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i> لوحة التحكم</a></li>
                <li><a href="add-news.php" class="menu-item"><i class="fa-solid fa-square-plus"></i> إضافة خبر</a></li>
                <li><a href="manage-news.php" class="menu-item"><i class="fa-solid fa-newspaper"></i> الأخبار</a></li>
                <li><a href="manage-employees.php" class="menu-item"><i class="fa-solid fa-users"></i> الموظفين</a></li>
                <li><a href="settings.php" class="menu-item active"><i class="fa-solid fa-sliders"></i> الإعدادات للباك اند</a></li>
            </ul>
        </div>
    </div>

    <!-- المحتوى المركزي الفاخر -->
    <div class="main-content">
        <div class="top-header">
            <div>
                <h1>لوحة الإعدادات وتوزيع الامتيازات</h1>
                <p style="color: var(--text-muted); font-size:13px; margin-top:4px;">توليد بوابات التوثيق وتعيين الهويات الرقمية لفرق العمل والتحرير</p>
            </div>
        </div>

        <!-- نظام عرض رسائل الإشعار الفوري -->
        <?php if (!empty($message)): ?>
            <div class="alert-panel alert-<?php echo $messageType; ?>">
                <i class="fa-solid <?php echo $messageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- كارت إنشاء موظف جديد -->
            <div class="section-box">
                <h3><i class="fa-solid fa-user-plus"></i> إنشاء مصفوفة حساب موظف</h3>
                <form action="settings.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="register_employee">
                    
                    <div class="form-group">
                        <label>اسم المستخدم (يوزر الحساب)</label>
                        <input type="text" name="username" class="form-control" placeholder="مثال: Kareem_Editor" required>
                    </div>
                    
                    <div class="form-group">
                        <label>البريد الإلكتروني المهني</label>
                        <input type="email" name="email" class="form-control" placeholder="name@knews.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>كلمة المرور (باسورد آمن)</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    
                    <div class="form-group">
                        <label>الوظيفة / الصلاحية البنائية</label>
                        <select name="role_id" class="form-control" required>
                            <option value="">اختر الرتبة والامتياز...</option>
                            <?php foreach($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-shield-halved"></i> بث الحساب وتفعيل الصلاحية
                    </button>
                </form>
            </div>

            <!-- كارت مراقبة وإدارة فريق الموظفين الفعليين -->
            <div class="section-box">
                <h3><i class="fa-solid fa-network-wired"></i> الهيكل النشط للمحررين ومسؤولي البث</h3>
                <table>
                    <thead>
                        <tr>
                            <th>الموظف</th>
                            <th>الوظيفة المعينة</th>
                            <th>الحالة</th>
                            <th>الأداء</th>
                            <th>تجاوز</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($employees as $emp): ?>
                            <tr>
                                <td>
                                    <span style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($emp['username']); ?></span>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top:2px;"><?php echo htmlspecialchars($emp['email']); ?></div>
                                </td>
                                <td><span class="role-tag"><?php echo htmlspecialchars($emp['role_name'] ?? 'محرر مشترك'); ?></span></td>
                                <td><span class="status-badge status-<?php echo $emp['status']; ?>"><?php echo $emp['status'] === 'Online' ? 'نشط' : 'غير متصل'; ?></span></td>
                                <td style="color: #10b981; font-weight: 700;"><?php echo $emp['performance']; ?>%</td>
                                <td>
                                    <?php if($emp['id'] > 1): ?>
                                        <form action="settings.php" method="POST" onsubmit="return confirm('هل أنت متأكد من سحب صلاحيات هذا الحساب وحذفه نهائياً؟');">
                                            <input type="hidden" name="action" value="delete_employee">
                                            <input type="hidden" name="user_id" value="<?php echo $emp['id']; ?>">
                                            <button type="submit" class="btn-delete-action"><i class="fa-solid fa-user-slash"></i></button>
                                        </form>
                                    <?php else: ?>
                                        <i class="fa-solid fa-lock" style="color: var(--text-muted); opacity: 0.5;"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- تأثير جسيمات الفضاء الخلفية المتناسبة مع اللوحة -->
    <script>
        const canvas = document.getElementById('worldBg'); const ctxBg = canvas.getContext('2d');
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        const meteors = [];
        for (let i = 0; i < 20; i++) { meteors.push({ x: Math.random() * canvas.width, y: Math.random() * canvas.height, speed: 0.5 + Math.random() * 1.5, radius: 0.5 + Math.random() * 1 }); }
        function drawMeteors() {
            ctxBg.clearRect(0, 0, canvas.width, canvas.height); ctxBg.fillStyle = '#ffffff';
            meteors.forEach(m => {
                ctxBg.beginPath(); ctxBg.globalAlpha = 0.3; ctxBg.arc(m.x, m.y, m.radius, 0, Math.PI * 2); ctxBg.fill();
                m.y -= m.speed; if (m.y < -10) { m.y = canvas.height + 10; m.x = Math.random() * canvas.width; }
            });
            requestAnimationFrame(drawMeteors);
        }
        drawMeteors();
    </script>
</body>
</html>