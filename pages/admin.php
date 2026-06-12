<?php
declare(strict_types=1);

require_admin();
ensure_storage_schema();

$tab = $_GET['tab'] ?? 'users';

// ── Global storage settings ────────────────────────────────────
$max_file_mb  = (int)get_setting('max_file_mb', '10');
$mat_quota_mb = (int)get_setting('course_materials_quota_mb', '1024');
$sub_quota_mb = (int)get_setting('course_submissions_quota_mb', '1024');
?>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
  <span style="width:42px;height:42px;border-radius:12px;background:var(--primary-soft);color:var(--primary);display:grid;place-items:center">
    <?= icon('shield', 22) ?>
  </span>
  <div>
    <h1 style="font-size:22px">ผู้ดูแลระบบ</h1>
    <p class="subtle" style="font-size:13px;margin:0">จัดการบัญชีครู/นักเรียน พื้นที่จัดเก็บไฟล์ และรายชื่อ AI</p>
  </div>
</div>

<div class="tabs" style="margin:18px 0 22px">
  <?php
  $tabs = [
      ['users',   'users',    'จัดการผู้ใช้'],
      ['storage', 'database', 'พื้นที่จัดเก็บไฟล์'],
      ['ai',      'sparkle',  'รายชื่อ AI'],
  ];
  foreach ($tabs as [$tid, $tic, $tlbl]):
  ?>
  <a href="<?= url('admin', ['tab' => $tid]) ?>" class="tab<?= $tab === $tid ? ' active' : '' ?>" style="text-decoration:none">
    <?= icon($tic, 17) ?> <?= $tlbl ?>
  </a>
  <?php endforeach; ?>
</div>

<?php
// ════════════════════════════════════════════════════════════════
// USERS tab
// ════════════════════════════════════════════════════════════════
if ($tab === 'users'):
    $role_filter = $_GET['role'] ?? 'all';
    $where  = "role != 'admin'";
    $params = [];
    if (in_array($role_filter, ['teacher', 'student'], true)) {
        $where   .= ' AND role = ?';
        $params[] = $role_filter;
    }
    $users         = db_rows("SELECT * FROM users WHERE {$where} ORDER BY role, id", $params);
    $teacher_count = (int)db_val("SELECT COUNT(*) FROM users WHERE role = 'teacher'");
    $student_count = (int)db_val("SELECT COUNT(*) FROM users WHERE role = 'student'");
?>

<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php
  $filters = [
      ['all',     'ทั้งหมด',  $teacher_count + $student_count],
      ['teacher', 'ครู',      $teacher_count],
      ['student', 'นักเรียน', $student_count],
  ];
  foreach ($filters as [$fid, $flbl, $fcnt]):
      $on = $role_filter === $fid;
  ?>
  <a href="<?= url('admin', ['tab' => 'users', 'role' => $fid]) ?>"
     class="btn btn-sm <?= $on ? 'btn-primary' : 'btn-ghost' ?>" style="text-decoration:none">
    <?= $flbl ?> <span class="badge <?= $on ? '' : 'gray' ?>" style="<?= $on ? 'background:rgba(255,255,255,.25);color:#fff' : '' ?>;font-size:11px"><?= $fcnt ?></span>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div style="padding:6px 10px">
    <?php foreach ($users as $u):
        $suspended = $u['status'] === 'suspended';
        $pending   = $u['status'] === 'pending';
    ?>
    <div style="display:flex;align-items:center;gap:13px;padding:12px;border-bottom:1px solid var(--line-1)<?= $suspended ? ';opacity:.55' : '' ?>">
      <?= avatar($u, 40) ?>
      <div style="min-width:0;flex:1 1 220px">
        <div style="font-weight:600;color:var(--heading);font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= h($u['name']) ?>
        </div>
        <div class="subtle" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= h($u['email'] ?? '—') ?><?= !empty($u['school']) ? ' · ' . h($u['school']) : '' ?>
        </div>
      </div>
      <span class="badge <?= $u['role'] === 'teacher' ? 'blue' : 'gray' ?>" style="font-size:11px;flex:0 0 auto">
        <?= $u['role'] === 'teacher' ? 'ครู' : 'นักเรียน' ?>
      </span>
      <span class="badge <?= $suspended ? 'orange' : ($pending ? 'gray' : 'green') ?>" style="font-size:11px;flex:0 0 auto">
        <?= $suspended ? 'ถูกระงับ' : ($pending ? 'รอยืนยัน' : 'ใช้งานได้') ?>
      </span>
      <div style="display:flex;gap:6px;flex:0 0 auto">
        <button class="btn btn-sm btn-ghost" title="รีเซ็ตรหัสผ่าน"
                onclick="openResetModal(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['name'])) ?>')">
          <?= icon('key', 14) ?> รีเซ็ตรหัสผ่าน
        </button>
        <button class="btn btn-sm btn-ghost" style="color:<?= $suspended ? 'var(--primary)' : 'var(--danger)' ?>"
                onclick="toggleUserStatus(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['name'])) ?>', '<?= $suspended ? 'active' : 'suspended' ?>')">
          <?= $suspended ? icon('check-circle', 14) . ' เปิดใช้งาน' : icon('lock', 14) . ' ระงับบัญชี' ?>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
    <p class="subtle" style="font-size:13.5px;padding:18px 12px">ไม่พบผู้ใช้</p>
    <?php endif; ?>
  </div>
