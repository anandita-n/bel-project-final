<?php

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $parts = array_filter($parts);
    if (empty($parts)) return '?';
    if (count($parts) === 1) return mb_strtoupper(mb_substr($parts[0], 0, 2));
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
}

function avatar_class(string $role): string {
    return match ($role) {
        'admin' => 'avatar-admin',
        'manager' => 'avatar-manager',
        default => 'avatar-employee',
    };
}

/** A person's avatar: their uploaded photo if they have one, otherwise initials.
 *  $person needs 'id', 'name', 'role', and either 'has_photo' or 'photo_filename'. */
function render_avatar(array $person, string $sizeClass = ''): string {
    $cls = trim('avatar ' . $sizeClass . ' ' . avatar_class($person['role'] ?? 'employee'));
    $hasPhoto = !empty($person['has_photo']) || !empty($person['photo_filename']);
    if ($hasPhoto && !empty($person['id'])) {
        return '<img class="' . htmlspecialchars($cls) . ' avatar-img" src="api/employees/photo.php?action=view&id=' . (int)$person['id'] . '" alt="' . htmlspecialchars($person['name'] ?? '') . '">';
    }
    return '<span class="' . htmlspecialchars($cls) . '">' . htmlspecialchars(initials($person['name'] ?? '')) . '</span>';
}

/** Per-rule pass/fail breakdown, used both to validate server-side and to mirror the live
 *  checklist shown in the UI (assets/js/password-rules.js implements the same rules in JS). */
function password_rule_results(string $password): array {
    return [
        'length' => mb_strlen($password) >= 8 && mb_strlen($password) <= 32,
        'upper' => (bool)preg_match('/[A-Z]/', $password),
        'lower' => (bool)preg_match('/[a-z]/', $password),
        'number' => (bool)preg_match('/[0-9]/', $password),
        'special' => (bool)preg_match('/[^A-Za-z0-9]/', $password),
        'no_spaces' => !preg_match('/\s/', $password),
    ];
}

function is_valid_password(string $password): bool {
    return !in_array(false, password_rule_results($password), true);
}

function is_valid_email(string $email): bool {
    if ($email === '' || preg_match('/\s/', $email)) {
        return false;
    }
    if (substr_count($email, '@') !== 1) {
        return false;
    }
    if (str_starts_with($email, '.') || str_starts_with($email, '@') || str_ends_with($email, '.') || str_ends_with($email, '@')) {
        return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    [, $domain] = explode('@', $email);
    return (bool)preg_match('/\.[A-Za-z]{2,}$/', $domain);
}

/** A person's full name: Unicode letters, spaces, hyphens, and apostrophes only (so real names
 *  like "O'Brien" or "Anne-Marie" work, and non-Latin scripts aren't rejected) — no digits,
 *  symbols, HTML/script characters, or emoji. Rejects double spaces and requires 2–100 chars
 *  after trimming, with at least one actual letter (so "- -" or "''" alone doesn't pass). */
function is_valid_full_name(string $name): bool {
    $name = trim($name);
    $len = mb_strlen($name);
    if ($len < 2 || $len > 100) {
        return false;
    }
    if (preg_match('/\s{2,}/u', $name)) {
        return false;
    }
    // Must start and end with a letter (no leading/trailing hyphen or apostrophe); letters,
    // combining marks (\p{M} — vowel signs/virama etc. in Devanagari, Tamil, Vietnamese diacritics...),
    // spaces, hyphens, and apostrophes only in between.
    return (bool)preg_match('/^\p{L}[\p{L}\p{M}\s\'-]*[\p{L}\p{M}]$/u', $name);
}

/** Free-text organisational fields (Stream, Group): letters/digits/spaces and a handful of
 *  punctuation marks real values use ("R&D", "Tier-1, APAC") — still blocks HTML/script
 *  characters and other symbol noise. Optional fields, so an empty string is always valid. */
function is_valid_org_field(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return true;
    }
    if (mb_strlen($value) > 100) {
        return false;
    }
    return (bool)preg_match('/^[\p{L}\p{N}\s\'\-&,.\/]+$/u', $value);
}

/** Overlapping avatar circles + a "+N" overflow bubble, for showing a task's list of assignees at a glance.
 *  $people: array of {id,name,role}. */
function render_avatar_stack(array $people, int $max = 4): string {
    if (!$people) {
        return '<span class="avatar-stack-empty">Unassigned</span>';
    }
    $shown = array_slice($people, 0, $max);
    $overflow = count($people) - count($shown);
    $names = implode(', ', array_column($people, 'name'));

    $html = '<span class="avatar-stack" title="' . htmlspecialchars($names) . '">';
    foreach ($shown as $p) {
        $html .= render_avatar($p, 'avatar-sm');
    }
    if ($overflow > 0) {
        $html .= '<span class="avatar avatar-sm avatar-stack-more">+' . $overflow . '</span>';
    }
    $html .= '</span>';
    return $html;
}

/** Grid/List "Assignee" column variant — avatar plus the person's name, one per line, instead
 *  of an overlapping icon-only stack (that's fine on cards, not in a data table). */
function render_avatar_stack_with_names(array $people): string {
    if (!$people) {
        return '<span class="avatar-stack-empty">Unassigned</span>';
    }
    $html = '<div class="assignee-name-list">';
    foreach ($people as $p) {
        $html .= '<span class="assignee-name-row">' . render_avatar($p, 'avatar-sm')
            . '<span class="assignee-name-text">' . htmlspecialchars($p['name']) . '</span></span>';
    }
    $html .= '</div>';
    return $html;
}

function like_escape(string $term): string {
    return addcslashes($term, '%_\\');
}

/** Absolute path to the outside-webroot directory where task attachment files are stored
 *  (never reachable via HTTP — downloads go through api/projects/attachments.php). */
function attachment_upload_dir(): string {
    return dirname(__DIR__, 3) . '/bel-pms-uploads/task_attachments/';
}

/** Same outside-webroot convention as the other upload dirs — photos are served through
 *  api/employees/photo.php rather than a direct URL. */
function employee_photo_upload_dir(): string {
    return dirname(__DIR__, 3) . '/bel-pms-uploads/employee_photos/';
}

function allowed_photo_extensions(): array {
    return ['png', 'jpg', 'jpeg'];
}

function allowed_photo_mime_types(): array {
    return ['image/png', 'image/jpeg'];
}

function employee_photo_max_upload_bytes(): int {
    return 5 * 1024 * 1024; // 5MB
}

function project_document_upload_dir(): string {
    return dirname(__DIR__, 3) . '/bel-pms-uploads/project_documents/';
}

/** Shared allowlist for project document uploads (Create Project Step 1 and the project
 *  workspace's Documents tab both validate against these — keep them in sync). */
