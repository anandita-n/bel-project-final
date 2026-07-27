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

function like_escape(string $term): string {
    return addcslashes($term, '%_\\');
}

/** Server-side render of an employee row — mirrors employeeRowHTML() in employees.php's inline JS. */
function render_employee_row(array $e, array $currentUser): string {
    $html = '<tr data-id="' . $e['id'] . '" data-name="' . htmlspecialchars($e['name']) . '"'
        . ' data-role="' . htmlspecialchars($e['role']) . '"'
        . ' data-department="' . htmlspecialchars($e['department'] ?? '') . '"'
        . ' data-telephone="' . htmlspecialchars($e['telephone'] ?? '') . '"'
        . ' data-manager-id="' . htmlspecialchars($e['manager_id'] ?? '') . '"'
        . ' data-manager-name="' . htmlspecialchars($e['manager_name'] ?? '') . '">';
    $html .= '<td><div class="row-name"><span class="avatar ' . avatar_class($e['role']) . '">' . htmlspecialchars(initials($e['name'])) . '</span>';
    $html .= '<a href="employee_detail.php?id=' . $e['id'] . '">' . htmlspecialchars($e['name']) . '</a></div></td>';
    $html .= '<td>' . htmlspecialchars($e['employee_code']) . '</td>';
    $html .= '<td>' . htmlspecialchars($e['email']) . '</td>';
    $html .= '<td><span class="tag tag-' . htmlspecialchars($e['role']) . '">' . htmlspecialchars(ucfirst($e['role'])) . '</span></td>';
    $html .= '<td class="dept-cell">' . htmlspecialchars($e['department'] ?? '—') . '</td>';
    $html .= '<td class="manager-cell">' . htmlspecialchars($e['manager_name'] ?? '—') . '</td>';

    if ($currentUser['role'] === 'admin') {
        $html .= '<td class="actions"><button type="button" class="row-kebab emp-row-kebab" title="More actions">&#8942;</button></td>';
    }

    $html .= '</tr>';
    return $html;
}

/** Server-side render of a project row — mirrors projectRowHTML() in projects.php's inline JS. */
function render_project_row(array $p): string {
    $html = '<tr>';
    $html .= '<td><a href="project_detail.php?id=' . $p['id'] . '">' . htmlspecialchars($p['project_code']) . '</a></td>';
    $html .= '<td><a href="project_detail.php?id=' . $p['id'] . '">' . htmlspecialchars($p['name']) . '</a></td>';
    $html .= '<td><div class="row-name"><span class="avatar avatar-sm avatar-manager">' . htmlspecialchars(initials($p['manager_name'])) . '</span>' . htmlspecialchars($p['manager_name']) . '</div></td>';
    $html .= '<td>' . (int)$p['member_count'] . '</td>';
    $html .= '<td><span class="tag tag-' . htmlspecialchars($p['status']) . '">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $p['status']))) . '</span></td>';
    $html .= '</tr>';
    return $html;
}

/** Server-side render of a kanban task card — mirrors taskCardHTML() in project_detail.php's inline JS. */
function render_task_card(array $t, array $taskStatuses, bool $canManage, array $currentUser): string {
    $isOverdue = $t['due_date'] && $t['due_date'] < date('Y-m-d') && $t['status'] !== 'done';
    $canUpdate = $canManage || (int)$t['assigned_to'] === (int)$currentUser['id'];

    $assigneeHtml = $t['assignee_name']
        ? '<span class="avatar avatar-sm ' . avatar_class($t['assignee_role']) . '" style="vertical-align:middle;">' . htmlspecialchars(initials($t['assignee_name'])) . '</span> ' . htmlspecialchars($t['assignee_name'])
        : 'Unassigned';

    $statusOptions = '';
    foreach ($taskStatuses as $sk => $sl) {
        $selected = $sk === $t['status'] ? ' selected' : '';
        $statusOptions .= '<option value="' . $sk . '"' . $selected . '>' . htmlspecialchars($sl) . '</option>';
    }

    $dueLabel = $t['due_date'] ? date('d M', strtotime($t['due_date'])) : '';
    $dateLabel = (!empty($t['start_date']) && $t['start_date'])
        ? date('d M', strtotime($t['start_date'])) . ' → ' . $dueLabel
        : $dueLabel;

    $html = '<div class="task-card priority-' . htmlspecialchars($t['priority']) . '" data-task-id="' . $t['id'] . '" data-current-status="' . htmlspecialchars($t['status']) . '">';
    if ($canManage) {
        $html .= '<button type="button" class="task-kebab" title="More actions">&#8942;</button>';
    }
    $html .= '<span class="task-title">' . htmlspecialchars($t['title']) . '</span>';
    $html .= '<div class="task-meta"><span>' . $assigneeHtml . '</span>';
    $html .= '<span class="task-due' . ($isOverdue ? ' overdue' : '') . '">' . htmlspecialchars($dateLabel) . '</span></div>';
    if ($canUpdate) {
        $html .= '<select class="task-status-select">' . $statusOptions . '</select>';
    }
    $html .= '</div>';

    return $html;
}

/** Server-side render of a team member card — replaces the old table row. */
function render_member_card(array $m, bool $canManage): string {
    $html = '<div class="member-card" data-user-id="' . $m['id'] . '" data-user-name="' . htmlspecialchars($m['name']) . '">';
    if ($canManage) {
        $html .= '<button type="button" class="task-kebab" title="More actions">&#8942;</button>';
    }
    $html .= '<div class="member-card-head">';
    $html .= '<span class="avatar ' . avatar_class($m['system_role']) . '">' . htmlspecialchars(initials($m['name'])) . '</span>';
    $html .= '<div class="row-name"><strong>' . htmlspecialchars($m['name']) . '</strong>';
    $html .= ' <span class="tag tag-' . htmlspecialchars($m['system_role']) . '">' . htmlspecialchars(ucfirst($m['system_role'])) . '</span></div>';
    $html .= '</div>';
    $html .= '<div class="member-card-sub">' . htmlspecialchars($m['email']) . '</div>';
    $html .= '<div class="member-card-sub">' . htmlspecialchars($m['department'] ?? '—') . '</div>';
    $html .= '<span class="member-card-role">' . htmlspecialchars($m['role_in_project']) . '</span>';
    $html .= '</div>';
    return $html;
}
