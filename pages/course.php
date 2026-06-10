<?php
declare(strict_types=1);

$course_id  = (int)($_GET['course_id'] ?? 0);
$c          = get_course($course_id);
$guest_mode = !is_logged_in();

if (!$c) { echo '<div class="empty"><h3>ไม่พบรายวิชา</h3></div>'; return; }

// Guest ดูได้เฉพาะวิชาสาธารณะ
if ($guest_mode && empty($c['is_public'])) {
    echo '<div class="empty">'
        . '<div class="e-ic">' . icon('lock', 30) . '</div>'
        . '<h3>รายวิชานี้ไม่เปิดสาธารณะ</h3>'
        . '<p>กรุณา <a href="index.php?page=login">เข้าสู่ระบบ</a> เพื่อเข้าถึงรายวิชา</p>'
        . '</div>';
    return;
}

$role    = $guest_mode ? 'guest' : current_role();
$tab     = $_GET['tab'] ?? ($guest_mode ? 'lessons' : 'stream');
$lessons = db_rows('SELECT l.*, lp.ai_id, lp.rating, (SELECT COUNT(*) FROM lesson_materials WHERE lesson_id = l.id) AS mat_count FROM lessons l LEFT JOIN lesson_prompts lp ON lp.lesson_id = l.id WHERE l.course_id = ? ORDER BY l.sort_order, l.id', [$course_id]);
$works   = db_rows('SELECT a.*, ap.ai_id FROM assignments a LEFT JOIN assignment_prompts ap ON ap.assignment_id = a.id WHERE a.course_id = ? ORDER BY a.id', [$course_id]);
$teacher = db_row('SELECT * FROM users WHERE id = ?', [$c['teacher_id']]);
try {
    $posts = db_rows('SELECT * FROM course_posts WHERE course_id = ? ORDER BY created_at DESC', [$course_id]);
} catch (PDOException) {
    $posts = [];
}

// ── Course header ──────────────────────────────────────────────
?>
<?php
$is_owner = !$guest_mode && is_teacher() && (int)$c['teacher_id'] === current_user_id();
?>

<div style="display:flex;align-items:center;margin-bottom:4px">
  <div class="breadcrumb" style="margin-bottom:0">
    <a href="<?= url('courses') ?>">รายวิชา</a>
    <?= icon('chevron-right', 14) ?>
    <span style="color:var(--body);font-weight:600"><?= h($c['name']) ?></span>
  </div>
  <?php if ($is_owner): ?>
  <a href="<?= url('course_settings', ['course_id' => $course_id]) ?>"
     class="btn btn-ghost" style="margin-left:auto;gap:7px;text-decoration:none">
    <?= icon('settings', 15) ?> ตั้งค่ารายวิชา
  </a>
  <?php endif; ?>
</div>

<div class="card" style="overflow:hidden;margin-bottom:22px">
  <div style="background:<?= h($c['banner']) ?>;padding:28px 28px 24px;color:<?= h($c['ink_color']) ?>;position:relative">
    <div style="position:absolute;right:-20px;top:-30px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.4)"></div>
    <span class="badge" style="background:rgba(255,255,255,.65);color:<?= h($c['ink_color']) ?>;margin-bottom:10px"><?= h($c['code']) ?></span>
    <h1 style="color:<?= h($c['ink_color']) ?>;font-size:27px"><?= h($c['name']) ?></h1>
    <div style="display:flex;gap:18px;margin-top:12px;font-size:13.5px;color:<?= h($c['ink_color']) ?>;opacity:.85;flex-wrap:wrap">
      <span style="display:flex;align-items:center;gap:6px"><?= icon('users', 16, $c['ink_color']) ?> <?= h($c['section']) ?></span>
      <span style="display:flex;align-items:center;gap:6px"><?= icon('edit', 16, $c['ink_color']) ?> <?= h($teacher['name'] ?? '') ?></span>
    </div>
  </div>
  <div class="tabs" style="margin:0;padding:0 16px;border-top:none">
    <?php
    $tabs = $guest_mode ? [
        ['lessons', 'book', 'เนื้อหาบทเรียน', count($lessons)],
    ] : [
        ['stream',  'stream',    'ฟีดประกาศ',      null],
        ['lessons', 'book',      'เนื้อหาบทเรียน', count($lessons)],
        ['work',    'clipboard', 'งาน / การบ้าน',  count($works)],
        ['people',  'users',     'สมาชิก',          (int)$c['student_count']],
    ];
    foreach ($tabs as [$tid, $tic, $tlbl, $tcnt]):
        $act = $tab === $tid ? ' active' : '';
    ?>
    <a href="<?= url('course', ['course_id' => $course_id, 'tab' => $tid]) ?>"
       class="tab<?= $act ?>" style="text-decoration:none">
      <?= icon($tic, 17) ?> <?= $tlbl ?>
      <?php if ($tcnt !== null): ?>
      <span class="t-count"><?= $tcnt ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php

