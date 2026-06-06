<?php
declare(strict_types=1);
session_start();

$role = $_POST['role'] ?? 'teacher';
if (!in_array($role, ['teacher', 'student'], true)) {
    $role = 'teacher';
}

$_SESSION['role']    = $role;
$_SESSION['user_id'] = $role === 'teacher' ? 1 : 2;

$redirect = $_POST['redirect'] ?? '/LGAIE/';
header("Location: $redirect");
exit;
