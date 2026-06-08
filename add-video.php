<?php
// 1. ربط قاعدة البيانات
require_once 'db.php';

$message = "";
$messageType = "";

// 2. معالجة عمليات الإرسال (إضافة - تعديل - حذف)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // أ: عملية إضافة فيديو جديد
    if (isset($_POST['action']) && $_POST['action'] == 'insert') {
        $title       = $_POST['title'] ?? '';
        $video_url   = $_POST['video_url'] ?? '';
        $category    = $_POST['category'] ?? 'عام';
        $description = $_POST['description'] ?? '';

        if (!empty($title) && !empty($video_url)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO videos (title, video_url, category, description, views) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $video_url, $category, $description, rand(100, 1500)]);
                $message = "تم إطلاق الفيديو بنجاح إلى الفضاء الخارجي للمنصة! 🚀";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "فشل الإرسال: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
    
    // ب: عملية تحديث فيديو قائم
    if (isset($_POST['action']) && $_POST['action'] == 'update') {
        $id          = $_POST['video_id'] ?? '';
        $title       = $_POST['title'] ?? '';
        $video_url   = $_POST['video_url'] ?? '';
        $category    = $_POST['category'] ?? 'عام';
        $description = $_POST['description'] ?? '';

        if (!empty($id) && !empty($title) && !empty($video_url)) {
            try {
                $stmt = $pdo->prepare("UPDATE videos SET title = ?, video_url = ?, category = ?, description = ? WHERE id = ?");
                $stmt->execute([$title, $video_url, $category, $description, $id]);
                $message = "تم تحديث بيانات ومسار الفيديو بنجاح! 💾";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "فشل التعديل: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }

    // ج: عملية حذف فيديو
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id = $_POST['video_id'] ?? '';
        if (!empty($id)) {
            try {
                $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
                $stmt->execute([$id]);
                $message = "تم تدمير وإزالة الفيديو من خوادم المنصة بنجاح. 💥";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "فشل الحذف: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// 3. جلب جميع الفيديوهات الحالية للعرض أسفل الصفحة
$videos = $pdo->query("SELECT * FROM videos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// دالة مساعدة لاستخراج معرف اليوتيوب لتوليد الغلاف في الـ PHP
function getYouTubeId($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
    return isset($match[1]) ? $match[1] : 'mqdefault';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS - Cyber Video Studio</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background-color: #030508; color: #ffffff; display: flex; 
            min-height: 100vh; overflow-x: hidden; font-family: 'Cairo', sans-serif;
        }
        
        #meteorCanvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
        .sidebar, .main-content { position: relative; z-index: 1; }
        
        /* الـ Sidebar المتوافق */
        .sidebar { 
            width: 290px; background: rgba(9, 13, 22, 0.75); backdrop-filter: blur(15px);
            padding: 25px 15px; display: flex; flex-direction: column; justify-content: space-between; 
            border-left: 1px solid rgba(19, 26, 42, 0.6); height: 100vh; position: sticky; top: 0;
        }
        .logo-area { font-size: 26px; font-weight: 900; letter-spacing: 1px; margin-bottom: 25px; font-family: 'Orbitron', sans-serif; text-align: right; padding-right:10px; }
        .logo-area span { color: #3b82f6; text-shadow: 0 0 15px rgba(59, 130, 246, 0.8); }
        .menu-item { 
            padding: 11px 14px; border-radius: 10px; margin-bottom: 6px; color: #64748b; 
            display: flex; align-items: center; text-decoration: none; font-size: 13.5px; font-weight: 600; transition: all 0.3s;
        }
        .menu-item.active { background: linear-gradient(135deg, #a855f7, #6b21a8); box-shadow: 0 4px 20px rgba(168, 85, 247, 0.5); color: #fff; }
        .menu-item i { margin-left: 12px; font-size: 14px; }
        .menu-item:hover:not(.active) { background-color: rgba(17, 24, 39, 0.5); color: #cbd5e1; transform: translateX(-3px); }

        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 35px; }
        .top-owner-profile {
            display: flex; align-items: center; gap: 12px; background: rgba(9, 13, 22, 0.6);
            backdrop-filter: blur(8px); border: 1px solid rgba(168, 85, 247, 0.2); padding: 6px 14px; border-radius: 30px;
        }
        .owner-avatar { width: 34px; height: 34px; border-radius: 50%; border: 1.5px solid #a855f7; }

        /* حاوية الفورم */
        .cyber-form-container {
            background: rgba(9, 13, 22, 0.65); backdrop-filter: blur(20px);
            border: 1px solid rgba(19, 26, 42, 0.8); border-radius: 24px; padding: 35px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.4); display: grid; grid-template-columns: 1.51fr 1fr; gap: 30px; margin-bottom: 50px;
        }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { display: block; color: #94a3b8; font-size: 14px; font-weight: 700; margin-bottom: 8px; }
        .form-group label i { margin-left: 6px; color: #a855f7; }
        
        .cyber-input, .cyber-select, .cyber-textarea {
            width: 100%; background: rgba(3, 5, 8, 0.7); border: 1.5px solid #131a2a;
            border-radius: 12px; padding: 12px 16px; color: #fff; font-size: 14px; outline: none;
            font-family: 'Cairo'; transition: all 0.3s ease;
        }
        .cyber-input:focus, .cyber-select:focus, .cyber-textarea:focus {
            border-color: #a855f7; box-shadow: 0 0 15px rgba(168, 85, 247, 0.25); background: rgba(3, 5, 8, 0.9);
        }
        
        .cyber-btn-submit {
            background: linear-gradient(135deg, #a855f7, #7c3aed); color: #fff; border: none;
            padding: 14px 30px; border-radius: 12px; font-size: 16px; font-weight: 700; font-family: 'Cairo';
            cursor: pointer; width: 100%; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 5px 20px rgba(124, 58, 237, 0.4);
        }
        .cyber-btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(168, 85, 247, 0.6); }

        .preview-box {
            border: 2px dashed rgba(168, 85, 247, 0.3); border-radius: 18px;
            background: rgba(255,255,255, 0.01); padding: 20px; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center; min-height: 250px; position: relative; overflow: hidden;
        }
        .preview-box img { width: 100%; height: auto; border-radius: 12px; display: none; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .preview-placeholder { color: #475569; }
        .preview-placeholder i { font-size: 48px; margin-bottom: 12px; color: #1e1b4b; animation: float 3s ease-in-out infinite; }

        /* ستايل شبكة كروت إدارة الفيديو الجديدة */
        .video-grid-title { font-size: 22px; font-weight: 900; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .video-grid-title span { color: #a855f7; font-family: 'Orbitron'; }
        
        .video-cards-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 25px; }
        
        .video-card {
            background: rgba(9, 13, 22, 0.6); backdrop-filter: blur(10px); border: 1px solid #131a2a;
            border-radius: 20px; overflow: hidden; transition: all 0.3s ease; display: flex; flex-direction: column; justify-content: space-between;
        }
        .video-card:hover { transform: translateY(-6px); border-color: #a855f7; box-shadow: 0 12px 30px rgba(168, 85, 247, 0.2); }
        
        .video-card-thumb-wrapper { position: relative; width: 100%; pt: 56.25%; overflow: hidden; background: #000; }
        .video-card-thumb { width: 100%; height: 180px; object-fit: cover; border-bottom: 1px solid rgba(19, 26, 42, 0.5); }
        .video-card-category {
            position: absolute; top: 12px; right: 12px; background: rgba(168, 85, 247, 0.85);
            backdrop-filter: blur(5px); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #fff;
        }
        
        .video-card-body { padding: 20px; flex-grow: 1; }
        .video-card-title { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 8px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .video-card-desc { font-size: 13px; color: #64748b; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 15px; }
        
        .video-card-stats { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #475569; font-family: 'Orbitron'; border-top: 1px solid rgba(19, 26, 42, 0.4); padding-top: 12px; }
        
        /* أزرار التحكم الاحترافية للكارت */
        .video-card-actions { display: flex; gap: 10px; padding: 0 20px 20px 20px; }
        .btn-action {
            flex: 1; padding: 10px; border-radius: 10px; font-family: 'Cairo'; font-size: 13px; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; border: none; outline: none; transition: all 0.2s;
        }
        .btn-edit { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #3b82f6; }
        .btn-edit:hover { background: #3b82f6; color: #fff; box-shadow: 0 0 15px rgba(59, 130, 246, 0.4); }
        .btn-delete { background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.3); color: #f43f5e; }
        .btn-delete:hover { background: #f43f5e; color: #fff; box-shadow: 0 0 15px rgba(244, 63, 94, 0.4); }

        /* نافذة التعديل المنبثقة (Edit Modal) */
        .cyber-modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 5, 8, 0.85);
            backdrop-filter: blur(10px); z-index: 100; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: all 0.3s ease;
        }
        .cyber-modal.open { opacity: 1; pointer-events: auto; }
        .modal-content {
            background: rgba(9, 13, 22, 0.95); border: 1px solid #a855f7; width: 100%; max-width: 550px;
            padding: 30px; border-radius: 24px; box-shadow: 0 0 40px rgba(168, 85, 247, 0.3); position: relative;
        }
        .modal-close { position: absolute; top: 20px; left: 20px; color: #64748b; cursor: pointer; font-size: 20px; transition: color 0.2s; }
        .modal-close:hover { color: #f43f5e; }

        .alert-panel { padding: 14px 20px; border-radius: 12px; margin-bottom: 25px; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .alert-panel.success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-panel.error { background: rgba(244, 63, 94, 0.1); border: 1px solid #f43f5e; color: #f43f5e; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
    </style>
</head>
<body>

    <canvas id="meteorCanvas"></canvas>

    <div class="sidebar">
        <div>
            <div class="logo-area">K<span>·STREAM</span></div>
            <nav style="margin-top: 20px;">
                <a href="dashboard.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i> <span>العودة للنواة الرئيسية</span></a>
                <a href="#" class="menu-item active"><i class="fa-solid fa-square-plus"></i> <span>بث فيديو جديد</span></a>
                <a href="#" class="menu-item"><i class="fa-solid fa-video"></i> <span>مكتبة الفيديوهات</span></a>
                <a href="#" class="menu-item"><i class="fa-solid fa-sliders"></i> <span>إعدادات البث</span></a>
            </nav>
        </div>
        <div class="menu-item" style="color:#475569;">منصة البث v2.5</div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h1>بث فيديو احترافي جديد</h1>
                <p style="color: #64748b; margin-top: 4px;">قم بدمج وتضمين الفيديوهات المليونية داخل النظام</p>
            </div>
            <div class="top-owner-profile">
                <img src="https://ui-avatars.com/api/?name=Owner+Knews&background=a855f7&color=fff" class="owner-avatar" alt="Owner">
                <div style="text-align: right;">
                    <span style="font-size: 13px; font-weight:700; display:block;">صاحب الحساب</span>
                    <span style="font-size: 10px; color:#a855f7; font-weight:600;">بث نشط خارق</span>
                </div>
            </div>
        </div>

        <?php if(!empty($message)): ?>
            <div class="alert-panel <?php echo $messageType; ?>">
                <i class="<?php echo $messageType == 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-exclamation'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="cyber-form-container">
            <input type="hidden" name="action" value="insert">
            <div>
                <div class="form-group">
                    <label><i class="fa-solid fa-heading"></i>عنوان الفيديو الفضائي</label>
                    <input type="text" name="title" class="cyber-input" placeholder="مثال: وثائقي مذهل عن سقوط النيازك العملاقة..." required>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-link"></i>رابط الفيديو (YouTube)</label>
                    <input type="url" id="videoUrlInput" name="video_url" class="cyber-input" placeholder="https://www.youtube.com/watch?v=..." required>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-tags"></i>تصنيف محتوى الفيديو</label>
                    <select name="category" class="cyber-select">
                        <option value="تقنية">تقنية وعلوم سيبرانية</option>
                        <option value="أخبار عاجلة">تقارير وأخبار مصورة</option>
                        <option value="فضاء">نيازك وفلك</option>
                        <option value="عام">منوعات عامة</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fa-solid fa-align-right"></i>نبذة ووصف الفيديو التوضيحي</label>
                    <textarea name="description" class="cyber-textarea" rows="4" placeholder="اكتب تفاصيل المقطع هنا..."></textarea>
                </div>

                <button type="submit" class="cyber-btn-submit">
                    <i class="fa-solid fa-cloud-arrow-up"></i> إطلاق وبث الفيديو الآن
                </button>
            </div>

            <div style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <label style="display: block; color: #94a3b8; font-size: 14px; font-weight: 700; margin-bottom: 8px;">
                        <i class="fa-solid fa-wand-magic-sparkles" style="color:#a855f7; margin-left:6px;"></i>المعاينة الذكية المباشرة للغلاف
                    </label>
                    <div class="preview-box" id="previewBox">
                        <div class="preview-placeholder" id="placeholderText">
                            <i class="fa-solid fa-satellite-dish"></i>
                            <p style="font-size:13px;">ضع رابط يوتيوب صحيح ليقوم الرادار بالتقاط الغلاف فوراً</p>
                        </div>
                        <img id="thumbImg" src="" alt="Video Thumbnail Preview">
                    </div>
                </div>
                
                <div style="background: rgba(168, 85, 247, 0.03); border: 1px solid rgba(168, 85, 247, 0.1); padding: 15px; border-radius: 12px; font-size: 12px; color: #64748b; line-height: 1.6;">
                    <i class="fa-solid fa-circle-info" style="color:#a855f7; margin-left:4px;"></i> 
                    <strong>نظام الرادار الذكي:</strong> يقوم تلقائياً بقراءة الـ ID الخاص بالفيديو وتأكيد اتصاله بخوادم البث لضمان تجربة مستخدم فوق الخيال.
                </div>
            </div>
        </form>

        <div class="video-grid-title">
            <i class="fa-solid fa-photo-film"></i> غرفة التحكم بالفيديوهات الحية <span>(<?php echo count($videos); ?>)</span>
        </div>

        <div class="video-cards-container">
            <?php if(empty($videos)): ?>
                <p style="color: #64748b; grid-column: 1/-1; text-align: center; padding: 40px; background: rgba(255,255,255,0.01); border-radius: 15px; border: 1px dashed #131a2a;">لا توجد فيديوهات مبثوثة حالياً. أطلق أول مقطع فيديو بالأعلى!</p>
            <?php else: ?>
                <?php foreach($videos as $video): 
                    $ytId = getYouTubeId($video['video_url']);
                    $thumbUrl = "https://img.youtube.com/vi/{$ytId}/mqdefault.jpg";
                ?>
                    <div class="video-card">
                        <div class="video-card-thumb-wrapper">
                            <img src="<?php echo $thumbUrl; ?>" class="video-card-thumb" alt="Thumbnail">
                            <span class="video-card-category"><?php echo htmlspecialchars($video['category']); ?></span>
                        </div>
                        <div class="video-card-body">
                            <h3 class="video-card-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                            <p class="video-card-desc"><?php echo htmlspecialchars($video['description']); ?></p>
                            <div class="video-card-stats">
                                <span><i class="fa-solid fa-eye" style="margin-left: 5px; color:#10b981;"></i><?php echo number_format($video['views']); ?> VIEWS</span>
                                <span><i class="fa-regular fa-calendar-days" style="margin-left: 5px;"></i><?php echo date('Y/m/d', strtotime($video['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="video-card-actions">
                            <button class="btn-action btn-edit" 
                                    onclick="openEditModal(<?php echo $video['id']; ?>, '<?php echo addslashes($video['title']); ?>', '<?php echo addslashes($video['video_url']); ?>', '<?php echo $video['category']; ?>', '<?php echo addslashes($video['description']); ?>')">
                                <i class="fa-solid fa-pen-to-square"></i> تعديل
                            </button>
                            
                            <form action="" method="POST" style="flex: 1;" onsubmit="return confirm('هل أنت متأكد من تدمير وحذف هذا الفيديو تماماً؟');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                <button type="submit" class="btn-action btn-delete">
                                    <i class="fa-solid fa-trash-can"></i> حذف
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="cyber-modal" id="editModal">
        <div class="modal-content">
            <i class="fa-solid fa-xmark modal-close" onclick="closeEditModal()"></i>
            <h3 style="margin-bottom: 25px; font-size: 18px; color: #a855f7;"><i class="fa-solid fa-user-gear" style="margin-left: 8px;"></i>تحديث مصفوفة الفيديو</h3>
            
            <form action="" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="video_id" id="edit_video_id">
                
                <div class="form-group">
                    <label>عنوان الفيديو المحدث</label>
                    <input type="text" name="title" id="edit_title" class="cyber-input" required>
                </div>
                <div class="form-group">
                    <label>رابط مسار الفيديو</label>
                    <input type="url" name="video_url" id="edit_video_url" class="cyber-input" required>
                </div>
                <div class="form-group">
                    <label>تغيير التصنيف</label>
                    <select name="category" id="edit_category" class="cyber-select">
                        <option value="تقنية">تقنية وعلوم سيبرانية</option>
                        <option value="أخبار عاجلة">تقارير وأخبار مصورة</option>
                        <option value="فضاء">نيازك وفلك</option>
                        <option value="عام">منوعات عامة</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>تعديل الوصف التوضيحي</label>
                    <textarea name="description" id="edit_description" class="cyber-textarea" rows="3"></textarea>
                </div>
                <button type="submit" class="cyber-btn-submit" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); box-shadow: 0 5px 20px rgba(29, 78, 216, 0.4);">
                    <i class="fa-solid fa-floppy-disk"></i> حفظ التعديلات السحابية
                </button>
            </form>
        </div>
    </div>

    <script>
        // دالة لفتح المودال وملء الحقول بالبيانات المختارة للتعديل
        function openEditModal(id, title, url, category, description) {
            document.getElementById('edit_video_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_video_url').value = url;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').classList.add('open');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('open');
        }

        // رادار التقاط الـ Thumbnail التلقائي من يوتيوب للفورم الرئيسي
        const urlInput = document.getElementById('videoUrlInput');
        const thumbImg = document.getElementById('thumbImg');
        const placeholderText = document.getElementById('placeholderText');

        urlInput.addEventListener('input', function() {
            const url = this.value;
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            const match = url.match(regExp);

            if (match && match[2].length == 11) {
                const videoId = match[2];
                thumbImg.src = `https://img.youtube.com/vi/${videoId}/mqdefault.jpg`;
                thumbImg.style.display = 'block';
                placeholderText.style.display = 'none';
            } else {
                thumbImg.style.display = 'none';
                placeholderText.style.display = 'block';
            }
        });

        // محرك النيازك الساقطة المائل اللانهائي المذهل
        const canvas = document.getElementById('meteorCanvas');
        const ctxBg = canvas.getContext('2d');
        function resizeCanvas() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        resizeCanvas(); window.addEventListener('resize', resizeCanvas);

        const meteors = [];
        for (let i = 0; i < 25; i++) {
            meteors.push({
                x: Math.random() * canvas.width * 1.3, y: Math.random() * -canvas.height,
                length: 50 + Math.random() * 70, speed: 5 + Math.random() * 6, opacity: 0.1 + Math.random() * 0.5
            });
        }

        function drawMeteors() {
            ctxBg.clearRect(0, 0, canvas.width, canvas.height);
            meteors.forEach(m => {
                let gradient = ctxBg.createLinearGradient(m.x, m.y, m.x - m.length, m.y + m.length);
                gradient.addColorStop(0, `rgba(255, 255, 255, ${m.opacity})`);
                gradient.addColorStop(0.3, `rgba(168, 85, 247, ${m.opacity * 0.5})`);
                gradient.addColorStop(1, 'transparent');
                ctxBg.strokeStyle = gradient; ctxBg.lineWidth = 1.2;
                ctxBg.beginPath(); ctxBg.moveTo(m.x, m.y); ctxBg.lineTo(m.x - m.length, m.y + m.length); ctxBg.stroke();
                m.x -= m.speed; m.y += m.speed;
                if (m.y > canvas.height || m.x < -100) { m.x = Math.random() * canvas.width * 1.3; m.y = -100; }
            });
            requestAnimationFrame(drawMeteors);
        }
        drawMeteors();
    </script>
</body>
</html>