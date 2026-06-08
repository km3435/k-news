<?php
// financial-statements.php - نظام المصفوفة المالية والكشوفات السيبرانية المتطورة
require_once 'db.php';

$message = '';
$alert_class = '';

// تحديد الشهر الحالي كافتراضي إذا لم يتم اختياره
$selected_month = $_GET['month'] ?? date('Y-m');

// --- 1. معالجة تحديث أو إضافة السجل المالي لموظف ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_financial') {
    $employee_id = intval($_POST['employee_id'] ?? 0);
    $bonus = floatval($_POST['bonus'] ?? 0);
    $deductions = floatval($_POST['deductions'] ?? 0);
    $tax_rate = floatval($_POST['tax_rate'] ?? 0);
    $payment_status = $_POST['payment_status'] ?? 'معلق';

    if ($employee_id > 0) {
        try {
            // استخدام ON DUPLICATE KEY UPDATE لتحديث السجل إن وجد لنفس الموظف ونفس الشهر، أو إنشائه
            $stmt = $pdo->prepare("INSERT INTO financial_records (employee_id, month_year, bonus, deductions, tax_rate, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE bonus = ?, deductions = ?, tax_rate = ?, payment_status = ?");
            
            $stmt->execute([
                $employee_id, $selected_month, $bonus, $deductions, $tax_rate, $payment_status,
                $bonus, $deductions, $tax_rate, $payment_status
            ]);

            $message = "تم تحديث المصفوفة الحسابية للكادر وتعديل القيود المالية بنجاح.";
            $alert_class = "alert-success";
        } catch (PDOException $e) {
            $message = "فشل تحديث البيانات المالية: " . $e->getMessage();
            $alert_class = "alert-error";
        }
    }
}

