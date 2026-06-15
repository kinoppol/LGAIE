<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ถ้าล็อกอินแล้วให้ไปหน้า courses
if (is_logged_in()) {
    redirect('index.php?page=courses');
}

$q        = trim($_GET['q'] ?? '');
$theme    = $_SESSION['theme'] ?? 'system';

// ดึงรายวิชาสาธารณะ
$params = [];
$where  = 'c.is_public = 1 AND c.is_archived = 0';
if ($q !== '') {
    $where   .= ' AND (c.name LIKE ? OR c.code LIKE ? OR c.section LIKE ? OR u.name LIKE ?)';
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

$courses = db_rows("
    SELECT c.*, u.name AS teacher_name, u.avatar_class AS teacher_av, u.avatar_path AS teacher_av_path, u.initials AS teacher_initials,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) AS assignment_count,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) AS student_count
    FROM courses c
    JOIN users u ON u.id = c.teacher_id
    WHERE {$where}
    ORDER BY c.id DESC
", $params);
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ค้นหารายวิชา — ClassroomAI</title>
  <link rel="stylesheet" href="css/theme.css">
  <script>
    (function(){
      var m = localStorage.getItem('ca-theme') || '<?= h($theme) ?>';
      var dark = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme:dark)').matches);
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
  </script>
  <style>
    .explore-wrap { max-width: 1100px; margin: 0 auto; padding: 0 1.25rem 3rem; }
    .explore-hero { text-align: center; padding: 3rem 1rem 2rem; }
    .explore-hero h1 { font-size: 2rem; font-weight: 800; color: var(--heading); margin-bottom: .5rem; }
    .explore-hero p  { color: var(--sub); font-size: 1rem; margin: 0; }
    .search-bar { display: flex; gap: 0; max-width: 560px; margin: 1.5rem auto 0; }
    .search-bar input { flex: 1; border-radius: 12px 0 0 12px; border-right: none; height: 46px; }
    .search-bar button { border-radius: 0 12px 12px 0; height: 46px; padding: 0 20px; }
    .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1rem; margin-top: 2rem; }
  </style>
</head>
<body>

<!-- Topbar -->
<header style="background:var(--card);border-bottom:1px solid var(--line-2);padding:0 1.5rem;
               height:58px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:100;
               box-shadow:0 1px 4px rgba(0,0,0,.06)">
  <a href="index.php?page=explore" style="display:flex;align-items:center;gap:9px;text-decoration:none">
    <span style="width:34px;height:34px;border-radius:9px;background:var(--primary);display:grid;place-items:center">
      <?= icon('sparkle', 18, '#fff') ?>
    </span>
    <span style="font-size:1rem;font-weight:800;color:var(--heading)">Classroom<span style="color:var(--primary)">AI</span></span>
  </a>
  <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
    <div class="theme-switch" id="theme-switch" title="โหมดสี">
      <button type="button" data-theme="light" title="สว่าง"><?= icon('sun', 17) ?></button>
      <button type="button" data-theme="dark"  title="มืด"><?= icon('moon', 17) ?></button>
      <button type="button" data-theme="system" title="ตามระบบ"><?= icon('monitor', 17) ?></button>
    </div>
    <a href="index.php?page=login"    class="btn btn-ghost"   style="text-decoration:none;font-size:.875rem">เข้าสู่ระบบ</a>
    <a href="index.php?page=register" class="btn btn-primary" style="text-decoration:none;font-size:.875rem">สมัครสมาชิก</a>
  </div>
</header>

