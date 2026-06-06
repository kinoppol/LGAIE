/* ============================================================
   data.jsx — mock data for ClassroomAI LMS
   Exposed on window.DATA
   ============================================================ */

// ---- AI registry (logo letter + brand color) ----
const AI_REGISTRY = {
  chatgpt:    { id: 'chatgpt',    name: 'ChatGPT',    letter: 'G', color: '#10a37f', url: 'chat.openai.com' },
  claude:     { id: 'claude',     name: 'Claude',     letter: 'C', color: '#d97757', url: 'claude.ai' },
  gemini:     { id: 'gemini',     name: 'Gemini',     letter: '✦', color: '#4285f4', url: 'gemini.google.com' },
  copilot:    { id: 'copilot',    name: 'Copilot',    letter: 'Co', color: '#7a5cff', url: 'copilot.microsoft.com' },
  perplexity: { id: 'perplexity', name: 'Perplexity', letter: 'P', color: '#20808d', url: 'perplexity.ai' },
};
const AI_LIST = Object.values(AI_REGISTRY);

// ---- Users ----
const TEACHER = { name: 'อ. สมหญิง วัฒนกุล', role: 'ครูผู้สอน', av: 'av-1', initials: 'สญ' };
const STUDENT = { name: 'ธนกร ใจดี', role: 'นักเรียน · ม.4/2', av: 'av-2', initials: 'ธก' };

const STUDENTS = [
  { name: 'ธนกร ใจดี', av: 'av-2', initials: 'ธก' },
  { name: 'พิมพ์ชนก ศรีสุข', av: 'av-3', initials: 'พช' },
  { name: 'ณัฐวุฒิ ทองคำ', av: 'av-4', initials: 'ณว' },
  { name: 'สุชาดา แก้วมณี', av: 'av-6', initials: 'สด' },
  { name: 'อภิสิทธิ์ บุญมา', av: 'av-5', initials: 'อส' },
];

// ---- Courses ----
const COURSES = [
  { id: 'c1', code: 'ว31104', name: 'วิทยาการคำนวณ', section: 'ม.4/2 · ห้อง 314', short: 'วค',
    banner: 'linear-gradient(120deg,#cdeee2,#e7f7f1)', ink: '#0c7a5e', color: '#2bb393', students: 32, lessons: 8, assignments: 3, progress: 64 },
  { id: 'c2', code: 'อ31202', name: 'ภาษาอังกฤษเพื่อการสื่อสาร', section: 'ม.4/2 · ห้อง 208', short: 'EN',
    banner: 'linear-gradient(120deg,#d8e3fd,#ecf1ff)', ink: '#3257c7', color: '#6b8efb', students: 34, lessons: 12, assignments: 5, progress: 48 },
  { id: 'c3', code: 'ว32241', name: 'ชีววิทยา', section: 'ม.5/1 · ห้องปฏิบัติการ 2', short: 'ชว',
    banner: 'linear-gradient(120deg,#e8dcfb,#f4edff)', ink: '#7140cf', color: '#a585f2', students: 28, lessons: 10, assignments: 4, progress: 72 },
  { id: 'c4', code: 'ส31102', name: 'ประวัติศาสตร์ไทย', section: 'ม.4/2 · ห้อง 105', short: 'ปวศ',
    banner: 'linear-gradient(120deg,#ffe6cc,#fff2e2)', ink: '#bd741a', color: '#f0a44e', students: 36, lessons: 9, assignments: 2, progress: 35 },
];

// ---- Helper to build a teacher prompt block ----
function prompt(p) { return { prompt: p.prompt, ai: p.ai, example: p.example, rating: p.rating, note: p.note }; }

