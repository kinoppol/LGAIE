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

// Access check — owner or co-teacher may manage students/invites
if (!teaches_course($course_id)) json_err('ไม่มีสิทธิ์จัดการรายวิชานี้', 403);
$course = db_row('SELECT id, name FROM courses WHERE id = ?', [$course_id]);

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

// ── เชิญโดยระบุอีเมล (รองรับหลายอีเมลคั่นด้วย comma หรือขึ้นบรรทัดใหม่) ──
if ($action === 'invite_email') {
    // Auto-migrate status column
    try {
        get_db()->exec("ALTER TABLE course_enrollments
            ADD COLUMN IF NOT EXISTS status ENUM('pending','active') NOT NULL DEFAULT 'active'");
    } catch (PDOException) {}

    $raw   = trim($_POST['emails'] ?? $_POST['email'] ?? '');
    if (!$raw) json_err('กรุณากรอกอีเมล');

    // แยกอีเมลด้วย comma, semicolon หรือ newline
    $parts  = preg_split('/[\s,;]+/', $raw);
    $emails = array_values(array_filter(array_map('trim', $parts)));
    if (empty($emails)) json_err('ไม่พบอีเมลที่ถูกต้อง');

    $results = [];
    foreach ($emails as $email) {
        $email = strtolower($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $results[] = ['email' => $email, 'ok' => false, 'msg' => 'อีเมลไม่ถูกต้อง'];
            continue;
        }
        $student = db_row('SELECT id, name, role FROM users WHERE email = ?', [$email]);
        if (!$student) {
            $results[] = ['email' => $email, 'ok' => false, 'msg' => 'ไม่พบในระบบ'];
            continue;
        }
        if ($student['role'] !== 'student') {
            $results[] = ['email' => $email, 'ok' => false, 'msg' => $student['name'] . ' ไม่ใช่นักเรียน'];
            continue;
        }
        $sid = (int)$student['id'];
        $existing = db_row('SELECT status FROM course_enrollments WHERE course_id = ? AND user_id = ?',
            [$course_id, $sid]);
        if ($existing) {
            $state = $existing['status'] === 'pending' ? 'รอตอบรับอยู่แล้ว' : 'ลงทะเบียนไว้แล้ว';
            $results[] = ['email' => $email, 'ok' => false, 'msg' => $student['name'] . ' ' . $state];
            continue;
        }
        db_run(
            "INSERT INTO course_enrollments (course_id, user_id, join_type, status) VALUES (?, ?, 'invite_email', 'pending')",
            [$course_id, $sid]
        );
        $results[] = ['email' => $email, 'ok' => true, 'msg' => 'ส่งคำเชิญให้ ' . $student['name'] . ' แล้ว'];
    }

    $ok_count   = count(array_filter($results, fn($r) => $r['ok']));
    $fail_count = count($results) - $ok_count;
    $summary    = "ส่งคำเชิญสำเร็จ {$ok_count} คน";
    if ($fail_count) $summary .= " · ไม่สำเร็จ {$fail_count} คน";
    json_ok(['message' => $summary, 'results' => $results]);
}

// ── ลบนักเรียนออกจากรายวิชา ───────────────────────────────────────────
if ($action === 'remove_student') {
    $sid = (int)($_POST['student_id'] ?? 0);
    if (!$sid) json_err('ไม่พบนักเรียน');
    db_run('DELETE FROM course_enrollments WHERE course_id = ? AND user_id = ?', [$course_id, $sid]);
    json_ok(['message' => 'นำนักเรียนออกจากรายวิชาแล้ว']);
}

json_err('action ไม่ถูกต้อง');
