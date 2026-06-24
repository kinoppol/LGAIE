<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
if (!is_teacher()) json_err('ไม่มีสิทธิ์', 403);

$course_id = (int)($_POST['course_id'] ?? 0);
if (!$course_id) json_err('ไม่ระบุรายวิชา');

$owns = db_val('SELECT 1 FROM courses WHERE id = ? AND teacher_id = ?', [$course_id, current_user_id()]);
if (!$owns) json_err('ไม่มีสิทธิ์แก้ไขรายวิชานี้', 403);

$enabled   = isset($_POST['enabled']) ? 1 : 0;
$bg_style  = array_key_exists($_POST['background_style'] ?? '', cert_bg_styles()) ? $_POST['background_style'] : 'plain';

// Grade levels
$grades_raw = json_decode(trim($_POST['grade_json'] ?? '[]'), true);
if (!is_array($grades_raw)) $grades_raw = [];
$clean = [];
foreach ($grades_raw as $g) {
    $label = trim((string)($g['label'] ?? ''));
    $min   = max(0, min(100, (int)($g['min'] ?? 0)));
    if ($label !== '') $clean[] = ['label' => $label, 'min' => $min];
}
usort($clean, fn($a, $b) => $b['min'] <=> $a['min']);

ensure_certificate_schema();

// Fetch existing row to get current background_image path
$existing      = db_row('SELECT * FROM course_certificates WHERE course_id = ?', [$course_id]);
$cur_bg_image  = (string)($existing['background_image'] ?? '');
$new_bg_image  = $cur_bg_image;

// Handle custom background image upload
$has_upload = isset($_FILES['background_image'])
    && ($_FILES['background_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    && !empty($_FILES['background_image']['name']);

$remove_bg = ($_POST['remove_background_image'] ?? '0') === '1';

if ($has_upload) {
    $f = $_FILES['background_image'];
    if ($f['error'] !== UPLOAD_ERR_OK)
        json_err('อัปโหลดรูปไม่สำเร็จ (error ' . $f['error'] . ')');
    if ($f['size'] > 5 * 1024 * 1024)
        json_err('ไฟล์รูปพื้นหลังต้องไม่เกิน 5 MB');
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true))
        json_err('รองรับเฉพาะไฟล์รูป JPG, PNG, GIF, WEBP');
    if (@getimagesize($f['tmp_name']) === false)
        json_err('ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง');
    try {
        $stored = store_uploaded_file($f, 'cert_bg', 'cbg_');
        $new_bg_image = $stored['path'];
        if ($cur_bg_image && $cur_bg_image !== $new_bg_image) {
            @unlink(__DIR__ . '/../' . $cur_bg_image);
        }
    } catch (Throwable $e) {
        json_err('บันทึกรูปไม่สำเร็จ: ' . $e->getMessage());
    }
} elseif ($remove_bg && $cur_bg_image) {
    @unlink(__DIR__ . '/../' . $cur_bg_image);
    $new_bg_image = '';
    if ($bg_style === 'custom') $bg_style = 'plain';
}

$json = json_encode($clean, JSON_UNESCAPED_UNICODE);
if ($existing) {
    db_run('UPDATE course_certificates SET enabled=?, grade_json=?, background_style=?, background_image=? WHERE course_id=?',
        [$enabled, $json, $bg_style, $new_bg_image, $course_id]);
} else {
    db_run('INSERT INTO course_certificates (course_id, enabled, grade_json, background_style, background_image) VALUES (?,?,?,?,?)',
        [$course_id, $enabled, $json, $bg_style, $new_bg_image]);
}

json_ok(['message' => 'บันทึกการตั้งค่าเกียรติบัตรเรียบร้อยแล้ว']);
