<?php
/**
 * Storage Setup Script — ClassroomAI
 * เปิดหน้านี้ครั้งเดียวผ่านเบราว์เซอร์เพื่อสร้างโฟลเดอร์และตรวจสอบสิทธิ์
 */
$is_win  = DIRECTORY_SEPARATOR === '\\';
$base    = __DIR__;
$up_root = $base . '/uploads/';

$subdirs = ['materials', 'submissions', 'examples'];
$htaccess = "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .rb\nRemoveHandler .php .php3\nphp_flag engine off\n";

$all_ok  = true;
$results = [];

function try_mkdir(string $path): array
{
    if (is_dir($path)) return ['ok' => true, 'msg' => '✅ มีอยู่แล้ว'];
    if (@mkdir($path, 0775, true)) return ['ok' => true, 'msg' => '✅ สร้างสำเร็จ'];
    return ['ok' => false, 'msg' => '❌ สร้างล้มเหลว (สิทธิ์ไม่พอ)'];
}

function try_write(string $dir): bool
{
    $f = $dir . '.wtest_' . getmypid();
    $ok = @file_put_contents($f, 'ok') !== false;
    if ($ok) @unlink($f);
    return $ok;
}

// uploads/ root
$r = try_mkdir($up_root);
$results['uploads/'] = [array_merge($r, ['label' => 'uploads/'])];
if (!$r['ok']) $all_ok = false;
else {
    @chmod($up_root, 0775);
    if (!try_write($up_root)) {
        // Try exec chmod
        @exec('chmod 775 ' . escapeshellarg(realpath($up_root)));
        if (!try_write($up_root)) {
            $results['uploads/'][] = ['ok' => false, 'msg' => '❌ เขียนไม่ได้ — ดูคำสั่ง SSH ด้านล่าง'];
            $all_ok = false;
        } else {
            $results['uploads/'][] = ['ok' => true, 'msg' => '✅ เขียนได้หลัง chmod'];
        }
    } else {
        $results['uploads/'][] = ['ok' => true, 'msg' => '✅ เขียนได้'];
    }
}

foreach ($subdirs as $sub) {
    $dir  = $up_root . $sub . '/';
    $rows = [];

    // mkdir
    $r = try_mkdir($dir);
    $rows[] = $r;
    if (!$r['ok']) { $all_ok = false; $results[$sub . '/'] = $rows; continue; }

    // .htaccess
    $ha = $dir . '.htaccess';
    if (!file_exists($ha)) {
        $wrote = @file_put_contents($ha, $htaccess);
        $rows[] = ['ok' => $wrote !== false, 'msg' => $wrote !== false ? '✅ สร้าง .htaccess' : '⚠️ ไม่สามารถสร้าง .htaccess'];
    } else {
        $rows[] = ['ok' => true, 'msg' => '✅ .htaccess มีอยู่แล้ว'];
    }

    // permission & write test
    @chmod($dir, 0775);
    if (try_write($dir)) {
        $rows[] = ['ok' => true, 'msg' => '✅ เขียนได้'];
    } else {
        // try exec chmod
        @exec('chmod 775 ' . escapeshellarg(realpath($dir) ?: $dir));
        if (try_write($dir)) {
            $rows[] = ['ok' => true, 'msg' => '✅ เขียนได้หลัง chmod'];
        } else {
            $rows[] = ['ok' => false, 'msg' => '❌ เขียนไม่ได้ — ดูคำสั่ง SSH ด้านล่าง'];
            $all_ok = false;
        }
    }

    $results[$sub . '/'] = $rows;
}

$realbase  = realpath($base) ?: $base;
$real_up   = realpath($up_root) ?: $up_root;
$ssh_cmds  = [
    "# รันคำสั่งนี้บน server (SSH) เพื่อแก้สิทธิ์:",
    "cd " . $realbase,
    "mkdir -p uploads/materials uploads/submissions uploads/examples",
    "chmod -R 775 uploads/",
    "chown -R www-data:www-data uploads/   # หรือ apache:apache ถ้าใช้ CentOS/RHEL",
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Storage Setup — ClassroomAI</title>
<style>
  *{box-sizing:border-box}
  body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 20px;background:#f8fafc;color:#1e293b}
  h1{font-size:22px;margin-bottom:2px}
  .sub{color:#64748b;font-size:14px;margin-bottom:28px}
  .card{background:#fff;border-radius:12px;padding:18px 22px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
  .dir{font-family:monospace;font-weight:700;font-size:13.5px;color:#0f172a;margin-bottom:10px}
  li{list-style:none;font-size:13px;padding:3px 0}
  .ok{background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:16px 20px;margin-top:24px}
  .ok h2{color:#15803d;margin:0 0 6px;font-size:16px}
  .ok p{margin:0;color:#166534;font-size:14px}
  .err{background:#fef2f2;border:1.5px solid #fca5a5;border-radius:10px;padding:16px 20px;margin-top:24px}
  .err h2{color:#dc2626;margin:0 0 8px;font-size:16px}
  pre{background:#1e293b;color:#e2e8f0;padding:16px;border-radius:10px;font-size:12.5px;overflow-x:auto;white-space:pre-wrap;margin:10px 0 0}
  .info{background:#eff6ff;border:1.5px solid #93c5fd;border-radius:10px;padding:14px 18px;margin-top:16px;font-size:13px;color:#1e40af}
  .meta{margin-top:20px;font-size:12px;color:#94a3b8}
</style>
</head>
<body>
<h1>🗂️ Storage Setup</h1>
<p class="sub">ตรวจสอบและสร้างโฟลเดอร์เก็บไฟล์แนบ — ClassroomAI</p>

<?php foreach ($results as $label => $rows): ?>
<div class="card">
  <div class="dir">uploads/<?= htmlspecialchars($label) ?></div>
  <ul>
    <?php foreach ($rows as $row): ?>
    <li><?= htmlspecialchars($row['msg']) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endforeach; ?>

<?php if ($all_ok): ?>
<div class="ok">
  <h2>✅ พร้อมใช้งาน</h2>
  <p>โฟลเดอร์ทั้งหมดสร้างและเขียนได้ — <a href="index.php" style="color:#15803d">กลับไป ClassroomAI</a> แล้วลองอัปโหลดไฟล์อีกครั้ง</p>
</div>
<?php else: ?>
<div class="err">
  <h2>❌ ยังมีโฟลเดอร์ที่เขียนไม่ได้</h2>
  <p style="color:#7f1d1d;font-size:13px;margin-bottom:8px">PHP ไม่มีสิทธิ์เขียน — ต้อง SSH เข้า server แล้วรันคำสั่งด้านล่าง:</p>
  <pre><?= htmlspecialchars(implode("\n", $ssh_cmds)) ?></pre>
  <p style="margin-top:10px;font-size:12.5px;color:#991b1b">หลังรันคำสั่งแล้ว reload หน้านี้ใหม่เพื่อตรวจสอบ</p>
</div>
<?php endif; ?>

<div class="info">
  ℹ️ ลบหรือเปลี่ยนชื่อไฟล์นี้หลังจากใช้งานเสร็จเพื่อความปลอดภัย
</div>

<p class="meta">
  Server path: <?= htmlspecialchars($realbase) ?> |
  PHP user: <?= htmlspecialchars(get_current_user()) ?> |
  OS: <?= PHP_OS ?> |
  Server: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') ?>
</p>
</body>
</html>
