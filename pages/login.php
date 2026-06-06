<?php
declare(strict_types=1);
// Redirect if already authenticated
if (isset($_SESSION['user_id'])) { redirect(url('dashboard')); }

$err = '';
if (!empty($_SESSION['error'])) { $err = $_SESSION['error']; unset($_SESSION['error']); }

$theme_js = "var m=localStorage.getItem('ca-theme')||'system';document.documentElement.setAttribute('data-theme',(m==='dark'||(m==='system'&&window.matchMedia('(prefers-color-scheme:dark)').matches))?'dark':'light');";
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>เข้าสู่ระบบ — ClassroomAI</title>
  <link rel="stylesheet" href="css/theme.css">
  <script><?= $theme_js ?></script>
  <style>
    body{min-height:100vh;background:var(--bg);display:grid;place-items:center;padding:2rem 1rem}
    .shell{width:100%;max-width:420px}
    .brand{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:2rem}
    .bmark{width:44px;height:44px;background:var(--primary);border-radius:13px;display:grid;place-items:center;flex:0 0 auto}
    .bname{font-size:1.45rem;font-weight:800;color:var(--heading)}
    .bname b{color:var(--primary)}
    .card{background:var(--card);border:1px solid var(--line-2);border-radius:20px;padding:2rem 2rem 1.75rem}
    .card h1{font-size:1.15rem;font-weight:700;color:var(--heading);margin:0 0 .2rem}
    .sub{font-size:.875rem;color:var(--sub);margin-bottom:1.75rem}
    .field{margin-bottom:1rem}
    .field label{display:block;font-size:.82rem;font-weight:600;color:var(--heading);margin-bottom:.38rem}
    .field input{width:100%;padding:.6rem .85rem;border:1.5px solid var(--line-2);border-radius:9px;background:var(--bg);color:var(--text);font-size:.9rem;box-sizing:border-box;transition:border-color .15s,box-shadow .15s}
    .field input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-soft)}
    .pw-wrap{position:relative}
    .pw-wrap input{padding-right:44px}
    .pw-eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--sub);padding:4px;line-height:0}
    .pw-eye:hover{color:var(--heading)}
    .alert-err{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:10px;padding:.7rem 1rem;font-size:.875rem;margin-bottom:1.25rem;display:flex;gap:8px;align-items:flex-start}
    .demo-hint{background:var(--primary-soft);border-radius:10px;padding:.7rem 1rem;font-size:.8rem;color:var(--primary);margin-bottom:1.5rem;line-height:1.6}
    .demo-hint strong{font-weight:700;color:var(--primary)}
    .auth-link{margin-top:1.5rem;text-align:center;font-size:.875rem;color:var(--sub)}
    .auth-link a{color:var(--primary);font-weight:600;text-decoration:none}
    .auth-link a:hover{text-decoration:underline}
    .divider{display:flex;align-items:center;gap:.75rem;margin:1.25rem 0;color:var(--sub);font-size:.8rem}
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--line-2)}
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
    <h1>เข้าสู่ระบบ</h1>
    <p class="sub">ยินดีต้อนรับ — กรอกข้อมูลเพื่อเข้าใช้งาน</p>

    <?php if ($err): ?>
    <div class="alert-err">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" style="flex:0 0 auto;margin-top:1px"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
      <div><?= h($err) ?></div>
    </div>
    <?php endif; ?>

    <div class="demo-hint">
      <strong>ข้อมูลทดสอบ</strong><br>
      ครู: <code>teacher@demo.com</code> / <code>demo1234</code><br>
      นักเรียน: <code>student1@demo.com</code> / <code>demo1234</code>
    </div>

    <form method="post" action="api/login.php">
      <input type="hidden" name="redirect" value="<?= h($_GET['redirect'] ?? '') ?>">

      <div class="field">
        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" placeholder="example@email.com" required
               autocomplete="email" value="<?= h($_GET['email'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="password">รหัสผ่าน</label>
        <div class="pw-wrap">
          <input type="password" id="password" name="password" placeholder="รหัสผ่าน"
                 required autocomplete="current-password">
          <button type="button" class="pw-eye" onclick="togglePw('password',this)" title="แสดง/ซ่อนรหัสผ่าน">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
              <path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem;gap:8px">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
        เข้าสู่ระบบ
      </button>
    </form>

    <div class="divider">หรือ</div>

    <div class="auth-link">
      ยังไม่มีบัญชี? <a href="<?= url('register') ?>">ลงทะเบียนที่นี่</a>
    </div>
  </div>

</div>
<script>
function togglePw(id,btn){
  var i=document.getElementById(id);
  i.type=i.type==='password'?'text':'password';
  btn.style.color=i.type==='text'?'var(--primary)':'';
}
</script>
</body>
</html>