// ── STREAM tab ─────────────────────────────────────────────────
if ($tab === 'stream'): ?>
<div class="row wrap" style="align-items:flex-start">
  <div style="flex:1 1 560px;min-width:0">
    <?php if (is_teacher()): ?>
    <div class="card card-pad" style="margin-bottom:18px;display:flex;align-items:center;gap:12px">
      <?= avatar($teacher, 42) ?>
      <button class="input" style="text-align:left;color:var(--muted);background:var(--surface-2);cursor:pointer"
              onclick="openModal('add-post')">
        ประกาศหรือแจ้งข้อมูลให้นักเรียน…
      </button>
    </div>
    <?php endif; ?>


    <?php foreach ($posts as $p): ?>
    <div class="post">
      <div class="post__head">
        <?= avatar($teacher, 42) ?>
        <div>
          <div class="ph-name"><?= h($teacher['name'] ?? '') ?></div>
          <div class="ph-meta">โพสต์ประกาศ · <?= h(date('j M Y', strtotime($p['created_at']))) ?></div>
        </div>
      </div>
      <div class="post__body">
        <p style="margin:0;color:var(--body);line-height:1.7;white-space:pre-wrap"><?= h($p['body']) ?></p>
        <?php if ($p['prompt_text']): ?>
        <div class="ai-tint-box" style="margin-top:14px;padding:14px 16px">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
            <?= icon('sparkle', 15, 'var(--primary)') ?>
            <span style="font-size:13px;font-weight:700;color:var(--primary)">Prompt AI ที่แนะนำ</span>
            <?= $p['ai_id'] ? ai_pill($p['ai_id'], 'sm') : '' ?>
          </div>
          <pre style="margin:0;font-size:12.5px;font-family:ui-monospace,monospace;color:var(--body);white-space:pre-wrap;line-height:1.6"><?= h($p['prompt_text']) ?></pre>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>


    <?php foreach ($works as $w): ?>
    <div class="post">
      <div class="post__head">
        <span class="avatar" style="width:42px;height:42px;background:var(--warn-soft);color:#c76a13"><?= icon('clipboard', 20) ?></span>
        <div>
          <div class="ph-name"><?= h($teacher['name'] ?? '') ?></div>
          <div class="ph-meta">มอบหมายงาน · กำหนดส่ง <?= h($w['due_short']) ?></div>
        </div>
        <span class="post__type"><span class="badge orange"><?= h($w['assignment_type']) ?></span></span>
      </div>
      <div class="post__body">
        <div class="post__title"><?= h($w['title']) ?></div>
        <p style="margin:0 0 12px;color:var(--body);font-size:14px;line-height:1.6">
          <?= h(mb_substr($w['instructions'], 0, 120)) ?>…
        </p>
        <div style="display:flex;align-items:center;gap:10px">
          <span class="chip"><?= icon('sparkle', 14, 'var(--primary)') ?> มี Prompt AI แนบ</span>
          <?= $w['ai_id'] ? ai_pill($w['ai_id'], 'sm') : '' ?>
          <a href="<?= url('assignment', ['assignment_id' => $w['id']]) ?>" class="btn btn-sm btn-soft" style="margin-left:auto;text-decoration:none">
            ดูรายละเอียดงาน <?= icon('arrow-right', 15) ?>
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Sidebar -->
  <div style="flex:0 0 280px;min-width:260px">
    <div class="card card-pad" style="margin-bottom:18px">
      <h3 style="font-size:15px;margin-bottom:14px">กำหนดส่งที่ใกล้ถึง</h3>
      <?php if (empty($works)): ?>
      <p class="subtle" style="font-size:13px">ยังไม่มีงาน</p>
      <?php endif; ?>
      <?php foreach ($works as $w): ?>
      <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:14px">
        <span style="width:8px;height:8px;border-radius:50%;background:var(--warn);margin-top:6px;flex:0 0 auto"></span>
        <div>
          <div style="font-size:13.5px;font-weight:600;color:var(--heading);line-height:1.35"><?= h($w['title']) ?></div>
          <div class="subtle" style="font-size:12px">กำหนดส่ง <?= h($w['due_date']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="card card-pad" style="background:var(--primary-soft);border:1px solid var(--primary-soft-2)">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
        <?= icon('target', 19, 'var(--primary-700)') ?>
        <h3 style="font-size:14.5px;color:var(--primary-700)">ความคืบหน้า</h3>
      </div>
      <div style="font-size:13px;color:var(--primary-700);margin-bottom:8px">เรียนไปแล้ว <?= count($lessons) ?> บทเรียน</div>
      <div class="progress" style="background:#fff"><span style="width:60%"></span></div>
    </div>
  </div>
</div>

<?php

// ── LESSONS tab ────────────────────────────────────────────────
elseif ($tab === 'lessons'): ?>
<div style="display:flex;align-items:center;margin-bottom:18px">
  <h2 style="font-size:19px">เนื้อหาบทเรียน</h2>
  <?php if (!$guest_mode && is_teacher()): ?>
  <button class="btn btn-primary" style="margin-left:auto" onclick="openModal('add-lesson')">
    <?= icon('plus', 18, '#fff') ?> เพิ่มเนื้อหา + Prompt
  </button>
  <?php endif; ?>
</div>

<?php if ($guest_mode): ?>
<div style="display:flex;align-items:center;gap:10px;background:var(--primary-soft);border:1px solid var(--primary-soft-2);
            border-radius:11px;padding:12px 16px;margin-bottom:18px;font-size:13.5px;color:var(--primary)">
  <?= icon('lock', 16, 'var(--primary)') ?>
  <span>เข้าสู่ระบบเพื่อเข้าถึงเนื้อหา สื่อการสอน และ Prompt AI ในแต่ละหน่วย —
    <a href="index.php?page=login" style="font-weight:700;color:var(--primary)">เข้าสู่ระบบ</a>
    หรือ <a href="index.php?page=register" style="font-weight:700;color:var(--primary)">สมัครสมาชิก</a>
  </span>
</div>
<?php endif; ?>

<?php if (empty($lessons)): ?>
<div class="empty">
  <div class="e-ic"><?= icon('book', 30) ?></div>
  <h3>ยังไม่มีเนื้อหา</h3>
  <p><?= (!$guest_mode && is_teacher()) ? 'เริ่มเพิ่มบทเรียนแรกพร้อม Prompt AI ที่แนะนำ' : 'ครูยังไม่เพิ่มเนื้อหา' ?></p>
</div>
<?php endif; ?>
<?php foreach ($lessons as $l):
    $lesson_href = $guest_mode
        ? 'index.php?page=login&redirect=' . urlencode('index.php?page=lesson&lesson_id=' . $l['id'])
        : url('lesson', ['lesson_id' => $l['id']]);
?>
<a href="<?= $lesson_href ?>" class="lrow" style="align-items:flex-start;padding:18px 20px;text-decoration:none<?= $guest_mode ? ';opacity:.85' : '' ?>">
  <span class="lr-ic" style="background:var(--primary-soft);color:var(--primary)"><?= icon('book', 20) ?></span>
  <div style="min-width:0;flex:1">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
      <span class="badge gray" style="font-size:11px"><?= h($l['week_label']) ?></span>
      <span class="chip" style="font-size:11.5px;padding:3px 9px"><?= icon('sparkle', 13, 'var(--primary)') ?> Prompt AI</span>
      <?php if ($guest_mode): ?>
      <span class="badge" style="font-size:11px;background:var(--line-2);color:var(--sub)"><?= icon('lock', 11, 'var(--sub)') ?> ต้องเข้าสู่ระบบ</span>
      <?php endif; ?>
    </div>
    <div class="lr-title"><?= h($l['title']) ?></div>
    <div class="lr-sub" style="margin-top:4px;white-space:normal;max-width:640px"><?= h(mb_substr($l['description'], 0, 110)) ?>…</div>
    <?php if (!$guest_mode): ?>
    <div style="display:flex;gap:8px;margin-top:10px;align-items:center">
      <?= $l['ai_id'] ? ai_pill($l['ai_id'], 'sm') : '' ?>
      <?= star_rating((int)($l['rating'] ?? 0), 13) ?>
      <span class="subtle" style="font-size:12px">· <?= $l['mat_count'] ?> ไฟล์แนบ</span>
    </div>
    <?php endif; ?>
  </div>
  <?= icon($guest_mode ? 'lock' : 'chevron-right', 18, 'var(--faint)') ?>
</a>
<?php endforeach; ?>

<?php

// ── WORK tab ───────────────────────────────────────────────────
elseif ($tab === 'work'): ?>
<div style="display:flex;align-items:center;margin-bottom:18px">
  <h2 style="font-size:19px">งาน / การบ้าน</h2>
  <?php if (is_teacher()): ?>
  <button class="btn btn-primary" style="margin-left:auto" onclick="openModal('add-assignment')">
    <?= icon('plus', 18, '#fff') ?> เพิ่มงาน + Prompt
  </button>
  <?php endif; ?>
</div>
<?php foreach ($works as $w): ?>
<a href="<?= url('assignment', ['assignment_id' => $w['id']]) ?>" class="lrow" style="align-items:flex-start;padding:18px 20px;text-decoration:none">
  <span class="lr-ic" style="background:var(--warn-soft);color:#c76a13"><?= icon('clipboard', 20) ?></span>
  <div style="min-width:0;flex:1">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
      <span class="badge orange" style="font-size:11px"><?= h($w['assignment_type']) ?></span>
      <span class="chip" style="font-size:11.5px;padding:3px 9px"><?= icon('sparkle', 13, 'var(--primary)') ?> Prompt AI</span>
      <?php if ($w['allow_improve']): ?>
      <span class="badge blue" style="font-size:11px">ปรับ prompt ได้</span>
      <?php endif; ?>
    </div>
    <div class="lr-title"><?= h($w['title']) ?></div>
    <div class="lr-sub" style="margin-top:4px;white-space:normal;max-width:620px"><?= h(mb_substr($w['instructions'], 0, 100)) ?>…</div>
  </div>
  <div class="lr-right">
    <span class="badge orange"><?= icon('clock', 13) ?> <?= h($w['due_short']) ?></span>
    <?php
    if (is_teacher()) {
        $sub_cnt = (int)db_val('SELECT COUNT(*) FROM submissions WHERE assignment_id = ?', [$w['id']]);
        $total   = (int)$c['student_count'];
        echo "<span class=\"subtle\" style=\"font-size:12.5px\">ส่งแล้ว {$sub_cnt}/{$total}</span>";
    } else {
        echo "<span class=\"badge gray\" style=\"font-size:11px\">{$w['points']} คะแนน</span>";
    }
    ?>
  </div>
</a>
<?php endforeach; ?>

<?php

// ── PEOPLE tab ─────────────────────────────────────────────────
elseif ($tab === 'people'):
    $students = db_rows('SELECT u.* FROM users u JOIN course_enrollments e ON e.user_id = u.id WHERE e.course_id = ? AND u.role = "student" ORDER BY u.id', [$course_id]);
?>
<div class="row wrap" style="align-items:flex-start">
  <div style="flex:1 1 380px">
    <div class="card">
      <div class="card-head"><?= icon('edit', 18, 'var(--primary)') ?><h3>ครูผู้สอน</h3></div>
      <div class="card-pad" style="display:flex;align-items:center;gap:12px">
        <?= avatar($teacher, 46) ?>
        <div>
          <div style="font-weight:700;color:var(--heading)"><?= h($teacher['name'] ?? '') ?></div>
          <div class="subtle" style="font-size:13px">ครูผู้สอน</div>
        </div>
      </div>
    </div>
  </div>
  <div style="flex:1 1 420px">
    <div class="card">
      <div class="card-head">
        <?= icon('users', 18, 'var(--accent)') ?><h3>นักเรียน</h3>
        <span class="badge gray" style="margin-left:auto"><?= count($students) ?> คน</span>
      </div>
      <div style="padding:10px">
        <?php foreach ($students as $i => $s): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:9px">
          <?= avatar($s, 38) ?>
          <span style="font-weight:600;color:var(--heading);font-size:14px"><?= h($s['name']) ?></span>
          <span class="subtle" style="margin-left:auto;font-size:12.5px">เลขที่ <?= $i + 1 ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
// ── Add Post Modal ────────────────────────────────────────────
if (!$guest_mode && is_teacher()):
    modal_start('add-post', 'โพสต์ประกาศ', 'stream', false, true);
?>
<form id="add-post-form" method="post" action="api/create_post.php" data-ajax>
  <input type="hidden" name="course_id" value="<?= $course_id ?>">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;color:var(--muted);font-size:13px">
    <?= icon('grid', 15) ?> <?= h($c['name']) ?> · <?= h($c['section']) ?>
  </div>
  <div class="field">
    <label>ข้อความประกาศ <span style="color:var(--danger)">*</span></label>
    <textarea class="textarea" name="body" rows="4"
              placeholder="เช่น สัปดาห์นี้เราจะเรียนเรื่อง… อย่าลืมเตรียมงาน…" required
              style="min-height:110px"></textarea>
  </div>

  <!-- Optional AI prompt section (hidden by default) -->
  <div id="post-prompt-section" style="display:none">
    <div class="ai-tint-box" style="padding:16px 16px 10px;margin-top:4px">
      <div style="display:flex;align-items:center;gap:9px;margin-bottom:12px">
        <span style="width:30px;height:30px;border-radius:8px;background:var(--card);color:var(--primary);display:grid;place-items:center"><?= icon('sparkle', 16) ?></span>
        <div>
          <div style="font-weight:700;color:var(--heading);font-size:14px">Prompt AI ที่แนะนำ <span style="font-weight:400;color:var(--sub);font-size:12px">(ไม่บังคับ)</span></div>
          <div class="subtle" style="font-size:11.5px">แชร์ prompt ที่ทดลองแล้วเพื่อให้นักเรียนเริ่มต้นได้เลย</div>
        </div>
        <button type="button"
                onclick="document.getElementById('post-prompt-section').style.display='none';document.getElementById('post-prompt-text').value='';document.getElementById('post-add-prompt-btn').style.display='flex'"
                style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--sub);display:flex;align-items:center;gap:4px;font-size:12px">
          <?= icon('x', 14) ?> ลบออก
        </button>
      </div>
      <div class="field">
        <label>ข้อความ Prompt</label>
        <textarea id="post-prompt-text" class="textarea" name="prompt_text"
                  style="font-family:ui-monospace,monospace;font-size:13px;min-height:90px"
                  placeholder="วาง prompt ที่คุณใช้กับ AI…"></textarea>
      </div>
      <div class="field">
        <label>AI ที่แนะนำ</label>
        <?= ai_select('ai_id', 'chatgpt') ?>
      </div>
    </div>
  </div>

  <button type="button" id="post-add-prompt-btn"
          onclick="document.getElementById('post-prompt-section').style.display='block';this.style.display='none'"
          style="display:flex;align-items:center;gap:7px;margin-top:10px;background:none;
                 border:1.5px dashed var(--line-2);border-radius:9px;padding:8px 14px;
                 cursor:pointer;color:var(--sub);font-size:13px;width:100%;justify-content:center;
                 transition:border-color .15s,color .15s"
          onmouseenter="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
          onmouseleave="this.style.borderColor='var(--line-2)';this.style.color='var(--sub)'">
    <?= icon('sparkle', 15) ?> + เพิ่ม Prompt AI ที่แนะนำ
  </button>
