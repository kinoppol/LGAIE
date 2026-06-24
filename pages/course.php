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
$lessons = db_rows('SELECT l.*, lp.ai_id, lp.rating, (lp.id IS NOT NULL) AS has_prompt, (SELECT COUNT(*) FROM lesson_materials WHERE lesson_id = l.id) AS mat_count FROM lessons l LEFT JOIN lesson_prompts lp ON lp.lesson_id = l.id WHERE l.course_id = ? ORDER BY l.sort_order, l.id', [$course_id]);
$works   = db_rows('SELECT a.*, ap.ai_id, ap.prompt_text AS prompt_text FROM assignments a LEFT JOIN assignment_prompts ap ON ap.assignment_id = a.id WHERE a.course_id = ? ORDER BY a.id', [$course_id]);
$teacher = db_row('SELECT * FROM users WHERE id = ?', [$c['teacher_id']]);
try {
    $posts = db_rows('SELECT * FROM course_posts WHERE course_id = ? ORDER BY created_at DESC', [$course_id]);
} catch (PDOException) {
    $posts = [];
}

// ── Storage data (for sidebar donut) ──────────────────────────
ensure_storage_schema();
$mat_used  = course_storage_used($course_id, 'materials');
$mat_quota = course_quota_bytes($course_id, 'materials');
$sub_used  = course_storage_used($course_id, 'submissions');
$sub_quota = course_quota_bytes($course_id, 'submissions');
$mat_pct   = $mat_quota > 0 ? min(100, $mat_used / $mat_quota * 100) : 0;
$sub_pct   = $sub_quota > 0 ? min(100, $sub_used / $sub_quota * 100) : 0;
$mat_color = $mat_pct >= 90 ? 'var(--danger)' : ($mat_pct >= 70 ? 'var(--warn)' : 'var(--primary)');
$sub_color = $sub_pct >= 90 ? 'var(--danger)' : ($sub_pct >= 70 ? 'var(--warn)' : 'var(--primary)');

