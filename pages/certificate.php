<?php
declare(strict_types=1);

if (!is_logged_in()) {
    header('Location: ../index.php?page=login');
    exit;
}

$course_id  = (int)($_GET['course_id']  ?? 0);
$student_id = (int)($_GET['student_id'] ?? current_user_id());

if (!is_teacher() && $student_id !== current_user_id()) {
    http_response_code(403);
    echo '<p>ไม่มีสิทธิ์ดูเกียรติบัตรนี้</p>';
    exit;
}

ensure_certificate_schema();

$course = get_course($course_id);
if (!$course) { echo '<p>ไม่พบรายวิชา</p>'; exit; }

$student = db_row('SELECT * FROM users WHERE id = ? AND role = "student"', [$student_id]);
if (!$student) { echo '<p>ไม่พบข้อมูลนักเรียน</p>'; exit; }

$cert = db_row('SELECT * FROM course_certificates WHERE course_id = ?', [$course_id]);
if (!$cert || !$cert['enabled']) {
    echo '<p>รายวิชานี้ยังไม่เปิดระบบเกียรติบัตร</p>'; exit;
}

$asgn_info  = db_row('SELECT COUNT(*) AS cnt, COALESCE(SUM(points),0) AS total FROM assignments WHERE course_id = ?', [$course_id]);
$total_asgn = (int)($asgn_info['cnt']   ?? 0);
$total_pts  = (int)($asgn_info['total'] ?? 0);

