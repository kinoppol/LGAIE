/* ============================================================
   ui.jsx — icons + shared primitives, exported to window
   ============================================================ */
const { useState, useEffect, useRef } = React;

// ---------- Icon set (stroke, 20px default) ----------
function Icon({ name, size = 20, color = 'currentColor', sw = 1.7, style }) {
  const P = { width: size, height: size, viewBox: '0 0 24 24', fill: 'none', stroke: color, strokeWidth: sw, strokeLinecap: 'round', strokeLinejoin: 'round', style };
  const paths = {
    home: <><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/></>,
    grid: <><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></>,
    book: <><path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 1 2-2h14"/></>,
    clipboard: <><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 11h6M9 15h4"/></>,
    stream: <><path d="M4 6h16M4 12h16M4 18h10"/></>,
    check: <path d="M5 12.5 10 17.5 19.5 6.5"/>,
    checkCircle: <><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5 11 15l4.5-5"/></>,
    clock: <><circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/></>,
    users: <><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M16.5 19a5.5 5.5 0 0 0-2-4"/></>,
    plus: <path d="M12 5v14M5 12h14"/>,
    copy: <><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></>,
    sparkle: <><path d="M12 3v4M12 17v4M3 12h4M17 12h4" opacity="0"/><path d="M12 4.5 13.6 9 18 10.5 13.6 12 12 16.5 10.4 12 6 10.5 10.4 9z"/><path d="M18.5 4.5l.6 1.6 1.6.6-1.6.6-.6 1.6-.6-1.6-1.6-.6 1.6-.6z"/></>,
    robot: <><rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V4M9 4h6"/><circle cx="9" cy="13" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="13" r="1.2" fill="currentColor" stroke="none"/><path d="M9.5 16.5h5"/></>,
    bell: <><path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 19a2 2 0 0 0 4 0"/></>,
    search: <><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></>,
    star: <path d="M12 3.5l2.6 5.6 6 .8-4.4 4.1 1.1 6L12 17.3 6.7 20l1.1-6L3.4 9.9l6-.8z"/>,
    arrowRight: <path d="M5 12h14M13 6l6 6-6 6"/>,
    arrowLeft: <path d="M19 12H5M11 18l-6-6 6-6"/>,
    chevronRight: <path d="M9 6l6 6-6 6"/>,
    file: <><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></>,
    download: <><path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/></>,
    edit: <><path d="M15.5 4.5l4 4L8 20H4v-4z"/><path d="M13.5 6.5l4 4"/></>,
    send: <><path d="M21 4 3 11l6 2.5L11 20l3.5-6L21 4z"/><path d="M9 13.5 21 4"/></>,
    x: <path d="M6 6l12 12M18 6 6 18"/>,
    trophy: <><path d="M8 4h8v4a4 4 0 0 1-8 0z"/><path d="M8 5H5v2a3 3 0 0 0 3 3M16 5h3v2a3 3 0 0 1-3 3"/><path d="M12 12v4M9 20h6M10 16h4l.5 4h-5z"/></>,
    bulb: <><path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 1 4 10.5c-.7.7-1 1.2-1 2.5H9c0-1.3-.3-1.8-1-2.5A6 6 0 0 1 12 3z"/></>,
    target: <><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/></>,
    flag: <><path d="M5 21V4M5 4h11l-1.5 3.5L16 11H5"/></>,
    calendar: <><rect x="4" y="5" width="16" height="16" rx="2.5"/><path d="M4 9.5h16M8 3v4M16 3v4"/></>,
    chart: <><path d="M4 20V4M4 20h16"/><rect x="7" y="12" width="3" height="5" rx="1" fill="currentColor" stroke="none"/><rect x="12.5" y="8" width="3" height="9" rx="1" fill="currentColor" stroke="none"/><rect x="18" y="14" width="3" height="3" rx="1" fill="currentColor" stroke="none" opacity="0"/></>,
    thumbsUp: <><path d="M7 11v9H4a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1z"/><path d="M7 11l4-7a2 2 0 0 1 2 1.5V9h5a2 2 0 0 1 2 2.3l-1 6a2 2 0 0 1-2 1.7H7"/></>,
    settings: <><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></>,
    logout: <><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></>,
    folder: <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>,
    message: <path d="M21 12a8 8 0 0 1-11.5 7.2L4 20l.8-5.5A8 8 0 1 1 21 12z"/>,
    sun: <><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.5 4.5l1.4 1.4M18.1 18.1l1.4 1.4M2 12h2M20 12h2M4.5 19.5l1.4-1.4M18.1 5.9l1.4-1.4"/></>,
    moon: <path d="M20 14.5A8 8 0 0 1 9.5 4a7 7 0 1 0 10.5 10.5z"/>,
    monitor: <><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></>,
  };
  return <svg {...P}>{paths[name] || null}</svg>;
}