function storage_donut(float $pct, string $color): string {
    $r     = 30;
    $circ  = round(2 * M_PI * $r, 3);
    $dash  = round($pct / 100 * $circ, 3);
    $label = $pct < 0.5 ? '0%' : ($pct < 1 ? '<1%' : round($pct) . '%');
    return '<svg viewBox="0 0 80 80" width="80" height="80" style="display:block;margin:0 auto 6px">'
         . '<circle cx="40" cy="40" r="' . $r . '" fill="none" stroke="var(--line-2)" stroke-width="9"/>'
         . '<circle cx="40" cy="40" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="9"'
         . ' stroke-linecap="round"'
         . ' stroke-dasharray="' . $dash . ' ' . $circ . '"'
         . ' transform="rotate(-90 40 40)"/>'
         . '<text x="40" y="44" text-anchor="middle" font-size="13" font-weight="700" fill="var(--heading)">' . htmlspecialchars($label) . '</text>'
         . '</svg>';
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
        ['scores',  'trophy',    'คะแนน',           null],
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
            <?php if ($p['ai_id']): ?>
            <a href="<?= h(ai_prompt_url($p['ai_id'], $p['prompt_text'] ?? '')) ?>" target="_blank" rel="noopener" style="text-decoration:none">
                <?= ai_pill($p['ai_id'], 'sm') ?>
            </a>
            <?php endif; ?>
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
          <?php if ($w['ai_id']): ?>
          <a href="<?= h(ai_prompt_url($w['ai_id'], $w['prompt_text'] ?? '')) ?>" target="_blank" rel="noopener" style="text-decoration:none">
              <?= ai_pill($w['ai_id'], 'sm') ?>
          </a>
          <?php endif; ?>
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
    <div class="card card-pad">
      <div style="display:flex;align-items:center;gap:7px;margin-bottom:14px">
        <?= icon('database', 17) ?>
        <h3 style="font-size:14px">พื้นที่จัดเก็บ</h3>
      </div>
      <div style="display:flex;gap:6px">

        <!-- Materials donut -->
        <div style="flex:1;text-align:center">
          <?= storage_donut($mat_pct, $mat_color) ?>
          <div style="font-size:11.5px;font-weight:700;color:var(--heading);margin-bottom:3px">ไฟล์เนื้อหา</div>
          <div style="font-size:10.5px;color:var(--sub);line-height:1.5">
            <?= format_bytes($mat_used) ?><br>
            <span style="color:var(--muted)">จาก <?= format_bytes($mat_quota) ?></span>
          </div>
        </div>

        <div style="width:1px;background:var(--line-2);margin:4px 0"></div>

        <!-- Submissions donut -->
        <div style="flex:1;text-align:center">
          <?= storage_donut($sub_pct, $sub_color) ?>
          <div style="font-size:11.5px;font-weight:700;color:var(--heading);margin-bottom:3px">ไฟล์งานส่ง</div>
          <div style="font-size:10.5px;color:var(--sub);line-height:1.5">
            <?= format_bytes($sub_used) ?><br>
            <span style="color:var(--muted)">จาก <?= format_bytes($sub_quota) ?></span>
          </div>
        </div>

      </div>
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
      <?php if (!empty($l['has_prompt'])): ?>
      <span class="chip" style="font-size:11.5px;padding:3px 9px"><?= icon('sparkle', 13, 'var(--primary)') ?> Prompt AI</span>
      <?php endif; ?>
      <?php if ($guest_mode): ?>
      <span class="badge" style="font-size:11px;background:var(--line-2);color:var(--sub)"><?= icon('lock', 11, 'var(--sub)') ?> ต้องเข้าสู่ระบบ</span>
      <?php endif; ?>
    </div>
    <div class="lr-title"><?= h($l['title']) ?></div>
    <div class="lr-sub" style="margin-top:4px;white-space:normal;max-width:640px"><?= h(mb_substr($l['description'], 0, 110)) ?>…</div>
    <?php if (!$guest_mode): ?>
    <div style="display:flex;gap:8px;margin-top:10px;align-items:center">
      <?php if (!empty($l['has_prompt'])): ?>
      <?= $l['ai_id'] ? ai_pill($l['ai_id'], 'sm') : '' ?>
      <?= star_rating((int)($l['rating'] ?? 0), 13) ?>
      <span class="subtle" style="font-size:12px">·</span>
      <?php endif; ?>
      <span class="subtle" style="font-size:12px"><?= $l['mat_count'] ?> ไฟล์แนบ</span>
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
    // Auto-migrate status column
    try { get_db()->exec("ALTER TABLE course_enrollments ADD COLUMN IF NOT EXISTS status ENUM('pending','active') NOT NULL DEFAULT 'active'"); } catch (PDOException) {}
    $students = db_rows('SELECT u.*, e.status AS enrollment_status FROM users u JOIN course_enrollments e ON e.user_id = u.id WHERE e.course_id = ? AND u.role = "student" ORDER BY e.status DESC, u.id', [$course_id]);
    // Auto-migrate: ensure invite table exists
    try { get_db()->exec('CREATE TABLE IF NOT EXISTS course_invites (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, course_id INT UNSIGNED NOT NULL, invite_type ENUM("link","code","email") NOT NULL DEFAULT "code", invite_token VARCHAR(40) NULL, invite_code VARCHAR(10) NULL, invited_email VARCHAR(150) NULL, created_by INT UNSIGNED NOT NULL, expires_at DATETIME NULL, max_uses INT UNSIGNED NULL, use_count INT UNSIGNED DEFAULT 0, is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE, FOREIGN KEY (created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); } catch (PDOException) {}
    $invite_code_row = db_row('SELECT * FROM course_invites WHERE course_id = ? AND invite_type = "code" ORDER BY id DESC LIMIT 1', [$course_id]);
?>
<div class="row wrap" style="align-items:flex-start">
  <div style="flex:1 1 340px;display:flex;flex-direction:column;gap:18px">
    <!-- Teacher card -->
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

    <?php if ($is_owner): ?>
    <!-- Invite code card -->
    <div class="card">
      <div class="card-head"><?= icon('globe', 18, 'var(--primary)') ?><h3>รหัสเชิญนักเรียน</h3></div>
      <div class="card-pad" style="padding-top:10px">
        <?php if ($invite_code_row && $invite_code_row['is_active']): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
          <span id="invite-code-display"
                style="flex:1;font-size:1.5rem;font-weight:800;letter-spacing:.2em;color:var(--primary);
                       background:var(--primary-soft);border-radius:10px;padding:10px 14px;text-align:center;
                       font-family:ui-monospace,monospace">
            <?= h($invite_code_row['invite_code']) ?>
          </span>
          <button class="btn btn-ghost" style="padding:10px 14px"
                  onclick="navigator.clipboard.writeText('<?= h($invite_code_row['invite_code']) ?>').then(()=>showToast('คัดลอกรหัสแล้ว'))"
                  title="คัดลอกรหัส">
            <?= icon('copy', 18) ?>
          </button>
        </div>
        <div class="subtle" style="font-size:12px;margin-bottom:12px">
          ใช้แล้ว <?= (int)$invite_code_row['use_count'] ?> ครั้ง · รหัสนี้ใช้งานได้อยู่
        </div>
        <?php elseif ($invite_code_row && !$invite_code_row['is_active']): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--line-1);border-radius:10px;margin-bottom:14px">
          <?= icon('lock', 18, 'var(--sub)') ?>
          <span style="color:var(--sub);font-size:14px">การลงทะเบียนด้วยรหัสถูกปิดอยู่</span>
        </div>
        <?php else: ?>
        <p class="subtle" style="font-size:13.5px;margin-bottom:14px">ยังไม่มีรหัสเชิญ กดสร้างรหัสเพื่อเปิดรับนักเรียน</p>
        <?php endif; ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-soft" style="gap:6px;font-size:13px" id="reset-code-btn"
                  onclick="manageInvite('reset_code')">
            <?= icon('refresh', 15) ?>
            <?= $invite_code_row ? 'รีเซ็ตรหัสใหม่' : 'สร้างรหัสเชิญ' ?>
          </button>
          <?php if ($invite_code_row): ?>
          <button class="btn btn-ghost" style="gap:6px;font-size:13px" id="toggle-code-btn"
                  onclick="manageInvite('toggle_code')">
            <?= $invite_code_row['is_active'] ? icon('lock', 15) . ' ปิดการลงทะเบียน' : icon('globe', 15) . ' เปิดการลงทะเบียน' ?>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Invite by email card -->
    <div class="card">
      <div class="card-head"><?= icon('edit', 18, 'var(--accent)') ?><h3>เชิญโดยระบุอีเมล</h3></div>
      <div class="card-pad" style="padding-top:10px">
        <p class="subtle" style="font-size:12px;margin-bottom:10px">
          วางอีเมลพร้อมกันได้หลายบรรทัด หรือคั่นด้วยเครื่องหมาย comma
        </p>
        <form id="invite-email-form" onsubmit="inviteByEmail(event)">
          <textarea class="textarea" id="invite-email-input"
                    placeholder="student1@school.ac.th&#10;student2@school.ac.th&#10;หรือ email1, email2, email3"
                    style="min-height:90px;font-size:13px;font-family:ui-monospace,monospace" required></textarea>
          <div id="invite-results" style="display:none;margin-top:10px;max-height:180px;overflow-y:auto;
               border:1px solid var(--line-2);border-radius:8px;font-size:12.5px"></div>
          <button class="btn btn-primary" type="submit" style="margin-top:10px;width:100%;gap:6px">
            <?= icon('send', 15, '#fff') ?> ส่งคำเชิญ
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div style="flex:1 1 420px">
    <div class="card">
      <?php
        $active_students  = array_filter($students, fn($s) => ($s['enrollment_status'] ?? 'active') === 'active');
        $pending_students = array_filter($students, fn($s) => ($s['enrollment_status'] ?? 'active') === 'pending');
      ?>
      <div class="card-head">
        <?= icon('users', 18, 'var(--accent)') ?><h3>นักเรียน</h3>
        <span class="badge gray" style="margin-left:auto"><?= count($active_students) ?> คน</span>
        <?php if ($pending_students): ?>
        <span class="badge" style="background:var(--warn-soft);color:#c76a13"><?= count($pending_students) ?> รอตอบรับ</span>
        <?php endif; ?>
      </div>
      <div style="padding:10px">
        <?php foreach ($students as $i => $s):
            $is_pending = ($s['enrollment_status'] ?? 'active') === 'pending';
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:9px<?= $is_pending ? ';opacity:.6' : '' ?>"
             id="student-row-<?= $s['id'] ?>">
          <?= avatar($s, 38) ?>
          <div style="min-width:0;flex:1">
            <div style="font-weight:600;color:var(--heading);font-size:14px"><?= h($s['name']) ?></div>
            <?php if ($is_pending): ?>
            <div style="font-size:11.5px;color:var(--sub)"><?= icon('clock', 12, 'var(--sub)') ?> รอตอบรับคำเชิญ</div>
            <?php endif; ?>
          </div>
          <?php if ($is_owner): ?>
          <button class="btn btn-sm btn-ghost" style="color:var(--danger)"
                  onclick="removeStudent(<?= (int)$s['id'] ?>, <?= $course_id ?>, '<?= h(addslashes($s['name'])) ?>')">
            <?= icon('x', 14) ?>
          </button>
          <?php else: ?>
          <span class="subtle" style="font-size:12.5px">เลขที่ <?= $i + 1 ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
        <p class="subtle" style="font-size:13px;padding:10px 12px">ยังไม่มีนักเรียนในรายวิชานี้</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($is_owner): ?>
<script>
const _cid = <?= $course_id ?>;

function manageInvite(action) {
    var btn = document.getElementById(action === 'reset_code' ? 'reset-code-btn' : 'toggle-code-btn');
    if (btn) { btn.disabled = true; btn.style.opacity = '.6'; }
    var fd = new FormData();
    fd.append('course_id', _cid);
    fd.append('action', action);
    fetch('api/manage_invite.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                showToast(res.message || 'สำเร็จ');
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(res.error || 'เกิดข้อผิดพลาด', true);
                if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
            }
        })
        .catch(() => {
            showToast('เกิดข้อผิดพลาด', true);
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        });
}

