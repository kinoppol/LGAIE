/* ============================================================
   layout.jsx — Sidebar + Topbar shell
   ============================================================ */
const { useState: useStateL } = React;

function Sidebar({ nav, route, go, role }) {
  const D = window.DATA;
  const navMain = [
    { id: 'dashboard', label: 'หน้าหลัก', en: 'Dashboard', icon: 'home' },
    { id: 'courses', label: 'รายวิชาทั้งหมด', en: 'Courses', icon: 'grid' },
  ];
  const navWork = role === 'teacher'
    ? [{ id: 'tograde', label: 'งานรอตรวจ', en: 'To review', icon: 'clipboard', badge: 3 }]
    : [{ id: 'todo', label: 'งานที่ต้องส่ง', en: 'To-do', icon: 'clipboard', badge: 4 }];

  const isActive = (id) => route.screen === id || (id === 'courses' && route.screen === 'course');

  return (
    <aside className="sidebar">
      <div className="sidebar__brand">
        <span className="brand-mark"><Icon name="sparkle" size={22} color="#fff" /></span>
        <span className="brand-name">Classroom<b>AI</b></span>
      </div>
      <nav className="nav-scroll">
        <div className="nav-label">เมนูหลัก</div>
        {navMain.map(n => (
          <button key={n.id} className={'nav-item' + (isActive(n.id) ? ' active' : '')} onClick={() => go({ screen: n.id })}>
            <Icon name={n.icon} size={20} /> {n.label}
          </button>
        ))}
        {navWork.map(n => (
          <button key={n.id} className={'nav-item' + (isActive(n.id) ? ' active' : '')} onClick={() => go({ screen: n.id })}>
            <Icon name={n.icon} size={20} /> {n.label}
            <span className="nav-badge">{n.badge}</span>
          </button>
        ))}

        <div className="nav-label">รายวิชาของฉัน</div>
        {D.COURSES.map(c => (
          <button key={c.id} className={'nav-item' + (route.courseId === c.id ? ' active' : '')} onClick={() => go({ screen: 'course', courseId: c.id, tab: 'stream' })}>
            <span style={{ width: 20, height: 20, borderRadius: 6, background: c.banner, flex: '0 0 auto' }}></span>
            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</span>
          </button>
        ))}

        <div className="nav-label">อื่น ๆ</div>
        <button className="nav-item"><Icon name="calendar" size={20} /> ปฏิทิน</button>
        <button className="nav-item"><Icon name="settings" size={20} /> ตั้งค่า</button>
      </nav>
      <div className="sidebar__foot">
        <div className="course-mini">
          <span style={{ width: 34, height: 34, borderRadius: 9, background: 'var(--primary-soft)', color: 'var(--primary)', display: 'grid', placeItems: 'center', flex: '0 0 auto' }}>
            <Icon name="bulb" size={18} />
          </span>
          <div style={{ lineHeight: 1.3 }}>
            <div className="cm-name">ใช้ AI อย่างมีสติ</div>
            <div className="cm-sub">ตรวจสอบคำตอบเสมอ</div>
          </div>
        </div>
      </div>
    </aside>
  );
}

function ThemeToggle() {
  const [mode, setMode] = React.useState(() => localStorage.getItem('ca-theme') || 'system');

  React.useEffect(() => {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const apply = () => {
      const dark = mode === 'dark' || (mode === 'system' && mq.matches);
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    };
    apply();
    localStorage.setItem('ca-theme', mode);
    if (mode === 'system') {
      mq.addEventListener('change', apply);
      return () => mq.removeEventListener('change', apply);
    }
  }, [mode]);

  const opts = [
    { id: 'light', icon: 'sun', label: 'สว่าง' },
    { id: 'dark', icon: 'moon', label: 'มืด' },
    { id: 'system', icon: 'monitor', label: 'ตามระบบ' },
  ];
  return (
    <div className="theme-switch" title="โหมดสี: สว่าง / มืด / ตามระบบ">
      {opts.map(o => (
        <button key={o.id} className={mode === o.id ? 'on' : ''} onClick={() => setMode(o.id)} aria-label={o.label} title={o.label}>
          <Icon name={o.icon} size={17} />
        </button>
      ))}
    </div>
  );
}

function Topbar({ role, setRole, route, go }) {
  const D = window.DATA;
  const me = role === 'teacher' ? D.TEACHER : D.STUDENT;
  return (
    <header className="topbar">
      <div className="searchbox">
        <Icon name="search" size={18} />
        <input placeholder="ค้นหารายวิชา งาน หรือ prompt…" />
      </div>
      <div className="topbar__spacer"></div>

      <ThemeToggle />

      <div className="role-switch" title="สลับมุมมอง">
        <button className={role === 'teacher' ? 'on' : ''} onClick={() => setRole('teacher')}>
          <Icon name="edit" size={15} /> ครู
        </button>
        <button className={role === 'student' ? 'on stu' : ''} onClick={() => setRole('student')}>
          <Icon name="book" size={15} /> นักเรียน
        </button>
      </div>

      <button className="icon-btn"><Icon name="message" size={19} /></button>
      <button className="icon-btn"><Icon name="bell" size={19} /><span className="dot"></span></button>

      <div className="user-chip">
        <Avatar user={me} size={40} />
        <div>
          <div className="u-name">{me.name}</div>
          <div className="u-role">{me.role}</div>
        </div>
      </div>
    </header>
  );
}

Object.assign(window, { Sidebar, Topbar, ThemeToggle });
