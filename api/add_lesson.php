<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('Forbidden', 403);

$title      = trim($_POST['title'] ?? '');
$week       = trim($_POST['week_label'] ?? '');
$desc       = trim($_POST['description'] ?? '');
$prompt_txt = trim($_POST['prompt_text'] ?? '');
$ai_id      = trim($_POST['ai_id'] ?? 'chatgpt');
$rating     = max(1, min(5, (int)($_POST['rating'] ?? 3)));
$example    = trim($_POST['example_text'] ?? '');
$note       = trim($_POST['note_text'] ?? '');
$course_id  = (int)($_POST['course_id'] ?? 0);

if (!$title || !$week || !$prompt_txt || !$course_id) {
    json_err('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
}

$db = get_db();
$db->beginTransaction();
try {
    $sort = (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM lessons WHERE course_id = ?', [$course_id]);
    $lesson_id = db_run(
        'INSERT INTO lessons (course_id, title, week_label, description, sort_order) VALUES (?,?,?,?,?)',
        [$course_id, $title, $week, $desc, $sort]
    );
    db_run(
        'INSERT INTO lesson_prompts (lesson_id, prompt_text, ai_id, rating, example_text, note_text) VALUES (?,?,?,?,?,?)',
        [$lesson_id, $prompt_txt, $ai_id, $rating, $example ?: null, $note ?: null]
    );
    $db->commit();
    json_ok(['lesson_id' => $lesson_id, 'message' => 'เพิ่มบทเรียนเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
