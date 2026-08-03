<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TaskCommentRepository;
use App\Repositories\TaskAttachmentRepository;
use App\Repositories\ProjectDocumentRepository;

$u = current_user();
$project_id = (int)($_GET['id'] ?? 0);

$projects = new ProjectRepository();
$tasksRepo = new TaskRepository();
$commentsRepo = new TaskCommentRepository();
$attachmentsRepo = new TaskAttachmentRepository();
$documentsRepo = new ProjectDocumentRepository();

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

// The project manager isn't a project_members row — show them in the Members tab too,
// unless they were also separately added as a member (avoid a duplicate card).
if (!in_array((int)$project['manager_id'], array_column($members, 'id'), true)) {
    array_unshift($members, [
        'id' => (int)$project['manager_id'],
        'name' => $project['manager_name'],
        'email' => $project['manager_email'],
        'system_role' => $project['manager_role'],
        'department' => $project['manager_department'],
        'role_in_project' => 'Project Manager',
        'permission_level' => 'lead',
    ]);
}

$all_tasks = $tasksRepo->listForProject($project_id);
$task_statuses = \App\Repositories\TaskRepository::STATUSES;

$task_ids = array_map(fn($t) => (int)$t['id'], $all_tasks);
$labels_by_task = $tasksRepo->labelsForTasks($task_ids);
$subtasks_by_task = $tasksRepo->subtasksForTasks($task_ids);
$assignees_by_task = $tasksRepo->assigneesForTasks($task_ids);
$comment_counts_by_task = $commentsRepo->countsForTasks($task_ids);
$attachment_counts_by_task = $attachmentsRepo->countsForTasks($task_ids);
$documents = $documentsRepo->forProject($project_id);

$member_task_stats = [];
foreach ($members as $m) {
    $mine = array_filter($all_tasks, function ($t) use ($m, $assignees_by_task) {
        $assignees = $assignees_by_task[(int)$t['id']] ?? [];
        return in_array((int)$m['id'], array_column($assignees, 'id'), true);
    });
    $member_task_stats[$m['id']] = [
        'assigned' => count($mine),
        'completed' => count(array_filter($mine, fn($t) => $t['status'] === 'done')),
    ];
}

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

<div class="project-header-v2">
    <div class="ph-top">
        <div class="ph-title-block">
            <h2><?= htmlspecialchars($project['name']) ?></h2>
            <div class="ph-sub">
                <span class="dir-badge dir-badge-<?= htmlspecialchars($project['status']) ?>" id="phStatusTag"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $project['status']))) ?></span>
                <span class="ph-code"><?= htmlspecialchars($project['project_code']) ?></span>
                <?php if ($project['due_date']): ?>
                <span class="ph-sep">&middot;</span>
                <span>Due <?= htmlspecialchars(date('d M Y', strtotime($project['due_date']))) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($can_manage): ?>
        <div class="ph-actions">
            <div class="ph-more-wrap">
                <button type="button" class="row-kebab" id="projectMoreActions" title="More actions">&#8942;</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<div class="ph-meta-row">
    <span><span class="ph-meta-label">Department</span> <?= htmlspecialchars($project['department'] ?: '—') ?></span>
    <span class="ph-sep">&middot;</span>
    <span><span class="ph-meta-label">Created</span> <?= htmlspecialchars(date('d M Y', strtotime($project['created_at']))) ?></span>
</div>
<div class="ph-manager-row">
    <span class="ph-manager-label">Project Manager</span>
    <span class="ph-manager-item"><span class="ph-meta-label">Name</span> <?= htmlspecialchars($project['manager_name']) ?></span>
    <span class="ph-manager-item"><span class="ph-meta-label">Email</span> <?= htmlspecialchars($project['manager_email']) ?></span>
    <span class="ph-manager-item"><span class="ph-meta-label">Phone</span> <?= htmlspecialchars($project['manager_telephone'] ?: '—') ?></span>
</div>

