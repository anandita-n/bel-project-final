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
<script src="assets/js/modal.js"></script>

<div class="breadcrumb"><a href="projects.php">Projects</a> / <?= htmlspecialchars($project['project_code']) ?></div>

<div id="pageError" class="error-msg" style="display:none;"></div>

<div class="panel">
    <div class="panel-head"><h3><?= htmlspecialchars($project['name']) ?></h3>
        <span class="tag tag-<?= htmlspecialchars($project['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ', $project['status']))) ?></span>
    </div>
    <div class="panel-body">
        <div class="form-grid">
            <div><strong>Project Code:</strong> <?= htmlspecialchars($project['project_code']) ?></div>
            <div><strong>Manager:</strong> <?= htmlspecialchars($project['manager_name']) ?> (<?= htmlspecialchars($project['manager_email']) ?>)</div>
            <div><strong>Start Date:</strong> <?= htmlspecialchars($project['start_date'] ?? '—') ?></div>
            <div><strong>Created:</strong> <?= htmlspecialchars($project['created_at']) ?></div>
        </div>
        <?php if ($project['description']): ?>
        <p style="margin-top:14px;color:var(--text-muted);"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3>Resources / Team Members (<span id="memberCount"><?= count($members) ?></span>)</h3>
        <?php if ($can_manage): ?>
        <button type="button" id="openAddMemberModal" class="icon-btn" title="Add team member">+</button>
        <?php endif; ?>
    </div>
    <div class="panel-body" style="padding:0;">
        <table id="membersTable" style="<?= empty($members) ? 'display:none;' : '' ?>">
            <thead>
                <tr><th>Name</th><th>Email</th><th>System Role</th><th>Role in Project</th><th>Department</th><?php if ($can_manage): ?><th>Actions</th><?php endif; ?></tr>
            </thead>
            <tbody id="membersTbody">
                <?php foreach ($members as $m): ?>
                <tr data-user-id="<?= $m['id'] ?>">
                    <td>
                        <div class="row-name">
                            <span class="avatar <?= avatar_class($m['system_role']) ?>"><?= htmlspecialchars(initials($m['name'])) ?></span>
                            <a href="employee_detail.php?id=<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></a>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($m['email']) ?></td>
                    <td><span class="tag tag-<?= htmlspecialchars($m['system_role']) ?>"><?= htmlspecialchars(ucfirst($m['system_role'])) ?></span></td>
                    <td><?= htmlspecialchars($m['role_in_project']) ?></td>
                    <td><?= htmlspecialchars($m['department'] ?? '—') ?></td>
                    <?php if ($can_manage): ?>
                    <td class="actions">
                        <button type="button" class="btn btn-danger btn-sm remove-member-btn">Remove</button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div id="membersEmpty" class="empty-state" style="<?= empty($members) ? '' : 'display:none;' ?>">No team members assigned yet.</div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3>Tasks (<span id="taskCount"><?= count($all_tasks) ?></span>)</h3>
        <?php if ($can_manage): ?>
        <button type="button" id="openAddTaskModal" class="icon-btn" title="Add task">+</button>
        <?php endif; ?>
    </div>
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

<script>
window.PAGE_CONFIG = {
    projectId: <?= (int)$project_id ?>,
    canManage: <?= $can_manage ? 'true' : 'false' ?>,
    currentUserId: <?= (int)$u['id'] ?>,
    statusLabels: <?= json_encode($task_statuses) ?>,
};
</script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/pages/project-detail.js"></script>

<?php require 'includes/layout_bottom.php'; ?>
