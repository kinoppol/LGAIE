<?php
declare(strict_types=1);

/**
 * ตัวตรวจสอบการติดตั้ง — ต้อง require ได้ "ก่อน" config/db.php และ functions.php
 * จึงห้ามพึ่งพาอะไรจากสองไฟล์นั้นเลย
 *
 * install_guard()     → ยังไม่มี config/db.php  ⇒ ส่งไปหน้าติดตั้ง
 * install_guard_db()  → ต่อฐานข้อมูลไม่ได้ / ยังไม่ได้ import schema ⇒ ส่งไปหน้าติดตั้ง
 */

const INSTALL_GUARD_CONFIG = __DIR__ . '/../config/db.php';
const INSTALL_GUARD_SCRIPT = __DIR__ . '/../install.php';

/** ตารางที่ต้องมีเสมอหลังติดตั้งเสร็จ ใช้เป็นตัวชี้วัดว่า schema ถูก import แล้ว */
const INSTALL_GUARD_TABLE = 'users';

/** ส่งผู้ใช้ไปหน้าติดตั้ง หรือแสดงคำแนะนำถ้าไฟล์ติดตั้งถูกลบไปแล้ว */
function install_guard_redirect(string $reason): never
{
    if (is_file(INSTALL_GUARD_SCRIPT)) {
        header('Location: install.php?from=guard');
        exit;
    }
    install_guard_fail($reason);
}

/** หน้าแจ้งเตือนแบบสแตนด์อโลน (ใช้เมื่อไม่มี install.php ให้ส่งต่อ) */
function install_guard_fail(string $reason): never
{
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    $msg = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="th"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ยังไม่ได้ติดตั้งระบบ · ClassroomAI</title>
<style>
 body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f7fb;
      font-family:system-ui,"Segoe UI",sans-serif;color:#1f2333}
 .box{max-width:32rem;background:#fff;border:1px solid #e3e6ef;border-radius:14px;
      padding:2rem 2.25rem;box-shadow:0 8px 28px rgba(20,25,50,.07)}
 h1{margin:0 0 .5rem;font-size:1.25rem}
 p{margin:.5rem 0;line-height:1.7;color:#4a5068}
 code{background:#f1f3f9;border-radius:5px;padding:.15rem .4rem;font-size:.9em}
</style></head><body>
<div class="box">
  <h1>ยังไม่ได้ติดตั้งระบบ</h1>
  <p>{$msg}</p>
  <p>ไม่พบไฟล์ <code>install.php</code> จึงส่งไปหน้าติดตั้งอัตโนมัติไม่ได้
     กรุณากู้ไฟล์ติดตั้งกลับมา แล้วเปิด <code>install.php</code> ผ่านเบราว์เซอร์
     หรือสร้าง <code>config/db.php</code> เองแล้วนำเข้า <code>sql/schema.sql</code></p>
</div></body></html>
HTML;
    exit;
}

/** เรียกก่อน require config/db.php */
function install_guard(): void
{
    if (!is_file(INSTALL_GUARD_CONFIG)) {
        unset($_SESSION['db_installed']);
        install_guard_redirect('ยังไม่พบไฟล์ตั้งค่าฐานข้อมูล (config/db.php)');
    }
}

/** เรียกหลัง require config/db.php — ตรวจว่าต่อฐานข้อมูลได้และ import schema แล้ว */
function install_guard_db(): void
{
    // ตรวจครั้งเดียวต่อ session เพื่อไม่ให้เสีย query ทุก request
    if (!empty($_SESSION['db_installed'])) return;

    try {
        get_db()->query('SELECT 1 FROM ' . INSTALL_GUARD_TABLE . ' LIMIT 1');
        $_SESSION['db_installed'] = true;
    } catch (Throwable) {
        unset($_SESSION['db_installed']);
        install_guard_redirect('เชื่อมต่อฐานข้อมูลไม่ได้ หรือยังไม่ได้นำเข้าโครงสร้างตาราง');
    }
}
