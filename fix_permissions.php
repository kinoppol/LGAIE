<?php
/**
 * fix_permissions.php — ตั้ง permission โฟลเดอร์ uploads/ ให้ web server เขียนได้
 *
 * วิธีใช้:
 *   - ผ่าน SSH: php fix_permissions.php
 *   - ผ่านเบราว์เซอร์: http://your-domain/LGAIE/fix_permissions.php?secret=YOUR_SECRET
 *
 * ลบไฟล์นี้ออกหลังใช้งานเสร็จแล้ว
 */

// ── Security: ต้องใส่ secret ถ้าเรียกผ่าน HTTP ──────────────────────────
$secret = 'classroomai_fix_2024'; // เปลี่ยนก่อนใช้งานจริง

$via_cli = PHP_SAPI === 'cli';
if (!$via_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (($_GET['secret'] ?? '') !== $secret) {
        http_response_code(403);
        exit("403 Forbidden — เพิ่ม ?secret={$secret} ใน URL\n");
    }
}

$base = __DIR__;
$dirs = [
    $base . '/uploads',
    $base . '/uploads/examples',
];

$htaccess = <<<'HTA'
Options -ExecCGI
AddHandler cgi-script .php .pl .py .rb
RemoveHandler .php .php3
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
HTA;

$ok  = true;
$log = [];

foreach ($dirs as $dir) {
    // สร้างถ้ายังไม่มี
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0775, true)) {
            $log[] = "✓ สร้างโฟลเดอร์: {$dir}";
        } else {
            $log[] = "✗ สร้างไม่ได้: {$dir} — ลอง: mkdir -p {$dir}";
            $ok = false;
            continue;
        }
    }

    // ตั้ง permission
    if (@chmod($dir, 0775)) {
        $log[] = "✓ chmod 775: {$dir}";
    } else {
        $log[] = "! chmod ล้มเหลว (อาจต้องรัน: chmod 775 {$dir})";
    }

    // ตรวจสอบ
    if (is_writable($dir)) {
        $log[] = "✓ เขียนได้แล้ว: {$dir}";
    } else {
        $log[] = "✗ ยังเขียนไม่ได้: {$dir}";
        $log[] = "  → ลองรัน: sudo chown -R www-data:www-data {$dir} && sudo chmod -R 775 {$dir}";
        $ok = false;
    }
}

// วาง .htaccess ถ้ายังไม่มี
foreach ($dirs as $dir) {
    $htpath = $dir . '/.htaccess';
    if (is_dir($dir) && !file_exists($htpath)) {
        if (@file_put_contents($htpath, $htaccess) !== false) {
            $log[] = "✓ สร้าง .htaccess: {$htpath}";
        }
    }
}

// ทดสอบเขียนไฟล์จริง
$test = $base . '/uploads/examples/.write_test';
if (@file_put_contents($test, 'ok') !== false) {
    @unlink($test);
    $log[] = "✓ ทดสอบเขียนไฟล์สำเร็จ";
} else {
    $log[] = "✗ ทดสอบเขียนไฟล์ล้มเหลว";
    $ok = false;
}

// Output
echo ($ok ? "✅ สำเร็จ — อัปโหลดไฟล์ได้แล้ว\n" : "❌ ยังมีปัญหา — ดูรายละเอียดด้านล่าง\n");
echo str_repeat('─', 60) . "\n";
echo implode("\n", $log) . "\n";
echo str_repeat('─', 60) . "\n";

if (!$ok) {
    echo "\n📋 คำสั่ง SSH สำหรับ Linux:\n";
    echo "  sudo chown -R www-data:www-data {$base}/uploads\n";
    echo "  sudo chmod -R 775 {$base}/uploads\n";
    echo "\n  หรือ (ถ้าไม่มี sudo):\n";
    echo "  chmod 777 {$base}/uploads/examples\n";
}

if (!$via_cli) {
    echo "\n⚠️  ลบไฟล์นี้ออกหลังใช้งานเสร็จแล้ว:\n  rm {$base}/fix_permissions.php\n";
}
