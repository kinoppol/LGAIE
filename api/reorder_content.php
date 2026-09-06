<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('ไม่มีสิทธิ์', 403);

$course_id = (int)($_POST['course_id'] ?? 0);
$order     = (array)($_POST['order'] ?? []); // เช่น ["lesson:12","work:5","lesson:13"] — ลำดับตามที่ลากวาง

if (!$course_id || !$order) json_err('ข้อมูลไม่ครบถ้วน');
if (!teaches_course($course_id)) json_err('ไม่มีสิทธิ์จัดการรายวิชานี้', 403);

try { get_db()->exec("ALTER TABLE assignments ADD COLUMN IF NOT EXISTS sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER allow_improve"); } catch (PDOException) {}

$parsed = [];
foreach ($order as $token) {
    if (!preg_match('/^(lesson|work):(\d+)$/', (string)$token, $m)) json_err('รูปแบบข้อมูลไม่ถูกต้อง');
    $parsed[] = ['type' => $m[1], 'id' => (int)$m[2]];
}

$lesson_ids = array_values(array_unique(array_column(array_filter($parsed, fn($p) => $p['type'] === 'lesson'), 'id')));
$work_ids   = array_values(array_unique(array_column(array_filter($parsed, fn($p) => $p['type'] === 'work'),   'id')));

// ตรวจว่าทุกรายการเป็นของรายวิชานี้จริง (กันข้อมูลปลอมแปลง/ข้ามรายวิชา)
if ($lesson_ids) {
    $ph  = implode(',', array_fill(0, count($lesson_ids), '?'));
    $cnt = (int)db_val("SELECT COUNT(*) FROM lessons WHERE course_id = ? AND id IN ({$ph})", [$course_id, ...$lesson_ids]);
    if ($cnt !== count($lesson_ids)) json_err('พบบทเรียนที่ไม่ตรงกับรายวิชานี้ — กรุณารีเฟรชหน้าแล้วลองใหม่');
}
if ($work_ids) {
    $ph  = implode(',', array_fill(0, count($work_ids), '?'));
    $cnt = (int)db_val("SELECT COUNT(*) FROM assignments WHERE course_id = ? AND id IN ({$ph})", [$course_id, ...$work_ids]);
    if ($cnt !== count($work_ids)) json_err('พบงานที่ไม่ตรงกับรายวิชานี้ — กรุณารีเฟรชหน้าแล้วลองใหม่');
}

// อ่านค่า sort_order เดิมของรายการทั้งหมดที่ส่งมา แล้ว "สลับตำแหน่งกันเอง" ตามลำดับใหม่
// (ไม่ reset เป็น 0..n-1 ใหม่ทั้งหมด) เพื่อไม่ให้กระทบลำดับของ "หน่วย" อื่นที่ไม่ได้ลาก —
// ลำดับหน่วยคำนวณจาก MIN(sort_order) ของเนื้อหาในหน่วยนั้น ถ้า renumber จากศูนย์ใหม่
// ทุกครั้ง หน่วยที่เพิ่งถูกลากจะกลายเป็นหน่วยแรกเสมอโดยไม่ได้ตั้งใจ
$current_orders = [];
foreach ($parsed as $p) {
    $current_orders[] = $p['type'] === 'lesson'
        ? (int)db_val('SELECT sort_order FROM lessons WHERE id = ?', [$p['id']])
        : (int)db_val('SELECT sort_order FROM assignments WHERE id = ?', [$p['id']]);
}
sort($current_orders, SORT_NUMERIC);

$db = get_db();
$db->beginTransaction();
try {
    foreach ($parsed as $i => $p) {
        $new_order = $current_orders[$i];
        if ($p['type'] === 'lesson') {
            db_run('UPDATE lessons SET sort_order = ? WHERE id = ? AND course_id = ?', [$new_order, $p['id'], $course_id]);
        } else {
            db_run('UPDATE assignments SET sort_order = ? WHERE id = ? AND course_id = ?', [$new_order, $p['id'], $course_id]);
        }
    }
    $db->commit();
    json_ok(['message' => 'จัดลำดับเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
