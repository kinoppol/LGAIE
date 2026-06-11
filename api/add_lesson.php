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
$ai_id      = trim($_POST['ai_id'] ?? '');
$rating     = max(1, min(5, (int)($_POST['rating'] ?? 3)));
$example    = trim($_POST['example_text'] ?? '');
$note       = trim($_POST['note_text'] ?? '');
$course_id  = (int)($_POST['course_id'] ?? 0);

if (!$title || !$week || !$prompt_txt || !$course_id) {
    json_err('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
}

// Auto-migrate columns
try { get_db()->exec("ALTER TABLE lesson_prompts MODIFY COLUMN ai_id VARCHAR(20) NULL"); } catch (PDOException) {}
try { get_db()->exec("ALTER TABLE lesson_prompts ADD COLUMN example_file VARCHAR(255) NULL"); } catch (PDOException) {}
try { get_db()->exec("ALTER TABLE lesson_prompts ADD COLUMN example_file_name VARCHAR(255) NULL"); } catch (PDOException) {}
ensure_storage_schema();

// ตรวจไฟล์แนบเนื้อหาทั้งชุด (ขนาดต่อไฟล์ + โควต้ารวมของวิชา) ก่อนเขียนข้อมูลใด ๆ
$materials = collect_uploaded_files('materials');
if ($err = upload_batch_error($materials, $course_id, 'materials')) json_err($err);

['path' => $example_file, 'name' => $example_file_name] = upload_example_file();

$db = get_db();
$db->beginTransaction();
$saved_paths = [];
try {
    $sort = (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM lessons WHERE course_id = ?', [$course_id]);
    $lesson_id = db_run(
        'INSERT INTO lessons (course_id, title, week_label, description, sort_order) VALUES (?,?,?,?,?)',
        [$course_id, $title, $week, $desc, $sort]
    );
    db_run(
        'INSERT INTO lesson_prompts (lesson_id, prompt_text, ai_id, rating, example_text, example_file, example_file_name, note_text) VALUES (?,?,?,?,?,?,?,?)',
        [$lesson_id, $prompt_txt, $ai_id ?: null, $rating, $example ?: null, $example_file, $example_file_name, $note ?: null]
    );
    foreach ($materials as $f) {
        $st = store_uploaded_file($f, 'materials', 'mat_');
        $saved_paths[] = $st['path'];
        db_run(
            'INSERT INTO lesson_materials (lesson_id, name, file_type, file_path, file_size) VALUES (?,?,?,?,?)',
            [$lesson_id, $st['name'], $st['type'], $st['path'], $st['size']]
        );
    }
    $db->commit();
    json_ok(['lesson_id' => $lesson_id, 'message' => 'เพิ่มบทเรียนเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    foreach ($saved_paths as $p) {
        $fp = __DIR__ . '/../' . $p;
        if (file_exists($fp)) @unlink($fp);
    }
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
