<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_teacher()) json_err('ครูไม่สามารถส่งงานได้', 403);

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$answer        = trim($_POST['answer_text'] ?? '');
$prompt_used   = trim($_POST['prompt_used'] ?? '');
$ai_used       = trim($_POST['ai_used'] ?? 'claude');
$result        = trim($_POST['result_text'] ?? '');
$better        = isset($_POST['better_than_teacher']) ? 1 : 0;
$compare_note  = trim($_POST['compare_note'] ?? '');
$redirect      = $_POST['redirect'] ?? '?page=dashboard';
$student_id    = current_user_id();

if (!$assignment_id || !$prompt_used) {
    $_SESSION['error'] = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    header("Location: $redirect");
    exit;
}

try {
    db_run(
        'INSERT INTO submissions (assignment_id, student_id, answer_text, prompt_used, ai_used, better_than_teacher, compare_note, result_text)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           answer_text=VALUES(answer_text), prompt_used=VALUES(prompt_used), ai_used=VALUES(ai_used),
           better_than_teacher=VALUES(better_than_teacher), compare_note=VALUES(compare_note), result_text=VALUES(result_text)',
        [$assignment_id, $student_id, $answer ?: null, $prompt_used, $ai_used, $better, $compare_note ?: null, $result ?: null]
    );
    $_SESSION['success'] = 'ส่งงานเรียบร้อยแล้ว!';
} catch (Exception $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

header("Location: $redirect");
exit;
