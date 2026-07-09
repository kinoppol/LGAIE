<?php
declare(strict_types=1);

$role     = current_role();
$all_courses = get_courses_with_stats();
$courses     = array_filter($all_courses, fn($c) => ($c['enrollment_status'] ?? 'active') === 'active');
$pending     = array_filter($all_courses, fn($c) => ($c['enrollment_status'] ?? 'active') === 'pending');
$archived    = is_teacher() ? get_archived_courses() : [];
?>

<div class="page-head" style="display:flex;align-items:flex-end;margin-bottom:22px">
  <div>
    <h1>รายวิชาทั้งหมด</h1>
    <p class="subtle" style="margin-top:6px;margin-bottom:0">
      <?= $role === 'teacher' ? 'รายวิชาที่คุณเป็นผู้สอน' : 'รายวิชาที่คุณลงทะเบียนเรียน' ?>
    </p>
  </div>
  <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
  <?php if (!empty($courses)): ?>
  <div class="view-toggle" role="group" aria-label="สลับมุมมอง">
    <button type="button" id="vt-grid" class="vt-btn" title="มุมมองตาราง" onclick="setCourseView('grid')">
      <?= icon('grid', 17) ?>
    </button>
    <button type="button" id="vt-list" class="vt-btn" title="มุมมองรายการ" onclick="setCourseView('list')">
      <?= icon('list', 17) ?>
    </button>
  </div>
  <?php endif; ?>
  <?php if (!is_teacher()): ?>
  <a href="<?= url('browse') ?>" class="btn btn-ghost" style="gap:8px;text-decoration:none">
    <?= icon('search', 18, 'var(--primary)') ?> ค้นหารายวิชา
  </a>
  <button class="btn btn-soft" style="gap:8px" onclick="openModal('join-course')">
    <?= icon('plus', 18, 'var(--primary)') ?> ลงทะเบียนด้วยรหัส
  </button>
  <?php else: ?>
  <button class="btn btn-primary" style="gap:8px" onclick="openModal('new-course')">
    <?= icon('plus', 18, '#fff') ?> สร้างรายวิชา
  </button>
  <?php endif; ?>
  </div>
</div>

<?php if (!empty($pending) && !is_teacher()): ?>
<!-- ── Pending Invitations ── -->
<div style="margin-bottom:2rem">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
    <?= icon('edit', 17, 'var(--accent)') ?>
    <h2 style="font-size:16px;font-weight:700;color:var(--heading)">คำเชิญที่รอตอบรับ</h2>
    <span class="badge" style="background:var(--warn-soft);color:#c76a13"><?= count($pending) ?> รายวิชา</span>
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(290px,1fr))">
    <?php foreach ($pending as $c): ?>
    <div class="course-card" style="opacity:.6;filter:saturate(.4);position:relative" id="pending-card-<?= $c['id'] ?>">
      <div class="course-card__banner" style="background:<?= h($c['banner']) ?>;color:<?= h($c['ink_color']) ?>">
        <span class="badge" style="background:rgba(255,255,255,.65);color:<?= h($c['ink_color']) ?>;font-size:11px;margin-bottom:8px">
          <?= h($c['code']) ?>
        </span>
        <h3><?= h($c['name']) ?></h3>
        <div class="cc-sec"><?= h($c['section']) ?></div>
        <?= course_avatar($c) ?>
      </div>
      <div class="course-card__body" style="padding:12px 16px 4px">
        <div style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--sub)">
          <?= icon('edit', 14) ?> ครูเชิญคุณเข้าเรียน
        </div>
      </div>
      <div class="course-card__foot" style="justify-content:flex-end;gap:8px;padding:10px 14px">
        <button class="btn btn-sm btn-ghost" style="font-size:12.5px"
                onclick="confirmDecline(<?= $c['id'] ?>, '<?= h(addslashes($c['name'])) ?>')">
          ปฏิเสธ
        </button>
        <button class="btn btn-sm btn-primary" style="font-size:12.5px;gap:5px"
                onclick="respondInvite(<?= $c['id'] ?>, 'accept', this)">
          <?= icon('check', 13, '#fff') ?> ตอบรับ
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (empty($courses)): ?>
<div class="empty">
  <div class="e-ic"><?= icon('grid', 30) ?></div>
  <h3>ยังไม่มีรายวิชา</h3>
  <p><?= is_teacher() ? 'กดปุ่ม "สร้างรายวิชา" เพื่อเริ่มต้น' : 'คุณยังไม่ได้ลงทะเบียนเรียนในรายวิชาใด' ?></p>
