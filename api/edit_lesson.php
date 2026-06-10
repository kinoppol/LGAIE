<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('ไม่มีสิทธิ์', 403);

$lesson_id  = (int)($_POST['lesson_id']   ?? 0);
$title      = trim($_POST['title']        ?? '');
$week       = trim($_POST['week_label']   ?? '');
$desc       = trim($_POST['description']  ?? '');
$prompt_txt = trim($_POST['prompt_text']  ?? '');
$ai_id      = trim($_POST['ai_id']        ?? '');
$rating     = max(1, min(5, (int)($_POST['rating']       ?? 3)));
$example    = trim($_POST['example_text'] ?? '');
$note       = trim($_POST['note_text']    ?? '');

if (!$lesson_id || !$title || !$week || !$prompt_txt) {
    json_err('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
}

// Verify the lesson belongs to a course owned by this teacher
$owns = db_val('
    SELECT 1 FROM lessons l
    JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND c.teacher_id = ?
', [$lesson_id, current_user_id()]);
if (!$owns) json_err('ไม่มีสิทธิ์แก้ไขบทเรียนนี้', 403);

// Auto-migrate columns
try { get_db()->exec("ALTER TABLE lesson_prompts MODIFY COLUMN ai_id VARCHAR(20) NULL"); } catch (PDOException) {}
try { get_db()->exec("ALTER TABLE lesson_prompts ADD COLUMN example_file VARCHAR(255) NULL"); } catch (PDOException) {}

$existing_file = db_val('SELECT example_file FROM lesson_prompts WHERE lesson_id = ?', [$lesson_id]) ?: null;
$example_file  = upload_example_file('example_file', $existing_file);

$db = get_db();
$db->beginTransaction();
try {
    db_run(
        'UPDATE lessons SET title = ?, week_label = ?, description = ? WHERE id = ?',
        [$title, $week, $desc, $lesson_id]
    );

    $has_prompt = db_val('SELECT 1 FROM lesson_prompts WHERE lesson_id = ?', [$lesson_id]);
    if ($has_prompt) {
        db_run(
            'UPDATE lesson_prompts SET prompt_text = ?, ai_id = ?, rating = ?, example_text = ?, example_file = ?, note_text = ? WHERE lesson_id = ?',
            [$prompt_txt, $ai_id ?: null, $rating, $example ?: null, $example_file, $note ?: null, $lesson_id]
        );
    } else {
        db_run(
            'INSERT INTO lesson_prompts (lesson_id, prompt_text, ai_id, rating, example_text, example_file, note_text) VALUES (?,?,?,?,?,?,?)',
            [$lesson_id, $prompt_txt, $ai_id ?: null, $rating, $example ?: null, $example_file, $note ?: null]
        );
    }

    $db->commit();
    json_ok(['message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
