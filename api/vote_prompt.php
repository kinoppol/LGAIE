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

// Who owns this submission, and which assignment is it for?
$target = db_row('SELECT student_id, assignment_id FROM submissions WHERE id = ?', [$submission_id]);
if (!$target) {
    if ($is_ajax) json_err('ไม่พบงานที่ต้องการโหวต');
    header('Location: ?page=dashboard');
    exit;
}

// Students may vote on peers' work only after their own submission is graded,
// and may never vote their own. Teachers/admins are unrestricted.
if (!is_teacher()) {
    if ((int)$target['student_id'] === $voter_id) {
        if ($is_ajax) json_err('ไม่สามารถโหวตงานของตัวเองได้', 403);
        header('Location: ?page=dashboard');
        exit;
    }
    $mine = db_row(
        'SELECT status FROM submissions WHERE assignment_id = ? AND student_id = ?',
        [$target['assignment_id'], $voter_id]
    );
    if (!$mine || $mine['status'] !== 'graded') {
        if ($is_ajax) json_err('ต้องส่งงานและได้รับการตรวจก่อนจึงจะโหวตได้', 403);
        header('Location: ?page=dashboard');
        exit;
    }
}

// Toggle the vote: remove it if it already exists, otherwise add it
$already = (int)db_val(
    'SELECT COUNT(*) FROM submission_votes WHERE submission_id = ? AND voter_id = ?',
    [$submission_id, $voter_id]
);

try {
    if ($already) {
        db_run('DELETE FROM submission_votes WHERE submission_id = ? AND voter_id = ?', [$submission_id, $voter_id]);
        $voted = false;
    } else {
        db_run('INSERT IGNORE INTO submission_votes (submission_id, voter_id) VALUES (?,?)', [$submission_id, $voter_id]);
        $voted = true;
    }
} catch (Exception $e) {
    if ($is_ajax) json_err('เกิดข้อผิดพลาด');
    header('Location: ?page=dashboard');
    exit;
}

$vote_count = (int)db_val(
    'SELECT COUNT(*) FROM submission_votes WHERE submission_id = ?',
    [$submission_id]
);

if ($is_ajax) {
    json_ok([
        'vote_count' => $vote_count,
        'voted'      => $voted,
        'message'    => $voted ? 'โหวตแล้ว' : 'ยกเลิกโหวตแล้ว',
    ]);
}

$_SESSION['success'] = $voted ? 'โหวตแล้ว' : 'ยกเลิกโหวตแล้ว';
header('Location: ' . ($_POST['redirect'] ?? '?page=dashboard'));
exit;
