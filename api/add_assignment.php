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

if (!$title || !$due || !$prompt_txt || !$course_id) {
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

// Allow ai_id to be NULL for existing installations
try {
    get_db()->exec("ALTER TABLE assignment_prompts MODIFY COLUMN ai_id VARCHAR(20) NULL");
} catch (PDOException) {}

$db = get_db();
$db->beginTransaction();
try {
    $assignment_id = db_run(
        'INSERT INTO assignments (course_id, title, assignment_type, due_date, due_short, points, instructions, allow_improve) VALUES (?,?,?,?,?,?,?,?)',
        [$course_id, $title, $type, $due_display, $due_short, $points, $instr, $allow]
    );
    db_run(
        'INSERT INTO assignment_prompts (assignment_id, prompt_text, ai_id, rating, example_text, note_text) VALUES (?,?,?,?,?,?)',
        [$assignment_id, $prompt_txt, $ai_id ?: null, $rating, $example ?: null, $note ?: null]
    );
    $db->commit();
    json_ok(['assignment_id' => $assignment_id, 'message' => 'เพิ่มงานเรียบร้อยแล้ว']);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
