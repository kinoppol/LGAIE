/* ============================================================
   screens_course.jsx — Course page: Stream / Lessons / Work / People
   ============================================================ */

function CourseHeader({ c, role, route, go, onAdd }) {
  const D = window.DATA;
  const tabs = [
    { id: 'stream', label: 'ฟีดประกาศ', en: 'Stream', icon: 'stream' },
    { id: 'lessons', label: 'เนื้อหาบทเรียน', en: 'Lessons', icon: 'book', count: (D.LESSONS[c.id]||[]).length },
    { id: 'work', label: 'งาน / การบ้าน', en: 'Classwork', icon: 'clipboard', count: (D.ASSIGNMENTS[c.id]||[]).length },
    { id: 'people', label: 'สมาชิก', en: 'People', icon: 'users', count: c.students },
  ];
  return (
    <>
      <div className="breadcrumb">
        <a onClick={() => go({ screen: 'courses' })}>รายวิชา</a>
        <Icon name="chevronRight" size={14} />
        <span style={{ color: 'var(--body)', fontWeight: 600 }}>{c.name}</span>
      </div>
      <div className="card" style={{ overflow: 'hidden', marginBottom: 22 }}>
        <div style={{ background: c.banner, padding: '28px 28px 24px', color: c.ink, position: 'relative' }}>
          <div style={{ position: 'absolute', right: -20, top: -30, width: 180, height: 180, borderRadius: '50%', background: 'rgba(255,255,255,.4)' }}></div>
          <span className="badge" style={{ background: 'rgba(255,255,255,.65)', color: c.ink, marginBottom: 10 }}>{c.code}</span>
          <h1 style={{ color: c.ink, fontSize: 27 }}>{c.name}</h1>
          <div style={{ display: 'flex', gap: 18, marginTop: 12, fontSize: 13.5, color: c.ink, opacity: .85, flexWrap: 'wrap' }}>
            <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><Icon name="users" size={16} color={c.ink} /> {c.section}</span>
            <span style={{ display: 'flex', alignItems: 'center', gap: 6 }}><Icon name="edit" size={16} color={c.ink} /> {D.TEACHER.name}</span>
          </div>
        </div>
        <div className="tabs" style={{ margin: 0, padding: '0 16px', borderTop: 'none' }}>
          {tabs.map(t => (
            <button key={t.id} className={'tab' + (route.tab === t.id ? ' active' : '')} onClick={() => go({ ...route, tab: t.id })}>
              <Icon name={t.icon} size={17} /> {t.label}
              {t.count != null && <span className="t-count">{t.count}</span>}
            </button>
          ))}
        </div>
      </div>
    </>
  );
}

