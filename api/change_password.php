<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) redirect('../index.php?page=login');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../index.php?page=profile');

$uid      = current_user_id();
$current  = $_POST['current_password']  ?? '';
$new_pass = $_POST['new_password']      ?? '';
$confirm  = $_POST['confirm_password']  ?? '';

// Validate
$errors = [];
if ($current === '')            $errors[] = 'กรุณากรอกรหัสผ่านปัจจุบัน';
if (strlen($new_pass) < 6)     $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
if ($new_pass !== $confirm)     $errors[] = 'รหัสผ่านใหม่ยืนยันไม่ตรงกัน';

if ($errors) {
    $_SESSION['error'] = implode(' · ', $errors);
    redirect('../index.php?page=profile');
}

// Verify current password
$user = db_row('SELECT password_hash FROM users WHERE id = ?', [$uid]);
if (!$user || !password_verify($current, $user['password_hash'] ?? '')) {
    $_SESSION['error'] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    redirect('../index.php?page=profile');
}

// Prevent reuse of same password
if (password_verify($new_pass, $user['password_hash'])) {
    $_SESSION['error'] = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม';
    redirect('../index.php?page=profile');
}

// Update
$new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
db_run('UPDATE users SET password_hash = ? WHERE id = ?', [$new_hash, $uid]);

$_SESSION['success'] = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
redirect('../index.php?page=profile');
