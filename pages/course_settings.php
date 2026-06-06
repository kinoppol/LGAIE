<?php
declare(strict_types=1);

$course_id = (int)($_GET['course_id'] ?? 0);
$c = db_row('SELECT * FROM courses WHERE id = ?', [$course_id]);
if (!$c) { echo '<div class="empty"><h3>ไม่พบรายวิชา</h3></div>'; return; }

// เฉพาะเจ้าของเท่านั้น
if (!is_teacher() || (int)$c['teacher_id'] !== current_user_id()) {
    echo '<div class="empty"><h3>ไม่มีสิทธิ์เข้าถึงหน้านี้</h3></div>';
    return;
}

$derived_count = (int)db_val('SELECT COUNT(*) FROM courses WHERE template_id = ?', [$course_id]);
$can_delete    = !(!empty($c['is_template']) && $derived_count > 0);

$lesson_count  = (int)db_val('SELECT COUNT(*) FROM lessons     WHERE course_id = ?', [$course_id]);
$assign_count  = (int)db_val('SELECT COUNT(*) FROM assignments WHERE course_id = ?', [$course_id]);
$student_count = (int)db_val('SELECT COUNT(*) FROM course_enrollments WHERE course_id = ?', [$course_id]);
?>

<style>
.cs-wrap  { max-width:680px; margin:0 auto; }
.cs-card  { background:var(--card); border:1px solid var(--line-2); border-radius:16px;
            padding:1.75rem 2rem; margin-bottom:1.25rem; }
.cs-card h2 { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
              color:var(--sub); padding-bottom:.65rem; margin:0 0 1.25rem;
              border-bottom:1px solid var(--line-2); }
.cs-field { margin-bottom:1rem; }
.cs-field label { display:block; font-size:.82rem; font-weight:600; color:var(--heading); margin-bottom:.42rem; }
.cs-field label .opt { font-weight:400; color:var(--sub); }
.cs-field .req { color:#ef4444; margin-left:2px; }
.cs-field input,.cs-field select {
  width:100%; padding:.6rem 1rem; border:1.5px solid var(--line-2); border-radius:9px;
  background:var(--bg); color:var(--text); font-size:.9rem; box-sizing:border-box;
  transition:border-color .15s,box-shadow .15s; }
.cs-field input:focus,.cs-field select:focus {
  outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-soft); }
.cs-2col { display:grid; grid-template-columns:1fr 2fr; gap:1rem; }
.cs-foot { display:flex; justify-content:flex-end; padding-top:1.25rem;
           margin-top:.25rem; border-top:1px solid var(--line-2); }
.toggle-row { display:flex; align-items:center; justify-content:space-between;
              padding:.75rem 1rem; border:1.5px solid var(--line-2); border-radius:9px;
              background:var(--bg); margin-bottom:.85rem; cursor:pointer; gap:12px; }
.toggle-row:hover { border-color:var(--primary); }
.toggle-row input[type=checkbox] { width:18px; height:18px; accent-color:var(--primary); flex:0 0 auto; }
.toggle-info strong { font-size:.875rem; font-weight:600; color:var(--heading); }
.toggle-info small  { display:block; font-size:.77rem; color:var(--sub); margin-top:2px; }

/* secret code box */
.secret-box { background:var(--primary-soft); border:1px solid var(--primary-soft-2,var(--line-2));
              border-radius:10px; padding:.85rem 1rem; display:flex; align-items:center;
              gap:12px; margin-bottom:.85rem; }
.secret-code { font-family:ui-monospace,monospace; font-size:1.1rem; font-weight:700;
               color:var(--primary); letter-spacing:.12em; flex:1; }

/* Danger zone */
.danger-card { background:var(--card); border:1.5px solid #fca5a5; border-radius:16px;
               padding:1.75rem 2rem; }
