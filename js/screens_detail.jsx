/* ============================================================
   screens_detail.jsx — AddContentModal, LessonPage
   ============================================================ */

/* ---------------- ADD CONTENT / WORK MODAL ---------------- */
function AddContentModal({ type, c, onClose }) {
  const isWork = type === 'work';
  const [title, setTitle] = React.useState('');
  const [desc, setDesc] = React.useState('');
  const [promptText, setPromptText] = React.useState('');
  const [ai, setAi] = React.useState('chatgpt');
  const [rating, setRating] = React.useState(4);
  const [example, setExample] = React.useState('');
  const [note, setNote] = React.useState('');
  const [allowImprove, setAllowImprove] = React.useState(true);
  const [due, setDue] = React.useState('');
  const [points, setPoints] = React.useState(10);
  const [saved, setSaved] = React.useState(false);

  const canSave = title.trim() && promptText.trim();
  const save = () => { setSaved(true); setTimeout(onClose, 1100); };

  return (
    <Modal wide
      title={isWork ? 'เพิ่มงาน / การบ้าน + Prompt AI' : 'เพิ่มเนื้อหาบทเรียน + Prompt AI'}
      icon={isWork ? 'clipboard' : 'book'}
      onClose={onClose}
      footer={<>
        <button className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
        <button className={'btn ' + (canSave ? 'btn-primary' : 'btn-ghost')} disabled={!canSave} onClick={save} style={!canSave ? { opacity: .5, cursor: 'not-allowed' } : null}>
          <Icon name={saved ? 'check' : 'send'} size={17} color={canSave ? '#fff' : 'currentColor'} /> {saved ? 'บันทึกแล้ว!' : (isWork ? 'มอบหมายงาน' : 'โพสต์เนื้อหา')}
        </button>
      </>}>

      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 18, color: 'var(--muted)', fontSize: 13 }}>
        <Icon name="grid" size={15} /> {c.name} · {c.section}
      </div>

      <div className="field">
        <label>{isWork ? 'ชื่องาน / การบ้าน' : 'หัวข้อบทเรียน'} <span style={{ color: 'var(--danger)' }}>*</span></label>
        <input className="input" placeholder={isWork ? 'เช่น ออกแบบอัลกอริทึมแก้ปัญหาในชีวิตประจำวัน' : 'เช่น แนวคิดเชิงคำนวณ'} value={title} onChange={e => setTitle(e.target.value)} />
      </div>
      <div className="field">
        <label>{isWork ? 'คำสั่ง / รายละเอียดงาน' : 'คำอธิบายเนื้อหา'}</label>
        <textarea className="textarea" placeholder={isWork ? 'อธิบายสิ่งที่ต้องการให้นักเรียนทำ…' : 'อธิบายเนื้อหาที่นักเรียนจะได้เรียนรู้…'} value={desc} onChange={e => setDesc(e.target.value)} />
      </div>

      {isWork && (
        <div className="row" style={{ gap: 14 }}>
          <div className="field" style={{ flex: 1 }}>
            <label>กำหนดส่ง</label>
            <input className="input" placeholder="เช่น 12 มิ.ย. 2569" value={due} onChange={e => setDue(e.target.value)} />
          </div>
          <div className="field" style={{ flex: 1 }}>
            <label>คะแนนเต็ม</label>
            <input className="input" type="number" value={points} onChange={e => setPoints(e.target.value)} />
          </div>
        </div>
      )}

      {/* ---- AI PROMPT SECTION ---- */}
      <div className="ai-tint-box" style={{ padding: '16px 16px 6px', marginTop: 6 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginBottom: 4 }}>
          <span style={{ width: 32, height: 32, borderRadius: 9, background: 'var(--card)', color: 'var(--primary)', display: 'grid', placeItems: 'center' }}><Icon name="sparkle" size={18} /></span>
          <div>
            <div style={{ fontWeight: 700, color: 'var(--heading)', fontSize: 14.5 }}>Prompt AI ที่แนะนำ</div>
            <div className="subtle" style={{ fontSize: 12 }}>ระบุ prompt และ AI ที่คุณทดลองแล้วได้ผลลัพธ์น่าพอใจ</div>
          </div>
        </div>

        <div className="field" style={{ marginTop: 12 }}>
          <label>ข้อความ Prompt <span style={{ color: 'var(--danger)' }}>*</span></label>
          <textarea className="textarea" style={{ fontFamily: 'ui-monospace, monospace', fontSize: 13 }} placeholder="วาง prompt ที่คุณใช้กับ AI ที่นี่…" value={promptText} onChange={e => setPromptText(e.target.value)} />
        </div>

        <div className="row" style={{ gap: 14 }}>
          <div className="field" style={{ flex: 1 }}>
            <label>AI ที่ทดลองใช้แล้ว</label>
            <AISelect value={ai} onChange={setAi} />
          </div>
          <div className="field" style={{ flex: 1 }}>
            <label>ระดับความพอใจของผลลัพธ์</label>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, height: 44 }}>
              <StarRow value={rating} size={26} editable onChange={setRating} />
              <span className="badge gray">{rating}/5</span>
            </div>
          </div>
        </div>

        <div className="field">
          <label>ผลลัพธ์ตัวอย่างที่ได้ <span className="subtle" style={{ fontWeight: 400 }}>(ไม่บังคับ)</span></label>
          <textarea className="textarea" style={{ minHeight: 70 }} placeholder="สรุปสั้น ๆ ว่า AI ตอบกลับมาอย่างไร / ทำไมถึงพอใจ…" value={example} onChange={e => setExample(e.target.value)} />
        </div>
        <div className="field">
          <label>หมายเหตุ / คำแนะนำการใช้สำหรับนักเรียน <span className="subtle" style={{ fontWeight: 400 }}>(ไม่บังคับ)</span></label>
          <textarea className="textarea" style={{ minHeight: 60 }} placeholder="เช่น ให้นักเรียนลองปรับ prompt ให้ตรงกับหัวข้อตัวเอง…" value={note} onChange={e => setNote(e.target.value)} />
        </div>
      </div>

      {isWork && (
        <label style={{ display: 'flex', alignItems: 'flex-start', gap: 11, marginTop: 18, padding: '13px 15px', border: '1px solid var(--line-2)', borderRadius: 10, cursor: 'pointer', background: allowImprove ? 'var(--accent-soft)' : 'var(--card)', borderColor: allowImprove ? 'var(--ex-border)' : 'var(--line-2)' }}>
          <input type="checkbox" checked={allowImprove} onChange={e => setAllowImprove(e.target.checked)} style={{ marginTop: 3, width: 17, height: 17, accentColor: 'var(--accent)' }} />
          <div>
            <div style={{ fontWeight: 700, color: 'var(--heading)', fontSize: 14 }}>เปิดให้นักเรียนปรับแต่ง prompt ได้</div>
            <div className="subtle" style={{ fontSize: 12.5, marginTop: 2 }}>นักเรียนสามารถค้นคว้าหา prompt ที่ให้ผลลัพธ์ดีกว่า แล้วระบุ prompt + AI ที่ใช้ตอนส่งงาน</div>
          </div>
        </label>
      )}
    </Modal>
  );
}

