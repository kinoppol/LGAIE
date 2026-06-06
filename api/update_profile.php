<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) redirect('../index.php?page=login');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('../index.php?page=profile');

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

// Rebuild initials from new name
$words    = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
$initials = '';
foreach (array_slice($words, 0, 2) as $w) {
    $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($chars)) $initials .= $chars[0];
}
if ($initials === '') $initials = mb_substr($name, 0, 1, 'UTF-8');

db_run(
    'UPDATE users SET name = ?, phone = ?, school = ?, province = ?, initials = ? WHERE id = ?',
    [$name, $phone, $school, $province, $initials, $uid]
);

$_SESSION['success'] = 'อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้ว';
redirect('../index.php?page=profile');