/* ---------------- STREAM ---------------- */
function StreamTab({ c, role, go }) {
  const D = window.DATA;
  const lessons = D.LESSONS[c.id] || [];
  const works = D.ASSIGNMENTS[c.id] || [];
  const feed = [
    { kind: 'announce', who: D.TEACHER, time: 'วันนี้ 08:30', title: null,
      body: 'สวัสดีนักเรียนทุกคน 🌿 สัปดาห์นี้เราจะเรียนเรื่องใหม่ อย่าลืมว่าทุกบทเรียนจะมี Prompt AI ที่ครูทดลองแล้วแนบไว้ให้ ลองนำไปต่อยอดค้นคว้านะคะ และเวลาส่งงานให้ระบุ prompt กับ AI ที่ใช้ด้วย' },
    ...lessons.map(l => ({ kind: 'lesson', who: D.TEACHER, time: l.week, item: l })),
    ...works.map(w => ({ kind: 'work', who: D.TEACHER, time: 'กำหนดส่ง ' + w.dueShort, item: w })),
  ];
  return (
    <div className="row wrap" style={{ alignItems: 'flex-start' }}>
      <div style={{ flex: '1 1 560px', minWidth: 0 }}>
        {role === 'teacher' && (
          <div className="card card-pad" style={{ marginBottom: 18, display: 'flex', alignItems: 'center', gap: 12 }}>
            <Avatar user={D.TEACHER} size={42} />
            <button className="input" style={{ textAlign: 'left', color: 'var(--muted)', background: 'var(--surface-2)' }} onClick={() => go({ screen: 'course', courseId: c.id, tab: 'lessons', add: 'lesson' })}>
              ประกาศหรือเพิ่มเนื้อหาให้นักเรียน…
            </button>
          </div>
        )}
        {feed.map((f, i) => {
          if (f.kind === 'announce') return (
            <div className="post" key={i}>
              <div className="post__head"><Avatar user={f.who} size={42} /><div><div className="ph-name">{f.who.name}</div><div className="ph-meta">โพสต์ประกาศ · {f.time}</div></div></div>
              <div className="post__body"><p style={{ margin: 0, color: 'var(--body)', lineHeight: 1.65 }}>{f.body}</p></div>
            </div>
          );
          const isLesson = f.kind === 'lesson';
          return (
            <div className="post" key={i}>
              <div className="post__head">
                <span className="avatar" style={{ width: 42, height: 42, background: isLesson ? 'var(--primary-soft)' : 'var(--warn-soft)', color: isLesson ? 'var(--primary)' : '#c76a13' }}><Icon name={isLesson ? 'book' : 'clipboard'} size={20} /></span>
                <div><div className="ph-name">{f.who.name}</div><div className="ph-meta">{isLesson ? 'เพิ่มเนื้อหาบทเรียน' : 'มอบหมายงาน'} · {f.time}</div></div>
                <span className="post__type"><span className={'badge ' + (isLesson ? 'green' : 'orange')}>{isLesson ? 'บทเรียน' : f.item.type}</span></span>
              </div>
              <div className="post__body">
                <div className="post__title">{f.item.title}</div>
                <p style={{ margin: '0 0 12px', color: 'var(--body)', fontSize: 14, lineHeight: 1.6 }}>{(isLesson ? f.item.desc : f.item.instructions).slice(0,120)}…</p>
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span className="chip"><Icon name="sparkle" size={14} color="var(--primary)" /> มี Prompt AI แนบ</span>
                  <AIPill id={f.item.prompt.ai} size="sm" />
                  <button className="btn btn-sm btn-soft" style={{ marginLeft: 'auto' }} onClick={() => isLesson ? go({ screen: 'lesson', courseId: c.id, lessonId: f.item.id }) : go({ screen: 'assignment', courseId: c.id, assignmentId: f.item.id })}>
                    {isLesson ? 'เปิดบทเรียน' : 'ดูรายละเอียดงาน'} <Icon name="arrowRight" size={15} />
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>
      <div style={{ flex: '0 0 280px', minWidth: 260 }}>
        <div className="card card-pad" style={{ marginBottom: 18 }}>
          <h3 style={{ fontSize: 15, marginBottom: 14 }}>กำหนดส่งที่ใกล้ถึง</h3>
          {works.length === 0 && <p className="subtle" style={{ fontSize: 13 }}>ยังไม่มีงาน</p>}
          {works.map(w => (
            <div key={w.id} style={{ display: 'flex', gap: 10, alignItems: 'flex-start', marginBottom: 14 }}>
              <span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--warn)', marginTop: 6, flex: '0 0 auto' }}></span>
              <div><div style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--heading)', lineHeight: 1.35 }}>{w.title}</div><div className="subtle" style={{ fontSize: 12 }}>กำหนดส่ง {w.due}</div></div>
            </div>
          ))}
        </div>
        <div className="card card-pad" style={{ background: 'var(--primary-soft)', border: '1px solid var(--primary-soft-2)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}><Icon name="target" size={19} color="var(--primary-700)" /><h3 style={{ fontSize: 14.5, color: 'var(--primary-700)' }}>ความคืบหน้า</h3></div>
          <div style={{ fontSize: 13, color: 'var(--primary-700)', marginBottom: 8 }}>เรียนไปแล้ว {c.progress}% ของรายวิชา</div>
          <div className="progress" style={{ background: '#fff' }}><span style={{ width: c.progress + '%' }}></span></div>
        </div>
      </div>
    </div>
  );
}

/* ---------------- LESSONS ---------------- */
function LessonsTab({ c, role, go, lessons, onAddLesson }) {
  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', marginBottom: 18 }}>
        <h2 style={{ fontSize: 19 }}>เนื้อหาบทเรียน</h2>
        {role === 'teacher' && <button className="btn btn-primary" style={{ marginLeft: 'auto' }} onClick={onAddLesson}><Icon name="plus" size={18} color="#fff" /> เพิ่มเนื้อหา + Prompt</button>}
      </div>
      {lessons.length === 0 && <div className="empty"><div className="e-ic"><Icon name="book" size={30} /></div><h3>ยังไม่มีเนื้อหา</h3><p>{role === 'teacher' ? 'เริ่มเพิ่มบทเรียนแรกพร้อม Prompt AI ที่แนะนำ' : 'ครูยังไม่เพิ่มเนื้อหา'}</p></div>}
      {lessons.map((l, i) => (
        <div key={l.id} className="lrow" style={{ alignItems: 'flex-start', padding: '18px 20px' }} onClick={() => go({ screen: 'lesson', courseId: c.id, lessonId: l.id })}>
          <span className="lr-ic" style={{ background: 'var(--primary-soft)', color: 'var(--primary)' }}><Icon name="book" size={20} /></span>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 3 }}>
              <span className="badge gray" style={{ fontSize: 11 }}>{l.week}</span>
              <span className="chip" style={{ fontSize: 11.5, padding: '3px 9px' }}><Icon name="sparkle" size={13} color="var(--primary)" /> Prompt AI</span>
            </div>
            <div className="lr-title">{l.title}</div>
            <div className="lr-sub" style={{ marginTop: 4, whiteSpace: 'normal', maxWidth: 640 }}>{l.desc.slice(0,110)}…</div>
            <div style={{ display: 'flex', gap: 8, marginTop: 10, alignItems: 'center' }}>
              <AIPill id={l.prompt.ai} size="sm" />
              <StarRow value={l.prompt.rating} size={13} />
              <span className="subtle" style={{ fontSize: 12 }}>· {l.materials.length} ไฟล์แนบ</span>
            </div>
          </div>
          <Icon name="chevronRight" size={20} color="var(--faint)" style={{ marginTop: 4 }} />
        </div>
      ))}
    </div>
  );
}

/* ---------------- WORK (assignments list) ---------------- */
function WorkTab({ c, role, go, works, onAddWork }) {
  return (
    <div>
      <div style={{ display: 'flex', alignItems: 'center', marginBottom: 18 }}>
        <h2 style={{ fontSize: 19 }}>งาน / การบ้าน</h2>
        {role === 'teacher' && <button className="btn btn-primary" style={{ marginLeft: 'auto' }} onClick={onAddWork}><Icon name="plus" size={18} color="#fff" /> เพิ่มงาน + Prompt</button>}
      </div>
      {works.map(w => (
        <div key={w.id} className="lrow" style={{ alignItems: 'flex-start', padding: '18px 20px' }} onClick={() => go({ screen: 'assignment', courseId: c.id, assignmentId: w.id })}>
          <span className="lr-ic" style={{ background: 'var(--warn-soft)', color: '#c76a13' }}><Icon name="clipboard" size={20} /></span>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 3 }}>
              <span className="badge orange" style={{ fontSize: 11 }}>{w.type}</span>
              <span className="chip" style={{ fontSize: 11.5, padding: '3px 9px' }}><Icon name="sparkle" size={13} color="var(--primary)" /> Prompt AI</span>
              {w.allowImprove && <span className="badge blue" style={{ fontSize: 11 }}>ปรับ prompt ได้</span>}
            </div>
            <div className="lr-title">{w.title}</div>
            <div className="lr-sub" style={{ marginTop: 4, whiteSpace: 'normal', maxWidth: 620 }}>{w.instructions.slice(0,100)}…</div>
          </div>
          <div className="lr-right">
            <span className="badge orange"><Icon name="clock" size={13} /> {w.dueShort}</span>
            {role === 'teacher'
              ? <span className="subtle" style={{ fontSize: 12.5 }}>ส่งแล้ว {w.submitted}/{w.total}</span>
              : <span className="badge gray" style={{ fontSize: 11 }}>{w.points} คะแนน</span>}
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---------------- PEOPLE ---------------- */
function PeopleTab({ c }) {
  const D = window.DATA;
  const roster = [...D.STUDENTS, { name: 'วราภรณ์ สุขใจ', av: 'av-1', initials: 'วภ' }, { name: 'กิตติพงษ์ มั่นคง', av: 'av-2', initials: 'กพ' }];
  return (
    <div className="row wrap" style={{ alignItems: 'flex-start' }}>
      <div style={{ flex: '1 1 380px' }}>
        <div className="card">
          <div className="card-head"><Icon name="edit" size={18} color="var(--primary)" /><h3>ครูผู้สอน</h3></div>
          <div className="card-pad" style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <Avatar user={D.TEACHER} size={46} /><div><div style={{ fontWeight: 700, color: 'var(--heading)' }}>{D.TEACHER.name}</div><div className="subtle" style={{ fontSize: 13 }}>{D.TEACHER.role}</div></div>
          </div>
        </div>
      </div>
      <div style={{ flex: '1 1 420px' }}>
        <div className="card">
          <div className="card-head"><Icon name="users" size={18} color="var(--accent)" /><h3>นักเรียน</h3><span className="badge gray" style={{ marginLeft: 'auto' }}>{c.students} คน</span></div>
          <div style={{ padding: 10 }}>
            {roster.map((s, i) => (
              <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', borderRadius: 9 }}>
                <Avatar user={s} size={38} /><span style={{ fontWeight: 600, color: 'var(--heading)', fontSize: 14 }}>{s.name}</span>
                <span className="subtle" style={{ marginLeft: 'auto', fontSize: 12.5 }}>เลขที่ {i+1}</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

function CoursePage({ role, route, go }) {
  const D = window.DATA;
  const c = D.COURSES.find(x => x.id === route.courseId);
  const [addType, setAddType] = React.useState(route.add || null);
  const lessons = D.LESSONS[c.id] || [];
  const works = D.ASSIGNMENTS[c.id] || [];
  const tab = route.tab || 'stream';

  return (
    <div className="content">
      <CourseHeader c={c} role={role} route={route} go={go} />
      {tab === 'stream' && <StreamTab c={c} role={role} go={go} />}
      {tab === 'lessons' && <LessonsTab c={c} role={role} go={go} lessons={lessons} onAddLesson={() => setAddType('lesson')} />}
      {tab === 'work' && <WorkTab c={c} role={role} go={go} works={works} onAddWork={() => setAddType('work')} />}
      {tab === 'people' && <PeopleTab c={c} />}

      {addType && <AddContentModal type={addType} c={c} onClose={() => setAddType(null)} />}
    </div>
  );
}

Object.assign(window, { CoursePage, CourseHeader, StreamTab, LessonsTab, WorkTab, PeopleTab });
