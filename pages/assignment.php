<?php
declare(strict_types=1);

$assignment_id = (int)($_GET['assignment_id'] ?? 0);
$a = get_assignment_with_prompt($assignment_id);
if (!$a) { echo '<div class="empty"><h3>ไม่พบงาน</h3></div>'; return; }

$c          = get_course((int)$a['course_id']);
$role       = current_role();
$uid        = current_user_id();
$subs       = get_submissions_for_assignment($assignment_id);
$graded_cnt = count(array_filter($subs, fn($s) => $s['status'] === 'graded'));
$better_cnt = count(array_filter($subs, fn($s) => $s['better_than_teacher']));

// My submission (student view)
$my_sub = null;
if (!is_teacher()) {
    foreach ($subs as $s) {
        if ((int)$s['student_id'] === $uid) { $my_sub = $s; break; }
    }
}

$total_enrolled = (int)db_val('SELECT COUNT(*) FROM course_enrollments WHERE course_id = ?', [$a['course_id']]);
?>

<div style="max-width:<?= is_teacher() ? '1100px' : '900px' ?>">
  <div class="breadcrumb">
    <a href="<?= url('courses') ?>">รายวิชา</a><?= icon('chevron-right', 14) ?>
    <a href="<?= url('course', ['course_id' => $c['id'], 'tab' => 'work']) ?>"><?= h($c['name'] ?? '') ?></a>
    <?= icon('chevron-right', 14) ?>
    <span style="color:var(--body);font-weight:600">งาน</span>
  </div>

  <!-- Assignment header -->
  <div class="card card-pad" style="margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:9px;margin-bottom:12px;flex-wrap:wrap">
      <span class="badge orange"><?= h($a['assignment_type']) ?></span>
      <span class="badge gray"><?= $a['points'] ?> คะแนน</span>
      <?php if ($a['allow_improve']): ?>
      <span class="badge blue"><?= icon('sparkle', 12) ?> ปรับ prompt ได้</span>
      <?php endif; ?>
      <?php $due_ts = thai_due_ts($a['due_date']); ?>
      <span class="badge orange" style="margin-left:auto" id="due-badge">
        <?= icon('clock', 13) ?> กำหนดส่ง <?= h($a['due_date']) ?><?php if ($due_ts): ?>
        <span id="due-remain" data-due-ts="<?= $due_ts ?>" style="opacity:.85"></span><?php endif; ?>
      </span>
      <?php if (is_teacher()): ?>
      <button class="btn btn-sm btn-ghost" onclick="openModal('edit-assignment')"><?= icon('edit', 15) ?> แก้ไข</button>
      <?php endif; ?>
    </div>
    <h1 style="font-size:24px;margin-bottom:12px"><?= h($a['title']) ?></h1>
    <p style="color:var(--body);font-size:15px;line-height:1.7;margin:0"><?= h($a['instructions']) ?></p>

    <?php if (is_teacher()): ?>
    <hr class="divider">
    <div style="display:flex;gap:24px;flex-wrap:wrap">
      <div class="stat" style="gap:11px">
        <span class="stat-ic" style="background:var(--accent-soft);color:var(--accent);width:40px;height:40px"><?= icon('send', 19) ?></span>
        <div><div class="stat-val" style="font-size:19px"><?= count($subs) ?>/<?= $total_enrolled ?></div><div class="stat-lbl">ส่งแล้ว</div></div>
      </div>
      <div class="stat" style="gap:11px">
        <span class="stat-ic" style="background:var(--primary-soft);color:var(--primary);width:40px;height:40px"><?= icon('check', 19) ?></span>
        <div><div class="stat-val" style="font-size:19px"><?= $graded_cnt ?></div><div class="stat-lbl">ตรวจแล้ว</div></div>
      </div>
      <div class="stat" style="gap:11px">
        <span class="stat-ic" style="background:var(--warn-soft);color:#c76a13;width:40px;height:40px"><?= icon('trophy', 19) ?></span>
        <div><div class="stat-val" style="font-size:19px"><?= $better_cnt ?></div><div class="stat-lbl">เคลม prompt ดีกว่า</div></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Teacher prompt block -->
  <div style="display:flex;align-items:center;gap:9px;margin-bottom:12px">
    <?= icon('sparkle', 20, 'var(--primary)') ?>
    <h2 style="font-size:18px">Prompt ตั้งต้นจากครู</h2>
  </div>
  <p class="subtle" style="font-size:14px;margin-top:-4px;margin-bottom:16px">
    <?= is_teacher()
      ? 'นี่คือ prompt ที่คุณแนบไว้ให้นักเรียนเริ่มต้น'
      : 'ครูทดลองแล้วได้ผลพอใช้ — ลองปรับแต่งให้ดีกว่านี้ แล้วระบุ prompt + AI ที่คุณใช้ตอนส่ง' ?>
  </p>
  <?php if (!empty($a['prompt'])): ?>
  <?php render_prompt_block($a['prompt'], 'Prompt ตั้งต้นที่ครูแนะนำ'); ?>
  <?php endif; ?>

  <hr class="divider" style="margin:28px 0">

  <?php if (is_teacher()): ?>
  <!-- ── Teacher grading view ─────────────────────────────── -->
  <div style="display:flex;align-items:center;margin-bottom:16px">
    <h2 style="font-size:18px">งานที่นักเรียนส่ง
      <span class="subtle" style="font-size:15px;font-weight:600">(<?= count($subs) ?>)</span>
    </h2>
    <div style="margin-left:auto;display:flex;gap:8px">
      <span class="chip">
        <span style="width:8px;height:8px;border-radius:50%;background:var(--warn)"></span>
        รอตรวจ <?= count($subs) - $graded_cnt ?>
      </span>
      <span class="chip">
        <span style="width:8px;height:8px;border-radius:50%;background:var(--primary)"></span>
        ตรวจแล้ว <?= $graded_cnt ?>
      </span>
    </div>
  </div>

  <?php if (empty($subs)): ?>
  <div class="empty">
    <div class="e-ic"><?= icon('clipboard', 30) ?></div>
    <h3>ยังไม่มีงานส่ง</h3>
  </div>
  <?php endif; ?>

  <?php
  $highlight_id = (int)($_GET['highlight'] ?? 0);
  foreach ($subs as $sub):
    $vote_count = (int)$sub['vote_count'];
  ?>
  <div class="card" id="sub-<?= (int)$sub['id'] ?>" style="margin-bottom:14px;transition:box-shadow .3s,outline .3s">
    <div style="padding:16px 20px;display:flex;align-items:center;gap:13px;border-bottom:1px solid var(--line)">
      <?= avatar(['avatar_class' => $sub['avatar_class'], 'initials' => $sub['initials']], 40) ?>
      <div>
        <div style="font-weight:700;color:var(--heading)"><?= h($sub['student_name']) ?></div>
        <div class="subtle" style="font-size:12.5px">ส่งเมื่อ <?= h(date('j M Y H:i', strtotime($sub['submitted_at']))) ?></div>
      </div>
      <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
        <?php if ($sub['better_than_teacher']): ?>
        <span class="badge orange"><?= icon('trophy', 13) ?> เคลม prompt ดีกว่า</span>
        <?php endif; ?>
        <?php if ($sub['status'] === 'graded'): ?>
        <span class="badge green"><?= icon('check', 13) ?> ให้คะแนนแล้ว · <?= $sub['grade'] ?>/<?= $a['points'] ?></span>
        <?php else: ?>
        <span class="badge gray">รอตรวจ</span>
        <?php endif; ?>
      </div>
    </div>
    <div style="padding:14px 20px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap">
        <span class="subtle" style="font-size:12.5px;font-weight:600">Prompt ที่นักเรียนใช้ · ตอบดีที่สุดด้วย</span>
        <?= ai_pill($sub['ai_used'], 'sm') ?>
        <span class="chip" style="font-size:11.5px">
          <?= icon('thumbs-up', 13, 'var(--accent)') ?> <?= $vote_count ?> โหวต
        </span>
      </div>
      <div class="prompt-text" style="font-size:12.5px"><?= h($sub['prompt_used']) ?></div>
      <?php if ($sub['compare_note']): ?>
      <div style="margin-top:10px;font-size:13px;color:var(--body);display:flex;gap:8px;align-items:flex-start">
        <?= icon('message', 15, 'var(--muted)') ?> <i>"<?= h($sub['compare_note']) ?>"</i>
      </div>
      <?php endif; ?>
      <?php if ($sub['feedback']): ?>
      <div class="note-box" style="margin-top:10px;padding:10px 12px;font-size:13px">
        <b>ความคิดเห็นครู:</b> <?= h($sub['feedback']) ?>
      </div>
      <?php endif; ?>
      <div style="display:flex;gap:10px;margin-top:14px">
        <button class="btn btn-sm <?= $sub['status'] === 'graded' ? 'btn-ghost' : 'btn-primary' ?>"
                onclick="openGradeModal(<?= json_encode([
                    'id'         => $sub['id'],
                    'name'       => $sub['student_name'],
                    'initials'   => $sub['initials'],
                    'av'         => $sub['avatar_class'],
                    'at'         => date('j M Y H:i', strtotime($sub['submitted_at'])),
                    'result'     => $sub['result_text'],
                    'ai'         => $sub['ai_used'],
                    'points'     => $a['points'],
                    'grade'      => $sub['grade'],
                    'feedback'   => $sub['feedback'],
                ], JSON_UNESCAPED_UNICODE) ?>)">
          <?= icon($sub['status'] === 'graded' ? 'edit' : 'check', 15, $sub['status'] !== 'graded' ? '#fff' : 'currentColor') ?>
          <?= $sub['status'] === 'graded' ? 'แก้ไขคะแนน' : 'ตรวจและให้คะแนน' ?>
        </button>
        <form method="post" action="api/vote_prompt.php" style="display:inline">
          <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
          <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
          <button class="btn btn-sm btn-ghost"><?= icon('thumbs-up', 15) ?> โหวตว่า prompt ดี</button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