</div>

<!-- Reset password modal -->
<?php modal_start('reset-pw', 'รีเซ็ตรหัสผ่าน', 'key'); ?>
<form id="reset-pw-form" onsubmit="submitResetPw(event)">
  <input type="hidden" name="user_id" id="rp-user-id">
  <p style="font-size:14px;color:var(--body);margin:0 0 14px">
    กำหนดรหัสผ่านใหม่ให้ <b id="rp-user-name" style="color:var(--heading)"></b>
  </p>
  <div class="field">
    <label>รหัสผ่านใหม่ <span style="color:var(--danger)">*</span> <span class="subtle" style="font-weight:400">(อย่างน้อย 6 ตัวอักษร)</span></label>
    <div style="display:flex;gap:8px">
      <input class="input" name="new_password" id="rp-password" minlength="6" required
             style="font-family:ui-monospace,monospace" autocomplete="off">
      <button type="button" class="btn btn-ghost" style="flex:0 0 auto" title="สุ่มรหัสผ่านใหม่"
              onclick="document.getElementById('rp-password').value = genPassword()">
        <?= icon('refresh', 15) ?> สุ่ม
      </button>
    </div>
    <div class="hint">แจ้งรหัสผ่านใหม่ให้ผู้ใช้ แล้วแนะนำให้เปลี่ยนเองในหน้าโปรไฟล์</div>
  </div>
  <div id="rp-result" style="display:none;margin-top:12px;padding:11px 14px;border-radius:9px;
       background:var(--primary-soft);color:var(--primary-700);font-size:13.5px"></div>
</form>
<?php modal_foot('reset-pw', 'ปิด', 'รีเซ็ตรหัสผ่าน'); ?>

<script>
function genPassword() {
  var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
  var out = '';
  var rnd = new Uint32Array(10);
  crypto.getRandomValues(rnd);
  for (var i = 0; i < 10; i++) out += chars[rnd[i] % chars.length];
  return out;
}

function openResetModal(id, name) {
  document.getElementById('rp-user-id').value = id;
  document.getElementById('rp-user-name').textContent = name;
  document.getElementById('rp-password').value = genPassword();
  var res = document.getElementById('rp-result');
  res.style.display = 'none';
  res.textContent = '';
  openModal('reset-pw');
}

function submitResetPw(e) {
  e.preventDefault();
  var form = document.getElementById('reset-pw-form');
  var fd = new FormData(form);
  fd.append('action', 'reset_password');
  fetch('api/admin_users.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      var box = document.getElementById('rp-result');
      if (res.ok) {
        box.style.display = 'block';
        box.innerHTML = '✓ ' + res.message + '<br>รหัสผ่านใหม่: <b style="font-family:ui-monospace,monospace;font-size:15px">'
          + document.getElementById('rp-password').value + '</b>';
        showToast(res.message);
      } else {
        showToast(res.error || 'เกิดข้อผิดพลาด', true);
      }
    })
    .catch(() => showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', true));
}

