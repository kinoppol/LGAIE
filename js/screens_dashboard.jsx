/* ============================================================
   screens_dashboard.jsx — Dashboard + Courses grid
   ============================================================ */

function StatCard({ icon, color, soft, val, label }) {
  return (
    <div className="card card-pad" style={{ flex: 1 }}>
      <div className="stat">
        <span className="stat-ic" style={{ background: soft, color }}><Icon name={icon} size={23} /></span>
        <div>
          <div className="stat-val">{val}</div>
          <div className="stat-lbl">{label}</div>
        </div>
      </div>
    </div>
  );
}

function CourseCard({ c, go }) {
  const D = window.DATA;
  return (
    <div className="course-card" onClick={() => go({ screen: 'course', courseId: c.id, tab: 'stream' })}>
      <div className="course-card__banner" style={{ background: c.banner, color: c.ink }}>
        <span className="badge" style={{ background: 'rgba(255,255,255,.65)', color: c.ink, fontSize: 11, marginBottom: 8 }}>{c.code}</span>
        <h3>{c.name}</h3>
        <div className="cc-sec">{c.section}</div>
        <span className="avatar av-1 cc-av" style={{ background: '#fff', color: c.ink, fontWeight: 800, fontSize: 15 }}>{c.short}</span>
      </div>
      <div className="course-card__body">
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 7 }}>
          <span className="subtle" style={{ fontSize: 12.5, fontWeight: 600 }}>ความคืบหน้าบทเรียน</span>
          <span style={{ fontSize: 12.5, fontWeight: 700, color: c.color }}>{c.progress}%</span>
        </div>
        <div className="progress"><span style={{ width: c.progress + '%', background: c.color }}></span></div>
      </div>
      <div className="course-card__foot">
        <span className="cf"><Icon name="book" size={16} /> {c.lessons} บทเรียน</span>
        <span className="cf"><Icon name="clipboard" size={16} /> {c.assignments} งาน</span>
        <span className="cf" style={{ marginLeft: 'auto' }}><Icon name="users" size={16} /> {c.students}</span>
      </div>
    </div>
  );
}

