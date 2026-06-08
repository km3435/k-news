<?php
// manage-employees.php - النظام المتكامل لإدارة الكوادر والقيادات الإقليمية والتنفيذية
require_once 'db.php';

$message = '';
$alert_class = '';

// ==========================================
// أولاً: معالجة عمليات قسم المدراء (Managers Crud)
// ==========================================

// 1. إضافة مدير جديد مع (الصورة والراتب والرتب المحدثة)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_manager') {
    $m_name = trim($_POST['manager_name'] ?? '');
    $m_title = trim($_POST['manager_title'] ?? '');
    $m_type = $_POST['manager_type'] ?? 'general';
    $m_salary = floatval($_POST['manager_salary'] ?? 0);
    $photo_path = '';

    // معالجة رفع صورة المدير
    if (isset($_FILES['manager_photo']) && $_FILES['manager_photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['manager_photo']['tmp_name'];
        $photo_path = 'uploads/managers/' . time() . '_' . $_FILES['manager_photo']['name'];
        if (!is_dir('uploads/managers/')) { mkdir('uploads/managers/', 0755, true); }
        move_uploaded_file($file_tmp, $photo_path);
    }

    if (!empty($m_name) && !empty($m_title)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO managers (name, title, type, photo, salary) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$m_name, $m_title, $m_type, $photo_path, $m_salary]);
            $message = "تم تسجيل القائد الإداري الجديد وحفظ ملفه المالي وصورته بنجاح.";
            $alert_class = "alert-success";
        } catch (PDOException $e) {
            $message = "خطأ أثناء تسجيل المدير: " . $e->getMessage();
            $alert_class = "alert-error";
        }
    }
}

// 2. تعديل بيانات مدير (بما فيها الصورة والراتب)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_manager') {
    $m_id = intval($_POST['manager_id'] ?? 0);
    $m_name = trim($_POST['manager_name'] ?? '');
    $m_title = trim($_POST['manager_title'] ?? '');
    $m_type = $_POST['manager_type'] ?? 'general';
    $m_salary = floatval($_POST['manager_salary'] ?? 0);

    if ($m_id > 0 && !empty($m_name)) {
        try {
            if (isset($_FILES['manager_photo']) && $_FILES['manager_photo']['error'] === UPLOAD_ERR_OK) {
                // رفع الصورة الجديدة وحذف القديمة
                $photo_path = 'uploads/managers/' . time() . '_' . $_FILES['manager_photo']['name'];
                move_uploaded_file($_FILES['manager_photo']['tmp_name'], $photo_path);
                
                $old_stmt = $pdo->prepare("SELECT photo FROM managers WHERE id = ?"); $old_stmt->execute([$m_id]);
                $old_img = $old_stmt->fetchColumn(); if(!empty($old_img) && file_exists($old_img)) { @unlink($old_img); }

                $stmt = $pdo->prepare("UPDATE managers SET name = ?, title = ?, type = ?, photo = ?, salary = ? WHERE id = ?");
                $stmt->execute([$m_name, $m_title, $m_type, $photo_path, $m_salary, $m_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE managers SET name = ?, title = ?, type = ?, salary = ? WHERE id = ?");
                $stmt->execute([$m_name, $m_title, $m_type, $m_salary, $m_id]);
            }
            $message = "تم تحديث مصفوفة بيانات القائد الإداري والراتب بنجاح.";
            $alert_class = "alert-success";
        } catch (PDOException $e) { $message = "فشل تحديث بيانات المدير: " . $e->getMessage(); $alert_class = "alert-error"; }
    }
}

// 3. حذف مدير مع مسح صورته
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_manager') {
    $m_id = intval($_POST['id'] ?? 0);
    if ($m_id > 0) {
        try {
            $img_stmt = $pdo->prepare("SELECT photo FROM managers WHERE id = ?"); $img_stmt->execute([$m_id]);
            $photo = $img_stmt->fetchColumn(); if(!empty($photo) && file_exists($photo)) { @unlink($photo); }
            
            $pdo->prepare("DELETE FROM managers WHERE id = ?")->execute([$m_id]);
            $message = "تم حذف المدير وإلغاء صلاحياته الإدارية والمالية من السيستم.";
            $alert_class = "alert-success";
        } catch (PDOException $e) { $message = "فشل حذف المدير: " . $e->getMessage(); $alert_class = "alert-error"; }
    }
}


// ==========================================
// ثانياً: معالجة عمليات قسم الموظفين (Employees Crud)
// ==========================================

