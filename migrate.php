<?php
/**
 * migrate.php — ตรวจสอบและซ่อมโครงสร้างฐานข้อมูล
 * เรียกใช้ครั้งเดียวหลังติดตั้งใหม่หรืออัปเดตระบบ
 * URL: http://localhost/LGAIE/migrate.php
 *
 * รายการ migration ทั้งหมดอยู่ใน run_all_migrations() (includes/functions.php)
 * ใช้ร่วมกับแท็บ "Migration" ในหน้าผู้ดูแลระบบ (pages/admin.php) — แก้ที่เดียวพอ
 */
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// ป้องกันเรียกจากภายนอก — อนุญาตเฉพาะ localhost หรือผู้ดูแลระบบที่ล็อกอินอยู่
$allowed_hosts = ['127.0.0.1', '::1', 'localhost'];
$is_local      = in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_hosts, true);
if (!$is_local && !is_admin()) {
    http_response_code(403);
    exit('Access denied — เข้าถึงได้เฉพาะจาก localhost หรือผู้ดูแลระบบ (admin) ที่ล็อกอินอยู่เท่านั้น');
}

$results = run_all_migrations();

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
