<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

$page = $_GET['page'] ?? (is_logged_in() ? 'dashboard' : 'home');

// ── Pages that render their own full HTML (no app shell) ──────
$standalone = ['login', 'register', 'explore', 'home', 'certificate'];
if (in_array($page, $standalone, true)) {
    $file = __DIR__ . "/pages/{$page}.php";
    if (file_exists($file)) require $file;
    exit;
}

// ── Pages accessible without login (guest layout) ─────────────
$public_pages = ['course'];
if (!is_logged_in() && in_array($page, $public_pages, true)) {
    $title_map_pub = ['course' => 'รายวิชา'];
    $title_pub = $title_map_pub[$page] ?? 'ClassroomAI';
    layout_start_guest($title_pub);
    echo '<div class="content" style="padding-top:1.5rem">';
    $file = __DIR__ . "/pages/{$page}.php";
    if (file_exists($file)) require $file;
    echo '</div>';
    layout_end_guest();
    exit;
}

// ── Auth guard ────────────────────────────────────────────────
if (!is_logged_in()) {
    $qs = http_build_query(['page' => $page] + $_GET);
    redirect('index.php?page=login&redirect=' . urlencode('index.php?' . $qs));
}

// ── Session defaults ──────────────────────────────────────────
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'system';
}

// Admin ใช้หน้า admin เป็นหน้าหลัก
if (is_admin() && $page === 'dashboard') {
    redirect('index.php?page=admin');
}

// ── Title map ─────────────────────────────────────────────────
$title_map = [
    'dashboard'  => 'หน้าหลัก',
    'courses'    => 'รายวิชาทั้งหมด',
    'course'     => 'รายวิชา',
    'lesson'     => 'บทเรียน',
    'assignment' => 'งาน',
    'todo'       => 'งานที่ต้องส่ง',
    'tograde'    => 'งานรอตรวจ',
    'workqueue'  => 'คิวงาน',
    'calendar'        => 'ปฏิทิน',
    'profile'         => 'โปรไฟล์',
    'course_settings' => 'ตั้งค่ารายวิชา',
    'explore'         => 'ค้นหารายวิชา',
    'admin'           => 'ผู้ดูแลระบบ',
];
$title = $title_map[$page] ?? 'ClassroomAI';

// Map alias pages → page files
$page_map = ['todo' => 'workqueue', 'tograde' => 'workqueue'];
$page_key = $page_map[$page] ?? $page;

layout_start($title);
?>
<div class="content">
<?php
$page_file = __DIR__ . "/pages/{$page_key}.php";
if (file_exists($page_file)) {
    require $page_file;
} else {
    require __DIR__ . '/pages/dashboard.php';
}
?>
</div><!-- /.content -->
<?php
layout_end();