function allowed_document_extensions(): array {
    return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'zip'];
}

function allowed_document_mime_types(): array {
    return [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'image/png',
        'image/jpeg',
        'application/zip',
        'application/x-zip-compressed',
    ];
}

/** Per-upload limits shared across project document upload points (Step 1, the Documents tab). */
function project_document_max_files_per_upload(): int {
    return 10;
}

function project_document_max_upload_bytes(): int {
    return 50 * 1024 * 1024; // 50MB
}

function forum_attachment_upload_dir(): string {
    return dirname(__DIR__, 3) . '/bel-pms-uploads/forum_attachments/';
}

/** Thin wrapper around NotificationRepository::create() so API files stay short.
 *  Never notifies a user about their own action (enforced in the repository). */
function notify_project_event(int $userId, ?int $actorId, int $projectId, ?int $taskId, string $type, string $message): void {
    (new \App\Repositories\NotificationRepository())->create($userId, $actorId, $projectId, $taskId, $type, $message);
}

/** Blocks any state-changing API action (create/update/delete task, defect, member, comment,
 *  attachment, document, dependency, review, project details...) once a project is archived —
 *  archived projects are read-only. Reads (list/view actions) must NOT call this; historical
 *  data stays fully visible. API-only (relies on json_error from api/_bootstrap.php). */
function require_project_active(array $project): void {
    if (!empty($project['archived_at'])) {
        json_error('This project is archived and read-only. Restore it to make changes.', 403);
    }
}

/** softDelete() suffixes email/employee_code with the row's own id so a new hire can reuse the
 *  original values; these strip that suffix back off for display, matching what reactivate()
 *  would restore them to. No-ops on an active employee's already-clean values. */
function display_employee_code(array $e): string {
    $suffix = '-DEL' . $e['id'];
    return str_ends_with($e['employee_code'], $suffix) ? substr($e['employee_code'], 0, -strlen($suffix)) : $e['employee_code'];
}
function display_employee_email(array $e): string {
    $suffix = '.deleted' . $e['id'];
    return str_ends_with($e['email'], $suffix) ? substr($e['email'], 0, -strlen($suffix)) : $e['email'];
}

/** Server-side render of an employee row — mirrors employeeRowHTML() in employees.js.
 *  $inactive: deactivated employees have no working profile link (employee_detail.php only
 *  finds active users) and get a Reactivate button in place of the Reports To column. */
function render_employee_row(array $e, bool $inactive = false): string {
    $html = '<tr data-id="' . $e['id'] . '" data-name="' . htmlspecialchars($e['name']) . '"'
        . ' data-role="' . htmlspecialchars($e['role']) . '"'
        . ' data-department="' . htmlspecialchars($e['department'] ?? '') . '"'
        . ' data-telephone="' . htmlspecialchars($e['telephone'] ?? '') . '"'
        . ' data-manager-id="' . htmlspecialchars($e['manager_id'] ?? '') . '"'
        . ' data-manager-name="' . htmlspecialchars($e['manager_name'] ?? '') . '">';
    $html .= '<td><div class="row-name">' . render_avatar($e);
    $html .= $inactive
        ? '<span>' . htmlspecialchars($e['name']) . '</span>'
        : '<a href="employee_detail.php?id=' . $e['id'] . '">' . htmlspecialchars($e['name']) . '</a>';
    $html .= '</div></td>';
    $html .= $inactive
        ? '<td>' . htmlspecialchars(display_employee_code($e)) . '</td>'
        : '<td><a class="code-link" href="employee_detail.php?id=' . $e['id'] . '">' . htmlspecialchars($e['employee_code']) . '</a></td>';
    $html .= '<td>' . htmlspecialchars($inactive ? display_employee_email($e) : $e['email']) . '</td>';
    $html .= '<td><span class="dir-badge dir-badge-' . htmlspecialchars($e['role']) . '">' . htmlspecialchars(ucfirst($e['role'])) . '</span></td>';
    $html .= '<td class="dept-cell">' . htmlspecialchars($e['department'] ?: '—') . '</td>';
    $html .= $inactive
        ? '<td><button type="button" class="pill-btn pill-btn-sm reactivate-btn">Reactivate</button></td>'
        : '<td class="manager-cell">' . htmlspecialchars($e['manager_name'] ?? '—') . '</td>';
    $html .= '</tr>';
    return $html;
}

/** Server-side render of a project document row — reuses the .attachment-row markup/CSS built for task attachments. */
function render_document_row(array $d, int $projectId, bool $canManage): string {
    $canRemove = $canManage;
    $downloadUrl = 'api/projects/documents.php?action=download&id=' . $d['id'] . '&project_id=' . $projectId;
    $html = '<div class="attachment-row" data-document-id="' . $d['id'] . '">';
    $html .= '<svg class="attachment-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    $html .= '<div class="attachment-info">';
    $html .= '<a href="' . $downloadUrl . '" target="_blank" class="attachment-name">' . htmlspecialchars($d['original_filename']) . '</a>';
    $sizeKb = round($d['size_bytes'] / 1024);
    $sizeLabel = $sizeKb < 1024 ? $sizeKb . ' KB' : round($d['size_bytes'] / 1024 / 1024, 1) . ' MB';
    $html .= '<div class="attachment-meta">' . htmlspecialchars($d['uploader_name']) . ' &middot; ' . htmlspecialchars(date('M j, Y', strtotime($d['created_at']))) . ' &middot; ' . $sizeLabel . '</div>';
    $html .= '</div>';
    if ($canRemove) {
        $html .= '<button type="button" class="attachment-remove document-remove" title="Remove">&times;</button>';
    }
    $html .= '</div>';
    return $html;
}

/** A "Key Documents" card for the project Overview tab — filename, file-type + updated date. */
function render_key_document_card(array $d, int $projectId): string {
    $downloadUrl = 'api/projects/documents.php?action=download&id=' . $d['id'] . '&project_id=' . $projectId;
    $ext = strtoupper(pathinfo($d['original_filename'], PATHINFO_EXTENSION)) ?: 'FILE';
    $html = '<a href="' . $downloadUrl . '" target="_blank" class="ov-doc-card">';
    $html .= '<svg class="ov-doc-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
    $html .= '<div class="ov-doc-info">';
    $html .= '<div class="ov-doc-name">' . htmlspecialchars($d['original_filename']) . '</div>';
    $html .= '<div class="ov-doc-meta">' . htmlspecialchars($ext) . ' &middot; Updated ' . htmlspecialchars(date('M j', strtotime($d['created_at']))) . '</div>';
    $html .= '</div>';
    $html .= '</a>';
    return $html;
}

