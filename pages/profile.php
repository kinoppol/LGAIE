<?php
declare(strict_types=1);

ensure_storage_schema();
$user      = current_user();
$provinces = get_provinces();
?>

<style>
/* ── Profile page ─────────────────────────────────────────── */
.prof-wrap   { max-width: 660px; margin: 0 auto; }
.prof-card   { background: var(--card); border: 1px solid var(--line-2); border-radius: 16px;
               padding: 1.75rem 2rem; margin-bottom: 1.25rem; }

/* Avatar bar */
.prof-avatar-row { display: flex; align-items: center; gap: 16px;
                   padding-bottom: 1.5rem; margin-bottom: 1.5rem;
                   border-bottom: 1px solid var(--line-2); }

/* Editable avatar */
.avatar-edit { position: relative; cursor: pointer; flex: 0 0 auto;
               border-radius: 50%; display: inline-block; line-height: 0; }
.avatar-edit .avatar-cam { position: absolute; right: -2px; bottom: -2px;
               width: 26px; height: 26px; border-radius: 50%;
               background: var(--primary); border: 2px solid var(--card);
               display: grid; place-items: center; line-height: 0;
               transition: background .15s; }
.avatar-edit:hover .avatar-cam { background: var(--primary-600); }

/* Section label */
.prof-sec { font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: var(--sub);
            padding-bottom: .65rem; margin-bottom: 1.25rem;
            border-bottom: 1px solid var(--line-2); }

/* Field */
.pf          { margin-bottom: 1.1rem; }
.pf label    { display: block; font-size: .82rem; font-weight: 600;
               color: var(--heading); margin-bottom: .45rem; }
