<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_teacher()) json_err('ไม่มีสิทธิ์', 403);

$course_id   = (int)($_POST['course_id']   ?? 0);
$body        = trim($_POST['body']         ?? '');
$prompt_text = trim($_POST['prompt_text']  ?? '');
$ai_id       = trim($_POST['ai_id']        ?? '');

if (!$course_id || $body === '') {
    json_err('กรุณากรอกข้อความประกาศ');
}

if (!teaches_course($course_id)) json_err('ไม่มีสิทธิ์ในรายวิชานี้', 403);

// Auto-create table for existing installations
get_db()->exec("CREATE TABLE IF NOT EXISTS course_posts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id   INT UNSIGNED NOT NULL,
    teacher_id  INT UNSIGNED NOT NULL,
    body        TEXT         NOT NULL,
    prompt_text TEXT         NULL,
    ai_id       VARCHAR(20)  NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_course (course_id),
    FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

db_run(
    'INSERT INTO course_posts (course_id, teacher_id, body, prompt_text, ai_id) VALUES (?, ?, ?, ?, ?)',
    [$course_id, current_user_id(), $body, $prompt_text ?: null, $ai_id ?: null]
);

json_ok(['message' => 'โพสต์ประกาศเรียบร้อย']);
