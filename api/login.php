<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?page=login');
    exit;
}

$email    = strtolower(trim($_POST['email']    ?? ''));
$password = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? '';

if (!$email || !$password) {
    $_SESSION['error'] = 'กรุณากรอกอีเมลและรหัสผ่าน';
    redirect('../index.php?page=login');
}

$user = db_row('SELECT * FROM users WHERE email = ?', [$email]);

if (!$user || !password_verify($password, $user['password_hash'] ?? '')) {
    $_SESSION['error'] = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    redirect('../index.php?page=login');
}

if ($user['status'] !== 'active') {
    $_SESSION['error'] = 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
    redirect('../index.php?page=login');
}

// Start authenticated session
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['role']    = $user['role'];
$_SESSION['theme']   = $_SESSION['theme'] ?? 'system';
session_regenerate_id(true);

$dest = (trim($redirect) !== '' && str_starts_with($redirect, '../index.php'))
    ? $redirect
    : '../index.php?page=dashboard';

redirect($dest);