$score = db_row('
    SELECT COUNT(DISTINCT s.id) AS submitted_count,
        COALESCE(SUM(CASE WHEN s.status = "graded" THEN s.grade ELSE 0 END),0) AS earned_points
    FROM assignments a
    LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
    WHERE a.course_id = ?
', [$student_id, $course_id]);

$submitted = (int)($score['submitted_count'] ?? 0);
$earned    = (int)($score['earned_points']   ?? 0);
$pct       = $total_pts > 0 ? round($earned / $total_pts * 100, 1) : 0;

if ($submitted < $total_asgn) {
    echo '<p style="padding:2rem;font-family:sans-serif">ส่งงานยังไม่ครบ (' . $submitted . '/' . $total_asgn . ' งาน)</p>'; exit;
}

$grades = json_decode($cert['grade_json'] ?? '[]', true) ?: [];
usort($grades, fn($a, $b) => ($b['min']??0) <=> ($a['min']??0));
$grade_label = '';
foreach ($grades as $g) {
    if ($pct >= (float)($g['min']??0)) { $grade_label = (string)($g['label']??''); break; }
}

if (!$grade_label) {
    echo '<p style="padding:2rem;font-family:sans-serif">คะแนน ' . $pct . '% ไม่ผ่านเกณฑ์รับเกียรติบัตร (ต้องการ ≥' . ($grades ? (int)end($grades)['min'] : 0) . '%)</p>'; exit;
}

$teacher = db_row('SELECT * FROM users WHERE id = ?', [$course['teacher_id']]);
$theme   = $_SESSION['theme'] ?? 'system';
$today   = date('j F Y', strtotime('+543 years', strtotime(date('Y-m-d'))));
// Thai month names
$months_th = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน',
              'กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
$d = getdate();
$date_th = $d['mday'] . ' ' . $months_th[$d['mon']] . ' ' . ($d['year'] + 543);
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เกียรติบัตร — <?= h($student['name']) ?></title>
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
    body { margin: 0; padding: 0; background: var(--bg); font-family: 'Sarabun', 'Noto Sans Thai', sans-serif; }

    .no-print { background: var(--card); border-bottom: 1px solid var(--line-2); padding: 14px 24px;
                display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .no-print a { text-decoration: none; color: var(--sub); font-size: 14px; }
    .no-print a:hover { color: var(--heading); }

    .cert-page { max-width: 780px; margin: 36px auto; padding: 0 16px 60px; }

    .cert-box {
      background: var(--card);
      border: 2px solid var(--line-2);
      border-radius: 20px;
      padding: 56px 64px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .cert-box::before {
      content: '';
      position: absolute;
      inset: 8px;
      border: 1.5px solid var(--line-2);
      border-radius: 14px;
      pointer-events: none;
    }

    .cert-banner {
      height: 10px;
      border-radius: 6px 6px 0 0;
      margin: -56px -64px 40px;
    }

    .cert-logo { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 32px; }
    .cert-logo img { height: 44px; width: auto; }
    .cert-logo span { font-size: 1.3rem; font-weight: 800; color: var(--heading); }

    .cert-label { font-size: 11px; letter-spacing: .18em; text-transform: uppercase;
                   color: var(--sub); font-weight: 700; margin-bottom: 8px; }
    .cert-title { font-size: 2.25rem; font-weight: 800; color: var(--heading);
                   line-height: 1.2; margin-bottom: 6px; }
    .cert-subtitle { font-size: .95rem; color: var(--sub); margin-bottom: 40px; }

    .cert-to { font-size: 13px; color: var(--sub); margin-bottom: 10px; }
    .cert-name { font-size: 2rem; font-weight: 800; color: var(--primary);
                  margin-bottom: 6px; line-height: 1.2; }
    .cert-school { font-size: 13.5px; color: var(--sub); margin-bottom: 36px; }

    .cert-divider { width: 60px; height: 3px; background: var(--primary); border-radius: 99px;
                     margin: 0 auto 28px; }

    .cert-desc { font-size: 14.5px; color: var(--body); line-height: 1.7; margin-bottom: 24px; }

    .cert-grade-badge {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 10px 24px;
      background: var(--primary-soft);
      border-radius: 99px;
      font-size: 1.1rem; font-weight: 800; color: var(--primary);
      margin-bottom: 32px;
    }

    .cert-score { font-size: 13px; color: var(--sub); margin-bottom: 40px; }

    .cert-sigs { display: flex; justify-content: center; gap: 60px; flex-wrap: wrap; margin-top: 8px; }
    .cert-sig { text-align: center; min-width: 140px; }
    .cert-sig-line { width: 120px; height: 1.5px; background: var(--line-2); margin: 0 auto 8px; }
    .cert-sig-name { font-size: 13.5px; font-weight: 700; color: var(--heading); }
    .cert-sig-role { font-size: 11.5px; color: var(--sub); margin-top: 2px; }

    .cert-date { font-size: 12px; color: var(--sub); margin-top: 28px; }

    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
      .cert-page { margin: 0; padding: 0; max-width: 100%; }
      .cert-box { border-color: #ccc; border-radius: 0; page-break-inside: avoid; }
    }
  </style>
</head>
<body>

<div class="no-print">
  <a href="index.php?page=course&course_id=<?= $course_id ?>&tab=scores">
    ← กลับไปหน้าคะแนน
  </a>
  <div style="margin-left:auto;display:flex;gap:10px">
    <button onclick="window.print()" class="btn btn-primary" style="gap:7px">
      <?= icon('printer', 16, '#fff') ?> พิมพ์เกียรติบัตร
    </button>
  </div>
</div>

<div class="cert-page">
  <div class="cert-box">

    <div class="cert-banner" style="background:<?= h($course['banner'] ?: 'linear-gradient(135deg,var(--primary),var(--primary-dark,var(--primary)))') ?>"></div>

    <div class="cert-logo">
      <img src="<?= asset('assets/ovec-logo.svg') ?>" alt="ClassroomAI">
      <span>Classroom<span style="color:var(--primary)">AI</span></span>
    </div>

    <div class="cert-label">Certificate of Achievement</div>
    <div class="cert-title">เกียรติบัตร</div>
    <div class="cert-subtitle">ใบรับรองความสำเร็จทางการเรียน</div>

    <div class="cert-divider"></div>

    <div class="cert-to">มอบให้แก่</div>
    <div class="cert-name"><?= h($student['name']) ?></div>
    <?php if (!empty($student['school'])): ?>
    <div class="cert-school"><?= h($student['school']) ?></div>
    <?php endif; ?>

    <div class="cert-desc">
      ได้สำเร็จการศึกษาในรายวิชา<br>
      <strong style="color:var(--heading)"><?= h($course['name']) ?></strong>
      <?php if (!empty($course['code']) || !empty($course['section'])): ?>
      <br><span style="font-size:13px"><?= h(trim(($course['code']??'') . ' ' . ($course['section']??''))) ?></span>
      <?php endif; ?>
    </div>

    <div class="cert-grade-badge">
      <?= icon('trophy', 20, 'var(--primary)') ?>
      <?= h($grade_label) ?>
    </div>

    <div class="cert-score">
      คะแนนที่ได้ <?= $earned ?>/<?= $total_pts ?> คะแนน (<?= $pct ?>%)
      &ensp;·&ensp; ส่งงานครบ <?= $submitted ?> ชิ้น
    </div>

    <div class="cert-sigs">
      <div class="cert-sig">
        <div class="cert-sig-line"></div>
        <div class="cert-sig-name"><?= h($teacher['name'] ?? 'ครูผู้สอน') ?></div>
        <div class="cert-sig-role">ครูผู้สอน</div>
        <?php if (!empty($teacher['school'])): ?>
        <div class="cert-sig-role" style="margin-top:2px"><?= h($teacher['school']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="cert-date"><?= $date_th ?></div>

  </div>
</div>

<script>window.AI_TOOLS = [];</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
<?php exit; ?>
