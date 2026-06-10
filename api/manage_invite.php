<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) json_err('Unauthorized', 401);
if (!is_teacher())   json_err('Forbidden', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$course_id = (int)($_POST['course_id'] ?? 0);
$action    = trim($_POST['action'] ?? '');

if (!$course_id) json_err('ไม่พบรายวิชา');

// Ownership check
$course = db_row('SELECT id, name FROM courses WHERE id = ? AND teacher_id = ?', [$course_id, current_user_id()]);
if (!$course) json_err('ไม่มีสิทธิ์จัดการรายวิชานี้', 403);

// Auto-migrate: ensure course_invites table exists
try {
    get_db()->exec('CREATE TABLE IF NOT EXISTS course_invites (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_id     INT UNSIGNED NOT NULL,
        invite_type   ENUM("link","code","email") NOT NULL DEFAULT "code",
        invite_token  VARCHAR(40)  NULL,
        invite_code   VARCHAR(10)  NULL,
        invited_email VARCHAR(150) NULL,
        created_by    INT UNSIGNED NOT NULL,
        expires_at    DATETIME     NULL,
        max_uses      INT UNSIGNED NULL,
        use_count     INT UNSIGNED DEFAULT 0,
        is_active     TINYINT(1)   DEFAULT 1,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
} catch (PDOException) {}

// ── สร้างหรือ reset รหัสเชิญ ─────────────────────────────────────────
if ($action === 'reset_code') {
    // สร้างรหัส 8 ตัว ไม่ซ้ำ
    do {
        $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
        $dup  = db_val('SELECT 1 FROM course_invites WHERE invite_code = ?', [$code]);
    } while ($dup);

    // ลบรหัส code เดิมของรายวิชานี้ก่อน แล้วสร้างใหม่
    db_run('DELETE FROM course_invites WHERE course_id = ? AND invite_type = "code"', [$course_id]);
    db_run(
        'INSERT INTO course_invites (course_id, invite_type, invite_code, created_by, is_active) VALUES (?, "code", ?, ?, 1)',
        [$course_id, $code, current_user_id()]
    );
    json_ok(['message' => 'สร้างรหัสเชิญใหม่แล้ว', 'invite_code' => $code]);
}

// ── เปิด/ปิดการลงทะเบียนด้วยรหัส ────────────────────────────────────
if ($action === 'toggle_code') {
    $invite = db_row('SELECT id, is_active FROM course_invites WHERE course_id = ? AND invite_type = "code"', [$course_id]);
    if (!$invite) json_err('ยังไม่มีรหัสเชิญ กรุณาสร้างก่อน');
    $new = $invite['is_active'] ? 0 : 1;
    db_run('UPDATE course_invites SET is_active = ? WHERE id = ?', [$new, $invite['id']]);
    $msg = $new ? 'เปิดการลงทะเบียนด้วยรหัสแล้ว' : 'ปิดการลงทะเบียนด้วยรหัสแล้ว';
    json_ok(['message' => $msg, 'is_active' => $new]);
}

// ── เชิญโดยระบุอีเมล ─────────────────────────────────────────────────
if ($action === 'invite_email') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_err('อีเมลไม่ถูกต้อง');
    }

    // ค้นหา user จาก email
    $student = db_row('SELECT id, name, role FROM users WHERE email = ?', [$email]);
    if (!$student) {
        json_err('ไม่พบผู้ใช้ที่มีอีเมล ' . $email . ' ในระบบ');
    }
    if ($student['role'] !== 'student') {
        json_err('ผู้ใช้นี้ไม่ใช่นักเรียน');
    }

    $sid = (int)$student['id'];
    $already = db_val('SELECT 1 FROM course_enrollments WHERE course_id = ? AND user_id = ?', [$course_id, $sid]);
    if ($already) {
        json_err($student['name'] . ' ลงทะเบียนรายวิชานี้ไว้แล้ว');
    }

    db_run(
        'INSERT INTO course_enrollments (course_id, user_id, join_type) VALUES (?, ?, "invite_email")',
        [$course_id, $sid]
    );
    json_ok(['message' => 'เพิ่ม ' . $student['name'] . ' เข้ารายวิชาเรียบร้อยแล้ว']);
}

// ── ลบนักเรียนออกจากรายวิชา ───────────────────────────────────────────
if ($action === 'remove_student') {
    $sid = (int)($_POST['student_id'] ?? 0);
    if (!$sid) json_err('ไม่พบนักเรียน');
    db_run('DELETE FROM course_enrollments WHERE course_id = ? AND user_id = ?', [$course_id, $sid]);
    json_ok(['message' => 'นำนักเรียนออกจากรายวิชาแล้ว']);
}

json_err('action ไม่ถูกต้อง');
