# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ClassroomAI is a school LMS (Learning Management System) built with **PHP 8 + MariaDB 10**, running under XAMPP. Its core feature is embedding AI prompt guidance alongside lesson content and assignments — teachers attach tested prompts (with AI name, star rating, example output, and notes); students submit their own improved prompts alongside their work.

- **Live URL:** `http://localhost/LGAIE/`
- **Database:** `classroomai` on `localhost` (root, no password — XAMPP defaults in `config/db.php`)
- **Setup:** Run `install.php` in the browser (writes `config/db.php`, imports `sql/schema.sql`, sets demo passwords to `demo1234`), or import manually via `mysql -u root classroomai < sql/schema.sql`. Delete `install.php` after install.
- **Repair structure:** `migrate.php` (localhost-only) re-applies every `ADD COLUMN IF NOT EXISTS` / `CREATE TABLE IF NOT EXISTS` and seeds defaults. Run it after pulling schema changes onto an existing DB.

## Architecture

### Request Flow

`index.php` is the single front controller. It starts the session, bootstraps helpers and layout, then `require`s the matching page file based on `?page=` query param. Routing has **three tiers**, checked in this order in `index.php`:

1. **Standalone pages** (`$standalone` array: `login`, `register`, `explore`, `home`, `certificate`) — render their own complete `<html>` document with no app shell. Do NOT wrap them in `layout_start()`.
2. **Public pages** (`$public_pages`: `course`) — viewable logged-out; wrapped in the guest shell via `layout_start_guest()` / `layout_end_guest()`.
3. **Authenticated pages** — everything else; gated by the auth guard, then wrapped in `layout_start()` / `layout_end()`. `$page_map` aliases (e.g. `todo`/`tograde` → `workqueue`) map a URL key to a different page file.

```
Request → index.php ─┬─ standalone: require pages/*.php (full HTML)        → exit
                     ├─ public+guest: layout_start_guest → pages/*.php → layout_end_guest
                     └─ auth: layout_start → pages/*.php → layout_end
                                                  ↓
                                           POST → api/*.php → redirect back
```

API endpoints (`api/*.php`) are plain PHP POST handlers — they read `$_POST`, call DB helpers, set `$_SESSION['success']`/`$_SESSION['error']`, then redirect back. Flash messages are emitted as `<meta>` tags in the `<head>` and consumed by `js/app.js` on page load.

### Key Files

| File | Role |
|------|------|
| `includes/functions.php` | All shared logic: DB helpers, icon SVG generator, UI components (`avatar()`, `ai_pill()`, `render_prompt_block()`, `star_input()`, `ai_select()`) |
| `includes/layout.php` | `layout_start()` outputs the full HTML head + sidebar + topbar; `layout_end()` closes the shell and injects `window.AI_TOOLS` JSON for JS |
| `config/db.php` | PDO singleton via `get_db()` |
| `js/app.js` | Vanilla JS: theme toggle (localStorage + `/api/set_theme.php`), modal open/close, AJAX form submission for `[data-ajax]` forms, copy-to-clipboard, grade modal population |
| `css/theme.css` | Full design system — CSS custom properties for both `:root` (light/pastel) and `[data-theme="dark"]`. All colours, spacing, and component styles live here. |

### Database Schema (key tables)

- `users` — role ENUM('teacher','student','admin'), used for role-switch (teacher=id 1, student=id 2 by default; admin=`admin@demo.com`)
- `courses` → `lessons` + `lesson_prompts` + `lesson_materials` (file attachments: `file_path`, `file_size`)
- `courses` → `assignments` + `assignment_prompts`
- `assignments` → `submissions` (one per student) → `submission_votes` + `submission_files`
- `ai_tools` — registry of AI names/colors loaded into every page via `get_ai_tools()` (cached statically)
- `app_settings` — key/value store for admin-set limits (`max_file_mb`, `course_materials_quota_mb`, `course_submissions_quota_mb`)
- `course_certificates` (per-course cert config + background style/image), `course_posts` (announcements), `quiz_questions`/`quiz_choices`/`quiz_responses` — added by later features

### Runtime Schema Migrations (`ensure_*_schema()`)

New columns/tables are introduced as idempotent `ALTER … ADD COLUMN IF NOT EXISTS` / `CREATE TABLE IF NOT EXISTS` blocks wrapped in `try/catch (PDOException)`, exposed as `ensure_*_schema()` helpers in `includes/functions.php`: `ensure_storage_schema()` (uploads + `users.avatar_path`), `ensure_certificate_schema()`, `ensure_directory_schema()` (`users.show_in_directory`, `users.bio`), `ensure_quiz_schema()`. Each guards itself with a `static $done` flag so it runs at most once per request.

