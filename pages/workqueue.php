<?php
declare(strict_types=1);

$role  = current_role();
$uid   = current_user_id();
$page  = $_GET['page'] ?? 'todo';

if (is_teacher()) {
    ensure_coteacher_schema();
    $items = db_rows('
        SELECT a.*, c.name AS course_name, c.primary_color AS course_color,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS sub_count,
            (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id AND s.status = "submitted") AS pending_count
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        WHERE c.is_archived = 0
          AND (c.teacher_id = ? OR c.id IN (SELECT course_id FROM course_teachers WHERE user_id = ?))
        ORDER BY a.due_date
    ', [$uid, $uid]);
} else {
    $items = db_rows('
        SELECT a.*, c.name AS course_name, c.primary_color AS course_color,
            (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) AS submitted
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        JOIN course_enrollments e ON e.course_id = a.course_id AND e.user_id = ?
        WHERE c.is_archived = 0
        ORDER BY a.due_date
    ', [$uid, $uid]);
}
?>

<div style="max-width:980px">
  <div class="page-head">
    <h1><?= is_teacher() ? 'งานรอตรวจ' : 'งานที่ต้องส่ง' ?></h1>
    <p class="subtle" style="margin-top:6px;margin-bottom:0">
      <?= is_teacher() ? 'งานจากทุกรายวิชาที่นักเรียนส่งเข้ามา' : 'รวมงานและการบ้านที่ใกล้ถึงกำหนดส่งจากทุกวิชา' ?>
    </p>
  </div>

  <?php foreach ($items as $a): ?>
  <a href="<?= url('assignment', ['assignment_id' => $a['id']]) ?>"
     class="lrow" style="align-items:flex-start;padding:18px 20px;text-decoration:none">
    <span class="lr-ic" style="background:<?= h($a['course_color']) ?>1c;color:<?= h($a['course_color']) ?>">
      <?= icon('clipboard', 20) ?>
    </span>
    <div style="min-width:0;flex:1">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
        <span class="badge" style="background:<?= h($a['course_color']) ?>1c;color:<?= h($a['course_color']) ?>;font-size:11px">
          <?= h($a['course_name']) ?>
        </span>
        <span class="badge orange" style="font-size:11px"><?= h($a['assignment_type']) ?></span>
      </div>
      <div class="lr-title"><?= h($a['title']) ?></div>
      <div class="lr-sub" style="margin-top:4px">กำหนดส่ง <?= h($a['due_date']) ?> · <?= $a['points'] ?> คะแนน</div>
    </div>
    <div class="lr-right">
      <?php if (is_teacher()): ?>
        <?php if ((int)$a['pending_count'] > 0): ?>
        <span class="badge orange"><?= icon('clock', 13) ?> รอตรวจ <?= $a['pending_count'] ?></span>
        <?php else: ?>
        <span class="badge green"><?= icon('check', 13) ?> ตรวจครบ</span>
        <?php endif; ?>
        <span class="subtle" style="font-size:12.5px">ส่งแล้ว <?= $a['sub_count'] ?></span>
      <?php else: ?>
        <?php if ($a['submitted']): ?>
        <span class="badge green"><?= icon('check', 13) ?> ส่งแล้ว</span>
        <?php else: ?>
        <span class="badge orange"><?= icon('clock', 13) ?> <?= h($a['due_short']) ?></span>
        <?php endif; ?>
      <?php endif; ?>
      <span class="btn btn-sm btn-soft">
        <?= is_teacher() ? 'ตรวจงาน' : 'ทำงาน' ?> <?= icon('arrow-right', 14) ?>
      </span>
    </div>
  </a>
  <?php endforeach; ?>

  <?php if (empty($items)): ?>
  <div class="empty">
    <div class="e-ic"><?= icon('check-circle', 30) ?></div>
    <h3><?= is_teacher() ? 'ไม่มีงานรอตรวจ' : 'ไม่มีงานค้าง' ?></h3>
    <p><?= is_teacher() ? 'นักเรียนยังไม่ส่งงาน หรือคุณตรวจครบทุกชิ้นแล้ว' : 'คุณส่งงานครบทุกชิ้นแล้ว' ?></p>
  </div>
  <?php endif; ?>
</div>