// 1. إضافة موظف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_employee') {
    $name = trim($_POST['name'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    $job_title = trim($_POST['job_title'] ?? '');
    $hire_date = $_POST['hire_date'] ?? date('Y-m-d');
    $base_salary = floatval($_POST['base_salary'] ?? 0);
    $allowances = floatval($_POST['allowances'] ?? 0);
    $insurance_num = trim($_POST['insurance_num'] ?? '');
    
    $emp_id_code = 'EMP-' . date('Y') . '-' . rand(1000, 9999);
    $photo_path = '';

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_path = 'uploads/employees/' . time() . '_' . $_FILES['photo']['name'];
        if (!is_dir('uploads/employees/')) { mkdir('uploads/employees/', 0755, true); }
        move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
    }

    if (!empty($name) && !empty($phone)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO employees (emp_id_code, name, national_id, email, phone, department_id, manager_id, job_title, hire_date, base_salary, allowances, insurance_num, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$emp_id_code, $name, $national_id, $email, $phone, $department_id, $manager_id, $job_title, $hire_date, $base_salary, $allowances, $insurance_num, $photo_path]);
            $message = "تم توليد الـ ID وإصدار قرار التعيين تحت القيادة المحددة.";
            $alert_class = "alert-success";
        } catch (PDOException $e) { $message = "فشل الإدخال: " . $e->getMessage(); $alert_class = "alert-error"; }
    }
}

// 2. تعديل موظف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_employee') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department_id = intval($_POST['department_id'] ?? 0);
    $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    $job_title = trim($_POST['job_title'] ?? '');
    $base_salary = floatval($_POST['base_salary'] ?? 0);
    $allowances = floatval($_POST['allowances'] ?? 0);
    $insurance_num = trim($_POST['insurance_num'] ?? '');

    if ($id > 0 && !empty($name)) {
        try {
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $photo_path = 'uploads/employees/' . time() . '_' . $_FILES['photo']['name'];
                move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
                $update_stmt = $pdo->prepare("UPDATE employees SET name=?, national_id=?, email=?, phone=?, department_id=?, manager_id=?, job_title=?, base_salary=?, allowances=?, insurance_num=?, photo=? WHERE id=?");
                $update_stmt->execute([$name, $national_id, $email, $phone, $department_id, $manager_id, $job_title, $base_salary, $allowances, $insurance_num, $photo_path, $id]);
            } else {
                $update_stmt = $pdo->prepare("UPDATE employees SET name=?, national_id=?, email=?, phone=?, department_id=?, manager_id=?, job_title=?, base_salary=?, allowances=?, insurance_num=? WHERE id=?");
                $update_stmt->execute([$name, $national_id, $email, $phone, $department_id, $manager_id, $job_title, $base_salary, $allowances, $insurance_num, $id]);
            }
            $message = "تم تعديل وحفظ بيانات الكادر بنجاح.";
            $alert_class = "alert-success";
        } catch (PDOException $e) { $message = "خطأ أثناء التحديث: " . $e->getMessage(); $alert_class = "alert-error"; }
    }
}

// 3. حذف موظف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_employee') {
    $emp_id = intval($_POST['id'] ?? 0);
    if ($emp_id > 0) {
        $img_stmt = $pdo->prepare("SELECT photo FROM employees WHERE id = ?"); $img_stmt->execute([$emp_id]);
        $photo = $img_stmt->fetchColumn(); if(!empty($photo) && file_exists($photo)) { @unlink($photo); }
        $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$emp_id]);
        $message = "تم مسح وإعدام ملف الموظف التابع."; $alert_class = "alert-success";
    }
}

// جلب وتوزيع البيانات النهائية للواجهة
try { $departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(); } catch (PDOException $e) { $departments = []; }
try { $managers = $pdo->query("SELECT * FROM managers ORDER BY id DESC")->fetchAll(); } catch (PDOException $e) { $managers = []; }
try {
    $all_employees = $pdo->query("SELECT e.*, d.name as dept_name, m.name as manager_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN managers m ON e.manager_id = m.id ORDER BY e.id DESC")->fetchAll();
    $emp_by_dept = []; foreach ($all_employees as $emp) { $emp_by_dept[$emp['department_id'] ?? 0][] = $emp; }
} catch (PDOException $e) { $all_employees = []; $emp_by_dept = []; }