function toggleUserStatus(id, name, newStatus) {
  var msg = newStatus === 'suspended'
    ? 'ระงับบัญชี "' + name + '"? ผู้ใช้จะเข้าสู่ระบบไม่ได้จนกว่าจะเปิดใช้งานอีกครั้ง'
    : 'เปิดใช้งานบัญชี "' + name + '" อีกครั้ง?';
  if (!confirm(msg)) return;
  var fd = new FormData();
  fd.append('action', 'set_status');
  fd.append('user_id', id);
  fd.append('status', newStatus);
  fetch('api/admin_users.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) { showToast(res.message); setTimeout(() => location.reload(), 700); }
      else showToast(res.error || 'เกิดข้อผิดพลาด', true);
    })
    .catch(() => showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', true));
}
</script>

<?php
// ════════════════════════════════════════════════════════════════
// STORAGE tab
// ════════════════════════════════════════════════════════════════
elseif ($tab === 'storage'):
    $courses_all = db_rows('
        SELECT c.id, c.code, c.name, c.banner, c.materials_quota_mb, c.submissions_quota_mb,
               u.name AS teacher_name
        FROM courses c JOIN users u ON u.id = c.teacher_id
        ORDER BY c.id');
?>

<!-- Global settings -->
<div class="card" style="margin-bottom:20px">
  <div class="card-head"><?= icon('settings', 18, 'var(--primary)') ?><h3>ค่ากลางของระบบ</h3></div>
  <div class="card-pad" style="padding-top:12px">
    <form method="post" action="api/admin_settings.php" data-ajax>
      <input type="hidden" name="action" value="save_global">
      <div class="row wrap" style="gap:14px">
        <div class="field" style="flex:1 1 200px">
          <label>ขนาดสูงสุดต่อไฟล์ (MB)</label>
          <input class="input" type="number" name="max_file_mb" min="1" max="100" value="<?= $max_file_mb ?>" required>
          <div class="hint">ใช้กับไฟล์อัปโหลดทุกประเภทในระบบ (1–100 MB)</div>
        </div>
        <div class="field" style="flex:1 1 220px">
          <label>โควต้าไฟล์เนื้อหาต่อวิชา (MB)</label>
          <input class="input" type="number" name="course_materials_quota_mb" min="1" max="102400" value="<?= $mat_quota_mb ?>" required>
          <div class="hint">รวมไฟล์ประกอบบทเรียนทั้งวิชา (1024 = 1 GB)</div>
        </div>
        <div class="field" style="flex:1 1 220px">
          <label>โควต้าไฟล์งานส่งต่อวิชา (MB)</label>
          <input class="input" type="number" name="course_submissions_quota_mb" min="1" max="102400" value="<?= $sub_quota_mb ?>" required>
          <div class="hint">รวมไฟล์งาน/การบ้านที่นักเรียนส่งทั้งวิชา — คิดแยกจากไฟล์เนื้อหา</div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:4px">
        <?= icon('check', 16, '#fff') ?> บันทึกค่ากลาง
      </button>
    </form>
  </div>
</div>

<!-- Per-course usage + overrides -->
<div class="card">
  <div class="card-head">
    <?= icon('database', 18, 'var(--accent)') ?><h3>การใช้พื้นที่รายวิชา</h3>
    <span class="subtle" style="margin-left:auto;font-size:12px">เว้นว่าง = ใช้ค่ากลาง</span>
  </div>
  <div style="padding:10px 14px">
    <?php foreach ($courses_all as $cr):
        $cid       = (int)$cr['id'];
        $mat_used  = course_storage_used($cid, 'materials');
        $sub_used  = course_storage_used($cid, 'submissions');
        $mat_quota = course_quota_bytes($cid, 'materials');
        $sub_quota = course_quota_bytes($cid, 'submissions');
        $mat_pct   = min(100, (int)round($mat_used / $mat_quota * 100));
        $sub_pct   = min(100, (int)round($sub_used / $sub_quota * 100));
    ?>
    <div style="padding:14px 6px;border-bottom:1px solid var(--line-1)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">
        <span style="width:26px;height:26px;border-radius:7px;background:<?= h($cr['banner']) ?>;flex:0 0 auto"></span>
        <div style="min-width:0;flex:1">
          <span style="font-weight:700;color:var(--heading);font-size:14px"><?= h($cr['name']) ?></span>
          <span class="subtle" style="font-size:12px"> · <?= h($cr['code']) ?> · <?= h($cr['teacher_name']) ?></span>
        </div>
      </div>
      <div class="row wrap" style="gap:14px;align-items:flex-end">
        <div style="flex:1 1 230px">
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
            <span class="subtle">ไฟล์เนื้อหา</span>
            <span style="color:var(--body)"><?= format_bytes($mat_used) ?> / <?= format_bytes($mat_quota) ?></span>
          </div>
          <div class="progress"><span style="width:<?= $mat_pct ?>%<?= $mat_pct >= 90 ? ';background:var(--danger)' : '' ?>"></span></div>
        </div>
        <div style="flex:1 1 230px">
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
            <span class="subtle">ไฟล์งานส่ง</span>
            <span style="color:var(--body)"><?= format_bytes($sub_used) ?> / <?= format_bytes($sub_quota) ?></span>
          </div>
          <div class="progress"><span style="width:<?= $sub_pct ?>%<?= $sub_pct >= 90 ? ';background:var(--danger)' : '' ?>"></span></div>
        </div>
        <form method="post" action="api/admin_settings.php" data-ajax
              style="display:flex;gap:8px;align-items:flex-end;flex:0 0 auto">
          <input type="hidden" name="action" value="set_course_quota">
          <input type="hidden" name="course_id" value="<?= $cid ?>">
          <div class="field" style="margin:0">
            <label style="font-size:11px">โควต้าเนื้อหา (MB)</label>
            <input class="input" type="number" name="materials_quota_mb" min="1" max="102400"
                   value="<?= $cr['materials_quota_mb'] !== null ? (int)$cr['materials_quota_mb'] : '' ?>"
                   placeholder="<?= $mat_quota_mb ?>" style="width:120px;padding:8px 10px;font-size:13px">
          </div>
          <div class="field" style="margin:0">
            <label style="font-size:11px">โควต้างานส่ง (MB)</label>
            <input class="input" type="number" name="submissions_quota_mb" min="1" max="102400"
                   value="<?= $cr['submissions_quota_mb'] !== null ? (int)$cr['submissions_quota_mb'] : '' ?>"
                   placeholder="<?= $sub_quota_mb ?>" style="width:120px;padding:8px 10px;font-size:13px">
          </div>
          <button type="submit" class="btn btn-sm btn-soft" style="margin-bottom:1px">บันทึก</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($courses_all)): ?>
    <p class="subtle" style="font-size:13.5px;padding:14px 6px">ยังไม่มีรายวิชาในระบบ</p>
    <?php endif; ?>
  </div>
