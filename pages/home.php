<?php
declare(strict_types=1);
// db.php, functions.php, layout.php already loaded by index.php

ensure_directory_schema();

$is_logged_in   = is_logged_in();
$theme          = $_SESSION['theme'] ?? 'system';

$public_courses = db_rows("
    SELECT c.id, c.name, c.code, c.section, c.banner, c.ink_color,
           u.name AS teacher_name, u.avatar_class, u.avatar_path, u.initials,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS lesson_count,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) AS student_count
    FROM courses c
    JOIN users u ON u.id = c.teacher_id
    WHERE c.is_public = 1 AND c.is_archived = 0
    ORDER BY c.id DESC
");

$dir_teachers = db_rows('SELECT * FROM users WHERE role = "teacher" AND show_in_directory = 1 AND status = "active" ORDER BY name');
$dir_students = db_rows('SELECT * FROM users WHERE role = "student" AND show_in_directory = 1 AND status = "active" ORDER BY name');
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ClassroomAI — ระบบจัดการเรียนรู้พร้อม AI</title>
  <link rel="icon" href="<?= asset('assets/ovec-logo.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
  <script>
    (function(){
      var m = localStorage.getItem('ca-theme') || '<?= h($theme) ?>';
      var dark = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme:dark)').matches);
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
  </script>
  <style>
    * { box-sizing: border-box; }
    .home-wrap  { max-width: 1080px; margin: 0 auto; padding: 0 1.25rem 4rem; }

    /* Hero */
    .home-hero  { text-align: center; padding: 4rem 1rem 3.5rem; }
    .home-hero .hero-logo { display: flex; align-items: center; justify-content: center; gap: 14px; margin-bottom: 1.5rem; }
    .home-hero .hero-logo img { height: 56px; width: auto; }
    .home-hero .hero-logo span { font-size: 2rem; font-weight: 800; color: var(--heading); }
    .home-hero h1  { font-size: 1.5rem; font-weight: 700; color: var(--heading); margin: 0 0 .75rem; line-height: 1.4; }
    .home-hero p   { color: var(--sub); font-size: 1rem; margin: 0 0 2rem; max-width: 520px; margin-inline: auto; }
    .home-hero .cta-row { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

    /* Section */
    .home-sec { margin-top: 3rem; }
    .home-sec-hd { display: flex; align-items: center; gap: 9px; margin-bottom: 1.25rem; }
    .home-sec-hd h2 { font-size: 1.15rem; font-weight: 800; color: var(--heading); margin: 0; }
    .home-sec-hd .count { font-size: .8rem; font-weight: 600; color: var(--sub);
                          background: var(--line-2); padding: 2px 9px; border-radius: 99px; }

    /* Course grid */
    .home-course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1rem; }
    .home-course-card { background: var(--card); border: 1px solid var(--line-2); border-radius: 14px;
                        padding: 1.1rem 1.25rem; text-decoration: none; display: block;
                        transition: border-color .15s, transform .15s, box-shadow .15s; }
    .home-course-card:hover { border-color: var(--primary); transform: translateY(-2px);
                               box-shadow: 0 4px 18px rgba(0,0,0,.08); }
    .home-course-card .cc-title { font-size: .95rem; font-weight: 700; color: var(--heading);
                                   margin-bottom: .4rem; line-height: 1.4; }
    .home-course-card .cc-desc  { font-size: .82rem; color: var(--sub); margin-bottom: .85rem;
                                   display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
                                   overflow: hidden; min-height: 2.3em; }
    .home-course-card .cc-meta  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .home-course-card .cc-teacher { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0; }
    .home-course-card .cc-teacher span { font-size: .78rem; color: var(--sub);
                                          white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .home-course-card .cc-stats { display: flex; gap: 8px; font-size: .75rem; color: var(--sub); flex-shrink: 0; }

    /* People grid */
    .home-people  { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
    .home-person  { display: flex; flex-direction: row; align-items: flex-start; gap: 14px; padding: 16px;
                    background: var(--card); border: 1px solid var(--line-2); border-radius: 14px; }
    .home-person .pinfo  { flex: 1; min-width: 0; }
    .home-person .pmeta  { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
    .home-person .pname  { font-size: .9rem; font-weight: 700; color: var(--heading); line-height: 1.3; }
    .home-person .pschool { font-size: .78rem; color: var(--sub); line-height: 1.3; }
    .home-person .pbio   { font-size: 1rem; color: var(--heading); line-height: 1.55; font-style: italic;
                            font-weight: 500; margin-bottom: 6px; }

    .home-empty { padding: 1.5rem; background: var(--line-2); border-radius: 12px;
                  color: var(--sub); font-size: .875rem; text-align: center; }

    @media (max-width: 600px) {
      .home-hero h1 { font-size: 1.25rem; }
      .home-hero .hero-logo span { font-size: 1.5rem; }
      .home-hero .hero-logo img  { height: 42px; }
    }
  </style>
</head>
<body>

<!-- Topbar -->
<header style="background:var(--card);border-bottom:1px solid var(--line-2);padding:0 1.5rem;
               height:58px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:100;
               box-shadow:0 1px 4px rgba(0,0,0,.06)">
  <a href="index.php?page=home" style="display:flex;align-items:center;gap:9px;text-decoration:none">
    <img src="<?= asset('assets/ovec-logo.svg') ?>" alt="ClassroomAI" style="height:32px;width:auto">
    <span style="font-size:1rem;font-weight:800;color:var(--heading)">Classroom<span style="color:var(--primary)">AI</span></span>
  </a>
  <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
    <div class="theme-switch" id="theme-switch" title="โหมดสี">
      <button type="button" data-theme="light"  title="สว่าง"><?= icon('sun', 17) ?></button>
      <button type="button" data-theme="dark"   title="มืด"><?= icon('moon', 17) ?></button>
      <button type="button" data-theme="system" title="ตามระบบ"><?= icon('monitor', 17) ?></button>
    </div>
    <?php if ($is_logged_in): ?>
    <a href="index.php?page=dashboard" class="btn btn-primary" style="text-decoration:none;font-size:.875rem">
      <?= icon('home', 15, '#fff') ?> ไปที่ระบบ
    </a>
    <?php else: ?>
    <a href="index.php?page=login"    class="btn btn-ghost"   style="text-decoration:none;font-size:.875rem">เข้าสู่ระบบ</a>
    <a href="index.php?page=register" class="btn btn-primary" style="text-decoration:none;font-size:.875rem">สมัครสมาชิก</a>
    <?php endif; ?>
  </div>
</header>

<div class="home-wrap">

  <!-- ── Hero ──────────────────────────────────────────── -->
  <div class="home-hero">
    <div class="hero-logo">
      <img src="<?= asset('assets/ovec-logo.svg') ?>" alt="">
      <span>Classroom<span style="color:var(--primary)">AI</span></span>
    </div>
    <h1>ระบบจัดการเรียนรู้พร้อมคำแนะนำ AI สำหรับห้องเรียน</h1>
    <p>ครูแนบ Prompt AI ที่ทดสอบแล้วไว้กับบทเรียนและงาน นักเรียนส่งงานพร้อมระบุ Prompt ที่ใช้จริง</p>
    <div class="cta-row">
      <?php if ($is_logged_in): ?>
      <a href="index.php?page=dashboard" class="btn btn-primary" style="text-decoration:none;padding:.7rem 1.75rem;font-size:.95rem">
        <?= icon('home', 16, '#fff') ?> ไปที่ระบบ
      </a>
      <?php else: ?>
      <a href="index.php?page=login" class="btn btn-primary" style="text-decoration:none;padding:.7rem 1.75rem;font-size:.95rem">
        <?= icon('send', 16, '#fff') ?> เข้าสู่ระบบ
      </a>
      <a href="index.php?page=register" class="btn btn-soft" style="text-decoration:none;padding:.7rem 1.75rem;font-size:.95rem">
        สมัครสมาชิก
      </a>
      <?php endif; ?>
      <a href="index.php?page=explore" class="btn btn-ghost" style="text-decoration:none;padding:.7rem 1.5rem;font-size:.95rem">
        <?= icon('search', 15) ?> ค้นหารายวิชา
      </a>
    </div>
  </div>

  <hr style="border:none;border-top:1px solid var(--line-2);margin:0">

  <!-- ── รายวิชาสาธารณะ ────────────────────────────────── -->
  <div class="home-sec">
    <div class="home-sec-hd">
      <?= icon('book', 20, 'var(--primary)') ?>
      <h2>รายวิชาสาธารณะ</h2>
      <span class="count"><?= count($public_courses) ?> วิชา</span>
    </div>

    <?php if ($public_courses): ?>
    <div class="home-course-grid">
      <?php foreach ($public_courses as $c): ?>
      <a href="index.php?page=course&course_id=<?= $c['id'] ?>" class="home-course-card">
        <div class="cc-title"><?= h($c['name']) ?></div>
        <div class="cc-desc"><?= h(($c['code'] ? $c['code'] . ' ' : '') . ($c['section'] ?: '')) ?></div>
        <div class="cc-meta">
          <div class="cc-teacher">
            <?= avatar(['avatar_class' => $c['avatar_class'], 'avatar_path' => $c['avatar_path'], 'initials' => $c['initials']], 22) ?>
            <span><?= h($c['teacher_name']) ?></span>
          </div>
          <div class="cc-stats">
            <span title="บทเรียน"><?= icon('book', 13) ?> <?= $c['lesson_count'] ?></span>
            <span title="นักเรียน"><?= icon('users', 13) ?> <?= $c['student_count'] ?></span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="home-empty">ยังไม่มีรายวิชาสาธารณะในขณะนี้</div>
    <?php endif; ?>
  </div>

  <!-- ── ครูผู้สอน ─────────────────────────────────────── -->
  <div class="home-sec">
    <div class="home-sec-hd">
      <?= icon('users', 20, 'var(--primary)') ?>
      <h2>ครูผู้สอน</h2>
      <?php if ($dir_teachers): ?><span class="count"><?= count($dir_teachers) ?> คน</span><?php endif; ?>
    </div>

    <?php if ($dir_teachers): ?>
    <div class="home-people">
      <?php foreach ($dir_teachers as $t): ?>
      <div class="home-person">
        <?= avatar($t, 64) ?>
        <div class="pinfo">
          <?php if (!empty($t['bio'])): ?>
          <div class="pbio">"<?= h($t['bio']) ?>"</div>
          <?php endif; ?>
          <div class="pmeta">
            <span class="pname"><?= h($t['name']) ?></span>
            <?php if (!empty($t['school'])): ?>
            <span class="pschool"><?= h($t['school']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="home-empty">ยังไม่มีครูที่อนุญาตให้แสดงชื่อ</div>
    <?php endif; ?>
  </div>

  <!-- ── นักเรียน ──────────────────────────────────────── -->
  <div class="home-sec">
    <div class="home-sec-hd">
      <?= icon('users', 20, 'var(--primary)') ?>
      <h2>นักเรียนในระบบ</h2>
      <?php if ($dir_students): ?><span class="count"><?= count($dir_students) ?> คน</span><?php endif; ?>
    </div>

    <?php if ($dir_students): ?>
    <div class="home-people">
      <?php foreach ($dir_students as $s): ?>
      <div class="home-person">
        <?= avatar($s, 64) ?>
        <div class="pinfo">
          <?php if (!empty($s['bio'])): ?>
          <div class="pbio">"<?= h($s['bio']) ?>"</div>
          <?php endif; ?>
          <div class="pmeta">
            <span class="pname"><?= h($s['name']) ?></span>
            <?php if (!empty($s['school'])): ?>
            <span class="pschool"><?= h($s['school']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="home-empty">ยังไม่มีนักเรียนที่อนุญาตให้แสดงชื่อ</div>
    <?php endif; ?>
  </div>

</div><!-- .home-wrap -->

<!-- Toast -->
<div id="toast-container" style="position:fixed;bottom:26px;left:50%;transform:translateX(-50%);z-index:200;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none"></div>

<script>window.AI_TOOLS = <?= json_encode(array_values(get_ai_tools()), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