</form>
<?php modal_foot('add-post', 'ยกเลิก', 'โพสต์ประกาศ', 'btn-primary'); ?>

<?php
// ── Add Lesson Modal ──────────────────────────────────────────
    modal_start('add-lesson', 'เพิ่มเนื้อหาบทเรียน + Prompt AI', 'book', true);
?>
<form id="add-lesson-form" method="post" action="api/add_lesson.php" data-ajax>
  <input type="hidden" name="course_id" value="<?= $course_id ?>">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;color:var(--muted);font-size:13px">
    <?= icon('grid', 15) ?> <?= h($c['name']) ?> · <?= h($c['section']) ?>
  </div>
  <div class="field">
    <label>หัวข้อบทเรียน <span style="color:var(--danger)">*</span></label>
    <input class="input" name="title" placeholder="เช่น แนวคิดเชิงคำนวณ" required>
  </div>
  <div class="field">
    <label>สัปดาห์/หน่วย <span style="color:var(--danger)">*</span></label>
    <input class="input" name="week_label" placeholder="เช่น สัปดาห์ที่ 1" required>
  </div>
  <div class="field">
    <label>คำอธิบายเนื้อหา</label>
    <textarea class="textarea" name="description" placeholder="อธิบายเนื้อหาที่นักเรียนจะได้เรียนรู้…"></textarea>
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
      <textarea class="textarea" name="prompt_text" style="font-family:ui-monospace,monospace;font-size:13px"
                placeholder="วาง prompt ที่คุณใช้กับ AI ที่นี่…" required></textarea>
    </div>
    <div class="row" style="gap:14px">
      <div class="field" style="flex:1">
        <label>AI ที่ทดลองใช้แล้ว</label>
        <?= ai_select('ai_id', 'chatgpt') ?>
      </div>
      <div class="field" style="flex:1">
        <label>ระดับความพอใจ</label>
        <?= star_input(4, 'rating') ?>
      </div>
    </div>
    <div class="field">
      <label>ผลลัพธ์ตัวอย่าง <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
      <textarea class="textarea" name="example_text" style="min-height:70px" placeholder="สรุปสั้น ๆ ว่า AI ตอบกลับมาอย่างไร…"></textarea>
    </div>
    <div class="field">
      <label>หมายเหตุ/คำแนะนำ <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
      <textarea class="textarea" name="note_text" style="min-height:60px" placeholder="เช่น ให้นักเรียนลองปรับ prompt ให้ตรงกับหัวข้อตัวเอง…"></textarea>
    </div>
  </div>