// ---------- File type icon color ----------
function FileBadge({ type }) {
  const map = { pdf: ['#ea5455','PDF'], ppt: ['#ff9f43','PPT'], img: ['#a371f7','IMG'], doc: ['#3b7df5','DOC'] };
  const [c, t] = map[type] || ['#8a94a6', 'FILE'];
  return <span style={{ width: 38, height: 38, borderRadius: 9, background: c+'22', color: c, display: 'grid', placeItems: 'center', fontSize: 10, fontWeight: 800, flex: '0 0 auto' }}>{t}</span>;
}

// ---------- AI pill ----------
function AIPill({ id, size = 'md' }) {
  const ai = window.DATA.AI_REGISTRY[id];
  if (!ai) return null;
  const sm = size === 'sm';
  return (
    <span className="ai-pill" style={sm ? { padding: '3px 9px 3px 4px', fontSize: 11.5 } : null}>
      <span className="ai-logo" style={{ background: ai.color, width: sm ? 17 : 20, height: sm ? 17 : 20, fontSize: sm ? 9 : 11 }}>{ai.letter}</span>
      {ai.name}
    </span>
  );
}

// ---------- AI select (custom dropdown over a native select) ----------
function AISelect({ value, onChange }) {
  return (
    <div style={{ position: 'relative' }}>
      <select className="select" value={value} onChange={e => onChange(e.target.value)} style={{ paddingLeft: 40, appearance: 'none', fontWeight: 600 }}>
        {window.DATA.AI_LIST.map(ai => <option key={ai.id} value={ai.id}>{ai.name}</option>)}
      </select>
      {value && (
        <span className="ai-logo" style={{ background: window.DATA.AI_REGISTRY[value].color, position: 'absolute', left: 11, top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none' }}>
          {window.DATA.AI_REGISTRY[value].letter}
        </span>
      )}
      <span style={{ position: 'absolute', right: 13, top: '50%', transform: 'translateY(-50%)', pointerEvents: 'none', color: 'var(--muted)' }}>
        <Icon name="chevronRight" size={16} style={{ transform: 'rotate(90deg)' }} />
      </span>
    </div>
  );
}

// ---------- Stars ----------
function Stars({ value, size = 15, editable = false, onChange }) {
  return (
    <span className="stars">
      {[1,2,3,4,5].map(n => (
        <span key={n} className={n <= value ? '' : 'empty'} onClick={editable ? () => onChange(n) : undefined} style={{ cursor: editable ? 'pointer' : 'default' }}>
          <Icon name="star" size={size} color={n <= value ? '#ff9f43' : '#e0e3ea'} sw={0} />
        </span>
      ))}
    </span>
  );
}
function StarFill({ filled, size = 15, onClick, editable }) {
  return <svg onClick={editable ? onClick : undefined} width={size} height={size} viewBox="0 0 24 24" style={{ cursor: editable ? 'pointer' : 'default' }}>
    <path d="M12 3.5l2.6 5.6 6 .8-4.4 4.1 1.1 6L12 17.3 6.7 20l1.1-6L3.4 9.9l6-.8z" fill={filled ? '#ff9f43' : '#e4e7ee'} />
  </svg>;
}
function StarRow({ value, size = 15, editable = false, onChange }) {
  return <span style={{ display: 'inline-flex', gap: 2 }}>
    {[1,2,3,4,5].map(n => <StarFill key={n} filled={n <= value} size={size} editable={editable} onClick={() => onChange && onChange(n)} />)}
  </span>;
}

// ---------- Avatar ----------
function Avatar({ user, size = 38 }) {
  return <span className={'avatar ' + (user.av || 'av-1')} style={{ width: size, height: size, fontSize: size * 0.38 }}>{user.initials}</span>;
}

// ---------- Copy button ----------
function CopyBtn({ text, label = 'คัดลอก' }) {
  const [done, setDone] = useState(false);
  const copy = () => {
    const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); } catch(e) {}
    document.body.removeChild(ta);
    setDone(true); setTimeout(() => setDone(false), 1600);
  };
  return (
    <button className="btn btn-sm btn-ghost copy-btn" onClick={copy}>
      <Icon name={done ? 'check' : 'copy'} size={15} color={done ? 'var(--primary)' : 'currentColor'} />
      {done ? 'คัดลอกแล้ว' : label}
    </button>
  );
}

