<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) json_err('กรุณาเข้าสู่ระบบก่อน', 401);
if (is_teacher())   json_err('ครูไม่สามารถลงทะเบียนเรียนได้', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$uid  = current_user_id();
$code = strtoupper(trim($_POST['invite_code'] ?? ''));

// Auto-migrate tables if needed
try {
    get_db()->exec('CREATE TABLE IF NOT EXISTS course_invites (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, course_id INT UNSIGNED NOT NULL, invite_type ENUM("link","code","email") NOT NULL DEFAULT "code", invite_token VARCHAR(40) NULL, invite_code VARCHAR(10) NULL, invited_email VARCHAR(150) NULL, created_by INT UNSIGNED NOT NULL, expires_at DATETIME NULL, max_uses INT UNSIGNED NULL, use_count INT UNSIGNED DEFAULT 0, is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE, FOREIGN KEY (created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    get_db()->exec('CREATE TABLE IF NOT EXISTS course_enrollments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, course_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, join_type ENUM("invite_code","invite_email","manual","public") NOT NULL DEFAULT "invite_code", enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_enroll (course_id, user_id), FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (PDOException) {}

if (!$code) json_err('กรุณากรอกรหัสเชิญ');

// ค้นหา invite code ที่ active
$invite = db_row('
    SELECT ci.*, c.id AS cid, c.name AS cname, c.is_archived
    FROM course_invites ci
    JOIN courses c ON c.id = ci.course_id
    WHERE ci.invite_code = ? AND ci.invite_type = "code" AND ci.is_active = 1
', [$code]);

if (!$invite) json_err('รหัสเชิญไม่ถูกต้องหรือถูกปิดใช้งานแล้ว');
if ($invite['is_archived']) json_err('รายวิชานี้ถูกจัดเก็บแล้ว ไม่สามารถลงทะเบียนได้');

// ตรวจสอบ expiry
if ($invite['expires_at'] && strtotime($invite['expires_at']) < time()) {
    json_err('รหัสเชิญหมดอายุแล้ว');
}

// ตรวจสอบ max_uses
if ($invite['max_uses'] && (int)$invite['use_count'] >= (int)$invite['max_uses']) {
    json_err('รหัสเชิญนี้ถูกใช้ครบจำนวนสูงสุดแล้ว');
}

$course_id = (int)$invite['cid'];

// ตรวจสอบว่าลงทะเบียนแล้วหรือยัง
$already = db_val('SELECT 1 FROM course_enrollments WHERE course_id = ? AND user_id = ?', [$course_id, $uid]);
if ($already) json_err('คุณลงทะเบียนรายวิชา "' . $invite['cname'] . '" ไว้แล้ว');

$db = get_db();
$db->beginTransaction();
try {
    db_run(
        'INSERT INTO course_enrollments (course_id, user_id, join_type) VALUES (?, ?, "invite_code")',
        [$course_id, $uid]
    );
    db_run('UPDATE course_invites SET use_count = use_count + 1 WHERE id = ?', [$invite['id']]);
    $db->commit();
    json_ok(['message' => 'ลงทะเบียนรายวิชา "' . $invite['cname'] . '" เรียบร้อยแล้ว', 'course_id' => $course_id]);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
