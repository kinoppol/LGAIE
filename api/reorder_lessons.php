<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('ไม่มีสิทธิ์', 403);

$course_id = (int)($_POST['course_id'] ?? 0);
$order     = array_values(array_filter(array_map('intval', (array)($_POST['order'] ?? []))));

if (!$course_id || !$order) json_err('ข้อมูลไม่ครบถ้วน');
if (!teaches_course($course_id)) json_err('ไม่มีสิทธิ์จัดการรายวิชานี้', 403);

// ตรวจว่า lesson ทุกตัวใน order เป็นของรายวิชานี้จริง และมีครบทุกบทเรียนของวิชานี้
$actual_ids = array_map('intval', array_column(db_rows('SELECT id FROM lessons WHERE course_id = ?', [$course_id]), 'id'));
sort($actual_ids);
$sent_ids = $order;
sort($sent_ids);
if ($actual_ids !== $sent_ids) json_err('รายการบทเรียนไม่ตรงกับข้อมูลปัจจุบัน — กรุณารีเฟรชหน้าแล้วลองใหม่');

$db = get_db();
$db->beginTransaction();
try {
    foreach ($order as $i => $lesson_id) {
        db_run('UPDATE lessons SET sort_order = ? WHERE id = ? AND course_id = ?', [$i, $lesson_id, $course_id]);
    }
    $db->commit();
    json_ok(['message' => 'จัดลำดับเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
