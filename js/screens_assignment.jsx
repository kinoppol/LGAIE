/* ============================================================
   screens_assignment.jsx — Assignment detail, student submit, teacher grading
   ============================================================ */

/* ---- Student submission composer ---- */
function SubmitPanel({ assignment, onSubmit }) {
  const D = window.DATA;
  const [promptUsed, setPromptUsed] = React.useState('');
  const [ai, setAi] = React.useState('claude');
  const [result, setResult] = React.useState('');
  const [compareNote, setCompareNote] = React.useState('');
  const [better, setBetter] = React.useState(false);
  const [answer, setAnswer] = React.useState('');
  const canSubmit = answer.trim() && promptUsed.trim();

  return (
    <div className="card" style={{ border: '2px solid var(--accent-soft)' }}>
      <div className="card-head" style={{ background: 'var(--accent-soft)', borderBottom: '1px solid #d4e3fc' }}>
        <span style={{ width: 34, height: 34, borderRadius: 9, background: '#fff', color: 'var(--accent)', display: 'grid', placeItems: 'center' }}><Icon name="send" size={18} /></span>
        <h3 style={{ color: 'var(--accent-700)' }}>ส่งงานของคุณ</h3>
        <span className="badge orange" style={{ marginLeft: 'auto' }}><Icon name="clock" size={13} /> กำหนดส่ง {assignment.dueShort}</span>
      </div>
      <div className="card-pad">
        <div className="field">
          <label>คำตอบ / ผลงานของคุณ <span style={{ color: 'var(--danger)' }}>*</span></label>
          <textarea className="textarea" placeholder="เขียนคำตอบหรือสรุปผลงานของคุณที่นี่… (หรือแนบไฟล์ด้านล่าง)" value={answer} onChange={e => setAnswer(e.target.value)} />
          <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
            <button className="btn btn-sm btn-ghost"><Icon name="file" size={15} /> แนบไฟล์</button>
            <button className="btn btn-sm btn-ghost"><Icon name="folder" size={15} /> Google Drive</button>
          </div>
        </div>

        <div className="ai-tint-box" style={{ padding: 16, marginTop: 6 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginBottom: 14 }}>
            <span style={{ width: 30, height: 30, borderRadius: 8, background: 'var(--card)', color: 'var(--primary)', display: 'grid', placeItems: 'center' }}><Icon name="sparkle" size={17} /></span>
            <div>
              <div style={{ fontWeight: 700, color: 'var(--heading)', fontSize: 14 }}>ระบุ AI และ Prompt ที่คุณใช้</div>
              <div className="subtle" style={{ fontSize: 12 }}>บอกครูว่าคุณค้นคว้าด้วย prompt อะไร และ AI ตัวไหนตอบได้ดีที่สุด</div>
            </div>
          </div>

          <div className="field">
            <label>Prompt ที่คุณใช้ <span style={{ color: 'var(--danger)' }}>*</span></label>
            <textarea className="textarea" style={{ fontFamily: 'ui-monospace, monospace', fontSize: 13, minHeight: 80 }} placeholder="วาง prompt ที่คุณปรับแต่งและใช้จริง…" value={promptUsed} onChange={e => setPromptUsed(e.target.value)} />
            <div className="hint">เคล็ดลับ: ลองเริ่มจาก prompt ของครูแล้วปรับให้ตรงกับสิ่งที่คุณเลือก</div>
          </div>
          <div className="field">
            <label>AI ที่ให้คำตอบดีที่สุด</label>
            <AISelect value={ai} onChange={setAi} />
          </div>
          <div className="field" style={{ marginBottom: 6 }}>
            <label>ผลลัพธ์ที่ได้จาก AI <span className="subtle" style={{ fontWeight: 400 }}>(ไม่บังคับ)</span></label>
            <textarea className="textarea" style={{ minHeight: 60 }} placeholder="สรุปสั้น ๆ ว่า AI ตอบกลับมาอย่างไร…" value={result} onChange={e => setResult(e.target.value)} />
          </div>
        </div>

        {assignment.allowImprove && (
          <label style={{ display: 'flex', alignItems: 'flex-start', gap: 11, marginTop: 16, padding: '14px 16px', border: '1px solid', borderColor: better ? 'var(--primary-soft-2)' : 'var(--line-2)', borderRadius: 10, cursor: 'pointer', background: better ? 'var(--primary-soft)' : 'var(--card)' }}>
            <input type="checkbox" checked={better} onChange={e => setBetter(e.target.checked)} style={{ marginTop: 3, width: 17, height: 17, accentColor: 'var(--primary)' }} />
            <div style={{ flex: 1 }}>
              <div style={{ fontWeight: 700, color: 'var(--heading)', fontSize: 14, display: 'flex', alignItems: 'center', gap: 7 }}><Icon name="trophy" size={16} color="var(--warn)" /> prompt ของฉันให้ผลลัพธ์ดีกว่าของครู</div>
              <div className="subtle" style={{ fontSize: 12.5, marginTop: 3 }}>ถ้าคุณคิดว่า prompt ที่ปรับแต่งดีกว่า บอกเหตุผลให้เพื่อนและครูโหวตได้</div>
              {better && <textarea className="textarea animate-in" style={{ minHeight: 56, marginTop: 10 }} placeholder="อธิบายว่าทำไม prompt ของคุณถึงดีกว่า เช่น เพิ่มเงื่อนไข / เจาะจงมากขึ้น…" value={compareNote} onChange={e => setCompareNote(e.target.value)} />}
            </div>
          </label>
        )}

        <div style={{ display: 'flex', gap: 10, marginTop: 18 }}>
          <button className={'btn ' + (canSubmit ? 'btn-accent' : 'btn-ghost')} disabled={!canSubmit} style={!canSubmit ? { opacity: .5, cursor: 'not-allowed' } : null}
            onClick={() => onSubmit({ promptUsed, ai, result, compareNote, better, answer })}>
            <Icon name="send" size={17} color={canSubmit ? '#fff' : 'currentColor'} /> ส่งงาน
          </button>
          <button className="btn btn-ghost">บันทึกร่าง</button>
        </div>
      </div>
    </div>
  );
}

