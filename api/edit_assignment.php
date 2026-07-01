<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('ไม่มีสิทธิ์', 403);

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$title      = trim($_POST['title']           ?? '');
$type       = trim($_POST['assignment_type'] ?? 'งาน');
$due        = trim($_POST['due_date']        ?? '');
$due_time   = trim($_POST['due_time']        ?? '');
$points     = max(1, (int)($_POST['points']  ?? 10));
$instr      = trim($_POST['instructions']    ?? '');
$prompt_txt = trim($_POST['prompt_text']     ?? '');
$ai_id      = trim($_POST['ai_id']           ?? '');
$rating     = max(1, min(5, (int)($_POST['rating']       ?? 3)));
$example    = trim($_POST['example_text']    ?? '');
$note       = trim($_POST['note_text']       ?? '');
$allow      = isset($_POST['allow_improve'])  ? 1 : 0;

if (!$assignment_id || !$title || !$prompt_txt) {
    json_err('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
}

// Access check — teacher must teach the course this assignment belongs to
$course_id = (int)db_val('SELECT course_id FROM assignments WHERE id = ?', [$assignment_id]);
if (!$course_id || !teaches_course($course_id)) json_err('ไม่มีสิทธิ์แก้ไขงานนี้', 403);

// Auto-migrate columns
try { get_db()->exec("ALTER TABLE assignment_prompts MODIFY COLUMN ai_id VARCHAR(20) NULL"); } catch (PDOException) {}
try { get_db()->exec("ALTER TABLE assignment_prompts ADD COLUMN example_file VARCHAR(255) NULL"); } catch (PDOException) {}
try { get_db()->exec("ALTER TABLE assignment_prompts ADD COLUMN example_file_name VARCHAR(255) NULL"); } catch (PDOException) {}
ensure_quiz_schema();

$existing_file      = db_val('SELECT example_file      FROM assignment_prompts WHERE assignment_id = ?', [$assignment_id]) ?: null;
$existing_file_name = db_val('SELECT example_file_name FROM assignment_prompts WHERE assignment_id = ?', [$assignment_id]) ?: null;

// ถ้ากดลบไฟล์เดิม
if (($_POST['remove_example_file'] ?? '0') === '1') {
    if ($existing_file) { $old = __DIR__ . '/../' . $existing_file; if (file_exists($old)) @unlink($old); }
    $example_file = null; $example_file_name = null;
} else {
    ['path' => $example_file, 'name' => $example_file_name] = upload_example_file('example_file', $existing_file, $existing_file_name);
}

$db = get_db();
$db->beginTransaction();
try {
    if ($due && strtotime($due)) {
        $th_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
        $time_str    = preg_match('/^\d{2}:\d{2}$/', $due_time) ? $due_time : '23:59';
        $ts          = strtotime($due);
        $d           = (int)date('j', $ts);
        $m           = (int)date('n', $ts);
        $y           = (int)date('Y', $ts) + 543;
        $due_display = "{$d} {$th_months[$m]} {$y} เวลา {$time_str} น.";
        $due_short   = "{$d} {$th_months[$m]}";
        db_run(
            'UPDATE assignments SET title=?, assignment_type=?, due_date=?, due_short=?, points=?, instructions=?, allow_improve=? WHERE id=?',
            [$title, $type, $due_display, $due_short, $points, $instr, $allow, $assignment_id]
        );
    } else {
        db_run(
            'UPDATE assignments SET title=?, assignment_type=?, points=?, instructions=?, allow_improve=? WHERE id=?',
            [$title, $type, $points, $instr, $allow, $assignment_id]
        );
    }

    $has_prompt = db_val('SELECT 1 FROM assignment_prompts WHERE assignment_id = ?', [$assignment_id]);
    if ($has_prompt) {
        db_run(
            'UPDATE assignment_prompts SET prompt_text=?, ai_id=?, rating=?, example_text=?, example_file=?, example_file_name=?, note_text=? WHERE assignment_id=?',
            [$prompt_txt, $ai_id ?: null, $rating, $example ?: null, $example_file, $example_file_name, $note ?: null, $assignment_id]
        );
    } else {
        db_run(
            'INSERT INTO assignment_prompts (assignment_id, prompt_text, ai_id, rating, example_text, example_file, example_file_name, note_text) VALUES (?,?,?,?,?,?,?,?)',
            [$assignment_id, $prompt_txt, $ai_id ?: null, $rating, $example ?: null, $example_file, $example_file_name, $note ?: null]
        );
    }

    db_run('DELETE FROM assignment_links WHERE assignment_id = ?', [$assignment_id]);
    $link_urls   = $_POST['link_url']   ?? [];
    $link_labels = $_POST['link_label'] ?? [];
    foreach ($link_urls as $i => $url) {
        $url = trim($url);
        if ($url === '') continue;
        $label = trim($link_labels[$i] ?? '');
        db_run(
            'INSERT INTO assignment_links (assignment_id, url, label, sort_order) VALUES (?,?,?,?)',
            [$assignment_id, $url, $label, $i]
        );
    }
    $db->commit();
    json_ok(['message' => 'บันทึกการแก้ไขเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