.pf label .opt { font-weight: 400; color: var(--sub); }
.pf .req     { color: #ef4444; margin-left: 2px; }
.pf input,
.pf select   { width: 100%; padding: .6rem 1rem; border: 1.5px solid var(--line-2);
               border-radius: 9px; background: var(--bg); color: var(--text);
               font-size: .9rem; box-sizing: border-box;
               transition: border-color .15s, box-shadow .15s; }
.pf input:focus,
.pf select:focus { outline: none; border-color: var(--primary);
                   box-shadow: 0 0 0 3px var(--primary-soft); }
.pf input:disabled { opacity: .55; cursor: not-allowed; background: var(--line-2); }
.pf-row      { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* Password eye toggle */
.pw-wrap     { position: relative; }
.pw-wrap input { padding-right: 46px; }
.pw-eye      { position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
               background: none; border: none; cursor: pointer;
               color: var(--sub); padding: 4px; line-height: 0; }
.pw-eye:hover { color: var(--heading); }

/* Strength bar */
.strength-bar { height: 4px; border-radius: 4px; background: var(--line-2);
                margin-top: 7px; overflow: hidden; }
.strength-fill { height: 100%; border-radius: 4px; width: 0; transition: all .25s; }
.strength-lbl  { font-size: .73rem; margin-top: 4px; color: var(--sub); min-height: 1em; }

/* Match label */
.match-lbl { font-size: .73rem; margin-top: 4px; min-height: 1em; }

/* Form footer */
.prof-foot { display: flex; justify-content: flex-end; padding-top: 1.25rem;
             margin-top: .5rem; border-top: 1px solid var(--line-2); }
</style>

<div class="prof-wrap">

  <!-- Page header -->
  <div style="margin-bottom:1.75rem">
    <h1 style="font-size:1.3rem;font-weight:800;color:var(--heading);margin:0 0 .3rem;display:flex;align-items:center;gap:8px">
      <?= icon('settings', 20, 'var(--primary)') ?> ตั้งค่าโปรไฟล์
    </h1>
    <p style="color:var(--sub);font-size:.875rem;margin:0">แก้ไขข้อมูลส่วนตัวและรหัสผ่านของบัญชีคุณ</p>
  </div>

  <!-- ── Card 1: Personal info ── -->
  <div class="prof-card">

    <form method="post" action="api/update_profile.php" enctype="multipart/form-data">

    <!-- Avatar + name -->
    <div class="prof-avatar-row">
      <label class="avatar-edit" title="คลิกเพื่อเปลี่ยนรูปประจำตัว">
        <span id="avatar-preview"><?= avatar($user, 72) ?></span>
        <span class="avatar-cam"><?= icon('camera', 16, '#fff') ?></span>
        <input type="file" name="avatar_image" id="avatar-input" accept="image/png,image/jpeg,image/gif,image/webp"
               hidden onchange="previewAvatar(this)">
      </label>
      <div>
        <div style="font-size:1.05rem;font-weight:700;color:var(--heading);margin-bottom:2px">
          <?= h($user['name'] ?? '') ?>
        </div>
        <div style="font-size:.83rem;color:var(--sub);margin-bottom:6px">
          <?= h($user['email'] ?? '') ?>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span class="badge <?= is_teacher() ? 'green' : 'blue' ?>" style="font-size:.73rem">
            <?= is_teacher() ? 'ครูผู้สอน' : 'นักเรียน' ?>
          </span>
          <button type="button" class="btn btn-sm btn-soft" onclick="document.getElementById('avatar-input').click()">
            <?= icon('camera', 14) ?> เปลี่ยนรูป
          </button>
          <button type="button" class="btn btn-sm btn-ghost" id="avatar-remove-btn"
                  style="<?= empty($user['avatar_path']) ? 'display:none' : '' ?>" onclick="removeAvatar()">
            <?= icon('trash', 14) ?> ลบรูป
          </button>
        </div>
        <div class="subtle" style="font-size:.72rem;margin-top:6px">รองรับ JPG, PNG, GIF, WEBP · ไม่เกิน 5 MB</div>
      </div>
    </div>
    <input type="hidden" name="remove_avatar" id="remove-avatar" value="0">

    <div class="prof-sec">ข้อมูลส่วนตัว</div>

      <div class="pf-row">
        <div class="pf">
          <label>ชื่อ-สกุล<span class="req">*</span></label>
          <input type="text" name="name" value="<?= h($user['name'] ?? '') ?>"
                 required autocomplete="name">
        </div>
        <div class="pf">
          <label>หมายเลขโทรศัพท์<span class="req">*</span></label>
          <input type="tel" name="phone" value="<?= h($user['phone'] ?? '') ?>"
                 required placeholder="08x-xxx-xxxx">
        </div>
      </div>

      <div class="pf">
        <label>อีเมล <span class="opt">(ไม่สามารถเปลี่ยนได้)</span></label>
        <input type="email" value="<?= h($user['email'] ?? '') ?>" disabled>
      </div>

      <div class="pf">
        <label>ชื่อสถานศึกษา<span class="req">*</span></label>
        <input type="text" name="school" value="<?= h($user['school'] ?? '') ?>"
               required placeholder="โรงเรียน / มหาวิทยาลัย / สถาบัน">
      </div>

      <div class="pf">
        <label>จังหวัดที่ตั้งของสถานศึกษา<span class="req">*</span></label>
        <select name="province" required>
          <option value="">— เลือกจังหวัด —</option>
          <?php foreach ($provinces as $p): ?>
          <option value="<?= h($p) ?>" <?= ($user['province'] ?? '') === $p ? 'selected' : '' ?>>
            <?= h($p) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="prof-foot">
        <button type="submit" class="btn btn-primary" style="gap:8px;min-width:140px;justify-content:center">
          <?= icon('check', 16, '#fff') ?> บันทึกข้อมูล
        </button>
      </div>

    </form>
  </div>

  <!-- ── Card 2: Change password ── -->
  <div class="prof-card">

    <div class="prof-sec">เปลี่ยนรหัสผ่าน</div>

    <form method="post" action="api/change_password.php">

      <div class="pf">
        <label>รหัสผ่านปัจจุบัน<span class="req">*</span></label>
        <div class="pw-wrap">
          <input type="password" name="current_password" id="pw-cur"
                 placeholder="รหัสผ่านที่ใช้อยู่" required autocomplete="current-password">
          <button type="button" class="pw-eye" onclick="togglePw('pw-cur',this)" title="แสดง/ซ่อน">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
              <path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="pf-row">
        <div class="pf">
          <label>รหัสผ่านใหม่<span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="new_password" id="pw-new"
                   placeholder="อย่างน้อย 6 ตัวอักษร" required autocomplete="new-password"
                   oninput="checkStrength(this.value)">
            <button type="button" class="pw-eye" onclick="togglePw('pw-new',this)" title="แสดง/ซ่อน">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                <path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <div class="strength-bar"><div class="strength-fill" id="pw-bar"></div></div>
          <div class="strength-lbl" id="pw-lbl"></div>
        </div>

        <div class="pf">
          <label>ยืนยันรหัสผ่านใหม่<span class="req">*</span></label>
          <div class="pw-wrap">
            <input type="password" name="confirm_password" id="pw-conf"
                   placeholder="พิมพ์อีกครั้ง" required autocomplete="new-password"
                   oninput="checkMatch()">
            <button type="button" class="pw-eye" onclick="togglePw('pw-conf',this)" title="แสดง/ซ่อน">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                <path d="M1 12S5 5 12 5s11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <div class="match-lbl" id="pw-match"></div>
        </div>
      </div>

      <div class="prof-foot">
        <button type="submit" class="btn btn-primary" style="gap:8px;min-width:160px;justify-content:center;background:#7c3aed">
          <?= icon('send', 16, '#fff') ?> เปลี่ยนรหัสผ่าน
        </button>
      </div>

    </form>
  </div>

</div><!-- /.prof-wrap -->

<script>
// Initials-only avatar to restore when the image is removed
var avatarFallback = <?= json_encode(avatar(['avatar_class' => $user['avatar_class'] ?? 'av-1', 'initials' => $user['initials'] ?? '?'], 72), JSON_UNESCAPED_UNICODE) ?>;

function previewAvatar(input) {
  var file = input.files && input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    showToast('ไฟล์รูปต้องไม่เกิน 5 MB', true);
    input.value = '';
    return;
  }
  var reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('avatar-preview').innerHTML =
      '<span class="avatar" style="width:72px;height:72px;overflow:hidden;background:var(--surface-2)">' +
      '<img src="' + e.target.result + '" alt="" style="width:100%;height:100%;object-fit:cover;display:block"></span>';
    document.getElementById('remove-avatar').value = '0';
    document.getElementById('avatar-remove-btn').style.display = '';
  };
  reader.readAsDataURL(file);
}

