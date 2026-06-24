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

$enabled    = isset($_POST['enabled']) ? 1 : 0;
$grade_json = trim($_POST['grade_json'] ?? '[]');

$grades = json_decode($grade_json, true);
if (!is_array($grades)) $grades = [];
$clean = [];
foreach ($grades as $g) {
    $label = trim((string)($g['label'] ?? ''));
    $min   = max(0, min(100, (int)($g['min'] ?? 0)));
    if ($label !== '') $clean[] = ['label' => $label, 'min' => $min];
}
usort($clean, fn($a, $b) => $b['min'] <=> $a['min']);

ensure_certificate_schema();

$existing = db_val('SELECT id FROM course_certificates WHERE course_id = ?', [$course_id]);
if ($existing) {
    db_run('UPDATE course_certificates SET enabled = ?, grade_json = ? WHERE course_id = ?',
        [$enabled, json_encode($clean, JSON_UNESCAPED_UNICODE), $course_id]);
} else {
    db_run('INSERT INTO course_certificates (course_id, enabled, grade_json) VALUES (?,?,?)',
        [$course_id, $enabled, json_encode($clean, JSON_UNESCAPED_UNICODE)]);
}

json_ok(['message' => 'บันทึกการตั้งค่าเกียรติบัตรเรียบร้อยแล้ว']);
