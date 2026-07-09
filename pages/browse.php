<?php
declare(strict_types=1);

// Students browse & self-enroll in public courses. Teachers/admins get redirected.
if (is_teacher() || is_admin()) {
    redirect('index.php?page=courses');
}

$uid = current_user_id();
$q   = trim($_GET['q'] ?? '');

// Course IDs the student is already enrolled in (to show "ลงทะเบียนแล้ว")
$enrolled_ids = [];
try {
    foreach (db_rows('SELECT course_id FROM course_enrollments WHERE user_id = ?', [$uid]) as $r) {
        $enrolled_ids[(int)$r['course_id']] = true;
    }
} catch (PDOException) {}

$params = [];
$where  = 'c.is_public = 1 AND c.is_archived = 0';
if ($q !== '') {
    $where   .= ' AND (c.name LIKE ? OR c.code LIKE ? OR c.section LIKE ? OR u.name LIKE ?)';
    $like     = "%{$q}%";
    $params    = [$like, $like, $like, $like];
}

$courses = db_rows("
    SELECT c.*, u.name AS teacher_name, u.avatar_class AS teacher_av, u.avatar_path AS teacher_av_path, u.initials AS teacher_initials,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id)     AS lesson_count,
           (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) AS assignment_count,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) AS student_count
    FROM courses c
    JOIN users u ON u.id = c.teacher_id
    WHERE {$where}
    ORDER BY c.id DESC
", $params);
?>

<div class="page-head" style="margin-bottom:8px">
  <h1><?= icon('search', 24, 'var(--primary)') ?> ค้นหารายวิชา</h1>
  <p class="subtle" style="margin-top:6px;margin-bottom:0">
    รายวิชาสาธารณะที่ครูเปิดให้ลงทะเบียนเรียนได้ด้วยตนเอง
  </p>
</div>

<form method="get" action="index.php" style="display:flex;gap:8px;max-width:520px;margin:18px 0 6px">
  <input type="hidden" name="page" value="browse">
  <input class="input" type="text" name="q" value="<?= h($q) ?>"
         placeholder="ค้นหาชื่อวิชา รหัสวิชา หรือชื่อครู…" style="flex:1">
  <button class="btn btn-primary" type="submit"><?= icon('search', 16, '#fff') ?> ค้นหา</button>
  <?php if ($q !== ''): ?>
  <a href="<?= url('browse') ?>" class="btn btn-ghost" style="text-decoration:none">ล้าง</a>
  <?php endif; ?>
</form>

<div style="font-size:.88rem;color:var(--sub);margin:10px 0 4px">
  <?php if ($q !== ''): ?>
    ผลการค้นหา "<strong style="color:var(--heading)"><?= h($q) ?></strong>" — พบ <?= count($courses) ?> รายวิชา
  <?php else: ?>
    รายวิชาสาธารณะทั้งหมด <?= count($courses) ?> รายวิชา
  <?php endif; ?>
</div>

<?php if (empty($courses)): ?>
<div class="empty" style="margin-top:2rem">
  <div class="e-ic"><?= icon('search', 30) ?></div>
  <h3>ไม่พบรายวิชา</h3>
  <p>ลองค้นหาด้วยคำอื่น<?php if ($q !== ''): ?> หรือ <a href="<?= url('browse') ?>">ดูรายวิชาทั้งหมด</a><?php endif; ?></p>
</div>
<?php else: ?>
<div class="course-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1rem;margin-top:14px">
  <?php foreach ($courses as $c):
      $cid        = (int)$c['id'];
      $is_enroll  = isset($enrolled_ids[$cid]);
  ?>
  <div class="course-card" id="browse-card-<?= $cid ?>" style="display:block">
    <a href="<?= url('course', ['course_id' => $cid, 'tab' => 'lessons']) ?>"
       class="course-card__banner" style="background:<?= h($c['banner']) ?>;color:<?= h($c['ink_color']) ?>;text-decoration:none;display:block">
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
    </a>
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
    <div style="padding:0 14px 14px">
      <?php if ($is_enroll): ?>
      <a href="<?= url('course', ['course_id' => $cid, 'tab' => 'lessons']) ?>"
         class="btn btn-soft" style="width:100%;justify-content:center;text-decoration:none">
        <?= icon('check', 15, 'var(--primary)') ?> ลงทะเบียนแล้ว — เข้าเรียน
      </a>
      <?php else: ?>
      <button class="btn btn-primary" style="width:100%;justify-content:center"
              id="enroll-btn-<?= $cid ?>"
              onclick="enrollPublic(<?= $cid ?>, '<?= h(addslashes($c['name'])) ?>')">
        <?= icon('plus', 15, '#fff') ?> ลงทะเบียนเรียน
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
function enrollPublic(cid, name) {
  var btn = document.getElementById('enroll-btn-' + cid);
  if (btn) { btn.disabled = true; btn.style.opacity = '.6'; }
  var fd = new FormData();
  fd.append('course_id', cid);
  fetch('api/enroll_public.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        showToast(res.message);
        setTimeout(function(){ location.href = 'index.php?page=course&course_id=' + cid + '&tab=lessons'; }, 700);
      } else {
        showToast(res.error || 'เกิดข้อผิดพลาด', true);
        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
      }
    })
    .catch(function(){
      showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', true);
      if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    });
}
</script>
