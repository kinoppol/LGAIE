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
    <p style="color:var(--body);font-size:15px;line-height:1.7;margin:0"><?= format_instructions($lesson['description'] ?? '') ?></p>

    <?php if (!empty($lesson['materials'])): ?>
    <hr class="divider">
    <div style="font-size:13px;font-weight:700;color:var(--heading);margin-bottom:12px">เอกสารประกอบ</div>
    <div class="row wrap">
      <?php foreach ($lesson['materials'] as $m): ?>
      <?= attachment_item($m) ?>
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
<form method="post" action="api/edit_lesson.php" data-ajax enctype="multipart/form-data">
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
  <?php if (!empty($lesson['materials'])): ?>
  <div class="field">
    <label>ไฟล์ประกอบเนื้อหาที่มีอยู่</label>
    <?php foreach ($lesson['materials'] as $m): ?>
    <div class="mat-row" style="display:flex;align-items:center;gap:8px;padding:7px 12px;margin-bottom:6px;
                                border:1.5px solid var(--line-2);border-radius:8px;background:var(--surface-2)">
      <!-- enabled = จะถูกลบเมื่อบันทึก (toggle ผ่าน toggleMatRemove) -->
      <input type="hidden" name="remove_materials[]" value="<?= (int)$m['id'] ?>" disabled>
      <?= icon('paperclip', 14, 'var(--sub)') ?>
      <span class="mat-name" style="font-size:12.5px;color:var(--body);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        <?= h($m['name']) ?>
      </span>
      <?php if ((int)($m['file_size'] ?? 0) > 0): ?>
      <span class="subtle" style="font-size:11.5px;flex:0 0 auto"><?= format_bytes((int)$m['file_size']) ?></span>
      <?php endif; ?>
      <button type="button" title="ลบไฟล์นี้เมื่อบันทึก" onclick="toggleMatRemove(this)"
              style="width:24px;height:24px;border-radius:6px;border:none;cursor:pointer;flex:0 0 auto;
                     background:#fee2e2;color:#ef4444;font-weight:700;line-height:1">✕</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php multi_file_input('materials', 'เพิ่มไฟล์ประกอบเนื้อหา') ?>

  <!-- Prompt AI (ไม่บังคับ — ขยายอัตโนมัติถ้ามี prompt อยู่แล้ว) -->
  <?php $has_prompt = !empty($p['prompt_text']); ?>
  <div id="el-prompt-section" style="display:<?= $has_prompt ? 'block' : 'none' ?>">
    <div class="ai-tint-box" style="padding:16px 16px 6px;margin-top:6px">
      <div style="display:flex;align-items:center;gap:9px;margin-bottom:12px">
        <span style="width:32px;height:32px;border-radius:9px;background:var(--card);color:var(--primary);display:grid;place-items:center"><?= icon('sparkle', 18) ?></span>
        <div>
          <div style="font-weight:700;color:var(--heading);font-size:14.5px">Prompt AI ที่แนะนำ <span style="font-weight:400;color:var(--sub);font-size:12px">(ไม่บังคับ)</span></div>
          <div class="subtle" style="font-size:12px">ระบุ prompt และ AI ที่คุณทดลองแล้วได้ผลลัพธ์น่าพอใจ</div>
        </div>
        <button type="button"
                onclick="document.getElementById('el-prompt-section').style.display='none';document.getElementById('el-prompt-text').value='';document.getElementById('el-add-prompt-btn').style.display='flex'"
                style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--sub);display:flex;align-items:center;gap:4px;font-size:12px">
          <?= icon('x', 14) ?> ลบออก
        </button>
      </div>
      <div class="field">
        <label>ข้อความ Prompt</label>
        <textarea id="el-prompt-text" class="textarea" name="prompt_text" style="font-family:ui-monospace,monospace;font-size:13px"><?= h($p['prompt_text'] ?? '') ?></textarea>
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
        <?php example_file_input($p['example_file'] ?? null, $p['example_file_name'] ?? null) ?>
      </div>
      <div class="field">
        <label>หมายเหตุ/คำแนะนำ <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
        <textarea class="textarea" name="note_text" style="min-height:60px"><?= h($p['note_text'] ?? '') ?></textarea>
      </div>
    </div>
  </div>
  <button type="button" id="el-add-prompt-btn"
          onclick="document.getElementById('el-prompt-section').style.display='block';this.style.display='none'"
          style="display:<?= $has_prompt ? 'none' : 'flex' ?>;align-items:center;gap:7px;margin-top:10px;background:none;
                 border:1.5px dashed var(--line-2);border-radius:9px;padding:8px 14px;
                 cursor:pointer;color:var(--sub);font-size:13px;width:100%;justify-content:center;
                 transition:border-color .15s,color .15s"
          onmouseenter="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
          onmouseleave="this.style.borderColor='var(--line-2)';this.style.color='var(--sub)'">
    <?= icon('sparkle', 15) ?> + เพิ่ม Prompt AI ที่แนะนำ
  </button>
</form>
<?php modal_foot('edit-lesson', 'ยกเลิก', 'บันทึกการแก้ไข'); ?>
<?php endif; ?>
