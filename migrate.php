<?php
/**
 * migrate.php — ตรวจสอบและซ่อมโครงสร้างฐานข้อมูล
 * เรียกใช้ครั้งเดียวหลังติดตั้งใหม่หรืออัปเดตระบบ
 * URL: http://localhost/LGAIE/migrate.php
 */
declare(strict_types=1);

// ป้องกันเรียกจากภายนอก — อนุญาตเฉพาะ localhost
$allowed_hosts = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_hosts, true)) {
    http_response_code(403);
    exit('Access denied — localhost only.');
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = get_db();
$results = [];

function migrate_run(PDO $db, string $label, string $sql): array
{
    try {
        $db->exec($sql);
        return ['label' => $label, 'status' => 'ok', 'msg' => 'สำเร็จ'];
    } catch (PDOException $e) {
        return ['label' => $label, 'status' => 'skip', 'msg' => $e->getMessage()];
    }
}

// ── 1. คอลัมน์ใน users ที่เพิ่มภายหลัง ─────────────────────────────────────
$results[] = migrate_run($db, 'users.email',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL AFTER initials");
$results[] = migrate_run($db, 'users.password_hash',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER email");
$results[] = migrate_run($db, 'users.phone',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL");
$results[] = migrate_run($db, 'users.school',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS school VARCHAR(200) NULL");
$results[] = migrate_run($db, 'users.province',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS province VARCHAR(100) NULL");
$results[] = migrate_run($db, 'users.status',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','pending','suspended') NOT NULL DEFAULT 'active'");
$results[] = migrate_run($db, 'users.avatar_path',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) NULL");
$results[] = migrate_run($db, 'users.show_in_directory',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS show_in_directory TINYINT(1) NOT NULL DEFAULT 0");
$results[] = migrate_run($db, 'users.bio',
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio VARCHAR(255) NOT NULL DEFAULT ''");
try { $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_email ON users (email)"); $results[] = ['label'=>'users.uq_email','status'=>'ok','msg'=>'สำเร็จ']; } catch (PDOException $e) { $results[] = ['label'=>'users.uq_email','status'=>'skip','msg'=>$e->getMessage()]; }

// ── 2. คอลัมน์ใน courses ─────────────────────────────────────────────────────
$results[] = migrate_run($db, 'courses.is_public',
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_public TINYINT(1) DEFAULT 0");
$results[] = migrate_run($db, 'courses.is_template',
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_template TINYINT(1) DEFAULT 0");
$results[] = migrate_run($db, 'courses.template_id',
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS template_id INT UNSIGNED NULL");
$results[] = migrate_run($db, 'courses.template_secret',
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS template_secret CHAR(12) NULL");
$results[] = migrate_run($db, 'courses.is_archived',
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0");
$results[] = migrate_run($db, 'courses.archived_at',
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL");
$results[] = migrate_run($db, 'courses.materials_quota_mb',
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS materials_quota_mb INT UNSIGNED NULL");
$results[] = migrate_run($db, 'courses.submissions_quota_mb',
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS submissions_quota_mb INT UNSIGNED NULL");

// ── 3. คอลัมน์ใน lesson_materials ───────────────────────────────────────────
$results[] = migrate_run($db, 'lesson_materials.file_path',
    "ALTER TABLE lesson_materials ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) NULL");
$results[] = migrate_run($db, 'lesson_materials.file_size',
    "ALTER TABLE lesson_materials ADD COLUMN IF NOT EXISTS file_size INT UNSIGNED NOT NULL DEFAULT 0");
$results[] = migrate_run($db, 'lesson_materials.uploaded_at',
    "ALTER TABLE lesson_materials ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");

// ── 4. คอลัมน์ใน course_enrollments ─────────────────────────────────────────
$results[] = migrate_run($db, 'course_enrollments.join_type',
    "ALTER TABLE course_enrollments ADD COLUMN IF NOT EXISTS join_type ENUM('direct','invite_link','invite_code','invite_email','self','template') DEFAULT 'direct'");
$results[] = migrate_run($db, 'course_enrollments.joined_at',
    "ALTER TABLE course_enrollments ADD COLUMN IF NOT EXISTS joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");

// ── 5. คอลัมน์ example_file ใน prompts ──────────────────────────────────────
$results[] = migrate_run($db, 'lesson_prompts.ai_id nullable',
    "ALTER TABLE lesson_prompts MODIFY COLUMN ai_id VARCHAR(20) NULL");
$results[] = migrate_run($db, 'assignment_prompts.ai_id nullable',
    "ALTER TABLE assignment_prompts MODIFY COLUMN ai_id VARCHAR(20) NULL");
$results[] = migrate_run($db, 'lesson_prompts.example_file',
    "ALTER TABLE lesson_prompts ADD COLUMN IF NOT EXISTS example_file VARCHAR(255) NULL");
$results[] = migrate_run($db, 'lesson_prompts.example_file_name',
    "ALTER TABLE lesson_prompts ADD COLUMN IF NOT EXISTS example_file_name VARCHAR(255) NULL");
$results[] = migrate_run($db, 'assignment_prompts.example_file',
    "ALTER TABLE assignment_prompts ADD COLUMN IF NOT EXISTS example_file VARCHAR(255) NULL");
$results[] = migrate_run($db, 'assignment_prompts.example_file_name',
    "ALTER TABLE assignment_prompts ADD COLUMN IF NOT EXISTS example_file_name VARCHAR(255) NULL");

// ── 6. ตารางใหม่ ──────────────────────────────────────────────────────────────
$results[] = migrate_run($db, 'table: submission_files',
    "CREATE TABLE IF NOT EXISTS submission_files (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        submission_id INT UNSIGNED NOT NULL,
        name          VARCHAR(255) NOT NULL,
        file_path     VARCHAR(255) NOT NULL,
        file_type     VARCHAR(10)  NOT NULL,
        file_size     INT UNSIGNED NOT NULL DEFAULT 0,
        uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$results[] = migrate_run($db, 'table: app_settings',
    "CREATE TABLE IF NOT EXISTS app_settings (
        setting_key   VARCHAR(50)  PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$results[] = migrate_run($db, 'table: course_posts',
    "CREATE TABLE IF NOT EXISTS course_posts (
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

$results[] = migrate_run($db, 'table: course_certificates',
    "CREATE TABLE IF NOT EXISTS course_certificates (
        id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_id        INT UNSIGNED NOT NULL UNIQUE,
        enabled          TINYINT(1)   NOT NULL DEFAULT 0,
        grade_json       TEXT         NOT NULL DEFAULT '[]',
        background_style VARCHAR(32)  NOT NULL DEFAULT 'plain',
        background_image VARCHAR(255) NOT NULL DEFAULT '',
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = migrate_run($db, 'course_certificates.background_style',
    "ALTER TABLE course_certificates ADD COLUMN IF NOT EXISTS background_style VARCHAR(32) NOT NULL DEFAULT 'plain'");
$results[] = migrate_run($db, 'course_certificates.background_image',
    "ALTER TABLE course_certificates ADD COLUMN IF NOT EXISTS background_image VARCHAR(255) NOT NULL DEFAULT ''");
$results[] = migrate_run($db, 'course_certificates.orientation',
    "ALTER TABLE course_certificates ADD COLUMN IF NOT EXISTS orientation VARCHAR(16) NOT NULL DEFAULT 'portrait'");

$results[] = migrate_run($db, 'table: course_teachers',
    "CREATE TABLE IF NOT EXISTS course_teachers (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_id  INT UNSIGNED NOT NULL,
        user_id    INT UNSIGNED NOT NULL,
        co_role    ENUM('co','supervisor') NOT NULL DEFAULT 'co',
        added_by   INT UNSIGNED NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_course_teacher (course_id, user_id),
        INDEX (user_id),
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── 7. app_settings seed ─────────────────────────────────────────────────────
$results[] = migrate_run($db, 'app_settings seed',
    "INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
     ('max_file_mb','10'),
     ('course_materials_quota_mb','1024'),
     ('course_submissions_quota_mb','1024')");

// ── 8. สร้างโฟลเดอร์ uploads ─────────────────────────────────────────────────
try {
    ensure_all_upload_dirs();
    $results[] = ['label' => 'upload dirs', 'status' => 'ok', 'msg' => 'สร้าง/ตรวจสอบโฟลเดอร์ uploads/ แล้ว'];
} catch (Throwable $e) {
    $results[] = ['label' => 'upload dirs', 'status' => 'error', 'msg' => $e->getMessage()];
}

$ok_count   = count(array_filter($results, fn($r) => $r['status'] === 'ok'));
$skip_count = count(array_filter($results, fn($r) => $r['status'] === 'skip'));
$err_count  = count(array_filter($results, fn($r) => $r['status'] === 'error'));
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Migration — ClassroomAI</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 20px; background:#f8f9fa; color:#1a1a2e; }
  h1 { font-size:1.5rem; margin-bottom:4px; }
  .summary { display:flex; gap:16px; margin:16px 0 24px; }
  .chip { padding:6px 14px; border-radius:20px; font-size:14px; font-weight:600; }
  .chip.ok   { background:#d1fae5; color:#065f46; }
  .chip.skip { background:#fef9c3; color:#713f12; }
  .chip.err  { background:#fee2e2; color:#991b1b; }
  table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); }
  th, td { text-align:left; padding:10px 14px; font-size:13px; border-bottom:1px solid #f0f0f0; }
  th { background:#f4f4f7; font-weight:600; color:#555; }
  .s-ok   { color:#059669; font-weight:600; }
  .s-skip { color:#ca8a04; }
  .s-error{ color:#dc2626; font-weight:600; }
  .msg { color:#666; font-size:12px; }
  .back { display:inline-block; margin-top:24px; padding:10px 22px; background:#3b82f6; color:#fff; border-radius:8px; text-decoration:none; font-size:14px; }
</style>
</head>
<body>
<h1>ClassroomAI — Migration</h1>
<p style="color:#666;font-size:14px">ตรวจสอบและซ่อมโครงสร้างฐานข้อมูลทั้งหมด</p>
<div class="summary">
  <span class="chip ok">✓ <?= $ok_count ?> สำเร็จ</span>
  <span class="chip skip">~ <?= $skip_count ?> ข้าม (มีอยู่แล้ว)</span>
  <?php if ($err_count): ?><span class="chip err">✗ <?= $err_count ?> ผิดพลาด</span><?php endif; ?>
</div>
<table>
  <tr><th>รายการ</th><th>สถานะ</th><th>รายละเอียด</th></tr>
  <?php foreach ($results as $r): ?>
  <tr>
    <td><code><?= htmlspecialchars($r['label']) ?></code></td>
    <td class="s-<?= $r['status'] ?>"><?= match($r['status']) { 'ok'=>'✓ สำเร็จ', 'skip'=>'~ ข้าม', default=>'✗ ผิดพลาด' } ?></td>
    <td class="msg"><?= htmlspecialchars($r['msg']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<a href="index.php" class="back">← กลับหน้าหลัก</a>
</body>
</html>
