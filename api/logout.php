<?php
session_start();

// If an admin is impersonating another user, "logout" returns them to admin
// instead of ending the session entirely.
if (!empty($_SESSION['impersonator_id'])) {
    $_SESSION['user_id'] = (int) $_SESSION['impersonator_id'];
    $_SESSION['role']    = (string) ($_SESSION['impersonator_role'] ?? 'admin');
    unset($_SESSION['impersonator_id'], $_SESSION['impersonator_role'], $_SESSION['impersonator_name']);
    $_SESSION['success'] = 'กลับสู่บัญชีผู้ดูแลระบบแล้ว';
    header('Location: ../index.php?page=admin');
    exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ../index.php?page=home');
exit;
