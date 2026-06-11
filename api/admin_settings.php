<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_admin()) json_err('Forbidden — เฉพาะผู้ดูแลระบบ', 403);

ensure_storage_schema();

$action = $_POST['action'] ?? '';

/** อ่านค่า MB จากฟอร์ม — บังคับช่วง $min..$max */
function read_mb(string $field, int $min, int $max): int
{
    $v = (int)($_POST[$field] ?? 0);
    if ($v < $min || $v > $max) {
        json_err("ค่า {$field} ต้องอยู่ระหว่าง {$min}–{$max} MB");
    }
    return $v;
}

switch ($action) {
    case 'save_global':
        $max_file = read_mb('max_file_mb', 1, 100);
        $mat_q    = read_mb('course_materials_quota_mb', 1, 102400);
        $sub_q    = read_mb('course_submissions_quota_mb', 1, 102400);
        set_setting('max_file_mb', (string)$max_file);
        set_setting('course_materials_quota_mb', (string)$mat_q);
        set_setting('course_submissions_quota_mb', (string)$sub_q);
        json_ok(['message' => 'บันทึกค่ากลางเรียบร้อยแล้ว']);

    case 'set_course_quota':
        $course_id = (int)($_POST['course_id'] ?? 0);
        if (!db_val('SELECT 1 FROM courses WHERE id = ?', [$course_id])) json_err('ไม่พบรายวิชา');
        // ช่องว่าง = ล้าง override กลับไปใช้ค่ากลาง (NULL)
        $mat = trim((string)($_POST['materials_quota_mb'] ?? ''));
        $sub = trim((string)($_POST['submissions_quota_mb'] ?? ''));
        $mat_v = $mat === '' ? null : read_mb('materials_quota_mb', 1, 102400);
        $sub_v = $sub === '' ? null : read_mb('submissions_quota_mb', 1, 102400);
        db_run('UPDATE courses SET materials_quota_mb = ?, submissions_quota_mb = ? WHERE id = ?',
            [$mat_v, $sub_v, $course_id]);
        json_ok(['message' => 'บันทึกโควต้ารายวิชาเรียบร้อยแล้ว']);

    default:
        json_err('ไม่รู้จักคำสั่งนี้');
}
