<?php
declare(strict_types=1);

// ── Guard: delete this file after successful installation ─────
define('CONFIG_FILE', __DIR__ . '/config/db.php');
define('SQL_FILE',    __DIR__ . '/sql/schema.sql');

// ── Helpers ───────────────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function read_config(): array
{
    $defaults = ['host' => 'localhost', 'name' => 'classroomai', 'user' => 'root', 'pass' => '', 'charset' => 'utf8mb4'];
    if (!file_exists(CONFIG_FILE)) return $defaults;
    $src = file_get_contents(CONFIG_FILE);
    preg_match("/define\('DB_HOST',\s*'([^']*)'\)/",    $src, $m1);
    preg_match("/define\('DB_NAME',\s*'([^']*)'\)/",    $src, $m2);
    preg_match("/define\('DB_USER',\s*'([^']*)'\)/",    $src, $m3);
    preg_match("/define\('DB_PASS',\s*'([^']*)'\)/",    $src, $m4);
    preg_match("/define\('DB_CHARSET',\s*'([^']*)'\)/", $src, $m5);
    return [
        'host'    => $m1[1] ?? $defaults['host'],
        'name'    => $m2[1] ?? $defaults['name'],
        'user'    => $m3[1] ?? $defaults['user'],
        'pass'    => $m4[1] ?? $defaults['pass'],
        'charset' => $m5[1] ?? $defaults['charset'],
    ];
}

function write_config(string $host, string $name, string $user, string $pass, string $charset): bool
{
    $esc = fn(string $v) => addslashes($v);
    $content = <<<PHP
<?php
declare(strict_types=1);

define('DB_HOST',    '{$esc($host)}');
define('DB_NAME',    '{$esc($name)}');
define('DB_USER',    '{$esc($user)}');
define('DB_PASS',    '{$esc($pass)}');
define('DB_CHARSET', '{$esc($charset)}');

function get_db(): PDO
{
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return \$pdo;
}
PHP;
    // Create the config/ directory if it does not exist yet
    $dir = dirname(CONFIG_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return false;
    }
    return (bool) file_put_contents(CONFIG_FILE, $content);
}