</div>

<?php
// ════════════════════════════════════════════════════════════════
// AI tab — manage the AI registry without reinstalling
// ════════════════════════════════════════════════════════════════
elseif ($tab === 'ai'):
    $ai_tools = array_values(get_ai_tools());
    // Usage count per AI (to gate deletion)
    $usage = [];
    foreach ($ai_tools as $t) {
        $id = $t['id'];
        $usage[$id] = (int)db_val('SELECT COUNT(*) FROM lesson_prompts     WHERE ai_id = ?',  [$id])
                    + (int)db_val('SELECT COUNT(*) FROM assignment_prompts WHERE ai_id = ?',  [$id])
                    + (int)db_val('SELECT COUNT(*) FROM submissions        WHERE ai_used = ?', [$id]);
    }
?>

<!-- Export / Import -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <a href="api/admin_ai.php?action=export" class="btn btn-sm btn-ghost" style="text-decoration:none">
    <?= icon('download', 15) ?> ส่งออก (JSON)
  </a>
  <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('ai-import-file').click()">
    <?= icon('upload', 15) ?> นำเข้า (JSON)
  </button>
  <input type="file" id="ai-import-file" accept="application/json,.json" hidden onchange="importAi(this)">
  <span class="subtle" style="font-size:12px;align-self:center">สำรอง/ย้ายรายชื่อ AI ระหว่างเซิร์ฟเวอร์ได้</span>
