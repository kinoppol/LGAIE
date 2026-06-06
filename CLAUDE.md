# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ClassroomAI is a school LMS (Learning Management System) built with **PHP 8 + MariaDB 10**, running under XAMPP. Its core feature is embedding AI prompt guidance alongside lesson content and assignments — teachers attach tested prompts (with AI name, star rating, example output, and notes); students submit their own improved prompts alongside their work.

- **Live URL:** `http://localhost/LGAIE/`
- **Database:** `classroomai` on `localhost` (root, no password — XAMPP defaults in `config/db.php`)
- **Setup:** Import `sql/schema.sql` via phpMyAdmin or `mysql -u root classroomai < sql/schema.sql`

## Architecture

### Request Flow

`index.php` is the single front controller. It starts the session, bootstraps helpers and layout, then `require`s the matching page file based on `?page=` query param.

```
Request → index.php → includes/layout.php (layout_start) → pages/*.php → layout_end()
                                                          ↓
                                                   POST → api/*.php → redirect back
```

Page routing lives entirely in `index.php` via a `$page_map` array. Adding a new page means creating `pages/newpage.php` and adding it to the map.

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

- `users` — role ENUM('teacher','student'), used for role-switch (teacher=id 1, student=id 2 by default)
- `courses` → `lessons` + `lesson_prompts` + `lesson_materials`
- `courses` → `assignments` + `assignment_prompts`
- `assignments` → `submissions` (one per student) → `submission_votes`
- `ai_tools` — registry of AI names/colors loaded into every page via `get_ai_tools()` (cached statically)

### Session Conventions

- `$_SESSION['role']` — `'teacher'` or `'student'`
- `$_SESSION['user_id']` — 1 (teacher) or 2 (student) for the demo
- `$_SESSION['theme']` — `'light'`, `'dark'`, or `'system'`
- `$_SESSION['success']` / `$_SESSION['error']` — flash messages consumed once by layout

### Role Switch

`api/switch_role.php` (POST) sets `$_SESSION['role']` and `$_SESSION['user_id']`, then redirects. `is_teacher()` and `current_role()` are the canonical checks throughout page files.

### Adding a New Page

1. Create `pages/mypage.php` (outputs content inside `.content` div — layout wraps it)
2. Add to `$title_map` and `$page_map` in `index.php`
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
