<?php
/**
 * Storage Setup Script
 * เปิดหน้านี้ครั้งเดียวเพื่อสร้างโฟลเดอร์เก็บไฟล์และตรวจสอบสิทธิ์
 * URL: http://localhost/LGAIE/setup_storage.php
 */
$dirs = [
    'uploads'              => __DIR__ . '/uploads/',
    'uploads/materials'    => __DIR__ . '/uploads/materials/',
    'uploads/submissions'  => __DIR__ . '/uploads/submissions/',
    'uploads/examples'     => __DIR__ . '/uploads/examples/',
];

$htaccess = "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .rb\nRemoveHandler .php .php3\nphp_flag engine off\n";

$results = [];

foreach ($dirs as $label => $path) {
    $status = [];

    // 1. สร้างโฟลเดอร์ถ้าไม่มี
    if (!is_dir($path)) {
        $made = @mkdir($path, 0775, true);
        $status[] = $made ? '✅ สร้างโฟลเดอร์สำเร็จ' : '❌ สร้างโฟลเดอร์ล้มเหลว';
    } else {
        $status[] = '✅ โฟลเดอร์มีอยู่แล้ว';
    }

    // 2. chmod (Unix only)
    @chmod($path, 0775);

    // 3. เขียน .htaccess
    $ha = $path . '.htaccess';
    if ($label !== 'uploads' && !file_exists($ha)) {
        $wrote = @file_put_contents($ha, $htaccess);
        $status[] = $wrote !== false ? '✅ สร้าง .htaccess แล้ว' : '⚠️ ไม่สามารถสร้าง .htaccess';
    }

    // 4. ทดสอบเขียนไฟล์จริง
    $test = $path . '.wtest_setup';
    $ok   = (@file_put_contents($test, 'ok') !== false);
    if ($ok) {
        @unlink($test);
        $status[] = '✅ เขียนไฟล์ได้ (writable)';
    } else {
        // Windows: ลอง icacls
        if (DIRECTORY_SEPARATOR === '\\') {
            $real = str_replace('/', '\\', realpath($path) ?: $path);
            $out  = [];
            @exec("icacls \"{$real}\" /grant Everyone:(OI)(CI)F /T 2>&1", $out);
            $status[] = '🔧 รัน icacls: ' . implode(' ', $out);
            $ok2 = (@file_put_contents($test, 'ok') !== false);
            if ($ok2) {
                @unlink($test);
                $status[] = '✅ เขียนไฟล์ได้หลัง icacls';
            } else {
                $status[] = '❌ ยังเขียนไม่ได้ — ต้องแก้สิทธิ์ด้วยตัวเอง';
            }
        } else {
            $status[] = '❌ เขียนไม่ได้ — รัน: chmod 775 ' . realpath($path);
        }
    }

    $results[$label] = $status;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Storage Setup — ClassroomAI</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; background: #f8fafc; color: #1e293b; }
  h1   { font-size: 22px; margin-bottom: 4px; }
  .sub { color: #64748b; font-size: 14px; margin-bottom: 28px; }
  .card { background: white; border-radius: 12px; padding: 18px 22px; margin-bottom: 16px;
          box-shadow: 0 1px 4px rgba(0,0,0,.08); }
  .dir  { font-family: monospace; font-size: 13.5px; font-weight: 700; color: #0f172a; margin-bottom: 10px; }
  li    { font-size: 13px; padding: 3px 0; list-style: none; }
  .done { background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 10px; padding: 16px 20px; margin-top: 24px; }
  .done h2 { color: #15803d; margin: 0 0 6px; font-size: 16px; }
  .done p  { margin: 0; color: #166534; font-size: 14px; }
  .info { background: #eff6ff; border: 1.5px solid #93c5fd; border-radius: 10px; padding: 14px 18px; margin-top: 16px; font-size: 13px; color: #1e40af; }
</style>
</head>
<body>
<h1>🗂️ Storage Setup</h1>
<p class="sub">ตรวจสอบและสร้างโฟลเดอร์เก็บไฟล์แนบสำหรับระบบ ClassroomAI</p>

<?php foreach ($results as $label => $steps): ?>
<div class="card">
  <div class="dir"><?= htmlspecialchars($label) ?>/</div>
  <ul>
    <?php foreach ($steps as $s): ?>
    <li><?= htmlspecialchars($s) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endforeach; ?>

<div class="done">
  <h2>✅ ดำเนินการเสร็จสิ้น</h2>
  <p>กลับไปที่ <a href="index.php" style="color:#15803d">ClassroomAI</a> แล้วลองอัปโหลดไฟล์อีกครั้ง</p>
</div>

<div class="info">
  ℹ️ ลบหรือเปลี่ยนชื่อไฟล์นี้หลังจากใช้งานเสร็จเพื่อความปลอดภัย
</div>

<p style="margin-top:20px;font-size:12px;color:#94a3b8">
  PHP user: <?= htmlspecialchars(get_current_user()) ?> |
  Server: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') ?> |
  OS: <?= PHP_OS ?>
</p>
</body>
</html>