// دالة لمطابقة مسمى الرتب باللغة العربية
function getManagerTypeName($type) {
    switch ($type) {
        case 'country': return 'مدير دولة';
        case 'region': return 'مدير منطقة إقليمية';
        case 'executive': return 'مدير تنفيذي قطاعي';
        case 'general': return 'مدير عام الكيان';
        default: return 'مدير عام';
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-HQ - مصفوفة التحكم بالقيادات العليا والكوادر</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" crossorigin="anonymous"></script>
    
    <style>
        :root {
            --bg-primary: #02040a; --bg-secondary: #0b111e; --bg-input: rgba(5, 8, 16, 0.7);
            --border-color: #1e293b; --accent-blue: #3b82f6; --accent-purple: #a855f7;
            --accent-glow: rgba(59, 130, 246, 0.4); --text-main: #f8fafc; --text-muted: #64748b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Cairo', sans-serif; }
        body { background-color: var(--bg-primary); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; position: relative; }
        
        #meteorCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.6; }
        .sidebar, .main-content { position: relative; z-index: 1; }

        /* القائمة الجانبية المضيئة */
        .sidebar { width: 290px; background: linear-gradient(180deg, #040712 0%, #090f1d 100%); padding: 35px 24px; display: flex; flex-direction: column; justify-content: space-between; border-left: 1px solid var(--border-color); box-shadow: 5px 0 30px rgba(0,0,0,0.7); }
        .logo-area { font-size: 26px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .logo-area span { color: var(--accent-blue); text-shadow: 0 0 15px var(--accent-glow); }
        
        .icon-orb-container { position: relative; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .icon-loop { position: absolute; width: 100%; height: 100%; border: 2px dashed transparent; border-radius: 50%; animation: orbitClockwise 6s linear infinite; }
        .loop-1 { border-top-color: var(--accent-blue); border-bottom-color: var(--accent-purple); }
        .loop-2 { border-left-color: #10b981; border-right-color: #f59e0b; animation-duration: 4s; animation-direction: reverse; width: 85%; height: 85%; }
        .icon-orb-container i { z-index: 2; font-size: 16px; color: #fff; text-shadow: 0 0 8px rgba(255,255,255,0.6); }
        @keyframes orbitClockwise { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .menu-list { list-style: none; margin-top: 40px; }
        .menu-item { padding: 12px 15px; border-radius: 14px; margin-bottom: 15px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 14px; text-decoration: none; font-size: 15px; font-weight: 600; transition: all 0.3s; }
        .menu-item:hover { color: var(--text-main); background: rgba(255,255,255,0.03); }
        .menu-item.active { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; box-shadow: 0 0 20px var(--accent-glow); }
        .user-profile-footer { display: flex; align-items: center; gap: 14px; padding-top: 25px; border-top: 1px solid var(--border-color); }
        .user-profile-footer img { width: 45px; height: 45px; border-radius: 50%; border: 2px solid var(--accent-purple); }

        .main-content { flex: 1; padding: 45px; overflow-y: auto; }
        .top-header h1 { font-size: 34px; font-weight: 800; background: linear-gradient(135deg, #fff 30%, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* لوحات النيون للنماذج */
        .form-grid-panel { background: rgba(11, 17, 30, 0.45); border: 1px solid var(--border-color); border-radius: 24px; padding: 30px; margin-bottom: 40px; backdrop-filter: blur(16px); box-shadow: 0 20px 40px rgba(0,0,0,0.5); position: relative; }
        .form-grid-panel::after { content: ''; position: absolute; bottom: 0; right: 0; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, var(--accent-blue), var(--accent-purple), transparent); }
        .form-title { font-size: 18px; font-weight: 700; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; color: #fff; }
        
        .inputs-matrix { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; }
        .input-box { display: flex; flex-direction: column; gap: 8px; }
        .input-box label { font-size: 13px; font-weight: 700; color: #94a3b8; }
        .input-box input, .input-box select { background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 12px; padding: 12px 14px; color: #fff; font-size: 14px; outline: none; transition: all 0.3s; }
        .input-box input:focus, .input-box select:focus { border-color: var(--accent-blue); box-shadow: 0 0 15px rgba(59, 130, 246, 0.25); }
        
        .btn-submit { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; border: none; padding: 14px 28px; border-radius: 12px; font-weight: 700; cursor: pointer; margin-top: 20px; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px var(--accent-glow); }

        /* كروت عرض الهوية والمدراء المتقدمة */
        .managers-system-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 20px; margin-top: 20px; }
        .manager-neon-card { background: rgba(15, 23, 42, 0.7); border: 1px solid var(--border-color); border-radius: 20px; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; position: relative; border-right: 4px solid var(--accent-blue); }
        
        /* ألوان حدود حسب الرتبة الإدارية للمدير */
        .manager-neon-card.country { border-right-color: #ef4444; } /* أحمر - مدير دولة */
        .manager-neon-card.region { border-right-color: #f59e0b; }  /* برتقالي - مدير منطقة */
        .manager-neon-card.executive { border-right-color: #10b981; }/* أخضر - تنفيذي */
        .manager-neon-card.general { border-right-color: #a855f7; }  /* بنفسجي - عام */

        .m-avatar-frame { width: 55px; height: 55px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.1); }
        .m-badge-pill { font-size: 10px; padding: 3px 10px; border-radius: 20px; font-weight: 800; background: rgba(255,255,255,0.05); color: #fff; width: max-content; }

        .category-tabs-container { display: flex; gap: 12px; margin-bottom: 35px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
        .tab-btn { background: rgba(11, 17, 30, 0.4); border: 1px solid var(--border-color); color: var(--text-muted); padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all 0.3s; }
        .tab-btn.active { color: #fff; border-color: var(--accent-blue); background: rgba(59, 130, 246, 0.05); }
        .tab-btn .badge { background: #030712; padding: 2px 6px; border-radius: 6px; font-size: 11px; }

        .category-content-panel { display: none; }
        .category-content-panel.active { display: block; }

        .employees-cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 25px; }
        .id-card-wrapper { background: linear-gradient(145deg, rgba(15, 23, 42, 0.85) 0%, rgba(2, 6, 23, 0.98) 100%); border: 1px solid var(--border-color); border-radius: 24px; padding: 25px; display: flex; flex-direction: column; align-items: center; border-top: 4px solid var(--accent-blue); transition: all 0.3s; }
        .id-card-wrapper:hover { transform: translateY(-5px); border-color: var(--accent-purple); box-shadow: 0 20px 40px rgba(139, 92, 246, 0.15); }
        
        .id-photo-frame { width: 100px; height: 100px; border-radius: 50%; padding: 3px; background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple)); margin-bottom: 15px; }
        .id-photo-frame img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; background: #0f172a; }
        .id-name { font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 4px; }
        .id-title { font-size: 13px; color: #93c5fd; background: rgba(59, 130, 246, 0.1); padding: 3px 12px; border-radius: 20px; margin-bottom: 15px; }
        
        .id-details-list { width: 100%; border-top: 1px dashed rgba(30, 41, 59, 0.6); padding-top: 15px; display: flex; flex-direction: column; gap: 8px; margin-bottom: 15px; }
        .id-detail-row { display: flex; justify-content: space-between; font-size: 12px; }
        .id-detail-row .lbl { color: var(--text-muted); }
        .id-detail-row .val { color: #e2e8f0; font-weight: 700; }

        .id-actions-bar { width: 100%; display: flex; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 15px; margin-bottom: 10px; }
        .action-btn { flex: 1; padding: 8px; border-radius: 8px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; color: #fff; transition: all 0.2s; }
        .btn-edit-trigger { background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); }
        .btn-edit-trigger:hover { background: var(--accent-blue); }
        .btn-delete-trigger { background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.3); }
        .btn-delete-trigger:hover { background: #f43f5e; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(2, 4, 10, 0.9); backdrop-filter: blur(10px); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: var(--bg-secondary); border: 1px solid var(--border-color); width: 100%; max-width: 750px; border-radius: 20px; padding: 30px; border-top: 4px solid var(--accent-purple); }
        
        .alert { padding: 15px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-error { background: rgba(244, 63, 94, 0.1); border: 1px solid #f43f5e; color: #f43f5e; }
    </style>
</head>
<body>

    <canvas id="meteorCanvas"></canvas>

    <!-- القائمة الجانبية -->
    <div class="sidebar">
        <div>
            <div class="logo-area">
                <div class="icon-orb-container"><div class="icon-loop loop-1"></div><div class="icon-loop loop-2"></div><i class="fa-solid fa-shield-halved"></i></div>
                <span>K·HQ</span>
            </div>
            <ul class="menu-list">
                <li><a href="dashboard.php" class="menu-item"><div class="icon-orb-container"><div class="icon-loop loop-1"></div><i class="fa-solid fa-chart-pie"></i></div> لوحة التحكم</a></li>
                <li><a href="manage-employees.php" class="menu-item active"><div class="icon-orb-container"><div class="icon-loop loop-1"></div><i class="fa-solid fa-users-gear"></i></div> إدارة الموظفين والمدراء</a></li>
                <li><a href="financial-statements.php" class="menu-item"><div class="icon-orb-container"><div class="icon-loop loop-1"></div><i class="fa-solid fa-wallet"></i></div> الكشوفات المالية</a></li>
            </ul>
        </div>
        <div class="user-profile-footer">
            <img src="https://ui-avatars.com/api/?name=Kareem+HQ&background=3b82f6&color=fff" alt="Admin">
            <div><h4 style="font-size: 13px; font-weight:700;">إدارة العمليات</h4><p style="font-size: 11px; color: var(--text-muted);">مدير المنظومة العليا</p></div>
        </div>
    </div>

    <!-- المحتوى الرئيسي للوحة -->
    <div class="main-content">
        <div class="top-header" style="margin-bottom: 35px;">
            <h1>مركز إدارة القيادات والكوادر المتكامل</h1>
            <p style="color: var(--text-muted); margin-top: 4px;">تتبع الرتب التنفيذية، رواتب القيادات، وربط الموظفين التابعين هيكلياً</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $alert_class; ?>"><i class="fa-solid fa-circle-info"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <!-- ==================== 👑 قسم إدارة القيادات والمدراء المستقل مع الراتب والصورة 👑 ==================== -->
        <div class="form-grid-panel" style="border-right: 3px solid var(--accent-purple);">
            <div class="form-title">
                <div class="icon-orb-container" style="width:36px; height:36px;"><div class="icon-loop loop-1"></div><i class="fa-solid fa-user-tie" style="font-size:12px;"></i></div>
                البوابة القيادية المتقدمة: تسجيل وتعديل حوكمة المدراء والرواتب
            </div>
            
            <form action="manage-employees.php" method="POST" enctype="multipart/form-data" style="margin-bottom: 25px;">
                <input type="hidden" name="action" value="add_manager">
                <div class="inputs-matrix" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                    <div class="input-box"><label>اسم المدير الكامل *</label><input type="text" name="manager_name" required placeholder="مثال: م. كريم عبد العزيز"></div>
                    <div class="input-box"><label>المسمى الوظيفي الخاص *</label><input type="text" name="manager_title" required placeholder="مثال: نائب رئيس مجلس الإدارة"></div>
                    <div class="input-box">
                        <label>الرتبة والمستوى الإداري *</label>
                        <select name="manager_type" required>
                            <option value="general">مدير عام الكيان (General Manager)</option>
                            <option value="executive">مدير تنفيذي قطاعي (Executive Manager)</option>
                            <option value="region">مدير منطقة إقليمية (Region Manager)</option>
                            <option value="country">مدير دولة (Country Manager)</option>
                        </select>
                    </div>
                    <div class="input-box"><label>الراتب المخصص للمدير (EGP) *</label><input type="number" step="0.01" name="manager_salary" required placeholder="0.00"></div>
                    <div class="input-box"><label>الصورة الشخصية للقيادي</label><input type="file" name="manager_photo" accept="image/*"></div>
                </div>
                <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #a855f7, #6366f1);"><i class="fa-solid fa-user-shield"></i> تثبيت المدير واعتماد الراتب في الهيكل</button>
            </form>

            <h3 style="font-size:14px; color:#fff; border-top: 1px solid var(--border-color); padding-top:15px; margin-bottom:12px;">المصفوفة القيادية الحالية:</h3>
            <div class="managers-system-grid">
                <?php if(empty($managers)): echo "<p style='color:var(--text-muted); font-size:12px;'>لا يوجد أي مدراء مسجلين بالسيستم حالياً.</p>"; endif; ?>
                <?php foreach($managers as $m): 
                    $m_avatar = !empty($m['photo']) ? $m['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($m['name']) . '&background=1e1b4b&color=fff';
                ?>
                    <div class="manager-neon-card <?php echo $m['type']; ?>">
                        <div style="display:flex; gap:12px; align-items:center;">
                            <img src="<?php echo $m_avatar; ?>" class="m-avatar-frame" alt="Manager">
                            <div>
                                <h4 style="font-size:14px; color:#fff; margin-bottom:2px;"><?php echo htmlspecialchars($m['name']); ?></h4>
                                <p style="font-size:11px; color:var(--text-muted); margin-bottom:5px;"><?php echo htmlspecialchars($m['title']); ?></p>
                                <span class="m-badge-pill"><?php echo getManagerTypeName($m['type']); ?></span>
                            </div>
                        </div>
                        <div style="margin-top:12px; font-size:12px; display:flex; justify-content:space-between; background:rgba(0,0,0,0.2); padding:6px 10px; border-radius:8px;">
                            <span style="color:var(--text-muted)">الراتب الهيكلي:</span>
                            <span style="color:#10b981; font-weight:700;"><?php echo number_format($m['salary'], 2); ?> EGP</span>
                        </div>
                        <div style="display:flex; gap:8px; margin-top:12px; border-top:1px dashed rgba(255,255,255,0.05); padding-top:10px;">
                            <button class="action-btn btn-edit-trigger" style="padding:4px 8px; font-size:11px;" onclick="openManagerEditModal(<?php echo htmlspecialchars(json_encode($m)); ?>)"><i class="fa-solid fa-pen"></i> تعديل</button>
                            <form action="manage-employees.php" method="POST" onsubmit="return confirm('حذف هذا المدير سيترك موظفيه بلا مسؤول إداري مباشر، هل أنت متأكد؟');">
                                <input type="hidden" name="action" value="delete_manager"><input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                <button type="submit" class="action-btn btn-delete-trigger" style="padding:4px 8px; font-size:11px;"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==================== 👥 قسم إدارة كروت وبطاقات الموظفين 👥 ==================== -->
        <div class="form-grid-panel">
            <div class="form-title">
                <div class="icon-orb-container" style="width:36px; height:36px;"><div class="icon-loop loop-1"></div><i class="fa-solid fa-user-plus" style="font-size:12px;"></i></div>
                إدراج كادر بشري جديد بالمصفوفة
            </div>
            
            <form action="manage-employees.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_employee">
                <div class="inputs-matrix">
                    <div class="input-box"><label>الاسم الكامل للموظف *</label><input type="text" name="name" required placeholder="أحمد رأفت عبد السلام"></div>
                    <div class="input-box"><label>الهوية الوطنية / الجواز *</label><input type="text" name="national_id" required></div>
                    <div class="input-box"><label>رقم الهاتف *</label><input type="text" name="phone" required></div>
                    <div class="input-box"><label>البريد الإلكتروني</label><input type="email" name="email"></div>
                    <div class="input-box">
                        <label>القسم المخصص *</label>
                        <select name="department_id" required>
                            <option value="">-- اختر القسم التنظيمي --</option>
                            <?php foreach($departments as $dept): ?><option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- ربط الموظف بالمدير القيادي -->
                    <div class="input-box">
                        <label style="color: var(--accent-purple); font-weight:800;">المدير المسؤول المباشر *</label>
                        <select name="manager_id" required>
                            <option value="">-- حدد المسؤول القيادي للموظف --</option>
                            <?php foreach($managers as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?> (<?php echo getManagerTypeName($m['type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="input-box"><label>المسمى الوظيفي للكادر *</label><input type="text" name="job_title" required placeholder="Senior Backend Developer"></div>
                    <div class="input-box"><label>الراتب الأساسي (EGP) *</label><input type="number" step="0.01" name="base_salary" required></div>
                    <div class="input-box"><label>الحوافز والبدلات</label><input type="number" step="0.01" name="allowances" value="0.00"></div>
                    <div class="input-box"><label>الملف التأميني</label><input type="text" name="insurance_num"></div>
                    <div class="input-box"><label>الصورة المعتمدة للـ ID</label><input type="file" name="photo" accept="image/*"></div>
                </div>
                <button type="submit" class="btn-submit"><i class="fa-solid fa-id-card"></i> توليد وإصدار بطاقة الهوية الذكية للموظف</button>
            </form>
        </div>

        <!-- تبويبات الموظفين وعرض كروت الـ ID الذكية -->
        <div class="category-tabs-container">
            <button class="tab-btn active" onclick="switchTab('all')">كافة الكوادر <span class="badge"><?php echo count($all_employees); ?></span></button>
            <?php foreach($departments as $d): ?>
                <button class="tab-btn" onclick="switchTab('dept_<?php echo $d['id']; ?>')"><?php echo htmlspecialchars($d['name']); ?> <span class="badge"><?php echo count($emp_by_dept[$d['id']] ?? []); ?></span></button>
            <?php endforeach; ?>
        </div>

        <div id="pane_all" class="category-content-panel active">
            <div class="employees-cards-grid">
                <?php foreach($all_employees as $emp): 
                    $avatar = !empty($emp['photo']) ? $emp['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($emp['name']) . '&background=0b111e&color=fff&size=128';
                ?>
                    <div class="id-card-wrapper">
                        <div style="width:100%; display:flex; justify-content:space-between; font-size:11px; margin-bottom:15px; color:var(--text-muted);">
                            <span>K-HQ SECURE</span><span><?php echo $emp['emp_id_code']; ?></span>
                        </div>
                        <div class="id-photo-frame"><img src="<?php echo $avatar; ?>"></div>
                        <div class="id-name"><?php echo htmlspecialchars($emp['name']); ?></div>
                        <div class="id-title"><?php echo htmlspecialchars($emp['job_title']); ?></div>
                        
                        <div class="id-details-list">
                            <div class="id-detail-row"><span class="lbl">القسم الحالي:</span><span class="val"><?php echo htmlspecialchars($emp['dept_name'] ?? 'غير محدد'); ?></span></div>
                            <div class="id-detail-row" style="background:rgba(168,85,247,0.08); padding:2px 6px; border-radius:6px;"><span class="lbl" style="color:var(--accent-purple);">المدير المسؤول:</span><span class="val"><?php echo htmlspecialchars($emp['manager_name'] ?? 'لا يوجد مدير كلف'); ?></span></div>
                            <div class="id-detail-row"><span class="lbl">رقم التواصل:</span><span class="val" dir="ltr"><?php echo htmlspecialchars($emp['phone']); ?></span></div>
                            <div class="id-detail-row"><span class="lbl">الراتب الإجمالي:</span><span class="val"><?php echo number_format($emp['base_salary'] + $emp['allowances'], 2); ?> EGP</span></div>
                        </div>

                        <div class="id-actions-bar">
                            <button class="action-btn btn-edit-trigger" onclick="openEmployeeEditModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)"><i class="fa-solid fa-user-gear"></i> تعديل</button>
                            <form action="manage-employees.php" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف الموظف؟');" style="flex:1; display:flex;">
                                <input type="hidden" name="action" value="delete_employee"><input type="hidden" name="id" value="<?php echo $emp['id']; ?>">
                                <button type="submit" class="action-btn btn-delete-trigger" style="width:100%;"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php foreach($departments as $d): ?>
            <div id="pane_dept_<?php echo $d['id']; ?>" class="category-content-panel">
                <div class="employees-cards-grid">
                    <?php 
                    $dept_emps = $emp_by_dept[$d['id']] ?? [];
                    if(empty($dept_emps)) echo "<p style='color:var(--text-muted);'>لا يوجد موظفين في هذا القسم حالياً.</p>";
                    foreach($dept_emps as $emp): 
                        $avatar = !empty($emp['photo']) ? $emp['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($emp['name']);
                    ?>
                        <div class="id-card-wrapper">
                            <div class="id-photo-frame"><img src="<?php echo $avatar; ?>"></div>
                            <div class="id-name"><?php echo htmlspecialchars($emp['name']); ?></div>
                            <div class="id-title"><?php echo htmlspecialchars($emp['job_title']); ?></div>
                            <div class="id-actions-bar">
                                <button class="action-btn btn-edit-trigger" onclick="openEmployeeEditModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)"><i class="fa-solid fa-user-gear"></i> تعديل</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ==================== 🛠️ مودالات التعديل المنبثقة (Popups Modals) 🛠️ ==================== -->
    
    <!-- 1. مودال تعديل المدير المتقدم -->
    <div class="modal-overlay" id="editManagerModal">
        <div class="modal-box" style="max-width:520px;">
            <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:#fff;"><i class="fa-solid fa-user-shield"></i> تحديث القيادة الإدارية والمالية</h3>
                <button style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer;" onclick="closeModal('editManagerModal')">×</button>
            </div>
            <form action="manage-employees.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_manager">
                <input type="hidden" name="manager_id" id="m_modal_id">
                <div class="input-box" style="margin-bottom:15px;"><label>اسم المدير الكامل</label><input type="text" name="manager_name" id="m_modal_name" required></div>
                <div class="input-box" style="margin-bottom:15px;"><label>المسمى القيادي</label><input type="text" name="manager_title" id="m_modal_title" required></div>
                <div class="input-box" style="margin-bottom:15px;">
                    <label>الرتبة والمستوى</label>
                    <select name="manager_type" id="m_modal_type">
                        <option value="general">مدير عام الكيان</option>
                        <option value="executive">مدير تنفيذي قطاعي</option>
                        <option value="region">مدير منطقة إقليمية</option>
                        <option value="country">مدير دولة</option>
                    </select>
                </div>
                <div class="input-box" style="margin-bottom:15px;"><label>الراتب الهيكلي (EGP)</label><input type="number" step="0.01" name="manager_salary" id="m_modal_salary" required></div>
                <div class="input-box" style="margin-bottom:20px;"><label>تحديث الصورة الشخصية للمدير (اختياري)</label><input type="file" name="manager_photo" accept="image/*"></div>
                <button type="submit" class="btn-submit" style="width:100%;">تحديث وحفظ البيانات الاعتمادية</button>
            </form>
        </div>
    </div>

    <!-- 2. مودال تعديل الموظف -->
    <div class="modal-overlay" id="editEmployeeModal">
        <div class="modal-box">
            <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:#fff;"><i class="fa-solid fa-user-pen"></i> تعديل مصفوفة بيانات الموظف</h3>
                <button style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer;" onclick="closeModal('editEmployeeModal')">×</button>
            </div>
            <form action="manage-employees.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_employee">
                <input type="hidden" name="id" id="emp_modal_id">
                <div class="inputs-matrix">
                    <div class="input-box"><label>الاسم الكامل</label><input type="text" name="name" id="emp_modal_name" required></div>
                    <div class="input-box"><label>الهوية الوطنية</label><input type="text" name="national_id" id="emp_modal_national_id" required></div>
                    <div class="input-box"><label>الهاتف</label><input type="text" name="phone" id="emp_modal_phone" required></div>
                    <div class="input-box"><label>البريد الإلكتروني</label><input type="email" name="email" id="emp_modal_email"></div>
                    <div class="input-box">
                        <label>القسم</label>
                        <select name="department_id" id="emp_modal_dept_id">
                            <?php foreach($departments as $dept): ?><option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-box">
                        <label style="color:var(--accent-purple);">المدير المسؤول</label>
                        <select name="manager_id" id="emp_modal_manager_id">
                            <?php foreach($managers as $m): ?><option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-box"><label>المسمى الوظيفي</label><input type="text" name="job_title" id="emp_modal_job_title" required></div>
                    <div class="input-box"><label>الراتب</label><input type="number" step="0.01" name="base_salary" id="emp_modal_salary" required></div>
                    <div class="input-box"><label>البدلات</label><input type="number" step="0.01" name="allowances" id="emp_modal_allowances" required></div>
                    <div class="input-box"><label>الملف التأميني</label><input type="text" name="insurance_num" id="emp_modal_ins"></div>
                    <div class="input-box"><label>تحديث الصورة الشخصية للموظف</label><input type="file" name="photo" accept="image/*"></div>
                </div>
                <button type="submit" class="btn-submit" style="margin-top:20px; width:100%;">حفظ التعديلات الجديدة</button>
            </form>
        </div>
    </div>

    <script>
        function switchTab(id) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.category-content-panel').forEach(p => p.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById('pane_' + id).classList.add('active');
        }

        function openManagerEditModal(m) {
            document.getElementById('m_modal_id').value = m.id;
            document.getElementById('m_modal_name').value = m.name;
            document.getElementById('m_modal_title').value = m.title;
            document.getElementById('m_modal_type').value = m.type;
            document.getElementById('m_modal_salary').value = m.salary;
            document.getElementById('editManagerModal').classList.add('active');
        }

        function openEmployeeEditModal(e) {
            document.getElementById('emp_modal_id').value = e.id;
            document.getElementById('emp_modal_name').value = e.name;
            document.getElementById('emp_modal_national_id').value = e.national_id;
            document.getElementById('emp_modal_phone').value = e.phone;
            document.getElementById('emp_modal_email').value = e.email;
            document.getElementById('emp_modal_dept_id').value = e.department_id;
            document.getElementById('emp_modal_manager_id').value = e.manager_id;
            document.getElementById('emp_modal_job_title').value = e.job_title;
            document.getElementById('emp_modal_salary').value = e.base_salary;
            document.getElementById('emp_modal_allowances').value = e.allowances;
            document.getElementById('emp_modal_ins').value = e.insurance_num;
            document.getElementById('editEmployeeModal').classList.add('active');
        }

        function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }

        // ==================== 🌌 تأثير خلفية النيازك التفاعلية 🌌 ====================
        const canvas = document.getElementById('meteorCanvas'); const ctx = canvas.getContext('2d');
        let width = canvas.width = window.innerWidth; let height = canvas.height = window.innerHeight;
        let meteors = [];
        function createMeteor() { return { x: Math.random() * width * 1.2, y: Math.random() * -20, length: Math.random() * 60 + 40, speed: Math.random() * 4 + 2, opacity: Math.random() * 0.4 + 0.1 }; }
        for (let i = 0; i < 15; i++) meteors.push(createMeteor());
        function draw() {
            ctx.clearRect(0,0,width,height);
            meteors.forEach((m, i) => {
                m.x -= m.speed * 0.7; m.y += m.speed;
                let g = ctx.createLinearGradient(m.x, m.y, m.x + m.length*0.7, m.y - m.length);
                g.addColorStop(0, `rgba(139, 92, 246, ${m.opacity})`); g.addColorStop(1, 'rgba(0,0,0,0)');
                ctx.strokeStyle = g; ctx.lineWidth = 1.5; ctx.beginPath(); ctx.moveTo(m.x, m.y); ctx.lineTo(m.x + m.length*0.7, m.y - m.length); ctx.stroke();
                if(m.y > height || m.x < -100) meteors[i] = createMeteor();
            });
            requestAnimationFrame(draw);
        }
        draw();
    </script>
</body>
</html>