**Gotcha (caused a recent fatal crash):** the same migration that adds a column lives in three places — `sql/schema.sql` (both the `CREATE TABLE` and the backward-compat `ALTER` block), `migrate.php`, and an `ensure_*_schema()` helper. A fresh `schema.sql` import does NOT necessarily include a later-added column unless schema.sql was updated. **Any page/endpoint that reads a late-added column must call the matching `ensure_*_schema()` at the top** before querying, or it crashes on installs that predate the column. When you add a column, update all three locations.

### File Uploads & Storage Quotas

- Lesson materials (`materials[]`) and submission files (`files[]`) are multi-file uploads stored under `uploads/materials/` and `uploads/submissions/`; the example-output file lives in `uploads/examples/`.
- Limits are enforced in `includes/functions.php`: `max_file_bytes()` (per-file, default 10 MB), `course_quota_bytes($course_id, 'materials'|'submissions')` (per-course totals, default 1 GB each — **counted separately**). Per-course overrides live in `courses.materials_quota_mb` / `courses.submissions_quota_mb` (NULL = global default).
- Upload flow helpers: `collect_uploaded_files()` → `upload_batch_error()` (validate before any DB write; returns error string or null) → `store_uploaded_file()` (throws `RuntimeException`). UI components: `multi_file_input()` (works with `data-multifile` JS in `app.js`), `attachment_item()` for display.
- `ensure_storage_schema()` auto-migrates the storage columns/tables; call it in any endpoint touching uploads.

### Admin Role

- Admin account: `admin@demo.com` / `demo1234` (seeded; password set by `install.php`). Admin lands on `pages/admin.php` (`?page=admin`, dashboard redirects there).
- Admin tabs: **users** (reset password, suspend/activate teacher & student accounts via `api/admin_users.php`) and **storage** (global limits + per-course quota overrides + usage bars via `api/admin_settings.php`).
- Guards: `is_admin()` / `require_admin()`. Admin accounts cannot manage each other.

### Session Conventions

- `$_SESSION['role']` — `'teacher'`, `'student'`, or `'admin'`
- `$_SESSION['user_id']` — 1 (teacher) or 2 (student) for the demo
- `$_SESSION['theme']` — `'light'`, `'dark'`, or `'system'`
- `$_SESSION['success']` / `$_SESSION['error']` — flash messages consumed once by layout

### Role Switch

`api/switch_role.php` (POST) sets `$_SESSION['role']` and `$_SESSION['user_id']`, then redirects. `is_teacher()` and `current_role()` are the canonical checks throughout page files.

### Adding a New Page

1. Create `pages/mypage.php` (outputs content inside `.content` div — layout wraps it). If the page reads any late-added DB column, call the relevant `ensure_*_schema()` at the top.
2. Add a `$title_map` entry in `index.php`. For pages that render their own full HTML, add the key to `$standalone`; for logged-out access, add to `$public_pages`; use `$page_map` only to alias one URL key to another page file.
3. Link from sidebar in `includes/layout.php` if needed

### Adding a New API Endpoint

1. Create `api/myendpoint.php`
2. Start with `session_start()` + `require_once` for `config/db.php` and `includes/functions.php`
3. Use `json_ok()` / `json_err()` for AJAX callers, or set flash + redirect for form submissions
4. Use `db_run()` / `db_row()` / `db_rows()` / `db_val()` — never raw PDO in endpoint files

### JS Patterns

- **AJAX forms:** add `data-ajax` attribute to a `<form>` — `app.js` intercepts submit, POSTs via `fetch`, shows toast, reloads on success
- **Modals:** `openModal('id')` / `closeModal('id')` — overlay element must have id `{id}-overlay`
- **Copy buttons:** `<button class="copy-btn" data-copy="text">` — handled globally via event delegation
- **Star inputs:** rendered by `star_input()` PHP helper; JS `setStars(svg)` updates hidden input and visual state
- **AI select:** rendered by `ai_select()` PHP helper; JS `updateAiSelect(sel)` syncs the logo badge

### HTML Prototype (Legacy)

`index.html` and `js/*.jsx` are the original React/Babel prototype from the design handoff. They are not used by the PHP app but kept as reference. The PHP app reuses `css/theme.css` unchanged.