/* ---- Student's submitted card (after submit) ---- */
function SubmittedCard({ sub }) {
  const D = window.DATA;
  return (
    <div className="card animate-in">
      <div className="card-head" style={{ background: 'var(--primary-soft)', borderBottom: '1px solid var(--primary-soft-2)' }}>
        <Icon name="checkCircle" size={20} color="var(--primary)" />
        <h3 style={{ color: 'var(--primary-700)' }}>ส่งงานเรียบร้อยแล้ว</h3>
        <span className="badge green" style={{ marginLeft: 'auto' }}>รอตรวจ</span>
      </div>
      <div className="card-pad">
        <div className="field" style={{ marginBottom: 14 }}>
          <label>คำตอบที่ส่ง</label>
          <div style={{ fontSize: 14, color: 'var(--body)', lineHeight: 1.6 }}>{sub.answer || '— แนบไฟล์ —'}</div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 8 }}>
          <span className="subtle" style={{ fontSize: 12.5, fontWeight: 600 }}>AI ที่ใช้</span>
          <AIPill id={sub.ai} size="sm" />
          {sub.better && <span className="badge orange"><Icon name="trophy" size={13} /> ระบุว่าดีกว่า prompt ครู</span>}
        </div>
        <div className="prompt-text" style={{ marginTop: 6 }}>{sub.promptUsed}</div>
      </div>
    </div>
  );
}

