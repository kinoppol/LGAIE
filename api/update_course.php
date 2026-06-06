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

$course = db_row('SELECT * FROM courses WHERE id = ?', [$course_id]);
if (!$course)                                      json_err('ไม่พบรายวิชา', 404);
if ((int)$course['teacher_id'] !== current_user_id()) json_err('ไม่มีสิทธิ์แก้ไข', 403);

$update_type = $_POST['update_type'] ?? 'info'; // 'info' | 'color'

// ── อัปเดตสีและพื้นหลัง ───────────────────────────────────────
if ($update_type === 'color') {
    $banner  = trim($_POST['banner']        ?? '');
    $ink     = trim($_POST['ink_color']     ?? '');
    $primary = trim($_POST['primary_color'] ?? '');

    // Validate hex colors
    if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $ink))     json_err('สีตัวอักษรไม่ถูกต้อง');
    if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $primary)) json_err('สีหลักไม่ถูกต้อง');
    // Banner must be a gradient or solid color
    if (!str_contains($banner, '#'))                      json_err('ค่าพื้นหลังไม่ถูกต้อง');

    db_run(
        'UPDATE courses SET banner=?, ink_color=?, primary_color=? WHERE id=?',
        [$banner, $ink, $primary, $course_id]
    );
    json_ok(['message' => 'อัปเดตสีและพื้นหลังเรียบร้อยแล้ว']);
}

// ── อัปเดตข้อมูลพื้นฐาน ──────────────────────────────────────
$code        = trim($_POST['code']     ?? '');
$name        = trim($_POST['name']     ?? '');
$section     = trim($_POST['section']  ?? '');
$is_public   = isset($_POST['is_public'])   ? 1 : 0;
$is_template = isset($_POST['is_template']) ? 1 : 0;

if (!$code || !$name || !$section) json_err('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');

if (!$is_template && !empty($course['is_template'])) {
    $derived = (int)db_val('SELECT COUNT(*) FROM courses WHERE template_id = ?', [$course_id]);
    if ($derived > 0) {
        json_err("ไม่สามารถยกเลิกสถานะต้นแบบได้ เพราะมีรายวิชาที่ใช้ต้นแบบนี้อยู่ {$derived} รายวิชา");
    }
}

if ($is_template && !empty($course['template_id'])) {
    json_err('รายวิชาที่สร้างจากต้นแบบไม่สามารถกำหนดเป็นต้นแบบได้');
}

$template_secret = $course['template_secret'];
if ($is_template && !$template_secret) {
    $template_secret = strtoupper(bin2hex(random_bytes(6)));
}
if (!$is_template) {
    $template_secret = null;
}

$words      = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
$short_name = '';
foreach (array_slice($words, 0, 2) as $w) {
    $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($chars)) $short_name .= $chars[0];
}
if ($short_name === '') $short_name = mb_substr($name, 0, 2, 'UTF-8');

db_run(
    'UPDATE courses SET code=?, name=?, section=?, short_name=?,
     is_public=?, is_template=?, template_secret=? WHERE id=?',
    [$code, $name, $section, $short_name,
     $is_public, $is_template, $template_secret, $course_id]
);

json_ok(['message' => 'อัปเดตข้อมูลรายวิชาเรียบร้อยแล้ว']);