.danger-card h2 { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
                  color:#ef4444; padding-bottom:.65rem; margin:0 0 1.25rem;
                  border-bottom:1px solid #fca5a5; }
.stat-row { display:flex; gap:1.5rem; margin-bottom:1rem; flex-wrap:wrap; }
.stat-chip { display:flex; align-items:center; gap:6px; font-size:.85rem; color:var(--sub); }
.stat-chip strong { color:var(--heading); }
</style>

<div class="cs-wrap">

  <!-- Breadcrumb -->
  <div class="breadcrumb" style="margin-bottom:1.5rem">
    <a href="<?= url('courses') ?>">รายวิชา</a>
    <?= icon('chevron-right', 14) ?>
    <a href="<?= url('course', ['course_id' => $course_id, 'tab' => 'stream']) ?>"><?= h($c['name']) ?></a>
    <?= icon('chevron-right', 14) ?>
    <span style="color:var(--body);font-weight:600">ตั้งค่ารายวิชา</span>
  </div>

  <!-- Page title -->
  <div style="margin-bottom:1.75rem">
    <h1 style="font-size:1.3rem;font-weight:800;color:var(--heading);margin:0 0 .3rem;display:flex;align-items:center;gap:8px">
      <?= icon('settings', 20, 'var(--primary)') ?> ตั้งค่ารายวิชา
    </h1>
    <p style="color:var(--sub);font-size:.875rem;margin:0">แก้ไขข้อมูลและการตั้งค่าของรายวิชา <?= h($c['name']) ?></p>
  </div>

  <!-- ── Card 1: ข้อมูลพื้นฐาน ── -->
  <div class="cs-card">
    <h2><?= icon('edit', 13, 'var(--sub)') ?> ข้อมูลพื้นฐาน</h2>

    <form method="post" action="api/update_course.php" data-ajax>
      <input type="hidden" name="course_id" value="<?= $course_id ?>">

      <div class="cs-2col">
        <div class="cs-field">
          <label>รหัสวิชา<span class="req">*</span></label>
          <input type="text" name="code" value="<?= h($c['code']) ?>" required
                 style="text-transform:uppercase;font-weight:600;letter-spacing:.04em">
        </div>
        <div class="cs-field">
          <label>ชื่อรายวิชา<span class="req">*</span></label>
          <input type="text" name="name" value="<?= h($c['name']) ?>" required>
        </div>
      </div>

      <div class="cs-field">
        <label>กลุ่ม / ห้อง / ภาคเรียน<span class="req">*</span></label>
        <input type="text" name="section" value="<?= h($c['section']) ?>" required>
      </div>

      <div style="margin-bottom:1rem">
        <label class="cs-field" style="margin:0">
          <span style="font-size:.82rem;font-weight:600;color:var(--heading);display:block;margin-bottom:.65rem">
            การตั้งค่าการเข้าถึง
          </span>
        </label>

        <label class="toggle-row" for="chk-public">
          <input type="checkbox" id="chk-public" name="is_public" value="1"
                 <?= !empty($c['is_public']) ? 'checked' : '' ?>>
          <div class="toggle-info">
            <strong>รายวิชาสาธารณะ</strong>
            <small>บุคคลทั่วไปสามารถลงทะเบียนเรียนได้โดยไม่ต้องรับเชิญ</small>
          </div>
        </label>

        <?php if (empty($c['template_id'])): // รายวิชาที่สร้างจาก template ตั้งเป็น template ไม่ได้ ?>
        <label class="toggle-row" for="chk-template">
          <input type="checkbox" id="chk-template" name="is_template" value="1"
                 <?= !empty($c['is_template']) ? 'checked' : '' ?>
                 <?= ($derived_count > 0) ? 'disabled title="มีรายวิชาที่ใช้ต้นแบบนี้อยู่ ไม่สามารถยกเลิกได้"' : '' ?>
                 onchange="document.getElementById('secret-section').style.display=this.checked?'flex':'none'">
          <div class="toggle-info">
            <strong>กำหนดเป็นรายวิชาต้นแบบ</strong>
            <small>ครูคนอื่นสามารถใช้รหัสลับสร้างรายวิชาจากต้นแบบนี้ได้</small>
          </div>
        </label>
        <?php else: ?>
        <div class="toggle-row" style="opacity:.5;cursor:not-allowed">
          <input type="checkbox" disabled>
          <div class="toggle-info">
            <strong>กำหนดเป็นรายวิชาต้นแบบ</strong>
            <small>ไม่สามารถตั้งได้ — รายวิชานี้สร้างจากต้นแบบของคนอื่น</small>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Template secret code (shown when is_template checked) -->
      <?php if (!empty($c['is_template']) && $c['template_secret']): ?>
      <div id="secret-section" style="display:flex" class="secret-box">
        <?= icon('flag', 18, 'var(--primary)') ?>
        <div style="flex:1;min-width:0">
          <div style="font-size:.78rem;font-weight:700;color:var(--primary);margin-bottom:4px">รหัสลับสำหรับครูที่ต้องการใช้ต้นแบบนี้</div>
          <div style="display:flex;align-items:center;gap:10px">
            <span class="secret-code"><?= h($c['template_secret']) ?></span>
            <button type="button" class="btn btn-sm btn-ghost copy-btn"
                    data-copy="<?= h($c['template_secret']) ?>"
                    style="flex:0 0 auto">
              <?= icon('copy', 14) ?> <span>คัดลอก</span>
            </button>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div id="secret-section" style="display:none" class="secret-box">
        <?= icon('flag', 18, 'var(--primary)') ?>
        <div style="font-size:.82rem;color:var(--primary)">รหัสลับจะถูกสร้างอัตโนมัติเมื่อบันทึก</div>
      </div>
      <?php endif; ?>

      <div class="cs-foot">
        <button type="submit" class="btn btn-primary" style="gap:8px;min-width:140px;justify-content:center">
          <?= icon('check', 16, '#fff') ?> บันทึกการเปลี่ยนแปลง
        </button>
      </div>
    </form>
  </div>

  <!-- ── Card 1b: สีและพื้นหลัง (Collapsible) ── -->
  <?php
  preg_match_all('/#([0-9a-fA-F]{3,6})/', $c['banner'], $allm);
  $banner_c1 = $allm[0][0] ?? '#cdeee2';
  $banner_c2 = $allm[0][1] ?? '#e7f7f1';
  $palettes = [
    ['linear-gradient(120deg,#cdeee2,#e7f7f1)', '#0c7a5e', '#2bb393', 'เขียวมรกต'],
    ['linear-gradient(120deg,#d8e3fd,#ecf1ff)', '#3257c7', '#6b8efb', 'น้ำเงินฟ้า'],
    ['linear-gradient(120deg,#e8dcfb,#f4edff)', '#7140cf', '#a585f2', 'ม่วงลาเวนเดอร์'],
    ['linear-gradient(120deg,#ffe6cc,#fff2e2)', '#bd741a', '#f0a44e', 'ส้มอุ่น'],
    ['linear-gradient(120deg,#fce4ec,#fff0f3)', '#c0394d', '#f07189', 'ชมพูกุหลาบ'],
    ['linear-gradient(120deg,#e0f2fe,#f0f9ff)', '#0369a1', '#38bdf8', 'ฟ้าน้ำทะเล'],
    ['linear-gradient(120deg,#fef9c3,#fefce8)', '#854d0e', '#ca8a04', 'เหลืองทอง'],
    ['linear-gradient(120deg,#f0fdf4,#dcfce7)', '#15803d', '#22c55e', 'เขียวมิ้นต์'],
    ['linear-gradient(120deg,#fdf4ff,#fae8ff)', '#86198f', '#d946ef', 'ม่วงสด'],
    ['linear-gradient(120deg,#fff1f2,#ffe4e6)', '#9f1239', '#f43f5e', 'แดงกุหลาบ'],
    ['linear-gradient(120deg,#ecfdf5,#d1fae5)', '#065f46', '#10b981', 'เขียวมิ้นต์เข้ม'],
    ['linear-gradient(120deg,#eff6ff,#dbeafe)', '#1e40af', '#3b82f6', 'น้ำเงินเข้ม'],
  ];
  ?>

  <div class="cs-card" style="padding:0;overflow:hidden">
    <!-- Header (toggle) -->
    <button type="button" onclick="toggleColorPanel(this)"
            style="width:100%;display:flex;align-items:center;gap:10px;padding:1.2rem 2rem;
                   background:none;border:none;cursor:pointer;text-align:left">
      <!-- Color preview dot -->
      <span style="width:32px;height:22px;border-radius:6px;flex:0 0 auto;
                   background:<?= h($c['banner']) ?>;border:1px solid var(--line-2)"></span>
      <span style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--sub)">
        สีและภาพพื้นหลัง
      </span>
      <span style="margin-left:auto;color:var(--sub);font-size:.8rem;display:flex;align-items:center;gap:5px">
        <span id="color-panel-lbl">ขยายเพื่อแก้ไข</span>
        <svg id="color-panel-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
             style="transition:transform .25s"><path d="M6 9l6 6 6-6"/></svg>
      </span>
    </button>

    <!-- Collapsible body -->
    <div id="color-panel-body" style="display:none;padding:0 2rem 1.75rem;border-top:1px solid var(--line-2)">

      <form method="post" action="api/update_course.php" data-ajax id="color-form" style="padding-top:1.25rem">
        <input type="hidden" name="course_id"     value="<?= $course_id ?>">
        <input type="hidden" name="update_type"   value="color">
        <input type="hidden" name="banner"        id="inp-banner"  value="<?= h($c['banner']) ?>">
        <input type="hidden" name="ink_color"     id="inp-ink"     value="<?= h($c['ink_color']) ?>">
        <input type="hidden" name="primary_color" id="inp-primary" value="<?= h($c['primary_color']) ?>">

        <!-- Live preview -->
        <div style="margin-bottom:1.1rem">
          <div style="font-size:.8rem;font-weight:600;color:var(--heading);margin-bottom:.45rem">ตัวอย่าง</div>
          <div id="banner-preview"
               style="background:<?= h($c['banner']) ?>;border-radius:10px;padding:16px 20px;
                      color:<?= h($c['ink_color']) ?>;position:relative;overflow:hidden;
                      transition:background .25s,color .25s;min-height:80px">
            <div style="position:absolute;right:-12px;top:-16px;width:90px;height:90px;
                        border-radius:50%;background:rgba(255,255,255,.3)"></div>
            <span class="badge" id="preview-badge"
                  style="background:rgba(255,255,255,.6);color:<?= h($c['ink_color']) ?>;
                         font-size:10px;margin-bottom:5px;display:inline-block">
              <?= h($c['code']) ?>
            </span>
            <div style="font-size:1rem;font-weight:800"><?= h($c['name']) ?></div>
            <div style="font-size:.78rem;opacity:.8;margin-top:2px"><?= h($c['section']) ?></div>
            <span id="preview-av" style="position:absolute;right:16px;bottom:12px">
              <?= avatar(current_user(), 38) ?>
            </span>
          </div>
        </div>

        <!-- Preset palette -->
        <div style="font-size:.8rem;font-weight:600;color:var(--heading);margin-bottom:.5rem">สีสำเร็จรูป</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(52px,1fr));gap:7px;margin-bottom:1.1rem">
          <?php foreach ($palettes as [$bg, $ink, $pri, $lbl]): ?>
          <button type="button" title="<?= h($lbl) ?>"
                  onclick="applyPalette(<?= h(json_encode([$bg,$ink,$pri])) ?>)"
                  style="height:40px;border-radius:8px;background:<?= h($bg) ?>;
                         border:2.5px solid <?= $c['banner'] === $bg ? $ink : 'transparent' ?>;
                         cursor:pointer;position:relative;transition:transform .1s,border-color .15s"
                  onmouseenter="this.style.transform='scale(1.07)'"
                  onmouseleave="this.style.transform='scale(1)'">
            <?php if ($c['banner'] === $bg): ?>
            <span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="<?= h($ink) ?>"
                   stroke-width="2.5" stroke-linecap="round"><path d="M5 12.5 10 17.5 19.5 6.5"/></svg>
            </span>
            <?php endif; ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Custom pickers — 2 rows of 2 -->
        <div style="font-size:.8rem;font-weight:600;color:var(--heading);margin-bottom:.5rem">ปรับแต่งเอง</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.1rem">
          <?php
          $clr_fields = [
            ['clr-start',   $banner_c1,         'สีพื้นหลัง (เริ่ม)'],
            ['clr-end',     $banner_c2,         'สีพื้นหลัง (จบ)'],
            ['clr-ink',     $c['ink_color'],    'สีตัวอักษร'],
            ['clr-primary', $c['primary_color'],'สีหลักรายวิชา'],
          ];
          foreach ($clr_fields as [$cid, $cval, $clbl]):
          ?>
          <div>
            <label style="display:block;font-size:.75rem;color:var(--sub);margin-bottom:.3rem"><?= $clbl ?></label>
            <div style="display:flex;align-items:center;gap:6px">
              <input type="color" id="<?= $cid ?>" value="<?= h($cval) ?>"
                     oninput="updateCustomBanner()"
                     style="width:36px;height:34px;border-radius:7px;border:1.5px solid var(--line-2);
                            padding:2px;cursor:pointer;flex:0 0 auto;background:var(--bg)">
              <input type="text" id="<?= $cid ?>-txt" value="<?= h($cval) ?>"
                     oninput="syncColorFromText('<?= $cid ?>','<?= $cid ?>-txt');updateCustomBanner()"
                     style="flex:1;min-width:0;padding:.38rem .6rem;border:1.5px solid var(--line-2);
                            border-radius:7px;font-size:.78rem;font-family:monospace;
                            background:var(--bg);color:var(--text)">
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div style="display:flex;justify-content:flex-end">
          <button type="submit" class="btn btn-primary" style="gap:8px">
            <?= icon('check', 15, '#fff') ?> บันทึกสีและพื้นหลัง
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Card 2: สมาชิก (ข้อมูลสรุป) ── -->
  <div class="cs-card" style="padding:1.25rem 2rem">
    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
      <?= avatar(current_user(), 44) ?>
      <div style="flex:1">
        <div style="font-weight:700;color:var(--heading)"><?= h($c['name']) ?></div>
        <div style="font-size:.82rem;color:var(--sub)"><?= h($c['code']) ?> · <?= h($c['section']) ?></div>
      </div>
      <div class="stat-row" style="margin:0">
        <div class="stat-chip"><?= icon('book', 15) ?> <strong><?= $lesson_count ?></strong> บทเรียน</div>
        <div class="stat-chip"><?= icon('clipboard', 15) ?> <strong><?= $assign_count ?></strong> งาน</div>
        <div class="stat-chip"><?= icon('users', 15) ?> <strong><?= $student_count ?></strong> นักเรียน</div>
      </div>
    </div>
  </div>

  <!-- ── Archive ── -->
  <div class="cs-card" style="border-color:<?= !empty($c['is_archived']) ? '#fcd34d' : 'var(--line-2)' ?>;
       <?= !empty($c['is_archived']) ? 'background:rgba(253,224,71,.05)' : '' ?>">
    <h2 style="color:<?= !empty($c['is_archived']) ? '#92400e' : 'var(--sub)' ?>">
      <?= icon('folder', 13, !empty($c['is_archived']) ? '#92400e' : 'var(--sub)') ?> จัดเก็บรายวิชา
    </h2>

    <?php if (!empty($c['is_archived'])): ?>
    <!-- Currently archived -->
    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:.85rem 1rem;margin-bottom:1.25rem;
                display:flex;align-items:center;gap:10px">
      <?= icon('folder', 18, '#92400e') ?>
      <div>
        <div style="font-size:.875rem;font-weight:600;color:#92400e">รายวิชานี้อยู่ในสถานะจัดเก็บแล้ว</div>
        <div style="font-size:.78rem;color:#b45309;margin-top:2px">
          จัดเก็บเมื่อ <?= $c['archived_at'] ? date('d M Y H:i', strtotime($c['archived_at'])) : '—' ?>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end">
      <button class="btn btn-primary" style="gap:7px" onclick="doArchive('restore')">
        <?= icon('arrow-right', 16, '#fff') ?> นำกลับมาใช้งาน
      </button>
    </div>

    <?php else: ?>
    <!-- Not archived -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
      <div style="flex:1">
        <div style="font-weight:600;color:var(--heading);margin-bottom:.25rem">จัดเก็บรายวิชาชั่วคราว</div>
        <div style="font-size:.83rem;color:var(--sub);line-height:1.6">
          รายวิชาจะถูกซ่อนออกจากรายการหลัก แต่ยังคงข้อมูลบทเรียน งาน และประวัติการส่งงานไว้ครบถ้วน
          สามารถนำกลับมาใช้งานได้ทุกเมื่อ
        </div>
      </div>
      <button class="btn btn-ghost" style="gap:7px;white-space:nowrap;color:#92400e;border-color:#fcd34d;flex:0 0 auto"
              onclick="doArchive('archive')">
        <?= icon('folder', 15, '#92400e') ?> จัดเก็บรายวิชา
      </button>
    </div>
    <?php endif; ?>