/* ---------------- LESSON DETAIL ---------------- */
function LessonPage({ role, route, go }) {
  const D = window.DATA;
  const c = D.COURSES.find(x => x.id === route.courseId);
  const lesson = (D.LESSONS[c.id] || []).find(l => l.id === route.lessonId);
  if (!lesson) return <div className="content"><div className="empty">ไม่พบเนื้อหา</div></div>;

  return (
    <div className="content" style={{ maxWidth: 880 }}>
      <div className="breadcrumb">
        <a onClick={() => go({ screen: 'courses' })}>รายวิชา</a><Icon name="chevronRight" size={14} />
        <a onClick={() => go({ screen: 'course', courseId: c.id, tab: 'lessons' })}>{c.name}</a><Icon name="chevronRight" size={14} />
        <span style={{ color: 'var(--body)', fontWeight: 600 }}>บทเรียน</span>
      </div>

      <div className="card card-pad" style={{ marginBottom: 20 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
          <span className="badge green">{lesson.week}</span>
          <span className="badge gray">เนื้อหาบทเรียน</span>
          {role === 'teacher' && <button className="btn btn-sm btn-ghost" style={{ marginLeft: 'auto' }}><Icon name="edit" size={15} /> แก้ไข</button>}
        </div>
        <h1 style={{ fontSize: 25, marginBottom: 12 }}>{lesson.title}</h1>
        <p style={{ color: 'var(--body)', fontSize: 15, lineHeight: 1.7, margin: 0 }}>{lesson.desc}</p>

        {lesson.materials.length > 0 && <>
          <hr className="divider" />
          <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--heading)', marginBottom: 12 }}>เอกสารประกอบ</div>
          <div className="row wrap">
            {lesson.materials.map((m, i) => (
              <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 11, padding: '11px 14px', border: '1px solid var(--line-2)', borderRadius: 10, minWidth: 230, cursor: 'pointer' }}>
                <FileBadge type={m.type} />
                <span style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--heading)' }}>{m.name}</span>
                <Icon name="download" size={17} color="var(--muted)" style={{ marginLeft: 'auto' }} />
              </div>
            ))}
          </div>
        </>}
      </div>

      {/* AI PROMPT for self-study */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginBottom: 12 }}>
        <Icon name="robot" size={20} color="var(--primary)" />
        <h2 style={{ fontSize: 18 }}>ค้นคว้าต่อยอดด้วย AI</h2>
      </div>
      <p className="subtle" style={{ fontSize: 14, marginTop: -4, marginBottom: 16 }}>
        ครูทดลองใช้ prompt นี้แล้วได้ผลลัพธ์น่าพอใจ — คัดลอกไปลองใช้ แล้วปรับให้เข้ากับสิ่งที่คุณอยากรู้เพิ่มเติม
      </p>
      <PromptBlock block={lesson.prompt} title="Prompt สำหรับค้นคว้าเพิ่มเติม" />

      <div style={{ display: 'flex', gap: 10, marginTop: 22 }}>
        <button className="btn btn-ghost" onClick={() => go({ screen: 'course', courseId: c.id, tab: 'lessons' })}><Icon name="arrowLeft" size={17} /> กลับไปหน้าบทเรียน</button>
      </div>
    </div>
  );
}

Object.assign(window, { AddContentModal, LessonPage });
