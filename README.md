# ClassroomAI

ระบบจัดการการเรียนรู้ (LMS) สำหรับโรงเรียน ที่ผสาน **AI Prompt Guidance** เข้ากับเนื้อหาบทเรียนและงานที่มอบหมาย ครูสามารถแนบ Prompt AI ที่ทดลองแล้วพร้อมให้คะแนนดาว ตัวอย่างผลลัพธ์ และหมายเหตุ นักเรียนส่ง Prompt ที่ปรับปรุงเองพร้อมกับงาน

**Stack:** PHP 8 · MariaDB 10 · Vanilla JS · XAMPP

---

## ความต้องการของระบบ

- XAMPP (Apache + MariaDB + PHP 8)
- PHP Extensions: `pdo_mysql`, `mbstring`

---

## การติดตั้ง

### วิธีที่ 1 — ใช้หน้าติดตั้ง (แนะนำ)

1. โคลนหรือวางโปรเจกต์ไว้ที่ `C:\xampp\htdocs\LGAIE`
2. เปิด XAMPP แล้วสตาร์ท **Apache** และ **MySQL**
3. เปิดเบราว์เซอร์ไปที่ `http://localhost/LGAIE/install.php`
4. กรอกข้อมูลการเชื่อมต่อฐานข้อมูล แล้วกด **ติดตั้ง**
5. หลังติดตั้งสำเร็จ เข้าสู่ระบบที่ `http://localhost/LGAIE/`

### วิธีที่ 2 — นำเข้า SQL ด้วยตนเอง

```bash
mysql -u root classroomai < sql/schema.sql
```

จากนั้นตั้งค่าไฟล์ `config/db.php` ให้ตรงกับ environment ของคุณ

---

## ข้อมูล Demo

หลังติดตั้ง จะมีผู้ใช้ตัวอย่างพร้อมใช้งาน:

| บทบาท | อีเมล | รหัสผ่าน |
|--------|-------|----------|
| ครู | `teacher@demo.com` | `demo1234` |
| นักเรียน | `student@demo.com` | `demo1234` |

---

## โครงสร้างโปรเจกต์

```
LGAIE/
├── api/                  # POST endpoints (redirect หรือ JSON response)
│   ├── login.php
│   ├── register.php
│   ├── create_course.php
│   ├── update_course.php
│   ├── archive_course.php
│   ├── delete_course.php
│   └── ...
├── config/
│   └── db.php            # PDO singleton (ไม่อยู่ใน Git)
├── css/
│   └── theme.css         # Design system — light/dark CSS custom properties
├── includes/
│   ├── functions.php     # DB helpers, UI components, auth helpers
│   ├── layout.php        # layout_start() / layout_end() shell
│   └── provinces.php     # ข้อมูล 77 จังหวัด
├── js/
│   └── app.js            # Theme toggle, modals, AJAX forms, toast
├── pages/                # Page files (render ภายใน layout shell)
│   ├── dashboard.php
│   ├── courses.php
│   ├── course.php
│   ├── course_settings.php
│   ├── lesson.php
│   ├── assignment.php
│   ├── workqueue.php
│   ├── profile.php
│   ├── login.php         # Standalone (ไม่ใช้ layout)
│   └── register.php      # Standalone (ไม่ใช้ layout)
├── sql/
│   └── schema.sql        # Schema + seed data
├── index.php             # Front controller
└── install.php           # Web-based installer
```

---

## ฟีเจอร์หลัก

- **ระบบสมาชิก** — ลงทะเบียน/เข้าสู่ระบบ แยกบทบาทครู/นักเรียน แก้ไขโปรไฟล์ เปลี่ยนรหัสผ่าน
- **จัดการรายวิชา** — สร้าง แก้ไข ปรับสีธีม จัดเก็บ (Archive) และลบรายวิชา
- **รายวิชาต้นแบบ** — กำหนดรายวิชาเป็น Template พร้อม Secret Code สำหรับ Clone
- **บทเรียน & งาน** — แนบ Prompt AI พร้อมให้ดาว ตัวอย่าง และหมายเหตุ
- **การส่งงาน** — นักเรียนส่ง Prompt ที่ปรับปรุงเองพร้อมชิ้นงาน ตรวจและให้คะแนนได้
- **การแจ้งเตือน** — กระดิ่งแจ้งงานรอตรวจ (ครู) / งานที่ยังไม่ส่ง (นักเรียน)
- **Dark Mode** — สลับ Light/Dark/System ได้ บันทึกใน session

---

## Architecture

`index.php` ทำหน้าที่เป็น Front Controller อ่าน `?page=` แล้ว `require` ไฟล์ใน `pages/` โดย layout ครอบทั้งหมดผ่าน `layout_start()` / `layout_end()`

```
Request → index.php → layout_start() → pages/*.php → layout_end()
                                ↓
                    POST → api/*.php → redirect / json_ok()
```

**DB Helpers** (ใน `includes/functions.php`):

```php
db_run($sql, $params)   // INSERT / UPDATE / DELETE
db_row($sql, $params)   // SELECT แถวเดียว
db_rows($sql, $params)  // SELECT หลายแถว
db_val($sql, $params)   // SELECT ค่าเดียว (COUNT, SUM ฯลฯ)
```

**AJAX Forms** — เพิ่ม `data-ajax` ใน `<form>` แล้ว `app.js` จะดัก submit ส่งผ่าน `fetch()` และแสดง toast อัตโนมัติ

---

## การเพิ่มหน้าใหม่

1. สร้าง `pages/mypage.php`
2. เพิ่มใน `$title_map` และ `$page_map` ใน `index.php`
3. เพิ่มลิงก์ใน Sidebar (`includes/layout.php`) หากต้องการ

---

## หมายเหตุ

- ไฟล์ `config/db.php` ถูกยกเว้นจาก Git เพราะมีข้อมูล credential — ต้องสร้างใหม่ผ่าน `install.php` หรือกำหนดเองหลัง clone
- `index.html` และ `js/*.jsx` คือ React/Babel prototype เดิม ไม่ได้ใช้งานใน PHP app แต่เก็บไว้เป็น reference