/* ---- Teacher: one submission row in the grading list ---- */
function SubmissionRow({ sub, onGrade }) {
  const D = window.DATA;
  return (
    <div className="card" style={{ marginBottom: 14 }}>
      <div style={{ padding: '16px 20px', display: 'flex', alignItems: 'center', gap: 13, borderBottom: '1px solid var(--line)' }}>
        <Avatar user={sub.student} size={40} />
        <div><div style={{ fontWeight: 700, color: 'var(--heading)' }}>{sub.student.name}</div><div className="subtle" style={{ fontSize: 12.5 }}>ส่งเมื่อ {sub.submittedAt}</div></div>
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
          {sub.betterThanTeacher && <span className="badge orange"><Icon name="trophy" size={13} /> เคลม prompt ดีกว่า</span>}
          {sub.status === 'graded'
            ? <span className="badge green"><Icon name="check" size={13} /> ให้คะแนนแล้ว · {sub.grade}/20</span>
            : <span className="badge gray">รอตรวจ</span>}
        </div>
      </div>
      <div style={{ padding: '14px 20px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 8, flexWrap: 'wrap' }}>
          <span className="subtle" style={{ fontSize: 12.5, fontWeight: 600 }}>Prompt ที่นักเรียนใช้ · ตอบดีที่สุดด้วย</span>
          <AIPill id={sub.aiUsed} size="sm" />
          <span className="chip" style={{ fontSize: 11.5 }}><Icon name="thumbsUp" size={13} color="var(--accent)" /> {sub.votes} โหวต</span>
        </div>
        <div className="prompt-text" style={{ fontSize: 12.5 }}>{sub.promptUsed}</div>
        {sub.compareNote && (
          <div style={{ marginTop: 10, fontSize: 13, color: 'var(--body)', display: 'flex', gap: 8, alignItems: 'flex-start' }}>
            <Icon name="message" size={15} color="var(--muted)" style={{ marginTop: 2, flex: '0 0 auto' }} /> <i>"{sub.compareNote}"</i>
          </div>
        )}
        <div style={{ display: 'flex', gap: 10, marginTop: 14 }}>
          <button className={'btn btn-sm ' + (sub.status === 'graded' ? 'btn-ghost' : 'btn-primary')} onClick={() => onGrade(sub)}>
            <Icon name={sub.status === 'graded' ? 'edit' : 'check'} size={15} color={sub.status === 'graded' ? 'currentColor' : '#fff'} /> {sub.status === 'graded' ? 'แก้ไขคะแนน' : 'ตรวจและให้คะแนน'}
          </button>
          <button className="btn btn-sm btn-ghost"><Icon name="thumbsUp" size={15} /> โหวตว่า prompt ดี</button>
        </div>
      </div>
    </div>
  );
}

/* ---- Teacher grading modal ---- */
function GradeModal({ sub, assignment, onClose, onSave }) {
  const [grade, setGrade] = React.useState(sub.grade || '');
  const [feedback, setFeedback] = React.useState(sub.feedback || '');
  return (
    <Modal title={'ตรวจงาน: ' + sub.student.name} icon="check" onClose={onClose}
      footer={<>
        <button className="btn btn-ghost" onClick={onClose}>ยกเลิก</button>
        <button className="btn btn-primary" onClick={() => onSave(grade, feedback)}><Icon name="check" size={16} color="#fff" /> บันทึกคะแนน</button>
      </>}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 18 }}>
        <Avatar user={sub.student} size={44} /><div><div style={{ fontWeight: 700, color: 'var(--heading)' }}>{sub.student.name}</div><div className="subtle" style={{ fontSize: 12.5 }}>ส่งเมื่อ {sub.submittedAt}</div></div>
      </div>
      <div className="field">
        <label>ผลลัพธ์ที่นักเรียนได้จาก AI</label>
        <div style={{ fontSize: 13.5, color: 'var(--body)', lineHeight: 1.6, background: 'var(--surface-2)', border: '1px solid var(--line)', borderRadius: 9, padding: '11px 13px' }}>{sub.result}</div>
      </div>
      <div className="row" style={{ gap: 14 }}>
        <div className="field" style={{ flex: '0 0 160px' }}>
          <label>คะแนน (เต็ม {assignment.points})</label>
          <input className="input" type="number" max={assignment.points} value={grade} onChange={e => setGrade(e.target.value)} placeholder="0" style={{ fontSize: 18, fontWeight: 700 }} />
        </div>
        <div className="field" style={{ flex: 1 }}>
          <label>AI ที่นักเรียนใช้</label>
          <div style={{ height: 44, display: 'flex', alignItems: 'center' }}><AIPill id={sub.aiUsed} /></div>
        </div>
      </div>
      <div className="field" style={{ marginBottom: 0 }}>
        <label>ความคิดเห็น / ข้อเสนอแนะ</label>
        <textarea className="textarea" placeholder="ให้ข้อเสนอแนะแก่นักเรียน…" value={feedback} onChange={e => setFeedback(e.target.value)} />
      </div>
    </Modal>
  );
}