// ---- Lessons (course content) with teacher prompt + AI ----
const LESSONS = {
  c1: [
    { id: 'l1', title: 'แนวคิดเชิงคำนวณ (Computational Thinking)', week: 'สัปดาห์ที่ 1',
      desc: 'ทำความเข้าใจองค์ประกอบ 4 ด้านของการคิดเชิงคำนวณ ได้แก่ การแยกย่อยปัญหา การหารูปแบบ การคิดเชิงนามธรรม และการออกแบบขั้นตอนวิธี',
      materials: [{ name: 'ใบความรู้-แนวคิดเชิงคำนวณ.pdf', type: 'pdf' }, { name: 'สไลด์บทที่ 1.pptx', type: 'ppt' }],
      prompt: prompt({
        prompt: 'อธิบายแนวคิดเชิงคำนวณ (Computational Thinking) ทั้ง 4 องค์ประกอบ สำหรับนักเรียนชั้น ม.4 พร้อมยกตัวอย่างในชีวิตประจำวันที่เข้าใจง่าย 1 ตัวอย่างต่อองค์ประกอบ และสรุปเป็นตารางสั้น ๆ',
        ai: 'chatgpt', rating: 5,
        example: 'ได้คำอธิบายเป็นภาษาที่นักเรียนเข้าใจง่าย พร้อมตัวอย่าง เช่น "การแยกย่อยปัญหา" เปรียบเหมือนการแบ่งงานทำความสะอาดบ้านเป็นห้อง ๆ และมีตารางสรุปครบทั้ง 4 ด้าน',
        note: 'แนะนำให้นักเรียนลองเปลี่ยนตัวอย่างเป็นเรื่องที่ตัวเองสนใจ เช่น เกม หรือกีฬา เพื่อให้เห็นภาพมากขึ้น' }) },
    { id: 'l2', title: 'การออกแบบอัลกอริทึมด้วย Flowchart', week: 'สัปดาห์ที่ 2',
      desc: 'เรียนรู้สัญลักษณ์ผังงาน (Flowchart) และฝึกเขียนขั้นตอนวิธีแก้ปัญหาอย่างเป็นระบบ',
      materials: [{ name: 'สัญลักษณ์ Flowchart.pdf', type: 'pdf' }],
      prompt: prompt({
        prompt: 'ช่วยออกแบบผังงาน (flowchart) สำหรับการตัดสินใจว่านักเรียนสอบผ่านหรือไม่ โดยใช้เกณฑ์คะแนนเต็ม 100 ผ่านที่ 50 คะแนน อธิบายเป็นขั้นตอนแบบข้อความที่นำไปวาดเป็นผังงานได้',
        ai: 'claude', rating: 4,
        example: 'ได้ลำดับขั้นตอนชัดเจน: เริ่ม → รับคะแนน → ตรวจสอบเงื่อนไข (คะแนน ≥ 50?) → ถ้าใช่แสดง "ผ่าน" ถ้าไม่แสดง "ไม่ผ่าน" → จบ เหมาะนำไปวาดต่อ',
        note: 'Claude อธิบายเงื่อนไขได้ละเอียด เหมาะกับการต่อยอดเป็นโค้ด ลองให้นักเรียนถามต่อว่า "แปลงเป็น pseudocode ให้หน่อย"' }) },
  ],
  c2: [
    { id: 'l3', title: 'Self-introduction & Daily Conversation', week: 'Week 1',
      desc: 'ฝึกแนะนำตัวเองและบทสนทนาในชีวิตประจำวัน เน้นการออกเสียงและความมั่นใจในการพูด',
      materials: [{ name: 'Vocabulary list - Greetings.pdf', type: 'pdf' }],
      prompt: prompt({
        prompt: 'Act as a friendly English tutor. Create a 6-line everyday conversation between two students meeting for the first time. Use simple A2-level vocabulary, then list 5 useful phrases with Thai translation.',
        ai: 'gemini', rating: 5,
        example: 'ได้บทสนทนาสั้น เป็นธรรมชาติ พร้อมคำแปลไทยของวลีสำคัญ เช่น "Nice to meet you = ยินดีที่ได้รู้จัก" นักเรียนนำไปฝึกพูดเป็นคู่ได้ทันที',
        note: 'ให้นักเรียนขอ Gemini อ่านออกเสียง (read aloud) เพื่อฝึกสำเนียง และลองเปลี่ยนสถานการณ์เป็นการสั่งอาหาร' }) },
  ],
  c3: [
    { id: 'l4', title: 'โครงสร้างและหน้าที่ของเซลล์', week: 'สัปดาห์ที่ 3',
      desc: 'ศึกษาออร์แกเนลล์ภายในเซลล์พืชและเซลล์สัตว์ พร้อมเปรียบเทียบความแตกต่าง',
      materials: [{ name: 'แผนภาพเซลล์.png', type: 'img' }, { name: 'ใบงานเซลล์.pdf', type: 'pdf' }],
      prompt: prompt({
        prompt: 'สร้างตารางเปรียบเทียบเซลล์พืชกับเซลล์สัตว์ ในหัวข้อ ผนังเซลล์ คลอโรพลาสต์ แวคิวโอล และรูปร่าง อธิบายหน้าที่ของแต่ละออร์แกเนลล์สั้น ๆ ระดับ ม.5',
        ai: 'perplexity', rating: 4,
        example: 'Perplexity ให้ตารางเปรียบเทียบพร้อมอ้างอิงแหล่งข้อมูล นักเรียนตรวจสอบความถูกต้องได้ และเห็นว่าเซลล์พืชมีผนังเซลล์-คลอโรพลาสต์ ส่วนเซลล์สัตว์ไม่มี',
        note: 'จุดเด่นคือมีลิงก์อ้างอิง เหมาะฝึกนักเรียนตรวจสอบข้อมูลก่อนเชื่อ อย่าลืมให้เทียบกับหนังสือเรียนด้วย' }) },
  ],
  c4: [
    { id: 'l5', title: 'อาณาจักรสุโขทัย: การเมืองและเศรษฐกิจ', week: 'สัปดาห์ที่ 2',
      desc: 'วิเคราะห์รูปแบบการปกครองแบบพ่อปกครองลูก และระบบเศรษฐกิจการค้าสมัยสุโขทัย',
      materials: [{ name: 'ศิลาจารึกหลักที่ 1.pdf', type: 'pdf' }],
      prompt: prompt({
        prompt: 'สรุปลักษณะการปกครองแบบ "พ่อปกครองลูก" ในสมัยสุโขทัย พร้อมยกหลักฐานจากศิลาจารึกหลักที่ 1 และเปรียบเทียบกับการปกครองสมัยอยุธยา ในรูปแบบที่เหมาะกับนักเรียน ม.4',
        ai: 'chatgpt', rating: 4,
        example: 'ได้คำสรุปกระชับ เปรียบเทียบสุโขทัย (ใกล้ชิดประชาชน) กับอยุธยา (สมมติเทพ) อย่างชัดเจน เหมาะใช้ทบทวนก่อนสอบ',
        note: 'ย้ำให้นักเรียนตรวจสอบปีและชื่อบุคคลกับหนังสือเรียนเสมอ เพราะ AI อาจคลาดเคลื่อนเรื่องรายละเอียดทางประวัติศาสตร์' }) },
  ],
};

