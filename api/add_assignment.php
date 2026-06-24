<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('Forbidden', 403);

$title      = trim($_POST['title'] ?? '');
$type       = trim($_POST['assignment_type'] ?? 'งาน');
$due        = trim($_POST['due_date'] ?? '');
$due_time   = trim($_POST['due_time'] ?? '');
$points     = max(1, (int)($_POST['points'] ?? 10));
$instr      = trim($_POST['instructions'] ?? '');
$prompt_txt = trim($_POST['prompt_text'] ?? '');
$ai_id      = trim($_POST['ai_id'] ?? '');
$rating     = max(1, min(5, (int)($_POST['rating'] ?? 3)));
$example    = trim($_POST['example_text'] ?? '');
$note       = trim($_POST['note_text'] ?? '');
$allow      = isset($_POST['allow_improve']) ? 1 : 0;
$course_id  = (int)($_POST['course_id'] ?? 0);

$questions_json = trim($_POST['questions_json'] ?? '');
$questions = [];
if ($questions_json !== '' && $questions_json !== '[]') {
    $questions = json_decode($questions_json, true) ?: [];
}

if (!$title || !$due || !$course_id) {
    json_err('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
}
if (!teaches_course($course_id)) json_err('ไม่มีสิทธิ์เพิ่มงานในรายวิชานี้', 403);
if ($type === 'แบบทดสอบ' && empty($questions)) {
    json_err('แบบทดสอบต้องมีคำถามอย่างน้อย 1 ข้อ');
}
if ($type !== 'แบบทดสอบ' && !$prompt_txt) {
    json_err('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
}

// Convert ISO date (YYYY-MM-DD CE) → Thai display strings
$th_months = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$time_str  = (preg_match('/^\d{2}:\d{2}$/', $due_time)) ? $due_time : '23:59';
$ts = strtotime($due);
if ($ts) {
    $d   = (int)date('j', $ts);
    $m   = (int)date('n', $ts);
    $y   = (int)date('Y', $ts) + 543;
    $due_display = "{$d} {$th_months[$m]} {$y} เวลา {$time_str} น.";
    $due_short   = "{$d} {$th_months[$m]}";
} else {
    $due_display = $due;
    $due_short   = mb_substr($due, 0, 10);
}

// Auto-migrate columns
try { get_db()->exec("ALTER TABLE assignment_prompts MODIFY COLUMN ai_id VARCHAR(20) NULL"); } catch (PDOException) {}
try { get_db()->exec("ALTER TABLE assignment_prompts ADD COLUMN example_file VARCHAR(255) NULL"); } catch (PDOException) {}
try { get_db()->exec("ALTER TABLE assignment_prompts ADD COLUMN example_file_name VARCHAR(255) NULL"); } catch (PDOException) {}
ensure_quiz_schema();

$example_file = $example_file_name = null;
if ($prompt_txt !== '') {
    ['path' => $example_file, 'name' => $example_file_name] = upload_example_file();
}

$db = get_db();
$db->beginTransaction();
try {
    $assignment_id = db_run(
        'INSERT INTO assignments (course_id, title, assignment_type, due_date, due_short, points, instructions, allow_improve) VALUES (?,?,?,?,?,?,?,?)',
        [$course_id, $title, $type, $due_display, $due_short, $points, $instr, $allow]
    );
    if ($prompt_txt !== '') {
        db_run(
            'INSERT INTO assignment_prompts (assignment_id, prompt_text, ai_id, rating, example_text, example_file, example_file_name, note_text) VALUES (?,?,?,?,?,?,?,?)',
            [$assignment_id, $prompt_txt, $ai_id ?: null, $rating, $example ?: null, $example_file, $example_file_name, $note ?: null]
        );
    }
    foreach ($questions as $i => $q) {
        $qtext = trim($q['text'] ?? '');
        $qtype = in_array($q['type'] ?? '', ['MCQ','truefalse']) ? $q['type'] : 'MCQ';
        $qpts  = max(1, (int)($q['points'] ?? 1));
        if (!$qtext) continue;
        $qid = db_run(
            'INSERT INTO quiz_questions (assignment_id, question_text, question_type, points, sort_order) VALUES (?,?,?,?,?)',
            [$assignment_id, $qtext, $qtype, $qpts, $i]
        );
        $choices = $q['choices'] ?? [];
        $correct = (int)($q['correct'] ?? 0);
        foreach ($choices as $ci => $ct) {
            $ct = trim((string)$ct);
            if ($ct === '' && $qtype === 'MCQ') continue;
            db_run(
                'INSERT INTO quiz_choices (question_id, choice_text, is_correct, sort_order) VALUES (?,?,?,?)',
                [$qid, $ct, ($ci === $correct) ? 1 : 0, $ci]
            );
        }
    }
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
    json_ok(['assignment_id' => $assignment_id, 'message' => 'เพิ่มงานเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
