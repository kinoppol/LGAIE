<?php
declare(strict_types=1);

$role    = current_role();
$user    = current_user();
$courses = get_courses_with_stats();
$all_assignments = db_rows('SELECT a.*, c.name AS course_name, c.primary_color AS course_color FROM assignments a JOIN courses c ON c.id = a.course_id WHERE c.is_archived = 0 ORDER BY a.id LIMIT 6');
$first_name = $user['name'] ?? '';
?>

<!-- Hero banner -->
<div class="card" style="background:linear-gradient(115deg,#d3f3e9 0%,#dcebff 55%,#e9e2ff 100%);border:none;margin-bottom:24px;overflow:hidden;position:relative">
  <div style="position:absolute;right:-30px;top:-40px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.45)"></div>
  <div style="position:absolute;right:90px;bottom:-70px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.32)"></div>
  <div class="card-pad" style="padding:26px 28px;position:relative">
    <div style="display:flex;align-items:center;gap:8px;color:#0f7d64;font-size:13.5px;font-weight:700;margin-bottom:8px">
      <?= icon('sparkle', 16, '#0f7d64') ?>
      <?= $role === 'teacher' ? 'แดชบอร์ดครูผู้สอน' : 'แดชบอร์ดผู้เรียน' ?>
    </div>
    <h1 style="color:#26324a;font-size:26px">สวัสดี, <?= h($first_name) ?> 👋</h1>
    <p style="color:#51607a;font-size:15px;max-width:560px;margin-top:8px;margin-bottom:0">
      <?= $role === 'teacher'
        ? 'เพิ่มเนื้อหาและงานพร้อม Prompt AI ที่คุณทดลองแล้วได้ผลดี เพื่อแนะแนวให้นักเรียนค้นคว้าต่อยอดอย่างชาญฉลาด'
        : 'เรียนรู้จาก Prompt ที่ครูแนะนำ แล้วลองปรับแต่งจนได้ผลลัพธ์ที่ดีกว่า — ระบุ AI และ prompt ที่คุณใช้ตอนส่งงานได้เลย' ?>
    </p>
    <div style="display:flex;gap:10px;margin-top:18px">
      <a href="<?= url('courses') ?>" class="btn btn-primary">
        <?= icon('grid', 17, '#fff') ?> ไปที่รายวิชา
      </a>
      <a href="<?= url($role === 'teacher' ? 'tograde' : 'todo') ?>"
         class="btn" style="background:rgba(255,255,255,.7);color:#0f7d64">
        <?= $role === 'teacher' ? 'งานรอตรวจ' : 'งานที่ต้องส่ง' ?>
        <?= icon('arrow-right', 16, '#0f7d64') ?>
      </a>
    </div>
  </div>
</div>