<?php if ($highlight_id > 0): ?>
<style>
@keyframes highlight-pulse {
  0%   { box-shadow: 0 0 0 0 rgba(var(--primary-rgb,43,179,147), .7); outline: 3px solid var(--primary); }
  40%  { box-shadow: 0 0 0 12px rgba(var(--primary-rgb,43,179,147), 0); outline: 3px solid var(--primary); }
  60%  { box-shadow: 0 0 0 0 transparent; outline: 3px solid transparent; }
  80%  { box-shadow: 0 0 0 10px rgba(var(--primary-rgb,43,179,147), 0); outline: 3px solid var(--primary); }
  100% { box-shadow: 0 0 0 0 transparent; outline: 2px solid var(--primary); }
}
.sub-highlight {
  animation: highlight-pulse 1.8s ease forwards;
  border-radius: 14px;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var target = document.getElementById('sub-<?= $highlight_id ?>');
  if (!target) return;
  // Scroll to element with offset for topbar
  var top = target.getBoundingClientRect().top + window.scrollY - 80;
  window.scrollTo({ top: top, behavior: 'smooth' });
  // Start highlight after scroll settles
  setTimeout(function() {
    target.classList.add('sub-highlight');
  }, 600);
});
</script>
<?php endif; ?>

  <!-- Grading modal (populated by JS) -->
  <div class="modal-overlay" id="grade-modal-overlay" style="display:none" onclick="closeModalOnBg(event,'grade-modal')">
    <div class="modal" id="grade-modal">
      <div class="modal__head">
        <span style="width:38px;height:38px;border-radius:10px;background:var(--primary-soft);color:var(--primary);display:grid;place-items:center;flex:0 0 auto">
          <?= icon('check', 20) ?>
        </span>
        <h3 id="grade-modal-title">ตรวจงาน</h3>
        <button type="button" class="x-btn" onclick="closeModal('grade-modal')"><?= icon('x', 18) ?></button>
      </div>
      <form method="post" action="api/grade_submission.php" id="grade-form">
        <input type="hidden" name="submission_id" id="gf-sub-id">
        <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
        <div class="modal__body">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
            <span id="gf-avatar"></span>
            <div>
              <div style="font-weight:700;color:var(--heading)" id="gf-name"></div>
              <div class="subtle" style="font-size:12.5px" id="gf-at"></div>
            </div>
          </div>
          <div class="field">
            <label>ผลลัพธ์ที่นักเรียนได้จาก AI</label>
            <div id="gf-result" style="font-size:13.5px;color:var(--body);line-height:1.6;background:var(--surface-2);border:1px solid var(--line);border-radius:9px;padding:11px 13px"></div>
          </div>
          <div class="row" style="gap:14px">
            <div class="field" style="flex:0 0 160px">
              <label id="gf-pts-lbl">คะแนน</label>
              <input class="input" type="number" name="grade" id="gf-grade" placeholder="0" style="font-size:18px;font-weight:700">
            </div>
            <div class="field" style="flex:1">
              <label>AI ที่นักเรียนใช้</label>
              <div style="height:44px;display:flex;align-items:center" id="gf-ai"></div>
            </div>
          </div>
          <div class="field" style="margin-bottom:0">
            <label>ความคิดเห็น / ข้อเสนอแนะ</label>
            <textarea class="textarea" name="feedback" id="gf-feedback" placeholder="ให้ข้อเสนอแนะแก่นักเรียน…"></textarea>
          </div>
        </div>
        <div class="modal__foot">
          <button type="button" class="btn btn-ghost" onclick="closeModal('grade-modal')">ยกเลิก</button>
          <button type="submit" class="btn btn-primary"><?= icon('check', 16, '#fff') ?> บันทึกคะแนน</button>
        </div>
      </form>
    </div>
  </div>

  <?php
  // ── Edit assignment modal ──────────────────────────────────
  $ep = $a['prompt'];
  modal_start('edit-assignment', 'แก้ไขงาน', 'clipboard', true, true);
  ?>
  <form method="post" action="api/edit_assignment.php" data-ajax enctype="multipart/form-data">
    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
    <div class="field">
      <label>ชื่องาน <span style="color:var(--danger)">*</span></label>
      <input class="input" name="title" value="<?= h($a['title']) ?>" required>
    </div>
    <div class="row" style="gap:14px">
      <div class="field" style="flex:1">
        <label>ประเภทงาน</label>
        <select class="input" name="assignment_type">
          <?php foreach (['งาน', 'แบบทดสอบ', 'โปรเจกต์'] as $t): ?>
          <option value="<?= $t ?>" <?= $a['assignment_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="flex:0 0 110px">
        <label>คะแนนเต็ม</label>
        <input class="input" type="number" name="points" min="1" value="<?= $a['points'] ?>">
      </div>
    </div>
    <div class="field">
      <label>คำอธิบาย / คำสั่งงาน</label>
      <textarea class="textarea" name="instructions"><?= h($a['instructions']) ?></textarea>
    </div>
    <?php
      $_due_ts   = thai_due_ts($a['due_date']);
      $_due_iso  = $_due_ts ? date('Y-m-d', $_due_ts) : '';
      $_due_time = $_due_ts ? date('H:i',   $_due_ts) : '';
    ?>
    <div class="row" style="gap:14px">
      <div class="field" style="flex:1;margin-bottom:0">
        <label>วันกำหนดส่ง</label>
        <input class="input" type="date" name="due_date" min="<?= date('Y-m-d') ?>" value="<?= $_due_iso ?>">
      </div>
      <div class="field" style="flex:0 0 130px;margin-bottom:0">
        <label>เวลา</label>
        <input class="input" type="time" name="due_time" value="<?= $_due_time ?>">
      </div>
    </div>
    <div class="field">
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
        <input type="checkbox" name="allow_improve" value="1" <?= $a['allow_improve'] ? 'checked' : '' ?>
               style="width:16px;height:16px;accent-color:var(--primary)">
        <span>อนุญาตให้นักเรียนส่ง prompt ที่ดีกว่า</span>
      </label>
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
        <textarea class="textarea" name="prompt_text" style="font-family:ui-monospace,monospace;font-size:13px" required><?= h($ep['prompt_text'] ?? '') ?></textarea>
      </div>
      <div class="row" style="gap:14px">
        <div class="field" style="flex:1">
          <label>AI ที่ทดลองใช้แล้ว</label>
          <?= ai_select('ai_id', $ep['ai_id'] ?? '') ?>
        </div>
        <div class="field" style="flex:1">
          <label>ระดับความพอใจ</label>
          <?= star_input((int)($ep['rating'] ?? 4), 'rating') ?>
        </div>
      </div>
      <div class="field">
        <label>ผลลัพธ์ตัวอย่าง <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
        <textarea class="textarea" name="example_text" style="min-height:70px"><?= h($ep['example_text'] ?? '') ?></textarea>
        <?php example_file_input($ep['example_file'] ?? null, $ep['example_file_name'] ?? null) ?>
      </div>
      <div class="field">
        <label>หมายเหตุ/คำแนะนำ <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
        <textarea class="textarea" name="note_text" style="min-height:60px"><?= h($ep['note_text'] ?? '') ?></textarea>
      </div>
    </div>
  </form>
  <?php modal_foot('edit-assignment', 'ยกเลิก', 'บันทึกการแก้ไข'); ?>

  <?php else: ?>
  <!-- ── Student submit / submitted view ──────────────────── -->
  <?php if ($my_sub): ?>
  <!-- Already submitted -->
  <div class="card animate-in">
    <div class="card-head" style="background:var(--primary-soft);border-bottom:1px solid var(--primary-soft-2)">
      <?= icon('check-circle', 20, 'var(--primary)') ?>
      <h3 style="color:var(--primary-700)">ส่งงานเรียบร้อยแล้ว</h3>
      <span class="badge <?= $my_sub['status'] === 'graded' ? 'green' : 'gray' ?>" style="margin-left:auto">
        <?= $my_sub['status'] === 'graded' ? 'ได้คะแนน ' . $my_sub['grade'] . '/' . $a['points'] : 'รอตรวจ' ?>
      </span>
    </div>
    <div class="card-pad">
      <div class="field" style="margin-bottom:14px">
        <label>คำตอบที่ส่ง</label>
        <div style="font-size:14px;color:var(--body);line-height:1.6"><?= h($my_sub['answer_text'] ?: '— แนบไฟล์ —') ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <span class="subtle" style="font-size:12.5px;font-weight:600">AI ที่ใช้</span>
        <?= ai_pill($my_sub['ai_used'], 'sm') ?>
        <?php if ($my_sub['better_than_teacher']): ?>
        <span class="badge orange"><?= icon('trophy', 13) ?> ระบุว่าดีกว่า prompt ครู</span>
        <?php endif; ?>
      </div>
      <div class="prompt-text" style="margin-top:6px"><?= h($my_sub['prompt_used']) ?></div>
      <?php if ($my_sub['feedback']): ?>
      <div class="note-box" style="margin-top:14px;padding:12px 14px">
        <b>ความคิดเห็นจากครู:</b> <?= h($my_sub['feedback']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- Submit form -->
  <div class="card" style="border:2px solid var(--accent-soft)">
    <div class="card-head" style="background:var(--accent-soft);border-bottom:1px solid #d4e3fc">
      <span style="width:34px;height:34px;border-radius:9px;background:#fff;color:var(--accent);display:grid;place-items:center">
        <?= icon('send', 18) ?>
      </span>
      <h3 style="color:var(--accent-700)">ส่งงานของคุณ</h3>
      <span class="badge orange" style="margin-left:auto"><?= icon('clock', 13) ?> กำหนดส่ง <?= h($a['due_short']) ?></span>
    </div>
    <div class="card-pad">
      <form id="submit-form" method="post" action="api/submit_assignment.php">
        <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
        <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">

        <div class="field">
          <label>คำตอบ / ผลงานของคุณ <span style="color:var(--danger)">*</span></label>
          <textarea class="textarea" name="answer_text" placeholder="เขียนคำตอบหรือสรุปผลงานของคุณที่นี่…" required></textarea>
        </div>

        <div class="ai-tint-box" style="padding:16px;margin-top:6px">
          <div style="display:flex;align-items:center;gap:9px;margin-bottom:14px">
            <span style="width:30px;height:30px;border-radius:8px;background:var(--card);color:var(--primary);display:grid;place-items:center">
              <?= icon('sparkle', 17) ?>
            </span>
            <div>
              <div style="font-weight:700;color:var(--heading);font-size:14px">ระบุ AI และ Prompt ที่คุณใช้</div>
              <div class="subtle" style="font-size:12px">บอกครูว่าคุณค้นคว้าด้วย prompt อะไร และ AI ตัวไหนตอบได้ดีที่สุด</div>
            </div>
          </div>
          <div class="field">
            <label>Prompt ที่คุณใช้ <span style="color:var(--danger)">*</span></label>
            <textarea class="textarea" name="prompt_used" style="font-family:ui-monospace,monospace;font-size:13px;min-height:80px"
                      placeholder="วาง prompt ที่คุณปรับแต่งและใช้จริง…" required></textarea>
            <div class="hint">เคล็ดลับ: ลองเริ่มจาก prompt ของครูแล้วปรับให้ตรงกับสิ่งที่คุณเลือก</div>
          </div>
          <div class="field">
            <label>AI ที่ให้คำตอบดีที่สุด</label>
            <?= ai_select('ai_used', 'claude') ?>
          </div>
          <div class="field" style="margin-bottom:6px">
            <label>ผลลัพธ์ที่ได้จาก AI <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
            <textarea class="textarea" name="result_text" style="min-height:60px" placeholder="สรุปสั้น ๆ ว่า AI ตอบกลับมาอย่างไร…"></textarea>
          </div>
        </div>

        <?php if ($a['allow_improve']): ?>
        <div id="better-wrap" style="margin-top:16px;padding:14px 16px;border:1px solid var(--line-2);border-radius:10px;cursor:pointer;transition:all .15s">
          <label style="display:flex;align-items:flex-start;gap:11px;cursor:pointer">
            <input type="checkbox" name="better_than_teacher" value="1"
                   style="margin-top:3px;width:17px;height:17px;accent-color:var(--primary)"
                   onchange="toggleBetterBox(this)">
            <div style="flex:1">
              <div style="font-weight:700;color:var(--heading);font-size:14px;display:flex;align-items:center;gap:7px">
                <?= icon('trophy', 16, 'var(--warn)') ?> prompt ของฉันให้ผลลัพธ์ดีกว่าของครู
              </div>
              <div class="subtle" style="font-size:12.5px;margin-top:3px">ถ้าคุณคิดว่า prompt ที่ปรับแต่งดีกว่า บอกเหตุผลให้เพื่อนและครูโหวตได้</div>
              <textarea class="textarea animate-in" name="compare_note" id="compare-note"
                        style="min-height:56px;margin-top:10px;display:none"
                        placeholder="อธิบายว่าทำไม prompt ของคุณถึงดีกว่า เช่น เพิ่มเงื่อนไข / เจาะจงมากขึ้น…"></textarea>
            </div>
          </label>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:10px;margin-top:18px">
          <button type="submit" class="btn btn-accent">
            <?= icon('send', 17, '#fff') ?> ส่งงาน
          </button>
          <button type="button" class="btn btn-ghost">บันทึกร่าง</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; // my_sub ?>
  <?php endif; // is_teacher ?>
</div>

<script>
(function () {
  var el = document.getElementById('due-remain');
  if (!el) return;
  var ts = parseInt(el.dataset.dueTs, 10) * 1000;
  if (!ts) return;
  var badge = document.getElementById('due-badge');

  function fmt(n) { return String(n).padStart(2, '0'); }

  function tick() {
    var diff = ts - Date.now();
    if (diff <= 0) {
      el.textContent = ' · เกินกำหนดแล้ว';
      if (badge) { badge.style.background = '#fca5a5'; badge.style.color = '#7f1d1d'; }
      return;
    }
    var days  = Math.floor(diff / 86400000);
    var hours = Math.floor(diff % 86400000 / 3600000);
    var mins  = Math.floor(diff % 3600000 / 60000);
    var secs  = Math.floor(diff % 60000 / 1000);
    var next;

    if (days >= 7) {
      el.textContent = ' · อีก ' + days + ' วัน';
      next = 3600000;
    } else if (days >= 2) {
      el.textContent = ' · อีก ' + days + ' วัน';
      next = 60000;
    } else if (days >= 1) {
      el.textContent = ' · อีก 1 วัน ' + hours + ' ชม.';
      next = 60000;
    } else if (hours >= 1) {
      el.textContent = ' · อีก ' + fmt(hours) + ':' + fmt(mins) + ':' + fmt(secs);
      if (badge) { badge.style.background = '#fed7aa'; badge.style.color = '#7c2d12'; }
      next = 1000;
    } else {
      el.textContent = ' · อีก ' + fmt(mins) + ':' + fmt(secs);
      if (badge) { badge.style.background = '#fca5a5'; badge.style.color = '#7f1d1d'; }
      next = 1000;
    }
    setTimeout(tick, next);
  }
  tick();
})();
</script>