/** The project detail page's "Overview" tab: Department/Project Manager on one row,
 *  Created on/Due date on the next, a divider, then Description. $withIds tags the
 *  Department/Due date values with ids so project-detail.js can live-update them after an edit. */
/** The rich, card-based About tab (Description [+ documents], Project Details, Project Manager)
 *  used on the full project workspace — shared with the read-only (same-department, non-member)
 *  view so both look like the same page, just without edit affordances. $withIds tags the due-date
 *  value with id="phDueDate" so the full page's "Edit Details" JS can update it in place; the
 *  read-only view has no such JS, so it passes false. */
function render_project_about_card(array $project, array $documents = [], bool $canEdit = false, bool $withIds = false): string {
    $html = '<div class="ov-sections">';
    $html .= '<div class="ov-row">';

    $html .= '<div class="ov-section ov-section-narrow">';
    $html .= '<div class="ov-box-head"><span class="ov-box-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span><h3 class="ov-box-title">Description</h3></div>';
    $html .= '<p class="ov-card-text">' . ($project['description'] ? nl2br(htmlspecialchars($project['description'])) : 'No description provided.') . '</p>';
    if (!empty($documents)) {
        $html .= '<div class="ov-desc-docs" id="ovDescDocs">';
        foreach ($documents as $d) {
            $html .= render_document_row($d, (int)$project['id'], $canEdit);
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    $dueDateId = $withIds ? ' id="phDueDate"' : '';
    $html .= '<div class="ov-section ov-section-fixed">';
    $html .= '<div class="ov-box-head"><span class="ov-box-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span><h3 class="ov-box-title">Project Details</h3></div>';
    $html .= '<div class="ov-details-row">';
    $html .= '<div class="ov-details-col"><div class="ov-label">Start date</div><div class="ov-value">' . ($project['start_date'] ? htmlspecialchars(date('M j, Y', strtotime($project['start_date']))) : '—') . '</div></div>';
    $html .= '<div class="ov-details-col ov-details-col-divided"><div class="ov-label">Due date</div><div class="ov-value"' . $dueDateId . '>' . ($project['due_date'] ? htmlspecialchars(date('M j, Y', strtotime($project['due_date']))) : '—') . '</div></div>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>'; // .ov-row

    $html .= '<div class="ov-section ov-section-plain">';
    $html .= '<div class="ov-box-head"><span class="ov-box-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a8 8 0 0 0-16 0v2"/><circle cx="12" cy="7" r="4"/></svg></span><h3 class="ov-box-title">Project Manager</h3></div>';
    $html .= '<div class="ov-pm-row">';
    $html .= '<div class="ov-pm-row-item ov-pm-row-identity">'
        . render_avatar(['id' => $project['manager_id'], 'name' => $project['manager_name'], 'role' => $project['manager_role'], 'photo_filename' => $project['manager_photo_filename'] ?? null])
        . '<div><div class="ov-team-name">' . htmlspecialchars($project['manager_name']) . '</div>';
    if (!empty($project['manager_employee_code'])) {
        $html .= '<span class="ov-pm-id-badge">' . htmlspecialchars($project['manager_employee_code']) . '</span>';
    }
    $html .= '</div></div>';
    $html .= '<div class="ov-pm-row-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> ' . htmlspecialchars($project['manager_email']) . '</div>';
    if (!empty($project['manager_telephone'])) {
        $html .= '<div class="ov-pm-row-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg> ' . htmlspecialchars($project['manager_telephone']) . '</div>';
    }
    $html .= '<div class="ov-pm-row-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ' . htmlspecialchars($project['manager_department'] ?: '—') . ' Department</div>';
    $html .= '</div>'; // .ov-pm-row
    $html .= '</div>'; // .ov-section-plain

    $html .= '</div>'; // .ov-sections
    return $html;
}

/** Server-side render of a project row — mirrors projectRowHTML() in projects.php's inline JS. */
function render_project_row(array $p): string {
    $managerRole = $p['manager_role'] ?? 'manager';
    $manager = ['id' => $p['manager_id'], 'name' => $p['manager_name'], 'role' => $managerRole, 'photo_filename' => $p['manager_photo_filename'] ?? null];
    $html = '<tr>';
    $html .= '<td><a href="project_detail.php?id=' . $p['id'] . '">' . htmlspecialchars($p['project_code']) . '</a></td>';
    $html .= '<td><a href="project_detail.php?id=' . $p['id'] . '">' . htmlspecialchars($p['name']) . '</a></td>';
    $html .= '<td><div class="row-name">' . render_avatar($manager, 'avatar-sm') . htmlspecialchars($p['manager_name']) . '</div></td>';
    $html .= '<td><span class="dir-badge dir-badge-' . htmlspecialchars($p['status']) . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $p['status']))) . '</span></td>';
    $html .= '</tr>';
    return $html;
}

/** One row of the Archived Projects table — code, name, department, manager, original status,
 *  archived date/by. Visually distinguished from the active project-table rows via .row-archived
 *  (muted text) so an archived list never reads as an active one. */
function render_archived_project_row(array $p): string {
    $managerRole = $p['manager_role'] ?? 'manager';
    $manager = ['id' => $p['manager_id'], 'name' => $p['manager_name'], 'role' => $managerRole, 'photo_filename' => $p['manager_photo_filename'] ?? null];
    $html = '<tr class="row-archived">';
    $html .= '<td><a href="project_detail.php?id=' . $p['id'] . '">' . htmlspecialchars($p['project_code']) . '</a></td>';
    $html .= '<td><a href="project_detail.php?id=' . $p['id'] . '">' . htmlspecialchars($p['name']) . '</a></td>';
    $html .= '<td>' . htmlspecialchars($p['department'] ?: '—') . '</td>';
    $html .= '<td><div class="row-name">' . render_avatar($manager, 'avatar-sm') . htmlspecialchars($p['manager_name']) . '</div></td>';
    $html .= '<td>' . ($p['archived_at'] ? htmlspecialchars(date('M j, Y', strtotime($p['archived_at']))) : '—') . '</td>';
    $html .= '</tr>';
    return $html;
}

/** The employee profile page's Projects panel — a card-list (code + name, role, a status badge
 *  and chevron on the right), with a search box (name/code), a status filter, a Newest/Oldest
 *  sort, and Prev/Next pagination once the filtered list exceeds one page, all client-side. */
