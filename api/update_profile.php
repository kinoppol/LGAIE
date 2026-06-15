<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) redirect('../index.php?page=login');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../index.php?page=profile');

// ── Directory visibility toggle (separate lightweight action) ────────────
if (($_POST['_action'] ?? '') === 'directory') {
    ensure_directory_schema();
    $show = isset($_POST['show_in_directory']) ? 1 : 0;
    db_run('UPDATE users SET show_in_directory = ? WHERE id = ?', [$show, current_user_id()]);
    $_SESSION['success'] = $show ? 'แสดงชื่อในหน้าสาธารณะเรียบร้อยแล้ว' : 'ซ่อนชื่อจากหน้าสาธารณะเรียบร้อยแล้ว';
    redirect('../index.php?page=profile');
}

$uid      = current_user_id();
$name     = trim($_POST['name']     ?? '');
$phone    = trim($_POST['phone']    ?? '');
$school   = trim($_POST['school']   ?? '');
$province = trim($_POST['province'] ?? '');

// Validate
$errors = [];
if ($name === '')     $errors[] = 'กรุณากรอกชื่อ-สกุล';
if ($phone === '')    $errors[] = 'กรุณากรอกหมายเลขโทรศัพท์';
if ($school === '')   $errors[] = 'กรุณากรอกชื่อสถานศึกษา';
if ($province === '' || !in_array($province, get_provinces(), true)) {
    $errors[] = 'กรุณาเลือกจังหวัดที่ถูกต้อง';
}

if ($errors) {
    $_SESSION['error'] = implode(' · ', $errors);
    redirect('../index.php?page=profile');
}

// ── Profile picture: upload new / remove existing ───────────────────────────
ensure_storage_schema();
$current_avatar = (string)db_val('SELECT avatar_path FROM users WHERE id = ?', [$uid]);
$new_avatar     = $current_avatar;     // unchanged by default
$remove_avatar  = ($_POST['remove_avatar'] ?? '0') === '1';

$has_upload = isset($_FILES['avatar_image'])
    && $_FILES['avatar_image']['error'] !== UPLOAD_ERR_NO_FILE
    && !empty($_FILES['avatar_image']['name']);

if ($has_upload) {
    $f = $_FILES['avatar_image'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่';
        redirect('../index.php?page=profile');
    }
    if ($f['size'] > 5 * 1024 * 1024) {
        $_SESSION['error'] = 'ไฟล์รูปต้องไม่เกิน 5 MB';
        redirect('../index.php?page=profile');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $_SESSION['error'] = 'รองรับเฉพาะไฟล์รูป JPG, PNG, GIF, WEBP';
        redirect('../index.php?page=profile');
    }
    // Reject files that aren't really images
    if (@getimagesize($f['tmp_name']) === false) {
        $_SESSION['error'] = 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง';
        redirect('../index.php?page=profile');
    }
    try {
        $stored     = store_uploaded_file($f, 'avatars', 'av_');
        $new_avatar = $stored['path'];
        // Remove the previous avatar file (best effort)
        if ($current_avatar && $current_avatar !== $new_avatar) {
            @unlink(__DIR__ . '/../' . $current_avatar);
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'บันทึกรูปไม่สำเร็จ: ' . $e->getMessage();
        redirect('../index.php?page=profile');
    }
} elseif ($remove_avatar && $current_avatar) {
    @unlink(__DIR__ . '/../' . $current_avatar);
    $new_avatar = null;
}

// Rebuild initials from new name
$words    = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
$initials = '';
foreach (array_slice($words, 0, 2) as $w) {
    $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($chars)) $initials .= $chars[0];
}
if ($initials === '') $initials = mb_substr($name, 0, 1, 'UTF-8');

db_run(
    'UPDATE users SET name = ?, phone = ?, school = ?, province = ?, initials = ?, avatar_path = ? WHERE id = ?',
    [$name, $phone, $school, $province, $initials, $new_avatar, $uid]
);

$_SESSION['success'] = 'อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว';
redirect('../index.php?page=profile');
