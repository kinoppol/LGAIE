<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$submission_id = (int)($_POST['submission_id'] ?? 0);
$voter_id      = current_user_id();
$is_ajax       = ($_POST['ajax'] ?? '') === '1';

if (!$submission_id) {
    if ($is_ajax) json_err('ไม่พบข้อมูลงาน');
    header('Location: ?page=dashboard');
    exit;
}

try {
    db_run(
        'INSERT IGNORE INTO submission_votes (submission_id, voter_id) VALUES (?,?)',
        [$submission_id, $voter_id]
    );
} catch (Exception $e) {
    // ignore duplicate vote silently
}

$vote_count = (int)db_val(
    'SELECT COUNT(*) FROM submission_votes WHERE submission_id = ?',
    [$submission_id]
);

if ($is_ajax) {
    json_ok(['vote_count' => $vote_count, 'message' => 'โหวตแล้ว']);
}

$_SESSION['success'] = 'โหวตแล้ว';
header('Location: ' . ($_POST['redirect'] ?? '?page=dashboard'));
exit;
