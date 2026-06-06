<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in())  json_err('Unauthorized', 401);
if (!is_teacher())    json_err('Forbidden', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

$code     = trim($_POST['code']     ?? '');
$name     = trim($_POST['name']     ?? '');
$section  = trim($_POST['section']  ?? '');
$is_public = (int)($_POST['is_public'] ?? 0);

if (!$code || !$name || !$section) {
    json_err('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
}

// Colour palettes
$palettes = [
    ['linear-gradient(120deg,#cdeee2,#e7f7f1)', '#0c7a5e', '#2bb393'],
    ['linear-gradient(120deg,#d8e3fd,#ecf1ff)', '#3257c7', '#6b8efb'],
    ['linear-gradient(120deg,#e8dcfb,#f4edff)', '#7140cf', '#a585f2'],
    ['linear-gradient(120deg,#ffe6cc,#fff2e2)', '#bd741a', '#f0a44e'],
    ['linear-gradient(120deg,#fce4ec,#fff0f3)', '#c0394d', '#f07189'],
    ['linear-gradient(120deg,#e0f2fe,#f0f9ff)', '#0369a1', '#38bdf8'],
];
$palette = $palettes[array_rand($palettes)];

// short_name: first char(s) of name words
$words      = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
$short_name = '';
foreach (array_slice($words, 0, 2) as $w) {
    $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($chars)) $short_name .= $chars[0];
}
if ($short_name === '') $short_name = mb_substr($name, 0, 2, 'UTF-8');

$course_id = db_run(
    'INSERT INTO courses (code, name, section, short_name, banner, ink_color, primary_color, teacher_id, is_public)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$code, $name, $section, $short_name, $palette[0], $palette[1], $palette[2], current_user_id(), $is_public]
);

// Auto-enroll the creator as teacher in course_teachers
db_run(
    'INSERT IGNORE INTO course_teachers (course_id, user_id) VALUES (?, ?)',
    [$course_id, current_user_id()]
);

json_ok(['course_id' => $course_id, 'message' => 'สร้างรายวิชาเรียบร้อยแล้ว']);
