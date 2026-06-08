<?php
// registered-customers.php - لوحة مراقبة وإدارة العملاء والزوار المسجلين في النواة السحابية لـ K-NEWS
require_once 'db.php';

$message = "";
$messageType = "";

// ⚙️ [ميزة مضافة] معالجة تغيير حالة العميل حياً (تعديل الحالة من قاعدة البيانات)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $c_id = intval($_POST['user_id'] ?? 0);
    $c_status = $_POST['new_status'];
    
    if ($c_id > 1 && in_array($c_status, ['Online', 'Away', 'Offline'])) {
        $stmtStatus = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        if ($stmtStatus->execute([$c_status, $c_id])) {
            $message = "تم تحديث الحالة التشغيلية للعميل بنجاح.";
            $messageType = "success";
        }
    }
}

// 🗑️ [ميزة مضافة] معالجة حذف وإقصاء حساب عميل نهائياً من الشبكة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_customer') {
    $customer_id = intval($_POST['id'] ?? 0);
    if ($customer_id > 1) { // حماية حساب السوبر آدمن من أي تجاوز
        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmtDel->execute([$customer_id])) {
            $message = "تم طرد الحساب وإلغاء تسجيل العميل من خادم النواة فوراً.";
            $messageType = "success";
        }
    }
}

// 📊 استعلامات ذكية لاستخراج الإحصائيات العامة الفورية للعملاء
$totalClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id != 1")->fetchColumn();
$activeClients = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id != 1 AND status = 'Online'")->fetchColumn();
$avgPerformance = $pdo->query("SELECT IFNULL(AVG(performance), 0) FROM users WHERE role_id != 1")->fetchColumn();

// جلب كافة بيانات العملاء مع فرزهم ديناميكياً (الأحدث أولاً)
$queryStr = "SELECT u.*, IFNULL(r.name, 'عميل منخرط') as role_name 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             WHERE u.role_id != 1 
             ORDER BY u.id DESC";
