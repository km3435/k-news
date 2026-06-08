<?php
// admin_slider.php - النسخة النهائية المتوافقة 100% مع بنية الـ phpMyAdmin لديك
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
} catch (\PDOException $e) {
     die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

$message = "";

// 2. معالجة الإرسال (إضافة خبر جديد إلى السلايدر مع تمرير جميع الحقول المطلوبة للداتابيز)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $badge       = $_POST['badge'];
    $title       = $_POST['title'];
    $description = $_POST['description'];
    $image_url   = $_POST['image_url'];
    $action_url  = !empty($_POST['action_url']) ? $_POST['action_url'] : '#';
    $is_active   = 1; // تمرير القيمة 1 ليصبح الخبر نشطاً تلقائياً ولا يسبب خطأ قيد التحقق

    if (!empty($title) && !empty($description) && !empty($image_url)) {
        try {
            // الاستعلام المطابق تماماً لبنية جدول phpMyAdmin الخاص بك
            $stmt = $pdo->prepare('INSERT INTO hero_sliders (badge, title, description, image_url, action_url, is_active) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$badge, $title, $description, $image_url, $action_url, $is_active]);
            $message = "<div class='alert success'><i class='fa-solid fa-circle-check'></i> تم إضافة الخبر إلى السلايدر بنجاح!</div>";
        } catch (\PDOException $e) {
            $message = "<div class='alert danger'>خطأ برمجائي أثناء الإدخال: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert danger'><i class='fa-solid fa-triangle-exclamation'></i> يرجى ملء جميع الحقول الأساسية.</div>";
    }
}

// 3. معالجة الحذف
if (isset($_GET['delete'])) {
    $id_to_delete = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare('DELETE FROM hero_sliders WHERE id = ?');
        $stmt->execute([$id_to_delete]);
        header("Location: admin_slider.php"); 
        exit;
    } catch (\PDOException $e) {
        $message = "<div class='alert danger'>فشل الحذف: " . $e->getMessage() . "</div>";
    }
}

// 4. جلب كافة السلايدات المعروضة حالياً لعرضها في الجدول
$all_sliders = [];
try {
    $stmt = $pdo->query('SELECT * FROM hero_sliders ORDER BY id DESC');
    $all_sliders = $stmt->fetchAll();
} catch (\PDOException $e) {
    $message = "<div class='alert danger'>خطأ في جلب البيانات: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم | إدارة السلايدر الكبير</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-admin: #f8fafc;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Cairo', sans-serif; }
        body { background-color: var(--bg-admin); color: var(--text-dark); display: flex; }

        .sidebar { width: 260px; height: 100vh; background-color: #0a0f1d; color: #fff; padding: 25px 15px; position: fixed; right: 0; top: 0; }
        .sidebar .logo { font-size: 22px; font-weight: 900; margin-bottom: 35px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; }
        .sidebar .logo span { color: var(--primary); }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li a { display: flex; align-items: center; gap: 12px; color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 14px; margin-bottom: 5px; transition: all 0.2s; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background-color: rgba(255,255,255,0.05); color: #fff; }
        .sidebar-menu li a.active { border-right: 4px solid var(--primary); }

        .main-content { margin-right: 260px; width: calc(100% - 260px); padding: 40px; }
        .page-title { font-size: 24px; font-weight: 800; margin-bottom: 5px; }
        .page-subtitle { color: var(--text-muted); font-size: 13px; margin-bottom: 30px; }

        .admin-card { background: #fff; border-radius: 12px; padding: 25px; border: 1px solid var(--border-color); box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 30px; }
        .card-title { font-size: 16px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .full-width { grid-column: span 2; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 13px; font-weight: 700; color: #475569; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 13px; outline: none; background-color: #f8fafc; transition: border-color 0.2s; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--primary); background-color: #fff; }
        
        .btn-submit { background-color: var(--primary); color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; margin-top: 15px; }
        .btn-submit:hover { background-color: var(--primary-hover); }

        .alert { padding: 12px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        .alert.success { background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert.danger { background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: right; }
        .data-table th { background-color: #f1f5f9; padding: 12px 15px; font-size: 13px; font-weight: 700; color: #475569; border-bottom: 2px solid var(--border-color); }
        .data-table td { padding: 15px; font-size: 13px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        .table-thumb { width: 70px; height: 45px; border-radius: 6px; object-fit: cover; }
        
        .badge-ui { font-size: 11px; padding: 3px 8px; border-radius: 4px; font-weight: 700; color: #fff; }
        .btn-action { text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .btn-delete { background-color: #fee2e2; color: #dc2626; }
        .btn-delete:hover { background-color: #fecaca; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="logo">K<span>·NEWS الباك إند</span></div>
        <ul class="sidebar-menu">
            <li><a href="admin_slider.php" class="active"><i class="fa-solid fa-sliders"></i> إدارة السلايدر الكبير</a></li>
            <li><a href="dashboard.php"><i class="fa-solid fa-layer-group"></i> التحكم بالأقسام</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title">إدارة السلايدر الكبير المتنقل</h1>
        <p class="page-subtitle">يمكنك من هنا إضافة أخبار جديدة للسلايدر الرئيسي وحذفها ومراقبة حالتها.</p>

        <?php echo $message; ?>

        <div class="admin-card">
            <div class="card-title"><i class="fa-solid fa-plus-circle" style="color:var(--primary);"></i> إضافة خبر عاجل جديد للسلايدر</div>
            <form action="admin_slider.php" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>شارة النشر (Tag/Badge)</label>
                        <select name="badge">
                            <option value="أخبار عاجلة">أخبار عاجلة</option>
                            <option value="اقتصاد">اقتصاد</option>
                            <option value="سياسة">سياسة</option>
                            <option value="تقنية">تقنية</option>
                            <option value="رياضة">رياضة</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>عنوان الخبر الرئيسي</label>
                        <input type="text" name="title" placeholder="مثال: رؤية 2030 تحقق إنجازات رقمية جديدة..." required>
                    </div>

                    <div class="form-group full-width">
                        <label>وصف الخبر (المتن القصير المظهر أسفل العنوان)</label>
                        <textarea name="description" rows="3" placeholder="اكتب تفاصيل موجزة ومختصرة تجذب القارئ..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label>رابط الصورة (Image URL)</label>
                        <input type="url" name="image_url" placeholder="ضع رابط الصورة المباشر هنا" required>
                    </div>

                    <div class="form-group">
                        <label>رابط التوجيه عند الضغط على "اقرأ المزيد" (اختياري)</label>
                        <input type="text" name="action_url" placeholder="مثال: article.php?id=50" value="#">
                    </div>
                </div>

                <button type="submit" class="btn-submit"><i class="fa-solid fa-floppy-disk"></i> حفظ ونشر بالسلايدر</button>
            </form>
        </div>

        <div class="admin-card">
            <div class="card-title"><i class="fa-solid fa-list-check" style="color:var(--primary);"></i> السلايدات الحالية بالموقع (المنشورة)</div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الصورة</th>
                        <th>الشارة</th>
                        <th>العنوان الرئيسي</th>
                        <th>الحالة</th>
                        <th>تاريخ الإضافة</th>
                        <th>التحكم والإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($all_sliders)): foreach($all_sliders as $row): ?>
                    <tr>
                        <td><img src="<?php echo htmlspecialchars($row['image_url'] ?? ''); ?>" class="table-thumb" alt="thumb"></td>
                        <td><span class="badge-ui" style="background:#ef4444;"><?php echo htmlspecialchars($row['badge'] ?? 'عام'); ?></span></td>
                        <td style="font-weight:700; max-width: 320px;"><?php echo htmlspecialchars($row['title'] ?? ''); ?></td>
                        <td>
                            <span class="badge-ui" style="background:#10b981;">
                                <?php echo (isset($row['is_active']) && $row['is_active'] == 1) ? 'نشط' : 'معطل'; ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted); font-size: 12px;"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                        <td>
                            <a href="admin_slider.php?delete=<?php echo $row['id']; ?>" class="btn-action btn-delete" onclick="return confirm('هل أنت متأكد من رغبتك في حذف هذا الخبر نهائياً من السلايدر؟');">
                                <i class="fa-solid fa-trash-can"></i> حذف
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color: var(--text-muted); padding:30px;">لا توجد أي أخبار مضافة في السلايدر حالياً. قم بإضافة أول خبر بالنموذج أعلاه!</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>