<div id="projectTabs">
    <div class="tabs" role="tablist">
        <button type="button" class="tab-btn" data-tab="list">Grid</button>
        <button type="button" class="tab-btn" data-tab="members">Members (<?= count($members) ?>)</button>
        <button type="button" class="tab-btn" data-tab="documents">Documents (<?= count($documents) ?>)</button>
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
            <div class="task-toolbar-spacer"></div>
            <?php if ($can_manage): ?>
            <button type="button" id="openAddTaskModal" class="btn btn-lg">+ Add Task</button>
            <?php endif; ?>
        </div>
        <div id="listBulkBar" class="list-bulk-bar" style="display:none;">
            <span id="listBulkCount">0 selected</span>
            <select id="listBulkStatus"><option value="">Change status…</option></select>
            <select id="listBulkPriority">
                <option value="">Change priority…</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <div id="listBulkAssigneePicker"></div>
            <button type="button" id="listBulkDelete" class="btn btn-sm btn-danger">Delete</button>
            <a href="#" id="listBulkClear" class="clear-link">Clear selection</a>
        </div>
        <div id="listViewContainer"></div>
    </div>

    <div class="tab-panel" data-panel="members">
        <?php if ($can_manage): ?>
        <div class="task-toolbar">
            <div class="task-toolbar-spacer"></div>
            <button type="button" id="openAddMemberModal" class="btn btn-lg">+ Add Member</button>
        </div>
        <?php endif; ?>
        <div id="membersEmpty" class="empty-state" style="<?= empty($members) ? '' : 'display:none;' ?>">No team members assigned yet.</div>
        <div class="member-card-grid" id="memberCardGrid" style="<?= empty($members) ? 'display:none;' : '' ?>">
            <?php foreach ($members as $m): ?>
                <?= render_member_card($m, $can_manage, $member_task_stats[$m['id']] ?? null) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tab-panel" data-panel="documents">
        <?php if ($can_manage): ?>
        <div class="task-toolbar">
            <div class="task-toolbar-spacer"></div>
            <label class="btn btn-lg" for="documentFileInput" style="cursor:pointer;">+ Upload Document</label>
            <input type="file" id="documentFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.png,.jpg,.jpeg,.zip" style="display:none;">
        </div>
        <?php endif; ?>
        <div id="documentsEmpty" class="empty-state" style="<?= empty($documents) ? '' : 'display:none;' ?>">No documents uploaded yet.</div>
        <div id="documentsList" style="<?= empty($documents) ? 'display:none;' : '' ?>">
            <?php foreach ($documents as $d): ?>
                <?= render_document_row($d, $project_id, $can_manage) ?>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
window.PAGE_CONFIG = {
    projectId: <?= (int)$project_id ?>,
    projectName: <?= json_encode($project['name']) ?>,
    projectCode: <?= json_encode($project['project_code']) ?>,
    projectStatus: <?= json_encode($project['status']) ?>,
    canManage: <?= $can_manage ? 'true' : 'false' ?>,
    isAdmin: <?= $u['role'] === 'admin' ? 'true' : 'false' ?>,
    managerId: <?= (int)$project['manager_id'] ?>,
    currentUserId: <?= (int)$u['id'] ?>,
    statusLabels: <?= json_encode($task_statuses) ?>,
};
window.PAGE_STATE = {
    members: <?= json_encode(array_map(fn($m) => [
        'id' => (int)$m['id'], 'name' => $m['name'], 'email' => $m['email'],
        'system_role' => $m['system_role'], 'department' => $m['department'],
        'role_in_project' => $m['role_in_project'], 'permission_level' => $m['permission_level'],
        'has_photo' => !empty($m['photo_filename']),
    ], $members)) ?>,
    tasks: <?= json_encode(array_map(fn($t) => [
        'id' => (int)$t['id'], 'title' => $t['title'], 'description' => $t['description'],
        'status' => $t['status'], 'priority' => $t['priority'],
        'start_date' => $t['start_date'], 'due_date' => $t['due_date'],
        'assigned_to' => $t['assigned_to'] ? (int)$t['assigned_to'] : null,
        'assignee_name' => $t['assignee_name'], 'assignee_role' => $t['assignee_role'],
        'assignees' => $assignees_by_task[$t['id']] ?? [],
        'labels' => $labels_by_task[$t['id']] ?? [],
        'subtasks' => $subtasks_by_task[$t['id']] ?? [],
        'comment_count' => $comment_counts_by_task[$t['id']] ?? 0,
        'attachment_count' => $attachment_counts_by_task[$t['id']] ?? 0,
    ], $all_tasks)) ?>,
};
</script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/pages/project-detail.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-detail.js') ?>"></script>
<script src="assets/js/pages/project-detail-views.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-detail-views.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