// ---- Assignments with teacher prompt + AI, allowing student improvement ----
const ASSIGNMENTS = {
  c1: [
    { id: 'a1', courseId: 'c1', title: 'ออกแบบอัลกอริทึมแก้ปัญหาในชีวิตประจำวัน', type: 'งาน',
      due: '12 มิ.ย. 2569', dueShort: '12 มิ.ย.', points: 20, status: 'open', submitted: 18, total: 32,
      instructions: 'เลือกปัญหาในชีวิตประจำวัน 1 เรื่อง แล้วออกแบบขั้นตอนวิธี (อัลกอริทึม) เพื่อแก้ปัญหานั้น นำเสนอเป็นผังงานหรือ pseudocode พร้อมอธิบายเหตุผล',
      prompt: prompt({
        prompt: 'ช่วยออกแบบอัลกอริทึมแบบ pseudocode สำหรับการจัดลำดับการทำการบ้านหลายวิชาให้เสร็จทันกำหนดส่ง โดยพิจารณาจากกำหนดส่งและความยากของแต่ละวิชา อธิบายแต่ละขั้นตอน',
        ai: 'claude', rating: 4,
        example: 'ได้ pseudocode ที่เรียงงานตาม deadline และถ่วงน้ำหนักความยาก พร้อมคำอธิบายตรรกะ นักเรียนนำไปปรับใช้กับงานจริงได้',
        note: 'นี่เป็นเพียง prompt เริ่มต้น — นักเรียนควรปรับแต่งให้ตรงกับปัญหาที่ตนเลือก และทดลองกับ AI หลายตัวเพื่อหาคำตอบที่ดีที่สุด' }),
      allowImprove: true },
    { id: 'a2', courseId: 'c1', title: 'แบบฝึกหัด: เขียน Flowchart 3 สถานการณ์', type: 'การบ้าน',
      due: '8 มิ.ย. 2569', dueShort: '8 มิ.ย.', points: 10, status: 'open', submitted: 25, total: 32,
      instructions: 'เขียนผังงานสำหรับ 3 สถานการณ์ที่กำหนด ส่งเป็นไฟล์ภาพหรือ PDF',
      prompt: prompt({
        prompt: 'อธิบายวิธีเขียน flowchart สำหรับการตรวจสอบว่าเลขที่ป้อนเข้ามาเป็นเลขคู่หรือเลขคี่ แสดงเป็นลำดับขั้นตอนที่นำไปวาดได้',
        ai: 'chatgpt', rating: 5,
        example: 'อธิบายการใช้ตัวดำเนินการ mod (เศษจากการหาร 2) เพื่อแยกคู่/คี่ ชัดเจน เหมาะเป็นตัวอย่างตั้งต้น',
        note: 'ลองให้นักเรียนถามต่อว่า "ถ้าต้องตรวจสอบหลายเลขพร้อมกันต้องปรับผังงานอย่างไร"' }),
      allowImprove: true },
  ],
  c2: [
    { id: 'a3', courseId: 'c2', title: 'Write a 120-word email to a pen pal', type: 'งาน',
      due: '15 มิ.ย. 2569', dueShort: '15 มิ.ย.', points: 25, status: 'open', submitted: 12, total: 34,
      instructions: 'เขียนอีเมลแนะนำตัวเองถึงเพื่อนต่างชาติ ความยาวประมาณ 120 คำ ใช้ Present Simple และ Present Continuous อย่างถูกต้อง',
      prompt: prompt({
        prompt: 'I am a Thai grade-10 student. Help me brainstorm 5 interesting things to write about myself in an email to a foreign pen pal. Then show me how to start and end an informal email politely in English.',
        ai: 'gemini', rating: 5,
        example: 'ได้ไอเดียหัวข้อ 5 อย่าง (งานอดิเรก อาหารโปรด ครอบครัว ฯลฯ) และโครงสร้างเปิด-ปิดอีเมล นักเรียนนำไปเรียบเรียงเป็นภาษาของตัวเอง',
        note: 'สำคัญ: ใช้ AI เพื่อ "หาไอเดียและตรวจไวยากรณ์" เท่านั้น ห้ามให้ AI เขียนทั้งฉบับแทน — ครูจะดูสำนวนเฉพาะตัวของนักเรียน' }),
      allowImprove: true },
  ],
  c3: [
    { id: 'a4', courseId: 'c3', title: 'รายงาน: เปรียบเทียบการลำเลียงสารผ่านเยื่อหุ้มเซลล์', type: 'งาน',
      due: '18 มิ.ย. 2569', dueShort: '18 มิ.ย.', points: 30, status: 'open', submitted: 8, total: 28,
      instructions: 'เขียนรายงาน 1 หน้า เปรียบเทียบการแพร่ การออสโมซิส และการลำเลียงแบบใช้พลังงาน พร้อมยกตัวอย่างในสิ่งมีชีวิต',
      prompt: prompt({
        prompt: 'อธิบายความแตกต่างระหว่าง diffusion, osmosis และ active transport ในการลำเลียงสารผ่านเยื่อหุ้มเซลล์ พร้อมยกตัวอย่างในร่างกายมนุษย์ ระดับ ม.5 และจัดเป็นหัวข้อให้เขียนรายงานต่อได้',
        ai: 'perplexity', rating: 4,
        example: 'ได้คำอธิบายพร้อมแหล่งอ้างอิง แยกประเภทการลำเลียงชัดเจน (ใช้/ไม่ใช้พลังงาน) นักเรียนตรวจสอบความถูกต้องจาก citation ได้',
        note: 'ให้นักเรียนเปรียบเทียบคำตอบจาก AI หลายตัว แล้วเลือกข้อมูลที่ตรงกับหนังสือเรียนมากที่สุดมาอ้างอิง' }),
      allowImprove: true },
  ],
  c4: [
    { id: 'a5', courseId: 'c4', title: 'วิเคราะห์ปัจจัยการล่มสลายของกรุงศรีอยุธยา', type: 'งาน',
      due: '20 มิ.ย. 2569', dueShort: '20 มิ.ย.', points: 20, status: 'open', submitted: 5, total: 36,
      instructions: 'วิเคราะห์ปัจจัยทั้งภายในและภายนอกที่นำไปสู่การเสียกรุงศรีอยุธยาครั้งที่ 2 พร้อมแสดงความคิดเห็น',
      prompt: prompt({
        prompt: 'สรุปปัจจัยภายในและภายนอกที่ทำให้กรุงศรีอยุธยาเสียกรุงครั้งที่ 2 (พ.ศ. 2310) แยกเป็นหัวข้อ พร้อมอธิบายเหตุผลแต่ละข้อ ในระดับที่นักเรียน ม.4 เข้าใจได้',
        ai: 'chatgpt', rating: 3,
        example: 'ได้กรอบปัจจัยภายใน (ความขัดแย้งภายใน การเมือง) และภายนอก (การรุกรานของพม่า) แต่ต้องตรวจสอบรายละเอียดปีและเหตุการณ์เพิ่มเติม',
        note: 'AI ให้กรอบความคิดได้ดี แต่ความแม่นยำทางประวัติศาสตร์ยังต้องตรวจสอบ — เป็นโจทย์ที่ดีให้นักเรียนปรับ prompt และเทียบกับ Perplexity ที่มีอ้างอิง' }),
      allowImprove: true },
  ],
};