<script>
function doArchive(action) {
  var btn = event.currentTarget;
  btn.disabled = true;
  btn.style.opacity = '.6';

  var body = new FormData();
  body.append('course_id', '<?= $course_id ?>');
  body.append('action', action);

  fetch('api/archive_course.php', { method: 'POST', body: body })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.ok) {
        showToast(res.message || 'ดำเนินการเรียบร้อย');
        setTimeout(function() { location.reload(); }, 900);
      } else {
        showToast(res.error || 'เกิดข้อผิดพลาด', true);
        btn.disabled = false;
        btn.style.opacity = '1';
      }
    })
    .catch(function(err) {
      showToast('เกิดข้อผิดพลาด: ' + err.message, true);
      btn.disabled = false;
      btn.style.opacity = '1';
    });
}
</script>
  </div>

  <!-- ── Danger Zone ── -->
  <div class="danger-card">
    <h2><?= icon('flag', 13, '#ef4444') ?> Danger Zone</h2>

    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
      <div>
        <div style="font-weight:600;color:var(--heading);margin-bottom:.25rem">ลบรายวิชานี้ถาวร</div>
        <div style="font-size:.83rem;color:var(--sub)">
          ลบบทเรียน <?= $lesson_count ?> รายการ งาน <?= $assign_count ?> รายการ และข้อมูลนักเรียน <?= $student_count ?> คน
          <?php if (!empty($c['is_template']) && $derived_count > 0): ?>
          <br><strong style="color:#ef4444">⚠ ลบไม่ได้ — มีรายวิชาที่ใช้ต้นแบบนี้อยู่ <?= $derived_count ?> รายวิชา</strong>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($can_delete): ?>
      <button class="btn" onclick="openModal('delete-course')"
              style="background:#fef2f2;color:#ef4444;border:1.5px solid #fca5a5;
                     font-weight:600;gap:7px;white-space:nowrap">
        <?= icon('x', 15, '#ef4444') ?> ลบรายวิชา
      </button>
      <?php else: ?>
      <button class="btn" disabled
              style="background:var(--bg);color:var(--sub);border:1px solid var(--line-2);
                     cursor:not-allowed;opacity:.55;gap:7px">
        <?= icon('x', 15) ?> ลบรายวิชา
      </button>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /.cs-wrap -->