function render_emp_projects_table(array $projects, string $employeeName, bool $isOwnProfile = false): string {
    $html = '<div class="emp-proj-toolbar">';
    $html .= '<h3 class="emp-proj-heading">Projects</h3>';
    if (!empty($projects)) {
        $html .= '<div class="emp-proj-toolbar-tools">';
        $html .= '<select class="filter-select" id="empProjectsStatus">';
        $html .= '<option value="">All statuses</option>';
        $html .= '<option value="active">Active</option>';
        $html .= '<option value="on_hold">On hold</option>';
        $html .= '<option value="completed">Completed</option>';
        $html .= '</select>';
        $html .= '<select class="filter-select" id="empProjectsSort">';
        $html .= '<option value="newest">Newest first</option>';
        $html .= '<option value="oldest">Oldest first</option>';
        $html .= '</select>';
        $html .= '<div class="search-bar emp-proj-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input type="text" id="empProjectsSearch" placeholder="Search projects…"></div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    if (empty($projects)) {
        return $html . '<div class="empty-state">Not currently part of any project.</div>';
    }

    $html .= '<div class="emp-proj-list" id="empProjectsList">';
    foreach ($projects as $p) {
        $statusClass = htmlspecialchars($p['status']);
        $statusLabel = htmlspecialchars(ucfirst(str_replace('_', ' ', $p['status'])));
        $searchKey = strtolower($p['project_code'] . ' ' . $p['name']);
        $startTs = $p['start_date'] ? strtotime($p['start_date']) : 0;
        $html .= '<a href="project_detail.php?id=' . $p['id'] . '" class="emp-proj-row" data-search="' . htmlspecialchars($searchKey) . '" data-status="' . $statusClass . '" data-start="' . $startTs . '">';
        $html .= '<div class="emp-proj-main">';
        $html .= '<div class="emp-proj-code">' . htmlspecialchars($p['project_code']) . '</div>';
        $html .= '<div class="emp-proj-name">' . htmlspecialchars($p['name']) . '</div>';
        $html .= '<div class="emp-proj-meta">' . htmlspecialchars($p['role_in_project']) . '</div>';
        $html .= '</div>';
        $html .= '<span class="dir-badge dir-badge-' . $statusClass . ' emp-proj-status">' . $statusLabel . '</span>';
        $html .= '<span class="emp-proj-chevron">&rsaquo;</span>';
        $html .= '</a>';
    }
    $html .= '</div>';
    $html .= '<div class="emp-proj-footer" id="empProjectsFooter"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg> <span id="empProjectsFooterText">Showing ' . count($projects) . ' of ' . count($projects) . ' project' . (count($projects) === 1 ? '' : 's') . '</span></div>';
    $html .= '<div class="pagination-bar" id="empProjectsPager" style="display:none;">';
    $html .= '<button type="button" class="btn btn-secondary btn-sm" id="empProjectsPrev">&larr; Prev</button>';
    $html .= '<span class="pagination-info" id="empProjectsPageInfo"></span>';
    $html .= '<button type="button" class="btn btn-secondary btn-sm" id="empProjectsNext">Next &rarr;</button>';
    $html .= '</div>';

    $html .= '<script>(function(){' .
        'var PAGE_SIZE=5;' .
        'var list=document.getElementById("empProjectsList");' .
        'var input=document.getElementById("empProjectsSearch");' .
        'var statusSelect=document.getElementById("empProjectsStatus");' .
        'var sortSelect=document.getElementById("empProjectsSort");' .
        'if(!input||!sortSelect)return;' .
        'var rows=Array.prototype.slice.call(document.querySelectorAll("#empProjectsList .emp-proj-row"));' .
        'var footer=document.getElementById("empProjectsFooterText");' .
        'var pager=document.getElementById("empProjectsPager");' .
        'var pageInfo=document.getElementById("empProjectsPageInfo");' .
        'var prevBtn=document.getElementById("empProjectsPrev");' .
        'var nextBtn=document.getElementById("empProjectsNext");' .
        'var total=rows.length;' .
        'var page=1;' .
        'function matches(r){' .
        'var q=input.value.trim().toLowerCase();var status=statusSelect.value;' .
        'return (!q||r.dataset.search.indexOf(q)!==-1)&&(!status||r.dataset.status===status);' .
        '}' .
        'function render(){' .
        'var filtered=rows.filter(matches);' .
        'var pageCount=Math.max(1,Math.ceil(filtered.length/PAGE_SIZE));' .
        'if(page>pageCount)page=pageCount;' .
        'var start=(page-1)*PAGE_SIZE;' .
        'var end=start+PAGE_SIZE;' .
        'rows.forEach(function(r){r.style.display="none";});' .
        'filtered.slice(start,end).forEach(function(r){r.style.display="";});' .
        'if(footer)footer.textContent="Showing "+filtered.length+" of "+total+" project"+(total===1?"":"s");' .
        'if(pager)pager.style.display=pageCount>1?"flex":"none";' .
        'if(pageInfo)pageInfo.textContent="Page "+page+" of "+pageCount;' .
        'if(prevBtn)prevBtn.disabled=page<=1;' .
        'if(nextBtn)nextBtn.disabled=page>=pageCount;' .
        '}' .
        'function applyFilter(){page=1;render();}' .
        'function applySort(){' .
        'var dir=sortSelect.value==="oldest"?1:-1;' .
        'rows.sort(function(a,b){return dir*((+a.dataset.start)-(+b.dataset.start));});' .
        'rows.forEach(function(r){list.appendChild(r);});' .
        'page=1;render();' .
        '}' .
        'input.addEventListener("input",applyFilter);' .
        'statusSelect.addEventListener("change",applyFilter);' .
        'sortSelect.addEventListener("change",applySort);' .
        'prevBtn.addEventListener("click",function(){if(page>1){page--;render();}});' .
        'nextBtn.addEventListener("click",function(){page++;render();});' .
        'render();' .
        '})();</script>';

    return $html;
}

/** "3 days ago" / "2 hours ago" style relative timestamp, falling back to a plain date once
 *  it's more than a month old (matches how most activity feeds handle old items). */
function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    $mins = (int)floor($diff / 60);
    if ($mins < 60) return $mins . ' minute' . ($mins === 1 ? '' : 's') . ' ago';
    $hours = (int)floor($mins / 60);
    if ($hours < 24) return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    $days = (int)floor($hours / 24);
    if ($days < 30) return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    return date('M j, Y', strtotime($datetime));
}

