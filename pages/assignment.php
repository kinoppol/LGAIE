<?php
declare(strict_types=1);

$assignment_id = (int)($_GET['assignment_id'] ?? 0);
$a = get_assignment_with_prompt($assignment_id);
if (!$a) { echo '<div class="empty"><h3>ไม่พบงาน</h3></div>'; return; }

$c          = get_course((int)$a['course_id']);
$role       = current_role();
$uid        = current_user_id();
$is_quiz    = is_quiz_assignment_type($a['assignment_type']);
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

// ไฟล์แนบของแต่ละ submission (จัดกลุ่มตาม submission_id)
$files_by_sub = [];
try {
    $all_files = db_rows('
        SELECT f.* FROM submission_files f
        JOIN submissions s ON s.id = f.submission_id
        WHERE s.assignment_id = ? ORDER BY f.id', [$assignment_id]);
    foreach ($all_files as $f) $files_by_sub[(int)$f['submission_id']][] = $f;
} catch (PDOException) {
    // ตาราง submission_files ยังไม่ถูกสร้าง (จะถูกสร้างเมื่อมีการอัปโหลดครั้งแรก)
}
?>

<div style="max-width:<?= is_teacher() ? '1100px' : '900px' ?>">
  <div class="breadcrumb">
    <a href="<?= url('courses') ?>">รายวิชา</a><?= icon('chevron-right', 14) ?>
    <a href="<?= url('course', ['course_id' => $c['id'], 'tab' => 'lessons']) ?>"><?= h($c['name'] ?? '') ?></a>
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
      <button class="btn btn-sm btn-ghost" style="color:var(--danger)"
              onclick="confirmDeleteAssignment(<?= (int)$a['id'] ?>, '<?= h(addslashes($a['title'])) ?>')"><?= icon('trash', 15) ?> ลบงาน</button>
      <?php endif; ?>
    </div>
    <h1 style="font-size:24px;margin-bottom:12px"><?= h($a['title']) ?></h1>
    <div style="color:var(--body);font-size:15px;line-height:1.7;margin:0"><?= format_instructions($a['instructions'] ?? '') ?></div>

    <?php if (!empty($a['links'])): ?>
    <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach ($a['links'] as $lnk): ?>
      <a href="<?= h($lnk['url']) ?>" target="_blank" rel="noopener noreferrer"
         style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;border:1px solid var(--line-2);background:var(--card);color:var(--primary);font-size:13px;text-decoration:none;transition:background .15s"
         onmouseover="this.style.background='var(--primary-soft)'" onmouseout="this.style.background='var(--card)'">
        <?= icon('link', 13) ?> <?= h($lnk['label'] ?: $lnk['url']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

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

  <?php if ($is_quiz):
    $quiz_questions = get_quiz_questions($assignment_id);
    $quiz_total_pts = array_sum(array_column($quiz_questions, 'points'));
  ?>
  <?php if (is_teacher()): ?>
  <!-- ── Teacher: คำถามในแบบทดสอบ (พร้อมเฉลย) ─────────────────── -->
  <div style="display:flex;align-items:center;gap:9px;margin-bottom:16px">
    <?= icon('clipboard', 20, 'var(--primary)') ?>
    <h2 style="font-size:18px">คำถามในแบบทดสอบ
      <span class="subtle" style="font-size:15px;font-weight:600">(<?= count($quiz_questions) ?> ข้อ · <?= $quiz_total_pts ?> คะแนน)</span>
    </h2>
  </div>
  <?php if (empty($quiz_questions)): ?>
  <div class="empty"><div class="e-ic"><?= icon('clipboard', 30) ?></div><h3>ยังไม่มีคำถาม</h3><p>แก้ไขงานนี้เพื่อเพิ่มคำถามแบบเลือกตอบ 4 ตัวเลือก</p></div>
  <?php endif; ?>
  <?php foreach ($quiz_questions as $qi => $q): ?>
  <div class="card card-pad" style="margin-bottom:12px">
    <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px">
      <span style="width:26px;height:26px;border-radius:8px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;display:grid;place-items:center;flex:0 0 auto"><?= $qi + 1 ?></span>
      <div style="flex:1">
        <div style="font-size:14.5px;font-weight:600;color:var(--heading);line-height:1.5"><?= h($q['question_text']) ?></div>
        <div class="subtle" style="font-size:12px;margin-top:2px"><?= $q['points'] ?> คะแนน</div>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;padding-left:36px">
      <?php foreach ($q['choices'] as $ch): ?>
      <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;<?= $ch['is_correct'] ? 'color:var(--primary);font-weight:700' : 'color:var(--body)' ?>">
        <?php if ($ch['is_correct']): ?>
        <?= icon('check-circle', 15, 'var(--primary)') ?>
        <?php else: ?>
        <span style="width:15px;height:15px;border-radius:50%;border:1.5px solid var(--line-2);display:inline-block;flex:0 0 auto"></span>
        <?php endif; ?>
        <?= h($ch['choice_text']) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <hr class="divider" style="margin:28px 0">

  <!-- ── ผลคะแนนนักเรียน ───────────────────────────────────────── -->
  <div style="display:flex;align-items:center;margin-bottom:16px">
    <h2 style="font-size:18px">ผลคะแนนนักเรียน
      <span class="subtle" style="font-size:15px;font-weight:600">(<?= count($subs) ?>/<?= $total_enrolled ?> คน)</span>
    </h2>
    <?php if (count($subs) > 0 && $quiz_total_pts > 0):
        $avg = round(array_sum(array_column($subs, 'grade')) / count($subs), 1);
    ?>
    <span class="chip" style="margin-left:auto">เฉลี่ย <?= $avg ?>/<?= $quiz_total_pts ?></span>
    <?php endif; ?>
  </div>
  <?php if (empty($subs)): ?>
  <div class="empty"><div class="e-ic"><?= icon('trophy', 30) ?></div><h3>ยังไม่มีนักเรียนทำแบบทดสอบ</h3></div>
  <?php else: ?>
  <div class="card">
    <div style="padding:6px 10px">
      <?php foreach ($subs as $s): ?>
      <div style="display:flex;align-items:center;gap:13px;padding:12px;border-bottom:1px solid var(--line-1)">
        <?= avatar(['avatar_class' => $s['avatar_class'], 'avatar_path' => $s['avatar_path'] ?? null, 'initials' => $s['initials']], 36) ?>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;color:var(--heading);font-size:13.5px"><?= h($s['student_name']) ?></div>
          <div class="subtle" style="font-size:11.5px">ส่งเมื่อ <?= h(date('j M Y H:i', strtotime($s['submitted_at']))) ?></div>
        </div>
        <span class="badge <?= $quiz_total_pts > 0 && (int)$s['grade'] >= $quiz_total_pts * 0.5 ? 'green' : 'orange' ?>" style="font-size:12px;flex:0 0 auto">
          <?= (int)$s['grade'] ?>/<?= $quiz_total_pts ?> คะแนน
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php else: /* ── นักเรียน ───────────────────────────────────── */ ?>
  <?php if ($my_sub): ?>
  <!-- ── ทำแล้ว: แสดงผลคะแนน + เฉลย ────────────────────────────── -->
  <div class="card" style="border:2px solid var(--primary-soft-2);margin-bottom:20px">
    <div class="card-head" style="background:var(--primary-soft);border-bottom:1px solid var(--primary-soft-2)">
      <span style="width:34px;height:34px;border-radius:9px;background:#fff;color:var(--primary);display:grid;place-items:center"><?= icon('trophy', 18) ?></span>
      <h3>ผลคะแนนของคุณ</h3>
      <span class="badge green" style="margin-left:auto;font-size:13px"><?= (int)$my_sub['grade'] ?>/<?= $quiz_total_pts ?> คะแนน</span>
    </div>
  </div>
  <?php
    $my_responses = get_quiz_responses($assignment_id, $uid);
  ?>
  <?php foreach ($quiz_questions as $qi => $q):
      $my_choice_id = (int)($my_responses[$q['id']]['choice_id'] ?? 0);
  ?>
  <div class="card card-pad" style="margin-bottom:12px">
    <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px">
      <span style="width:26px;height:26px;border-radius:8px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;display:grid;place-items:center;flex:0 0 auto"><?= $qi + 1 ?></span>
      <div style="font-size:14.5px;font-weight:600;color:var(--heading);line-height:1.5"><?= h($q['question_text']) ?></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;padding-left:36px">
      <?php foreach ($q['choices'] as $ch):
          $is_mine    = (int)$ch['id'] === $my_choice_id;
          $is_correct = (bool)$ch['is_correct'];
          $color      = $is_correct ? 'var(--primary)' : ($is_mine ? 'var(--danger)' : 'var(--body)');
      ?>
      <div style="display:flex;align-items:center;gap:8px;font-size:13.5px;color:<?= $color ?>;<?= ($is_correct || $is_mine) ? 'font-weight:700' : '' ?>">
        <?php if ($is_correct): ?>
        <?= icon('check-circle', 15, 'var(--primary)') ?>
        <?php elseif ($is_mine): ?>
        <?= icon('x', 15, 'var(--danger)') ?>
        <?php else: ?>
        <span style="width:15px;height:15px;border-radius:50%;border:1.5px solid var(--line-2);display:inline-block;flex:0 0 auto"></span>
        <?php endif; ?>
        <?= h($ch['choice_text']) ?>
        <?php if ($is_mine && !$is_correct): ?><span class="subtle" style="font-size:11px">(คำตอบของคุณ)</span><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php else: /* ยังไม่ทำ */ ?>
  <!-- ── แบบฟอร์มทำแบบทดสอบ ────────────────────────────────────── -->
  <div class="card" style="border:2px solid var(--accent-soft)">
    <div class="card-head" style="background:var(--accent-soft);border-bottom:1px solid #d4e3fc">
      <span style="width:34px;height:34px;border-radius:9px;background:#fff;color:var(--accent);display:grid;place-items:center"><?= icon('clipboard', 18) ?></span>
      <h3 style="color:var(--accent-700)">ทำแบบทดสอบ</h3>
      <span class="badge orange" style="margin-left:auto"><?= icon('clock', 13) ?> กำหนดส่ง <?= h($a['due_short']) ?></span>
    </div>
    <div class="card-pad">
      <?php if (empty($quiz_questions)): ?>
      <p class="subtle" style="font-size:13.5px">ครูยังไม่เพิ่มคำถามในแบบทดสอบนี้</p>
      <?php else: ?>
      <form id="quiz-form" onsubmit="return submitQuiz(event)">
        <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
        <?php foreach ($quiz_questions as $qi => $q): ?>
        <div style="margin-bottom:22px;padding-bottom:18px;<?= $qi < count($quiz_questions) - 1 ? 'border-bottom:1px solid var(--line-1)' : '' ?>">
          <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px">
            <span style="width:26px;height:26px;border-radius:8px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;display:grid;place-items:center;flex:0 0 auto"><?= $qi + 1 ?></span>
            <div style="font-size:14.5px;font-weight:600;color:var(--heading);line-height:1.5">
              <?= h($q['question_text']) ?>
              <span class="subtle" style="font-weight:400;font-size:12px"> (<?= $q['points'] ?> คะแนน)</span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;padding-left:36px">
            <?php foreach ($q['choices'] as $ch): ?>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:9px 12px;border:1.5px solid var(--line-2);border-radius:9px;transition:border-color .15s"
                   onmouseenter="this.style.borderColor='var(--primary)'" onmouseleave="this.style.borderColor='var(--line-2)'">
              <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $ch['id'] ?>" required
                     style="width:16px;height:16px;accent-color:var(--primary);flex:0 0 auto">
              <span style="font-size:13.5px;color:var(--body)"><?= h($ch['choice_text']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-accent" id="quiz-submit-btn">
          <?= icon('send', 17, '#fff') ?> ส่งคำตอบ
        </button>
      </form>
      <script>
      function submitQuiz(e) {
        e.preventDefault();
        if (!confirm('ยืนยันส่งคำตอบ? หลังส่งแล้วจะแก้ไขไม่ได้')) return false;
        var btn = document.getElementById('quiz-submit-btn');
        btn.disabled = true; btn.style.opacity = '.6';
        var fd = new FormData(document.getElementById('quiz-form'));
        fetch('api/submit_quiz.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(res => {
            if (res.ok) {
              showToast(res.message || 'ส่งคำตอบแล้ว');
              setTimeout(() => location.reload(), 900);
            } else {
              btn.disabled = false; btn.style.opacity = '1';
              showToast(res.error || 'เกิดข้อผิดพลาด', true);
            }
          })
          .catch(() => { btn.disabled = false; btn.style.opacity = '1'; showToast('เกิดข้อผิดพลาด — ตรวจสอบการเชื่อมต่อ', true); });
        return false;
      }
      </script>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; /* my_sub */ ?>
  <?php endif; /* is_teacher */ ?>

  <?php else: /* ── งานทั่วไป (ไม่ใช่แบบทดสอบ) ───────────────────── */ ?>

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
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
      <?php if (count($subs) > 1): ?>
      <button type="button" class="btn btn-sm btn-ghost" id="sort-toggle" data-sort="votes" title="คลิกเพื่อสลับการเรียง" onclick="toggleSort(this)">
        <span id="sort-ic-votes" style="display:inline-flex"><?= icon('thumbs-up', 15) ?></span>
        <span id="sort-ic-time" style="display:none"><?= icon('clock', 15) ?></span>
        <span class="subtle" style="font-weight:500;font-size:12px">กำลังเรียงตาม</span>
        <span id="sort-label" style="font-weight:700">โหวต</span>
      </button>
      <?php endif; ?>
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
  ?>
  <div id="subs-list">
  <?php
  foreach ($subs as $sub):
    $vote_count = (int)$sub['vote_count'];
  ?>
  <div class="card sub-card" id="sub-<?= (int)$sub['id'] ?>" data-votes="<?= $vote_count ?>" data-submitted="<?= (int)strtotime($sub['submitted_at']) ?>" style="margin-bottom:14px;transition:box-shadow .3s,outline .3s">
    <div style="padding:16px 20px;display:flex;align-items:center;gap:13px;border-bottom:1px solid var(--line)">
      <?= avatar(['avatar_class' => $sub['avatar_class'], 'avatar_path' => $sub['avatar_path'] ?? null, 'initials' => $sub['initials']], 40) ?>
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
          <?= icon('thumbs-up', 13, 'var(--accent)') ?> <span class="vote-num" id="votes-<?= (int)$sub['id'] ?>"><?= $vote_count ?></span> โหวต
        </span>
      </div>
      <div class="prompt-text" style="font-size:12.5px"><?= h($sub['prompt_used']) ?></div>
      <?php if (!empty($files_by_sub[(int)$sub['id']])): ?>
      <div style="margin-top:10px">
        <div class="subtle" style="font-size:12.5px;font-weight:600;margin-bottom:6px">ไฟล์แนบ (<?= count($files_by_sub[(int)$sub['id']]) ?>)</div>
        <div class="row wrap">
          <?php foreach ($files_by_sub[(int)$sub['id']] as $f): ?>
          <?= attachment_item($f) ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
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
                onclick="openGradeModal(<?= h(json_encode([
                    'id'         => $sub['id'],
                    'name'       => $sub['student_name'],
                    'initials'   => $sub['initials'],
                    'av'         => $sub['avatar_class'],
                    'at'         => date('j M Y H:i', strtotime($sub['submitted_at'])),
                    'answer'     => $sub['answer_text'],
                    'result'     => $sub['result_text'],
                    'ai'         => $sub['ai_used'],
                    'points'     => $a['points'],
                    'grade'      => $sub['grade'],
                    'feedback'   => $sub['feedback'],
                ], JSON_UNESCAPED_UNICODE)) ?>)">
          <?= icon($sub['status'] === 'graded' ? 'edit' : 'check', 15, $sub['status'] !== 'graded' ? '#fff' : 'currentColor') ?>
          <?= $sub['status'] === 'graded' ? 'แก้ไขคะแนน' : 'ตรวจและให้คะแนน' ?>
        </button>
        <?php $voted = !empty($sub['voted_by_me']); ?>
        <button type="button" id="vote-btn-<?= (int)$sub['id'] ?>"
                class="btn btn-sm <?= $voted ? 'btn-ghost' : 'btn-soft' ?>"
                data-voted="<?= $voted ? '1' : '0' ?>"
                onclick="votePrompt(this, <?= (int)$sub['id'] ?>)">
          <?= icon('thumbs-up', 15) ?>
          <span class="vote-btn-label"><?= $voted ? 'ยกเลิกโหวต' : 'โหวตว่า prompt ดี' ?></span>
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div><!-- #subs-list -->

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
      <form method="post" action="api/grade_submission.php" id="grade-form" data-ajax>
        <input type="hidden" name="submission_id" id="gf-sub-id">
        <div class="modal__body">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
            <span id="gf-avatar"></span>
            <div>
              <div style="font-weight:700;color:var(--heading)" id="gf-name"></div>
              <div class="subtle" style="font-size:12.5px" id="gf-at"></div>
            </div>
          </div>
          <div class="field" id="gf-answer-wrap">
            <label>คำตอบ / ผลงาน</label>
            <div id="gf-answer" style="font-size:13.5px;color:var(--body);line-height:1.6;background:var(--surface-2);border:1px solid var(--line);border-radius:9px;padding:11px 13px;white-space:pre-wrap"></div>
          </div>
          <div class="field" id="gf-result-wrap">
            <label>ผลลัพธ์ที่ได้จาก AI</label>
            <div id="gf-result" style="font-size:13.5px;color:var(--body);line-height:1.6;background:var(--surface-2);border:1px solid var(--line);border-radius:9px;padding:11px 13px;white-space:pre-wrap"></div>
          </div>
          <div class="row" style="gap:14px">
            <div class="field" style="flex:0 0 160px">
              <label id="gf-pts-lbl">คะแนน</label>
              <input class="input" type="number" name="grade" id="gf-grade" placeholder="0" min="0" step="1"
                     oninput="clampGrade(this)" style="font-size:18px;font-weight:700">
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
    <div class="field">
      <label>สัปดาห์/หน่วย <span class="subtle" style="font-weight:400">(ไม่บังคับ — จัดกลุ่มร่วมกับเนื้อหาบทเรียนของหน่วยเดียวกัน)</span></label>
      <input class="input" name="week_label" value="<?= h($a['week_label'] ?? '') ?>" list="week-label-options" autocomplete="off">
      <?php week_label_datalist('week-label-options', get_course_week_labels((int)$a['course_id'])); ?>
    </div>
    <div class="row" style="gap:14px">
      <div class="field" style="flex:1">
        <label>ประเภทงาน</label>
        <?php
        $type_options = ['งาน', 'การบ้าน', 'โครงงาน', 'แบบทดสอบ', 'แบบทดสอบก่อนเรียน', 'แบบทดสอบหลังเรียน', 'ข้อสอบปลายภาค'];
        if (!in_array($a['assignment_type'], $type_options, true)) $type_options[] = $a['assignment_type'];
        ?>
        <select class="input" name="assignment_type" id="ea-type-sel" onchange="easToggle(this.value)">
          <?php foreach ($type_options as $t): ?>
          <option value="<?= h($t) ?>" <?= $a['assignment_type'] === $t ? 'selected' : '' ?>><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" id="ea-points-wrap" style="flex:0 0 110px;<?= $is_quiz ? 'display:none' : '' ?>">
        <label>คะแนนเต็ม</label>
        <input class="input" type="number" name="points" min="1" value="<?= $a['points'] ?>">
        <?php if ($is_quiz): ?>
        <div class="hint">คำนวณจากผลรวมคะแนนคำถามอัตโนมัติ</div>
        <?php endif; ?>
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
        <input class="input" type="date" name="due_date" value="<?= $_due_iso ?>">
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
    <div id="ea-prompt-wrap" style="display:<?= $is_quiz ? 'none' : 'block' ?>">
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
        <textarea class="textarea" name="prompt_text" id="ea-prompt-txt" style="font-family:ui-monospace,monospace;font-size:13px" <?= $is_quiz ? '' : 'required' ?>><?= h($ep['prompt_text'] ?? '') ?></textarea>
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
    </div>
    <?php if ($is_quiz): ?>
    <div class="note-box" style="font-size:13px">
      <?= icon('info', 14, 'var(--sub)') ?> งานประเภทแบบทดสอบไม่ต้องระบุ Prompt AI — จัดการคำถามได้จากหน้ารายละเอียดงานนี้
    </div>
    <?php endif; ?>
    <script>
    function easToggle(type) {
      var quizTypes = ['แบบทดสอบ', 'แบบทดสอบก่อนเรียน', 'แบบทดสอบหลังเรียน', 'ข้อสอบปลายภาค'];
      var isQuiz = quizTypes.indexOf(type) !== -1;
      var wrap     = document.getElementById('ea-prompt-wrap');
      var txt      = document.getElementById('ea-prompt-txt');
      var ptsWrap  = document.getElementById('ea-points-wrap');
      if (wrap)    wrap.style.display    = isQuiz ? 'none' : 'block';
      if (txt)     txt.required          = !isQuiz;
      if (ptsWrap) ptsWrap.style.display = isQuiz ? 'none' : 'block';
    }
    </script>

    <!-- ── ลิงก์สื่อการสอน ──────────────────────── -->
    <div style="margin-top:12px;padding:14px 15px;border:1px solid var(--line-2);border-radius:10px">
      <div style="font-size:13px;font-weight:700;color:var(--heading);margin-bottom:10px;display:flex;align-items:center;gap:7px">
        <?= icon('link', 15) ?> ลิงก์สื่อการสอน <span class="subtle" style="font-weight:400;font-size:12px">(ไม่บังคับ)</span>
      </div>
      <div id="edit-asgn-links-container">
        <?php foreach ($a['links'] as $lnk): ?>
        <div class="link-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center">
          <input class="input" name="link_url[]" type="url" placeholder="https://..."
                 value="<?= h($lnk['url']) ?>" style="flex:2;min-width:0">
          <input class="input" name="link_label[]" placeholder="ชื่อลิงก์ (ไม่บังคับ)"
                 value="<?= h($lnk['label']) ?>" style="flex:1;min-width:0">
          <button type="button" onclick="this.closest('.link-row').remove()"
                  style="flex:0 0 32px;height:32px;border:none;border-radius:8px;background:var(--danger-soft,#fee2e2);color:var(--danger,#dc2626);cursor:pointer;font-size:18px;line-height:1;display:grid;place-items:center">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" onclick="addLinkRow('edit-asgn-links-container')"
              class="btn btn-sm btn-ghost" style="margin-top:2px">
        <?= icon('plus', 14) ?> เพิ่มลิงก์
      </button>
    </div>
  </form>
  <?php modal_foot('edit-assignment', 'ยกเลิก', 'บันทึกการแก้ไข'); ?>

  <!-- Delete assignment confirmation modal -->
  <div id="del-assignment-overlay" class="modal-overlay" onclick="if(event.target===this)closeModal('del-assignment')" style="display:none">
    <div class="modal" style="max-width:430px">
      <div class="modal__head">
        <span class="modal__ic" style="background:var(--danger-soft,#fee2e2);color:var(--danger,#ef4444)"><?= icon('trash', 20, 'var(--danger,#ef4444)') ?></span>
        <h2 class="modal__title">ลบงานที่มอบหมาย</h2>
        <button class="modal__close" onclick="closeModal('del-assignment')"><?= icon('x', 18) ?></button>
      </div>
      <div class="modal__body">
        <p style="color:var(--body);line-height:1.7;margin:0">
          คุณต้องการลบงาน <strong id="del-asgn-name" style="color:var(--heading)"></strong> ใช่หรือไม่?
        </p>
        <p style="font-size:13px;color:var(--sub);margin:10px 0 0">งานที่นักเรียนส่ง คะแนน และไฟล์แนบทั้งหมดจะถูกลบถาวร และไม่สามารถย้อนกลับได้</p>
      </div>
      <div class="modal__foot">
        <button type="button" class="btn btn-ghost" onclick="closeModal('del-assignment')">ยกเลิก</button>
        <button type="button" id="del-asgn-confirm" class="btn" style="background:#ef4444;color:#fff;border-color:#ef4444" onclick="doDeleteAssignment()">
          <?= icon('trash', 15, '#fff') ?> ยืนยันลบงาน
        </button>
      </div>
    </div>
  </div>
  <script>
  var _delAsgnId = null;
  function confirmDeleteAssignment(id, title) {
      _delAsgnId = id;
      document.getElementById('del-asgn-name').textContent = '"' + title + '"';
      openModal('del-assignment');
  }
  function doDeleteAssignment() {
      if (_delAsgnId === null) return;
      var btn = document.getElementById('del-asgn-confirm');
      btn.disabled = true; btn.style.opacity = '.6';
      var fd = new FormData();
      fd.append('assignment_id', _delAsgnId);
      fetch('api/delete_assignment.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(res => {
              if (res.ok) {
                  showToast(res.message || 'ลบงานแล้ว');
                  setTimeout(() => location.href = '<?= url('course', ['course_id' => (int)$a['course_id'], 'tab' => 'lessons']) ?>', 800);
              } else {
                  btn.disabled = false; btn.style.opacity = '1';
                  closeModal('del-assignment');
                  showToast(res.error || 'เกิดข้อผิดพลาด', true);
              }
          })
          .catch(() => {
              btn.disabled = false; btn.style.opacity = '1';
              closeModal('del-assignment');
              showToast('เกิดข้อผิดพลาด', true);
          });
  }
  </script>

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
      <button class="btn btn-sm btn-ghost" id="resubmit-toggle-btn" onclick="toggleResubmit()"
              style="gap:5px;font-size:12px;flex-shrink:0">
        <?= icon('edit', 13) ?> แก้ไขงาน
      </button>
    </div>
    <div class="card-pad">
      <div class="field" style="margin-bottom:14px">
        <label>คำตอบที่ส่ง</label>
        <div style="font-size:14px;color:var(--body);line-height:1.6"><?= h($my_sub['answer_text'] ?: '— แนบไฟล์ —') ?></div>
      </div>
      <?php if (!empty($files_by_sub[(int)$my_sub['id']])): ?>
      <div class="field" style="margin-bottom:14px">
        <label>ไฟล์ที่แนบ</label>
        <div class="row wrap">
          <?php foreach ($files_by_sub[(int)$my_sub['id']] as $f): ?>
          <?= attachment_item($f) ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
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

  <!-- Resubmit form (hidden, toggled by แก้ไขงาน button) -->
  <div id="resubmit-wrap" style="display:none;margin-top:14px">
    <div class="card" style="border:2px dashed var(--line-2)">
      <div class="card-head" style="background:var(--warn-soft);border-bottom:1px solid #fde8c8">
        <?= icon('edit', 18, '#c76a13') ?>
        <h3 style="color:#c76a13">แก้ไขและส่งงานใหม่</h3>
        <span class="subtle" style="font-size:12px;margin-left:auto">งานที่ให้คะแนนไว้จะถูกรีเซ็ตเพื่อตรวจใหม่</span>
      </div>
      <div class="card-pad">
        <form id="resubmit-form" method="post" action="api/submit_assignment.php" enctype="multipart/form-data">
          <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
          <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">

          <div class="field">
            <label>คำตอบ / ผลงานของคุณ
              <span class="subtle" style="font-weight:400;font-size:12px">(จำเป็นถ้าไม่มีไฟล์แนบ)</span>
            </label>
            <textarea class="textarea" name="answer_text" id="resubmit-answer-input"
                      placeholder="เขียนคำตอบหรือสรุปผลงานของคุณที่นี่…"><?= h($my_sub['answer_text'] ?? '') ?></textarea>
          </div>

          <?php multi_file_input('files', 'เพิ่มไฟล์แนบใหม่') ?>

          <div class="ai-tint-box" style="padding:16px;margin-top:6px">
            <div style="display:flex;align-items:center;gap:9px;margin-bottom:14px">
              <span style="width:30px;height:30px;border-radius:8px;background:var(--card);color:var(--primary);display:grid;place-items:center">
                <?= icon('sparkle', 17) ?>
              </span>
              <div style="font-weight:700;color:var(--heading);font-size:14px">ระบุ AI และ Prompt ที่คุณใช้</div>
            </div>
            <div class="field">
              <label>Prompt ที่คุณใช้ <span style="color:var(--danger)">*</span></label>
              <textarea class="textarea" name="prompt_used" style="font-family:ui-monospace,monospace;font-size:13px;min-height:80px"
                        placeholder="วาง prompt ที่คุณปรับแต่งและใช้จริง…" required><?= h($my_sub['prompt_used'] ?? '') ?></textarea>
            </div>
            <div class="field">
              <label>AI ที่ให้คำตอบดีที่สุด</label>
              <?= ai_select('ai_used', $my_sub['ai_used'] ?? 'claude') ?>
            </div>
            <div class="field" style="margin-bottom:6px">
              <label>ผลลัพธ์ที่ได้จาก AI <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
              <textarea class="textarea" name="result_text" style="min-height:60px"
                        placeholder="สรุปสั้น ๆ ว่า AI ตอบกลับมาอย่างไร…"><?= h($my_sub['result_text'] ?? '') ?></textarea>
            </div>
          </div>

          <?php if ($a['allow_improve']): ?>
          <div style="margin-top:16px;padding:14px 16px;border:1px solid var(--line-2);border-radius:10px">
            <label style="display:flex;align-items:flex-start;gap:11px;cursor:pointer">
              <input type="checkbox" name="better_than_teacher" value="1"
                     style="margin-top:3px;width:17px;height:17px;accent-color:var(--primary)"
                     <?= $my_sub['better_than_teacher'] ? 'checked' : '' ?>
                     onchange="this.closest('label').querySelector('.resub-note').style.display=this.checked?'block':'none'">
              <div style="flex:1">
                <div style="font-weight:700;color:var(--heading);font-size:14px;display:flex;align-items:center;gap:7px">
                  <?= icon('trophy', 16, 'var(--warn)') ?> prompt ของฉันให้ผลลัพธ์ดีกว่าของครู
                </div>
                <textarea class="textarea resub-note" name="compare_note"
                          style="min-height:56px;margin-top:10px;display:<?= $my_sub['better_than_teacher'] ? 'block' : 'none' ?>"
                          placeholder="อธิบายว่าทำไม prompt ของคุณถึงดีกว่า…"><?= h($my_sub['compare_note'] ?? '') ?></textarea>
              </div>
            </label>
          </div>
          <?php endif; ?>

          <div style="display:flex;gap:10px;margin-top:18px">
            <button type="submit" class="btn btn-primary" onclick="return validateResubmit()">
              <?= icon('send', 17, '#fff') ?> ส่งงานใหม่
            </button>
            <button type="button" class="btn btn-ghost" onclick="toggleResubmit()">ยกเลิก</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script>
  function toggleResubmit() {
    var wrap = document.getElementById('resubmit-wrap');
    var btn  = document.getElementById('resubmit-toggle-btn');
    var open = wrap.style.display === 'none' || wrap.style.display === '';
    // toggle: if currently hidden → show; if shown → hide
    var nowHidden = wrap.style.display === 'none';
    wrap.style.display = nowHidden ? '' : 'none';
    if (btn) btn.innerHTML = nowHidden
      ? '<?= addslashes(icon('x', 13)) ?> ยกเลิก'
      : '<?= addslashes(icon('edit', 13)) ?> แก้ไขงาน';
    if (nowHidden) wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  function validateResubmit() {
    var answer = document.getElementById('resubmit-answer-input');
    var hasText = answer && answer.value.trim().length > 0;
    var hasFiles = false;
    document.querySelectorAll('#resubmit-form input[type="file"]').forEach(function(inp) {
      if (inp.files && inp.files.length > 0) hasFiles = true;
    });
    document.querySelectorAll('#resubmit-form .multifile-item').forEach(function() { hasFiles = true; });
    if (!hasText && !hasFiles) {
      showToast('กรุณาพิมพ์คำตอบหรือแนบไฟล์ผลงานอย่างน้อย 1 ไฟล์', true);
      if (answer) answer.focus();
      return false;
    }
    return true;
  }
  </script>

  <?php
  // ── Peer submissions: visible only after the student's own work is graded ──
  if ($my_sub['status'] === 'graded'):
    $peers = array_values(array_filter($subs, fn($s) => (int)$s['student_id'] !== $uid));
  ?>
  <div style="margin:26px 0 16px;display:flex;align-items:center;gap:10px">
    <h2 style="font-size:18px;margin:0"><?= icon('users', 19, 'var(--primary)') ?> งานของเพื่อนร่วมชั้น</h2>
    <span class="subtle" style="font-size:14px;font-weight:600">(<?= count($peers) ?>)</span>
    <span class="chip" style="margin-left:auto;font-size:11.5px"><?= icon('thumbs-up', 13, 'var(--accent)') ?> โหวต prompt ที่คุณคิดว่าดี</span>
  </div>

  <?php if (empty($peers)): ?>
  <div class="empty"><div class="e-ic"><?= icon('users', 28) ?></div><h3>ยังไม่มีงานของเพื่อนคนอื่น</h3></div>
  <?php else: ?>
  <div id="subs-list">
  <?php foreach ($peers as $sub):
    $vote_count = (int)$sub['vote_count'];
    $voted      = !empty($sub['voted_by_me']);
  ?>
  <div class="card sub-card" id="sub-<?= (int)$sub['id'] ?>" data-votes="<?= $vote_count ?>" data-submitted="<?= (int)strtotime($sub['submitted_at']) ?>" style="margin-bottom:14px">
    <div style="padding:16px 20px;display:flex;align-items:center;gap:13px;border-bottom:1px solid var(--line)">
      <?= avatar(['avatar_class' => $sub['avatar_class'], 'avatar_path' => $sub['avatar_path'] ?? null, 'initials' => $sub['initials']], 40) ?>
      <div>
        <div style="font-weight:700;color:var(--heading)"><?= h($sub['student_name']) ?></div>
        <div class="subtle" style="font-size:12.5px">ส่งเมื่อ <?= h(date('j M Y H:i', strtotime($sub['submitted_at']))) ?></div>
      </div>
      <?php if ($sub['better_than_teacher']): ?>
      <span class="badge orange" style="margin-left:auto"><?= icon('trophy', 13) ?> เคลม prompt ดีกว่า</span>
      <?php endif; ?>
    </div>
    <div style="padding:14px 20px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap">
        <span class="subtle" style="font-size:12.5px;font-weight:600">Prompt ที่เพื่อนใช้ · ตอบดีที่สุดด้วย</span>
        <?= ai_pill($sub['ai_used'], 'sm') ?>
        <span class="chip" style="font-size:11.5px">
          <?= icon('thumbs-up', 13, 'var(--accent)') ?> <span class="vote-num" id="votes-<?= (int)$sub['id'] ?>"><?= $vote_count ?></span> โหวต
        </span>
      </div>
      <div class="prompt-text" style="font-size:12.5px"><?= h($sub['prompt_used']) ?></div>
      <?php if (!empty($files_by_sub[(int)$sub['id']])): ?>
      <div style="margin-top:10px">
        <div class="subtle" style="font-size:12.5px;font-weight:600;margin-bottom:6px">ไฟล์แนบ (<?= count($files_by_sub[(int)$sub['id']]) ?>)</div>
        <div class="row wrap">
          <?php foreach ($files_by_sub[(int)$sub['id']] as $f): ?>
          <?= attachment_item($f) ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($sub['compare_note']): ?>
      <div style="margin-top:10px;font-size:13px;color:var(--body);display:flex;gap:8px;align-items:flex-start">
        <?= icon('message', 15, 'var(--muted)') ?> <i>"<?= h($sub['compare_note']) ?>"</i>
      </div>
      <?php endif; ?>
      <div style="margin-top:14px">
        <button type="button" id="vote-btn-<?= (int)$sub['id'] ?>"
                class="btn btn-sm <?= $voted ? 'btn-ghost' : 'btn-soft' ?>"
                data-voted="<?= $voted ? '1' : '0' ?>"
                onclick="votePrompt(this, <?= (int)$sub['id'] ?>)">
          <?= icon('thumbs-up', 15) ?>
          <span class="vote-btn-label"><?= $voted ? 'ยกเลิกโหวต' : 'โหวตว่า prompt ดี' ?></span>
        </button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div><!-- #subs-list -->
  <?php endif; // empty peers ?>
  <?php endif; // my_sub graded ?>

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
      <form id="submit-form" method="post" action="api/submit_assignment.php" enctype="multipart/form-data">
        <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
        <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">

        <div class="field">
          <label>คำตอบ / ผลงานของคุณ
            <span class="subtle" style="font-weight:400;font-size:12px">(จำเป็นถ้าไม่มีไฟล์แนบ)</span>
          </label>
          <textarea class="textarea" name="answer_text" id="answer-text-input"
                    placeholder="เขียนคำตอบหรือสรุปผลงานของคุณที่นี่…"></textarea>
        </div>

        <?php multi_file_input('files', 'แนบไฟล์ผลงาน') ?>

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
          <button type="submit" class="btn btn-accent" id="submit-btn" onclick="return validateSubmit()">
            <?= icon('send', 17, '#fff') ?> ส่งงาน
          </button>
          <button type="button" class="btn btn-ghost">บันทึกร่าง</button>
        </div>
      </form>
      <script>
      function validateSubmit() {
        var answer = document.getElementById('answer-text-input');
        var hasText = answer && answer.value.trim().length > 0;
        // ตรวจว่ามีไฟล์ใน data-multifile wrapper หรือ input[type=file]
        var hasFiles = false;
        document.querySelectorAll('#submit-form input[type="file"]').forEach(function(inp) {
          if (inp.files && inp.files.length > 0) hasFiles = true;
        });
        // ตรวจ hidden inputs ที่ data-multifile JS อาจสร้าง
        document.querySelectorAll('#submit-form .multifile-item').forEach(function() { hasFiles = true; });
        if (!hasText && !hasFiles) {
          showToast('กรุณาพิมพ์คำตอบหรือแนบไฟล์ผลงานอย่างน้อย 1 ไฟล์', true);
          if (answer) answer.focus();
          return false;
        }
        return true;
      }
      </script>
    </div>
  </div>
  <?php endif; // my_sub ?>
  <?php endif; // is_teacher ?>

  <?php endif; // is_quiz ?>
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
