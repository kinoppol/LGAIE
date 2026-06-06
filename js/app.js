/* ============================================================
   app.js — ClassroomAI LMS client-side logic
   Vanilla JS: theme toggle, modals, AJAX forms, copy, toast
   ============================================================ */

// ── Theme toggle ──────────────────────────────────────────────
(function initTheme() {
  const root   = document.documentElement;
  const sw     = document.getElementById('theme-switch');
  if (!sw) return;

  const mq = window.matchMedia('(prefers-color-scheme: dark)');

  function applyTheme(mode) {
    const dark = mode === 'dark' || (mode === 'system' && mq.matches);
    root.setAttribute('data-theme', dark ? 'dark' : 'light');
    localStorage.setItem('ca-theme', mode);
    sw.querySelectorAll('button').forEach(b => {
      b.classList.toggle('on', b.dataset.theme === mode);
    });
    // persist to server session
    fetch('api/set_theme.php?theme=' + encodeURIComponent(mode)).catch(() => {});
  }

  sw.addEventListener('click', e => {
    const btn = e.target.closest('[data-theme]');
    if (btn) applyTheme(btn.dataset.theme);
  });

  mq.addEventListener('change', () => {
    if (localStorage.getItem('ca-theme') === 'system') applyTheme('system');
  });

  // Restore active button
  const saved = localStorage.getItem('ca-theme') || 'system';
  sw.querySelectorAll('button').forEach(b => {
    b.classList.toggle('on', b.dataset.theme === saved);
  });
})();

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, isError = false) {
  const container = document.getElementById('toast-container');
  if (!container) return;
  const t   = document.createElement('div');
  const bg  = isError ? '#dc2626' : '#16a34a';
  const ico = isError
    ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" style="flex:0 0 auto"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>'
    : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" style="flex:0 0 auto"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5 11 15l4.5-5"/></svg>';
  t.style.cssText = `
    background:${bg};color:#fff;display:flex;align-items:center;gap:10px;
    padding:13px 20px;border-radius:14px;font-size:.9rem;font-weight:600;
    box-shadow:0 6px 24px rgba(0,0,0,.4);max-width:380px;line-height:1.45;
    pointer-events:auto;transition:opacity .3s;
  `;
  t.innerHTML = ico + '<span>' + msg + '</span>';
  container.appendChild(t);
  // fade out then remove after 4.5 s
  setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 320); }, 4500);
}

// Show PHP flash messages on page load
(function checkFlash() {
  const meta = document.querySelector('meta[name="flash-success"]');
  const err  = document.querySelector('meta[name="flash-error"]');
  if (meta) showToast(meta.content);
  if (err)  showToast(err.content, true);
})();

// ── Modal helpers ─────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id + '-overlay');
  if (el) {
    el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  const el = document.getElementById(id + '-overlay');
  if (el) {
    el.style.display = 'none';
    document.body.style.overflow = '';
  }
}

function closeModalOnBg(event, id) {
  if (event.target === event.currentTarget) closeModal(id);
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay[style*="flex"]').forEach(el => {
      el.style.display = 'none';
    });
    document.body.style.overflow = '';
  }
});

// ── Toggle collapsible element ────────────────────────────────
function toggleEl(elId, lblId, labelOpen, labelClose) {
  const el  = document.getElementById(elId);
  const lbl = document.getElementById(lblId);
  if (!el) return;
  const visible = el.style.display !== 'none';
  el.style.display  = visible ? 'none' : 'block';
  if (lbl) lbl.textContent = visible ? labelOpen : labelClose;
}

// ── Copy to clipboard ─────────────────────────────────────────
document.addEventListener('click', e => {
  const btn = e.target.closest('.copy-btn[data-copy]');
  if (!btn) return;
  const text = btn.dataset.copy;
  navigator.clipboard.writeText(text).then(() => {
    const span = btn.querySelector('span');
    const orig = span ? span.textContent : 'คัดลอก';
    if (span) span.textContent = 'คัดลอกแล้ว';
    btn.style.color = 'var(--primary)';
    setTimeout(() => {
      if (span) span.textContent = orig;
      btn.style.color = '';
    }, 1600);
  }).catch(() => {
    // fallback
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('คัดลอกแล้ว');
  });
});

// ── Star rating input ─────────────────────────────────────────
function setStars(svg) {
  const wrap  = svg.closest('.star-input');
  if (!wrap) return;
  const value = parseInt(svg.dataset.v, 10);
  wrap.dataset.value = value;
  const hidden = wrap.querySelector('input[type=hidden]');
  if (hidden) hidden.value = value;
  wrap.querySelectorAll('svg').forEach(s => {
    const fill = parseInt(s.dataset.v, 10) <= value ? '#ff9f43' : '#e4e7ee';
    const path = s.querySelector('path');
    if (path) path.setAttribute('fill', fill);
  });
  // update display badge if any
  const disp = document.getElementById('rating-display') || document.getElementById('asgn-rating-display');
  if (disp) disp.textContent = value + '/5';
}