</div>

<!-- Add new AI -->
<div class="card" style="margin-bottom:20px">
  <div class="card-head"><?= icon('sparkle', 18, 'var(--primary)') ?><h3>เพิ่ม AI ใหม่</h3></div>
  <div class="card-pad" style="padding-top:12px">
    <form id="ai-add-form" onsubmit="saveAi(event, 'add')">
      <input type="hidden" name="mode" value="add">
      <div class="row wrap" style="gap:14px;align-items:flex-end">
        <div style="display:flex;align-items:center;gap:12px;flex:0 0 auto">
          <span class="ai-logo" id="ai-add-preview" style="background:#4d6bfe;width:46px;height:46px;font-size:15px">AI</span>
        </div>
        <div class="field" style="flex:1 1 130px;margin:0">
          <label>รหัส (slug) <span style="color:var(--danger)">*</span></label>
          <input class="input" name="id" required pattern="[A-Za-z0-9_-]{2,20}"
                 placeholder="เช่น kimi" style="font-family:ui-monospace,monospace"
                 oninput="this.value=this.value.toLowerCase()">
        </div>
        <div class="field" style="flex:1 1 160px;margin:0">
          <label>ชื่อ <span style="color:var(--danger)">*</span></label>
          <input class="input" name="name" required maxlength="50" placeholder="เช่น Kimi"
                 oninput="aiPreview('ai-add')">
        </div>
        <div class="field" style="flex:0 1 90px;margin:0">
          <label>โลโก้ย่อ <span style="color:var(--danger)">*</span></label>
          <input class="input" name="letter" id="ai-add-letter" required maxlength="5" placeholder="K"
                 oninput="aiPreview('ai-add')">
        </div>
        <div class="field" style="flex:0 1 80px;margin:0">
          <label>สี</label>
          <input class="input" type="color" name="color" id="ai-add-color" value="#4d6bfe"
                 style="padding:4px;height:40px" oninput="aiPreview('ai-add')">
        </div>
        <div class="field" style="flex:1 1 180px;margin:0">
          <label>URL <span style="color:var(--danger)">*</span></label>
          <input class="input" name="url" required maxlength="100" placeholder="kimi.com">
        </div>
        <button type="submit" class="btn btn-primary" style="flex:0 0 auto">
          <?= icon('check', 16, '#fff') ?> เพิ่ม
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Existing AIs -->
<div class="card">
  <div class="card-head">
    <?= icon('sparkle', 18, 'var(--accent)') ?><h3>AI ที่มีอยู่</h3>
    <span class="subtle" style="margin-left:auto;font-size:12px"><?= count($ai_tools) ?> รายการ</span>
  </div>
  <style>
    .ai-grid { --cols: 44px 96px minmax(130px,1fr) 64px 50px minmax(150px,1.4fr) auto;
               min-width: 760px; }
    .ai-grid-head, .ai-grid .ai-row {
        display: grid; grid-template-columns: var(--cols);
        gap: 10px; align-items: center; }
    .ai-grid-head { padding: 4px 4px 8px; font-size: 11px; font-weight: 700;
                    color: var(--sub); border-bottom: 1px solid var(--line-2); }
    .ai-grid .ai-row { padding: 11px 4px; border-bottom: 1px solid var(--line-1); }
    .ai-grid .ai-row:last-child { border-bottom: 0; }
    .ai-grid .input { padding: 8px 10px; font-size: 13px; }
    .ai-grid .ai-acts { display: flex; gap: 6px; }
  </style>
  <div style="padding:8px 14px;overflow-x:auto">
    <div class="ai-grid">
      <div class="ai-grid-head">
        <span></span><span>รหัส</span><span>ชื่อ</span><span>โลโก้</span>
        <span>สี</span><span>URL</span><span></span>
      </div>
      <?php foreach ($ai_tools as $t):
          $id  = $t['id'];
          $cnt = $usage[$id] ?? 0;
      ?>
      <form class="ai-row" onsubmit="saveAi(event, '<?= h($id) ?>')">
        <input type="hidden" name="mode" value="edit">
        <input type="hidden" name="id" value="<?= h($id) ?>">
        <span class="ai-logo" id="ai-<?= h($id) ?>-preview"
              style="background:<?= h($t['color']) ?>;width:42px;height:42px;font-size:14px"><?= h($t['letter']) ?></span>
        <input class="input" value="<?= h($id) ?>" disabled
               style="font-family:ui-monospace,monospace;padding:8px 10px;font-size:13px">
        <input class="input" name="name" value="<?= h($t['name']) ?>" required maxlength="50"
               oninput="aiPreview('ai-<?= h($id) ?>')">
        <input class="input" name="letter" id="ai-<?= h($id) ?>-letter" value="<?= h($t['letter']) ?>"
               required maxlength="5" oninput="aiPreview('ai-<?= h($id) ?>')">
        <input class="input" type="color" name="color" id="ai-<?= h($id) ?>-color" value="<?= h($t['color']) ?>"
               style="padding:3px;height:37px" oninput="aiPreview('ai-<?= h($id) ?>')">
        <input class="input" name="url" value="<?= h($t['url']) ?>" required maxlength="100">
        <div class="ai-acts">
          <button type="submit" class="btn btn-sm btn-soft">บันทึก</button>
          <button type="button" class="btn btn-sm btn-ghost" style="color:var(--danger)"
                  title="<?= $cnt > 0 ? 'มีการใช้งานอยู่ ' . $cnt . ' รายการ' : 'ลบ' ?>"
                  <?= $cnt > 0 ? 'disabled' : '' ?>
                  onclick="deleteAi('<?= h($id) ?>', '<?= h(addslashes($t['name'])) ?>')">
            <?= icon('trash', 14) ?><?= $cnt > 0 ? ' ' . $cnt : '' ?>
          </button>
        </div>
      </form>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
