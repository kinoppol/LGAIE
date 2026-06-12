<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$action = trim($_POST['action'] ?? ($_GET['action'] ?? ''));

// ── Export the whole AI registry as a downloadable JSON file ─────────────────
if ($action === 'export') {
    $rows = db_rows('SELECT id, name, letter, color, url FROM ai_tools ORDER BY id');
    $payload = [
        'type'        => 'classroomai.ai_tools',
        'version'     => 1,
        'exported_at' => date('c'),
        'tools'       => $rows,
    ];
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="ai-tools-' . date('Ymd-His') . '.json"');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);

// ── Import AI tools from an uploaded JSON file ───────────────────────────────
if ($action === 'import') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_err('กรุณาเลือกไฟล์ JSON ที่ต้องการนำเข้า');
    }
    if ($_FILES['file']['size'] > 1 * 1024 * 1024) json_err('ไฟล์ใหญ่เกินไป (ไม่เกิน 1 MB)');

    $raw  = file_get_contents($_FILES['file']['tmp_name']);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) json_err('ไฟล์ไม่ใช่ JSON ที่ถูกต้อง');

    // Accept either {tools:[...]} or a bare array of tools
    $tools = $data['tools'] ?? $data;
    if (!is_array($tools) || !$tools) json_err('ไม่พบรายการ AI ในไฟล์');

    $added = $updated = $skipped = 0;
    $errors = [];
    foreach ($tools as $i => $t) {
        if (!is_array($t)) { $skipped++; continue; }
        $id     = strtolower(trim((string)($t['id'] ?? '')));
        $name   = trim((string)($t['name'] ?? ''));
        $letter = trim((string)($t['letter'] ?? ''));
        $color  = strtolower(trim((string)($t['color'] ?? '')));
        $url    = preg_replace('#^https?://#i', '', trim((string)($t['url'] ?? '')));
        $url    = rtrim((string)$url, '/');

        if (!preg_match('/^[a-z0-9_-]{2,20}$/', $id)
            || $name === '' || mb_strlen($name) > 50
            || $letter === '' || mb_strlen($letter) > 5
            || !preg_match('/^#[0-9a-f]{6}$/', $color)
            || $url === '' || mb_strlen($url) > 100) {
            $skipped++;
            $errors[] = 'แถวที่ ' . ($i + 1) . ' (' . ($id ?: '?') . ')';
            continue;
        }

        $exists = (bool)get_ai($id);
        db_run(
            'INSERT INTO ai_tools (id, name, letter, color, url) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), letter = VALUES(letter),
                                     color = VALUES(color), url = VALUES(url)',
            [$id, $name, $letter, $color, $url]
        );
        $exists ? $updated++ : $added++;
    }

    $msg = "นำเข้าสำเร็จ — เพิ่ม {$added}, อัปเดต {$updated}";
    if ($skipped > 0) $msg .= ", ข้าม {$skipped}";
    if ($errors)      $msg .= ' (ข้อมูลไม่ถูกต้อง: ' . implode(', ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '…' : '') . ')';
    json_ok(['message' => $msg, 'added' => $added, 'updated' => $updated, 'skipped' => $skipped]);
}

// ── Delete an AI tool ───────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = trim($_POST['id'] ?? '');
    if ($id === '' || !get_ai($id)) json_err('ไม่พบ AI ที่ต้องการลบ');

    // Block deletion while the AI is still referenced anywhere
    $used = (int)db_val('SELECT COUNT(*) FROM lesson_prompts     WHERE ai_id = ?',  [$id])
          + (int)db_val('SELECT COUNT(*) FROM assignment_prompts WHERE ai_id = ?',  [$id])
          + (int)db_val('SELECT COUNT(*) FROM submissions        WHERE ai_used = ?', [$id]);
    if ($used > 0) {
        json_err("ลบไม่ได้ — มี {$used} รายการ (prompt/งานส่ง) ที่ยังใช้ AI นี้อยู่");
    }

    db_run('DELETE FROM ai_tools WHERE id = ?', [$id]);
    json_ok(['message' => 'ลบ AI เรียบร้อยแล้ว']);
}

// ── Add or update an AI tool ────────────────────────────────────────────────
if ($action === 'save') {
    $is_edit = ($_POST['mode'] ?? '') === 'edit';
    $id      = strtolower(trim($_POST['id'] ?? ''));
    $name    = trim($_POST['name'] ?? '');
    $letter  = trim($_POST['letter'] ?? '');
    $color   = strtolower(trim($_POST['color'] ?? ''));
    $url     = trim($_POST['url'] ?? '');

    // Normalise url: drop protocol + trailing slash
    $url = preg_replace('#^https?://#i', '', $url);
    $url = rtrim((string)$url, '/');

    // Validate
    if (!preg_match('/^[a-z0-9_-]{2,20}$/', $id)) {
        json_err('รหัส AI ต้องเป็น a–z, 0–9, - หรือ _ ความยาว 2–20 ตัว');
    }
    if ($name === '' || mb_strlen($name) > 50)  json_err('กรุณากรอกชื่อ AI (ไม่เกิน 50 ตัวอักษร)');
    if ($letter === '' || mb_strlen($letter) > 5) json_err('โลโก้ย่อต้องมี 1–5 ตัวอักษร');
    if (!preg_match('/^#[0-9a-f]{6}$/', $color))  json_err('สีต้องเป็นรหัสฮกซ์ เช่น #4d6bfe');
    if ($url === '' || mb_strlen($url) > 100)     json_err('กรุณากรอก URL ของ AI');

    $exists = (bool)get_ai($id);
    if (!$is_edit && $exists) {
        json_err("รหัส \"{$id}\" ถูกใช้แล้ว — เลือกรหัสอื่น หรือกดแก้ไขรายการเดิม");
    }
    if ($is_edit && !$exists) {
        json_err('ไม่พบ AI ที่ต้องการแก้ไข');
    }

    db_run(
        'INSERT INTO ai_tools (id, name, letter, color, url) VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), letter = VALUES(letter),
                                 color = VALUES(color), url = VALUES(url)',
        [$id, $name, $letter, $color, $url]
    );
    json_ok(['message' => $is_edit ? 'อัปเดต AI เรียบร้อยแล้ว' : 'เพิ่ม AI ใหม่เรียบร้อยแล้ว']);
}

json_err('คำสั่งไม่ถูกต้อง');