</form>
<?php modal_foot('add-lesson', 'ยกเลิก', 'โพสต์เนื้อหา'); ?>

<?php
// ── Add Assignment Modal ──────────────────────────────────────
    modal_start('add-assignment', 'เพิ่มงาน / การบ้าน + Prompt AI', 'clipboard', true, true);
?>
<form id="add-assignment-form" method="post" action="api/add_assignment.php" data-ajax>
  <input type="hidden" name="course_id" value="<?= $course_id ?>">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;color:var(--muted);font-size:13px">
    <?= icon('grid', 15) ?> <?= h($c['name']) ?> · <?= h($c['section']) ?>
  </div>
  <div class="field">
    <label>ชื่องาน / การบ้าน <span style="color:var(--danger)">*</span></label>
    <input class="input" name="title" placeholder="เช่น ออกแบบอัลกอริทึมแก้ปัญหาในชีวิตประจำวัน" required>
  </div>
  <div class="row" style="gap:14px">
    <div class="field" style="flex:1">
      <label>ประเภท</label>
      <select class="select" name="assignment_type">
        <option value="งาน">งาน</option>
        <option value="การบ้าน">การบ้าน</option>
        <option value="โครงงาน">โครงงาน</option>
        <option value="แบบทดสอบ">แบบทดสอบ</option>
      </select>
    </div>
    <div class="field" style="flex:1">
      <label>กำหนดส่ง <span style="color:var(--danger)">*</span></label>
      <input class="input" type="date" name="due_date" min="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field" style="flex:0 0 110px">
      <label>เวลา <span style="color:var(--sub);font-weight:400;font-size:11.5px">(ถ้าไม่ระบุ = 23:59)</span></label>
      <input class="input" type="time" name="due_time" placeholder="23:59">
    </div>
    <div class="field" style="flex:0 0 120px">
      <label>คะแนนเต็ม</label>
      <input class="input" type="number" name="points" value="10" min="1">
    </div>
  </div>
  <div class="field">
    <label>คำสั่ง / รายละเอียดงาน</label>
    <textarea class="textarea" name="instructions" placeholder="อธิบายสิ่งที่ต้องการให้นักเรียนทำ…"></textarea>
  </div>
  <div class="ai-tint-box" style="padding:16px 16px 6px;margin-top:6px">
    <div style="display:flex;align-items:center;gap:9px;margin-bottom:12px">
      <span style="width:32px;height:32px;border-radius:9px;background:var(--card);color:var(--primary);display:grid;place-items:center"><?= icon('sparkle', 18) ?></span>
      <div>
        <div style="font-weight:700;color:var(--heading);font-size:14.5px">Prompt AI ที่แนะนำ</div>
        <div class="subtle" style="font-size:12px">ระบุ prompt และ AI ที่คุณทดลองแล้วได้ผลดี</div>
      </div>
    </div>
    <div class="field">
      <label>ข้อความ Prompt <span style="color:var(--danger)">*</span></label>
      <textarea class="textarea" name="prompt_text" style="font-family:ui-monospace,monospace;font-size:13px"
                placeholder="วาง prompt ที่คุณใช้กับ AI ที่นี่…" required></textarea>
    </div>
    <div class="row" style="gap:14px">
      <div class="field" style="flex:1">
        <label>AI ที่ทดลองใช้แล้ว</label>
        <?= ai_select('ai_id', 'chatgpt') ?>
      </div>
      <div class="field" style="flex:1">
        <label>ระดับความพอใจ</label>
        <?= star_input(4, 'rating') ?>
      </div>
    </div>
    <div class="field">
      <label>ผลลัพธ์ตัวอย่าง <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
      <textarea class="textarea" name="example_text" style="min-height:70px" placeholder="สรุปสั้น ๆ ว่า AI ตอบกลับมาอย่างไร…"></textarea>
    </div>
    <div class="field">
      <label>หมายเหตุ/คำแนะนำ <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
      <textarea class="textarea" name="note_text" style="min-height:60px" placeholder="เช่น ให้นักเรียนลองปรับ prompt ให้ตรงกับหัวข้อตัวเอง…"></textarea>
    </div>
  </div>
  <label style="display:flex;align-items:flex-start;gap:11px;margin-top:18px;padding:13px 15px;border:1px solid var(--line-2);border-radius:10px;cursor:pointer"
         id="allow-improve-wrap">
    <input type="checkbox" name="allow_improve" value="1" checked
           style="margin-top:3px;width:17px;height:17px;accent-color:var(--accent)"
           onchange="this.closest('label').style.background=this.checked?'var(--accent-soft)':'var(--card)'">
    <div>
      <div style="font-weight:700;color:var(--heading);font-size:14px">เปิดให้นักเรียนปรับแต่ง prompt ได้</div>
      <div class="subtle" style="font-size:12.5px;margin-top:2px">นักเรียนสามารถค้นคว้าหา prompt ที่ให้ผลลัพธ์ดีกว่า แล้วระบุ prompt + AI ที่ใช้ตอนส่งงาน</div>
    </div>
  </label>
</form>
<?php modal_foot('add-assignment', 'ยกเลิก', 'มอบหมายงาน'); ?>

<?php endif; // is_teacher ?>