<!-- Stat cards -->
<div class="row wrap" style="margin-bottom:24px">
<?php if ($role === 'teacher'): ?>
  <?php
  $pending = count_pending_for_teacher();
  $prompt_cnt = (int)db_val('
        SELECT COUNT(*) FROM lesson_prompts lp
        JOIN lessons l ON l.id = lp.lesson_id
        JOIN courses c ON c.id = l.course_id
        WHERE c.is_archived = 0
    ') + (int)db_val('
        SELECT COUNT(*) FROM assignment_prompts ap
        JOIN assignments a ON a.id = ap.assignment_id
        JOIN courses c     ON c.id = a.course_id
        WHERE c.is_archived = 0
    ');
  $student_cnt = (int)db_val('
        SELECT COUNT(DISTINCT e.user_id) FROM course_enrollments e
        JOIN courses c ON c.id = e.course_id
        JOIN users u   ON u.id = e.user_id
        WHERE c.is_archived = 0 AND u.role = "student"
    ');
  ?>
  <div class="card card-pad" style="flex:1">
    <div class="stat">
      <span class="stat-ic" style="background:var(--primary-soft);color:#16a37a"><?= icon('grid', 23) ?></span>
      <div><div class="stat-val"><?= count($courses) ?></div><div class="stat-lbl">รายวิชาที่สอน</div></div>
    </div>
  </div>
  <div class="card card-pad" style="flex:1">
    <div class="stat">
      <span class="stat-ic" style="background:var(--warn-soft);color:#ff9f43"><?= icon('clipboard', 23) ?></span>
      <div><div class="stat-val"><?= $pending ?></div><div class="stat-lbl">งานรอตรวจ</div></div>
    </div>
  </div>
  <div class="card card-pad" style="flex:1">
    <div class="stat">
      <span class="stat-ic" style="background:var(--accent-soft);color:#3b7df5"><?= icon('sparkle', 23) ?></span>
      <div><div class="stat-val"><?= $prompt_cnt ?></div><div class="stat-lbl">Prompt ที่แชร์ไว้</div></div>
    </div>
  </div>
  <div class="card card-pad" style="flex:1">
    <div class="stat">
      <span class="stat-ic" style="background:#f1e9ff;color:#a371f7"><?= icon('users', 23) ?></span>
      <div><div class="stat-val"><?= $student_cnt ?></div><div class="stat-lbl">นักเรียนทั้งหมด</div></div>
    </div>
  </div>
<?php else: ?>
  <?php
  $uid = current_user_id();
  $submitted_cnt = (int)db_val('
        SELECT COUNT(*) FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        JOIN courses c     ON c.id = a.course_id
        WHERE s.student_id = ? AND c.is_archived = 0
    ', [$uid]);
  $pending_cnt = count_pending_for_student($uid);
  $graded = db_rows('
        SELECT s.grade, a.points FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        JOIN courses c     ON c.id = a.course_id
        WHERE s.student_id = ? AND s.status = "graded" AND c.is_archived = 0
    ', [$uid]);
  $avg = count($graded) ? round(array_sum(array_column($graded,'grade')) / array_sum(array_map(fn($r) => $r['points'], $graded)) * 100) : 0;
  ?>
  <div class="card card-pad" style="flex:1">
    <div class="stat">
      <span class="stat-ic" style="background:var(--accent-soft);color:#3b7df5"><?= icon('grid', 23) ?></span>
      <div><div class="stat-val"><?= count($courses) ?></div><div class="stat-lbl">รายวิชาที่ลงทะเบียน</div></div>
    </div>
  </div>
  <div class="card card-pad" style="flex:1">
    <div class="stat">
      <span class="stat-ic" style="background:var(--warn-soft);color:#ff9f43"><?= icon('clipboard', 23) ?></span>
      <div><div class="stat-val"><?= $pending_cnt ?></div><div class="stat-lbl">งานที่ต้องส่ง</div></div>
    </div>
  </div>
  <div class="card card-pad" style="flex:1">
    <div class="stat">
      <span class="stat-ic" style="background:var(--primary-soft);color:#16a37a"><?= icon('check-circle', 23) ?></span>
      <div><div class="stat-val"><?= $submitted_cnt ?></div><div class="stat-lbl">งานที่ส่งแล้ว</div></div>
    </div>
  </div>
  <div class="card card-pad" style="flex:1">
    <div class="stat">
      <span class="stat-ic" style="background:#f1e9ff;color:#a371f7"><?= icon('trophy', 23) ?></span>
      <div><div class="stat-val"><?= $avg ?>%</div><div class="stat-lbl">คะแนนเฉลี่ย</div></div>
    </div>
  </div>
<?php endif; ?>
</div>

<!-- Courses + Upcoming -->
<div class="row wrap" style="align-items:flex-start">

  <!-- Course grid -->
  <div style="flex:1 1 600px;min-width:0">
    <div style="display:flex;align-items:center;margin-bottom:16px">
      <h2 style="font-size:19px">รายวิชาของฉัน</h2>
      <a href="<?= url('courses') ?>" class="btn btn-sm btn-ghost" style="margin-left:auto">
        ดูทั้งหมด <?= icon('arrow-right', 15) ?>
      </a>
    </div>
    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(270px,1fr))">
      <?php foreach ($courses as $c): ?>
      <a href="<?= url('course', ['course_id' => $c['id'], 'tab' => 'stream']) ?>" class="course-card" style="text-decoration:none">
        <div class="course-card__banner" style="background:<?= h($c['banner']) ?>;color:<?= h($c['ink_color']) ?>">
          <span class="badge" style="background:rgba(255,255,255,.65);color:<?= h($c['ink_color']) ?>;font-size:11px;margin-bottom:8px"><?= h($c['code']) ?></span>
          <h3><?= h($c['name']) ?></h3>
          <div class="cc-sec"><?= h($c['section']) ?></div>
          <?= course_avatar($c) ?>
        </div>
        <div class="course-card__body">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px">
            <span class="subtle" style="font-size:12.5px;font-weight:600">ความคืบหน้าบทเรียน</span>
            <span style="font-size:12.5px;font-weight:700;color:<?= h($c['primary_color']) ?>">
              <?php
              $total = max(1, (int)$c['lesson_count']);
              // simple progress: just use static value for demo
              echo '—';
              ?>
            </span>
          </div>
          <div class="progress"><span style="width:60%;background:<?= h($c['primary_color']) ?>"></span></div>
        </div>
        <div class="course-card__foot">
          <span class="cf"><?= icon('book', 16) ?> <?= $c['lesson_count'] ?> บทเรียน</span>
          <span class="cf"><?= icon('clipboard', 16) ?> <?= $c['assignment_count'] ?> งาน</span>
          <span class="cf" style="margin-left:auto"><?= icon('users', 16) ?> <?= $c['student_count'] ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Upcoming sidebar -->
  <div style="flex:1 1 320px;min-width:300px">
    <div class="card">
      <div class="card-head">
        <?= icon('clock', 19, 'var(--warn)') ?>
        <h3><?= $role === 'teacher' ? 'กำหนดส่งที่ใกล้ถึง' : 'งานที่ต้องส่งเร็ว ๆ นี้' ?></h3>
      </div>
      <div style="padding:12px">
        <?php foreach ($all_assignments as $a): ?>
        <a href="<?= url('assignment', ['assignment_id' => $a['id']]) ?>"
           class="lrow" style="margin-bottom:8px;padding:12px 14px;text-decoration:none">
          <span class="lr-ic" style="background:<?= h($a['course_color']) ?>1c;color:<?= h($a['course_color']) ?>;width:38px;height:38px">
            <?= icon('clipboard', 18) ?>
          </span>
          <div style="min-width:0">
            <div class="lr-title" style="font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($a['title']) ?></div>
            <div class="lr-sub" style="font-size:12px"><?= h($a['course_name']) ?></div>
          </div>
          <span class="badge orange" style="margin-left:auto;font-size:11px"><?= h($a['due_short']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card ai-tint-box" style="margin-top:18px">
      <div class="card-pad">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
          <span style="width:36px;height:36px;border-radius:10px;background:var(--card);color:var(--accent);display:grid;place-items:center">
            <?= icon('robot', 20) ?>
          </span>
          <h3 style="font-size:15px">เคล็ดลับการใช้ AI</h3>
        </div>
        <p style="font-size:13.5px;color:var(--body);margin:0;line-height:1.6">
          <?= $role === 'teacher'
            ? 'ระบุ AI ที่ทดลองแล้วและให้ดาวความพอใจ เพื่อช่วยให้นักเรียนเริ่มต้นได้ถูกทาง'
            : 'อย่าคัดลอกคำตอบ AI ทั้งหมด — ลองปรับ prompt หลายครั้ง เปรียบเทียบหลาย AI แล้วเรียบเรียงเป็นภาษาของตัวเอง' ?>
        </p>
      </div>
    </div>
  </div>

</div>