$customers = $pdo->query($queryStr)->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl" id="htmlBlock">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS | إدارة العملاء والشبكة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-primary: #04060a; --bg-secondary: #090d16; --bg-input: #04060a;
            --border-color: #131a2a; --accent-blue: #3b82f6; --accent-purple: #8b5cf6;
            --text-main: #ffffff; --text-muted: #64748b; --accent-glow: rgba(59, 130, 246, 0.35);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: var(--bg-primary); color: var(--text-main); display: flex; min-height: 100vh; font-family: 'Cairo', sans-serif; overflow-x: hidden; }
        
        #worldBg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.3; }
        .sidebar, .main-content { position: relative; z-index: 1; }

        /* ستايل الجناح الجانبي (Sidebar) */
        .sidebar { width: 280px; background-color: var(--bg-secondary); padding: 30px 20px; display: flex; flex-direction: column; justify-content: space-between; border-left: 1px solid var(--border-color); }
        .logo-area { font-size: 26px; font-weight: 900; letter-spacing: 1px; margin-bottom: 30px; font-family: 'Orbitron', sans-serif; text-align: right; }
        .logo-area span { color: #3b82f6; text-shadow: 0 0 15px rgba(59, 130, 246, 0.8); }
        .menu-list { list-style: none; }
        .menu-item { padding: 12px 14px; border-radius: 10px; margin-bottom: 8px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 14px; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.3s; }
        .menu-item.active, .menu-item:hover { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; box-shadow: 0 4px 25px rgba(29, 78, 216, 0.4); }

        /* منطقة العمل والمحتوى */
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; gap: 20px; flex-wrap: wrap; }
        .top-header h1 { font-size: 28px; font-weight: 900; background: linear-gradient(to left, #ffffff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* محرك الفلترة والبحث الفوري المطور */
        .search-wrapper-ai { position: relative; width: 300px; }
        .search-wrapper-ai input { width: 100%; background: var(--bg-secondary); border: 1px solid var(--border-color); padding: 12px 16px 12px 42px; border-radius: 12px; color: #fff; outline: none; font-size: 13.5px; transition: all 0.3s; }
        .search-wrapper-ai input:focus { border-color: var(--accent-blue); box-shadow: 0 0 15px var(--accent-glow); }
        .search-wrapper-ai i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }

        /* كروت لوحة المؤشرات */
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 35px; }
        .metric-card { background: var(--bg-secondary); border: 1px solid var(--border-color); padding: 22px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; }
        .metric-card h4 { font-size: 14px; color: var(--text-muted); margin-bottom: 8px; }
        .metric-card .value { font-size: 30px; font-weight: 700; font-family: 'Orbitron', sans-serif; }
        .metric-icon { width: 44px; height: 44px; border-radius: 10px; background: rgba(59, 130, 246, 0.08); display: flex; align-items: center; justify-content: center; color: var(--accent-blue); font-size: 18px; }
        
        /* شريط التزيين الذكي للكروت */
        .bottom-border-glow { position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--accent-blue), var(--accent-purple)); }

        /* لوحة العرض الجدولية الهيكلية */
        .section-box { background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .box-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; text-align: right; }
        th { color: var(--text-muted); font-weight: 700; font-size: 13px; padding: 16px; border-bottom: 1px solid var(--border-color); }
        td { padding: 18px 16px; border-bottom: 1px solid var(--border-color); font-size: 14.5px; color: #cbd5e1; }
        .tr-row { transition: all 0.2s; }
        .tr-row:hover { background: rgba(255, 255, 255, 0.01); }

        /* شارات الحالة وتغيير الهوية */
        .client-avatar { width: 38px; height: 38px; border-radius: 50%; background: #131a2a; border: 1px solid var(--accent-blue); font-family: 'Orbitron', sans-serif; font-weight: 700; font-size: 12px; display: flex; align-items: center; justify-content: center; color: #fff; }
        .status-select { background: #04060a; border: 1px solid var(--border-color); color: #cbd5e1; padding: 5px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; outline: none; cursor: pointer; }
        .status-select:focus { border-color: var(--accent-blue); }

        .badge-status { font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        .badge-status::before { content: '•'; font-size: 16px; }
        .badge-Online { color: #10b981; } .badge-Away { color: #f59e0b; } .badge-Offline { color: #64748b; }

        .btn-delete { background: transparent; border: none; color: #f43f5e; cursor: pointer; font-size: 14px; transition: transform 0.2s; }
        .btn-delete:hover { transform: scale(1.15); color: #ef4444; }

        .alert-panel { padding: 14px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
    </style>
</head>
<body>

    <canvas id="worldBg"></canvas>

    <div class="sidebar">
        <div style="display: flex; flex-direction: column;">
            <div class="logo-area">K<span>·NEWS</span></div>
            <ul class="menu-list">
                <li><a href="dashboard.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i> لوحة التحكم</a></li>
                <li><a href="add-news.php" class="menu-item"><i class="fa-solid fa-square-plus"></i> إضافة خبر</a></li>
                <li><a href="manage-news.php" class="menu-item"><i class="fa-solid fa-newspaper"></i> الأخبار</a></li>
                <li><a href="registered-customers.php" class="menu-item active"><i class="fa-solid fa-address-book"></i> العملاء المسجلين</a></li>
                <li><a href="settings.php" class="menu-item"><i class="fa-solid fa-sliders"></i> الإعدادات</a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h1>قاعدة بيانات العملاء والمنخرطين</h1>
                <p style="color: var(--text-muted); font-size:13px; margin-top:4px;">مراقبة حركات العبور، فرز الهويات التفاعلية، وإدارة تفاعلات الحسابات</p>
            </div>
            
            <div class="search-wrapper-ai">
                <input type="text" id="customerSearch" placeholder="ابحث باسم العميل أو بريده الإلكتروني..." oninput="pipelineCustomerSearch(this.value)">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert-panel alert-<?php echo $messageType; ?>">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="analytics-grid">
            <div class="metric-card">
                <div>
                    <h4>إجمالي العملاء والزوار</h4>
                    <div class="value"><?php echo number_format($totalClients); ?></div>
                </div>
                <div class="metric-icon"><i class="fa-solid fa-users"></i></div>
                <div class="bottom-border-glow"></div>
            </div>
            <div class="metric-card">
                <div>
                    <h4>المنخرطين المتصلين حياً</h4>
                    <div class="value" style="color:#10b981;"><?php echo number_format($activeClients); ?></div>
                </div>
                <div class="metric-icon" style="color:#10b981; background:rgba(16,185,129,0.08);"><i class="fa-solid fa-signal"></i></div>
                <div class="bottom-border-glow" style="background:#10b981;"></div>
            </div>
            <div class="metric-card">
                <div>
                    <h4>متوسط معدل التفاعل</h4>
                    <div class="value" style="color:#a855f7;"><?php echo round($avgPerformance, 1); ?>%</div>
                </div>
                <div class="metric-icon" style="color:#a855f7; background:rgba(168,85,247,0.08);"><i class="fa-solid fa-bolt"></i></div>
                <div class="bottom-border-glow" style="background:#a855f7;"></div>
            </div>
        </div>

        <div class="section-box">
            <div class="box-header">
                <h3 style="font-size: 16px; font-weight:700;"><i class="fa-solid fa-database" style="color:var(--accent-blue); margin-left: 8px;"></i>السجلات المكتشفة بالنواة</h3>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>العميل المنخرط</th>
                        <th>البريد السحابي</th>
                        <th>تاريخ التسجيل</th>
                        <th>الحالة التشغيلية</th>
                        <th>مستوى التفاعل</th>
                        <th>إجراء طرد</th>
                    </tr>
                </thead>
                <tbody id="customerTableBody">
                    <?php if(empty($customers)): ?>
                        <tr id="noResultsRow"><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">لا يوجد عملاء مسجلين في النظام حالياً.</td></tr>
                    <?php else: foreach($customers as $user): 
                        // توليد الحرف الأول من يوزر العميل للافتار الجمالي
                        $firstLetter = strtoupper(substr($user['username'], 0, 1));
                    ?>
                        <tr class="tr-row">
                            <td style="display: flex; align-items: center; gap: 12px;">
                                <div class="client-avatar"><?php echo $firstLetter; ?></div>
                                <span class="customer-name" style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($user['username']); ?></span>
                            </td>
                            <td class="customer-email" style="direction: ltr; text-align: right; font-size:13.5px; color:#94a3b8;"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td style="color: var(--text-muted); font-size:13px; font-family:'Orbitron', sans-serif;"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            <td>
                                <form action="registered-customers.php" method="POST" style="display: inline-flex; align-items: center; gap:8px;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <span class="badge-status badge-<?php echo $user['status']; ?>"></span>
                                    <select name="new_status" class="status-select" onchange="this.form.submit()">
                                        <option value="Online" <?php echo $user['status'] == 'Online' ? 'selected' : ''; ?>>نشط</option>
                                        <option value="Away" <?php echo $user['status'] == 'Away' ? 'selected' : ''; ?>>بالخارج</option>
                                        <option value="Offline" <?php echo $user['status'] == 'Offline' ? 'selected' : ''; ?>>غير متصل</option>
                                    </select>
                                </form>
                            </td>
                            <td style="font-weight:700; color:var(--accent-blue); font-family:'Orbitron', sans-serif;"><?php echo $user['performance']; ?>%</td>
                            <td>
                                <form action="registered-customers.php" method="POST" onsubmit="return confirm('هل أنت متأكد من رغبتك في سحب صلاحيات هذا العميل وحذفه نهائياً؟');">
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn-delete"><i class="fa-solid fa-user-xmark"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function pipelineCustomerSearch(query) {
            const cleanQuery = query.toLowerCase().trim();
            const rows = document.querySelectorAll('#customerTableBody .tr-row');
            let matchedCount = 0;

            rows.forEach(row => {
                const name = row.querySelector('.customer-name').textContent.toLowerCase();
                const email = row.querySelector('.customer-email').textContent.toLowerCase();

                if (name.includes(cleanQuery) || email.includes(cleanQuery)) {
                    row.style.display = '';
                    matchedCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // إظهار سطر "لا توجد نتائج" إذا لم يتطابق البحث مع أي عميل
            let noResultsRow = document.getElementById('searchNoResults');
            if (matchedCount === 0 && cleanQuery !== '') {
                if (!noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'searchNoResults';
                    noResultsRow.innerHTML = `<td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">لا توجد سجلات مطابقة لهذا البحث التفتيشي.</td>`;
                    document.getElementById('customerTableBody').appendChild(noResultsRow);
                }
            } else if (noResultsRow) {
                noResultsRow.remove();
            }
        }

        // بناء جسيمات النيازك السحابية الفضائية للخلفية التكنولوجية الفخمة
        const canvas = document.getElementById('worldBg'); const ctxBg = canvas.getContext('2d');
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas(); window.addEventListener('resize', resizeCanvas);
        const meteors = [];
        for (let i = 0; i < 20; i++) { meteors.push({ x: Math.random() * canvas.width, y: Math.random() * canvas.height, speed: 0.6 + Math.random() * 1.2, radius: 0.5 + Math.random() * 1 }); }
        function drawMeteors() {
            ctxBg.clearRect(0, 0, canvas.width, canvas.height); ctxBg.fillStyle = '#ffffff';
            meteors.forEach(m => {
                ctxBg.beginPath(); ctxBg.globalAlpha = 0.25; ctxBg.arc(m.x, m.y, m.radius, 0, Math.PI * 2); ctxBg.fill();
                m.y -= m.speed; if (m.y < -10) { m.y = canvas.height + 10; m.x = Math.random() * canvas.width; }
            });
            requestAnimationFrame(drawMeteors);
        }
        drawMeteors();
    </script>
</body>
</html>