<!-- ── Delete Confirmation Modal ── -->
<?php if ($can_delete): ?>
<div class="modal-overlay" id="delete-course-overlay" style="display:none"
     onclick="closeModalOnBg(event,'delete-course')">
  <div class="modal" style="max-width:420px">
    <div class="modal__head">
      <span style="width:38px;height:38px;border-radius:10px;background:#fee2e2;color:#ef4444;
                   display:grid;place-items:center;flex:0 0 auto">
        <?= icon('x', 20, '#ef4444') ?>
      </span>
      <h3>ยืนยันการลบรายวิชา</h3>
      <button type="button" class="x-btn" onclick="closeModal('delete-course')"><?= icon('x', 18) ?></button>
    </div>
    <div class="modal__body">
      <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;
                  padding:.9rem 1rem;margin-bottom:1rem">
        <div style="font-weight:700;color:#991b1b;margin-bottom:.3rem;display:flex;align-items:center;gap:7px">
          <?= icon('flag', 15, '#991b1b') ?> คำเตือน — ไม่สามารถย้อนกลับได้
        </div>
        <div style="font-size:.85rem;color:#991b1b;line-height:1.6">
          การลบจะลบบทเรียน งาน การส่งงาน และข้อมูลนักเรียนทั้งหมดถาวร
        </div>
      </div>

      <div style="background:var(--bg);border:1px solid var(--line-2);border-radius:10px;
                  padding:.85rem 1rem;margin-bottom:1rem;font-size:.875rem">
        <div style="font-weight:700;color:var(--heading)"><?= h($c['name']) ?></div>
        <div style="color:var(--sub);margin-top:3px"><?= h($c['code']) ?> · <?= h($c['section']) ?></div>
        <div style="color:var(--sub);margin-top:6px;display:flex;gap:12px">
          <span><?= icon('book', 13, 'var(--sub)') ?> <?= $lesson_count ?> บทเรียน</span>
          <span><?= icon('clipboard', 13, 'var(--sub)') ?> <?= $assign_count ?> งาน</span>
          <span><?= icon('users', 13, 'var(--sub)') ?> <?= $student_count ?> นักเรียน</span>
        </div>
      </div>

      <div style="font-size:.85rem;color:var(--sub);margin-bottom:.5rem">
        พิมพ์ <strong style="color:var(--heading);font-family:monospace"><?= h($c['code']) ?></strong> เพื่อยืนยัน:
      </div>
      <input type="text" id="del-confirm" class="input"
             placeholder="<?= h($c['code']) ?>" oninput="checkDel(this.value)">
    </div>
    <div class="modal__foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('delete-course')">ยกเลิก</button>
      <form method="post" action="api/delete_course.php" id="del-form" style="display:contents">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <button type="button" id="del-btn" disabled
                style="background:#ef4444;color:#fff;padding:.55rem 1.25rem;border-radius:9px;
                       border:none;font-weight:600;opacity:.4;cursor:not-allowed;
                       display:flex;align-items:center;gap:7px;font-size:.875rem"
                onclick="submitDelete()">
          <?= icon('x', 15, '#fff') ?> ลบถาวร
        </button>
      </form>
    </div>
  </div>
