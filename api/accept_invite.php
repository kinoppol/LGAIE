<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) json_err('กรุณาเข้าสู่ระบบก่อน', 401);
if (is_teacher())   json_err('Forbidden', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$uid       = current_user_id();
$course_id = (int)($_POST['course_id'] ?? 0);
$action    = trim($_POST['action'] ?? ''); // 'accept' | 'decline'

if (!$course_id) json_err('ไม่พบรายวิชา');
if (!in_array($action, ['accept', 'decline'], true)) json_err('action ไม่ถูกต้อง');

// Auto-migrate status column
try {
    get_db()->exec("ALTER TABLE course_enrollments
        ADD COLUMN IF NOT EXISTS status ENUM('pending','active') NOT NULL DEFAULT 'active'");
} catch (PDOException) {}

$enrollment = db_row(
    "SELECT * FROM course_enrollments WHERE course_id = ? AND user_id = ? AND status = 'pending'",
    [$course_id, $uid]
);

if (!$enrollment) json_err('ไม่พบคำเชิญที่รอดำเนินการ');

if ($action === 'accept') {
    db_run("UPDATE course_enrollments SET status = 'active' WHERE course_id = ? AND user_id = ?",
        [$course_id, $uid]);
    $cname = db_val('SELECT name FROM courses WHERE id = ?', [$course_id]);
    json_ok(['message' => 'ตอบรับเข้าเรียนรายวิชา "' . $cname . '" แล้ว']);
} else {
    db_run('DELETE FROM course_enrollments WHERE course_id = ? AND user_id = ?',
        [$course_id, $uid]);
    json_ok(['message' => 'ปฏิเสธคำเชิญแล้ว']);
}