function removeAvatar() {
  document.getElementById('avatar-input').value = '';
  document.getElementById('remove-avatar').value = '1';
  document.getElementById('avatar-preview').innerHTML = avatarFallback;
  document.getElementById('avatar-remove-btn').style.display = 'none';
}

function togglePw(id, btn) {
  var inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.style.color = inp.type === 'text' ? 'var(--primary)' : '';
}

function checkStrength(val) {
  var bar = document.getElementById('pw-bar');
  var lbl = document.getElementById('pw-lbl');
  if (!bar) return;
  var s = 0;
  if (val.length >= 6)  s++;
  if (val.length >= 10) s++;
  if (/[A-Z]/.test(val) || /[ก-๙]/.test(val)) s++;
  if (/[0-9]/.test(val)) s++;
  if (/[^A-Za-z0-9ก-๙\s]/.test(val)) s++;
  var colors = ['','#ef4444','#f97316','#eab308','#22c55e','#15803d'];
  var labels = ['','อ่อนมาก','อ่อน','พอใช้','ดี','แข็งแกร่ง'];
  bar.style.width      = (s * 20) + '%';
  bar.style.background = colors[s] || '';
  lbl.textContent      = val.length ? labels[s] : '';
  lbl.style.color      = colors[s] || 'var(--sub)';
}

function checkMatch() {
  var a   = document.getElementById('pw-new').value;
  var b   = document.getElementById('pw-conf').value;
  var lbl = document.getElementById('pw-match');
  if (!b) { lbl.textContent = ''; return; }
  lbl.textContent = a === b ? '✓ รหัสผ่านตรงกัน' : '✗ รหัสผ่านไม่ตรงกัน';
  lbl.style.color = a === b ? '#22c55e' : '#ef4444';
}
</script>
