<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) json_err('กรุณาเข้าสู่ระบบก่อน', 401);
if (is_teacher())    json_err('ครูไม่สามารถลงทะเบียนเรียนได้', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$uid       = current_user_id();
$course_id = (int)($_POST['course_id'] ?? 0);
if (!$course_id) json_err('ไม่พบรายวิชา');

// Ensure enrollment table exists (mirrors join_course.php)
try {
    get_db()->exec('CREATE TABLE IF NOT EXISTS course_enrollments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, course_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, join_type ENUM("invite_code","invite_email","manual","public") NOT NULL DEFAULT "invite_code", enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_enroll (course_id, user_id), FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (PDOException) {}

// Only public, non-archived courses may be self-enrolled
$course = db_row('SELECT id, name, is_public, is_archived FROM courses WHERE id = ?', [$course_id]);
if (!$course)                        json_err('ไม่พบรายวิชานี้');
if (!$course['is_public'])           json_err('รายวิชานี้ไม่ได้เปิดให้ลงทะเบียนแบบสาธารณะ', 403);
if ($course['is_archived'])          json_err('รายวิชานี้ถูกจัดเก็บแล้ว ไม่สามารถลงทะเบียนได้');

$already = db_val('SELECT 1 FROM course_enrollments WHERE course_id = ? AND user_id = ?', [$course_id, $uid]);
if ($already) json_err('คุณลงทะเบียนรายวิชา "' . $course['name'] . '" ไว้แล้ว');

try {
    db_run('INSERT INTO course_enrollments (course_id, user_id, join_type) VALUES (?, ?, "public")', [$course_id, $uid]);
} catch (PDOException $e) {
    json_err('ลงทะเบียนไม่สำเร็จ: ' . $e->getMessage(), 500);
}

json_ok(['message' => 'ลงทะเบียนรายวิชา "' . $course['name'] . '" เรียบร้อยแล้ว', 'course_id' => $course_id]);
