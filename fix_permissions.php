<?php
/**
 * fix_permissions.php — ตั้ง permission โฟลเดอร์ uploads/ ให้ web server เขียนได้
 * ลบไฟล์นี้ออกหลังใช้งานเสร็จแล้ว
 */

$secret = 'classroomai_fix_2024';
$via_cli = PHP_SAPI === 'cli';
if (!$via_cli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (($_GET['secret'] ?? '') !== $secret) {
        http_response_code(403);
        exit("403 Forbidden\n");
    }
}

$base = __DIR__;
$uploads = $base . '/uploads';
$examples = $base . '/uploads/examples';
$htaccess = "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .rb\nRemoveHandler .php .php3\n";

echo "=== ClassroomAI — Fix Permissions ===\n\n";
echo "PHP running as: " . get_current_user() . " (uid=" . (function_exists('posix_geteuid') ? posix_geteuid() : 'n/a') . ")\n";
echo "Base path: {$base}\n\n";

// ── สร้างโฟลเดอร์ผ่าน PHP (ให้ owner เป็น Apache user) ──────────────
foreach ([$uploads, $examples] as $dir) {
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0777, true)) {
            echo "✓ mkdir: {$dir}\n";
        } else {
            // อ่าน error จริง
            $err = error_get_last();
            echo "✗ mkdir ล้มเหลว: {$dir}\n";
            echo "  error: " . ($err['message'] ?? 'unknown') . "\n";
        }
    } else {
        echo "  มีอยู่แล้ว: {$dir}\n";
        // แสดง owner ปัจจุบัน
        if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
            $owner = posix_getpwuid(fileowner($dir));
            echo "  owner: " . ($owner['name'] ?? fileowner($dir)) . "\n";
        }
    }

    @chmod($dir, 0777);
    echo "  permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";
    echo "  writable: " . (is_writable($dir) ? "YES ✓" : "NO ✗") . "\n\n";
}

// ── วาง .htaccess ──────────────────────────────────────────────────────
foreach ([$uploads, $examples] as $dir) {
    if (is_dir($dir) && !file_exists($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', $htaccess);
    }
}

// ── ทดสอบเขียนไฟล์ ────────────────────────────────────────────────────
$test = $examples . '/.write_test_' . time();
if (@file_put_contents($test, 'ok') !== false) {
    @unlink($test);
    echo "✅ เขียนไฟล์ได้แล้ว — อัปโหลดใช้งานได้ปกติ\n";
    echo "\nลบไฟล์นี้ด้วย: rm {$base}/fix_permissions.php\n";
} else {
    echo "❌ ยังเขียนไม่ได้ — ต้องรันผ่าน SSH:\n\n";
    echo "  sudo chown -R www-data:www-data {$uploads}\n";
    echo "  sudo chmod -R 775 {$uploads}\n\n";
    echo "  ถ้าไม่มี sudo:\n";
    echo "  chmod 777 {$examples}\n\n";

    // ลองหา user ที่ถูกต้อง
    if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
        $web_uid = posix_geteuid();
        echo "  หรือ: sudo chown -R {$web_uid} {$uploads} && sudo chmod -R 775 {$uploads}\n";
    }
}
