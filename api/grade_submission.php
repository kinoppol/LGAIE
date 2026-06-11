<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('Forbidden', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$submission_id = (int)($_POST['submission_id'] ?? 0);
$grade         = isset($_POST['grade']) && $_POST['grade'] !== '' ? (int)$_POST['grade'] : null;
$feedback      = trim($_POST['feedback'] ?? '');

if (!$submission_id) json_err('ไม่พบข้อมูลงาน');

// Verify the teacher owns the assignment for this submission
$check = db_row('
    SELECT s.id FROM submissions s
    JOIN assignments a ON a.id = s.assignment_id
    JOIN courses c ON c.id = a.course_id
    WHERE s.id = ? AND c.teacher_id = ?
', [$submission_id, current_user_id()]);

if (!$check) json_err('ไม่มีสิทธิ์ให้คะแนนงานนี้', 403);

try {
    db_run(
        'UPDATE submissions SET grade = ?, feedback = ?, status = "graded" WHERE id = ?',
        [$grade, $feedback ?: null, $submission_id]
    );
    json_ok(['message' => 'บันทึกคะแนนเรียบร้อยแล้ว']);
} catch (Exception $e) {
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
