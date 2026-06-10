<?php
declare(strict_types=1);

$role     = current_role();
$courses  = get_courses_with_stats();             // active only
$archived = is_teacher() ? get_archived_courses() : [];
?>

<div class="page-head" style="display:flex;align-items:flex-end;margin-bottom:22px">
  <div>
    <h1>รายวิชาทั้งหมด</h1>
    <p class="subtle" style="margin-top:6px;margin-bottom:0">
      <?= $role === 'teacher' ? 'รายวิชาที่คุณเป็นผู้สอน' : 'รายวิชาที่คุณลงทะเบียนเรียน' ?>
    </p>
  </div>
  <?php if (is_teacher()): ?>
  <button class="btn btn-primary" style="margin-left:auto;gap:8px" onclick="openModal('new-course')">
    <?= icon('plus', 18, '#fff') ?> สร้างรายวิชา
  </button>
  <?php endif; ?>
</div>

<?php if (empty($courses)): ?>
<div class="empty">
  <div class="e-ic"><?= icon('grid', 30) ?></div>
  <h3>ยังไม่มีรายวิชา</h3>
  <p><?= is_teacher() ? 'กดปุ่ม "สร้างรายวิชา" เพื่อเริ่มต้น' : 'คุณยังไม่ได้ลงทะเบียนเรียนในรายวิชาใด' ?></p>
</div>
<?php else: ?>
<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(290px,1fr))">
  <?php foreach ($courses as $c): ?>
  <a href="<?= url('course', ['course_id' => $c['id'], 'tab' => 'stream']) ?>" class="course-card" style="text-decoration:none;display:block">
    <div class="course-card__banner" style="background:<?= h($c['banner']) ?>;color:<?= h($c['ink_color']) ?>">
      <span class="badge" style="background:rgba(255,255,255,.65);color:<?= h($c['ink_color']) ?>;font-size:11px;margin-bottom:8px">
        <?= h($c['code']) ?>
      </span>
      <h3><?= h($c['name']) ?></h3>
      <div class="cc-sec"><?= h($c['section']) ?></div>
      <?= course_avatar($c) ?>
    </div>
    <div class="course-card__body">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px">
        <span class="subtle" style="font-size:12.5px;font-weight:600">บทเรียนและงาน</span>
        <?php if (!empty($c['is_public'])): ?>
        <span class="badge green" style="font-size:10px">สาธารณะ</span>
        <?php endif; ?>
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

<?php if (!empty($archived)): ?>
<!-- ── Archived Courses ── -->
<div style="margin-top:2.5rem">
  <button onclick="toggleEl('archived-list','archived-toggle','▸ รายวิชาที่จัดเก็บ (<?= count($archived) ?>)','▾ รายวิชาที่จัดเก็บ (<?= count($archived) ?>)')"
          style="display:flex;align-items:center;gap:8px;background:none;border:none;cursor:pointer;
                 color:var(--sub);font-size:.9rem;font-weight:600;padding:0;margin-bottom:1rem">
    <span id="archived-toggle">▸ รายวิชาที่จัดเก็บ (<?= count($archived) ?>)</span>
  </button>

  <div id="archived-list" style="display:none">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1rem">
      <?php foreach ($archived as $a): ?>
      <div class="course-card" style="position:relative;border:1.5px dashed var(--line-2)">

        <!-- Banner (คลิกเข้าดูรายวิชาได้) -->
        <a href="<?= url('course', ['course_id' => $a['id'], 'tab' => 'stream']) ?>"
           style="text-decoration:none;display:block">
          <div class="course-card__banner"
               style="background:<?= h($a['banner']) ?>;color:<?= h($a['ink_color']) ?>;filter:saturate(.35) brightness(.9)">
            <span class="badge" style="background:rgba(255,255,255,.55);color:<?= h($a['ink_color']) ?>;font-size:11px;margin-bottom:6px">
              <?= h($a['code']) ?>
            </span>
            <h3 style="font-size:1rem"><?= h($a['name']) ?></h3>
            <div class="cc-sec" style="font-size:12px"><?= h($a['section']) ?></div>
            <?= course_avatar($a, ' style="opacity:.65"') ?>
            <span style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,.5);color:#fff;
                          padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                          display:flex;align-items:center;gap:5px">
              <?= icon('folder', 12, '#fff') ?> จัดเก็บแล้ว
            </span>
          </div>
        </a>

        <!-- Footer -->
        <div class="course-card__foot" style="justify-content:space-between">
          <span class="cf"><?= icon('book', 15) ?> <?= $a['lesson_count'] ?></span>
          <span class="cf"><?= icon('clipboard', 15) ?> <?= $a['assignment_count'] ?></span>
          <div style="margin-left:auto;display:flex;gap:6px">
            <button class="btn btn-sm btn-ghost" style="gap:5px;color:var(--primary)"
                    title="นำกลับมาใช้งาน"
                    onclick="restoreCourse(<?= (int)$a['id'] ?>, this)">
              <?= icon('arrow-right', 13, 'var(--primary)') ?> นำกลับ
            </button>
            <a href="<?= url('course_settings', ['course_id' => $a['id']]) ?>"
               class="btn btn-sm btn-ghost" style="text-decoration:none" title="ตั้งค่ารายวิชา">
              <?= icon('settings', 13) ?>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
