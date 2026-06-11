<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) json_err('Forbidden — เฉพาะผู้ดูแลระบบ', 403);

$action  = $_POST['action'] ?? '';
$user_id = (int)($_POST['user_id'] ?? 0);

$target = db_row('SELECT * FROM users WHERE id = ?', [$user_id]);
if (!$target) json_err('ไม่พบผู้ใช้');
if ($target['role'] === 'admin') json_err('ไม่สามารถจัดการบัญชีผู้ดูแลระบบด้วยกันได้', 403);

switch ($action) {
    case 'reset_password':
        $pw = trim($_POST['new_password'] ?? '');
        if (mb_strlen($pw) < 6) json_err('รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร');
        db_run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $user_id]);
        json_ok(['message' => 'รีเซ็ตรหัสผ่านของ ' . $target['name'] . ' เรียบร้อยแล้ว']);

    case 'set_status':
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['active', 'suspended'], true)) json_err('สถานะไม่ถูกต้อง');
        db_run('UPDATE users SET status = ? WHERE id = ?', [$status, $user_id]);
        json_ok(['message' => ($status === 'suspended' ? 'ระงับบัญชี ' : 'เปิดใช้งานบัญชี ') . $target['name'] . ' แล้ว']);

    default:
        json_err('ไม่รู้จักคำสั่งนี้');
}
