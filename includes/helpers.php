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

/** The Create Project wizard's persistent horizontal stepper — called at the top of all six wizard pages. */
function render_wizard_stepper(int $currentStep): string {
    $steps = [1 => 'Details', 2 => 'Team', 3 => 'Tasks', 4 => 'Review', 5 => 'Finish'];
    $total = count($steps);
    $html = '<div class="wizard-steps">';
    $i = 0;
    foreach ($steps as $num => $label) {
        $i++;
        $state = $num < $currentStep ? 'done' : ($num === $currentStep ? 'active' : '');
        $numDisplay = $num < $currentStep ? '&check;' : (string)$num;
        $html .= '<span class="wizard-step ' . $state . '"><span class="wizard-step-num">' . $numDisplay . '</span><span class="wizard-step-label">' . htmlspecialchars($label) . '</span></span>';
        if ($i < $total) {
            $html .= '<span class="wizard-step-connector ' . ($num < $currentStep ? 'done' : '') . '"></span>';
        }
    }
    $html .= '</div>';
    return $html;
}

/** Server-side render of an employee row — mirrors employeeRowHTML() in employees.php's inline JS. */
function render_employee_row(array $e, array $currentUser): string {
    $html = '<tr data-id="' . $e['id'] . '" data-name="' . htmlspecialchars($e['name']) . '"'
        . ' data-role="' . htmlspecialchars($e['role']) . '"'
        . ' data-department="' . htmlspecialchars($e['department'] ?? '') . '"'
        . ' data-telephone="' . htmlspecialchars($e['telephone'] ?? '') . '"'
        . ' data-manager-id="' . htmlspecialchars($e['manager_id'] ?? '') . '"'
        . ' data-manager-name="' . htmlspecialchars($e['manager_name'] ?? '') . '">';
    $html .= '<td><div class="row-name">' . render_avatar($e);
    $html .= '<a href="employee_detail.php?id=' . $e['id'] . '">' . htmlspecialchars($e['name']) . '</a></div></td>';
    $html .= '<td><a class="code-link" href="employee_detail.php?id=' . $e['id'] . '">' . htmlspecialchars($e['employee_code']) . '</a></td>';
    $html .= '<td>' . htmlspecialchars($e['email']) . '</td>';
    $html .= '<td><span class="dir-badge dir-badge-' . htmlspecialchars($e['role']) . '">' . htmlspecialchars(ucfirst($e['role'])) . '</span></td>';
    $html .= '<td class="dept-cell">' . htmlspecialchars($e['department'] ?? '—') . '</td>';
    $html .= '<td class="manager-cell">' . htmlspecialchars($e['manager_name'] ?? '—') . '</td>';

    if ($currentUser['role'] === 'admin') {
        $html .= '<td class="actions"><button type="button" class="row-kebab emp-row-kebab" title="More actions">&#8942;</button></td>';
    }

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
    $html .= '<div class="attachment-meta">' . htmlspecialchars($d['uploader_name']) . ' &middot; ' . htmlspecialchars(date('d M Y', strtotime($d['created_at']))) . ' &middot; ' . $sizeLabel . '</div>';
    $html .= '</div>';
    if ($canRemove) {
        $html .= '<button type="button" class="attachment-remove document-remove" title="Remove">&times;</button>';
    }
    $html .= '</div>';
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
    $html .= '<td>' . (int)$p['member_count'] . '</td>';
    $html .= '<td><span class="dir-badge dir-badge-' . htmlspecialchars($p['status']) . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $p['status']))) . '</span></td>';
    $html .= '</tr>';
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

/** Server-side render of a team member card — replaces the old table row.
 *  $taskStats: ['assigned' => int, 'completed' => int] for this member on this project, or null if no tasks. */