<div class="explore-wrap">

  <!-- Hero -->
  <div class="explore-hero">
    <h1><?= icon('search', 28, 'var(--primary)') ?> ค้นหารายวิชาสาธารณะ</h1>
    <p>รายวิชาที่ครูเปิดให้สาธารณะดูรายชื่อหน่วยการเรียนได้ สมัครสมาชิกเพื่อเข้าถึงเนื้อหาและ Prompt AI ฉบับเต็ม</p>

    <form method="get" action="index.php" class="search-bar">
      <input type="hidden" name="page" value="explore">
      <input class="input" type="text" name="q" value="<?= h($q) ?>"
             placeholder="ค้นหาชื่อวิชา รหัสวิชา หรือชื่อครู…">
      <button class="btn btn-primary" type="submit">ค้นหา</button>
    </form>
  </div>

  <!-- Results header -->
  <div style="display:flex;align-items:center;gap:10px;margin-top:.5rem">
    <span style="font-size:.9rem;color:var(--sub)">
      <?php if ($q !== ''): ?>
        ผลการค้นหา "<strong style="color:var(--heading)"><?= h($q) ?></strong>" — พบ <?= count($courses) ?> รายวิชา
      <?php else: ?>
        รายวิชาสาธารณะทั้งหมด <?= count($courses) ?> รายวิชา
      <?php endif; ?>
    </span>
    <?php if ($q !== ''): ?>
    <a href="index.php?page=explore" style="font-size:.82rem;color:var(--primary);text-decoration:none">ล้างการค้นหา</a>
    <?php endif; ?>
  </div>

  <!-- Course grid -->
  <?php if (empty($courses)): ?>
  <div class="empty" style="margin-top:2rem">
    <div class="e-ic"><?= icon('search', 30) ?></div>
    <h3>ไม่พบรายวิชา</h3>
    <p>ลองค้นหาด้วยคำอื่น หรือ<?php if ($q !== ''): ?> <a href="index.php?page=explore">ดูรายวิชาทั้งหมด</a><?php endif; ?></p>
  </div>
  <?php else: ?>
  <div class="course-grid">
    <?php foreach ($courses as $c): ?>
    <a href="index.php?page=course&course_id=<?= (int)$c['id'] ?>&tab=lessons"
       class="course-card" style="text-decoration:none;display:block">
      <div class="course-card__banner" style="background:<?= h($c['banner']) ?>;color:<?= h($c['ink_color']) ?>">
        <span class="badge" style="background:rgba(255,255,255,.65);color:<?= h($c['ink_color']) ?>;font-size:11px;margin-bottom:8px">
          <?= h($c['code']) ?>
        </span>
        <h3><?= h($c['name']) ?></h3>
        <div class="cc-sec"><?= h($c['section']) ?></div>
        <?= course_avatar($c) ?>
        <span class="badge" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,.8);
              color:#16a37a;font-size:10.5px;font-weight:700">
          <?= icon('globe', 11, '#16a37a') ?> สาธารณะ
        </span>
      </div>
      <div class="course-card__body">
        <div style="display:flex;align-items:center;gap:7px">
          <?= avatar(['avatar_class' => $c['teacher_av'] ?? 'av-1', 'avatar_path' => $c['teacher_av_path'] ?? '', 'initials' => $c['teacher_initials'] ?? '?'], 24) ?>
          <span style="font-size:12.5px;color:var(--sub);font-weight:600"><?= h($c['teacher_name']) ?></span>
        </div>
      </div>
      <div class="course-card__foot">
        <span class="cf"><?= icon('book', 16) ?> <?= $c['lesson_count'] ?> บทเรียน</span>
        <span class="cf"><?= icon('clipboard', 16) ?> <?= $c['assignment_count'] ?> งาน</span>
        <span class="cf" style="margin-left:auto"><?= icon('users', 16) ?> <?= $c['student_count'] ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- CTA -->
  <div class="card" style="margin-top:2.5rem;text-align:center;padding:2rem;background:var(--primary-soft);border:1px solid var(--primary-soft-2)">
    <h3 style="font-size:1.1rem;color:var(--heading);margin-bottom:.5rem"><?= icon('sparkle', 18, 'var(--primary)') ?> สนใจเข้าเรียน?</h3>
    <p style="color:var(--sub);font-size:.9rem;margin-bottom:1.25rem">
      สมัครสมาชิกเพื่อเข้าถึงเนื้อหาบทเรียน Prompt AI ที่ครูแนะนำ และส่งงานได้ครบวงจร
    </p>
    <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap">
      <a href="index.php?page=register" class="btn btn-primary" style="text-decoration:none">สมัครสมาชิกฟรี</a>
      <a href="index.php?page=login"    class="btn btn-ghost"   style="text-decoration:none">เข้าสู่ระบบ</a>
    </div>
  </div>

</div><!-- .explore-wrap -->

<!-- Toast container -->
<div id="toast-container" style="position:fixed;bottom:26px;left:50%;transform:translateX(-50%);z-index:200;
     display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none"></div>

<script>
window.AI_TOOLS = [];
</script>
<script src="js/app.js"></script>
</body>
</html>