function inviteByEmail(e) {
    e.preventDefault();
    var raw = document.getElementById('invite-email-input').value.trim();
    if (!raw) return;
    var btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.style.opacity = '.6';
    var fd = new FormData();
    fd.append('course_id', _cid);
    fd.append('action', 'invite_email');
    fd.append('emails', raw);
    fetch('api/manage_invite.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                showToast(res.message || 'ส่งคำเชิญแล้ว');
                // แสดงผลลัพธ์แต่ละอีเมล
                var box = document.getElementById('invite-results');
                if (res.results && res.results.length > 1) {
                    box.style.display = 'block';
                    box.innerHTML = res.results.map(function(r) {
                        var ic = r.ok
                            ? '<span style="color:var(--success)">✓</span>'
                            : '<span style="color:var(--danger)">✗</span>';
                        return '<div style="display:flex;align-items:center;gap:8px;padding:6px 10px;border-bottom:1px solid var(--line-1)">'
                            + ic + '<span style="color:var(--sub);min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis">'
                            + r.email + '</span><span style="color:var(--body)">' + r.msg + '</span></div>';
                    }).join('');
                } else {
                    box.style.display = 'none';
                    document.getElementById('invite-email-input').value = '';
                }
                setTimeout(() => location.reload(), 1800);
            } else {
                showToast(res.error || 'เกิดข้อผิดพลาด', true);
            }
            btn.disabled = false; btn.style.opacity = '1';
        })
        .catch(() => {
            showToast('เกิดข้อผิดพลาด', true);
            btn.disabled = false; btn.style.opacity = '1';
        });
}