// ── AI select logo update ─────────────────────────────────────
window.AI_TOOLS = window.AI_TOOLS || [];

function updateAiSelect(sel) {
  const name   = sel.name;
  const logo   = document.getElementById('logo-' + name);
  const ai     = (window.AI_TOOLS || []).find(t => t.id === sel.value);
  if (logo && ai) {
    logo.style.background = ai.color;
    logo.textContent      = ai.letter;
  }
}

// ── AJAX form submission ──────────────────────────────────────
document.addEventListener('submit', e => {
  const form = e.target;
  if (!('ajax' in form.dataset)) return;
  e.preventDefault();

  const btn  = form.closest('.modal')?.querySelector('[type=submit]');
  const orig = btn?.textContent;
  if (btn) { btn.disabled = true; btn.textContent = 'กำลังบันทึก…'; }

  const data = new FormData(form);

  fetch(form.action, { method: 'POST', body: data })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        showToast(res.message || 'บันทึกเรียบร้อยแล้ว');
        // close the modal and reload after a moment
        setTimeout(() => window.location.reload(), 900);
      } else {
        showToast(res.error || 'เกิดข้อผิดพลาด', true);
        if (btn) { btn.disabled = false; btn.textContent = orig; }
      }
    })
    .catch(() => {
      showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', true);
      if (btn) { btn.disabled = false; btn.textContent = orig; }
    });
});

// ── Grade modal ───────────────────────────────────────────────
function openGradeModal(sub) {
  document.getElementById('gf-sub-id').value     = sub.id;
  document.getElementById('gf-name').textContent = sub.name;
  document.getElementById('gf-at').textContent   = 'ส่งเมื่อ ' + sub.at;
  document.getElementById('gf-result').textContent = sub.result || '—';
  document.getElementById('gf-grade').value      = sub.grade || '';
  document.getElementById('gf-feedback').value   = sub.feedback || '';
  document.getElementById('gf-pts-lbl').textContent = 'คะแนน (เต็ม ' + sub.points + ')';
  document.getElementById('grade-modal-title').textContent = 'ตรวจงาน: ' + sub.name;

  const avEl = document.getElementById('gf-avatar');
  if (avEl) {
    avEl.innerHTML = `<span class="avatar ${sub.av}" style="width:44px;height:44px;font-size:17px">${sub.initials}</span>`;
  }
  const aiEl = document.getElementById('gf-ai');
  if (aiEl) {
    const ai = (window.AI_TOOLS || []).find(t => t.id === sub.ai);
    if (ai) {
      aiEl.innerHTML = `<span class="ai-pill"><span class="ai-logo" style="background:${ai.color}">${ai.letter}</span>${ai.name}</span>`;
    }
  }

  document.getElementById('grade-modal-overlay').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

// ── "Better than teacher" prompt toggle ──────────────────────
function toggleBetterBox(cb) {
  const wrap = document.getElementById('better-wrap');
  const note = document.getElementById('compare-note');
  if (wrap) {
    wrap.style.background  = cb.checked ? 'var(--primary-soft)' : 'var(--card)';
    wrap.style.borderColor = cb.checked ? 'var(--primary-soft-2)' : 'var(--line-2)';
  }
  if (note) note.style.display = cb.checked ? 'block' : 'none';
}

// ── Bell notification dropdown ────────────────────────────────
function toggleBellMenu() {
  var d = document.getElementById('bell-dropdown');
  var u = document.getElementById('user-dropdown');
  if (!d) return;
  if (u) u.style.display = 'none';
  d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
  var d = document.getElementById('bell-dropdown');
  if (!d) return;
  if (!e.target.closest('#bell-btn') && !e.target.closest('#bell-dropdown')) {
    d.style.display = 'none';
  }
});

// ── User dropdown ─────────────────────────────────────────────
function toggleUserMenu() {
  var d = document.getElementById('user-dropdown');
  var b = document.getElementById('bell-dropdown');
  if (!d) return;
  if (b) b.style.display = 'none';
  d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
document.addEventListener('click', function(e) {
  var d = document.getElementById('user-dropdown');
  if (!d) return;
  if (!e.target.closest('[onclick="toggleUserMenu()"]') && !e.target.closest('#user-dropdown')) {
    d.style.display = 'none';
  }
});

// ── Search box (client-side placeholder) ─────────────────────
const searchInput = document.querySelector('.searchbox input');
if (searchInput) {
  searchInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && searchInput.value.trim()) {
      // future: implement server-side search
      showToast('ฟีเจอร์ค้นหากำลังพัฒนา…');
    }
  });
}
