<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/provinces.php';

// Redirect if already authenticated
if (isset($_SESSION['user_id'])) { redirect(url('dashboard')); }

$err       = '';
$active_role = $_GET['role'] ?? 'teacher'; // pre-select tab
$invite_code = $_GET['invite'] ?? '';

if (!empty($_SESSION['error'])) { $err = $_SESSION['error']; unset($_SESSION['error']); }

$provinces  = get_provinces();
$theme_js = "var m=localStorage.getItem('ca-theme')||'system';document.documentElement.setAttribute('data-theme',(m==='dark'||(m==='system'&&window.matchMedia('(prefers-color-scheme:dark)').matches))?'dark':'light');";

function province_opts(string $sel = ''): string {
    $out = '<option value="">— เลือกจังหวัด —</option>';
    foreach (get_provinces() as $p) {
        $s = $p === $sel ? ' selected' : '';
        $out .= "<option value=\"{$p}\"{$s}>{$p}</option>";
    }
    return $out;
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ลงทะเบียน — ClassroomAI</title>
  <link rel="stylesheet" href="css/theme.css">
  <script><?= $theme_js ?></script>
  <style>
    body{min-height:100vh;background:var(--bg);display:grid;place-items:start center;padding:2.5rem 1rem}
    .shell{width:100%;max-width:520px}
    .brand{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:2rem}
    .bmark{width:44px;height:44px;background:var(--primary);border-radius:13px;display:grid;place-items:center;flex:0 0 auto}
    .bname{font-size:1.45rem;font-weight:800;color:var(--heading)}
    .bname b{color:var(--primary)}
    .card{background:var(--card);border:1px solid var(--line-2);border-radius:20px;padding:2rem}
    .card h1{font-size:1.1rem;font-weight:700;color:var(--heading);margin:0 0 1.5rem}

    /* Role tabs */
    .role-tabs{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1.5px solid var(--line-2);border-radius:12px;overflow:hidden;margin-bottom:1.75rem}
    .role-tab{display:flex;align-items:center;justify-content:center;gap:8px;padding:.65rem 1rem;font-size:.88rem;font-weight:600;color:var(--sub);background:var(--card);cursor:pointer;border:none;border-right:1.5px solid var(--line-2);transition:all .15s}
    .role-tab:last-child{border-right:none}
    .role-tab.active{background:var(--primary);color:#fff}
    .role-tab:not(.active):hover{background:var(--primary-soft);color:var(--primary)}

    /* Student type info */
    .type-info{border-radius:10px;padding:.7rem 1rem;font-size:.82rem;line-height:1.6;margin-bottom:1.5rem;border:1px solid var(--line-2)}
    .type-info strong{font-weight:700}

    /* Fields */
    .field{margin-bottom:.95rem}
    .field label{display:block;font-size:.82rem;font-weight:600;color:var(--heading);margin-bottom:.38rem}
    .field label .req{color:#ef4444}
    .field input,.field select{width:100%;padding:.58rem .85rem;border:1.5px solid var(--line-2);border-radius:9px;background:var(--bg);color:var(--text);font-size:.9rem;box-sizing:border-box;transition:border-color .15s,box-shadow .15s}
    .field input:focus,.field select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-soft)}
    .field-row{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
    .field-hint{font-size:.75rem;color:var(--sub);margin-top:.3rem}
    .pw-wrap{position:relative}
    .pw-wrap input{padding-right:44px}
    .pw-eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--sub);padding:4px;line-height:0}
    .pw-eye:hover{color:var(--heading)}
    .section-lbl{font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sub);margin:.5rem 0 .85rem;padding-bottom:.5rem;border-bottom:1px solid var(--line-2)}

    /* Invite code box */
    .invite-box{background:var(--primary-soft);border:1px dashed var(--primary);border-radius:10px;padding:.85rem 1rem;margin-bottom:1rem}
    .invite-box label{color:var(--primary);font-weight:700;font-size:.85rem}
    .invite-box input{border-color:var(--primary-soft-2,var(--line-2));text-transform:uppercase;letter-spacing:.08em;font-weight:600;font-size:.95rem}
    .invite-box .field-hint{color:var(--primary)}

    /* Alerts */
    .alert-err{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:10px;padding:.75rem 1rem;font-size:.875rem;margin-bottom:1.25rem;display:flex;gap:8px;align-items:flex-start}

    .auth-link{margin-top:1.5rem;text-align:center;font-size:.875rem;color:var(--sub)}
    .auth-link a{color:var(--primary);font-weight:600;text-decoration:none}
    .auth-link a:hover{text-decoration:underline}
  </style>
