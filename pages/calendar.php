<?php
declare(strict_types=1);

$uid = current_user_id();

// ── Fetch assignments visible to the current user ─────────────
if (is_teacher()) {
    $items = db_rows('
        SELECT a.*, c.name AS course_name, c.primary_color AS course_color
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        WHERE c.is_archived = 0 AND c.teacher_id = ?
    ', [$uid]);
} else {
    $items = db_rows('
        SELECT a.*, c.name AS course_name, c.primary_color AS course_color,
            (SELECT 1 FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) AS submitted
        FROM assignments a
        JOIN courses c ON c.id = a.course_id
        JOIN course_enrollments e ON e.course_id = a.course_id AND e.user_id = ?
        WHERE c.is_archived = 0
    ', [$uid, $uid]);
}

// ── Parse free-text Thai due_date ("12 มิ.ย. 2569") → Y-m-d ────
$thai_months = [
    'ม.ค.' => 1, 'ก.พ.' => 2, 'มี.ค.' => 3, 'เม.ย.' => 4,
    'พ.ค.' => 5, 'มิ.ย.' => 6, 'ก.ค.' => 7, 'ส.ค.' => 8,
    'ก.ย.' => 9, 'ต.ค.' => 10, 'พ.ย.' => 11, 'ธ.ค.' => 12,
];
$th_month_names = [1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

function parse_thai_due(string $s, array $months): ?array
{
    $s = trim($s);
    // Expect: "<day> <month-abbr> <BE-year>"
    $parts = preg_split('/\s+/u', $s);
    if (count($parts) < 3) return null;
    $day  = (int)$parts[0];
    $mon  = $months[$parts[1]] ?? null;
    $year = (int)$parts[2];
    if (!$mon || $day < 1 || $day > 31 || $year < 2400) return null;
    return ['y' => $year - 543, 'm' => $mon, 'd' => $day];
}

// Group parsed assignments by Y-m-d; collect undated ones separately
$by_day   = [];   // 'Y-m-d' => [items]
$undated  = [];
foreach ($items as $a) {
    $p = parse_thai_due((string)$a['due_date'], $thai_months);
    if ($p === null) { $undated[] = $a; continue; }
    $key = sprintf('%04d-%02d-%02d', $p['y'], $p['m'], $p['d']);
    $by_day[$key][] = $a;
}

// ── Which month to display ────────────────────────────────────
$today = new DateTime('today');
$ym = $_GET['m'] ?? $today->format('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = $today->format('Y-m');
[$cy, $cm] = array_map('intval', explode('-', $ym));

$first    = new DateTime(sprintf('%04d-%02d-01', $cy, $cm));
$prev     = (clone $first)->modify('-1 month')->format('Y-m');
$next     = (clone $first)->modify('+1 month')->format('Y-m');
$days_in  = (int)$first->format('t');
$lead     = (int)$first->format('w'); // 0=Sun
$today_key = $today->format('Y-m-d');
$be_year  = $cy + 543;
?>

<style>
.cal-wrap   { max-width: 1000px; }
.cal-head   { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 12px; }
.cal-title  { font-size: 1.25rem; font-weight: 800; color: var(--heading); margin: 0; }
.cal-nav    { display: flex; align-items: center; gap: 8px; }
.cal-nav a  { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 9px;
              border: 1px solid var(--line-2); background: var(--card); color: var(--text); text-decoration: none; }
.cal-nav a:hover { background: var(--primary-soft); border-color: var(--primary); }
.cal-month  { min-width: 170px; text-align: center; font-weight: 700; color: var(--heading); font-size: 1.02rem; }

.cal-grid   { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
.cal-dow    { text-align: center; font-size: .73rem; font-weight: 700; color: var(--sub); padding: 6px 0; text-transform: uppercase; letter-spacing: .04em; }
.cal-cell   { min-height: 96px; border: 1px solid var(--line-2); border-radius: 10px; background: var(--card);
              padding: 6px 7px; display: flex; flex-direction: column; gap: 4px; overflow: hidden; }
.cal-cell.empty { background: transparent; border-color: transparent; }
.cal-cell.today { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-soft); }
.cal-dnum   { font-size: .82rem; font-weight: 700; color: var(--heading); align-self: flex-start; }
.cal-cell.today .cal-dnum { background: var(--primary); color: #fff; border-radius: 50%; width: 24px; height: 24px; display: grid; place-items: center; }
.cal-ev     { display: block; font-size: 11.5px; line-height: 1.25; padding: 3px 6px; border-radius: 6px;
              text-decoration: none; color: var(--heading); font-weight: 600;
              white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cal-legend { margin-top: 1.5rem; }
.cal-agenda-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid var(--line-2);
                   border-radius: 10px; background: var(--card); text-decoration: none; margin-bottom: 8px; }
.cal-dot    { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; }
@media (max-width: 640px) { .cal-cell { min-height: 64px; } .cal-ev { display: none; } .cal-cell::after { content: ''; } }
</style>

<div class="cal-wrap">
  <div class="cal-head">
    <h1 class="cal-title"><?= icon('calendar', 22, 'var(--primary)') ?> ปฏิทินกำหนดส่งงาน</h1>
    <div class="cal-nav">
      <a href="<?= url('calendar', ['m' => $prev]) ?>" title="เดือนก่อนหน้า"><?= icon('arrow-left', 18) ?></a>
      <span class="cal-month"><?= h($th_month_names[$cm]) ?> <?= $be_year ?></span>
      <a href="<?= url('calendar', ['m' => $next]) ?>" title="เดือนถัดไป"><?= icon('arrow-right', 18) ?></a>
    </div>
  </div>

  <div class="cal-grid">
    <?php foreach (['อา','จ','อ','พ','พฤ','ศ','ส'] as $dow): ?>
      <div class="cal-dow"><?= $dow ?></div>
    <?php endforeach; ?>

    <?php for ($i = 0; $i < $lead; $i++): ?>
      <div class="cal-cell empty"></div>
    <?php endfor; ?>

    <?php for ($d = 1; $d <= $days_in; $d++):
      $key = sprintf('%04d-%02d-%02d', $cy, $cm, $d);
      $evs = $by_day[$key] ?? [];
      $is_today = $key === $today_key;
    ?>
      <div class="cal-cell<?= $is_today ? ' today' : '' ?>">
        <span class="cal-dnum"><?= $d ?></span>
        <?php foreach ($evs as $a): ?>
          <a class="cal-ev" href="<?= url('assignment', ['assignment_id' => $a['id']]) ?>"
             title="<?= h($a['title']) ?> · <?= h($a['course_name']) ?>"
             style="background:<?= h($a['course_color']) ?>1c;color:<?= h($a['course_color']) ?>">
            <?= h($a['title']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>

  <?php if (!empty($undated)): ?>
  <div class="cal-legend">
    <div class="prof-sec" style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--sub);margin:1.5rem 0 .85rem">
      งานที่ไม่ระบุวันกำหนดส่งชัดเจน
    </div>
    <?php foreach ($undated as $a): ?>
      <a class="cal-agenda-item" href="<?= url('assignment', ['assignment_id' => $a['id']]) ?>">
        <span class="cal-dot" style="background:<?= h($a['course_color']) ?>"></span>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;color:var(--heading);font-size:14px"><?= h($a['title']) ?></div>
          <div class="subtle" style="font-size:12.5px"><?= h($a['course_name']) ?> · กำหนดส่ง <?= h($a['due_date']) ?></div>
        </div>
        <?= icon('arrow-right', 16, 'var(--sub)') ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($items)): ?>
  <div class="empty" style="margin-top:1.5rem">
    <div class="e-ic"><?= icon('calendar', 30) ?></div>
    <h3>ยังไม่มีงานในปฏิทิน</h3>
    <p>เมื่อมีงานหรือการบ้านที่กำหนดส่ง จะปรากฏในปฏิทินนี้</p>
  </div>
  <?php endif; ?>
</div>