function removeStudent(sid, cid, name) {
    if (!confirm('นำ "' + name + '" ออกจากรายวิชานี้?')) return;
    var fd = new FormData();
    fd.append('course_id', cid);
    fd.append('action', 'remove_student');
    fd.append('student_id', sid);
    fetch('api/manage_invite.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                showToast(res.message || 'นำออกแล้ว');
                var row = document.getElementById('student-row-' + sid);
                if (row) { row.style.opacity = '0'; row.style.transition = 'opacity .3s'; setTimeout(() => row.remove(), 300); }
            } else {
                showToast(res.error || 'เกิดข้อผิดพลาด', true);
            }
        });
}
</script>
<?php endif; ?>


<?php
// ── SCORES tab ─────────────────────────────────────────────────
elseif ($tab === 'scores' && !$guest_mode):
    ensure_certificate_schema();
    $asgn_info  = db_row('SELECT COUNT(*) AS cnt, COALESCE(SUM(points),0) AS total FROM assignments WHERE course_id = ?', [$course_id]);
    $total_asgn = (int)($asgn_info['cnt']   ?? 0);
    $total_pts  = (int)($asgn_info['total'] ?? 0);
    $cert        = db_row('SELECT * FROM course_certificates WHERE course_id = ?', [$course_id]) ?: ['enabled'=>0,'grade_json'=>'[]'];
    $cert_enabled = (bool)($cert['enabled'] ?? 0);
    $cert_grades  = json_decode($cert['grade_json'] ?? '[]', true) ?: [];
    usort($cert_grades, fn($a,$b) => ($b['min']??0) <=> ($a['min']??0));

    function cert_grade(float $pct, array $grades): string {
        foreach ($grades as $g) { if ($pct >= (float)($g['min']??0)) return (string)($g['label']??''); }
        return '';
    }

    if (is_teacher()):
        $score_rows = db_rows('
            SELECT u.id, u.name, u.avatar_class, u.avatar_path, u.initials,
                COUNT(DISTINCT s.id) AS submitted_count,
                COALESCE(SUM(CASE WHEN s.status = "graded" THEN s.grade ELSE 0 END),0) AS earned_points
            FROM users u
            JOIN course_enrollments e ON e.user_id = u.id AND e.course_id = ?
            LEFT JOIN assignments a ON a.course_id = ?
            LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = u.id
            WHERE u.role = "student"
            GROUP BY u.id, u.name, u.avatar_class, u.avatar_path, u.initials
            ORDER BY earned_points DESC
        ', [$course_id, $course_id]);
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <div>
    <div style="font-size:13px;color:var(--sub)">คะแนนรวมเต็ม <strong style="color:var(--heading)"><?= $total_pts ?> คะแนน</strong>
      &ensp;·&ensp; <?= $total_asgn ?> งาน &ensp;·&ensp; <?= count($score_rows) ?> นักเรียน</div>
  </div>
  <?php if ($is_owner): ?>
  <button class="btn btn-soft" style="gap:7px" onclick="openModal('cert-settings')">
    <?= icon('trophy', 15) ?>
    <?= $cert_enabled ? 'เกียรติบัตร: เปิด' : 'ตั้งค่าเกียรติบัตร' ?>
    <?php if ($cert_enabled): ?><span class="badge green" style="font-size:11px">ON</span><?php endif; ?>
  </button>
  <?php endif; ?>
</div>

<?php if ($score_rows): ?>
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:14px">
  <thead>
    <tr style="border-bottom:2px solid var(--line-2);text-align:left">
      <th style="padding:10px 12px;font-weight:700;color:var(--heading)">นักเรียน</th>
      <th style="padding:10px 12px;font-weight:700;color:var(--heading);text-align:center">ส่งแล้ว</th>
      <th style="padding:10px 12px;font-weight:700;color:var(--heading);text-align:center">คะแนนที่ได้</th>
      <th style="padding:10px 12px;font-weight:700;color:var(--heading);min-width:140px">ร้อยละ</th>
      <?php if ($cert_enabled && $cert_grades): ?>
      <th style="padding:10px 12px;font-weight:700;color:var(--heading)">ระดับ</th>
      <th style="padding:10px 12px;font-weight:700;color:var(--heading)"></th>
      <?php endif; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($score_rows as $row):
        $pct    = $total_pts > 0 ? round((int)$row['earned_points'] / $total_pts * 100, 1) : 0;
        $done   = (int)$row['submitted_count'] >= $total_asgn && $total_asgn > 0;
        $glabel = $cert_enabled ? cert_grade($pct, $cert_grades) : '';
        $bar_c  = $pct >= 80 ? 'var(--primary)' : ($pct >= 60 ? 'var(--warn)' : 'var(--danger)');
    ?>
    <tr style="border-bottom:1px solid var(--line-2)">
      <td style="padding:10px 12px">
        <div style="display:flex;align-items:center;gap:9px">
          <?= avatar($row, 32) ?>
          <span style="font-weight:600;color:var(--heading)"><?= h($row['name']) ?></span>
        </div>
      </td>
      <td style="padding:10px 12px;text-align:center">
        <span style="font-weight:600;color:<?= $done ? 'var(--primary)' : 'var(--body)' ?>">
          <?= (int)$row['submitted_count'] ?>/<?= $total_asgn ?>
        </span>
      </td>
      <td style="padding:10px 12px;text-align:center;font-weight:700;color:var(--heading)">
        <?= (int)$row['earned_points'] ?>/<?= $total_pts ?>
      </td>
      <td style="padding:10px 12px">
        <div style="display:flex;align-items:center;gap:8px">
          <div style="flex:1;height:7px;background:var(--line-2);border-radius:99px;overflow:hidden">
            <div style="height:100%;width:<?= min(100,$pct) ?>%;background:<?= $bar_c ?>;border-radius:99px;transition:width .4s"></div>
          </div>
          <span style="font-size:12px;font-weight:700;color:var(--heading);flex-shrink:0;min-width:38px;text-align:right"><?= $pct ?>%</span>
        </div>
      </td>
      <?php if ($cert_enabled && $cert_grades): ?>
      <td style="padding:10px 12px">
        <?php if ($glabel): ?>
        <span class="badge green" style="font-size:12px"><?= h($glabel) ?></span>
        <?php elseif ($done): ?>
        <span class="badge gray" style="font-size:12px">ไม่ผ่านเกณฑ์</span>
        <?php else: ?>
        <span style="color:var(--sub);font-size:12px">ส่งงานยังไม่ครบ</span>
        <?php endif; ?>
      </td>
      <td style="padding:10px 12px">
        <?php if ($glabel && $done): ?>
        <a href="index.php?page=certificate&course_id=<?= $course_id ?>&student_id=<?= (int)$row['id'] ?>"
           target="_blank" class="btn btn-sm btn-ghost" style="gap:5px;text-decoration:none">
          <?= icon('trophy', 13) ?> เกียรติบัตร
        </a>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<div class="empty"><div class="e-ic"><?= icon('users', 28) ?></div><h3>ยังไม่มีนักเรียนในวิชานี้</h3></div>
<?php endif; ?>

<?php else: // student view
    $uid = current_user_id();
    $my  = db_row('
        SELECT COUNT(DISTINCT s.id) AS submitted_count,
            COALESCE(SUM(CASE WHEN s.status = "graded" THEN s.grade ELSE 0 END),0) AS earned_points
        FROM assignments a
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
        WHERE a.course_id = ?
    ', [$uid, $course_id]) ?: ['submitted_count'=>0,'earned_points'=>0];
    $my_submitted = (int)$my['submitted_count'];
    $my_earned    = (int)$my['earned_points'];
    $my_pct       = $total_pts > 0 ? round($my_earned / $total_pts * 100, 1) : 0;
    $my_done      = $my_submitted >= $total_asgn && $total_asgn > 0;
    $my_grade     = cert_grade($my_pct, $cert_grades);
    $my_bar_c     = $my_pct >= 80 ? 'var(--primary)' : ($my_pct >= 60 ? 'var(--warn)' : 'var(--danger)');
?>
<div style="max-width:520px;margin:0 auto">
  <div class="card card-pad" style="text-align:center">
    <div style="font-size:13px;color:var(--sub);margin-bottom:20px">ผลการเรียนของคุณในวิชานี้</div>

    <div style="font-size:3rem;font-weight:800;color:var(--heading);line-height:1"><?= $my_pct ?>%</div>
    <div style="font-size:14px;color:var(--sub);margin-top:4px"><?= $my_earned ?>/<?= $total_pts ?> คะแนน</div>

    <div style="margin:20px 0 6px;height:10px;background:var(--line-2);border-radius:99px;overflow:hidden">
      <div style="height:100%;width:<?= min(100,$my_pct) ?>%;background:<?= $my_bar_c ?>;border-radius:99px;transition:width .6s"></div>
    </div>

    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--sub);margin-bottom:20px">
      <span>ส่งแล้ว <?= $my_submitted ?>/<?= $total_asgn ?> งาน</span>
      <?php if ($my_done): ?><span style="color:var(--primary);font-weight:600"><?= icon('check',13,'var(--primary)') ?> ส่งครบแล้ว</span><?php endif; ?>
    </div>

    <?php if ($cert_enabled && $cert_grades): ?>
    <?php if ($my_grade): ?>
    <div style="padding:16px;background:var(--primary-soft);border-radius:12px;margin-bottom:16px">
      <div style="font-size:12px;color:var(--primary);font-weight:700;margin-bottom:4px"><?= icon('trophy',14,'var(--primary)') ?> ระดับผลการสำเร็จ</div>
      <div style="font-size:1.4rem;font-weight:800;color:var(--heading)"><?= h($my_grade) ?></div>
    </div>
    <?php if ($my_done): ?>
    <a href="index.php?page=certificate&course_id=<?= $course_id ?>&student_id=<?= $uid ?>"
       target="_blank" class="btn btn-primary" style="text-decoration:none;gap:8px;justify-content:center;width:100%">
      <?= icon('trophy', 16, '#fff') ?> ดูเกียรติบัตร
    </a>
    <?php endif; ?>
    <?php else: ?>
    <div style="padding:14px;background:var(--line-2);border-radius:10px;font-size:13px;color:var(--sub)">
      <?= $my_done ? 'คะแนนไม่ผ่านเกณฑ์รับเกียรติบัตร' : 'ส่งงานให้ครบเพื่อรับเกียรติบัตร' ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($cert_grades): ?>
  <div class="card card-pad" style="margin-top:14px">
    <div style="font-size:13px;font-weight:700;color:var(--heading);margin-bottom:12px"><?= icon('trophy',15,'var(--primary)') ?> เกณฑ์ระดับผลการสำเร็จ</div>
    <?php foreach ($cert_grades as $g): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line-2)">
      <span style="font-weight:600;color:var(--heading)"><?= h($g['label']) ?></span>
      <span class="badge gray">≥ <?= (int)$g['min'] ?>%</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