function Dashboard({ role, go }) {
  const D = window.DATA;
  const me = role === 'teacher' ? D.TEACHER : D.STUDENT;
  const firstName = me.name.split(' ').slice(-1)[0];

  const allAssignments = Object.values(D.ASSIGNMENTS).flat();

  return (
    <div className="content">
      {/* hero */}
      <div className="card" style={{ background: 'linear-gradient(115deg,#d3f3e9 0%,#dcebff 55%,#e9e2ff 100%)', border: 'none', marginBottom: 24, overflow: 'hidden', position: 'relative' }}>
        <div style={{ position: 'absolute', right: -30, top: -40, width: 220, height: 220, borderRadius: '50%', background: 'rgba(255,255,255,.45)' }}></div>
        <div style={{ position: 'absolute', right: 90, bottom: -70, width: 160, height: 160, borderRadius: '50%', background: 'rgba(255,255,255,.32)' }}></div>
        <div className="card-pad" style={{ padding: '26px 28px', position: 'relative' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#0f7d64', fontSize: 13.5, fontWeight: 700, marginBottom: 8 }}>
            <Icon name="sparkle" size={16} color="#0f7d64" /> {role === 'teacher' ? 'แดชบอร์ดครูผู้สอน' : 'แดชบอร์ดผู้เรียน'}
          </div>
          <h1 style={{ color: '#26324a', fontSize: 26 }}>สวัสดี, {firstName} 👋</h1>
          <p style={{ color: '#51607a', fontSize: 15, maxWidth: 560, marginTop: 8, marginBottom: 0 }}>
            {role === 'teacher'
              ? 'เพิ่มเนื้อหาและงานพร้อม Prompt AI ที่คุณทดลองแล้วได้ผลดี เพื่อแนะแนวให้นักเรียนค้นคว้าต่อยอดอย่างชาญฉลาด'
              : 'เรียนรู้จาก Prompt ที่ครูแนะนำ แล้วลองปรับแต่งจนได้ผลลัพธ์ที่ดีกว่า — ระบุ AI และ prompt ที่คุณใช้ตอนส่งงานได้เลย'}
          </p>
          <div style={{ display: 'flex', gap: 10, marginTop: 18 }}>
            <button className="btn btn-primary" onClick={() => go({ screen: 'courses' })}>
              <Icon name="grid" size={17} color="#fff" /> ไปที่รายวิชา
            </button>
            <button className="btn" style={{ background: 'rgba(255,255,255,.7)', color: '#0f7d64' }} onClick={() => go({ screen: role === 'teacher' ? 'tograde' : 'todo' })}>
              {role === 'teacher' ? 'งานรอตรวจ' : 'งานที่ต้องส่ง'} <Icon name="arrowRight" size={16} color="#0f7d64" />
            </button>
          </div>
        </div>
      </div>

      {/* stats */}
      <div className="row wrap" style={{ marginBottom: 24 }}>
        {role === 'teacher' ? <>
          <StatCard icon="grid" color="#16a37a" soft="var(--primary-soft)" val="4" label="รายวิชาที่สอน" />
          <StatCard icon="clipboard" color="#ff9f43" soft="var(--warn-soft)" val="3" label="งานรอตรวจ" />
          <StatCard icon="sparkle" color="#3b7df5" soft="var(--accent-soft)" val="14" label="Prompt ที่แชร์ไว้" />
          <StatCard icon="users" color="#a371f7" soft="#f1e9ff" val="130" label="นักเรียนทั้งหมด" />
        </> : <>
          <StatCard icon="grid" color="#3b7df5" soft="var(--accent-soft)" val="4" label="รายวิชาที่ลงทะเบียน" />
          <StatCard icon="clipboard" color="#ff9f43" soft="var(--warn-soft)" val="4" label="งานที่ต้องส่ง" />
          <StatCard icon="checkCircle" color="#16a37a" soft="var(--primary-soft)" val="12" label="งานที่ส่งแล้ว" />
          <StatCard icon="trophy" color="#a371f7" soft="#f1e9ff" val="86%" label="คะแนนเฉลี่ย" />
        </>}
      </div>

      <div className="row wrap" style={{ alignItems: 'flex-start' }}>
        {/* courses */}
        <div style={{ flex: '1 1 600px', minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 16 }}>
            <h2 style={{ fontSize: 19 }}>รายวิชาของฉัน</h2>
            <button className="btn btn-sm btn-ghost" style={{ marginLeft: 'auto' }} onClick={() => go({ screen: 'courses' })}>ดูทั้งหมด <Icon name="arrowRight" size={15} /></button>
          </div>
          <div className="grid" style={{ gridTemplateColumns: 'repeat(auto-fill,minmax(270px,1fr))' }}>
            {D.COURSES.map(c => <CourseCard key={c.id} c={c} go={go} />)}
          </div>
        </div>

        {/* side: upcoming */}
        <div style={{ flex: '1 1 320px', minWidth: 300 }}>
          <div className="card">
            <div className="card-head"><Icon name="clock" size={19} color="var(--warn)" /><h3>{role === 'teacher' ? 'กำหนดส่งที่ใกล้ถึง' : 'งานที่ต้องส่งเร็ว ๆ นี้'}</h3></div>
            <div style={{ padding: 12 }}>
              {allAssignments.slice(0,5).map(a => {
                const c = D.COURSES.find(x => x.id === a.courseId);
                return (
                  <div key={a.id} className="lrow" style={{ marginBottom: 8, padding: '12px 14px' }} onClick={() => go({ screen: 'assignment', courseId: a.courseId, assignmentId: a.id })}>
                    <span className="lr-ic" style={{ background: c.color + '1c', color: c.color, width: 38, height: 38 }}><Icon name="clipboard" size={18} /></span>
                    <div style={{ minWidth: 0 }}>
                      <div className="lr-title" style={{ fontSize: 13.5, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{a.title}</div>
                      <div className="lr-sub" style={{ fontSize: 12 }}>{c.name}</div>
                    </div>
                    <span className="badge orange" style={{ marginLeft: 'auto', fontSize: 11 }}>{a.dueShort}</span>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="card ai-tint-box" style={{ marginTop: 18 }}>
            <div className="card-pad">
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 8 }}>
                <span style={{ width: 36, height: 36, borderRadius: 10, background: 'var(--card)', color: 'var(--accent)', display: 'grid', placeItems: 'center' }}><Icon name="robot" size={20} /></span>
                <h3 style={{ fontSize: 15 }}>เคล็ดลับการใช้ AI</h3>
              </div>
              <p style={{ fontSize: 13.5, color: 'var(--body)', margin: 0, lineHeight: 1.6 }}>
                {role === 'teacher'
                  ? 'ระบุ AI ที่ทดลองแล้วและให้ดาวความพอใจ เพื่อช่วยให้นักเรียนเริ่มต้นได้ถูกทาง'
                  : 'อย่าคัดลอกคำตอบ AI ทั้งหมด — ลองปรับ prompt หลายครั้ง เปรียบเทียบหลาย AI แล้วเรียบเรียงเป็นภาษาของตัวเอง'}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function CoursesGrid({ role, go }) {
  const D = window.DATA;
  return (
    <div className="content">
      <div className="page-head" style={{ display: 'flex', alignItems: 'flex-end' }}>
        <div>
          <h1>รายวิชาทั้งหมด</h1>
          <p className="subtle" style={{ marginTop: 6, marginBottom: 0 }}>{role === 'teacher' ? 'รายวิชาที่คุณเป็นผู้สอน' : 'รายวิชาที่คุณลงทะเบียนเรียน'}</p>
        </div>
        {role === 'teacher' && <button className="btn btn-primary" style={{ marginLeft: 'auto' }}><Icon name="plus" size={18} color="#fff" /> สร้างรายวิชา</button>}
      </div>
      <div className="grid" style={{ gridTemplateColumns: 'repeat(auto-fill,minmax(290px,1fr))' }}>
        {D.COURSES.map(c => <CourseCard key={c.id} c={c} go={go} />)}
      </div>
    </div>
  );
}

Object.assign(window, { Dashboard, CoursesGrid, CourseCard, StatCard });