function try_connect(string $host, string $user, string $pass, string $charset): PDO
{
    return new PDO(
        "mysql:host={$host};charset={$charset}",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

// ── State ─────────────────────────────────────────────────────
$cfg      = read_config();
$step     = $_POST['step'] ?? '';
$messages = [];   // ['type'=>'ok'|'err', 'text'=>'...']
$results  = [];
$success  = false;

// ── Handle: save config ───────────────────────────────────────
if ($step === 'save_config') {
    $host    = trim($_POST['db_host']    ?? '');
    $name    = trim($_POST['db_name']    ?? '');
    $user    = trim($_POST['db_user']    ?? '');
    $pass    = $_POST['db_pass']         ?? '';
    $charset = trim($_POST['db_charset'] ?? 'utf8mb4');

    if ($host === '' || $name === '' || $user === '') {
        $messages[] = ['type' => 'err', 'text' => 'กรุณากรอกข้อมูลให้ครบถ้วน (Host, Database, Username)'];
    } else {
        // Test connection first
        try {
            $test = try_connect($host, $user, $pass, $charset);
            // Connection OK — write config
            if (write_config($host, $name, $user, $pass, $charset)) {
                $cfg        = compact('host', 'name', 'user', 'pass', 'charset');
                $messages[] = ['type' => 'ok', 'text' => 'บันทึกการตั้งค่าลง config/db.php เรียบร้อยแล้ว'];
            } else {
                $messages[] = ['type' => 'err', 'text' => 'ไม่สามารถเขียนไฟล์ config/db.php ได้ — ตรวจสอบสิทธิ์การเขียนไฟล์'];
            }
        } catch (PDOException $e) {
            $messages[] = ['type' => 'err', 'text' => 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . $e->getMessage()];
        }
    }
}

// ── Handle: install DB ────────────────────────────────────────
if ($step === 'install') {
    if (!file_exists(SQL_FILE)) {
        $messages[] = ['type' => 'err', 'text' => 'ไม่พบไฟล์ sql/schema.sql'];
    } else {
        try {
            // Pass 1: connect without dbname → create database
            $pdo1 = new PDO(
                "mysql:host={$cfg['host']};charset={$cfg['charset']}",
                $cfg['user'], $cfg['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $dbname = $cfg['name'];
            $pdo1->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $results[] = ['ok' => true, 'msg' => "CREATE DATABASE {$dbname}"];
            unset($pdo1);

            // Pass 2: reconnect WITH dbname → run all table/data statements
            $pdo = new PDO(
                "mysql:host={$cfg['host']};dbname={$dbname};charset={$cfg['charset']}",
                $cfg['user'], $cfg['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
            );

            $sql = file_get_contents(SQL_FILE);

            // Strip CREATE DATABASE / USE lines — we already handled them above
            $sql = preg_replace('/^\s*(CREATE\s+DATABASE\b[^;]*;|USE\s+\w+\s*;)/im', '', $sql);

            // Split on semicolons; skip blank and comment-only chunks
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function (string $s): bool {
                    if ($s === '') return false;
                    // Remove all comment lines; if nothing left, skip
                    $stripped = preg_replace('/^\s*--[^\n]*$/m', '', $s);
                    return trim($stripped) !== '';
                }
            );

            foreach ($statements as $stmt) {
                $pdo->exec($stmt);
                if (preg_match('/^\s*(CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?|INSERT\s+INTO\s+`?(\w+)`?)/i', $stmt, $m)) {
                    $label = isset($m[2]) && $m[2] ? "CREATE TABLE {$m[2]}" : "INSERT INTO {$m[3]}";
                    $results[] = ['ok' => true, 'msg' => $label];
                }
            }

            // ── Set demo passwords by id (reliable even if email was NULL before) ──
            $demo_hash = password_hash('demo1234', PASSWORD_DEFAULT);
            $demo_ids  = [1, 2, 3, 4, 5, 6, 7, 8];
            $ph        = implode(',', array_fill(0, count($demo_ids), '?'));
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id IN ($ph)")
                ->execute([$demo_hash, ...$demo_ids]);
            $results[] = ['ok' => true, 'msg' => 'SET demo passwords (id 1-8) → demo1234'];

            // ── Admin demo password (เฉพาะตอนยังไม่เคยตั้ง — ไม่ทับรหัสที่เปลี่ยนแล้ว) ──
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@demo.com' AND role = 'admin' AND password_hash = ''")
                ->execute([$demo_hash]);
            $results[] = ['ok' => true, 'msg' => 'SET admin password (admin@demo.com) → demo1234'];

            $success    = true;
            $messages[] = ['type' => 'ok', 'text' => 'ติดตั้งฐานข้อมูลและข้อมูลตัวอย่างเรียบร้อยแล้ว (รหัสผ่านทดสอบ: demo1234)'];
        } catch (PDOException $e) {
            $messages[] = ['type' => 'err', 'text' => $e->getMessage()];
        }
    }
}

// ── Check current DB status ───────────────────────────────────
$db_ok      = false;
$tables     = [];
$row_counts = [];
try {
    $check = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $db_ok      = true;
    $tables     = $check->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $row_counts[$t] = (int)$check->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    }
} catch (PDOException) {}

$php_ok  = version_compare(PHP_VERSION, '8.0.0', '>=');
$pdo_ok  = extension_loaded('pdo_mysql');
$sql_ok  = file_exists(SQL_FILE);
// Writable if: the file exists and is writable; OR the config/ dir exists and is writable;
// OR config/ does not exist yet but its parent dir is writable (so we can create it).
$config_dir = dirname(CONFIG_FILE);
$cfg_writable = is_writable(CONFIG_FILE)
    || (!file_exists(CONFIG_FILE) && is_dir($config_dir) && is_writable($config_dir))
    || (!is_dir($config_dir) && is_writable(dirname($config_dir)));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ติดตั้งระบบ — ClassroomAI</title>
  <link rel="stylesheet" href="css/theme.css">
  <style>
    body { min-height:100vh; background:var(--bg); display:grid; place-items:start center; padding:2.5rem 1rem; }
    .installer { width:100%; max-width:660px; }
    .brand { display:flex; align-items:center; gap:10px; margin-bottom:2rem; }
    .brand-mark { width:42px; height:42px; background:var(--primary); border-radius:12px; display:grid; place-items:center; flex:0 0 auto; }
    .brand-name { font-size:1.4rem; font-weight:700; color:var(--heading); }
    .brand-name b { color:var(--primary); }
    .steps { display:flex; gap:0; margin-bottom:2rem; border-radius:12px; overflow:hidden; border:1px solid var(--line-2); }
    .step-tab { flex:1; padding:.6rem; text-align:center; font-size:.8rem; font-weight:600; color:var(--sub); background:var(--card); border-right:1px solid var(--line-2); cursor:default; }
    .step-tab:last-child { border-right:none; }
    .step-tab.active { background:var(--primary); color:#fff; }
    .step-tab.done { background:var(--primary-soft); color:var(--primary); }
    .card { background:var(--card); border:1px solid var(--line-2); border-radius:16px; padding:1.75rem; margin-bottom:1.25rem; }
    .card h2 { font-size:1rem; font-weight:700; color:var(--heading); margin:0 0 1.25rem; display:flex; align-items:center; gap:8px; }
    .status-row { display:flex; align-items:center; justify-content:space-between; padding:.5rem 0; border-bottom:1px solid var(--line-2); font-size:.875rem; }
    .status-row:last-child { border-bottom:none; }
    .badge { padding:2px 10px; border-radius:20px; font-size:.73rem; font-weight:600; }
    .badge-ok   { background:#d1fae5; color:#065f46; }
    .badge-no   { background:#fee2e2; color:#991b1b; }
    .badge-warn { background:#fef3c7; color:#92400e; }
    .badge-info { background:var(--primary-soft); color:var(--primary); }
    /* form */
    .field { margin-bottom:1rem; }
    .field label { display:block; font-size:.82rem; font-weight:600; color:var(--heading); margin-bottom:.4rem; }
    .field label span { font-weight:400; color:var(--sub); }
    .field input, .field select { width:100%; padding:.55rem .8rem; border:1px solid var(--line-2); border-radius:8px; background:var(--bg); color:var(--text); font-size:.9rem; box-sizing:border-box; }
    .field input:focus, .field select:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-soft); }
    .field-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .field-hint { font-size:.76rem; color:var(--sub); margin-top:.3rem; }
    /* alert */
    .alert { border-radius:10px; padding:.8rem 1rem; font-size:.875rem; margin-bottom:1rem; display:flex; gap:10px; align-items:flex-start; }
    .alert-err { background:#fee2e2; color:#991b1b; }
    .alert-ok  { background:#d1fae5; color:#065f46; }
    /* result list */
    .result-list { list-style:none; padding:0; margin:0; max-height:220px; overflow-y:auto;
                   border:1px solid var(--line-2); border-radius:10px; }
    .result-list li { display:flex; align-items:center; gap:8px; padding:.4rem .85rem;
                      border-bottom:1px solid var(--line-2); font-size:.78rem; font-family:monospace; }
    .result-list li:last-child { border-bottom:none; }
    .dot { width:7px; height:7px; border-radius:50%; flex:0 0 auto; }
    .dot-ok { background:#10b981; } .dot-err { background:#ef4444; }
    /* table grid */
    .tg { display:grid; grid-template-columns:1fr auto; }
    .tg-head { font-size:.72rem; font-weight:700; color:var(--sub); text-transform:uppercase; letter-spacing:.05em; padding:.3rem 0; border-bottom:1px solid var(--line-2); }
    .tg-cell { padding:.42rem 0; border-bottom:1px solid var(--line-2); font-size:.875rem; }
    .tg-cell.r { text-align:right; color:var(--sub); }
    .tg-last .tg-cell { border-bottom:none; }
    .action-row { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1.25rem; align-items:center; }
    .warn-box { background:#fef3c7; border:1px solid #fcd34d; border-radius:10px; padding:.7rem 1rem;
                font-size:.82rem; color:#92400e; display:flex; gap:8px; align-items:flex-start; margin-bottom:1rem; }
    .note-box { background:var(--bg); border-radius:10px; padding:.75rem 1rem; font-size:.8rem; color:var(--sub); margin-top:1rem; line-height:1.7; }
    .note-box code { background:var(--line-2); padding:1px 5px; border-radius:4px; color:var(--heading); font-size:.78rem; }
    .icon-sm { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; margin-top:1px; }
  </style>
</head>
<body>
<div class="installer">

  <div class="brand">
    <div class="brand-mark">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"><path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"/></svg>
    </div>
    <div class="brand-name">Classroom<b>AI</b> — ติดตั้งระบบ</div>
  </div>

  <!-- Steps indicator -->
  <?php
  $step1_done = $cfg_writable && $pdo_ok && $php_ok;
  $step2_done = $db_ok && count($tables) > 0;
  ?>
  <div class="steps">
    <div class="step-tab <?= $step2_done ? 'done' : ($step1_done ? 'done' : 'active') ?>">① ตรวจสอบระบบ</div>
    <div class="step-tab <?= $step2_done ? 'done' : ($step1_done ? 'active' : '') ?>">② ตั้งค่าการเชื่อมต่อ</div>
    <div class="step-tab <?= $step2_done ? 'active' : '' ?>">③ ติดตั้งฐานข้อมูล</div>
  </div>

  <!-- ── Flash messages ── -->
  <?php foreach ($messages as $msg): ?>
  <div class="alert alert-<?= $msg['type'] ?>">
    <?php if ($msg['type'] === 'ok'): ?>
    <svg class="icon-sm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5 11 15l4.5-5"/></svg>
    <?php else: ?>
    <svg class="icon-sm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
    <?php endif; ?>
    <div><?= h($msg['text']) ?></div>
  </div>
  <?php endforeach; ?>

  <!-- ── Card 1: System check ── -->
  <div class="card">
    <h2>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      ตรวจสอบระบบ
    </h2>
    <div class="status-row">
      <span>PHP Version (<?= PHP_VERSION ?>)</span>
      <span class="badge <?= $php_ok ? 'badge-ok' : 'badge-no' ?>"><?= $php_ok ? '✓ PHP 8+' : '✗ ต้องการ PHP 8+' ?></span>
    </div>
    <div class="status-row">
      <span>PDO MySQL Extension</span>
      <span class="badge <?= $pdo_ok ? 'badge-ok' : 'badge-no' ?>"><?= $pdo_ok ? '✓ พร้อม' : '✗ ไม่มี extension' ?></span>
    </div>
    <div class="status-row">
      <span>sql/schema.sql</span>
      <span class="badge <?= $sql_ok ? 'badge-ok' : 'badge-no' ?>"><?= $sql_ok ? '✓ พบไฟล์' : '✗ ไม่พบไฟล์' ?></span>
    </div>
    <div class="status-row">
      <span>สิทธิ์เขียน config/db.php</span>
      <span class="badge <?= $cfg_writable ? 'badge-ok' : 'badge-no' ?>"><?= $cfg_writable ? '✓ เขียนได้' : '✗ ไม่มีสิทธิ์' ?></span>
    </div>
    <div class="status-row">
      <span>การเชื่อมต่อ MariaDB (<?= h($cfg['host']) ?>)</span>
      <span class="badge <?= $db_ok ? 'badge-ok' : 'badge-warn' ?>">
        <?= $db_ok ? '✓ เชื่อมต่อสำเร็จ' : '— ยังไม่ได้เชื่อมต่อ' ?>
      </span>
    </div>
    <?php if ($db_ok): ?>
    <div class="status-row">
      <span>ฐานข้อมูล <strong><?= h($cfg['name']) ?></strong></span>
      <span class="badge <?= count($tables) > 0 ? 'badge-ok' : 'badge-warn' ?>">
        <?= count($tables) > 0 ? count($tables) . ' ตาราง' : 'ยังไม่มีตาราง' ?>
      </span>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Card 2: DB Config form ── -->
  <div class="card">
    <h2>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93A10 10 0 1 0 21 12"/><path d="M21 3v4h-4"/></svg>
      ตั้งค่าการเชื่อมต่อฐานข้อมูล
    </h2>

    <form method="post">
      <input type="hidden" name="step" value="save_config">

      <div class="field-row">
        <div class="field">
          <label>Host <span>(เซิร์ฟเวอร์ฐานข้อมูล)</span></label>
          <input type="text" name="db_host" value="<?= h($cfg['host']) ?>" placeholder="localhost" required>
        </div>
        <div class="field">
          <label>Port <span>(ถ้าไม่ใช่ค่าเริ่มต้น)</span></label>
          <input type="text" name="db_port" value="3306" placeholder="3306">
          <div class="field-hint">XAMPP ใช้พอร์ต 3306 เป็นค่าเริ่มต้น</div>
        </div>
      </div>

      <div class="field">
        <label>ชื่อฐานข้อมูล <span>(Database Name)</span></label>
        <input type="text" name="db_name" value="<?= h($cfg['name']) ?>" placeholder="classroomai" required>
        <div class="field-hint">หากยังไม่มีจะสร้างขึ้นใหม่อัตโนมัติเมื่อติดตั้ง</div>
      </div>

      <div class="field-row">
        <div class="field">
          <label>ชื่อผู้ใช้ <span>(Username)</span></label>
          <input type="text" name="db_user" value="<?= h($cfg['user']) ?>" placeholder="root" required autocomplete="username">
        </div>
        <div class="field">
          <label>รหัสผ่าน <span>(Password)</span></label>
          <input type="password" name="db_pass" value="<?= h($cfg['pass']) ?>" placeholder="เว้นว่างถ้าไม่มี" autocomplete="current-password">
        </div>
      </div>

      <div class="field">
        <label>Character Set</label>
        <select name="db_charset">
          <option value="utf8mb4" <?= $cfg['charset'] === 'utf8mb4' ? 'selected' : '' ?>>utf8mb4 (แนะนำ — รองรับ Emoji และภาษาไทย)</option>
          <option value="utf8"    <?= $cfg['charset'] === 'utf8'    ? 'selected' : '' ?>>utf8</option>
        </select>
      </div>

      <div class="action-row" style="margin-top:.5rem">
        <button type="submit" class="btn btn-primary" style="gap:8px">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          บันทึกการตั้งค่า
        </button>
        <?php if ($db_ok): ?>
        <span class="badge badge-ok" style="font-size:.8rem;padding:6px 12px">✓ เชื่อมต่อได้</span>
        <?php endif; ?>
      </div>

      <div class="note-box">
        การตั้งค่าจะถูกบันทึกลงในไฟล์ <code>config/db.php</code> — ระบบจะทดสอบการเชื่อมต่อก่อนบันทึกทุกครั้ง
      </div>
    </form>
  </div>

  <!-- ── Card 3: Install ── -->
  <div class="card">
    <h2>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
      ติดตั้งฐานข้อมูล
    </h2>

    <?php if (!$db_ok): ?>
    <div class="warn-box">
      <svg class="icon-sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
      <span>กรุณาตั้งค่าการเชื่อมต่อฐานข้อมูลและบันทึกก่อน แล้วจึงกดติดตั้ง</span>
    </div>
    <?php elseif (count($tables) > 0): ?>
    <div class="warn-box">
      <svg class="icon-sm" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>
      <span>ฐานข้อมูลมีข้อมูลอยู่แล้ว การรันซ้ำจะไม่ลบข้อมูลเดิม (ใช้ <code style="background:#fef3c7;padding:1px 5px;border-radius:4px">IF NOT EXISTS</code> + <code style="background:#fef3c7;padding:1px 5px;border-radius:4px">INSERT IGNORE</code>)</span>
    </div>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
    <ul class="result-list" style="margin-bottom:1rem">
      <?php foreach ($results as $r): ?>
      <li><span class="dot <?= $r['ok'] ? 'dot-ok' : 'dot-err' ?>"></span><?= h($r['msg']) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($db_ok && count($tables) > 0): ?>
    <div class="tg" style="margin-bottom:1rem">
      <div class="tg-head">ตาราง</div>
      <div class="tg-head" style="text-align:right">แถว</div>
      <?php foreach ($tables as $i => $t): ?>
      <div class="tg-cell <?= $i === count($tables)-1 ? '' : '' ?>"><?= h($t) ?></div>
      <div class="tg-cell r"><?= number_format($row_counts[$t]) ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="action-row">
      <form method="post">
        <input type="hidden" name="step" value="install">
        <button type="submit" class="btn <?= $db_ok ? (count($tables) > 0 ? 'btn-ghost' : 'btn-primary') : 'btn-ghost' ?>"
                style="gap:8px" <?= !$db_ok ? 'disabled title="ต้องเชื่อมต่อฐานข้อมูลก่อน"' : '' ?>>
          <?php if (count($tables) > 0): ?>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          ติดตั้งซ้ำ / Re-seed
          <?php else: ?>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="<?= $db_ok ? '#fff' : 'currentColor' ?>" stroke-width="1.7" stroke-linecap="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
          ติดตั้งฐานข้อมูล
          <?php endif; ?>
        </button>
      </form>

      <?php if ($db_ok && count($tables) > 0): ?>
      <a href="index.php" class="btn btn-primary" style="gap:8px;text-decoration:none">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        เปิดแอปพลิเคชัน
      </a>
      <?php endif; ?>
    </div>

    <?php if ($success): ?>
    <div class="note-box">
      <strong style="color:var(--heading)">หมายเหตุด้านความปลอดภัย:</strong><br>
      ลบไฟล์ <code>install.php</code> ออกหลังจากติดตั้งเสร็จแล้ว เพื่อป้องกันการรันซ้ำโดยไม่ตั้งใจ
    </div>
    <?php endif; ?>
  </div>

</div><!-- /.installer -->
</body>
</html>