function render_member_card(array $m, bool $canManage, ?array $taskStats = null): string {
    $html = '<div class="member-card" data-user-id="' . $m['id'] . '" data-user-name="' . htmlspecialchars($m['name']) . '">';
    if ($canManage) {
        $html .= '<button type="button" class="task-kebab" title="More actions">&#8942;</button>';
    }
    $html .= '<div class="member-card-head">';
    $html .= render_avatar(['id' => $m['id'], 'name' => $m['name'], 'role' => $m['system_role'], 'photo_filename' => $m['photo_filename'] ?? null]);
    $html .= '<div class="row-name"><strong>' . htmlspecialchars($m['name']) . '</strong>';
    $html .= ' <span class="tag tag-' . htmlspecialchars($m['system_role']) . '">' . htmlspecialchars(ucfirst($m['system_role'])) . '</span></div>';
    $html .= '</div>';
    $html .= '<div class="member-card-sub">' . htmlspecialchars($m['email']) . '</div>';
    $html .= '<div class="member-card-sub">' . htmlspecialchars($m['department'] ?? '—') . '</div>';
    $html .= '<span class="member-card-role">' . htmlspecialchars($m['role_in_project']) . '</span>';
    if (!empty($m['permission_level']) && $m['permission_level'] !== 'member') {
        $html .= ' <span class="member-card-permission">' . htmlspecialchars(ucfirst($m['permission_level'])) . '</span>';
    }

    $assigned = $taskStats['assigned'] ?? 0;
    $completed = $taskStats['completed'] ?? 0;
    $html .= '<div class="member-card-stats">';
    $html .= '<div class="member-card-stat"><span class="member-card-stat-num">' . $assigned . '</span><span class="member-card-stat-lbl">Assigned</span></div>';
    $html .= '<div class="member-card-stat"><span class="member-card-stat-num">' . $completed . '</span><span class="member-card-stat-lbl">Completed</span></div>';
    $html .= '</div>';
    if ($assigned > 0) {
        $pct = (int)round($completed / $assigned * 100);
        $html .= '<div class="workload-bar-track"><span class="workload-bar-fill" style="width:' . $pct . '%"></span></div>';
    }

    $html .= '</div>';
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
    $html = '<div class="forum-row">';
    $html .= '<div class="forum-stats">';
    $html .= '<div class="forum-stat' . ((int)$q['answer_count'] > 0 ? ' forum-stat-answered' : '') . '"><span class="forum-stat-num">' . (int)$q['answer_count'] . '</span><span class="forum-stat-lbl">answers</span></div>';
    $html .= '</div>';
    $html .= '<div class="forum-row-content">';
    $html .= '<div class="forum-row-title-line">';
    $html .= '<a class="forum-row-title" href="forum_question.php?id=' . $q['id'] . '">' . htmlspecialchars($q['title']) . '</a>';
    $html .= render_forum_status_badge($q['status']);
    $html .= '</div>';
    if (!empty($q['tags'])) {
        $html .= '<div class="forum-tag-list">';
        foreach ($q['tags'] as $tag) {
            $html .= '<span class="tag">' . htmlspecialchars($tag['name']) . '</span>';
        }
        $html .= '</div>';
    }
    $html .= '<div class="forum-row-byline">asked by ' . render_avatar(['id' => $q['user_id'], 'name' => $q['author_name'], 'role' => $q['author_role'], 'photo_filename' => $q['author_photo_filename'] ?? null], 'avatar-sm') . htmlspecialchars($q['author_name'])
        . (!empty($q['author_department']) ? ' &middot; ' . htmlspecialchars($q['author_department']) : '')
        . ' &middot; ' . htmlspecialchars(date('d M Y', strtotime($q['created_at']))) . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

/** Server-side render of one answer on a question thread page. */
function render_forum_answer(array $a, int $questionId, bool $isQuestionAuthor, array $currentUser, array $comments = [], bool $isHelpful = false, array $attachments = []): string {
    $isAccepted = (int)$a['id'] === (int)$a['accepted_answer_id'];
    $isAdmin = $currentUser['role'] === 'admin';
    $canDelete = $isAdmin || (int)$a['user_id'] === (int)$currentUser['id'];
    $canAttach = $isAdmin || (int)$a['user_id'] === (int)$currentUser['id'];

    $html = '<div class="forum-answer-row' . ($isAccepted ? ' forum-answer-accepted' : '') . '" data-answer-id="' . $a['id'] . '">';
    if ($isAccepted) {
        $html .= '<div class="forum-accepted-banner">&check; Accepted Solution</div>';
    }
    $html .= '<div class="forum-thread-body">';
    $html .= '<div class="forum-thread-content">';
    $html .= '<p class="forum-body-text">' . nl2br(htmlspecialchars($a['body'])) . '</p>';
    $html .= '<button type="button" class="forum-helpful-btn' . ($isHelpful ? ' active' : '') . '" data-answer-id="' . $a['id'] . '">';
    $html .= '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>';
    $html .= ' <span class="forum-helpful-label">Helpful</span> <span class="forum-helpful-count">(' . (int)$a['helpful_count'] . ')</span>';
    $html .= '</button>';
    $html .= '<div class="forum-row-byline">answered by ' . render_avatar(['id' => $a['user_id'], 'name' => $a['author_name'], 'role' => $a['author_role'], 'photo_filename' => $a['author_photo_filename'] ?? null], 'avatar-sm') . htmlspecialchars($a['author_name'])
        . (!empty($a['author_department']) ? ' &middot; ' . htmlspecialchars($a['author_department']) : '')
        . ' &middot; ' . htmlspecialchars(date('d M Y', strtotime($a['created_at']))) . '</div>';
    $html .= '<div class="forum-answer-actions">';
    if ($isQuestionAuthor && !$isAccepted) {
        $html .= '<button type="button" class="btn forum-accept-btn" data-question-id="' . $questionId . '" data-answer-id="' . $a['id'] . '">Accept Answer</button>';
    }
    if ($canDelete) {
        $html .= '<button type="button" class="forum-delete-link" data-type="answer" data-id="' . $a['id'] . '">Delete</button>';
    }
    $html .= '</div>';
    $html .= render_forum_attachments($attachments, (int)$a['id'], $canAttach, $currentUser);
    $html .= render_forum_comments($comments, 'answer', (int)$a['id'], $currentUser);
    $html .= '</div>';
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

/** Compact SO-style comment thread under a question or answer, plus an "Add a comment" reveal form. */
function render_forum_comments(array $comments, string $type, int $id, array $currentUser): string {
    $html = '<div class="forum-comments" data-type="' . $type . '" data-id="' . $id . '">';
    $html .= '<div class="forum-comment-list">';
    foreach ($comments as $c) {
        $canDelete = $currentUser['role'] === 'admin' || (int)$c['user_id'] === (int)$currentUser['id'];
        $html .= '<div class="forum-comment" data-comment-id="' . $c['id'] . '">';
        $html .= '<span class="forum-comment-body">' . htmlspecialchars($c['body']) . '</span>';
        $html .= ' &mdash; <span class="forum-comment-author">' . htmlspecialchars($c['author_name']) . '</span>';
        if ($canDelete) {
            $html .= ' <button type="button" class="forum-comment-delete" title="Delete comment">&times;</button>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '<button type="button" class="forum-add-comment-link">Add a comment</button>';
    $html .= '<div class="forum-comment-form" style="display:none;">';
    $html .= '<input type="text" class="forum-comment-input" maxlength="500" placeholder="Add a comment…">';
    $html .= '<button type="button" class="btn forum-comment-submit">Add</button>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}
