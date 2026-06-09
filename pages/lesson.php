<?php
declare(strict_types=1);

// Lesson content is not accessible to guests
if (!is_logged_in()) {
    $redir = urlencode('index.php?page=lesson&lesson_id=' . (int)($_GET['lesson_id'] ?? 0));
    redirect('index.php?page=login&redirect=' . $redir);
}

$lesson_id = (int)($_GET['lesson_id'] ?? 0);
$lesson    = get_lesson_with_prompt($lesson_id);
if (!$lesson) { echo '<div class="empty"><h3>ไม่พบบทเรียน</h3></div>'; return; }

$c = get_course((int)$lesson['course_id']);
?>

<div style="max-width:880px">
  <div class="breadcrumb">
    <a href="<?= url('courses') ?>">รายวิชา</a><?= icon('chevron-right', 14) ?>
    <a href="<?= url('course', ['course_id' => $c['id'], 'tab' => 'lessons']) ?>"><?= h($c['name'] ?? '') ?></a>
    <?= icon('chevron-right', 14) ?>
    <span style="color:var(--body);font-weight:600">บทเรียน</span>
  </div>

  <!-- Content card -->
  <div class="card card-pad" style="margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <span class="badge green"><?= h($lesson['week_label']) ?></span>
      <span class="badge gray">เนื้อหาบทเรียน</span>
      <?php if (is_teacher()): ?>
      <button class="btn btn-sm btn-ghost" style="margin-left:auto">
        <?= icon('edit', 15) ?> แก้ไข
      </button>
      <?php endif; ?>
    </div>
    <h1 style="font-size:25px;margin-bottom:12px"><?= h($lesson['title']) ?></h1>
    <p style="color:var(--body);font-size:15px;line-height:1.7;margin:0"><?= h($lesson['description']) ?></p>

    <?php if (!empty($lesson['materials'])): ?>
    <hr class="divider">
    <div style="font-size:13px;font-weight:700;color:var(--heading);margin-bottom:12px">เอกสารประกอบ</div>
    <div class="row wrap">
      <?php foreach ($lesson['materials'] as $m): ?>
      <div style="display:flex;align-items:center;gap:11px;padding:11px 14px;border:1px solid var(--line-2);border-radius:10px;min-width:230px;cursor:pointer">
        <?= file_badge($m['file_type']) ?>
        <span style="font-size:13.5px;font-weight:600;color:var(--heading)"><?= h($m['name']) ?></span>
        <?= icon('download', 17, 'var(--muted)') ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- AI Prompt section -->
  <?php if (!empty($lesson['prompt'])): ?>
  <div style="display:flex;align-items:center;gap:9px;margin-bottom:12px">
    <?= icon('robot', 20, 'var(--primary)') ?>
    <h2 style="font-size:18px">ค้นคว้าต่อยอดด้วย AI</h2>
  </div>
  <p class="subtle" style="font-size:14px;margin-top:-4px;margin-bottom:16px">
    ครูทดลองใช้ prompt นี้แล้วได้ผลลัพธ์น่าพอใจ — คัดลอกไปลองใช้ แล้วปรับให้เข้ากับสิ่งที่คุณอยากรู้เพิ่มเติม
  </p>
  <?php render_prompt_block($lesson['prompt'], 'Prompt สำหรับค้นคว้าเพิ่มเติม'); ?>
  <?php endif; ?>

  <div style="display:flex;gap:10px;margin-top:22px">
    <a href="<?= url('course', ['course_id' => $c['id'], 'tab' => 'lessons']) ?>"
       class="btn btn-ghost" style="text-decoration:none">
      <?= icon('arrow-left', 17) ?> กลับไปหน้าบทเรียน
    </a>
  </div>
</div>
