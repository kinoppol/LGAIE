<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) json_err('Unauthorized', 401);
if (!is_teacher())   json_err('Forbidden', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$course_id = (int)($_POST['course_id'] ?? 0);
$action    = $_POST['action'] ?? 'archive'; // 'archive' | 'restore'
if (!$course_id) json_err('ไม่พบรายวิชา');

$course = db_row('SELECT * FROM courses WHERE id = ?', [$course_id]);
if (!$course) json_err('ไม่พบรายวิชา', 404);
if ((int)$course['teacher_id'] !== current_user_id()) {
    json_err('ไม่มีสิทธิ์ดำเนินการ — เฉพาะเจ้าของรายวิชาเท่านั้น', 403);
}

if ($action === 'restore') {
    db_run(
        'UPDATE courses SET is_archived = 0, archived_at = NULL WHERE id = ?',
        [$course_id]
    );
    json_ok(['message' => 'นำรายวิชา "' . $course['name'] . '" กลับมาใช้งานแล้ว']);
} else {
    db_run(
        'UPDATE courses SET is_archived = 1, archived_at = NOW() WHERE id = ?',
        [$course_id]
    );
    json_ok(['message' => 'จัดเก็บรายวิชา "' . $course['name'] . '" เรียบร้อยแล้ว']);
}