</div>
<?php else: ?>
<?php if (!is_teacher() && !empty($pending)): ?>
<h2 style="font-size:16px;font-weight:700;color:var(--heading);margin-bottom:14px;display:flex;align-items:center;gap:8px">
  <?= icon('book', 17, 'var(--primary)') ?> รายวิชาที่กำลังเรียน
</h2>
<?php endif; ?>
<div class="grid course-grid" id="course-grid" style="grid-template-columns:repeat(auto-fill,minmax(290px,1fr))">
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

<?php if (!is_teacher() && !empty($pending)): ?>
<script>
var _declineCourseId = null;
var _declineCourseName = '';

function confirmDecline(courseId, courseName) {
  _declineCourseId  = courseId;
  _declineCourseName = courseName;
  document.getElementById('decline-course-name').textContent = courseName;
  openModal('decline-confirm');
}

function doDecline() {
  closeModal('decline-confirm');
  var card = document.getElementById('pending-card-' + _declineCourseId);
  var fd = new FormData();
  fd.append('course_id', _declineCourseId);
  fd.append('action', 'decline');
  fetch('api/accept_invite.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        showToast(res.message || 'ปฏิเสธคำเชิญแล้ว');
        if (card) {
          card.style.transition = 'opacity .4s, transform .4s';
          card.style.opacity = '0'; card.style.transform = 'scale(.95)';
          setTimeout(() => card.remove(), 420);
        }
      } else {
        showToast(res.error || 'เกิดข้อผิดพลาด', true);
      }
    })
    .catch(() => showToast('เกิดข้อผิดพลาด', true));
}

function respondInvite(courseId, action, btn) {
  btn.disabled = true; btn.style.opacity = '.5';
  var fd = new FormData();
  fd.append('course_id', courseId);
  fd.append('action', action);
  fetch('api/accept_invite.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        showToast(res.message || 'สำเร็จ');
        var card = document.getElementById('pending-card-' + courseId);
        if (card) {
          card.style.transition = 'opacity .4s, transform .4s';
          card.style.opacity = '0'; card.style.transform = 'scale(.95)';
          setTimeout(() => {
            card.remove();
            if (action === 'accept') location.reload();
          }, 420);
        }
      } else {
        showToast(res.error || 'เกิดข้อผิดพลาด', true);
        btn.disabled = false; btn.style.opacity = '1';
      }
    })
    .catch(() => { showToast('เกิดข้อผิดพลาด', true); btn.disabled = false; btn.style.opacity = '1'; });
}
</script>

<!-- Decline confirmation modal -->
<div id="decline-confirm-overlay" class="modal-overlay" onclick="if(event.target===this)closeModal('decline-confirm')" style="display:none">
  <div class="modal" style="max-width:420px">
    <div class="modal__head">
      <span class="modal__ic" style="background:var(--danger-soft,#fee2e2);color:var(--danger,#ef4444)"><?php echo icon('x', 20, 'var(--danger,#ef4444)') ?></span>
      <h2 class="modal__title">ยืนยันการปฏิเสธ</h2>
      <button class="modal__close" onclick="closeModal('decline-confirm')"><?php echo icon('x', 18) ?></button>
    </div>
    <div class="modal__body">
      <p style="color:var(--body);line-height:1.7;margin:0">
        คุณต้องการปฏิเสธคำเชิญเข้าเรียน<br>
        รายวิชา <strong id="decline-course-name" style="color:var(--heading)"></strong> ใช่หรือไม่?
      </p>
      <p style="font-size:13px;color:var(--sub);margin:10px 0 0">
        หากปฏิเสธแล้วจะต้องขอให้ครูส่งคำเชิญใหม่อีกครั้ง
      </p>
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('decline-confirm')">ยกเลิก</button>
      <button type="button" class="btn" style="background:#ef4444;color:#fff;border-color:#ef4444" onclick="doDecline()">
        <?php echo icon('x', 15, '#fff') ?> ยืนยันปฏิเสธ
      </button>
    </div>
  </div>
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

