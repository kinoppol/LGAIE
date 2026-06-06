<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) json_err('Unauthorized', 401);
if (!is_teacher())   json_err('Forbidden', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$course_id = (int)($_POST['course_id'] ?? 0);
if (!$course_id) json_err('ไม่พบรายวิชา');

// ── ตรวจสอบสิทธิ์: ต้องเป็นเจ้าของ ───────────────────────────
$course = db_row('SELECT * FROM courses WHERE id = ?', [$course_id]);
if (!$course) json_err('ไม่พบรายวิชา', 404);
if ((int)$course['teacher_id'] !== current_user_id()) {
    json_err('คุณไม่มีสิทธิ์ลบรายวิชานี้ — เฉพาะเจ้าของรายวิชาเท่านั้น', 403);
}

// ── ตรวจสอบ: รายวิชาต้นแบบที่มีรายวิชาอื่นใช้งานอยู่ ─────────
if (!empty($course['is_template'])) {
    $derived = (int)db_val(
        'SELECT COUNT(*) FROM courses WHERE template_id = ?',
        [$course_id]
    );
    if ($derived > 0) {
        json_err(
            "ไม่สามารถลบได้ — รายวิชาต้นแบบนี้ถูกใช้สร้างรายวิชาอื่นอยู่ {$derived} รายวิชา " .
            "กรุณาลบรายวิชาที่ใช้ต้นแบบนี้ก่อน หรือยกเลิกสถานะต้นแบบ"
        );
    }
}

// ── ลบ (FK CASCADE จะลบ enrollments, lessons, assignments, submissions ให้) ──
db_run('DELETE FROM courses WHERE id = ?', [$course_id]);

json_ok(['message' => 'ลบรายวิชา "' . $course['name'] . '" เรียบร้อยแล้ว']);
