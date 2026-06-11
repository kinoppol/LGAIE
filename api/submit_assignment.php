<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_teacher()) json_err('ครูไม่สามารถส่งงานได้', 403);

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
$answer        = trim($_POST['answer_text'] ?? '');
$prompt_used   = trim($_POST['prompt_used'] ?? '');
$ai_used       = trim($_POST['ai_used'] ?? 'claude');
$result        = trim($_POST['result_text'] ?? '');
$better        = isset($_POST['better_than_teacher']) ? 1 : 0;
$compare_note  = trim($_POST['compare_note'] ?? '');
$redirect      = $_POST['redirect'] ?? '?page=dashboard';
$student_id    = current_user_id();

$files = collect_uploaded_files('files');

if (!$assignment_id || !$prompt_used) {
    $_SESSION['error'] = 'กรุณากรอก Prompt ที่ใช้';
    header("Location: $redirect");
    exit;
}

// ต้องมีคำตอบเป็นข้อความ หรือแนบไฟล์อย่างน้อย 1 ไฟล์
if (!$answer && empty($files)) {
    $_SESSION['error'] = 'กรุณาพิมพ์คำตอบหรือแนบไฟล์ผลงานอย่างน้อย 1 ไฟล์';
    header("Location: $redirect");
    exit;
}

ensure_storage_schema();
$course_id = (int)db_val('SELECT course_id FROM assignments WHERE id = ?', [$assignment_id]);

// ตรวจไฟล์แนบงานทั้งชุด (โควต้าไฟล์งานส่งของวิชา — แยกจากไฟล์เนื้อหา)
if ($err = upload_batch_error($files, $course_id, 'submissions')) {
    $_SESSION['error'] = $err;
    header("Location: $redirect");
    exit;
}

try {
    db_run(
        'INSERT INTO submissions (assignment_id, student_id, answer_text, prompt_used, ai_used, better_than_teacher, compare_note, result_text)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           answer_text=VALUES(answer_text), prompt_used=VALUES(prompt_used), ai_used=VALUES(ai_used),
           better_than_teacher=VALUES(better_than_teacher), compare_note=VALUES(compare_note), result_text=VALUES(result_text)',
        [$assignment_id, $student_id, $answer ?: null, $prompt_used, $ai_used, $better, $compare_note ?: null, $result ?: null]
    );
    $submission_id = (int)db_val(
        'SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?',
        [$assignment_id, $student_id]
    );
    foreach ($files as $f) {
        $st = store_uploaded_file($f, 'submissions', 'sub_');
        db_run(
            'INSERT INTO submission_files (submission_id, name, file_path, file_type, file_size) VALUES (?,?,?,?,?)',
            [$submission_id, $st['name'], $st['path'], $st['type'], $st['size']]
        );
    }
    $_SESSION['success'] = 'ส่งงานเรียบร้อยแล้ว!';
} catch (Exception $e) {
    $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

header("Location: $redirect");
exit;