<?php if (!is_teacher()):
    modal_start('join-course', 'ลงทะเบียนรายวิชา', 'plus', false, false);
?>
<form id="join-course-form" method="post" action="api/join_course.php" data-ajax>
  <p style="color:var(--sub);font-size:13.5px;margin-bottom:18px">
    กรอกรหัสเชิญที่ได้รับจากครูผู้สอน เพื่อลงทะเบียนเข้าเรียนในรายวิชา
  </p>
  <div class="field">
    <label class="field-label">รหัสเชิญ <span style="color:#ef4444">*</span></label>
    <input class="input" type="text" name="invite_code"
           placeholder="เช่น ABCD1234"
           style="text-transform:uppercase;font-size:1.3rem;letter-spacing:.18em;font-weight:700;text-align:center"
           maxlength="10" required autocomplete="off" spellcheck="false">
  </div>
</form>
<?php modal_foot('join-course', 'ยกเลิก', 'ลงทะเบียน', 'btn-primary'); ?>
<?php endif; ?>

<style>
.field-label { display:block; font-size:.82rem; font-weight:600; color:var(--heading); margin-bottom:.4rem; }

/* ── View toggle ── */
.view-toggle { display:flex; border:1px solid var(--line-2); border-radius:9px; overflow:hidden; background:var(--card); }
.vt-btn { display:grid; place-items:center; width:36px; height:36px; border:none; background:none;
          color:var(--sub); cursor:pointer; transition:background .12s, color .12s; }
.vt-btn:hover { background:var(--primary-soft); }
.vt-btn.active { background:var(--primary); color:#fff; }
.vt-btn + .vt-btn { border-left:1px solid var(--line-2); }

/* ── List view layout ── */
.course-grid.list-view { grid-template-columns:1fr !important; gap:10px; }
.course-grid.list-view .course-card {
    display:grid; grid-template-columns:210px 1fr; align-items:stretch; }
.course-grid.list-view .course-card__banner {
    grid-row:1 / span 2; min-height:0; }
.course-grid.list-view .course-card__banner h3 { font-size:1rem; }
.course-grid.list-view .course-card__body { grid-column:2; grid-row:1; align-self:end; }
.course-grid.list-view .course-card__foot { grid-column:2; grid-row:2; }
@media (max-width:560px) {
    .course-grid.list-view .course-card { grid-template-columns:120px 1fr; }
}
</style>

<script>
function setCourseView(mode) {
  var grid = document.getElementById('course-grid');
  if (!grid) return;
  grid.classList.toggle('list-view', mode === 'list');
  var g = document.getElementById('vt-grid'), l = document.getElementById('vt-list');
  if (g) g.classList.toggle('active', mode !== 'list');
  if (l) l.classList.toggle('active', mode === 'list');
  try { localStorage.setItem('courseView', mode); } catch (e) {}
}
setCourseView(((function(){ try { return localStorage.getItem('courseView'); } catch(e){ return null; } })()) === 'list' ? 'list' : 'grid');
</script>
<?php if (!is_teacher()): ?>
<script>
(function() {
    var params = new URLSearchParams(window.location.search);
    var join = params.get('join');
    if (join) {
        var inp = document.querySelector('#join-course-form [name="invite_code"]');
        if (inp) inp.value = join.toUpperCase();
        openModal('join-course');
        history.replaceState(null, '', '?page=courses');
    }
})();
</script>
<?php endif; ?>
