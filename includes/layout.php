<?php
declare(strict_types=1);

function layout_start(string $page_title = 'ClassroomAI'): void
{
    $role    = current_role();
    $user    = current_user();
    $courses = get_courses_with_stats();
    $theme   = $_SESSION['theme'] ?? 'system';

    $badge_count = is_teacher()
        ? count_pending_for_teacher()
        : count_pending_for_student(current_user_id());

    // Notifications list
    $uid = current_user_id();
    if (is_teacher()) {
        $notifications = db_rows('
            SELECT s.id, s.assignment_id, u.name AS student_name, a.title AS asgn_title,
                   c.name AS course_name, s.submitted_at AS ts
            FROM submissions s
            JOIN users u        ON u.id = s.student_id
            JOIN assignments a  ON a.id = s.assignment_id
            JOIN courses c      ON c.id = a.course_id
            WHERE c.teacher_id = ? AND s.status = "submitted"
              AND c.is_archived = 0
            ORDER BY s.submitted_at DESC LIMIT 15
        ', [$uid]);
    } else {
        $notifications = db_rows('
            SELECT a.id, a.title AS asgn_title, c.name AS course_name,
                   a.due_date AS ts, a.due_short
            FROM assignments a
            JOIN courses c ON c.id = a.course_id
            JOIN course_enrollments e ON e.course_id = c.id AND e.user_id = ?
            WHERE c.is_archived = 0
              AND NOT EXISTS (
                SELECT 1 FROM submissions s
                WHERE s.assignment_id = a.id AND s.student_id = ?
            )
            ORDER BY a.id DESC LIMIT 15
        ', [$uid, $uid]);
    }

    $active = $_GET['page'] ?? 'dashboard';
    ?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($page_title) ?> — ClassroomAI</title>
  <?php if (!empty($_SESSION['success'])): ?>
  <meta name="flash-success" content="<?= h($_SESSION['success']) ?>">
  <?php unset($_SESSION['success']); endif; ?>
  <?php if (!empty($_SESSION['error'])): ?>
  <meta name="flash-error" content="<?= h($_SESSION['error']) ?>">
  <?php unset($_SESSION['error']); endif; ?>
  <link rel="icon" href="<?= asset('assets/favicon.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
  <script>
    (function(){
      var m = localStorage.getItem('ca-theme') || '<?= h($theme) ?>';
      var dark = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme:dark)').matches);
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
  </script>
</head>
<body>
<div class="app">

<!-- ── SIDEBAR ──────────────────────────────────────────────── -->
<aside class="sidebar">
  <div class="sidebar__brand">
    <img src="<?= asset('assets/ovec-logo.svg') ?>" alt="ClassroomAI" style="height:38px;width:auto;flex:0 0 auto">
    <span class="brand-name">Classroom<b>AI</b></span>
  </div>
  <nav class="nav-scroll">
    <?php if (is_admin()): ?>
    <div class="nav-label">ผู้ดูแลระบบ</div>
    <?php
    $cur_tab = $_GET['tab'] ?? 'users';
    $nav_admin = [
        ['users',   'users',    'จัดการผู้ใช้'],
        ['storage', 'database', 'พื้นที่จัดเก็บไฟล์'],
    ];
    foreach ($nav_admin as [$tb, $ic, $lbl]):
        $act = ($active === 'admin' && $cur_tab === $tb) ? ' active' : '';
    ?>
    <a href="<?= url('admin', ['tab' => $tb]) ?>" class="nav-item<?= $act ?>">
        <?= icon($ic, 20) ?> <?= $lbl ?>
    </a>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="nav-label">เมนูหลัก</div>

    <?php
    $nav_main = [
        ['dashboard', 'home',      'หน้าหลัก'],
        ['courses',   'grid',      'รายวิชาทั้งหมด'],
    ];
    foreach ($nav_main as [$pg, $ic, $lbl]):
        $act = $active === $pg ? ' active' : '';
    ?>
    <a href="<?= url($pg) ?>" class="nav-item<?= $act ?>">
        <?= icon($ic, 20) ?> <?= $lbl ?>
    </a>
    <?php endforeach; ?>

    <?php
    $wq_page  = is_teacher() ? 'tograde' : 'todo';
    $wq_label = is_teacher() ? 'งานรอตรวจ' : 'งานที่ต้องส่ง';
    $wq_act   = $active === $wq_page ? ' active' : '';
    ?>
    <a href="<?= url($wq_page) ?>" class="nav-item<?= $wq_act ?>">
        <?= icon('clipboard', 20) ?> <?= $wq_label ?>
        <?php if ($badge_count > 0): ?>
        <span class="nav-badge"><?= $badge_count ?></span>
        <?php endif; ?>
    </a>

    <?php if (!is_teacher()): ?>
    <a href="<?= url('browse') ?>" class="nav-item<?= $active === 'browse' ? ' active' : '' ?>">
        <?= icon('search', 20) ?> ค้นหารายวิชา
    </a>
    <?php endif; ?>

    <div class="nav-label">รายวิชาของฉัน</div>
    <?php
    $cur_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
    foreach ($courses as $c):
        $act = $cur_course === (int)$c['id'] ? ' active' : '';
    ?>
    <a href="<?= url('course', ['course_id' => $c['id'], 'tab' => 'stream']) ?>"
       class="nav-item<?= $act ?>">
        <span style="width:20px;height:20px;border-radius:6px;background:<?= h($c['banner']) ?>;flex:0 0 auto"></span>
        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($c['name']) ?></span>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="nav-label">อื่น ๆ</div>
    <a href="<?= url('calendar') ?>" class="nav-item<?= $active === 'calendar' ? ' active' : '' ?>"><?= icon('calendar', 20) ?> ปฏิทิน</a>
    <a href="<?= url('profile') ?>" class="nav-item<?= $active === 'profile' ? ' active' : '' ?>"><?= icon('settings', 20) ?> ตั้งค่า</a>
  </nav>

  <div class="sidebar__foot">
    <div class="course-mini">
      <span style="width:34px;height:34px;border-radius:9px;background:var(--primary-soft);color:var(--primary);display:grid;place-items:center;flex:0 0 auto">
        <?= icon('bulb', 18) ?>
      </span>
      <div style="line-height:1.3">
        <div class="cm-name">ใช้ AI อย่างมีสติ</div>
        <div class="cm-sub">ตรวจสอบคำตอบเสมอ</div>
      </div>
    </div>
    <div style="margin-top:10px;text-align:center;font-size:11px;color:var(--faint);letter-spacing:.04em;user-select:none">
      v<?= get_app_version() ?>
    </div>
  </div>
</aside>

<!-- ── MAIN ─────────────────────────────────────────────────── -->
<div class="main">

<!-- TOPBAR -->
<header class="topbar">
  <div class="searchbox">
    <?= icon('search', 18) ?>
    <input type="text" placeholder="ค้นหารายวิชา งาน หรือ prompt…">
  </div>
  <div class="topbar__spacer"></div>

  <!-- Theme toggle -->
  <div class="theme-switch" id="theme-switch" title="โหมดสี">
    <button type="button" data-theme="light" title="สว่าง"><?= icon('sun', 17) ?></button>
    <button type="button" data-theme="dark"  title="มืด"><?= icon('moon', 17) ?></button>
    <button type="button" data-theme="system" title="ตามระบบ"><?= icon('monitor', 17) ?></button>
  </div>


  <button class="icon-btn"><?= icon('message', 19) ?></button>

  <!-- Bell + notification dropdown -->
  <div style="position:relative;display:flex;align-items:center">
    <button class="icon-btn" id="bell-btn" onclick="toggleBellMenu()" title="การแจ้งเตือน">
      <?= icon('bell', 19) ?>
    </button>
    <?php if ($badge_count > 0): ?>
    <span style="position:absolute;top:6px;right:6px;width:9px;height:9px;border-radius:50%;
                 background:var(--danger);border:2px solid var(--bg);pointer-events:none" id="bell-dot"></span>
    <?php endif; ?>

    <div id="bell-dropdown" style="display:none;position:absolute;top:calc(100% + 8px);right:0;z-index:200;
      background:var(--card);border:1px solid var(--line-2);border-radius:14px;width:340px;
      box-shadow:0 8px 28px rgba(0,0,0,.15);overflow:hidden">

      <!-- Header -->
      <div style="display:flex;align-items:center;justify-content:space-between;
                  padding:.75rem 1rem;border-bottom:1px solid var(--line-2)">
        <span style="font-size:.9rem;font-weight:700;color:var(--heading)">
          <?= icon('bell', 15, 'var(--primary)') ?> การแจ้งเตือน
        </span>
        <?php if ($badge_count > 0): ?>
        <span class="badge" style="background:var(--primary);color:#fff;font-size:.72rem"><?= $badge_count ?> รายการ</span>
        <?php endif; ?>
      </div>

      <!-- Items -->
      <div style="max-height:360px;overflow-y:auto">
        <?php if (empty($notifications)): ?>
        <div style="padding:2rem 1rem;text-align:center;color:var(--sub);font-size:.85rem">
          <?= icon('check-circle', 32, 'var(--line-2)') ?><br>
          <span style="display:block;margin-top:.5rem">ไม่มีการแจ้งเตือนใหม่</span>
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $n): ?>
        <?php if (is_teacher()): ?>
        <a href="<?= url('assignment', ['assignment_id' => $n['assignment_id'], 'highlight' => $n['id']]) ?>" style="display:flex;gap:12px;padding:.75rem 1rem;
           text-decoration:none;border-bottom:1px solid var(--line-2);transition:background .12s"
           onmouseenter="this.style.background='var(--primary-soft)'" onmouseleave="this.style.background=''">
          <span style="width:36px;height:36px;border-radius:10px;background:var(--warn-soft);color:#c76a13;
                        display:grid;place-items:center;flex:0 0 auto">
            <?= icon('clipboard', 17, '#c76a13') ?>
          </span>
          <div style="min-width:0">
            <div style="font-size:.83rem;font-weight:600;color:var(--heading);
                         overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= h($n['student_name']) ?> ส่งงาน
            </div>
            <div style="font-size:.76rem;color:var(--sub);margin-top:2px;
                         overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= h($n['asgn_title']) ?> · <?= h($n['course_name']) ?>
            </div>
            <div style="font-size:.72rem;color:var(--sub);margin-top:2px">
              <?= h(date('d M', strtotime($n['ts']))) ?>
            </div>
          </div>
        </a>
        <?php else: ?>
        <a href="<?= url('assignment', ['assignment_id' => $n['id']]) ?>" style="display:flex;gap:12px;padding:.75rem 1rem;
           text-decoration:none;border-bottom:1px solid var(--line-2);transition:background .12s"
           onmouseenter="this.style.background='var(--primary-soft)'" onmouseleave="this.style.background=''">
          <span style="width:36px;height:36px;border-radius:10px;background:var(--primary-soft);color:var(--primary);
                        display:grid;place-items:center;flex:0 0 auto">
            <?= icon('flag', 17, 'var(--primary)') ?>
          </span>
          <div style="min-width:0">
            <div style="font-size:.83rem;font-weight:600;color:var(--heading);
                         overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              งานค้างส่ง: <?= h($n['asgn_title']) ?>
            </div>
            <div style="font-size:.76rem;color:var(--sub);margin-top:2px">
              <?= h($n['course_name']) ?>
            </div>
            <div style="font-size:.72rem;color:#ef4444;margin-top:2px;font-weight:600">
              กำหนดส่ง <?= h($n['due_short'] ?? $n['ts'] ?? '') ?>
            </div>
          </div>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Footer -->
      <?php if (!empty($notifications)): ?>
      <div style="padding:.6rem 1rem;border-top:1px solid var(--line-2);text-align:center">
        <a href="<?= url(is_teacher() ? 'tograde' : 'todo') ?>"
           style="font-size:.82rem;color:var(--primary);font-weight:600;text-decoration:none">
          ดูทั้งหมด →
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- User chip + dropdown -->
  <div style="position:relative">
    <div class="user-chip" style="cursor:pointer" onclick="toggleUserMenu()">
      <?= avatar($user, 40) ?>
      <div>
        <div class="u-name"><?= h($user['name'] ?? '') ?></div>
        <div class="u-role"><?= h($role === 'admin' ? 'ผู้ดูแลระบบ' : (!empty($user['school']) ? $user['school'] : ($role === 'teacher' ? 'ครูผู้สอน' : 'นักเรียน'))) ?></div>
      </div>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" style="color:var(--sub);flex:0 0 auto"><path d="M6 9l6 6 6-6"/></svg>
    </div>
    <div id="user-dropdown" style="display:none;position:absolute;top:calc(100% + 8px);right:0;z-index:200;
      background:var(--card);border:1px solid var(--line-2);border-radius:12px;padding:.4rem;min-width:180px;
      box-shadow:0 8px 28px rgba(0,0,0,.13)">
      <!-- User info header -->
      <div style="padding:.5rem .85rem .6rem;border-bottom:1px solid var(--line-2);margin-bottom:.35rem">
        <div style="display:flex;align-items:center;gap:9px;margin-bottom:.35rem">
          <?= avatar($user, 34) ?>
          <div>
            <div style="font-size:.82rem;font-weight:700;color:var(--heading);line-height:1.3"><?= h($user['name'] ?? '') ?></div>
            <div style="font-size:.72rem;color:var(--sub)"><?= h($user['email'] ?? '') ?></div>
          </div>
        </div>
        <?php if (!empty($user['school'])): ?>
        <div style="font-size:.73rem;color:var(--sub);padding-left:1px">
          <?= icon('folder', 12, 'var(--sub)') ?> <?= h($user['school']) ?>
          <?php if (!empty($user['province'])): ?> · <?= h($user['province']) ?><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <!-- Profile link -->
      <a href="<?= url('profile') ?>"
         style="display:flex;align-items:center;gap:9px;padding:.5rem .85rem;border-radius:8px;
                text-decoration:none;font-size:.875rem;color:var(--text);font-weight:500;transition:background .12s"
         onmouseenter="this.style.background='var(--primary-soft)'" onmouseleave="this.style.background=''">
        <?= icon('settings', 15, 'var(--sub)') ?>
        แก้ไขโปรไฟล์
      </a>
      <!-- Logout -->
      <a href="api/logout.php"
         style="display:flex;align-items:center;gap:9px;padding:.5rem .85rem;border-radius:8px;
                text-decoration:none;font-size:.875rem;color:#ef4444;font-weight:600;transition:background .12s"
         onmouseenter="this.style.background='#fee2e2'" onmouseleave="this.style.background=''">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.7" stroke-linecap="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
        <?= is_impersonating() ? 'กลับสู่ผู้ดูแลระบบ' : 'ออกจากระบบ' ?>
      </a>
    </div>
  </div>
</header>

<?php if (is_impersonating()): ?>
<!-- Impersonation banner -->
<div style="background:#f59e0b;color:#1a1a2e;padding:9px 20px;display:flex;align-items:center;gap:12px;
     font-size:13.5px;flex-wrap:wrap;box-shadow:0 1px 6px rgba(0,0,0,.12)">
  <?= icon('user', 16, '#1a1a2e') ?>
  <span>
    คุณกำลังใช้งานในนามของ <b><?= h($user['name'] ?? '') ?></b>
    (<?= h($role === 'teacher' ? 'ครู' : ($role === 'admin' ? 'ผู้ดูแลระบบ' : 'นักเรียน')) ?>)
    — สวมสิทธิ์โดย <b><?= h($_SESSION['impersonator_name'] ?? 'ผู้ดูแลระบบ') ?></b>
  </span>
  <a href="api/impersonate.php?action=stop"
     style="margin-left:auto;background:#1a1a2e;color:#fff;padding:5px 14px;border-radius:7px;
            text-decoration:none;font-weight:600;font-size:12.5px;display:inline-flex;align-items:center;gap:6px">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
    กลับสู่ผู้ดูแลระบบ
  </a>
</div>
<?php endif; ?>

<!-- PAGE CONTENT -->
<?php
}

function layout_end(): void
{
    ?>
</div><!-- .main -->
</div><!-- .app -->

<!-- Toast container -->
<div id="toast-container" style="position:fixed;bottom:26px;left:50%;transform:translateX(-50%);z-index:200;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none"></div>

<!-- AI data for JS -->
<script>
window.AI_TOOLS = <?= json_encode(array_values(get_ai_tools()), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
<?php
}

// ── Guest layout (ไม่มี sidebar / notification) ──────────────────────────────
function layout_start_guest(string $page_title = 'ClassroomAI'): void
{
    $theme = $_SESSION['theme'] ?? 'system';
    ?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($page_title) ?> — ClassroomAI</title>
  <link rel="icon" href="<?= asset('assets/favicon.svg') ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
  <script>
    (function(){
      var m = localStorage.getItem('ca-theme') || '<?= h($theme) ?>';
      var dark = m === 'dark' || (m === 'system' && window.matchMedia('(prefers-color-scheme:dark)').matches);
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    })();
  </script>
  <style>
    .guest-wrap { max-width: 960px; margin: 0 auto; padding: 0 1.25rem 3rem; }
  </style>
</head>
<body>

<!-- Guest topbar -->
<header style="background:var(--card);border-bottom:1px solid var(--line-2);padding:0 1.5rem;
               height:58px;display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:100;
               box-shadow:0 1px 4px rgba(0,0,0,.06)">
  <a href="index.php?page=explore" style="display:flex;align-items:center;gap:9px;text-decoration:none">
    <img src="<?= asset('assets/ovec-logo.svg') ?>" alt="ClassroomAI" style="height:32px;width:auto">
  </a>
  <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
    <!-- Theme toggle -->
    <div class="theme-switch" id="theme-switch" title="โหมดสี">
      <button type="button" data-theme="light" title="สว่าง"><?= icon('sun', 17) ?></button>
      <button type="button" data-theme="dark"  title="มืด"><?= icon('moon', 17) ?></button>
      <button type="button" data-theme="system" title="ตามระบบ"><?= icon('monitor', 17) ?></button>
    </div>
    <a href="index.php?page=login" class="btn btn-ghost" style="text-decoration:none;font-size:.875rem">
      เข้าสู่ระบบ
    </a>
    <a href="index.php?page=register" class="btn btn-primary" style="text-decoration:none;font-size:.875rem">
      สมัครสมาชิก
    </a>
  </div>
</header>

<div class="guest-wrap">
<?php
}

function layout_end_guest(): void
{
    ?>
</div><!-- .guest-wrap -->

<!-- Toast container -->
<div id="toast-container" style="position:fixed;bottom:26px;left:50%;transform:translateX(-50%);z-index:200;display:flex;flex-direction:column;align-items:center;gap:8px;pointer-events:none"></div>

<!-- AI data for JS -->
<script>
window.AI_TOOLS = <?= json_encode(array_values(get_ai_tools()), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
<?php
}

// ── Modal helper ─────────────────────────────────────────────────────────────
function modal_start(string $id, string $title, string $icon_name = '', bool $wide = false, bool $persist = false): void
{
    $w = $wide ? ' wide' : '';
    $bg_click    = $persist ? '' : " onclick=\"closeModalOnBg(event,'" . h($id) . "')\"";
    $data_persist = $persist ? ' data-persist="1"' : '';
    ?>
    <div class="modal-overlay" id="<?= h($id) ?>-overlay" style="display:none"<?= $bg_click ?><?= $data_persist ?>>
      <div class="modal<?= $w ?>">
        <div class="modal__head">
          <?php if ($icon_name): ?>
          <span style="width:38px;height:38px;border-radius:10px;background:var(--primary-soft);color:var(--primary);display:grid;place-items:center;flex:0 0 auto">
            <?= icon($icon_name, 20) ?>
          </span>
          <?php endif; ?>
          <h3><?= h($title) ?></h3>
          <button type="button" class="x-btn" onclick="closeModal('<?= h($id) ?>')"><?= icon('x', 18) ?></button>
        </div>
        <div class="modal__body">
    <?php
}

function modal_foot(string $id, string $cancel_label = 'ยกเลิก', string $submit_label = 'บันทึก', string $submit_cls = 'btn-primary'): void
{
    ?>
        </div><!-- /.modal__body -->
        <div class="modal__foot">
          <button type="button" class="btn btn-ghost" onclick="closeModal('<?= h($id) ?>')"><?= $cancel_label ?></button>
          <button type="button" class="btn <?= h($submit_cls) ?>" id="<?= h($id) ?>-submit"
                  onclick="var f=this.closest('.modal').querySelector('form');if(f){f.requestSubmit?f.requestSubmit():f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))}">
            <?= icon('send', 17, '#fff') ?> <?= h($submit_label) ?>
          </button>
        </div>
      </div><!-- /.modal -->
    </div><!-- /.modal-overlay -->
    <?php
}