// --- 2. جلب كافة الموظفين مدمجاً معهم سجلاتهم المالية للشهر المحدد ---
try {
    $query = "
        SELECT 
            e.id, e.emp_id_code, e.name, e.job_title, e.base_salary, e.allowances, e.photo,
            d.name as dept_name,
            fr.bonus, fr.deductions, fr.tax_rate, fr.payment_status
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN financial_records fr ON e.id = fr.employee_id AND fr.month_year = ?
        ORDER BY e.id DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$selected_month]);
    $financial_data = $stmt->fetchAll();

    // حساب الإحصائيات العامة للمنظومة المالية
    $total_gross = 0;
    $total_deductions = 0;
    $total_net = 0;
    $paid_count = 0;
    $pending_count = 0;

    foreach ($financial_data as $row) {
        $base = $row['base_salary'];
        $allowance = $row['allowances'];
        $bonus = $row['bonus'] ?? 0;
        $deduct = $row['deductions'] ?? 0;
        $tax_p = $row['tax_rate'] ?? 10;

        $gross = $base + $allowance + $bonus;
        $tax_amount = $gross * ($tax_p / 100);
        $net = $gross - $deduct - $tax_amount;

        $total_gross += $gross;
        $total_deductions += ($deduct + $tax_amount);
        $total_net += $net;

        if (($row['payment_status'] ?? 'معلق') === 'تم الصرف') {
            $paid_count++;
        } else {
            $pending_count++;
        }
    }

} catch (PDOException $e) {
    $financial_data = [];
    $total_gross = $total_deductions = $total_net = $paid_count = $pending_count = 0;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl" id="pageHtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-HQ - كشوفات الماتريكس المالية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js" crossorigin="anonymous"></script>
    
    <style>
        :root {
            --bg-primary: #02040a;
            --bg-secondary: #0b111e;
            --bg-input: rgba(5, 8, 16, 0.7);
            --border-color: #1e293b;
            --accent-blue: #3b82f6;
            --accent-purple: #a855f7;
            --accent-glow: rgba(59, 130, 246, 0.4);
            --text-main: #f8fafc;
            --text-muted: #64748b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html[dir="rtl"] * { font-family: 'Cairo', sans-serif; }
        
        body { background-color: var(--bg-primary); color: var(--text-main); display: flex; min-height: 100vh; overflow-x: hidden; position: relative; }
        
        /* 🌌 محرك خلفية النيازك والشهب الحية 🌌 */
        #meteorCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.7; }
        .sidebar, .main-content { position: relative; z-index: 1; }

        /* السايد بار الاحترافي */
        .sidebar { width: 290px; background: linear-gradient(180deg, #040712 0%, #090f1d 100%); padding: 35px 24px; display: flex; flex-direction: column; justify-content: space-between; border-left: 1px solid var(--border-color); box-shadow: 5px 0 30px rgba(0,0,0,0.7); }
        .logo-area { font-size: 26px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .logo-area span { color: var(--accent-blue); text-shadow: 0 0 15px var(--accent-glow); }
        
        /* 🌀 هندسة الحلقات النيون الدوارة 🌀 */
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

        .main-content { flex: 1; padding: 45px; overflow-y: auto; }
        .top-header h1 { font-size: 34px; font-weight: 800; background: linear-gradient(135deg, #fff 30%, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* لوحة التحكم اللحظية المصغرة للرواتب */
        .financial-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; margin-bottom: 35px; }
        .stat-card { background: rgba(11, 17, 30, 0.45); border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; backdrop-filter: blur(12px); box-shadow: 0 15px 30px rgba(0,0,0,0.4); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top:0; left:0; width:4px; height:100%; background: var(--accent-blue); }
        .stat-card.purple::before { background: var(--accent-purple); }
        .stat-card.green::before { background: #10b981; }
        .stat-card.amber::before { background: #f59e0b; }
        .stat-title { font-size: 13px; color: var(--text-muted); font-weight: 700; margin-bottom: 8px; }
        .stat-value { font-size: 24px; font-weight: 800; color: #fff; }

        /* الفلتر والتحكم بالشهر */
        .filter-panel { display: flex; align-items: center; justify-content: space-between; background: rgba(11, 17, 30, 0.3); border: 1px solid var(--border-color); padding: 18px 28px; border-radius: 16px; margin-bottom: 35px; backdrop-filter: blur(10px); }
        .filter-panel input[type="month"] { background: var(--bg-input); border: 1px solid var(--border-color); color: #fff; padding: 10px 16px; border-radius: 10px; font-weight: 700; outline: none; }

        /* جدول الحسابات السيبراني */
        .matrix-table-wrapper { background: rgba(11, 17, 30, 0.5); border: 1px solid var(--border-color); border-radius: 24px; overflow: hidden; backdrop-filter: blur(16px); box-shadow: 0 20px 40px rgba(0,0,0,0.6); }
        .matrix-table { width: 100%; border-collapse: collapse; text-align: right; font-size: 14px; }
        .matrix-table th { background: rgba(4, 7, 18, 0.8); padding: 18px; font-weight: 700; color: #94a3b8; border-bottom: 1px solid var(--border-color); }
        .matrix-table td { padding: 18px; border-bottom: 1px solid rgba(30, 41, 59, 0.5); color: #e2e8f0; vertical-align: middle; }
        .matrix-table tr:hover td { background: rgba(255,255,255,0.02); }

        .emp-profile-td { display: flex; align-items: center; gap: 12px; }
        .emp-profile-td img { width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--accent-blue); }

        /* البادجات الملونة للحالات */
        .status-pill { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .status-pill.paid { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-pill.pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        /* أزرار الإجراءات */
        .btn-calc { background: rgba(168, 85, 247, 0.15); border: 1px solid rgba(168, 85, 247, 0.4); color: #c084fc; padding: 8px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-calc:hover { background: var(--accent-purple); color: #fff; box-shadow: 0 0 15px rgba(168, 85, 247, 0.4); }

        /* المودال الخاص بالتسويات المالية */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(2, 4, 10, 0.85); backdrop-filter: blur(12px); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: var(--bg-secondary); border: 1px solid var(--border-color); width: 100%; max-width: 550px; border-radius: 24px; padding: 35px; border-top: 4px solid var(--accent-blue); animation: modalReveal 0.3s ease; }
        @keyframes modalReveal { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }
        
        .inputs-matrix { display: grid; grid-template-columns: 1fr; gap: 20px; }
        .input-box { display: flex; flex-direction: column; gap: 6px; }
        .input-box label { font-size: 13px; font-weight: 700; color: #94a3b8; }
        .input-box input, .input-box select { background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px; color: #fff; outline: none; }
        .input-box input:focus, .input-box select:focus { border-color: var(--accent-blue); }

        .btn-submit { background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; border: none; padding: 14px 28px; border-radius: 10px; font-weight: 700; cursor: pointer; margin-top: 20px; display: inline-flex; align-items: center; gap: 10px; width: 100%; justify-content: center; }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 25px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-error { background: rgba(244, 63, 94, 0.1); border: 1px solid #f43f5e; color: #f43f5e; }
    </style>
</head>
<body>

    <canvas id="meteorCanvas"></canvas>

    <!-- السايد بار الاحترافي -->
    <div class="sidebar">
        <div>
            <div class="logo-area">
                <div class="icon-orb-container">
                    <div class="icon-loop loop-1"></div>
                    <div class="icon-loop loop-2"></div>
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <span>K·HQ</span>
            </div>
            
            <ul class="menu-list">
                <li><a href="dashboard.php" class="menu-item"><div class="icon-orb-container"><div class="icon-loop loop-1"></div><i class="fa-solid fa-chart-pie"></i></div> لوحة التحكم</a></li>
                <li><a href="manage-employees.php" class="menu-item"><div class="icon-orb-container"><div class="icon-loop loop-1"></div><i class="fa-solid fa-users-gear"></i></div> إدارة الموظفين</a></li>
                <li><a href="financial-statements.php" class="menu-item active"><div class="icon-orb-container"><div class="icon-loop loop-1"></div><i class="fa-solid fa-wallet"></i></div> الكشوفات المالية</a></li>
            </ul>
        </div>
        
        <div class="user-profile-footer">
            <img src="https://ui-avatars.com/api/?name=Kareem+HQ&background=3b82f6&color=fff" alt="Admin">
            <div>
                <h4 style="font-size: 14px; font-weight:700;">إدارة العمليات</h4>
                <p style="font-size: 11px; color: var(--text-muted);">مدير الموارد البشرية</p>
            </div>
        </div>
    </div>

    <!-- المحتوى الرئيسي -->
    <div class="main-content">
        <div class="top-header" style="margin-bottom: 40px;">
            <h1>كشوفات الماتريكس والمستحقات الحسابية</h1>
            <p style="color: var(--text-muted); margin-top: 6px;">بوابة الإقرار المالي الفوري وتوزيع الحوافز الاستقطاعية المتقدمة لكوادر المنظومة</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $alert_class; ?>"><i class="fa-solid fa-circle-info"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <!-- لوحة الإحصائيات الفورية للشهر المختار -->
        <div class="financial-stats-grid">
            <div class="stat-card">
                <div class="stat-title">إجمالي الرواتب الخام (Gross)</div>
                <div class="stat-value"><?php echo number_format($total_gross, 2); ?> <span style="font-size:12px;">EGP</span></div>
            </div>
            <div class="stat-card purple">
                <div class="stat-title">إجمالي الخصومات والضرائب</div>
                <div class="stat-value"><?php echo number_format($total_deductions, 2); ?> <span style="font-size:12px;">EGP</span></div>
            </div>
            <div class="stat-card green">
                <div class="stat-title">الصافي الكلي للتوزيع (Net)</div>
                <div class="stat-value" style="color:#10b981;"><?php echo number_format($total_net, 2); ?> <span style="font-size:12px;">EGP</span></div>
            </div>
            <div class="stat-card amber">
                <div class="stat-title">حالة صرف المسيرات (حالي)</div>
                <div class="stat-value" style="font-size:16px; margin-top:6px;">
                    <i class="fa-solid fa-circle-check" style="color:#10b981;"></i> تم: <?php echo $paid_count; ?> &nbsp;|&nbsp; 
                    <i class="fa-solid fa-circle-stop" style="color:#f59e0b;"></i> معلق: <?php echo $pending_count; ?>
                </div>
            </div>
        </div>

        <!-- فلتر تحديد دورة الحساب المالية -->
        <div class="filter-panel">
            <span style="font-weight: 700; font-size: 15px;"><i class="fa-solid fa-sliders" style="color:var(--accent-blue);"></i> نافذة الدورة الحسابية المستهدفة</span>
            <form method="GET" action="financial-statements.php" id="monthFilterForm">
                <input type="month" name="month" value="<?php echo $selected_month; ?>" onchange="document.getElementById('monthFilterForm').submit();">
            </form>
        </div>

        <!-- مصفوفة جدول الحسابات -->
        <div class="matrix-table-wrapper">
            <table class="matrix-table">
                <thead>
                    <tr>
                        <th>الكادر البشري</th>
                        <th>القسم الهيكلي</th>
                        <th>الراتب الأساسي + البدلات</th>
                        <th>مكافآت (+)</th>
                        <th>جزاءات (-)</th>
                        <th>الضريبة %</th>
                        <th>الصافي المستحق (Net)</th>
                        <th>حالة القيد</th>
                        <th>إجراء تسوية</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($financial_data)): ?>
                        <tr><td colspan="9" style="text-align:center; color:var(--text-muted);">لا توجد كوادر بشرية مسجلة حالياً لاحتساب قيمها المالية.</td></tr>
                    <?php else: 
                        foreach($financial_data as $row):
                            $base_salary = $row['base_salary'];
                            $allowances = $row['allowances'];
                            $bonus = $row['bonus'] ?? 0;
                            $deductions = $row['deductions'] ?? 0;
                            $tax_rate = $row['tax_rate'] ?? 10;

                            // عملية المحاكاة البرمجية للمعادلة المالية السيبرانية
                            $gross = $base_salary + $allowances + $bonus;
                            $tax_amount = $gross * ($tax_rate / 100);
                            $net_salary = $gross - $deductions - $tax_amount;

                            $status_class = ($row['payment_status'] ?? 'معلق') === 'تم الصرف' ? 'paid' : 'pending';
                            $avatar = !empty($row['photo']) ? $row['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($row['name']) . '&background=0b111e&color=fff&size=64';
                    ?>
                        <tr>
                            <td>
                                <div class="emp-profile-td">
                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar">
                                    <div>
                                        <div style="font-weight:700; color:#fff;"><?php echo htmlspecialchars($row['name']); ?></div>
                                        <div style="font-size:11px; color:var(--text-muted); font-family:monospace;"><?php echo $row['emp_id_code']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span style="font-size:12px; background:rgba(168,85,247,0.1); color:var(--accent-purple); padding:4px 8px; border-radius:6px;"><?php echo htmlspecialchars($row['dept_name'] ?? 'عام'); ?></span></td>
                            <td><span style="font-weight:600;"><?php echo number_format($base_salary + $allowances, 2); ?></span></td>
                            <td style="color:#10b981; font-weight:700;">+<?php echo number_format($bonus, 2); ?></td>
                            <td style="color:#f43f5e; font-weight:700;">-<?php echo number_format($deductions, 2); ?></td>
                            <td style="color:#94a3b8; font-size:13px;"><?php echo $tax_rate; ?>%</td>
                            <td style="color:#3b82f6; font-weight:800; font-size:15px;"><?php echo number_format($net_salary, 2); ?> EGP</td>
                            <td>
                                <span class="status-pill <?php echo $status_class; ?>">
                                    <i class="fa-solid <?php echo $status_class === 'paid' ? 'fa-circle-check' : 'fa-circle-notch fa-spin'; ?>"></i>
                                    <?php echo $row['payment_status'] ?? 'معلق'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-calc" onclick="openFinancialModal(<?php echo htmlspecialchars(json_encode([
                                    'id' => $row['id'],
                                    'name' => $row['name'],
                                    'bonus' => $bonus,
                                    'deductions' => $deductions,
                                    'tax_rate' => $tax_rate,
                                    'status' => $row['payment_status'] ?? 'معلق'
                                ])); ?>)">
                                    <i class="fa-solid fa-money-bill-transfer"></i> تسوية
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ==================== 🎴 نافذة التسوية والمعالجة المالية (Modal) 🎴 ==================== -->
    <div class="modal-overlay" id="financialModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 style="color: #fff; font-size: 16px; font-weight: 800; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-calculator" style="color: var(--accent-blue);"></i> تعديل القيد المالي للموظف
                </h3>
                <button style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;" onclick="closeFinancialModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="modal_emp_title" style="color:var(--text-muted); font-size:13px; font-weight:700; margin-bottom:20px;"></div>
            
            <form action="financial-statements.php?month=<?php echo $selected_month; ?>" method="POST">
                <input type="hidden" name="action" value="update_financial">
                <input type="hidden" name="employee_id" id="modal_emp_id">
                
                <div class="inputs-matrix">
                    <div class="input-box">
                        <label>الحوافز والمكافآت الاستثنائية لشهر (EGP) (+)</label>
                        <input type="number" step="0.01" name="bonus" id="modal_bonus" required>
                    </div>
                    <div class="input-box">
                        <label>الاستقطاعات والجزاءات المباشرة (EGP) (-)</label>
                        <input type="number" step="0.01" name="deductions" id="modal_deductions" required>
                    </div>
                    <div class="input-box">
                        <label>النسبة المقتطعة للضرائب (%)</label>
                        <input type="number" step="0.1" name="tax_rate" id="modal_tax_rate" required>
                    </div>
                    <div class="input-box">
                        <label>حالة الاعتماد والصرف</label>
                        <select name="payment_status" id="modal_status" required>
                            <option value="معلق">معلق (تحت المراجعة والتدقيق)</option>
                            <option value="تم الصرف">تم الاعتماد (تم ضخ المستحقات)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-submit"><i class="fa-solid fa-receipt"></i> تثبيت البيانات وإدراجها بمسير الرواتب</button>
            </form>
        </div>
    </div>

    <script>
        // التحكم بالمودال الخاص بالتسويات المالية تلقائياً
        function openFinancialModal(data) {
            document.getElementById('modal_emp_id').value = data.id;
            document.getElementById('modal_emp_title').innerText = "الموظف المستهدف: " + data.name;
            document.getElementById('modal_bonus').value = data.bonus;
            document.getElementById('modal_deductions').value = data.deductions;
            document.getElementById('modal_tax_rate').value = data.tax_rate;
            document.getElementById('modal_status').value = data.status;

            document.getElementById('financialModal').classList.add('active');
        }

        function closeFinancialModal() {
            document.getElementById('financialModal').classList.remove('active');
        }

        window.onclick = function(event) {
            let modal = document.getElementById('financialModal');
            if (event.target == modal) { closeFinancialModal(); }
        }

        // ==================== 🌌 محرك رياح النيازك والشهب التفاعلية المتقدم 🌌 ====================
        window.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('meteorCanvas');
            const ctx = canvas.getContext('2d');
            let width = canvas.width = window.innerWidth;
            let height = canvas.height = window.innerHeight;

            window.addEventListener('resize', () => {
                width = canvas.width = window.innerWidth;
                height = canvas.height = window.innerHeight;
            });

            let meteors = [];
            function createMeteor() {
                return {
                    x: Math.random() * width * 1.3,
                    y: Math.random() * -20,
                    length: Math.random() * 80 + 40,
                    speed: Math.random() * 4 + 3,
                    opacity: Math.random() * 0.4 + 0.2
                };
            }

            for (let i = 0; i < 22; i++) { meteors.push(createMeteor()); }

            function animateMeteors() {
                ctx.clearRect(0, 0, width, height);
                meteors.forEach((m, idx) => {
                    m.x -= m.speed * 0.7;
                    m.y += m.speed;

                    let gradient = ctx.createLinearGradient(m.x, m.y, m.x + m.length * 0.7, m.y - m.length);
                    gradient.addColorStop(0, `rgba(59, 130, 246, ${m.opacity})`);
                    gradient.addColorStop(0.5, `rgba(139, 92, 246, ${m.opacity * 0.5})`);
                    gradient.addColorStop(1, 'rgba(0, 0, 0, 0)');

                    ctx.strokeStyle = gradient;
                    ctx.lineWidth = 1.8;
                    ctx.beginPath();
                    ctx.moveTo(m.x, m.y);
                    ctx.lineTo(m.x + m.length * 0.7, m.y - m.length);
                    ctx.stroke();

                    if (m.y > height || m.x < -100) { meteors[idx] = createMeteor(); }
                });
                requestAnimationFrame(animateMeteors);
            }
            animateMeteors();
        });
    </script>
</body>
</html>