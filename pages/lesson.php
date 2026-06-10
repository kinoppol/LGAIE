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
      <button class="btn btn-sm btn-ghost" style="margin-left:auto" onclick="openModal('edit-lesson')">
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

<?php if (is_teacher()):
    modal_start('edit-lesson', 'แก้ไขบทเรียน', 'book', true, true);
    $p = $lesson['prompt'];
?>
<form method="post" action="api/edit_lesson.php" data-ajax>
  <input type="hidden" name="lesson_id" value="<?= $lesson_id ?>">
  <div class="field">
    <label>หัวข้อบทเรียน <span style="color:var(--danger)">*</span></label>
    <input class="input" name="title" value="<?= h($lesson['title']) ?>" required>
  </div>
  <div class="field">
    <label>สัปดาห์/หน่วย <span style="color:var(--danger)">*</span></label>
    <input class="input" name="week_label" value="<?= h($lesson['week_label']) ?>" required>
  </div>
  <div class="field">
    <label>คำอธิบายเนื้อหา</label>
    <textarea class="textarea" name="description"><?= h($lesson['description']) ?></textarea>
  </div>
  <div class="ai-tint-box" style="padding:16px 16px 6px;margin-top:6px">
    <div style="display:flex;align-items:center;gap:9px;margin-bottom:12px">
      <span style="width:32px;height:32px;border-radius:9px;background:var(--card);color:var(--primary);display:grid;place-items:center"><?= icon('sparkle', 18) ?></span>
      <div>
        <div style="font-weight:700;color:var(--heading);font-size:14.5px">Prompt AI ที่แนะนำ</div>
        <div class="subtle" style="font-size:12px">ระบุ prompt และ AI ที่คุณทดลองแล้วได้ผลลัพธ์น่าพอใจ</div>
      </div>
    </div>
    <div class="field">
      <label>ข้อความ Prompt <span style="color:var(--danger)">*</span></label>
      <textarea class="textarea" name="prompt_text" style="font-family:ui-monospace,monospace;font-size:13px" required><?= h($p['prompt_text'] ?? '') ?></textarea>
    </div>
    <div class="row" style="gap:14px">
      <div class="field" style="flex:1">
        <label>AI ที่ทดลองใช้แล้ว</label>
        <?= ai_select('ai_id', $p['ai_id'] ?? '') ?>
      </div>
      <div class="field" style="flex:1">
        <label>ระดับความพอใจ</label>
        <?= star_input((int)($p['rating'] ?? 4), 'rating') ?>
      </div>
    </div>
    <div class="field">
      <label>ผลลัพธ์ตัวอย่าง <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
      <textarea class="textarea" name="example_text" style="min-height:70px"><?= h($p['example_text'] ?? '') ?></textarea>
      <?php example_file_input($p['example_file'] ?? null) ?>
    </div>
    <div class="field">
      <label>หมายเหตุ/คำแนะนำ <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
      <textarea class="textarea" name="note_text" style="min-height:60px"><?= h($p['note_text'] ?? '') ?></textarea>
    </div>
  </div>
</form>
<?php modal_foot('edit-lesson', 'ยกเลิก', 'บันทึกการแก้ไข'); ?>
<?php endif; ?>
