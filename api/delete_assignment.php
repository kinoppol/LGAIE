<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('ไม่มีสิทธิ์', 403);

$assignment_id = (int)($_POST['assignment_id'] ?? 0);
if (!$assignment_id) json_err('ไม่พบงานที่ต้องการลบ');

// Access check — teacher must teach the course this assignment belongs to
$course_id = (int)db_val('SELECT course_id FROM assignments WHERE id = ?', [$assignment_id]);
if (!$course_id || !teaches_course($course_id)) json_err('ไม่มีสิทธิ์ลบงานนี้', 403);

// Collect on-disk files to remove after the DB rows are gone (FK cascade
// removes the rows but not the uploaded files).
$files = [];
try {
    $rows = db_rows('
        SELECT sf.file_path
        FROM submission_files sf
        JOIN submissions s ON s.id = sf.submission_id
        WHERE s.assignment_id = ?
    ', [$assignment_id]);
    foreach ($rows as $r) { if (!empty($r['file_path'])) $files[] = $r['file_path']; }
} catch (PDOException) {}
try {
    $ex = db_val('SELECT example_file FROM assignment_prompts WHERE assignment_id = ?', [$assignment_id]);
    if ($ex) $files[] = $ex;
} catch (PDOException) {}

$db = get_db();
$db->beginTransaction();
try {
    // Tables without an ON DELETE CASCADE foreign key — clear defensively.
    foreach (['assignment_links', 'quiz_questions'] as $t) {
        try { db_run("DELETE FROM {$t} WHERE assignment_id = ?", [$assignment_id]); }
        catch (PDOException) {}
    }
    // assignment_prompts and submissions (and their children) cascade.
    db_run('DELETE FROM assignments WHERE id = ?', [$assignment_id]);
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    json_err('เกิดข้อผิดพลาด: ' . $e->getMessage(), 500);
}

// Remove orphaned uploads (best-effort).
foreach ($files as $f) {
    $path = __DIR__ . '/../' . ltrim($f, '/');
    if (is_file($path)) @unlink($path);
}

json_ok(['message' => 'ลบงานเรียบร้อยแล้ว']);