// ── Certificate settings modal (teacher only) ──────────────────
if ($is_owner):
    modal_start('cert-settings', 'ตั้งค่าเกียรติบัตร', 'trophy', false, true);
?>
<form method="post" action="api/save_certificate.php" data-ajax onsubmit="certSyncGrades()">
  <input type="hidden" name="course_id" value="<?= $course_id ?>">
  <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1.5px solid var(--line-2);border-radius:12px;cursor:pointer;margin-bottom:16px"
         id="cert-toggle-label" style="background:<?= $cert_enabled ? 'var(--accent-soft)' : 'var(--card)' ?>">
    <input type="checkbox" name="enabled" value="1" id="cert-toggle" <?= $cert_enabled ? 'checked' : '' ?>
           style="margin-top:2px;width:17px;height:17px;accent-color:var(--primary);flex-shrink:0"
           onchange="document.getElementById('cert-toggle-label').style.background=this.checked?'var(--accent-soft)':'var(--card)'">
    <div>
      <div style="font-weight:700;color:var(--heading);font-size:14.5px">เปิดระบบเกียรติบัตร</div>
      <div class="subtle" style="font-size:12.5px;margin-top:3px">นักเรียนที่ส่งงานครบและผ่านเกณฑ์จะสามารถดูและพิมพ์เกียรติบัตรได้</div>
    </div>
  </label>

  <div style="font-size:13px;font-weight:700;color:var(--heading);margin-bottom:10px;display:flex;align-items:center;gap:7px">
    <?= icon('trophy', 15) ?> ระดับผลการสำเร็จ
    <span class="subtle" style="font-weight:400;font-size:12px">(เรียงจากคะแนนสูงสุดก่อน)</span>
  </div>
  <div id="cert-grades-container">
    <?php foreach ($cert_grades as $g): ?>
    <div class="cert-grade-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center">
      <input class="input" name="grade_label[]" placeholder="ระดับ เช่น ดีเยี่ยม" value="<?= h($g['label']) ?>" style="flex:2;min-width:0">
      <input class="input" name="grade_min[]" type="number" min="0" max="100" placeholder="%" value="<?= (int)$g['min'] ?>" style="flex:0 0 80px;text-align:center">
      <span style="font-size:13px;color:var(--sub);flex-shrink:0">%</span>
      <button type="button" onclick="this.closest('.cert-grade-row').remove()"
              style="flex:0 0 32px;height:32px;border:none;border-radius:8px;background:var(--danger-soft,#fee2e2);color:var(--danger,#dc2626);cursor:pointer;font-size:18px;display:grid;place-items:center">×</button>
    </div>
    <?php endforeach; ?>
  </div>
  <button type="button" onclick="certAddGrade()" class="btn btn-sm btn-ghost" style="margin-bottom:12px">
    <?= icon('plus', 14) ?> เพิ่มระดับ
  </button>
  <input type="hidden" name="grade_json" id="cert-grade-json" value="<?= h($cert['grade_json'] ?? '[]') ?>">
  <div style="font-size:12px;color:var(--sub);padding:10px 14px;background:var(--line-2);border-radius:8px;margin-top:4px">
    <?= icon('info', 13) ?> นักเรียนต้องส่งงานครบทุกชิ้นก่อน จึงจะตรวจสอบเกณฑ์ได้
  </div>