</head>
<body>
<div class="shell">

  <div class="brand">
    <div class="bmark">
      <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round">
        <path d="M12 4.5 13.6 9 18 10.5 13.6 12 12 16.5 10.4 12 6 10.5 10.4 9z"/>
        <path d="M18.5 4.5l.6 1.6 1.6.6-1.6.6-.6 1.6-.6-1.6-1.6-.6 1.6-.6z"/>
      </svg>
    </div>
    <div class="bname">Classroom<b>AI</b></div>
  </div>

  <div class="card">
    <h1>สร้างบัญชีผู้ใช้ใหม่</h1>

    <!-- Role selector tabs -->
    <div class="role-tabs" id="role-tabs">
      <button type="button" class="role-tab <?= $active_role === 'teacher' ? 'active' : '' ?>"
              onclick="switchRole('teacher')">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M15.5 4.5l4 4L8 20H4v-4z"/></svg>
        ลงทะเบียนครู
      </button>
      <button type="button" class="role-tab <?= $active_role === 'student' ? 'active' : '' ?>"
              onclick="switchRole('student')">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 1 2-2h14"/></svg>
        ลงทะเบียนนักเรียน
      </button>
    </div>

    <?php if ($err): ?>
    <div class="alert-err">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" style="flex:0 0 auto;margin-top:1px"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
      <div><?= h($err) ?></div>
    </div>
    <?php endif; ?>

    <!-- Teacher form -->
    <form method="post" action="api/register.php" id="form-teacher"
          style="<?= $active_role !== 'teacher' ? 'display:none' : '' ?>">
      <input type="hidden" name="role" value="teacher">

      <div class="type-info" style="background:var(--primary-soft);color:var(--primary)">
        <strong>สำหรับครูและอาจารย์</strong> — หลังลงทะเบียนสามารถสร้างรายวิชา เพิ่มบทเรียน งาน และจัดการห้องเรียนได้ทันที
      </div>

      <div class="section-lbl">ข้อมูลส่วนตัว</div>

      <div class="field-row">
        <div class="field">
          <label>ชื่อ-สกุล <span class="req">*</span></label>
          <input type="text" name="name" placeholder="อ. สมชาย ใจดี" required autocomplete="name">
        </div>
        <div class="field">
          <label>หมายเลขโทรศัพท์ <span class="req">*</span></label>
          <input type="tel" name="phone" placeholder="08x-xxx-xxxx" required>
        </div>
      </div>

      <div class="field">
        <label>อีเมล <span class="req">*</span></label>
        <input type="email" name="email" placeholder="teacher@school.ac.th" required autocomplete="email">
      </div>

      <div class="field-row">
        <div class="field">
          <label>รหัสผ่าน <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="password" id="t-pass" placeholder="อย่างน้อย 6 ตัว" required autocomplete="new-password">
            <button type="button" class="pw-eye" onclick="togglePw('t-pass',this)">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div class="field">
          <label>ยืนยันรหัสผ่าน <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="password_confirm" id="t-pass2" placeholder="พิมพ์อีกครั้ง" required autocomplete="new-password">
            <button type="button" class="pw-eye" onclick="togglePw('t-pass2',this)">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div class="section-lbl">สถานศึกษา</div>

      <div class="field">
        <label>ชื่อสถานศึกษา <span class="req">*</span></label>
        <input type="text" name="school" placeholder="โรงเรียน / มหาวิทยาลัย / สถาบัน" required>
      </div>

      <div class="field">
        <label>จังหวัดที่ตั้งของสถานศึกษา <span class="req">*</span></label>
        <select name="province" required>
          <?= province_opts() ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.75rem;gap:8px">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        สมัครเป็นครู
      </button>
    </form>

    <!-- Student form -->
    <form method="post" action="api/register.php" id="form-student"
          style="<?= $active_role !== 'student' ? 'display:none' : '' ?>">
      <input type="hidden" name="role" value="student">

      <div class="type-info" style="background:#f0fdf4;color:#166534;border-color:#bbf7d0">
        <strong>สำหรับนักเรียน / บุคคลทั่วไป</strong> — สามารถเรียนในรายวิชาสาธารณะ หรือใส่รหัสเชิญจากครูเพื่อเข้าห้องเรียน
      </div>

      <div class="section-lbl">ข้อมูลส่วนตัว</div>

      <div class="field-row">
        <div class="field">
          <label>ชื่อ-สกุล <span class="req">*</span></label>
          <input type="text" name="name" placeholder="ชื่อ นามสกุล" required autocomplete="name">
        </div>
        <div class="field">
          <label>หมายเลขโทรศัพท์ <span class="req">*</span></label>
          <input type="tel" name="phone" placeholder="08x-xxx-xxxx" required>
        </div>
      </div>

      <div class="field">
        <label>อีเมล <span class="req">*</span></label>
        <input type="email" name="email" placeholder="student@email.com" required autocomplete="email">
      </div>

      <div class="field-row">
        <div class="field">
          <label>รหัสผ่าน <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="password" id="s-pass" placeholder="อย่างน้อย 6 ตัว" required autocomplete="new-password">
            <button type="button" class="pw-eye" onclick="togglePw('s-pass',this)">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div class="field">
          <label>ยืนยันรหัสผ่าน <span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="password_confirm" id="s-pass2" placeholder="พิมพ์อีกครั้ง" required autocomplete="new-password">
            <button type="button" class="pw-eye" onclick="togglePw('s-pass2',this)">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div class="section-lbl">สถานศึกษา</div>

      <div class="field">
        <label>ชื่อสถานศึกษา <span class="req">*</span></label>
        <input type="text" name="school" placeholder="โรงเรียน / มหาวิทยาลัย / บุคคลทั่วไป" required>
      </div>

      <div class="field">
        <label>จังหวัดที่ตั้งของสถานศึกษา <span class="req">*</span></label>
        <select name="province" required>
          <?= province_opts() ?>
        </select>
      </div>

      <?php if ($invite_code): ?>
      <!-- Pre-filled invite code from URL -->
      <input type="hidden" name="invite_code" value="<?= h($invite_code) ?>">
      <div class="invite-box" style="background:#d1fae5;border-color:#6ee7b7">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#065f46" stroke-width="1.7" stroke-linecap="round" style="vertical-align:middle;margin-right:5px"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5 11 15l4.5-5"/></svg>
        <strong style="color:#065f46">รหัสเชิญ: <?= h($invite_code) ?></strong> — คุณจะเข้าห้องเรียนอัตโนมัติหลังลงทะเบียน
      </div>
      <?php else: ?>
      <div class="invite-box">
        <div class="field" style="margin-bottom:0">
          <label>รหัสเชิญ (ถ้ามี)</label>
          <input type="text" name="invite_code" placeholder="เช่น ABC12345" maxlength="10"
                 style="text-transform:uppercase;letter-spacing:.08em;font-weight:600">
          <div class="field-hint">ใส่รหัสที่ครูแจ้ง เพื่อเข้าห้องเรียนอัตโนมัติ (ไม่บังคับ)</div>
        </div>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.75rem;gap:8px;background:#16a34a">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        สมัครเป็นนักเรียน
      </button>
    </form>

    <div class="auth-link">
      มีบัญชีแล้ว? <a href="<?= url('login') ?>">เข้าสู่ระบบ</a>
    </div>
  </div>

</div>

<script>
var currentRole = '<?= h($active_role) ?>';

function switchRole(role) {
  currentRole = role;
  document.querySelectorAll('.role-tab').forEach(function(t) {
    t.classList.toggle('active', t.getAttribute('onclick').includes("'" + role + "'"));
  });
  document.getElementById('form-teacher').style.display = role === 'teacher' ? '' : 'none';
  document.getElementById('form-student').style.display = role === 'student' ? '' : 'none';
}

function togglePw(id,btn){
  var i=document.getElementById(id);
  i.type=i.type==='password'?'text':'password';
  btn.style.color=i.type==='text'?'var(--primary)':'';
}

// Init from URL ?role=
(function(){
  var p=new URLSearchParams(location.search);
  if(p.has('role')) switchRole(p.get('role'));
})();
</script>
</body>
</html>
