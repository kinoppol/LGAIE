<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../index.php?page=register');
    exit;
}

$role     = $_POST['role']             ?? '';
$name     = trim($_POST['name']        ?? '');
$email    = strtolower(trim($_POST['email']    ?? ''));
$password = $_POST['password']         ?? '';
$confirm  = $_POST['password_confirm'] ?? '';
$phone    = trim($_POST['phone']       ?? '');
$school   = trim($_POST['school']      ?? '');
$province = trim($_POST['province']    ?? '');
$invite   = strtoupper(trim($_POST['invite_code'] ?? ''));

// ── Validate ──────────────────────────────────────────────────
$errors = [];
if (!in_array($role, ['teacher', 'student'], true)) {
    $errors[] = 'ประเภทผู้ใช้ไม่ถูกต้อง';
}
if ($name === '') {
    $errors[] = 'กรุณากรอกชื่อ-สกุล';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
}
if (strlen($password) < 6) {
    $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
}
if ($password !== $confirm) {
    $errors[] = 'รหัสผ่านยืนยันไม่ตรงกัน';
}
if ($phone === '') {
    $errors[] = 'กรุณากรอกหมายเลขโทรศัพท์';
}
if ($school === '') {
    $errors[] = 'กรุณากรอกชื่อสถานศึกษา';
}
if ($province === '' || !in_array($province, get_provinces(), true)) {
    $errors[] = 'กรุณาเลือกจังหวัดที่ถูกต้อง';
}

if ($errors) {
    $_SESSION['error'] = implode(' · ', $errors);
    redirect("../index.php?page=register&role={$role}");
}

// ── Check email uniqueness ────────────────────────────────────
if (db_val('SELECT 1 FROM users WHERE email = ?', [$email])) {
    $_SESSION['error'] = 'อีเมลนี้มีในระบบแล้ว กรุณาใช้อีเมลอื่นหรือเข้าสู่ระบบ';
    redirect("../index.php?page=register&role={$role}");
}

// ── Validate invite code (if provided) ───────────────────────
$invite_row = null;
if ($invite !== '') {
    $invite_row = db_row(
        "SELECT * FROM course_invites
         WHERE invite_code = ? AND is_active = 1
           AND (expires_at IS NULL OR expires_at > NOW())
           AND (max_uses IS NULL OR use_count < max_uses)",
        [$invite]
    );
    if (!$invite_row) {
        $_SESSION['error'] = 'รหัสเชิญไม่ถูกต้องหรือหมดอายุแล้ว';
        redirect("../index.php?page=register&role={$role}&invite={$invite}");
    }
}

// ── Build initials (first char of each Thai/Eng word, max 2) ─
$words    = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
$initials = '';
foreach (array_slice($words, 0, 2) as $w) {
    $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($chars)) $initials .= $chars[0];
}
if ($initials === '') $initials = mb_substr($name, 0, 1, 'UTF-8');

$av_pool      = ['av-1', 'av-2', 'av-3', 'av-4', 'av-5', 'av-6'];
$avatar_class = $av_pool[array_rand($av_pool)];
$pass_hash    = password_hash($password, PASSWORD_DEFAULT);

// ── Insert user ───────────────────────────────────────────────
try {
    $uid = db_run(
        'INSERT INTO users (name, role, avatar_class, initials, email, password_hash, phone, school, province, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$name, $role, $avatar_class, $initials, $email, $pass_hash, $phone, $school, $province, 'active']
    );

    // ── Enroll in course if invite code was valid ─────────────
    if ($invite_row) {
        db_run(
            'INSERT IGNORE INTO course_enrollments (course_id, user_id, join_type) VALUES (?, ?, ?)',
            [$invite_row['course_id'], $uid, 'invite_code']
        );
        db_run(
            'UPDATE course_invites SET use_count = use_count + 1 WHERE id = ?',
            [$invite_row['id']]
        );
    }

    // ── Enroll via invite link token (if ?token= in URL) ─────
    $token = trim($_POST['invite_token'] ?? '');
    if ($token !== '') {
        $trow = db_row(
            "SELECT * FROM course_invites
             WHERE invite_token = ? AND is_active = 1
               AND (expires_at IS NULL OR expires_at > NOW())",
            [$token]
        );
        if ($trow) {
            db_run(
                'INSERT IGNORE INTO course_enrollments (course_id, user_id, join_type) VALUES (?, ?, ?)',
                [$trow['course_id'], $uid, 'invite_link']
            );
            db_run(
                'UPDATE course_invites SET use_count = use_count + 1 WHERE id = ?',
                [$trow['id']]
            );
        }
    }

    $_SESSION['user_id'] = $uid;
    $_SESSION['role']    = $role;
    $_SESSION['theme']   = 'system';
    session_regenerate_id(true);

    $_SESSION['success'] = 'ลงทะเบียนสำเร็จ ยินดีต้อนรับสู่ ClassroomAI!';
    redirect('../index.php?page=dashboard');

} catch (PDOException $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
    redirect("../index.php?page=register&role={$role}");
}
