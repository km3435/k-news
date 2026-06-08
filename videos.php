<?php
// 1. ربط قاعدة البيانات
require_once 'db.php';

// 2. جلب التصنيف المختار إذا قام المستخدم بالفلترة
$selected_category = $_GET['cat'] ?? 'الكل';

// 3. بناء الاستعلام حسب الفلترة
if ($selected_category && $selected_category !== 'الكل') {
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE category = ? ORDER BY id DESC");
    $stmt->execute([$selected_category]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $videos = $pdo->query("SELECT * FROM videos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// 4. جلب قائمة التصنيفات الفريدة الموجودة في الداتا بيس ديناميكياً لعمل أزرار الفلترة
$categories = $pdo->query("SELECT DISTINCT category FROM videos")->fetchAll(PDO::FETCH_COLUMN);

// دالة استخراج ID اليوتيوب
function getYouTubeId($url) {
    $pattern = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?/\s]{11})%i';
    if (preg_match($pattern, $url, $match)) {
        return $match[1];
    }
    return 'mqdefault';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>K-NEWS - فيديو سبيس</title>
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
        
        /* الـ Sidebar المتناسق */
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

        /* نظام كبسولات الفلترة النيون */
        .filter-container { display: flex; gap: 12px; margin-bottom: 35px; flex-wrap: wrap; }
        .filter-badge {
            padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 700; color: #94a3b8;
            background: rgba(9, 13, 22, 0.6); border: 1px solid #131a2a; text-decoration: none; transition: all 0.3s;
        }
        .filter-badge:hover, .filter-badge.active {
            background: rgba(168, 85, 247, 0.15); border-color: #a855f7; color: #fff; box-shadow: 0 0 15px rgba(168, 85, 247, 0.3);
        }

        /* شبكة عرض كروت الفيديوهات السينمائية */
        .library-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; }
        
        .cinema-card {
            background: rgba(9, 13, 22, 0.5); border: 1px solid #131a2a; border-radius: 20px;
            overflow: hidden; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column; position: relative;
        }
        .cinema-card:hover { transform: translateY(-8px) scale(1.02); border-color: #3b82f6; box-shadow: 0 15px 35px rgba(59, 130, 246, 0.25); }
        
        .cinema-thumb-box { position: relative; width: 100%; height: 180px; overflow: hidden; background: #000; }
        .cinema-thumb { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .cinema-card:hover .cinema-thumb { transform: scale(1.1); }
        
        /* تأثير زر التشغيل عند الهوفر */
        .play-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 5, 8, 0.4);
            display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s;
        }
        .play-overlay i { font-size: 45px; color: #fff; text-shadow: 0 0 15px #3b82f6; transition: transform 0.3s; }
        .cinema-card:hover .play-overlay { opacity: 1; }
        .cinema-card:hover .play-overlay i { transform: scale(1.2); }

        .cinema-category {
            position: absolute; top: 12px; right: 12px; background: rgba(59, 130, 246, 0.85);
            backdrop-filter: blur(5px); padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #fff;
        }

        .cinema-body { padding: 20px; flex-grow: 1; }
        .cinema-title { font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 10px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cinema-desc { font-size: 12.5px; color: #64748b; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        
        .cinema-footer {
            padding: 15px 20px; border-top: 1px solid rgba(19, 26, 42, 0.4); display: flex;
            justify-content: space-between; align-items: center; font-size: 12px; color: #475569; font-family: 'Orbitron';
        }

        /* نافذة العرض السينمائي المدمجة (Player Lightbox) */
        .cinema-modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 5, 8, 0.95);
            backdrop-filter: blur(15px); z-index: 200; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: all 0.3s ease;
        }
        .cinema-modal.open { opacity: 1; pointer-events: auto; }
        .player-container { width: 100%; max-width: 850px; aspect-ratio: 16/9; background: #000; border-radius: 20px; overflow: hidden; border: 1px solid rgba(59, 130, 246, 0.4); box-shadow: 0 0 50px rgba(59, 130, 246, 0.3); position: relative; }
        .player-close { position: fixed; top: 25px; left: 25px; color: #fff; font-size: 30px; cursor: pointer; transition: color 0.2s; z-index: 210; }
        .player-close:hover { color: #f43f5e; transform: scale(1.1); }
        .cinema-modal iframe { width: 100%; height: 100%; border: none; }
    </style>
</head>
<body>

    <canvas id="meteorCanvas"></canvas>

    <div class="sidebar">
        <div>
            <div class="logo-area">K<span>·STREAM</span></div>
            <nav style="margin-top: 20px;">
                <a href="index.php" class="menu-item"><i class="fa-solid fa-chart-pie"></i> <span>العودة للنواة الرئيسية</span></a>
                <a href="add-video.php" class="menu-item"><i class="fa-solid fa-square-plus"></i> <span>بث فيديو جديد</span></a>
                <a href="#" class="menu-item active"><i class="fa-solid fa-video"></i> <span>مكتبة الفيديوهات</span></a>
                <a href="#" class="menu-item"><i class="fa-solid fa-sliders"></i> <span>إعدادات البث</span></a>
            </nav>
        </div>
        <div class="menu-item" style="color:#475569;">منصة البث v2.5</div>
    </div>

    <div class="main-content">
        <div class="top-header">
            <div>
                <h1>مكتبة الفيديوهات الرقمية</h1>
                <p style="color: #64748b; margin-top: 4px;">استعرض وشاهد محتوى منصة K-NEWS بجودة البث الفضائي الحية</p>
            </div>
        </div>

        <div class="filter-container">
            <a href="video-library.php?cat=الكل" class="filter-badge <?php echo $selected_category == 'الكل' ? 'active' : ''; ?>">
                <i class="fa-solid fa-layer-group" style="margin-left:6px;"></i> الكل
            </a>
            <?php foreach($categories as $cat): ?>
                <a href="video-library.php?cat=<?php echo urlencode($cat); ?>" 
                   class="filter-badge <?php echo $selected_category == $cat ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="library-grid">
            <?php if(empty($videos)): ?>
                <p style="color: #64748b; grid-column: 1/-1; text-align: center; padding: 60px; background: rgba(255,255,255,0.01); border-radius: 20px; border: 1px dashed #131a2a;">
                    <i class="fa-solid fa-video-slash" style="font-size: 30px; display: block; margin-bottom: 10px; color:#a855f7;"></i>
                    لا توجد فيديوهات في هذا التصنيف حالياً.
                </p>
            <?php else: ?>
                <?php foreach($videos as $video): 
                    $ytId = getYouTubeId($video['video_url']);
                    $thumbUrl = "https://img.youtube.com/vi/{$ytId}/mqdefault.jpg";
                ?>
                    <div class="cinema-card" onclick="startCinemaPlayer('<?php echo $ytId; ?>')">
                        <div class="cinema-thumb-box">
                            <img src="<?php echo $thumbUrl; ?>" class="cinema-thumb" alt="Cover">
                            <span class="cinema-category"><?php echo htmlspecialchars($video['category']); ?></span>
                            <div class="play-overlay">
                                <i class="fa-solid fa-circle-play"></i>
                            </div>
                        </div>
                        <div class="cinema-body">
                            <h3 class="cinema-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                            <p class="cinema-desc"><?php echo htmlspecialchars($video['description']); ?></p>
                        </div>
                        <div class="cinema-footer">
                            <span><i class="fa-solid fa-bolt" style="margin-left:5px; color:#3b82f6;"></i><?php echo number_format($video['views']); ?> VIEWS</span>
                            <span><i class="fa-regular fa-clock" style="margin-left:5px;"></i><?php echo date('Y/m/d', strtotime($video['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="cinema-modal" id="cinemaModal">
        <i class="fa-solid fa-xmark player-close" onclick="closeCinemaPlayer()"></i>
        <div class="player-container" id="playerContainer">
            </div>
    </div>

    <script>
        // تشغيل المشغل السينمائي وحقن الـ Iframe بشكل ديناميكي آمن لقراءة اليوتيوب تلقائياً
        function startCinemaPlayer(videoId) {
            const container = document.getElementById('playerContainer');
            container.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
            document.getElementById('cinemaModal').classType = "";
            document.getElementById('cinemaModal').classList.add('open');
        }

        // إغلاق المشغل وتفريغ الـ Container فوراً لإيقاف صوت وفيديو اليوتيوب تماماً
        function closeCinemaPlayer() {
            document.getElementById('cinemaModal').classList.remove('open');
            document.getElementById('playerContainer').innerHTML = '';
        }

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
                gradient.addColorStop(0.3, `rgba(59, 130, 246, ${m.opacity * 0.5})`); // وهج أزرق متناسق مع جودة العرض السينمائي للمكتبة
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