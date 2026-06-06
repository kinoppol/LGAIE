/* ============================================================
   app.jsx — router, role state, mount
   ============================================================ */
const { useState: useStateA } = React;

/* ---- Work queue (To-do for student / To-review for teacher) ---- */
function WorkQueue({ role, go }) {
  const D = window.DATA;
  const all = Object.values(D.ASSIGNMENTS).flat();
  return (
    <div className="content" style={{ maxWidth: 980 }}>
      <div className="page-head">
        <h1>{role === 'teacher' ? 'งานรอตรวจ' : 'งานที่ต้องส่ง'}</h1>
        <p className="subtle" style={{ marginTop: 6, marginBottom: 0 }}>
          {role === 'teacher' ? 'งานจากทุกรายวิชาที่นักเรียนส่งเข้ามา' : 'รวมงานและการบ้านที่ใกล้ถึงกำหนดส่งจากทุกวิชา'}
        </p>
      </div>
      {all.map(a => {
        const c = D.COURSES.find(x => x.id === a.courseId);
        const subs = D.SUBMISSIONS[a.id] || [];
        const pending = subs.filter(s => s.status !== 'graded').length;
        return (
          <div key={a.id} className="lrow" style={{ alignItems: 'flex-start', padding: '18px 20px' }} onClick={() => go({ screen: 'assignment', courseId: a.courseId, assignmentId: a.id })}>
            <span className="lr-ic" style={{ background: c.color + '1c', color: c.color }}><Icon name="clipboard" size={20} /></span>
            <div style={{ minWidth: 0, flex: 1 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 3 }}>
                <span className="badge" style={{ background: c.color + '1c', color: c.color, fontSize: 11 }}>{c.name}</span>
                <span className="badge orange" style={{ fontSize: 11 }}>{a.type}</span>
              </div>
              <div className="lr-title">{a.title}</div>
              <div className="lr-sub" style={{ marginTop: 4 }}>กำหนดส่ง {a.due} · {a.points} คะแนน</div>
            </div>
            <div className="lr-right">
              {role === 'teacher'
                ? (pending > 0 ? <span className="badge orange"><Icon name="clock" size={13} /> รอตรวจ {pending}</span> : <span className="badge green"><Icon name="check" size={13} /> ตรวจครบ</span>)
                : <span className="badge orange"><Icon name="clock" size={13} /> {a.dueShort}</span>}
              <button className="btn btn-sm btn-soft">{role === 'teacher' ? 'ตรวจงาน' : 'ทำงาน'} <Icon name="arrowRight" size={14} /></button>
            </div>
          </div>
        );
      })}
    </div>
  );
}

function App() {
  const [role, setRole] = useStateA('teacher');
  const [route, setRoute] = useStateA({ screen: 'dashboard' });
  const [toastNode, toast] = window.useToast();

  const go = (r) => { setRoute(r); window.scrollTo({ top: 0 }); };

  let screen;
  switch (route.screen) {
    case 'dashboard': screen = <Dashboard role={role} go={go} />; break;
    case 'courses': screen = <CoursesGrid role={role} go={go} />; break;
    case 'course': screen = <CoursePage role={role} route={route} go={go} />; break;
    case 'lesson': screen = <LessonPage role={role} route={route} go={go} />; break;
    case 'assignment': screen = <AssignmentPage role={role} route={route} go={go} toast={toast} />; break;
    case 'todo': case 'tograde': screen = <WorkQueue role={role} go={go} />; break;
    default: screen = <Dashboard role={role} go={go} />;
  }

  return (
    <div className="app">
      <Sidebar route={route} go={go} role={role} />
      <div className="main">
        <Topbar role={role} setRole={r => { setRole(r); }} route={route} go={go} />
        {screen}
      </div>
      {toastNode}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
