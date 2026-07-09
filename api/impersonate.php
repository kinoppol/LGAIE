<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── STOP: return to the admin who started impersonating ──────────────
if ($action === 'stop') {
    if (!empty($_SESSION['impersonator_id'])) {
        $_SESSION['user_id'] = (int) $_SESSION['impersonator_id'];
        $_SESSION['role']    = (string) ($_SESSION['impersonator_role'] ?? 'admin');
        unset($_SESSION['impersonator_id'], $_SESSION['impersonator_role'], $_SESSION['impersonator_name']);
        $_SESSION['success'] = 'กลับสู่บัญชีผู้ดูแลระบบแล้ว';
    }
    redirect('../index.php?page=admin');
}

// ── START: admin steps into another user's account ──────────────────
// Only a real admin (not already impersonating) may start.
if (!is_admin() || !empty($_SESSION['impersonator_id'])) {
    http_response_code(403);
    exit('403 Forbidden');
}

$target_id = (int) ($_POST['user_id'] ?? 0);
if (!$target_id) {
    $_SESSION['error'] = 'ไม่พบผู้ใช้ที่ต้องการสวมสิทธิ์';
    redirect('../index.php?page=admin');
}

$target = db_row('SELECT id, name, role, status FROM users WHERE id = ?', [$target_id]);
if (!$target) {
    $_SESSION['error'] = 'ไม่พบบัญชีผู้ใช้นี้';
    redirect('../index.php?page=admin');
}
if (($target['role'] ?? '') === 'admin') {
    $_SESSION['error'] = 'ไม่สามารถสวมสิทธิ์บัญชีผู้ดูแลระบบด้วยกันได้';
    redirect('../index.php?page=admin');
}

// Remember who we really are, then become the target user
$_SESSION['impersonator_id']   = current_user_id();
$_SESSION['impersonator_role'] = current_role();
$_SESSION['impersonator_name'] = (string) (current_user()['name'] ?? 'ผู้ดูแลระบบ');
$_SESSION['user_id']           = (int) $target['id'];
$_SESSION['role']              = (string) $target['role'];
$_SESSION['success']           = 'กำลังใช้งานในนามของ ' . $target['name'];

redirect('../index.php?page=dashboard');
