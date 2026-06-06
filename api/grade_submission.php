<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('Forbidden', 403);

$submission_id = (int)($_POST['submission_id'] ?? 0);
$grade         = (int)($_POST['grade'] ?? 0);
$feedback      = trim($_POST['feedback'] ?? '');
$redirect      = $_POST['redirect'] ?? '?page=dashboard';

if (!$submission_id) {
    $_SESSION['error'] = 'ข้อมูลไม่ถูกต้อง';
    header("Location: $redirect");
    exit;
}

try {
    db_run(
        'UPDATE submissions SET grade = ?, feedback = ?, status = "graded" WHERE id = ?',
        [$grade, $feedback ?: null, $submission_id]
    );
    $_SESSION['success'] = 'บันทึกคะแนนแล้ว';
} catch (Exception $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

header("Location: $redirect");
exit;
