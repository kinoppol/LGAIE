<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/provinces.php';

// ── HTML / Output ──────────────────────────────────────────────────────────

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Database helpers ────────────────────────────────────────────────────────

function db_row(string $sql, array $p = []): array|false
{
    $st = get_db()->prepare($sql);
    $st->execute($p);
    return $st->fetch();
}

function db_rows(string $sql, array $p = []): array
{
    $st = get_db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function db_run(string $sql, array $p = []): int
{
    $st = get_db()->prepare($sql);
    $st->execute($p);
    return (int) get_db()->lastInsertId();
}

function db_val(string $sql, array $p = []): mixed
{
    $st = get_db()->prepare($sql);
    $st->execute($p);
    return $st->fetchColumn();
}

// ── Routing / Sessions ──────────────────────────────────────────────────────

function base_url(): string
{
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    // detect sub-folder (works for /LGAIE/ or root)
    $script = dirname($_SERVER['SCRIPT_NAME']);
    return rtrim("{$scheme}://{$host}{$script}", '/');
}

function url(string $page, array $extra = []): string
{
    $q = array_merge(['page' => $page], $extra);
    return '?' . http_build_query($q);
}

function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

function json_ok(array $data = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, ...$data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $msg, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Current user / role ─────────────────────────────────────────────────────

function current_role(): string
{
    return $_SESSION['role'] ?? 'student';
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function is_teacher(): bool
{
    return current_role() === 'teacher';
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function require_auth(): void
{
    if (!is_logged_in()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '');
        redirect('index.php?page=login' . ($back ? '&redirect=' . $back : ''));
    }
}

function require_teacher(): void
{
    require_auth();
    if (!is_teacher()) {
        http_response_code(403);
        exit('403 Forbidden — เฉพาะครูเท่านั้น');
    }
}

function current_user(): array
{
    if (!is_logged_in()) return [];
    return db_row('SELECT * FROM users WHERE id = ?', [current_user_id()]) ?: [];
}

// ── Data helpers ────────────────────────────────────────────────────────────

function get_ai_tools(): array
{
    static $cache = null;
    if ($cache === null) {
        $rows  = db_rows('SELECT * FROM ai_tools ORDER BY id');
        $cache = array_column($rows, null, 'id');
    }
    return $cache;
}

function get_ai(string $id): array|false
{
    return get_ai_tools()[$id] ?? false;
}

function get_courses_with_stats(bool $include_archived = false): array
{
    $uid = current_user_id();

    if (is_teacher()) {
        $archived_clause = $include_archived ? '' : 'AND c.is_archived = 0';
        $sql    = "
            SELECT c.*,
                u.avatar_class AS teacher_av,
                u.initials     AS teacher_initials,
                u.name         AS teacher_name,
                (SELECT COUNT(*) FROM lessons             WHERE course_id = c.id) AS lesson_count,
                (SELECT COUNT(*) FROM assignments          WHERE course_id = c.id) AS assignment_count,
                (SELECT COUNT(*) FROM course_enrollments  WHERE course_id = c.id) AS student_count
            FROM courses c
            JOIN users u ON u.id = c.teacher_id
            WHERE c.teacher_id = ? {$archived_clause}
            ORDER BY c.id
        ";
        $params = [$uid];
    } else {
        $archived_clause = $include_archived ? '' : 'AND c.is_archived = 0';
        $sql    = "
            SELECT c.*,
                u.avatar_class AS teacher_av,
                u.initials     AS teacher_initials,
                u.name         AS teacher_name,
                (SELECT COUNT(*) FROM lessons             WHERE course_id = c.id) AS lesson_count,
                (SELECT COUNT(*) FROM assignments          WHERE course_id = c.id) AS assignment_count,
                (SELECT COUNT(*) FROM course_enrollments  WHERE course_id = c.id) AS student_count
            FROM courses c
            JOIN users u ON u.id = c.teacher_id
            JOIN course_enrollments e ON e.course_id = c.id AND e.user_id = ?
            WHERE 1=1 {$archived_clause}
            ORDER BY c.id
        ";
        $params = [$uid];
    }

    try {
        return db_rows($sql, $params);
    } catch (PDOException $e) {
        // Column is_archived not yet in DB — auto-add it then retry
        if (str_contains($e->getMessage(), 'is_archived')) {
            get_db()->exec("ALTER TABLE courses
                ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) DEFAULT 0,
                ADD COLUMN IF NOT EXISTS archived_at DATETIME NULL");
            return db_rows($sql, $params);
        }
        throw $e;
    }
}

function get_archived_courses(): array
{
    $uid = current_user_id();
    if (is_teacher()) {
        return db_rows('
            SELECT c.*,
                u.avatar_class AS teacher_av,
                u.initials     AS teacher_initials,
                u.name         AS teacher_name,
                (SELECT COUNT(*) FROM lessons        WHERE course_id = c.id) AS lesson_count,
                (SELECT COUNT(*) FROM assignments     WHERE course_id = c.id) AS assignment_count,
                (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) AS student_count
            FROM courses c
            JOIN users u ON u.id = c.teacher_id
            WHERE c.is_archived = 1 AND c.teacher_id = ?
            ORDER BY c.archived_at DESC
        ', [$uid]);
    }
    return db_rows('
        SELECT c.*,
            u.avatar_class AS teacher_av,
            u.initials     AS teacher_initials,
            u.name         AS teacher_name,
            (SELECT COUNT(*) FROM lessons        WHERE course_id = c.id) AS lesson_count,
            (SELECT COUNT(*) FROM assignments     WHERE course_id = c.id) AS assignment_count,
            (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) AS student_count
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        JOIN course_enrollments e ON e.course_id = c.id AND e.user_id = ?
        WHERE c.is_archived = 1
        ORDER BY c.archived_at DESC
    ', [$uid]);
}

function get_course(int $id): array|false
{
    return db_row('
        SELECT c.*,
            u.avatar_class AS teacher_av,
            u.initials     AS teacher_initials,
            u.name         AS teacher_name,
            (SELECT COUNT(*) FROM lessons        WHERE course_id = c.id) AS lesson_count,
            (SELECT COUNT(*) FROM assignments     WHERE course_id = c.id) AS assignment_count,
            (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) AS student_count
        FROM courses c
        JOIN users u ON u.id = c.teacher_id
        WHERE c.id = ?
    ', [$id]);
}

function get_lesson_with_prompt(int $id): array|false
{
    $lesson = db_row('SELECT * FROM lessons WHERE id = ?', [$id]);
    if (!$lesson) return false;
    $lesson['prompt']    = db_row('SELECT * FROM lesson_prompts    WHERE lesson_id = ?', [$id]) ?: [];
    $lesson['materials'] = db_rows('SELECT * FROM lesson_materials WHERE lesson_id = ? ORDER BY id', [$id]);
    return $lesson;
}

function get_assignment_with_prompt(int $id): array|false
{
    $a = db_row('SELECT * FROM assignments WHERE id = ?', [$id]);
    if (!$a) return false;
    $a['prompt'] = db_row('SELECT * FROM assignment_prompts WHERE assignment_id = ?', [$id]) ?: [];
    return $a;
}

function get_submissions_for_assignment(int $assignment_id): array
{
    $subs = db_rows('
        SELECT s.*, u.name AS student_name, u.avatar_class, u.initials,
            (SELECT COUNT(*) FROM submission_votes v WHERE v.submission_id = s.id) AS vote_count
        FROM submissions s
        JOIN users u ON u.id = s.student_id
        WHERE s.assignment_id = ?
        ORDER BY s.submitted_at DESC
    ', [$assignment_id]);
    return $subs;
}

function upload_example_file(string $field = 'example_file', ?string $existing = null): ?string
{
    if (empty($_FILES[$field]['name'])) return $existing;
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) return $existing;
    if ($file['size'] > 10 * 1024 * 1024) json_err('ไฟล์ตัวอย่างใหญ่เกิน 10 MB');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','ppt','pptx','xls','xlsx','txt','zip'];
    if (!in_array($ext, $allowed)) json_err('ประเภทไฟล์ไม่รองรับ');
    $dir = __DIR__ . '/../uploads/examples/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if ($existing) { $old = __DIR__ . '/../' . $existing; if (file_exists($old)) unlink($old); }
    $filename = uniqid('ex_') . '.' . $ext;
    move_uploaded_file($file['tmp_name'], $dir . $filename);
    return 'uploads/examples/' . $filename;
}

function example_file_input(?string $existing = null): void
{
    $lbl = $existing ? basename($existing) : 'แนบไฟล์ (ภาพ / PDF / เอกสาร ไม่เกิน 10 MB)';
    ?>
    <div style="margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <label style="cursor:pointer;display:inline-flex;align-items:center;gap:7px;font-size:12.5px;
                    color:var(--sub);padding:7px 12px;border:1.5px dashed var(--line-2);border-radius:8px;
                    transition:border-color .15s,color .15s"
             onmouseenter="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
             onmouseleave="this.style.borderColor='var(--line-2)';this.style.color='var(--sub)'">
        <?= icon('paperclip', 14) ?>
        <span class="ef-lbl"><?= h($lbl) ?></span>
        <input type="file" name="example_file"
               accept="image/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip"
               style="display:none"
               onchange="var s=this.closest('label').querySelector('.ef-lbl');s.textContent=this.files[0]?this.files[0].name:'แนบไฟล์ (ภาพ / PDF / เอกสาร ไม่เกิน 10 MB)'">
      </label>
      <?php if ($existing): ?>
      <a href="<?= h($existing) ?>" target="_blank" class="btn btn-sm btn-ghost" style="font-size:12px">
        <?= icon('download', 13) ?> ดูไฟล์เดิม
      </a>
      <?php endif; ?>
    </div>
    <?php
}

function thai_due_ts(string $due): int
{
    static $months = [
        'ม.ค.' => 1, 'ก.พ.' => 2, 'มี.ค.' => 3, 'เม.ย.' => 4,
        'พ.ค.' => 5, 'มิ.ย.' => 6, 'ก.ค.' => 7, 'ส.ค.' => 8,
        'ก.ย.' => 9, 'ต.ค.' => 10, 'พ.ย.' => 11, 'ธ.ค.' => 12,
    ];
    if (!preg_match('/(\d+)\s+(\S+)\s+(\d{4})(?:.*?(\d{2}:\d{2}))?/', $due, $m)) return 0;
    $month = $months[$m[2]] ?? 0;
    if (!$month) return 0;
    $y    = (int)$m[3] - 543;
    [$h, $min] = explode(':', $m[4] ?? '23:59');
    return (int)mktime((int)$h, (int)$min, 0, $month, (int)$m[1], $y);
}

function count_pending_for_teacher(): int
{
    return (int) db_val('
        SELECT COUNT(*) FROM submissions s
        JOIN assignments a ON a.id = s.assignment_id
        JOIN courses c     ON c.id = a.course_id
        WHERE c.teacher_id = ? AND s.status = "submitted"
          AND c.is_archived = 0
    ', [current_user_id()]);
}

function count_pending_for_student(int $student_id): int
{
    return (int) db_val('
        SELECT COUNT(*) FROM assignments a
        JOIN courses c             ON c.id = a.course_id
        JOIN course_enrollments e  ON e.course_id = a.course_id AND e.user_id = ?
        WHERE c.is_archived = 0
          AND NOT EXISTS (
            SELECT 1 FROM submissions s
            WHERE s.assignment_id = a.id AND s.student_id = ?
        )
    ', [$student_id, $student_id]);
}

// ── SVG Icon ────────────────────────────────────────────────────────────────

function icon(
    string $name,
    int    $size  = 20,
    string $color = 'currentColor',
    float  $sw    = 1.7,
    string $cls   = ''
): string {
    static $paths = [
        'home'       => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'grid'       => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'book'       => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a2 2 0 0 0-2 2z"/><path d="M4 19a2 2 0 0 1 2-2h14"/>',
        'clipboard'  => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 11h6M9 15h4"/>',
        'stream'     => '<path d="M4 6h16M4 12h16M4 18h10"/>',
        'check'      => '<path d="M5 12.5 10 17.5 19.5 6.5"/>',
        'check-circle'=> '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5 11 15l4.5-5"/>',
        'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
        'users'      => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M16.5 19a5.5 5.5 0 0 0-2-4"/>',
        'plus'       => '<path d="M12 5v14M5 12h14"/>',
        'copy'       => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'sparkle'    => '<path d="M12 4.5 13.6 9 18 10.5 13.6 12 12 16.5 10.4 12 6 10.5 10.4 9z"/><path d="M18.5 4.5l.6 1.6 1.6.6-1.6.6-.6 1.6-.6-1.6-1.6-.6 1.6-.6z"/>',
        'robot'      => '<rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 8V4M9 4h6"/><circle cx="9" cy="13" r="1.2" fill="currentColor" stroke="none"/><circle cx="15" cy="13" r="1.2" fill="currentColor" stroke="none"/><path d="M9.5 16.5h5"/>',
        'bell'       => '<path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 19a2 2 0 0 0 4 0"/>',
        'search'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'star'       => '<path d="M12 3.5l2.6 5.6 6 .8-4.4 4.1 1.1 6L12 17.3 6.7 20l1.1-6L3.4 9.9l6-.8z"/>',
        'arrow-right'=> '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'chevron-right'=> '<path d="M9 6l6 6-6 6"/>',
        'file'       => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
        'download'   => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>',
        'edit'       => '<path d="M15.5 4.5l4 4L8 20H4v-4z"/><path d="M13.5 6.5l4 4"/>',
        'send'       => '<path d="M21 4 3 11l6 2.5L11 20l3.5-6L21 4z"/><path d="M9 13.5 21 4"/>',
        'x'          => '<path d="M6 6l12 12M18 6 6 18"/>',
        'trophy'     => '<path d="M8 4h8v4a4 4 0 0 1-8 0z"/><path d="M8 5H5v2a3 3 0 0 0 3 3M16 5h3v2a3 3 0 0 1-3 3"/><path d="M12 12v4M9 20h6M10 16h4l.5 4h-5z"/>',
        'bulb'       => '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 1 4 10.5c-.7.7-1 1.2-1 2.5H9c0-1.3-.3-1.8-1-2.5A6 6 0 0 1 12 3z"/>',
        'target'     => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1" fill="currentColor" stroke="none"/>',
        'flag'       => '<path d="M5 21V4M5 4h11l-1.5 3.5L16 11H5"/>',
        'calendar'   => '<rect x="4" y="5" width="16" height="16" rx="2.5"/><path d="M4 9.5h16M8 3v4M16 3v4"/>',
        'thumbs-up'  => '<path d="M7 11v9H4a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1z"/><path d="M7 11l4-7a2 2 0 0 1 2 1.5V9h5a2 2 0 0 1 2 2.3l-1 6a2 2 0 0 1-2 1.7H7"/>',
        'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'message'    => '<path d="M21 12a8 8 0 0 1-11.5 7.2L4 20l.8-5.5A8 8 0 1 1 21 12z"/>',
        'sun'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.5 4.5l1.4 1.4M18.1 18.1l1.4 1.4M2 12h2M20 12h2M4.5 19.5l1.4-1.4M18.1 5.9l1.4-1.4"/>',
        'moon'       => '<path d="M20 14.5A8 8 0 0 1 9.5 4a7 7 0 1 0 10.5 10.5z"/>',
        'monitor'    => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'folder'     => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'globe'      => '<circle cx="12" cy="12" r="9"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>',
        'lock'       => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ];
    $inner = $paths[$name] ?? '';
    $ca    = $cls ? " class=\"" . h($cls) . "\"" : '';
    return sprintf(
        '<svg%s width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="%.1f" stroke-linecap="round" stroke-linejoin="round">%s</svg>',
        $ca, $size, $size, h($color), $sw, $inner
    );
}

// ── UI Components ───────────────────────────────────────────────────────────

/** Avatar สำหรับ course card (ใช้ avatar ครูเจ้าของ) */
function course_avatar(array $course, string $extra_style = ''): string
{
    $av  = h($course['teacher_av']       ?? 'av-1');
    $ini = h($course['teacher_initials'] ?? $course['short_name'] ?? '?');
    return "<span class=\"avatar {$av} cc-av\"{$extra_style}>{$ini}</span>";
}

function avatar(array $user, int $size = 38): string
{
    $av = h($user['avatar_class'] ?? 'av-1');
    $in = h($user['initials'] ?? '?');
    $fs = (int)round($size * 0.38);
    return "<span class=\"avatar {$av}\" style=\"width:{$size}px;height:{$size}px;font-size:{$fs}px\">{$in}</span>";
}

function ai_pill(string $id, string $size = 'md'): string
{
    $ai = get_ai($id);
    if (!$ai) return '';
    $sm    = $size === 'sm';
    $style = $sm ? 'padding:3px 9px 3px 4px;font-size:11.5px' : '';
    $lw    = $sm ? 17 : 20;
    $lfs   = $sm ? 9  : 11;
    return sprintf(
        '<span class="ai-pill" style="%s"><span class="ai-logo" style="background:%s;width:%dpx;height:%dpx;font-size:%dpx">%s</span>%s</span>',
        $style, h($ai['color']), $lw, $lw, $lfs, h($ai['letter']), h($ai['name'])
    );
}

function ai_select(string $name, string $selected = ''): string
{
    $tools  = get_ai_tools();
    $none   = $selected === '' ? ' selected' : '';
    $opts   = "<option value=\"\"{$none}>— ไม่ระบุ AI —</option>";
    foreach ($tools as $t) {
        $sel   = $t['id'] === $selected ? ' selected' : '';
        $opts .= "<option value=\"" . h($t['id']) . "\"{$sel}>" . h($t['name']) . "</option>";
    }
    $color  = ($selected && isset($tools[$selected])) ? h($tools[$selected]['color'])  : 'var(--line-2)';
    $letter = ($selected && isset($tools[$selected])) ? h($tools[$selected]['letter']) : '—';
    return <<<HTML
    <div class="ai-select-wrap" style="position:relative">
        <select class="select" name="{$name}" id="sel-{$name}"
                style="padding-left:40px;appearance:none;font-weight:600"
                onchange="updateAiSelect(this)">
            {$opts}
        </select>
        <span class="ai-logo ai-sel-logo" id="logo-{$name}"
              style="background:{$color};position:absolute;left:11px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:10px">{$letter}</span>
        <span style="position:absolute;right:13px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--muted)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="transform:rotate(90deg)"><path d="M9 6l6 6-6 6"/></svg>
        </span>
    </div>
    HTML;
}

function star_rating(int $value, int $size = 15): string
{
    $out = '<span style="display:inline-flex;gap:2px">';
    for ($i = 1; $i <= 5; $i++) {
        $fill  = $i <= $value ? '#ff9f43' : '#e4e7ee';
        $out  .= "<svg width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 24 24\"><path d=\"M12 3.5l2.6 5.6 6 .8-4.4 4.1 1.1 6L12 17.3 6.7 20l1.1-6L3.4 9.9l6-.8z\" fill=\"{$fill}\"/></svg>";
    }
    return $out . '</span>';
}

function star_input(int $value = 4, string $name = 'rating'): string
{
    $out = "<div class=\"star-input\" data-name=\"{$name}\" data-value=\"{$value}\" style=\"display:inline-flex;align-items:center;gap:4px;cursor:pointer\">";
    for ($i = 1; $i <= 5; $i++) {
        $fill = $i <= $value ? '#ff9f43' : '#e4e7ee';
        $out .= "<svg data-v=\"{$i}\" width=\"26\" height=\"26\" viewBox=\"0 0 24 24\" onclick=\"setStars(this)\" style=\"cursor:pointer\"><path d=\"M12 3.5l2.6 5.6 6 .8-4.4 4.1 1.1 6L12 17.3 6.7 20l1.1-6L3.4 9.9l6-.8z\" fill=\"{$fill}\"/></svg>";
    }
    $out .= "<span class=\"badge gray star-badge\" style=\"margin-left:4px\">{$value}/5</span>";
    $out .= "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\"></div>";
    return $out;
}

function ai_prompt_url(string $ai_id, string $prompt = ''): string
{
    $q = urlencode($prompt);
    $with_prompt = [
        'chatgpt'    => "https://chat.openai.com/?q={$q}",
        'claude'     => "https://claude.ai/new?q={$q}",
        'gemini'     => "https://gemini.google.com/app?q={$q}",
        'copilot'    => "https://copilot.microsoft.com/?q={$q}",
        'perplexity' => "https://www.perplexity.ai/search?q={$q}",
    ];
    $base = [
        'chatgpt'    => 'https://chat.openai.com/',
        'claude'     => 'https://claude.ai/',
        'gemini'     => 'https://gemini.google.com/',
        'copilot'    => 'https://copilot.microsoft.com/',
        'perplexity' => 'https://www.perplexity.ai/',
    ];
    if ($q && isset($with_prompt[$ai_id])) return $with_prompt[$ai_id];
    if (isset($base[$ai_id]))              return $base[$ai_id];
    $ai = get_ai($ai_id);
    return $ai ? 'https://' . $ai['url'] : '#';
}

// We can't use heredoc with function calls inside, so we wrap in a helper
function render_prompt_block(array $p, string $title = 'Prompt ที่ครูแนะนำ'): void
{
    $id      = 'pb' . substr(md5((string)($p['id'] ?? uniqid())), 0, 6);
    // Use specified AI or pick a random one
    $ai_id   = $p['ai_id'] ?? '';
    if (!$ai_id) {
        $tools = get_ai_tools();
        if ($tools) {
            $keys  = array_keys($tools);
            $ai_id = $keys[array_rand($keys)];
        }
    }
    $ai      = get_ai($ai_id);
    $rating  = (int)($p['rating'] ?? 0);
    ?>
    <div class="prompt-block">
        <div class="prompt-block__head">
            <span class="pb-title"><?= icon('sparkle', 17, 'var(--primary)') ?> <?= h($title) ?></span>
            <span style="margin-left:auto;display:flex;align-items:center;gap:10px">
                <span class="subtle" style="font-size:12px">AI ที่แนะนำ</span>
                <?php if ($ai): ?>
                <a href="<?= h(ai_prompt_url($ai_id, $p['prompt_text'] ?? '')) ?>"
                   target="_blank" rel="noopener"
                   style="text-decoration:none" title="เปิด <?= h($ai['name']) ?> พร้อม prompt นี้">
                    <?= ai_pill($ai_id, 'sm') ?>
                </a>
                <?php endif; ?>
            </span>
        </div>
        <div class="prompt-body">
            <div style="display:flex;align-items:center;margin-bottom:9px">
                <span class="subtle" style="font-size:12.5px;font-weight:600">ข้อความ Prompt</span>
                <button type="button" class="btn btn-sm btn-ghost copy-btn"
                        data-copy="<?= h($p['prompt_text'] ?? '') ?>"
                        style="margin-left:auto">
                    <?= icon('copy', 15) ?> <span>คัดลอก</span>
                </button>
            </div>
            <div class="prompt-text"><?= h($p['prompt_text'] ?? '') ?></div>

            <div style="display:flex;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap">
                <span class="subtle" style="font-size:12.5px;font-weight:600">ระดับความพอใจของครู</span>
                <?= star_rating($rating) ?>
                <span class="badge gray" style="font-size:11px"><?= $rating ?>/5</span>
                <?php if (!empty($p['example_text']) || !empty($p['example_file'])): ?>
                <button type="button" class="btn btn-sm btn-ghost" style="margin-left:auto"
                        onclick="toggleEl('<?= $id ?>-ex','<?= $id ?>-lbl','ดูผลลัพธ์ตัวอย่างที่ครูได้','ซ่อนผลลัพธ์ตัวอย่าง')">
                    <?= icon('bulb', 15) ?>
                    <span id="<?= $id ?>-lbl">ดูผลลัพธ์ตัวอย่างที่ครูได้</span>
                </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($p['example_text']) || !empty($p['example_file'])): ?>
            <div class="ex-box" id="<?= $id ?>-ex" style="margin-top:12px;padding:12px 14px;display:none">
                <div style="font-size:12px;font-weight:700;color:var(--accent-700);margin-bottom:6px;display:flex;align-items:center;gap:6px">
                    <?= icon('robot', 15, 'var(--accent-700)') ?>
                    ผลลัพธ์ตัวอย่างจาก <?= $ai ? h($ai['name']) : '' ?>
                </div>
                <?php if (!empty($p['example_text'])): ?>
                <div style="font-size:13.5px;color:var(--heading);line-height:1.6"><?= h($p['example_text']) ?></div>
                <?php endif; ?>
                <?php if (!empty($p['example_file'])): ?>
                <?php $ef = $p['example_file']; $ef_ext = strtolower(pathinfo($ef, PATHINFO_EXTENSION)); ?>
                <?php if (in_array($ef_ext, ['jpg','jpeg','png','gif','webp'])): ?>
                <img src="<?= h($ef) ?>" alt="ผลลัพธ์ตัวอย่าง"
                     style="max-width:100%;max-height:320px;object-fit:contain;border-radius:8px;margin-top:8px;display:block">
                <?php else: ?>
                <a href="<?= h($ef) ?>" target="_blank" rel="noopener"
                   class="btn btn-sm btn-ghost" style="margin-top:8px;display:inline-flex">
                    <?= icon('download', 14) ?> <?= $ef_ext === 'pdf' ? 'ดาวน์โหลด PDF ตัวอย่าง' : 'ดาวน์โหลดไฟล์ตัวอย่าง' ?>
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($p['note_text'])): ?>
            <div class="note-box" style="margin-top:12px;display:flex;gap:9px;align-items:flex-start;padding:11px 13px">
                <?= icon('flag', 16, 'var(--warn-ink)') ?>
                <div style="font-size:13px;color:var(--warn-ink);line-height:1.55">
                    <b>หมายเหตุจากครู:</b> <?= h($p['note_text']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function file_badge(string $type): string
{
    $map = [
        'pdf' => ['#ea5455', 'PDF'],
        'ppt' => ['#ff9f43', 'PPT'],
        'img' => ['#a371f7', 'IMG'],
        'doc' => ['#3b7df5', 'DOC'],
    ];
    [$c, $t] = $map[$type] ?? ['#8a94a6', 'FILE'];
    return "<span style=\"width:38px;height:38px;border-radius:9px;background:{$c}22;color:{$c};display:grid;place-items:center;font-size:10px;font-weight:800;flex:0 0 auto\">{$t}</span>";
}
