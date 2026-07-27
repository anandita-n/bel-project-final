<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;

$u = current_user();
$project_id = (int)($_GET['id'] ?? 0);

$projects = new ProjectRepository();
$tasksRepo = new TaskRepository();

$project = $projects->findById($project_id);
if (!$project) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:40px;">Project not found. <a href="projects.php">Back to projects</a></div>');
}

if (!$projects->userHasAccess($project_id, $u)) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;">You do not have access to this project.</div>');
}

$can_manage = $u['role'] === 'admin' || $u['id'] === (int)$project['manager_id'];

$members = $projects->members($project_id);
$all_tasks = $tasksRepo->listForProject($project_id);
$tasks_by_status = $tasksRepo->groupByStatus($all_tasks);
$task_statuses = \App\Repositories\TaskRepository::STATUSES;

$page_title = 'Project: ' . $project['name'];
require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<script src="assets/js/drawer.js?v=<?= filemtime(__DIR__ . '/assets/js/drawer.js') ?>"></script>
<script src="assets/js/dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown.js') ?>"></script>
<script src="assets/js/tabs.js?v=<?= filemtime(__DIR__ . '/assets/js/tabs.js') ?>"></script>

<div class="breadcrumb"><a href="projects.php">Projects</a></div>

<div id="pageError" class="error-msg" style="display:none;"></div>

<div class="project-header">
    <div class="project-header-top">
        <h2><?= htmlspecialchars($project['name']) ?></h2>
        <span class="tag tag-<?= htmlspecialchars($project['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $project['status']))) ?></span>
    </div>
    <div class="project-header-top project-header-id">
        <h2><?= htmlspecialchars($project['project_code']) ?></h2>
    </div>
    <div class="project-header-meta">
        <span class="row-name"><span class="avatar avatar-sm avatar-manager"><?= htmlspecialchars(initials($project['manager_name'])) ?></span><?= htmlspecialchars($project['manager_name']) ?></span>
        <span class="sep">&middot;</span>
        <span>Created <?= htmlspecialchars(date('d M Y', strtotime($project['created_at']))) ?></span>
    </div>
    <?php if ($project['description']): ?>
    <p class="overview-description" style="margin-top:10px;margin-bottom:0;"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
    <?php endif; ?>
</div>

<div id="projectTabs">
    <div class="tabs" role="tablist">
        <button type="button" class="tab-btn" data-tab="board">Board</button>
        <button type="button" class="tab-btn" data-tab="list">List</button>
        <button type="button" class="tab-btn" data-tab="members">Members (<?= count($members) ?>)</button>
    </div>

    <div class="tab-panel" data-panel="board">
        <div class="task-toolbar">
            <div class="search-bar" style="min-width:200px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" class="task-filter-search" placeholder="Search tasks…">
            </div>
            <select class="task-filter-status"><option value="all">All statuses</option></select>
            <select class="task-filter-priority">
                <option value="all">All priorities</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <select class="task-filter-assignee"><option value="all">All assignees</option></select>
            <div class="task-toolbar-spacer"></div>
            <?php if ($can_manage): ?>
            <button type="button" id="openAddTaskModal" class="btn btn-sm">+ Add Task</button>
            <?php endif; ?>
        </div>

        <div id="boardViewBoard">
            <div id="tasksEmpty" class="empty-state" style="<?= empty($all_tasks) ? '' : 'display:none;' ?>">No tasks yet on this project.</div>
            <div class="kanban" id="kanbanBoard" style="<?= empty($all_tasks) ? 'display:none;' : '' ?>">
                <?php foreach ($task_statuses as $status_key => $status_label): $col_tasks = $tasks_by_status[$status_key]; ?>
                <div class="kanban-col" data-status="<?= $status_key ?>">
                    <div class="kanban-col-head">
                        <span><?= htmlspecialchars($status_label) ?></span>
                        <span class="col-count"><?= count($col_tasks) ?></span>
                    </div>
                    <div class="kanban-col-body" data-status-body="<?= $status_key ?>">
                        <?php foreach ($col_tasks as $t): ?>
                            <?= render_task_card($t, $task_statuses, $can_manage, $u) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="tab-panel" data-panel="list">
        <div class="task-toolbar">
            <div class="search-bar" style="min-width:200px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" class="task-filter-search" placeholder="Search tasks…">
            </div>
            <select class="task-filter-status"><option value="all">All statuses</option></select>
            <select class="task-filter-priority">
                <option value="all">All priorities</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <select class="task-filter-assignee"><option value="all">All assignees</option></select>
        </div>
        <div id="listViewContainer"></div>
    </div>

    <div class="tab-panel" data-panel="members">
        <div class="task-toolbar">
            <div class="task-toolbar-spacer"></div>
            <?php if ($can_manage): ?>
            <button type="button" id="openAddMemberModal" class="btn btn-sm">+ Add Member</button>
            <?php endif; ?>
        </div>
        <div id="membersEmpty" class="empty-state" style="<?= empty($members) ? '' : 'display:none;' ?>">No team members assigned yet.</div>
        <div class="member-card-grid" id="memberCardGrid" style="<?= empty($members) ? 'display:none;' : '' ?>">
            <?php foreach ($members as $m): ?>
                <?= render_member_card($m, $can_manage) ?>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
window.PAGE_CONFIG = {
    projectId: <?= (int)$project_id ?>,
    projectName: <?= json_encode($project['name']) ?>,
    projectCode: <?= json_encode($project['project_code']) ?>,
    canManage: <?= $can_manage ? 'true' : 'false' ?>,
    currentUserId: <?= (int)$u['id'] ?>,
    statusLabels: <?= json_encode($task_statuses) ?>,
};
window.PAGE_STATE = {
    members: <?= json_encode(array_map(fn($m) => [
        'id' => (int)$m['id'], 'name' => $m['name'], 'email' => $m['email'],
        'system_role' => $m['system_role'], 'department' => $m['department'],
        'role_in_project' => $m['role_in_project'],
    ], $members)) ?>,
    tasks: <?= json_encode(array_map(fn($t) => [
        'id' => (int)$t['id'], 'title' => $t['title'], 'description' => $t['description'],
        'status' => $t['status'], 'priority' => $t['priority'],
        'start_date' => $t['start_date'], 'due_date' => $t['due_date'],
        'assigned_to' => $t['assigned_to'] ? (int)$t['assigned_to'] : null,
        'assignee_name' => $t['assignee_name'], 'assignee_role' => $t['assignee_role'],
    ], $all_tasks)) ?>,
};
</script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/pages/project-detail.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-detail.js') ?>"></script>
<script src="assets/js/pages/project-detail-views.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-detail-views.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