// ---------- Teacher Prompt Block (read view) ----------
function PromptBlock({ block, title = 'Prompt ที่ครูแนะนำ', accent = 'primary' }) {
  const [showEx, setShowEx] = useState(false);
  return (
    <div className="prompt-block">
      <div className="prompt-block__head">
        <span className="pb-title"><Icon name="sparkle" size={17} color="var(--primary)" /> {title}</span>
        <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
          <span className="subtle" style={{ fontSize: 12 }}>AI ที่แนะนำ</span>
          <AIPill id={block.ai} size="sm" />
        </span>
      </div>
      <div className="prompt-body">
        <div style={{ display: 'flex', alignItems: 'center', marginBottom: 9 }}>
          <span className="subtle" style={{ fontSize: 12.5, fontWeight: 600 }}>ข้อความ Prompt</span>
          <CopyBtn text={block.prompt} />
        </div>
        <div className="prompt-text">{block.prompt}</div>

        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 14, flexWrap: 'wrap' }}>
          <span className="subtle" style={{ fontSize: 12.5, fontWeight: 600 }}>ระดับความพอใจของครู</span>
          <StarRow value={block.rating} />
          <span className="badge gray" style={{ fontSize: 11 }}>{block.rating}/5</span>
          <button className="btn btn-sm btn-ghost" style={{ marginLeft: 'auto' }} onClick={() => setShowEx(!showEx)}>
            <Icon name="bulb" size={15} /> {showEx ? 'ซ่อนผลลัพธ์ตัวอย่าง' : 'ดูผลลัพธ์ตัวอย่างที่ครูได้'}
          </button>
        </div>

        {showEx && (
          <div className="animate-in ex-box" style={{ marginTop: 12, padding: '12px 14px' }}>
            <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--accent-700)', marginBottom: 6, display: 'flex', alignItems: 'center', gap: 6 }}>
              <Icon name="robot" size={15} color="var(--accent-700)" /> ผลลัพธ์ตัวอย่างจาก {window.DATA.AI_REGISTRY[block.ai].name}
            </div>
            <div style={{ fontSize: 13.5, color: 'var(--heading)', lineHeight: 1.6 }}>{block.example}</div>
          </div>
        )}

        {block.note && (
          <div className="note-box" style={{ marginTop: 12, display: 'flex', gap: 9, alignItems: 'flex-start', padding: '11px 13px' }}>
            <Icon name="flag" size={16} color="var(--warn-ink)" style={{ marginTop: 2, flex: '0 0 auto' }} />
            <div style={{ fontSize: 13, color: 'var(--warn-ink)', lineHeight: 1.55 }}><b>หมายเหตุจากครู:</b> {block.note}</div>
          </div>
        )}
      </div>
    </div>
  );
}

// ---------- Modal ----------
function Modal({ title, icon, onClose, children, footer, wide }) {
  useEffect(() => {
    const h = e => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', h); return () => window.removeEventListener('keydown', h);
  }, []);
  return (
    <div className="modal-overlay" onMouseDown={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className={'modal' + (wide ? ' wide' : '')}>
        <div className="modal__head">
          {icon && <span style={{ width: 38, height: 38, borderRadius: 10, background: 'var(--primary-soft)', color: 'var(--primary)', display: 'grid', placeItems: 'center', flex: '0 0 auto' }}><Icon name={icon} size={20} /></span>}
          <h3>{title}</h3>
          <button className="x-btn" onClick={onClose}><Icon name="x" size={18} /></button>
        </div>
        <div className="modal__body">{children}</div>
        {footer && <div className="modal__foot">{footer}</div>}
      </div>
    </div>
  );
}

// ---------- Toast ----------
function useToast() {
  const [toast, setToast] = useState(null);
  const show = (msg) => { setToast(msg); setTimeout(() => setToast(null), 2600); };
  const node = toast ? <div className="toast"><Icon name="checkCircle" size={18} color="#1ec18f" /> {toast}</div> : null;
  return [node, show];
}

Object.assign(window, { Icon, FileBadge, AIPill, AISelect, Stars, StarRow, StarFill, Avatar, CopyBtn, PromptBlock, Modal, useToast });