function render_emp_assets_table(array $assets, bool $isOwnProfile = false): string {
    $html = '<h3 class="plain-section-heading">' . ($isOwnProfile ? 'My Assets' : 'Assets') . '</h3>';
    if (empty($assets)) {
        return $html . '<div class="empty-state">No assets assigned.</div>';
    }
    $categories = \App\Repositories\AssetRepository::CATEGORIES;
    $statuses = \App\Repositories\AssetRepository::STATUSES;
    $html .= '<table class="table-bordered-heading"><thead><tr>';
    $html .= '<th>Asset ID</th><th>Name</th><th>Category</th><th>Serial Number</th><th>Status</th>';
    $html .= '</tr></thead><tbody>';
    foreach ($assets as $a) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($a['asset_code']) . '</td>';
        $html .= '<td>' . htmlspecialchars($a['name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($categories[$a['category']] ?? $a['category']) . '</td>';
        $html .= '<td>' . htmlspecialchars($a['serial_number'] ?: '—') . '</td>';
        $html .= '<td><span class="dir-badge ' . asset_status_tag_class($a['status']) . '">' . htmlspecialchars($statuses[$a['status']] ?? $a['status']) . '</span></td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/** Flat dir-badge variant per asset status — same visual language as the Employees/Projects directories. */
function asset_status_tag_class(string $status): string {
    return match ($status) {
        'available' => 'dir-badge-available',
        'assigned' => 'dir-badge-assigned',
        'under_repair' => 'dir-badge-under_repair',
        'retired' => 'dir-badge-retired',
        'lost' => 'dir-badge-lost',
        default => 'dir-badge-retired',
    };
}

/** Server-side render of an asset row — mirrors assetRowHTML() in assets.js's inline search re-render. */
function render_asset_row(array $a, bool $isAdmin): string {
    $categories = \App\Repositories\AssetRepository::CATEGORIES;
    $statuses = \App\Repositories\AssetRepository::STATUSES;
    $html = '<tr data-id="' . $a['id'] . '" data-name="' . htmlspecialchars($a['name']) . '"'
        . ' data-category="' . htmlspecialchars($a['category']) . '"'
        . ' data-serial="' . htmlspecialchars($a['serial_number'] ?? '') . '"'
        . ' data-department="' . htmlspecialchars($a['department'] ?? '') . '"'
        . ' data-purchase-date="' . htmlspecialchars($a['purchase_date'] ?? '') . '"'
        . ' data-warranty-expiry="' . htmlspecialchars($a['warranty_expiry'] ?? '') . '"'
        . ' data-assigned-to="' . htmlspecialchars($a['assigned_to'] ?? '') . '"'
        . ' data-assignee-name="' . htmlspecialchars($a['assignee_name'] ?? '') . '"'
        . ' data-status="' . htmlspecialchars($a['status']) . '">';
    $html .= '<td>' . htmlspecialchars($a['asset_code']) . '</td>';
    $html .= '<td>' . htmlspecialchars($a['name']) . '</td>';
    $html .= '<td>' . htmlspecialchars($categories[$a['category']] ?? $a['category']) . '</td>';
    $html .= '<td>' . htmlspecialchars($a['serial_number'] ?: '—') . '</td>';
    $html .= '<td>' . htmlspecialchars($a['assignee_name'] ?? '—') . '</td>';
    $html .= '<td>' . htmlspecialchars($a['department'] ?: '—') . '</td>';
    $html .= '<td><span class="dir-badge ' . asset_status_tag_class($a['status']) . '">' . htmlspecialchars($statuses[$a['status']] ?? $a['status']) . '</span></td>';
    if ($isAdmin) {
        $html .= '<td class="actions"><button type="button" class="row-kebab asset-row-kebab" title="More actions">&#8942;</button></td>';
    }
    $html .= '</tr>';
    return $html;
}


function render_label_chip(array $label): string {
    return '<span class="task-label task-label-' . htmlspecialchars($label['color']) . '">' . htmlspecialchars($label['name']) . '</span>';
}

/** Small colored due-date pill: red if overdue (and not done), amber if due today, else neutral. */
function render_due_badge(?string $dueDate, string $status): string {
    if (!$dueDate) {
        return '<span class="due-badge due-badge-none">No date</span>';
    }
    $today = date('Y-m-d');
    $class = 'due-badge-upcoming';
    if ($status !== 'done' && $dueDate < $today) {
        $class = 'due-badge-late';
    } elseif ($dueDate === $today) {
        $class = 'due-badge-today';
    }
    return '<span class="due-badge ' . $class . '">' . htmlspecialchars(date('d M', strtotime($dueDate))) . '</span>';
}

/** Read-only server-rendered task grid — mirrors the no-checkbox/no-kebab variant of
 *  taskListRowHTML()/renderList() in project-detail-views.js, for the same-department
 *  read-only project view where nothing is interactive or editable. */
function render_readonly_task_grid(array $tasks, array $assigneesByTask): string {
    if (empty($tasks)) {
        return '<div class="empty-state">No tasks yet.</div>';
    }
    $statuses = \App\Repositories\TaskRepository::STATUSES;
    $priorityIcons = [
        'high' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
        'medium' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="9" x2="19" y2="9"/><line x1="5" y1="15" x2="19" y2="15"/></svg>',
        'low' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
    ];
    $today = date('Y-m-d');
    $html = '<div id="readonlyTaskWrap"><div class="task-list task-list-readonly" id="readonlyTaskList">';
    $html .= '<div class="task-list-head"><span>Title</span><span>Assignee</span><span>Status</span>'
        . '<span>Priority</span><span>Created</span><span>Updated</span><span>Due</span></div>';
    foreach ($tasks as $t) {
        $priority = $t['priority'] ?: 'medium';
        $assigneeIds = array_column($assigneesByTask[(int)$t['id']] ?? [], 'id');
        $isLate = $t['due_date'] && $t['due_date'] < $today && $t['status'] !== 'done';
        $html .= '<div class="task-list-row"'
            . ' data-title="' . htmlspecialchars(mb_strtolower($t['title'])) . '"'
            . ' data-status="' . htmlspecialchars($t['status']) . '"'
            . ' data-priority="' . htmlspecialchars($priority) . '"'
            . ' data-assignees="' . htmlspecialchars(implode(',', $assigneeIds)) . '"'
            . ' data-created="' . htmlspecialchars(substr($t['created_at'], 0, 10)) . '"'
            . ' data-due="' . htmlspecialchars($t['due_date'] ?? '') . '">';
        $html .= '<span class="task-list-title">' . htmlspecialchars($t['title']) . '</span>';
        $html .= '<span>' . render_avatar_stack_with_names($assigneesByTask[(int)$t['id']] ?? []) . '</span>';
        $html .= '<span><span class="grid-status-badge grid-status-' . htmlspecialchars($t['status']) . '">'
            . htmlspecialchars($statuses[$t['status']] ?? $t['status']) . '</span></span>';
        $html .= '<span><span class="grid-priority grid-priority-' . htmlspecialchars($priority) . '">'
            . ($priorityIcons[$priority] ?? '') . htmlspecialchars(ucfirst($priority)) . '</span></span>';
        $html .= '<span class="task-list-date">' . htmlspecialchars(date('M j, Y', strtotime($t['created_at']))) . '</span>';
        $html .= '<span class="task-list-date">' . htmlspecialchars(date('M j, Y', strtotime($t['updated_at']))) . '</span>';
        $html .= '<span class="task-list-date' . ($isLate ? ' task-list-date-late' : '') . '">'
            . ($t['due_date'] ? htmlspecialchars(date('M j, Y', strtotime($t['due_date']))) : '—') . '</span>';
        $html .= '</div>';
    }
    $html .= '</div></div>';
    return $html;
}

/** Server-side render of a kanban task card — mirrors taskCardHTML() in project_detail.php's inline JS. */
function render_task_card(array $t, array $taskStatuses, bool $canManage, array $currentUser, array $labels = [], array $subtasks = [], int $commentCount = 0, int $attachmentCount = 0, array $assignees = []): string {
    $isAssignee = in_array((int)$currentUser['id'], array_column($assignees, 'id'), true);
    $canUpdate = $canManage || $isAssignee;

    $assigneeHtml = render_avatar_stack($assignees);

    $statusOptions = '';
    foreach ($taskStatuses as $sk => $sl) {
        $selected = $sk === $t['status'] ? ' selected' : '';
        $statusOptions .= '<option value="' . $sk . '"' . $selected . '>' . htmlspecialchars($sl) . '</option>';
    }

    $doneCount = count(array_filter($subtasks, fn($s) => $s['is_done']));

    $html = '<div class="task-card priority-' . htmlspecialchars($t['priority']) . '" data-task-id="' . $t['id'] . '" data-current-status="' . htmlspecialchars($t['status']) . '">';
    if ($canManage) {
        $html .= '<button type="button" class="task-kebab" title="More actions">&#8942;</button>';
    }
    $html .= '<span class="task-title">' . htmlspecialchars($t['title']) . '</span>';
    if ($labels) {
        $html .= '<div class="task-label-row">';
        foreach ($labels as $label) {
            $html .= render_label_chip($label);
        }
        $html .= '</div>';
    }
    $html .= '<span class="tag tag-' . htmlspecialchars($t['priority']) . ' task-priority-chip">' . htmlspecialchars(ucfirst($t['priority'])) . '</span>';
    $html .= '<div class="task-meta"><span>' . $assigneeHtml . '</span>';
    $html .= render_due_badge($t['due_date'], $t['status']) . '</div>';
    if ($subtasks || $commentCount || $attachmentCount) {
        $html .= '<div class="task-meta-badges">';
        if ($subtasks) {
            $html .= '<span class="task-meta-badge">' . $doneCount . '/' . count($subtasks) . ' subtasks</span>';
        }
        if ($commentCount) {
            $html .= '<span class="task-meta-badge"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> ' . $commentCount . '</span>';
        }
        if ($attachmentCount) {
            $html .= '<span class="task-meta-badge"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg> ' . $attachmentCount . '</span>';
        }
        $html .= '</div>';
    }
    if ($canUpdate) {
        $html .= '<select class="task-status-select">' . $statusOptions . '</select>';
    }
    $html .= '</div>';

    return $html;
}

/** Server-side render of the Defects tab table (reuses the Tasks list's .task-list grid system
 *  with its own column layout). Rows are clickable (opens the defect drawer in JS) — no kebab. */
function render_defect_list(array $defects): string {
    if (empty($defects)) {
        return '<div class="empty-state" id="defectsEmpty">No defects reported yet.</div>';
    }
    $html = '<div class="task-list defect-list" id="defectList">';
    $html .= '<div class="task-list-head defect-list-head">'
        . '<span>ID</span><span>Defect</span><span>Assignee</span><span>Status</span><span>Severity</span>'
        . '<span>Created</span><span>Updated</span></div>';
    foreach ($defects as $d) {
        $html .= render_defect_row($d);
    }
    $html .= '</div>';
    return $html;
}

/** One row of the Defects tab table. */
function render_defect_row(array $d): string {
    $severities = \App\Repositories\DefectRepository::SEVERITIES;
    $statuses = \App\Repositories\DefectRepository::STATUSES;
    $assignee = !empty($d['assigned_to']) ? [[
        'id' => (int)$d['assigned_to'], 'name' => $d['assignee_name'], 'role' => $d['assignee_role'],
        'has_photo' => !empty($d['assignee_photo_filename']),
    ]] : [];
    $html = '<div class="task-list-row defect-list-row" data-defect-id="' . $d['id'] . '"'
        . ' data-status="' . htmlspecialchars($d['status']) . '" data-severity="' . htmlspecialchars($d['severity']) . '"'
        . ' data-created="' . htmlspecialchars(substr($d['created_at'], 0, 10)) . '">';
    $html .= '<span class="defect-list-code">' . htmlspecialchars($d['code']) . '</span>';
    $html .= '<span class="defect-list-title">' . htmlspecialchars($d['title']) . '</span>';
    $html .= '<span>' . render_avatar_stack_with_names($assignee) . '</span>';
    $html .= '<span><span class="grid-status-badge grid-status-' . htmlspecialchars($d['status']) . '">'
        . htmlspecialchars($statuses[$d['status']] ?? $d['status']) . '</span></span>';
    $html .= '<span><span class="grid-status-badge grid-severity-' . htmlspecialchars($d['severity']) . '">'
        . htmlspecialchars($severities[$d['severity']] ?? $d['severity']) . '</span></span>';
    $html .= '<span class="task-list-date">' . htmlspecialchars(date('M j, Y', strtotime($d['created_at']))) . '</span>';
    $html .= '<span class="task-list-date">' . htmlspecialchars(date('M j, Y', strtotime($d['updated_at']))) . '</span>';
    $html .= '</div>';
    return $html;
}

/** Server-side render of a team member card. $taskStats: ['assigned' => int, 'completed' => int]
 *  for this member on this project, or null if no tasks. data-role/data-department carry the
 *  raw filter values the Members tab's search/role/department dropdowns match against. */
/** $showKebab: whether this viewer has any access to the card's menu at all — full workspace
 *  members always do (Comment is available to anyone on the project, Edit Role/Remove are
 *  further restricted client-side to managers/admins); the read-only same-department view
 *  passes false since those viewers aren't actually on the project. */
function render_member_card(array $m, bool $showKebab, ?array $taskStats = null): string {
    $html = '<div class="member-card" data-user-id="' . $m['id'] . '" data-user-name="' . htmlspecialchars($m['name'])
        . '" data-role="' . htmlspecialchars($m['role_in_project']) . '" data-department="' . htmlspecialchars($m['department'] ?? '') . '">';
    if ($showKebab) {
        $html .= '<button type="button" class="task-kebab" title="More actions">&#8942;</button>';
    }
    $html .= '<div class="member-card-head">';
    $html .= render_avatar(['id' => $m['id'], 'name' => $m['name'], 'role' => $m['system_role'], 'photo_filename' => $m['photo_filename'] ?? null]);
    $html .= '<div class="row-name"><strong>' . htmlspecialchars($m['name']) . '</strong>';
    if (!empty($m['employee_code'])) {
        $html .= '<div class="member-card-id">' . htmlspecialchars($m['employee_code']) . '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<span class="member-card-role">' . htmlspecialchars($m['role_in_project']) . '</span>';
    if (!empty($m['permission_level']) && $m['permission_level'] !== 'member') {
        $html .= ' <span class="member-card-permission">' . htmlspecialchars(ucfirst($m['permission_level'])) . '</span>';
    }
    $html .= '<div class="member-card-dept"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> '
        . htmlspecialchars($m['department'] ?? '—') . '</div>';

    $assigned = $taskStats['assigned'] ?? 0;
    $completed = $taskStats['completed'] ?? 0;
    $html .= '<div class="member-card-stats">';
    $html .= '<div class="member-card-stat member-card-stat-assigned"><span class="member-card-stat-lbl">Active Tasks</span><span class="member-card-stat-num">' . $assigned . '</span></div>';
    $html .= '<div class="member-card-stat member-card-stat-completed"><span class="member-card-stat-lbl">Completed Tasks</span><span class="member-card-stat-num">' . $completed . '</span></div>';
    $html .= '</div>';

    $html .= '</div>';
    return $html;
}

/** Buckets a project-update's notification "type" into the filter dropdown's categories. */
function project_update_category(string $type): string {
    return match ($type) {
        'comment_added', 'mentioned' => 'comments',
        'task_assigned', 'due_date_changed', 'task_completed', 'attachment_uploaded' => 'tasks',
        default => 'status',
    };
}

/** One row of the project Overview/Updates activity feed: actor avatar, bolded actor name,
 *  the rest of the message with any quoted task/project title bolded (plain text, not a link),
 *  and — for messages with a trailing ": preview text" (comments) — that preview shown as a
 *  separate quoted line below. Messages are pre-built in the recipient's voice ("assigned you
 *  to…"), so "you" is swapped for the actual recipient's name since this feed is shown to
 *  everyone, not just them. $withMeta wraps it with data-* attrs for JS filtering. */
function render_project_update_row(array $u, bool $withMeta = false): string {
    $from = $u['actor_name'] ?? 'System';
    $message = $u['message'];
    if (!empty($u['recipient_name'])) {
        $message = preg_replace('/\byou\b/', $u['recipient_name'], $message, 1);
    }

    $preview = null;
    if (preg_match('/^(.*"[^"]+")\s*:\s(.+)$/s', $message, $cm)) {
        $message = $cm[1];
        $preview = $cm[2];
    }

    $rest = $message;
    if (stripos($rest, $from) === 0) {
        $rest = ltrim(substr($rest, strlen($from)));
    }

    // Only the actor's name is bold — the rest of the sentence, including the quoted
    // task/project title, stays plain text.
    $restHtml = htmlspecialchars($rest);

    $attrs = $withMeta
        ? ' data-category="' . htmlspecialchars(project_update_category($u['type'])) . '" data-created="' . htmlspecialchars($u['created_at']) . '"'
        : '';

    $html = '<div class="ov-update-row"' . $attrs . '>';
    $html .= render_avatar(['id' => $u['actor_id'] ?? null, 'name' => $from, 'role' => $u['actor_role'] ?? null, 'photo_filename' => $u['actor_photo_filename'] ?? null], 'avatar-sm');
    $html .= '<div class="ov-update-body">';
    $html .= '<div class="ov-update-text"><strong>' . htmlspecialchars($from) . '</strong> ' . $restHtml . '</div>';
    if ($preview !== null) {
        $html .= '<div class="ov-update-preview">&quot;' . htmlspecialchars($preview) . '&quot;</div>';
    }
    $html .= '<div class="ov-update-meta">' . htmlspecialchars(time_ago($u['created_at'])) . ' &middot; by ' . htmlspecialchars($from) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

/** Flat (ungrouped) feed — used for the Overview tab's 3-item "Latest updates" preview. */
function render_project_updates(array $updates): string {
    if (empty($updates)) {
        return '<div class="empty-state">No activity yet.</div>';
    }
    $html = '';
    foreach ($updates as $u) {
        $html .= render_project_update_row($u);
    }
    return $html;
}

/** Blue "Open" / green "Solved" badge — used on both the list page and the thread header. */
function render_forum_status_badge(string $status): string {
    if ($status === 'solved') {
        return '<span class="forum-status-badge solved">&check; Solved</span>';
    }
    return '<span class="forum-status-badge open">Open</span>';
}

/** Server-side render of a forum question row — mirrors forumRowHTML() in forum.js's AJAX re-render. */
function render_forum_row(array $q): string {
    $isSolved = $q['status'] === 'solved';
    $isAnswered = !$isSolved && (int)$q['answer_count'] > 0;
    $statClass = $isSolved ? ' forum-stat-solved' : ($isAnswered ? ' forum-stat-answered' : '');
    $html = '<div class="forum-row">';
    $html .= '<div class="forum-stats">';
    $html .= '<div class="forum-stat' . $statClass . '">'
        . '<span class="forum-stat-num">' . (int)$q['answer_count'] . '</span><span class="forum-stat-lbl">answers</span></div>';
    $html .= '</div>';
    $html .= '<div class="forum-row-content">';
    $html .= '<div class="forum-row-title-line">';
    $html .= '<a class="forum-row-title" href="forum_question.php?id=' . $q['id'] . '">' . htmlspecialchars($q['title']) . '</a>';
    $html .= '</div>';
    $html .= '<div class="forum-row-byline">asked by ' . render_avatar(['id' => $q['user_id'], 'name' => $q['author_name'], 'role' => $q['author_role'], 'photo_filename' => $q['author_photo_filename'] ?? null], 'avatar-sm') . htmlspecialchars($q['author_name'])
        . (!empty($q['author_department']) ? ' &middot; ' . htmlspecialchars($q['author_department']) : '')
        . ' &middot; ' . htmlspecialchars(date('M j, Y', strtotime($q['created_at']))) . '</div>';
    if (!empty($q['tags'])) {
        $html .= '<div class="forum-tag-list">';
        foreach ($q['tags'] as $tag) {
            $html .= '<span class="tag">' . htmlspecialchars($tag['name']) . '</span>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

/** Server-side render of one answer on a question thread page. */
function render_forum_answer(array $a, int $questionId, bool $isQuestionAuthor, array $currentUser, bool $isHelpful = false, array $attachments = [], array $comments = []): string {
    $isAccepted = (int)$a['id'] === (int)$a['accepted_answer_id'];
    $isAdmin = $currentUser['role'] === 'admin';
    $canDelete = $isAdmin || (int)$a['user_id'] === (int)$currentUser['id'];
    $canAttach = $isAdmin || (int)$a['user_id'] === (int)$currentUser['id'];

    $html = '<div class="forum-answer-row' . ($isAccepted ? ' forum-answer-accepted' : '') . '" data-answer-id="' . $a['id'] . '">';
    $html .= '<div class="forum-answer-card' . ($isAccepted ? ' forum-answer-card-accepted' : '') . '">';
    $html .= '<div class="forum-answer-head">';
    $html .= render_avatar(['id' => $a['user_id'], 'name' => $a['author_name'], 'role' => $a['author_role'], 'photo_filename' => $a['author_photo_filename'] ?? null], 'avatar-sm');
    $html .= '<div class="forum-answer-who">';
    $html .= '<div class="forum-answer-name-line"><strong>' . htmlspecialchars($a['author_name']) . '</strong>'
        . (!empty($a['author_department']) ? ' <span class="tag">' . htmlspecialchars($a['author_department']) . '</span>' : '') . '</div>';
    $html .= '<div class="forum-answer-time">' . htmlspecialchars(date('M j, Y \\a\\t g:i A', strtotime($a['created_at']))) . '</div>';
    $html .= '</div>';
    if ($canDelete) {
        $html .= '<button type="button" class="forum-delete-link" data-type="answer" data-id="' . $a['id'] . '">'
            . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>'
            . ' Delete</button>';
    }
    $html .= '</div>';
    $html .= '<p class="forum-body-text">' . nl2br(htmlspecialchars($a['body'])) . '</p>';
    $html .= '<div class="forum-answer-actions">';
    $html .= '<button type="button" class="forum-helpful-btn' . ($isHelpful ? ' active' : '') . '" data-answer-id="' . $a['id'] . '">';
    $html .= '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>';
    $html .= ' <span class="forum-helpful-label">Helpful</span> <span class="forum-helpful-count">(' . (int)$a['helpful_count'] . ')</span>';
    $html .= '</button>';
    if ($isQuestionAuthor) {
        $html .= '<button type="button" class="forum-accept-toggle' . ($isAccepted ? ' active' : '') . '"'
            . ' data-question-id="' . $questionId . '" data-answer-id="' . $a['id'] . '" data-accepted="' . ($isAccepted ? '1' : '0') . '">'
            . '&check; ' . ($isAccepted ? 'Accepted' : 'Accept Answer') . '</button>';
    } elseif ($isAccepted) {
        $html .= '<span class="forum-accepted-badge">&check; Accepted Answer</span>';
    }
    $html .= '<button type="button" class="forum-reply-link" data-answer-id="' . $a['id'] . '">'
        . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>'
        . ' Reply</button>';
    $html .= '</div>';
    $html .= render_forum_attachments($attachments, (int)$a['id'], $canAttach, $currentUser);
    $html .= render_forum_replies($comments, (int)$a['id'], $currentUser);
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

/** Reply thread under one answer, plus a hidden "Reply" composer revealed by the
 *  .forum-reply-link button rendered alongside it in render_forum_answer(). */
function render_forum_replies(array $comments, int $answerId, array $currentUser): string {
    $html = '<div class="forum-replies" data-answer-id="' . $answerId . '">';
    $html .= '<div class="forum-reply-list">';
    foreach ($comments as $c) {
        $canDelete = $currentUser['role'] === 'admin' || (int)$c['user_id'] === (int)$currentUser['id'];
        $html .= '<div class="forum-reply" data-comment-id="' . $c['id'] . '">';
        $html .= '<span class="forum-reply-avatar">' . htmlspecialchars(mb_strtoupper(mb_substr($c['author_name'], 0, 1))) . '</span>';
        $html .= '<div class="forum-reply-content">';
        $html .= '<div class="forum-reply-bubble">';
        $html .= '<span class="forum-reply-author">' . htmlspecialchars($c['author_name']) . '</span>';
        $html .= '<span class="forum-reply-body">' . htmlspecialchars($c['body']) . '</span>';
        $html .= '</div>';
        $html .= '<span class="forum-reply-time">' . htmlspecialchars(date('M j, Y \\a\\t g:i A', strtotime($c['created_at']))) . '</span>';
        $html .= '</div>';
        if ($canDelete) {
            $html .= '<button type="button" class="forum-reply-delete" title="Delete reply">&times;</button>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<div class="forum-reply-form" style="display:none;">';
    $html .= '<input type="text" class="forum-reply-input" maxlength="500" placeholder="Write a reply…">';
    $html .= '<button type="button" class="link-btn forum-reply-submit">Reply</button>';
    $html .= '<button type="button" class="forum-reply-cancel">Cancel</button>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

/** Attachment list + (author/admin-only) upload control for one answer — reuses the same
 *  .attachment-row markup/CSS built for task attachments and project documents. */
function render_forum_attachments(array $attachments, int $answerId, bool $canUpload, array $currentUser): string {
    $html = '<div class="forum-attachments" data-answer-id="' . $answerId . '">';
    foreach ($attachments as $att) {
        $canRemove = $currentUser['role'] === 'admin' || (int)$att['user_id'] === (int)$currentUser['id'];
        $downloadUrl = 'api/forum/attachments.php?action=download&id=' . $att['id'];
        $sizeKb = round($att['size_bytes'] / 1024);
        $sizeLabel = $sizeKb < 1024 ? $sizeKb . ' KB' : round($att['size_bytes'] / 1024 / 1024, 1) . ' MB';
        $html .= '<div class="attachment-row" data-attachment-id="' . $att['id'] . '">';
        $html .= '<svg class="attachment-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        $html .= '<div class="attachment-info">';
        $html .= '<a href="' . $downloadUrl . '" target="_blank" class="attachment-name">' . htmlspecialchars($att['original_filename']) . '</a>';
        $html .= '<div class="attachment-meta">' . htmlspecialchars($att['uploader_name']) . ' &middot; ' . $sizeLabel . '</div>';
        $html .= '</div>';
        if ($canRemove) {
            $html .= '<button type="button" class="attachment-remove forum-attachment-remove" title="Remove">&times;</button>';
        }
        $html .= '</div>';
    }
    if ($canUpload) {
        $html .= '<label class="forum-attach-link">+ Attach File<input type="file" class="forum-attach-input" style="display:none;"></label>';
    }
    $html .= '</div>';
    return $html;
}

