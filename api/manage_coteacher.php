<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('ไม่มีสิทธิ์', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$course_id = (int)($_POST['course_id'] ?? 0);
$action    = trim($_POST['action'] ?? '');
if (!$course_id) json_err('ไม่พบรายวิชา');

// Only the course owner may manage the teaching team
if (!owns_course($course_id)) json_err('เฉพาะเจ้าของรายวิชาเท่านั้นที่จัดการทีมผู้สอนได้', 403);

ensure_coteacher_schema();

if ($action === 'add') {
    $email   = trim($_POST['email'] ?? '');
    $co_role = ($_POST['co_role'] ?? 'co') === 'supervisor' ? 'supervisor' : 'co';
    if ($email === '') json_err('กรุณากรอกอีเมล');

    $user = db_row('SELECT id, name, role FROM users WHERE email = ?', [$email]);
    if (!$user)                       json_err('ไม่พบบัญชีผู้ใช้ที่ใช้อีเมลนี้ในระบบ');
    if (($user['role'] ?? '') !== 'teacher') json_err('บัญชีนี้ไม่ใช่บัญชีครู ไม่สามารถเพิ่มเป็นผู้ร่วมสอนได้');

    $owner_id = (int)db_val('SELECT teacher_id FROM courses WHERE id = ?', [$course_id]);
    if ((int)$user['id'] === $owner_id) json_err('ครูท่านนี้เป็นเจ้าของรายวิชาอยู่แล้ว');

    $exists = db_val('SELECT 1 FROM course_teachers WHERE course_id = ? AND user_id = ?', [$course_id, $user['id']]);
    if ($exists) json_err('ครูท่านนี้อยู่ในทีมผู้สอนอยู่แล้ว');

    db_run(
        'INSERT INTO course_teachers (course_id, user_id, co_role, added_by) VALUES (?,?,?,?)',
        [$course_id, $user['id'], $co_role, current_user_id()]
    );
    $label = $co_role === 'supervisor' ? 'ครูนิเทศ' : 'ครูร่วมสอน';
    json_ok(['message' => "เพิ่ม {$user['name']} เป็น{$label}เรียบร้อยแล้ว"]);
}

if ($action === 'remove') {
    $coteacher_id = (int)($_POST['coteacher_id'] ?? 0);
    if (!$coteacher_id) json_err('ไม่พบรายการที่ต้องการนำออก');
    db_run('DELETE FROM course_teachers WHERE id = ? AND course_id = ?', [$coteacher_id, $course_id]);
    json_ok(['message' => 'นำครูออกจากทีมผู้สอนแล้ว']);
}

json_err('คำสั่งไม่ถูกต้อง');