</div>
<script>
const DEL_CODE = '<?= addslashes($c['code']) ?>';
function checkDel(v) {
  var btn = document.getElementById('del-btn');
  var ok  = v === DEL_CODE;
  btn.disabled         = !ok;
  btn.style.opacity    = ok ? '1' : '.4';
  btn.style.cursor     = ok ? 'pointer' : 'not-allowed';
}
function submitDelete() {
  if (document.getElementById('del-confirm').value !== DEL_CODE) return;
  var data = new FormData(document.getElementById('del-form'));
  fetch('api/delete_course.php', { method:'POST', body:data })
    .then(r => r.json())
    .then(res => {
      if (res.ok) { window.location.href = '<?= url('courses') ?>'; }
      else { alert(res.error || 'เกิดข้อผิดพลาด'); }
    })
    .catch(() => alert('ไม่สามารถเชื่อมต่อได้'));
}
</script>
<?php endif; ?>

<script>
// ── Toggle color panel ────────────────────────────────────
function toggleColorPanel(btn) {
  var body    = document.getElementById('color-panel-body');
  var lbl     = document.getElementById('color-panel-lbl');
  var chevron = document.getElementById('color-panel-chevron');
  var open    = body.style.display !== 'none';
  body.style.display    = open ? 'none' : 'block';
  lbl.textContent       = open ? 'ขยายเพื่อแก้ไข' : 'ยุบ';
  chevron.style.transform = open ? '' : 'rotate(180deg)';
}

