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
try { get_db()->exec("ALTER TABLE lesson_prompts ADD COLUMN example_file_name VARCHAR(255) NULL"); } catch (PDOException) {}
ensure_storage_schema();

$course_id = (int)db_val('SELECT course_id FROM lessons WHERE id = ?', [$lesson_id]);

// ไฟล์แนบเดิมที่ครูกดลบ (ตรวจว่าเป็นของบทเรียนนี้จริง)
$remove_ids   = array_values(array_filter(array_map('intval', (array)($_POST['remove_materials'] ?? []))));
$removed_rows = [];
if ($remove_ids) {
    $ph = implode(',', array_fill(0, count($remove_ids), '?'));
    $removed_rows = db_rows("SELECT * FROM lesson_materials WHERE lesson_id = ? AND id IN ({$ph})", [$lesson_id, ...$remove_ids]);
}
$freed_bytes = array_sum(array_map(fn($r) => (int)($r['file_size'] ?? 0), $removed_rows));

// ตรวจไฟล์ใหม่ทั้งชุดก่อนเขียนข้อมูล (หักขนาดไฟล์ที่กำลังลบออกจากพื้นที่ที่ใช้)
$materials = collect_uploaded_files('materials');
if ($err = upload_batch_error($materials, $course_id, 'materials', $freed_bytes)) json_err($err);

$existing_file      = db_val('SELECT example_file      FROM lesson_prompts WHERE lesson_id = ?', [$lesson_id]) ?: null;
$existing_file_name = db_val('SELECT example_file_name FROM lesson_prompts WHERE lesson_id = ?', [$lesson_id]) ?: null;

// ถ้ากดลบไฟล์เดิม
if (($_POST['remove_example_file'] ?? '0') === '1') {
    if ($existing_file) { $old = __DIR__ . '/../' . $existing_file; if (file_exists($old)) @unlink($old); }
    $example_file = null; $example_file_name = null;
} else {
    ['path' => $example_file, 'name' => $example_file_name] = upload_example_file('example_file', $existing_file, $existing_file_name);
}

$db = get_db();
$db->beginTransaction();
$saved_paths = [];
try {
    db_run(
        'UPDATE lessons SET title = ?, week_label = ?, description = ? WHERE id = ?',
        [$title, $week, $desc, $lesson_id]
    );

    if ($removed_rows) {
        $ph = implode(',', array_fill(0, count($removed_rows), '?'));
        db_run("DELETE FROM lesson_materials WHERE id IN ({$ph})",
            array_map(fn($r) => (int)$r['id'], $removed_rows));
    }
    foreach ($materials as $f) {
        $st = store_uploaded_file($f, 'materials', 'mat_');
        $saved_paths[] = $st['path'];
        db_run(
            'INSERT INTO lesson_materials (lesson_id, name, file_type, file_path, file_size) VALUES (?,?,?,?,?)',
            [$lesson_id, $st['name'], $st['type'], $st['path'], $st['size']]
        );
    }

    $has_prompt = db_val('SELECT 1 FROM lesson_prompts WHERE lesson_id = ?', [$lesson_id]);
    if ($has_prompt) {
        db_run(
            'UPDATE lesson_prompts SET prompt_text = ?, ai_id = ?, rating = ?, example_text = ?, example_file = ?, example_file_name = ?, note_text = ? WHERE lesson_id = ?',
            [$prompt_txt, $ai_id ?: null, $rating, $example ?: null, $example_file, $example_file_name, $note ?: null, $lesson_id]
        );
    } else {
        db_run(
            'INSERT INTO lesson_prompts (lesson_id, prompt_text, ai_id, rating, example_text, example_file, example_file_name, note_text) VALUES (?,?,?,?,?,?,?,?)',
            [$lesson_id, $prompt_txt, $ai_id ?: null, $rating, $example ?: null, $example_file, $example_file_name, $note ?: null]
        );
    }

    $db->commit();

    // ลบไฟล์จริงหลัง commit สำเร็จเท่านั้น
    foreach ($removed_rows as $r) {
        if (!empty($r['file_path'])) {
            $fp = __DIR__ . '/../' . $r['file_path'];
            if (file_exists($fp)) @unlink($fp);
        }
    }
    json_ok(['message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    foreach ($saved_paths as $p) {
        $fp = __DIR__ . '/../' . $p;
        if (file_exists($fp)) @unlink($fp);
    }
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