</form>
<?php modal_foot('cert-settings', 'ยกเลิก', 'บันทึก'); ?>
<script>
function certAddGrade(label, min) {
  var c = document.getElementById('cert-grades-container');
  var row = document.createElement('div');
  row.className = 'cert-grade-row';
  row.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;align-items:center';
  var lv = label ? String(label).replace(/"/g,'&quot;') : '';
  var mv = min !== undefined ? min : '';
  row.innerHTML =
    '<input class="input" name="grade_label[]" placeholder="ระดับ เช่น ดีเยี่ยม" value="' + lv + '" style="flex:2;min-width:0">' +
    '<input class="input" name="grade_min[]" type="number" min="0" max="100" placeholder="%" value="' + mv + '" style="flex:0 0 80px;text-align:center">' +
    '<span style="font-size:13px;color:var(--sub);flex-shrink:0">%</span>' +
    '<button type="button" onclick="this.closest(\'.cert-grade-row\').remove()" style="flex:0 0 32px;height:32px;border:none;border-radius:8px;background:var(--danger-soft,#fee2e2);color:var(--danger,#dc2626);cursor:pointer;font-size:18px;display:grid;place-items:center">×</button>';
  c.appendChild(row);
}
function certSyncGrades() {
  var rows = document.querySelectorAll('#cert-grades-container .cert-grade-row');
  var data = [];
  rows.forEach(function(r) {
    var lbl = r.querySelector('[name="grade_label[]"]').value.trim();
    var mn  = parseInt(r.querySelector('[name="grade_min[]"]').value) || 0;
    if (lbl) data.push({label: lbl, min: mn});
  });
  document.getElementById('cert-grade-json').value = JSON.stringify(data);
}
</script>
<?php endif; // close if ($is_owner) for cert modal ?>

<?php endif; // close entire tab if/elseif chain ?>

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
<form id="add-lesson-form" method="post" action="api/add_lesson.php" data-ajax enctype="multipart/form-data">
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
  <?php multi_file_input('materials', 'ไฟล์ประกอบเนื้อหา') ?>

  <!-- Prompt AI (ไม่บังคับ — กดปุ่มเพื่อขยายฟอร์ม) -->
  <div id="lesson-prompt-section" style="display:none">
    <div class="ai-tint-box" style="padding:16px 16px 6px;margin-top:6px">
      <div style="display:flex;align-items:center;gap:9px;margin-bottom:12px">
        <span style="width:32px;height:32px;border-radius:9px;background:var(--card);color:var(--primary);display:grid;place-items:center"><?= icon('sparkle', 18) ?></span>
        <div>
          <div style="font-weight:700;color:var(--heading);font-size:14.5px">Prompt AI ที่แนะนำ <span style="font-weight:400;color:var(--sub);font-size:12px">(ไม่บังคับ)</span></div>
          <div class="subtle" style="font-size:12px">ระบุ prompt และ AI ที่คุณทดลองแล้วได้ผลลัพธ์น่าพอใจ</div>
        </div>
        <button type="button"
                onclick="document.getElementById('lesson-prompt-section').style.display='none';document.getElementById('lesson-prompt-text').value='';document.getElementById('lesson-add-prompt-btn').style.display='flex'"
                style="margin-left:auto;background:none;border:none;cursor:pointer;color:var(--sub);display:flex;align-items:center;gap:4px;font-size:12px">
          <?= icon('x', 14) ?> ลบออก
        </button>
      </div>
      <div class="field">
        <label>ข้อความ Prompt</label>
        <textarea id="lesson-prompt-text" class="textarea" name="prompt_text" style="font-family:ui-monospace,monospace;font-size:13px"
                  placeholder="วาง prompt ที่คุณใช้กับ AI ที่นี่…"></textarea>
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
        <?php example_file_input() ?>
      </div>
      <div class="field">
        <label>หมายเหตุ/คำแนะนำ <span class="subtle" style="font-weight:400">(ไม่บังคับ)</span></label>
        <textarea class="textarea" name="note_text" style="min-height:60px" placeholder="เช่น ให้นักเรียนลองปรับ prompt ให้ตรงกับหัวข้อตัวเอง…"></textarea>
      </div>
    </div>
  </div>
  <button type="button" id="lesson-add-prompt-btn"
          onclick="document.getElementById('lesson-prompt-section').style.display='block';this.style.display='none'"
          style="display:flex;align-items:center;gap:7px;margin-top:10px;background:none;
                 border:1.5px dashed var(--line-2);border-radius:9px;padding:8px 14px;
                 cursor:pointer;color:var(--sub);font-size:13px;width:100%;justify-content:center;
                 transition:border-color .15s,color .15s"
          onmouseenter="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
          onmouseleave="this.style.borderColor='var(--line-2)';this.style.color='var(--sub)'">
    <?= icon('sparkle', 15) ?> + เพิ่ม Prompt AI ที่แนะนำ
  </button>
</form>
<?php modal_foot('add-lesson', 'ยกเลิก', 'โพสต์เนื้อหา'); ?>

<?php
// ── Add Assignment Modal ──────────────────────────────────────
    modal_start('add-assignment', 'เพิ่มงาน / การบ้าน + Prompt AI', 'clipboard', true, true);
?>
<form id="add-assignment-form" method="post" action="api/add_assignment.php" data-ajax enctype="multipart/form-data">
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
      <select class="select" name="assignment_type" id="asgn-type-sel"
              onchange="qbToggleSections(this.value)">
        <option value="งาน">งาน</option>
        <option value="การบ้าน">การบ้าน</option>
        <option value="โครงงาน">โครงงาน</option>
        <option value="แบบทดสอบ">แบบทดสอบ</option>
      </select>
    </div>
    <div class="field" style="flex:1">
      <label>กำหนดส่ง <span style="color:var(--danger)">*</span></label>
      <input class="input" type="date" name="due_date" required>
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
  <!-- ── Quiz Builder (แสดงเฉพาะ แบบทดสอบ) ──────────────────────── -->
  <div id="asgn-quiz-section" style="display:none;margin-top:10px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <div style="font-size:14px;font-weight:700;color:var(--heading);display:flex;align-items:center;gap:7px">
        <?= icon('clipboard', 16) ?> รายการคำถาม <span id="qb-count" style="color:var(--sub);font-weight:400">(0)</span>
      </div>
    </div>

    <!-- รายการคำถาม -->
    <div id="qb-list"></div>

    <!-- ฟอร์มเพิ่ม/แก้ไขคำถาม -->
    <div id="qb-form" style="display:none;border:1.5px solid var(--primary);border-radius:11px;
                              padding:14px 16px;margin-bottom:10px;background:var(--surface-2)">
      <div style="font-size:13px;font-weight:700;color:var(--heading);margin-bottom:10px" id="qb-form-title">เพิ่มคำถาม</div>
      <div class="field" style="margin-bottom:10px">
        <label style="font-size:12.5px">ข้อคำถาม <span style="color:var(--danger)">*</span></label>
        <textarea id="qb-text" class="textarea" rows="2" placeholder="พิมพ์ข้อคำถาม..." style="font-size:13.5px;min-height:60px"></textarea>
      </div>
      <div style="display:flex;gap:10px;margin-bottom:10px">
        <div class="field" style="flex:1;margin-bottom:0">
          <label style="font-size:12.5px">ประเภท</label>
          <select id="qb-type" class="select" style="font-size:13px" onchange="qbTypeChange()">
            <option value="MCQ">เลือกตอบ (MCQ)</option>
            <option value="truefalse">ถูก / ผิด</option>
          </select>
        </div>
        <div class="field" style="flex:0 0 90px;margin-bottom:0">
          <label style="font-size:12.5px">คะแนน</label>
          <input id="qb-points" class="input" type="number" min="1" value="1" style="font-size:13px">
        </div>
      </div>

      <!-- MCQ choices -->
      <div id="qb-mcq-wrap">
        <div style="font-size:12px;font-weight:600;color:var(--sub);margin-bottom:7px">ตัวเลือก <span style="font-weight:400">(เลือกข้อที่ถูกต้อง)</span></div>
        <div id="qb-choices"></div>
        <button type="button" onclick="qbAddChoice()"
                style="display:flex;align-items:center;gap:5px;background:none;border:1px dashed var(--line-2);
                       border-radius:7px;padding:5px 12px;font-size:12.5px;color:var(--sub);cursor:pointer;margin-top:4px">
          + เพิ่มตัวเลือก
        </button>
      </div>

      <!-- True/False -->
      <div id="qb-tf-wrap" style="display:none">
        <div style="font-size:12px;font-weight:600;color:var(--sub);margin-bottom:8px">เฉลย</div>
        <div style="display:flex;gap:20px">
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13.5px">
            <input type="radio" id="qb-tf-true" name="qb-tf" value="true" checked
                   style="width:16px;height:16px;accent-color:var(--primary)"> ถูก
          </label>
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:13.5px">
            <input type="radio" id="qb-tf-false" name="qb-tf" value="false"
                   style="width:16px;height:16px;accent-color:var(--danger)"> ผิด
          </label>
        </div>
      </div>

      <div style="display:flex;gap:8px;margin-top:14px">
        <button type="button" onclick="qbSave()"
                class="btn btn-primary" style="font-size:13px;flex:1">บันทึกคำถาม</button>
        <button type="button" onclick="qbCancel()"
                class="btn btn-ghost" style="font-size:13px">ยกเลิก</button>
      </div>
    </div>

    <!-- ปุ่มเพิ่มคำถาม -->
    <button type="button" id="qb-add-btn" onclick="qbShowForm(-1)"
            style="display:flex;align-items:center;justify-content:center;gap:7px;width:100%;
                   background:none;border:1.5px dashed var(--line-2);border-radius:9px;
                   padding:8px 14px;font-size:13px;color:var(--sub);cursor:pointer;
                   transition:border-color .15s,color .15s"
            onmouseenter="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
            onmouseleave="this.style.borderColor='var(--line-2)';this.style.color='var(--sub)'">
      <?= icon('plus', 15) ?> + เพิ่มคำถามใหม่
    </button>

    <input type="hidden" name="questions_json" id="qb-json" value="[]">
  </div>

  <!-- ── Prompt AI (ซ่อนเมื่อ แบบทดสอบ) ──────────────────────── -->
  <div id="asgn-prompt-section">
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
      <textarea class="textarea" name="prompt_text" id="asgn-prompt-txt"
                style="font-family:ui-monospace,monospace;font-size:13px"
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
      <?php example_file_input() ?>
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
  </div>

  <!-- ── ลิงก์สื่อการสอน ──────────────────────── -->
  <div style="margin-top:12px;padding:14px 15px;border:1px solid var(--line-2);border-radius:10px">
    <div style="font-size:13px;font-weight:700;color:var(--heading);margin-bottom:10px;display:flex;align-items:center;gap:7px">
      <?= icon('link', 15) ?> ลิงก์สื่อการสอน <span class="subtle" style="font-weight:400;font-size:12px">(ไม่บังคับ)</span>
    </div>
    <div id="asgn-links-container"></div>
    <button type="button" onclick="addLinkRow('asgn-links-container')"
            class="btn btn-sm btn-ghost" style="margin-top:2px">
      <?= icon('plus', 14) ?> เพิ่มลิงก์
    </button>
  </div>
</form>
<?php modal_foot('add-assignment', 'ยกเลิก', 'มอบหมายงาน'); ?>

<?php endif; // is_teacher ?>

