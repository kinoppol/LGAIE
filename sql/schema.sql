-- ============================================================
-- ClassroomAI — Database Schema + Seed Data
-- MariaDB 10+ / MySQL 8+ compatible
-- ============================================================

CREATE DATABASE IF NOT EXISTS classroomai
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE classroomai;

-- -------- AI Tools Registry --------
CREATE TABLE IF NOT EXISTS ai_tools (
  id      VARCHAR(20)  PRIMARY KEY,
  name    VARCHAR(50)  NOT NULL,
  letter  VARCHAR(5)   NOT NULL,
  color   VARCHAR(20)  NOT NULL,
  url     VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Users --------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  role          ENUM('teacher','student','admin') NOT NULL,
  avatar_class  VARCHAR(10)  DEFAULT 'av-1',
  initials      VARCHAR(5)   NOT NULL,
  email         VARCHAR(150) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  phone         VARCHAR(20)  NULL,
  school        VARCHAR(200) NULL,
  province      VARCHAR(100) NULL,
  status        ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Courses --------
CREATE TABLE IF NOT EXISTS courses (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(20)  NOT NULL,
  name            VARCHAR(200) NOT NULL,
  section         VARCHAR(100) NOT NULL,
  short_name      VARCHAR(10)  NOT NULL,
  banner          VARCHAR(500) NOT NULL,
  ink_color       VARCHAR(20)  NOT NULL,
  primary_color   VARCHAR(20)  NOT NULL,
  teacher_id      INT UNSIGNED NOT NULL,
  is_public       TINYINT(1)   DEFAULT 0  COMMENT 'Enrollable without invite',
  is_template     TINYINT(1)   DEFAULT 0  COMMENT 'Can be used as course template',
  template_id     INT UNSIGNED NULL       COMMENT 'Source template course id',
  template_secret CHAR(12)     NULL       COMMENT 'Secret code for others to clone',
  FOREIGN KEY (teacher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Enrollments --------
CREATE TABLE IF NOT EXISTS course_enrollments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  join_type  ENUM('direct','invite_link','invite_code','invite_email','self','template') DEFAULT 'direct',
  joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  UNIQUE KEY uq_enroll (course_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Course Invites --------
CREATE TABLE IF NOT EXISTS course_invites (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id     INT UNSIGNED NOT NULL,
  invite_type   ENUM('link','code','email') NOT NULL DEFAULT 'code',
  invite_token  VARCHAR(40)  NULL UNIQUE  COMMENT 'URL token for link-type invites',
  invite_code   VARCHAR(10)  NULL UNIQUE  COMMENT 'Short code shown to students',
  invited_email VARCHAR(150) NULL         COMMENT 'For email-specific invites',
  created_by    INT UNSIGNED NOT NULL,
  expires_at    DATETIME     NULL,
  max_uses      INT UNSIGNED NULL,
  use_count     INT UNSIGNED DEFAULT 0,
  is_active     TINYINT(1)   DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Course Co-Teachers --------
CREATE TABLE IF NOT EXISTS course_teachers (
  course_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  added_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (course_id, user_id),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Lessons --------
CREATE TABLE IF NOT EXISTS lessons (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id   INT UNSIGNED NOT NULL,
  title       VARCHAR(300) NOT NULL,
  week_label  VARCHAR(50)  NOT NULL,
  description TEXT         NOT NULL,
  sort_order  INT DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Lesson Materials --------
CREATE TABLE IF NOT EXISTS lesson_materials (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(200) NOT NULL,
  file_type   VARCHAR(10)  NOT NULL,
  file_path   VARCHAR(255) NULL,
  file_size   INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Lesson AI Prompts --------
CREATE TABLE IF NOT EXISTS lesson_prompts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lesson_id    INT UNSIGNED NOT NULL UNIQUE,
  prompt_text  TEXT         NOT NULL,
  ai_id        VARCHAR(20)  NOT NULL,
  rating       TINYINT UNSIGNED DEFAULT 3,
  example_text TEXT,
  note_text    TEXT,
  FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  FOREIGN KEY (ai_id)     REFERENCES ai_tools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Assignments --------
CREATE TABLE IF NOT EXISTS assignments (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id       INT UNSIGNED NOT NULL,
  title           VARCHAR(300) NOT NULL,
  assignment_type VARCHAR(20)  DEFAULT 'งาน',
  due_date        VARCHAR(50)  NOT NULL,
  due_short       VARCHAR(20)  NOT NULL,
  points          INT UNSIGNED DEFAULT 10,
  status          VARCHAR(20)  DEFAULT 'open',
  instructions    TEXT         NOT NULL,
  allow_improve   TINYINT(1)   DEFAULT 1,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Assignment AI Prompts --------
CREATE TABLE IF NOT EXISTS assignment_prompts (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id   INT UNSIGNED NOT NULL UNIQUE,
  prompt_text     TEXT         NOT NULL,
  ai_id           VARCHAR(20)  NOT NULL,
  rating          TINYINT UNSIGNED DEFAULT 3,
  example_text    TEXT,
  note_text       TEXT,
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (ai_id)         REFERENCES ai_tools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Submissions --------
CREATE TABLE IF NOT EXISTS submissions (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id       INT UNSIGNED NOT NULL,
  student_id          INT UNSIGNED NOT NULL,
  answer_text         TEXT,
  prompt_used         TEXT         NOT NULL,
  ai_used             VARCHAR(20)  NOT NULL,
  better_than_teacher TINYINT(1)   DEFAULT 0,
  compare_note        TEXT,
  result_text         TEXT,
  status              ENUM('submitted','graded') DEFAULT 'submitted',
  grade               INT,
  feedback            TEXT,
  submitted_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id)    REFERENCES users(id)       ON DELETE CASCADE,
  UNIQUE KEY uq_submission (assignment_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Submission Files (ไฟล์แนบงานที่นักเรียนส่ง) --------
CREATE TABLE IF NOT EXISTS submission_files (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id INT UNSIGNED NOT NULL,
  name          VARCHAR(255) NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  file_type     VARCHAR(10)  NOT NULL,
  file_size     INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- App Settings (ค่ากลางที่ admin กำหนด) --------
CREATE TABLE IF NOT EXISTS app_settings (
  setting_key   VARCHAR(50)  PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------- Submission Votes --------
CREATE TABLE IF NOT EXISTS submission_votes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id INT UNSIGNED NOT NULL,
  voter_id      INT UNSIGNED NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
  FOREIGN KEY (voter_id)      REFERENCES users(id)       ON DELETE CASCADE,
  UNIQUE KEY uq_vote (submission_id, voter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -------- Course Posts (Announcements) --------
CREATE TABLE IF NOT EXISTS course_posts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id   INT UNSIGNED NOT NULL,
  teacher_id  INT UNSIGNED NOT NULL,
  body        TEXT         NOT NULL,
  prompt_text TEXT         NULL,
  ai_id       VARCHAR(20)  NULL,
  created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_course (course_id),
  FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- BACKWARD-COMPAT: Add new columns to existing tables
-- (Safe to run on both fresh and existing installations)
-- ============================================================

-- เพิ่ม role 'admin' (ปลอดภัยกับข้อมูลเดิม — ค่า enum เดิมยังอยู่ครบ)
ALTER TABLE users
  MODIFY COLUMN role ENUM('teacher','student','admin') NOT NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email         VARCHAR(150) NULL     AFTER initials,
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER email,
  ADD COLUMN IF NOT EXISTS phone         VARCHAR(20)  NULL     AFTER password_hash,
  ADD COLUMN IF NOT EXISTS school        VARCHAR(200) NULL     AFTER phone,
  ADD COLUMN IF NOT EXISTS province      VARCHAR(100) NULL     AFTER school,
  ADD COLUMN IF NOT EXISTS status        ENUM('active','pending','suspended') NOT NULL DEFAULT 'active' AFTER province;

CREATE UNIQUE INDEX IF NOT EXISTS uq_email ON users (email);

ALTER TABLE courses
  ADD COLUMN IF NOT EXISTS is_public       TINYINT(1)   DEFAULT 0    AFTER teacher_id,
  ADD COLUMN IF NOT EXISTS is_template     TINYINT(1)   DEFAULT 0    AFTER is_public,
  ADD COLUMN IF NOT EXISTS template_id     INT UNSIGNED NULL          AFTER is_template,
  ADD COLUMN IF NOT EXISTS template_secret CHAR(12)     NULL          AFTER template_id,
  ADD COLUMN IF NOT EXISTS is_archived     TINYINT(1)   DEFAULT 0    AFTER template_secret,
  ADD COLUMN IF NOT EXISTS archived_at     DATETIME     NULL          AFTER is_archived,
  ADD COLUMN IF NOT EXISTS materials_quota_mb   INT UNSIGNED NULL COMMENT 'Override โควต้าไฟล์เนื้อหา (NULL = ใช้ค่ากลาง)',
  ADD COLUMN IF NOT EXISTS submissions_quota_mb INT UNSIGNED NULL COMMENT 'Override โควต้าไฟล์งานส่ง (NULL = ใช้ค่ากลาง)';

-- ไฟล์แนบเนื้อหาบทเรียน: เก็บ path/ขนาดไฟล์จริง
ALTER TABLE lesson_materials
  ADD COLUMN IF NOT EXISTS file_path   VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS file_size   INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE course_enrollments
  ADD COLUMN IF NOT EXISTS join_type ENUM('direct','invite_link','invite_code','invite_email','self','template') DEFAULT 'direct' AFTER user_id,
  ADD COLUMN IF NOT EXISTS joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER join_type;

-- Allow ai_id to be NULL (teacher may choose not to specify an AI)
ALTER TABLE lesson_prompts     MODIFY COLUMN ai_id VARCHAR(20) NULL;
ALTER TABLE assignment_prompts MODIFY COLUMN ai_id VARCHAR(20) NULL;

-- Example output file upload support (image / PDF / document, max 10 MB)
ALTER TABLE lesson_prompts     ADD COLUMN IF NOT EXISTS example_file      VARCHAR(255) NULL;
ALTER TABLE lesson_prompts     ADD COLUMN IF NOT EXISTS example_file_name VARCHAR(255) NULL;
ALTER TABLE assignment_prompts ADD COLUMN IF NOT EXISTS example_file      VARCHAR(255) NULL;
ALTER TABLE assignment_prompts ADD COLUMN IF NOT EXISTS example_file_name VARCHAR(255) NULL;


-- ============================================================
-- SEED DATA
-- ============================================================

INSERT IGNORE INTO ai_tools (id, name, letter, color, url) VALUES
('chatgpt',    'ChatGPT',    'G',   '#10a37f', 'chat.openai.com'),
('claude',     'Claude',     'C',   '#d97757', 'claude.ai'),
('gemini',     'Gemini',     '✦',  '#4285f4', 'gemini.google.com'),
('copilot',    'Copilot',    'Co',  '#7a5cff', 'copilot.microsoft.com'),
('perplexity', 'Perplexity', 'P',   '#20808d', 'perplexity.ai'),
('deepseek',   'DeepSeek',   'DS',  '#4d6bfe', 'chat.deepseek.com'),
('notebooklm', 'NotebookLM', 'NL',  '#e8710a', 'notebooklm.google.com'),
('grok',       'Grok',       'xAI', '#111827', 'grok.com'),
('mistral',    'Mistral',    'M',   '#fa520f', 'chat.mistral.ai'),
('meta',       'Meta AI',    'Me',  '#0866ff', 'meta.ai'),
('qwen',       'Qwen',       'Q',   '#6c4ce6', 'chat.qwen.ai'),
('dola',       'Dola',       'Do',  '#0ea5a4', 'heydola.com');

-- ค่ากลางพื้นที่จัดเก็บไฟล์ (admin แก้ไขได้ในหน้า "พื้นที่จัดเก็บไฟล์")
INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('max_file_mb',                 '10'),
('course_materials_quota_mb',   '1024'),
('course_submissions_quota_mb', '1024');

-- password_hash will be set by install.php after SQL runs (password = demo1234)
INSERT INTO users (id, name, role, avatar_class, initials, email, phone, school, province, status) VALUES
(1, 'อ. สมหญิง วัฒนกุล',  'teacher', 'av-1', 'สญ', 'teacher@demo.com',  '0812345678', 'โรงเรียนสาธิต กรุงเทพ', 'กรุงเทพมหานคร', 'active'),
(2, 'ธนกร ใจดี',           'student', 'av-2', 'ธก', 'student1@demo.com', '0823456789', 'โรงเรียนสาธิต กรุงเทพ', 'กรุงเทพมหานคร', 'active'),
(3, 'พิมพ์ชนก ศรีสุข',     'student', 'av-3', 'พช', 'student2@demo.com', '0834567890', 'โรงเรียนสาธิต กรุงเทพ', 'กรุงเทพมหานคร', 'active'),
(4, 'ณัฐวุฒิ ทองคำ',       'student', 'av-4', 'ณว', 'student3@demo.com', '0845678901', 'โรงเรียนสาธิต กรุงเทพ', 'กรุงเทพมหานคร', 'active'),
(5, 'สุชาดา แก้วมณี',      'student', 'av-6', 'สด', 'student4@demo.com', '0856789012', 'โรงเรียนสาธิต กรุงเทพ', 'กรุงเทพมหานคร', 'active'),
(6, 'อภิสิทธิ์ บุญมา',     'student', 'av-5', 'อส', 'student5@demo.com', '0867890123', 'โรงเรียนสาธิต กรุงเทพ', 'กรุงเทพมหานคร', 'active'),
(7, 'วราภรณ์ สุขใจ',       'student', 'av-1', 'วภ', 'student6@demo.com', '0878901234', 'โรงเรียนสาธิต กรุงเทพ', 'กรุงเทพมหานคร', 'active'),
(8, 'กิตติพงษ์ มั่นคง',    'student', 'av-2', 'กพ', 'student7@demo.com', '0889012345', 'โรงเรียนสาธิต กรุงเทพ', 'กรุงเทพมหานคร', 'active')
ON DUPLICATE KEY UPDATE
  email    = VALUES(email),
  phone    = VALUES(phone),
  school   = VALUES(school),
  province = VALUES(province),
  status   = VALUES(status);

-- บัญชีผู้ดูแลระบบ (ไม่กำหนด id — อิง unique email เพื่อไม่ทับผู้ใช้เดิม / รหัสผ่านตั้งโดย install.php)
INSERT IGNORE INTO users (name, role, avatar_class, initials, email, status) VALUES
('ผู้ดูแลระบบ', 'admin', 'av-4', 'AD', 'admin@demo.com', 'active');

INSERT IGNORE INTO courses (id, code, name, section, short_name, banner, ink_color, primary_color, teacher_id) VALUES
(1,'ว31104','วิทยาการคำนวณ','ม.4/2 · ห้อง 314','วค',
 'linear-gradient(120deg,#cdeee2,#e7f7f1)','#0c7a5e','#2bb393',1),
(2,'อ31202','ภาษาอังกฤษเพื่อการสื่อสาร','ม.4/2 · ห้อง 208','EN',
 'linear-gradient(120deg,#d8e3fd,#ecf1ff)','#3257c7','#6b8efb',1),
(3,'ว32241','ชีววิทยา','ม.5/1 · ห้องปฏิบัติการ 2','ชว',
 'linear-gradient(120deg,#e8dcfb,#f4edff)','#7140cf','#a585f2',1),
(4,'ส31102','ประวัติศาสตร์ไทย','ม.4/2 · ห้อง 105','ปวศ',
 'linear-gradient(120deg,#ffe6cc,#fff2e2)','#bd741a','#f0a44e',1);

INSERT IGNORE INTO course_enrollments (course_id, user_id, join_type) VALUES
(1,2,'direct'),(1,3,'direct'),(1,4,'direct'),(1,5,'direct'),(1,6,'direct'),(1,7,'direct'),(1,8,'direct'),
(2,2,'direct'),(2,3,'direct'),(2,4,'direct'),(2,5,'direct'),(2,6,'direct'),(2,7,'direct'),(2,8,'direct'),
(3,2,'direct'),(3,3,'direct'),(3,4,'direct'),(3,5,'direct'),(3,6,'direct'),(3,7,'direct'),(3,8,'direct'),
(4,2,'direct'),(4,3,'direct'),(4,4,'direct'),(4,5,'direct'),(4,6,'direct'),(4,7,'direct'),(4,8,'direct');

INSERT IGNORE INTO lessons (id, course_id, title, week_label, description, sort_order) VALUES
(1,1,'แนวคิดเชิงคำนวณ (Computational Thinking)','สัปดาห์ที่ 1',
 'ทำความเข้าใจองค์ประกอบ 4 ด้านของการคิดเชิงคำนวณ ได้แก่ การแยกย่อยปัญหา การหารูปแบบ การคิดเชิงนามธรรม และการออกแบบขั้นตอนวิธี',1),
(2,1,'การออกแบบอัลกอริทึมด้วย Flowchart','สัปดาห์ที่ 2',
 'เรียนรู้สัญลักษณ์ผังงาน (Flowchart) และฝึกเขียนขั้นตอนวิธีแก้ปัญหาอย่างเป็นระบบ',2),
(3,2,'Self-introduction & Daily Conversation','Week 1',
 'ฝึกแนะนำตัวเองและบทสนทนาในชีวิตประจำวัน เน้นการออกเสียงและความมั่นใจในการพูด',1),
(4,3,'โครงสร้างและหน้าที่ของเซลล์','สัปดาห์ที่ 3',
 'ศึกษาออร์แกเนลล์ภายในเซลล์พืชและเซลล์สัตว์ พร้อมเปรียบเทียบความแตกต่าง',1),
(5,4,'อาณาจักรสุโขทัย: การเมืองและเศรษฐกิจ','สัปดาห์ที่ 2',
 'วิเคราะห์รูปแบบการปกครองแบบพ่อปกครองลูก และระบบเศรษฐกิจการค้าสมัยสุโขทัย',1);

INSERT IGNORE INTO lesson_materials (lesson_id, name, file_type) VALUES
(1,'ใบความรู้-แนวคิดเชิงคำนวณ.pdf','pdf'),
(1,'สไลด์บทที่ 1.pptx','ppt'),
(2,'สัญลักษณ์ Flowchart.pdf','pdf'),
(3,'Vocabulary list - Greetings.pdf','pdf'),
(4,'แผนภาพเซลล์.png','img'),
(4,'ใบงานเซลล์.pdf','pdf'),
(5,'ศิลาจารึกหลักที่ 1.pdf','pdf');

INSERT IGNORE INTO lesson_prompts (lesson_id, prompt_text, ai_id, rating, example_text, note_text) VALUES
(1,'อธิบายแนวคิดเชิงคำนวณ (Computational Thinking) ทั้ง 4 องค์ประกอบ สำหรับนักเรียนชั้น ม.4 พร้อมยกตัวอย่างในชีวิตประจำวันที่เข้าใจง่าย 1 ตัวอย่างต่อองค์ประกอบ และสรุปเป็นตารางสั้น ๆ',
 'chatgpt',5,
 'ได้คำอธิบายเป็นภาษาที่นักเรียนเข้าใจง่าย พร้อมตัวอย่าง เช่น "การแยกย่อยปัญหา" เปรียบเหมือนการแบ่งงานทำความสะอาดบ้านเป็นห้อง ๆ และมีตารางสรุปครบทั้ง 4 ด้าน',
 'แนะนำให้นักเรียนลองเปลี่ยนตัวอย่างเป็นเรื่องที่ตัวเองสนใจ เช่น เกม หรือกีฬา เพื่อให้เห็นภาพมากขึ้น'),
(2,'ช่วยออกแบบผังงาน (flowchart) สำหรับการตัดสินใจว่านักเรียนสอบผ่านหรือไม่ โดยใช้เกณฑ์คะแนนเต็ม 100 ผ่านที่ 50 คะแนน อธิบายเป็นขั้นตอนแบบข้อความที่นำไปวาดเป็นผังงานได้',
 'claude',4,
 'ได้ลำดับขั้นตอนชัดเจน: เริ่ม → รับคะแนน → ตรวจสอบเงื่อนไข (คะแนน ≥ 50?) → ถ้าใช่แสดง "ผ่าน" ถ้าไม่แสดง "ไม่ผ่าน" → จบ เหมาะนำไปวาดต่อ',
 'Claude อธิบายเงื่อนไขได้ละเอียด เหมาะกับการต่อยอดเป็นโค้ด ลองให้นักเรียนถามต่อว่า "แปลงเป็น pseudocode ให้หน่อย"'),
(3,'Act as a friendly English tutor. Create a 6-line everyday conversation between two students meeting for the first time. Use simple A2-level vocabulary, then list 5 useful phrases with Thai translation.',
 'gemini',5,
 'ได้บทสนทนาสั้น เป็นธรรมชาติ พร้อมคำแปลไทยของวลีสำคัญ เช่น "Nice to meet you = ยินดีที่ได้รู้จัก" นักเรียนนำไปฝึกพูดเป็นคู่ได้ทันที',
 'ให้นักเรียนขอ Gemini อ่านออกเสียง (read aloud) เพื่อฝึกสำเนียง และลองเปลี่ยนสถานการณ์เป็นการสั่งอาหาร'),
(4,'สร้างตารางเปรียบเทียบเซลล์พืชกับเซลล์สัตว์ ในหัวข้อ ผนังเซลล์ คลอโรพลาสต์ แวคิวโอล และรูปร่าง อธิบายหน้าที่ของแต่ละออร์แกเนลล์สั้น ๆ ระดับ ม.5',
 'perplexity',4,
 'Perplexity ให้ตารางเปรียบเทียบพร้อมอ้างอิงแหล่งข้อมูล นักเรียนตรวจสอบความถูกต้องได้ และเห็นว่าเซลล์พืชมีผนังเซลล์-คลอโรพลาสต์ ส่วนเซลล์สัตว์ไม่มี',
 'จุดเด่นคือมีลิงก์อ้างอิง เหมาะฝึกนักเรียนตรวจสอบข้อมูลก่อนเชื่อ อย่าลืมให้เทียบกับหนังสือเรียนด้วย'),
(5,'สรุปลักษณะการปกครองแบบ "พ่อปกครองลูก" ในสมัยสุโขทัย พร้อมยกหลักฐานจากศิลาจารึกหลักที่ 1 และเปรียบเทียบกับการปกครองสมัยอยุธยา ในรูปแบบที่เหมาะกับนักเรียน ม.4',
 'chatgpt',4,
 'ได้คำสรุปกระชับ เปรียบเทียบสุโขทัย (ใกล้ชิดประชาชน) กับอยุธยา (สมมติเทพ) อย่างชัดเจน เหมาะใช้ทบทวนก่อนสอบ',
 'ย้ำให้นักเรียนตรวจสอบปีและชื่อบุคคลกับหนังสือเรียนเสมอ เพราะ AI อาจคลาดเคลื่อนเรื่องรายละเอียดทางประวัติศาสตร์');

INSERT IGNORE INTO assignments (id, course_id, title, assignment_type, due_date, due_short, points, instructions, allow_improve) VALUES
(1,1,'ออกแบบอัลกอริทึมแก้ปัญหาในชีวิตประจำวัน','งาน','12 มิ.ย. 2569','12 มิ.ย.',20,
 'เลือกปัญหาในชีวิตประจำวัน 1 เรื่อง แล้วออกแบบขั้นตอนวิธี (อัลกอริทึม) เพื่อแก้ปัญหานั้น นำเสนอเป็นผังงานหรือ pseudocode พร้อมอธิบายเหตุผล',1),
(2,1,'แบบฝึกหัด: เขียน Flowchart 3 สถานการณ์','การบ้าน','8 มิ.ย. 2569','8 มิ.ย.',10,
 'เขียนผังงานสำหรับ 3 สถานการณ์ที่กำหนด ส่งเป็นไฟล์ภาพหรือ PDF',1),
(3,2,'Write a 120-word email to a pen pal','งาน','15 มิ.ย. 2569','15 มิ.ย.',25,
 'เขียนอีเมลแนะนำตัวเองถึงเพื่อนต่างชาติ ความยาวประมาณ 120 คำ ใช้ Present Simple และ Present Continuous อย่างถูกต้อง',1),
(4,3,'รายงาน: เปรียบเทียบการลำเลียงสารผ่านเยื่อหุ้มเซลล์','งาน','18 มิ.ย. 2569','18 มิ.ย.',30,
 'เขียนรายงาน 1 หน้า เปรียบเทียบการแพร่ การออสโมซิส และการลำเลียงแบบใช้พลังงาน พร้อมยกตัวอย่างในสิ่งมีชีวิต',1),
(5,4,'วิเคราะห์ปัจจัยการล่มสลายของกรุงศรีอยุธยา','งาน','20 มิ.ย. 2569','20 มิ.ย.',20,
 'วิเคราะห์ปัจจัยทั้งภายในและภายนอกที่นำไปสู่การเสียกรุงศรีอยุธยาครั้งที่ 2 พร้อมแสดงความคิดเห็น',1);

INSERT IGNORE INTO assignment_prompts (assignment_id, prompt_text, ai_id, rating, example_text, note_text) VALUES
(1,'ช่วยออกแบบอัลกอริทึมแบบ pseudocode สำหรับการจัดลำดับการทำการบ้านหลายวิชาให้เสร็จทันกำหนดส่ง โดยพิจารณาจากกำหนดส่งและความยากของแต่ละวิชา อธิบายแต่ละขั้นตอน',
 'claude',4,'ได้ pseudocode ที่เรียงงานตาม deadline และถ่วงน้ำหนักความยาก พร้อมคำอธิบายตรรกะ นักเรียนนำไปปรับใช้กับงานจริงได้',
 'นี่เป็นเพียง prompt เริ่มต้น — นักเรียนควรปรับแต่งให้ตรงกับปัญหาที่ตนเลือก และทดลองกับ AI หลายตัวเพื่อหาคำตอบที่ดีที่สุด'),
(2,'อธิบายวิธีเขียน flowchart สำหรับการตรวจสอบว่าเลขที่ป้อนเข้ามาเป็นเลขคู่หรือเลขคี่ แสดงเป็นลำดับขั้นตอนที่นำไปวาดได้',
 'chatgpt',5,'อธิบายการใช้ตัวดำเนินการ mod (เศษจากการหาร 2) เพื่อแยกคู่/คี่ ชัดเจน เหมาะเป็นตัวอย่างตั้งต้น',
 'ลองให้นักเรียนถามต่อว่า "ถ้าต้องตรวจสอบหลายเลขพร้อมกันต้องปรับผังงานอย่างไร"'),
(3,'I am a Thai grade-10 student. Help me brainstorm 5 interesting things to write about myself in an email to a foreign pen pal. Then show me how to start and end an informal email politely in English.',
 'gemini',5,'ได้ไอเดียหัวข้อ 5 อย่าง (งานอดิเรก อาหารโปรด ครอบครัว ฯลฯ) และโครงสร้างเปิด-ปิดอีเมล นักเรียนนำไปเรียบเรียงเป็นภาษาของตัวเอง',
 'สำคัญ: ใช้ AI เพื่อ "หาไอเดียและตรวจไวยากรณ์" เท่านั้น ห้ามให้ AI เขียนทั้งฉบับแทน — ครูจะดูสำนวนเฉพาะตัวของนักเรียน'),
(4,'อธิบายความแตกต่างระหว่าง diffusion, osmosis และ active transport ในการลำเลียงสารผ่านเยื่อหุ้มเซลล์ พร้อมยกตัวอย่างในร่างกายมนุษย์ ระดับ ม.5 และจัดเป็นหัวข้อให้เขียนรายงานต่อได้',
 'perplexity',4,'ได้คำอธิบายพร้อมแหล่งอ้างอิง แยกประเภทการลำเลียงชัดเจน (ใช้/ไม่ใช้พลังงาน) นักเรียนตรวจสอบความถูกต้องจาก citation ได้',
 'ให้นักเรียนเปรียบเทียบคำตอบจาก AI หลายตัว แล้วเลือกข้อมูลที่ตรงกับหนังสือเรียนมากที่สุดมาอ้างอิง'),
(5,'สรุปปัจจัยภายในและภายนอกที่ทำให้กรุงศรีอยุธยาเสียกรุงครั้งที่ 2 (พ.ศ. 2310) แยกเป็นหัวข้อ พร้อมอธิบายเหตุผลแต่ละข้อ ในระดับที่นักเรียน ม.4 เข้าใจได้',
 'chatgpt',3,'ได้กรอบปัจจัยภายใน (ความขัดแย้งภายใน การเมือง) และภายนอก (การรุกรานของพม่า) แต่ต้องตรวจสอบรายละเอียดปีและเหตุการณ์เพิ่มเติม',
 'AI ให้กรอบความคิดได้ดี แต่ความแม่นยำทางประวัติศาสตร์ยังต้องตรวจสอบ — เป็นโจทย์ที่ดีให้นักเรียนปรับ prompt และเทียบกับ Perplexity ที่มีอ้างอิง');

INSERT IGNORE INTO submissions (id, assignment_id, student_id, answer_text, prompt_used, ai_used,
  better_than_teacher, compare_note, result_text, status, grade, feedback) VALUES
(1,1,3,'pseudocode สำหรับจัดลำดับการรดน้ำต้นไม้ตามความถี่และปริมาณน้ำ',
 'ออกแบบอัลกอริทึม pseudocode สำหรับจัดลำดับการรดน้ำต้นไม้ 5 ชนิดที่ต้องการน้ำต่างกัน โดยพิจารณาความถี่และปริมาณน้ำ อธิบายเหตุผลแต่ละขั้นตอนแบบเข้าใจง่าย และเพิ่มเงื่อนไขกรณีฝนตก',
 'claude',1,'ผมเพิ่มเงื่อนไข "ถ้าฝนตก" เข้าไปจาก prompt เดิมของครู ทำให้อัลกอริทึมใช้ได้จริงมากขึ้นครับ',
 'ได้ pseudocode ที่ตรวจสอบสภาพอากาศก่อน ถ้าฝนตกให้ข้ามการรดน้ำวันนั้น และจัดลำดับต้นไม้ตามความถี่ที่ต้องการน้ำ',
 'graded',19,'เยี่ยมมาก! การเพิ่มเงื่อนไขกรณีฝนตกแสดงถึงการคิดเชิงระบบที่ดี prompt ปรับได้ตรงประเด็น'),
(2,1,4,'pseudocode จัดลำดับการทำการบ้าน 4 วิชาตามวันส่ง',
 'ช่วยออกแบบ pseudocode จัดลำดับการทำการบ้าน 4 วิชาตามวันส่งและความยาก ให้เสร็จก่อนกำหนด พร้อมแสดงตารางเวลาที่แนะนำ',
 'gemini',0,'ผมลองใช้ Gemini เพราะอยากได้ตารางเวลาด้วย แต่ส่วนการถ่วงน้ำหนักความยากยังสู้ของครูไม่ได้ครับ',
 'ได้ pseudocode เรียงงานตาม deadline และเพิ่มตารางเวลารายวัน','submitted',NULL,NULL),
(3,1,5,'algorithm สำหรับวางแผนการอ่านหนังสือสอบ 6 วิชาใน 10 วัน',
 'Design a pseudocode algorithm to plan my exam revision across 6 subjects within 10 days, prioritizing by exam date and my confidence level (1-5). Explain each step in Thai.',
 'chatgpt',1,'หนูเพิ่ม "ระดับความมั่นใจ 1-5" เป็นตัวแปรเข้าไป ทำให้ AI จัดลำดับได้ตรงกับความถนัดจริง ๆ ค่ะ',
 'ได้อัลกอริทึมที่ใช้ทั้งวันสอบและระดับความมั่นใจของตัวเองมาคำนวณ ทำให้แผนอ่านหนังสือสมจริง',
 'submitted',NULL,NULL);

INSERT IGNORE INTO submission_votes (submission_id, voter_id) VALUES
(1,2),(1,4),(1,5),(1,6),(1,7),(1,8),(1,1),
(2,2),(2,3),
(3,2),(3,3),(3,6),(3,7),(3,8);