// ---- Student submissions (for teacher grading view & student's own submitted view) ----
const SUBMISSIONS = {
  a1: [
    { id: 's1', student: STUDENTS[1], status: 'graded', grade: 19, submittedAt: '10 มิ.ย. 14:20',
      promptUsed: 'ออกแบบอัลกอริทึม pseudocode สำหรับจัดลำดับการรดน้ำต้นไม้ 5 ชนิดที่ต้องการน้ำต่างกัน โดยพิจารณาความถี่และปริมาณน้ำ อธิบายเหตุผลแต่ละขั้นตอนแบบเข้าใจง่าย และเพิ่มเงื่อนไขกรณีฝนตก',
      aiUsed: 'claude', betterThanTeacher: true, votes: 7,
      result: 'ได้ pseudocode ที่ตรวจสอบสภาพอากาศก่อน ถ้าฝนตกให้ข้ามการรดน้ำวันนั้น และจัดลำดับต้นไม้ตามความถี่ที่ต้องการน้ำ ครบถ้วนกว่าตัวอย่างของครูเพราะเพิ่มกรณีพิเศษ',
      compareNote: 'ผมเพิ่มเงื่อนไข "ถ้าฝนตก" เข้าไปจาก prompt เดิมของครู ทำให้อัลกอริทึมใช้ได้จริงมากขึ้นครับ',
      feedback: 'เยี่ยมมาก! การเพิ่มเงื่อนไขกรณีฝนตกแสดงถึงการคิดเชิงระบบที่ดี prompt ปรับได้ตรงประเด็น' },
    { id: 's2', student: STUDENTS[2], status: 'submitted', grade: null, submittedAt: '11 มิ.ย. 09:05',
      promptUsed: 'ช่วยออกแบบ pseudocode จัดลำดับการทำการบ้าน 4 วิชาตามวันส่งและความยาก ให้เสร็จก่อนกำหนด พร้อมแสดงตารางเวลาที่แนะนำ',
      aiUsed: 'gemini', betterThanTeacher: false, votes: 2,
      result: 'ได้ pseudocode เรียงงานตาม deadline และเพิ่มตารางเวลารายวัน แต่ยังไม่ได้ถ่วงน้ำหนักความยากชัดเจนเท่าตัวอย่างของครู',
      compareNote: 'ผมลองใช้ Gemini เพราะอยากได้ตารางเวลาด้วย แต่ส่วนการถ่วงน้ำหนักความยากยังสู้ของครูไม่ได้ครับ',
      feedback: null },
    { id: 's3', student: STUDENTS[3], status: 'submitted', grade: null, submittedAt: '11 มิ.ย. 16:40',
      promptUsed: 'Design a pseudocode algorithm to plan my exam revision across 6 subjects within 10 days, prioritizing by exam date and my confidence level (1-5). Explain each step in Thai.',
      aiUsed: 'chatgpt', betterThanTeacher: true, votes: 5,
      result: 'ได้อัลกอริทึมที่ใช้ทั้งวันสอบและระดับความมั่นใจของตัวเองมาคำนวณ ทำให้แผนอ่านหนังสือสมจริง เหมาะกับสถานการณ์ของหนูมากกว่าตัวอย่างเดิม',
      compareNote: 'หนูเพิ่ม "ระดับความมั่นใจ 1-5" เป็นตัวแปรเข้าไป ทำให้ AI จัดลำดับได้ตรงกับความถนัดจริง ๆ ค่ะ',
      feedback: null },
  ],
};

window.DATA = {
  AI_REGISTRY, AI_LIST, TEACHER, STUDENT, STUDENTS,
  COURSES, LESSONS, ASSIGNMENTS, SUBMISSIONS,
};