// ── Color settings ────────────────────────────────────────
function applyPalette(data) {
  var banner  = data[0], ink = data[1], primary = data[2];
  document.getElementById('inp-banner').value  = banner;
  document.getElementById('inp-ink').value     = ink;
  document.getElementById('inp-primary').value = primary;

  // Update preview
  var preview = document.getElementById('banner-preview');
  preview.style.background = banner;
  preview.style.color      = ink;
  document.getElementById('preview-badge').style.color = ink;
  // preview-av is now a real avatar — no color update needed

  // Parse gradient colors into pickers
  var matches = banner.match(/#[0-9a-fA-F]{3,6}/g) || [];
  if (matches[0]) { setColor('clr-start', matches[0]); }
  if (matches[1]) { setColor('clr-end',   matches[1]); }
  setColor('clr-ink',     ink);
  setColor('clr-primary', primary);
}

function setColor(id, hex) {
  var el = document.getElementById(id);
  var tx = document.getElementById(id + '-txt');
  if (el) el.value = hex;
  if (tx) tx.value = hex;
}

function syncColorFromText(colorId, textId) {
  var txt = document.getElementById(textId).value.trim();
  if (/^#[0-9a-fA-F]{6}$/.test(txt)) {
    document.getElementById(colorId).value = txt;
  }
}

function updateCustomBanner() {
  var c1  = document.getElementById('clr-start').value;
  var c2  = document.getElementById('clr-end').value;
  var ink = document.getElementById('clr-ink').value;
  var pri = document.getElementById('clr-primary').value;

  var banner = 'linear-gradient(120deg,' + c1 + ',' + c2 + ')';
  document.getElementById('inp-banner').value  = banner;
  document.getElementById('inp-ink').value     = ink;
  document.getElementById('inp-primary').value = pri;

  // Sync text inputs
  document.getElementById('clr-start-txt').value   = c1;
  document.getElementById('clr-end-txt').value     = c2;
  document.getElementById('clr-ink-txt').value     = ink;
  document.getElementById('clr-primary-txt').value = pri;

  // Update preview
  var preview = document.getElementById('banner-preview');
  preview.style.background = banner;
  preview.style.color      = ink;
  document.getElementById('preview-badge').style.color = ink;
}
</script>