// Live logo preview from a form's name/letter/color fields
function aiPreview(prefix) {
  var letter = document.getElementById(prefix + '-letter');
  var color  = document.getElementById(prefix + '-color');
  var prev   = document.getElementById(prefix + '-preview');
  if (!prev) return;
  if (letter && letter.value) prev.textContent = letter.value;
  if (color)  prev.style.background = color.value;
}

function saveAi(e, key) {
  e.preventDefault();
  var fd = new FormData(e.target);
  fd.append('action', 'save');
  fetch('api/admin_ai.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) { showToast(res.message); setTimeout(() => location.reload(), 700); }
      else showToast(res.error || 'เกิดข้อผิดพลาด', true);
    })
    .catch(() => showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', true));
}

function importAi(input) {
  var file = input.files && input.files[0];
  if (!file) return;
  var fd = new FormData();
  fd.append('action', 'import');
  fd.append('file', file);
  fetch('api/admin_ai.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) { showToast(res.message); setTimeout(() => location.reload(), 1200); }
      else showToast(res.error || 'นำเข้าไม่สำเร็จ', true);
    })
    .catch(() => showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', true))
    .finally(() => { input.value = ''; });
}

function deleteAi(id, name) {
  if (!confirm('ลบ AI "' + name + '" ออกจากระบบ?')) return;
  var fd = new FormData();
  fd.append('action', 'delete');
  fd.append('id', id);
  fetch('api/admin_ai.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) { showToast(res.message); setTimeout(() => location.reload(), 700); }
      else showToast(res.error || 'เกิดข้อผิดพลาด', true);
    })
    .catch(() => showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', true));
}
</script>

<?php endif; ?>
