<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in())            json_err('กรุณาเข้าสู่ระบบ', 401);
if (current_role() !== 'student') json_err('เฉพาะนักเรียนเท่านั้นที่ทำแบบทดสอบได้', 403);

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$student_id    = current_user_id();

$a = db_row('SELECT * FROM assignments WHERE id = ?', [$assignment_id]);
if (!$a) json_err('ไม่พบงานนี้');
if (!is_quiz_assignment_type($a['assignment_type'])) json_err('งานนี้ไม่ใช่แบบทดสอบ');

$enrolled = db_val('SELECT 1 FROM course_enrollments WHERE course_id = ? AND user_id = ?', [$a['course_id'], $student_id]);
if (!$enrolled) json_err('คุณไม่ได้ลงทะเบียนในรายวิชานี้', 403);

// ทำได้ครั้งเดียว — กันการส่งซ้ำ (unique key ที่ตาราง submissions ก็กันซ้ำอยู่แล้วเป็นชั้นที่สอง)
$already = db_val('SELECT 1 FROM submissions WHERE assignment_id = ? AND student_id = ?', [$assignment_id, $student_id]);
if ($already) json_err('คุณทำแบบทดสอบนี้ไปแล้ว');

$quiz_questions = get_quiz_questions($assignment_id);
if (!$quiz_questions) json_err('แบบทดสอบนี้ยังไม่มีคำถาม');

$answers = $_POST['answers'] ?? [];
if (!is_array($answers)) $answers = [];

$db = get_db();
$db->beginTransaction();
try {
    $total_points  = 0;
    $earned_points = 0;

    foreach ($quiz_questions as $q) {
        $qid            = (int)$q['id'];
        $total_points  += (int)$q['points'];
        $chosen_id      = (int)($answers[$qid] ?? 0);
        $chosen_choice  = null;
        foreach ($q['choices'] as $ch) {
            if ((int)$ch['id'] === $chosen_id) { $chosen_choice = $ch; break; }
        }
        $is_correct = $chosen_choice !== null && (int)$chosen_choice['is_correct'] === 1;
        if ($is_correct) $earned_points += (int)$q['points'];

        db_run(
            'INSERT INTO quiz_responses (assignment_id, student_id, question_id, choice_id, is_correct) VALUES (?,?,?,?,?)',
            [$assignment_id, $student_id, $qid, $chosen_choice ? (int)$chosen_choice['id'] : null, $is_correct ? 1 : 0]
        );
    }

    db_run(
        "INSERT INTO submissions (assignment_id, student_id, answer_text, prompt_used, ai_used, status, grade)
         VALUES (?, ?, ?, '', '', 'graded', ?)",
        [$assignment_id, $student_id, "ทำแบบทดสอบอัตโนมัติ — ได้ {$earned_points}/{$total_points} คะแนน", $earned_points]
    );

    $db->commit();
    json_ok([
        'message' => "ส่งคำตอบแล้ว — ได้คะแนน {$earned_points}/{$total_points}",
        'score'   => $earned_points,
        'total'   => $total_points,
    ]);
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}