function AssignmentPage({ role, route, go, toast }) {
  const D = window.DATA;
  const c = D.COURSES.find(x => x.id === route.courseId);
  const assignment = (D.ASSIGNMENTS[c.id] || []).find(a => a.id === route.assignmentId);
  const [submitted, setSubmitted] = React.useState(null);
  const [grading, setGrading] = React.useState(null);
  const [subs, setSubs] = React.useState(D.SUBMISSIONS[assignment?.id] || []);
  if (!assignment) return <div className="content"><div className="empty">ไม่พบงาน</div></div>;

  const handleSubmit = (data) => { setSubmitted(data); toast('ส่งงานเรียบร้อยแล้ว!'); window.scrollTo({ top: 0, behavior: 'smooth' }); };
  const saveGrade = (grade, feedback) => {
    setSubs(subs.map(s => s.id === grading.id ? { ...s, grade: Number(grade), feedback, status: 'graded' } : s));
    setGrading(null); toast('บันทึกคะแนนแล้ว');
  };

  const gradedCount = subs.filter(s => s.status === 'graded').length;

  return (
    <div className="content" style={{ maxWidth: role === 'teacher' ? 1100 : 900 }}>
      <div className="breadcrumb">
        <a onClick={() => go({ screen: 'courses' })}>รายวิชา</a><Icon name="chevronRight" size={14} />
        <a onClick={() => go({ screen: 'course', courseId: c.id, tab: 'work' })}>{c.name}</a><Icon name="chevronRight" size={14} />
        <span style={{ color: 'var(--body)', fontWeight: 600 }}>งาน</span>
      </div>

      {/* assignment header */}
      <div className="card card-pad" style={{ marginBottom: 20 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginBottom: 12, flexWrap: 'wrap' }}>
          <span className="badge orange">{assignment.type}</span>
          <span className="badge gray">{assignment.points} คะแนน</span>
          {assignment.allowImprove && <span className="badge blue"><Icon name="sparkle" size={12} /> ปรับ prompt ได้</span>}
          <span className="badge orange" style={{ marginLeft: 'auto' }}><Icon name="clock" size={13} /> กำหนดส่ง {assignment.due}</span>
        </div>
        <h1 style={{ fontSize: 24, marginBottom: 12 }}>{assignment.title}</h1>
        <p style={{ color: 'var(--body)', fontSize: 15, lineHeight: 1.7, margin: 0 }}>{assignment.instructions}</p>
        {role === 'teacher' && (
          <>
            <hr className="divider" />
            <div style={{ display: 'flex', gap: 24, flexWrap: 'wrap' }}>
              <div className="stat" style={{ gap: 11 }}><span className="stat-ic" style={{ background: 'var(--accent-soft)', color: 'var(--accent)', width: 40, height: 40 }}><Icon name="send" size={19} /></span><div><div className="stat-val" style={{ fontSize: 19 }}>{assignment.submitted}/{assignment.total}</div><div className="stat-lbl">ส่งแล้ว</div></div></div>
              <div className="stat" style={{ gap: 11 }}><span className="stat-ic" style={{ background: 'var(--primary-soft)', color: 'var(--primary)', width: 40, height: 40 }}><Icon name="check" size={19} /></span><div><div className="stat-val" style={{ fontSize: 19 }}>{gradedCount}</div><div className="stat-lbl">ตรวจแล้ว</div></div></div>
              <div className="stat" style={{ gap: 11 }}><span className="stat-ic" style={{ background: 'var(--warn-soft)', color: '#c76a13', width: 40, height: 40 }}><Icon name="trophy" size={19} /></span><div><div className="stat-val" style={{ fontSize: 19 }}>{subs.filter(s=>s.betterThanTeacher).length}</div><div className="stat-lbl">เคลม prompt ดีกว่า</div></div></div>
            </div>
          </>
        )}
      </div>

      {/* teacher's recommended prompt — both roles see it */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 9, marginBottom: 12 }}>
        <Icon name="sparkle" size={20} color="var(--primary)" />
        <h2 style={{ fontSize: 18 }}>Prompt ตั้งต้นจากครู</h2>
      </div>
      <p className="subtle" style={{ fontSize: 14, marginTop: -4, marginBottom: 16 }}>
        {role === 'teacher' ? 'นี่คือ prompt ที่คุณแนบไว้ให้นักเรียนเริ่มต้น' : 'ครูทดลองแล้วได้ผลพอใช้ — ลองปรับแต่งให้ดีกว่านี้ แล้วระบุ prompt + AI ที่คุณใช้ตอนส่ง'}
      </p>
      <PromptBlock block={assignment.prompt} title="Prompt ตั้งต้นที่ครูแนะนำ" />

      <hr className="divider" style={{ margin: '28px 0' }} />

      {/* role-specific bottom */}
      {role === 'student' ? (
        submitted ? <SubmittedCard sub={submitted} /> : <SubmitPanel assignment={assignment} onSubmit={handleSubmit} />
      ) : (
        <div>
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 16 }}>
            <h2 style={{ fontSize: 18 }}>งานที่นักเรียนส่ง <span className="subtle" style={{ fontSize: 15, fontWeight: 600 }}>({subs.length})</span></h2>
            <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
              <span className="chip"><span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--warn)' }}></span> รอตรวจ {subs.filter(s=>s.status!=='graded').length}</span>
              <span className="chip"><span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--primary)' }}></span> ตรวจแล้ว {gradedCount}</span>
            </div>
          </div>
          {subs.map(s => <SubmissionRow key={s.id} sub={s} onGrade={setGrading} />)}
          {subs.length === 0 && <div className="empty"><div className="e-ic"><Icon name="clipboard" size={30} /></div><h3>ยังไม่มีงานส่ง</h3></div>}
        </div>
      )}

      {grading && <GradeModal sub={grading} assignment={assignment} onClose={() => setGrading(null)} onSave={saveGrade} />}
    </div>
  );
}

Object.assign(window, { AssignmentPage, SubmitPanel, SubmissionRow, GradeModal, SubmittedCard });