function restoreCourse(courseId, btn) {
  btn.disabled = true;
  btn.style.opacity = '.5';
  var body = new FormData();
  body.append('course_id', courseId);
  body.append('action', 'restore');
  fetch('api/archive_course.php', { method: 'POST', body: body })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.ok) {
        showToast(res.message || 'นำกลับมาใช้งานแล้ว');
        setTimeout(function() { location.reload(); }, 900);
      } else {
        showToast(res.error || 'เกิดข้อผิดพลาด', true);
        btn.disabled = false; btn.style.opacity = '1';
      }
    })
    .catch(function(err) {
      showToast('เกิดข้อผิดพลาด: ' + (err.message || ''), true);
      btn.disabled = false; btn.style.opacity = '1';
    });
}
</script>
<?php endif; ?>

<?php if (is_teacher()):
    modal_start('new-course', 'สร้างรายวิชาใหม่', 'grid', false, true);
?>
<form method="post" action="api/create_course.php" data-ajax>

  <div style="display:grid;grid-template-columns:1fr 2fr;gap:1rem;margin-bottom:1rem">
    <div>
      <label class="field-label">รหัสวิชา <span style="color:#ef4444">*</span></label>
      <input class="input" type="text" name="code" placeholder="เช่น ว31104" required
             style="text-transform:uppercase;font-weight:600;letter-spacing:.04em">
    </div>
    <div>
      <label class="field-label">ชื่อรายวิชา <span style="color:#ef4444">*</span></label>
      <input class="input" type="text" name="name" placeholder="เช่น วิทยาการคำนวณ" required>
    </div>
  </div>

  <div style="margin-bottom:1rem">
    <label class="field-label">กลุ่ม / ห้อง / ภาคเรียน <span style="color:#ef4444">*</span></label>
    <input class="input" type="text" name="section"
           placeholder="เช่น ม.4/2 · ห้อง 314 หรือ ภาคต้น 2568" required>
  </div>

  <div style="margin-bottom:1rem">
    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:.65rem 1rem;
                  border:1.5px solid var(--line-2);border-radius:9px;background:var(--bg)">
      <input type="checkbox" name="is_public" value="1" style="width:16px;height:16px;accent-color:var(--primary)">
      <div>
        <div style="font-size:.875rem;font-weight:600;color:var(--heading)">รายวิชาสาธารณะ</div>
        <div style="font-size:.78rem;color:var(--sub)">บุคคลทั่วไปสามารถลงทะเบียนเรียนได้โดยไม่ต้องรับเชิญ</div>
      </div>
    </label>
  </div>

  <div style="padding:.85rem 1rem;background:var(--primary-soft);border-radius:10px;
              font-size:.8rem;color:var(--primary);display:flex;gap:8px;align-items:flex-start">
    <?= icon('sparkle', 15, 'var(--primary)') ?>
    <span>สีและชื่อย่อจะถูกกำหนดอัตโนมัติ สามารถแก้ไขได้ภายหลังในหน้าตั้งค่ารายวิชา</span>
  </div>

</form>
<?php modal_foot('new-course', 'ยกเลิก', 'สร้างรายวิชา', 'btn-primary'); ?>
<?php endif; ?>

<style>
.field-label { display:block; font-size:.82rem; font-weight:600; color:var(--heading); margin-bottom:.4rem; }
</style>
