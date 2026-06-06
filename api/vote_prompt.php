<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$submission_id = (int)($_POST['submission_id'] ?? 0);
$voter_id      = current_user_id();
$redirect      = $_POST['redirect'] ?? '?page=dashboard';

if ($submission_id) {
    try {
        db_run(
            'INSERT IGNORE INTO submission_votes (submission_id, voter_id) VALUES (?,?)',
            [$submission_id, $voter_id]
        );
        $_SESSION['success'] = 'โหวตแล้ว';
    } catch (Exception $e) {
        // ignore duplicate vote silently
    }
}

header("Location: $redirect");
exit;
