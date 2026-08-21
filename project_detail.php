<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TaskCommentRepository;
use App\Repositories\TaskAttachmentRepository;
use App\Repositories\ProjectDocumentRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\DefectRepository;

$u = current_user();
$project_id = (int)($_GET['id'] ?? 0);

$projects = new ProjectRepository();
$tasksRepo = new TaskRepository();
$commentsRepo = new TaskCommentRepository();
$attachmentsRepo = new TaskAttachmentRepository();
$documentsRepo = new ProjectDocumentRepository();
$notificationsRepo = new NotificationRepository();
$defectsRepo = new DefectRepository();

$project = $projects->findById($project_id);
if (!$project) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:40px;">Project not found. <a href="projects.php">Back to projects</a></div>');
}

if (!$projects->userHasAccess($project_id, $u)) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;">You do not have access to this project.</div>');
}

// Anyone outside this project (not its manager, not a project_members row, not an admin) who
// still passed the access check above only got in via the "same department" read-only rule —
// show them the description/manager/tasks (read-only), none of the member/document workspace
// or edit affordances.
if (!$projects->hasFullAccess($project, $u)) {
    $readonly_tasks = $tasksRepo->listForProject($project_id);
    $readonly_task_ids = array_map(fn($t) => (int)$t['id'], $readonly_tasks);
    $readonly_assignees_by_task = $tasksRepo->assigneesForTasks($readonly_task_ids);

    $readonly_members = $projects->members($project_id);
    if (!in_array((int)$project['manager_id'], array_column($readonly_members, 'id'), true)) {
        array_unshift($readonly_members, [
            'id' => (int)$project['manager_id'],
            'name' => $project['manager_name'],
            'email' => $project['manager_email'],
            'system_role' => $project['manager_role'],
            'department' => $project['manager_department'],
            'role_in_project' => 'Project Manager',
            'permission_level' => 'member',
        ]);
    }
    $readonly_member_stats = [];
    foreach ($readonly_members as $m) {
        $mine = array_filter($readonly_tasks, function ($t) use ($m, $readonly_assignees_by_task) {
            $assignees = $readonly_assignees_by_task[(int)$t['id']] ?? [];
            return in_array((int)$m['id'], array_column($assignees, 'id'), true);
        });
        $readonly_member_stats[$m['id']] = [
            'assigned' => count($mine),
            'completed' => count(array_filter($mine, fn($t) => $t['status'] === 'done')),
        ];
    }

    $page_title = 'Project: ' . $project['name'];
    require 'includes/layout_top.php';
    ?>
    <div class="breadcrumb"><a href="projects.php">Projects</a></div>

    <div class="project-header-v2">
        <div class="ph-top">
            <div class="ph-title-block">
                <div class="ph-title-line">
                    <h2><?= htmlspecialchars($project['project_code']) ?>: <?= htmlspecialchars($project['name']) ?></h2>
                    <span class="dir-badge dir-badge-<?= htmlspecialchars($project['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $project['status']))) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div id="projectTabs">
        <div class="tabs" role="tablist">
            <button type="button" class="tab-btn" data-tab="overview">About</button>
            <button type="button" class="tab-btn" data-tab="members">Members</button>
            <button type="button" class="tab-btn" data-tab="list">Tasks</button>
        </div>

        <div class="tab-panel" data-panel="overview">
            <?= render_project_about_card($project) ?>
        </div>

        <div class="tab-panel" data-panel="list">
            <div class="task-toolbar">
                <div class="search-bar" style="min-width:200px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" id="roTaskSearch" placeholder="Search tasks…">
                </div>
                <select id="roTaskStatus" class="filter-select">
                    <option value="all">All statuses</option>
                    <?php foreach (\App\Repositories\TaskRepository::STATUSES as $key => $label): ?>
                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="roTaskPriority" class="filter-select">
                    <option value="all">All priorities</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
                <select id="roTaskMine" class="filter-select">
                    <option value="all">All Tasks</option>
                    <option value="mine">My Tasks</option>
                </select>
                <select id="roTaskDate" class="filter-select">
                    <option value="all">All time</option>
                    <option value="1">Today</option>
                    <option value="7">This week</option>
                    <option value="30">This month</option>
                </select>
            </div>
            <?= render_readonly_task_grid($readonly_tasks, $readonly_assignees_by_task) ?>
        </div>

        <div class="tab-panel" data-panel="members">
            <?php if (empty($readonly_members)): ?>
            <div class="empty-state">No team members assigned yet.</div>
            <?php else: ?>
            <div class="member-card-grid">
                <?php foreach ($readonly_members as $m): ?>
                <?= render_member_card($m, false, $readonly_member_stats[$m['id']] ?? null) ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
    <script src="assets/js/tabs.js?v=<?= filemtime(__DIR__ . '/assets/js/tabs.js') ?>"></script>
    <script>initTabs(document.getElementById('projectTabs'), { defaultTab: 'overview' });</script>
    <script>window.ROJcfg = { currentUserId: <?= (int)$u['id'] ?> };</script>
    <script src="assets/js/pages/project-detail-readonly.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-detail-readonly.js') ?>"></script>
    <?php require 'includes/layout_bottom.php'; ?>
    <?php exit; ?>
<?php } ?>

<?php
$can_manage = $u['role'] === 'admin' || $u['id'] === (int)$project['manager_id'];
$is_archived = !empty($project['archived_at']);
// Archived projects are read-only — even a manager/admin can't add/edit/remove anything,
// only view history and Restore.
$can_edit = $can_manage && !$is_archived;

$members = $projects->members($project_id);

// The project manager isn't a project_members row — show them in the Members tab too,
// unless they were also separately added as a member (avoid a duplicate card).
if (!in_array((int)$project['manager_id'], array_column($members, 'id'), true)) {
    array_unshift($members, [
        'id' => (int)$project['manager_id'],
        'name' => $project['manager_name'],
        'email' => $project['manager_email'],
        'employee_code' => $project['manager_employee_code'],
        'system_role' => $project['manager_role'],
        'department' => $project['manager_department'],
        'role_in_project' => 'Project Manager',
        'permission_level' => 'member',
        'assigned_at' => $project['created_at'],
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

$defects = $defectsRepo->listForProject($project_id);
$defect_severities = DefectRepository::SEVERITIES;
$defect_statuses = DefectRepository::STATUSES;

// 'assigned' here means "active" (not yet done) — the Members tab's "Active tasks" column —
// not the raw total assigned, since a finished task shouldn't count as still active.
$member_task_stats = [];
foreach ($members as $m) {
    $mine = array_filter($all_tasks, function ($t) use ($m, $assignees_by_task) {
        $assignees = $assignees_by_task[(int)$t['id']] ?? [];
        return in_array((int)$m['id'], array_column($assignees, 'id'), true);
    });
    $completedCount = count(array_filter($mine, fn($t) => $t['status'] === 'done'));
    $member_task_stats[$m['id']] = [
        'assigned' => count($mine) - $completedCount,
        'completed' => $completedCount,
    ];
}

$project_updates = $notificationsRepo->forProject($project_id, 50);
$project_updates_preview = array_slice($project_updates, 0, 3);

// Grouped by calendar day for the Updates tab, same "Today"/"Yesterday"/date convention as
// the main Notifications page.
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$project_update_groups = [];
foreach ($project_updates as $upd) {
    $day = substr($upd['created_at'], 0, 10);
    $label = $day === $today ? 'Today' : ($day === $yesterday ? 'Yesterday' : date('F j, Y', strtotime($day)));
    $project_update_groups[$label][] = $upd;
}

$page_title = 'Project: ' . $project['name'];
require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js?v=<?= filemtime(__DIR__ . '/assets/js/emp-picker.js') ?>"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<script src="assets/js/drawer.js?v=<?= filemtime(__DIR__ . '/assets/js/drawer.js') ?>"></script>
<script src="assets/js/dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown.js') ?>"></script>
<script src="assets/js/tabs.js?v=<?= filemtime(__DIR__ . '/assets/js/tabs.js') ?>"></script>

<div class="breadcrumb"><a href="projects.php">Projects</a></div>

<div id="pageError" class="error-msg" style="display:none;"></div>

<div class="project-header-v2">
    <div class="ph-top">
        <div class="ph-title-block">
            <div class="ph-title-line">
                <h2><?= htmlspecialchars($project['project_code']) ?>: <span id="phTitleName"><?= htmlspecialchars($project['name']) ?></span></h2>
                <span class="dir-badge dir-badge-<?= htmlspecialchars($project['status']) ?>" id="phStatusTag"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $project['status']))) ?></span>
                <?php if ($is_archived): ?>
                <span class="dir-badge dir-badge-archived" title="Archived <?= htmlspecialchars(date('M j, Y', strtotime($project['archived_at']))) ?> by <?= htmlspecialchars($project['archived_by_name'] ?? 'Unknown') ?>">Archived</span>
                <?php endif; ?>
            </div>
            <?php if ($is_archived): ?>
            <div class="ph-archived-notice">This project is archived and read-only. All history is preserved — restore it to make changes.</div>
            <?php endif; ?>
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
<div id="projectTabs">
    <div class="tabs" role="tablist">
        <button type="button" class="tab-btn" data-tab="overview">About</button>
        <button type="button" class="tab-btn" data-tab="members">Members (<?= count($members) ?>)</button>
        <button type="button" class="tab-btn" data-tab="list">Tasks (<?= count($all_tasks) ?>)</button>
        <button type="button" class="tab-btn" data-tab="defects">Defects (<?= count($defects) ?>)</button>
        <button type="button" class="tab-btn" data-tab="documents">Documents (<?= count($documents) ?>)</button>
        <button type="button" class="tab-btn" data-tab="updates">Updates</button>
    </div>

    <div class="tab-panel" data-panel="overview">
        <?= render_project_about_card($project, $documents, $can_edit, true) ?>
    </div>

    <div class="tab-panel" data-panel="list">
        <div class="task-toolbar">
            <div class="search-bar" style="min-width:200px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" class="task-filter-search" placeholder="Search tasks…">
            </div>
            <select class="task-filter-status filter-select"><option value="all">All statuses</option></select>
            <select class="task-filter-priority filter-select">
                <option value="all">All priorities</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <select class="task-filter-mine filter-select">
                <option value="all">All Tasks</option>
                <option value="mine">My Tasks</option>
            </select>
            <select class="task-filter-date filter-select">
                <option value="all">All time</option>
                <option value="1">Today</option>
                <option value="7">This week</option>
                <option value="30">This month</option>
            </select>
            <div class="task-toolbar-spacer"></div>
            <?php if ($can_edit): ?>
            <button type="button" id="openAddTaskModal" class="pill-btn pill-btn-lg">+ Add Task</button>
            <?php endif; ?>
        </div>
        <div id="listBulkBar" class="list-bulk-bar" style="display:none;">
            <span id="listBulkCount">0 selected</span>
            <select id="listBulkStatus" class="filter-select"><option value="">Change status…</option></select>
            <select id="listBulkPriority" class="filter-select">
                <option value="">Change priority…</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <div id="listBulkAssigneePicker"></div>
            <button type="button" id="listBulkDelete" class="link-btn link-btn-danger">Delete</button>
            <a href="#" id="listBulkClear" class="clear-link">Save Changes</a>
        </div>
        <div id="listViewContainer"></div>
    </div>

    <div class="tab-panel" data-panel="members">
        <div class="task-toolbar">
            <div class="search-bar" style="min-width:200px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="membersSearch" placeholder="Search members…">
            </div>
            <div class="task-toolbar-spacer"></div>
            <?php if ($can_edit): ?>
            <button type="button" id="openAddMemberModal" class="pill-btn pill-btn-lg">+ Add Member</button>
            <?php endif; ?>
        </div>
        <div class="member-card-grid" id="memberCardGrid">
            <?php foreach ($members as $m): ?>
                <?= render_member_card($m, true, $member_task_stats[$m['id']] ?? null) ?>
            <?php endforeach; ?>
        </div>
        <div id="membersEmpty" class="empty-state" style="<?= empty($members) ? '' : 'display:none;' ?>">No team members assigned yet.</div>
        <div id="membersNoMatch" class="empty-state" style="display:none;">No members match these filters.</div>
    </div>

    <script>
    (function () {
        var searchInput = document.getElementById('membersSearch');
        var grid = document.getElementById('memberCardGrid');
        var noMatch = document.getElementById('membersNoMatch');
        if (!grid || !searchInput) return;

        searchInput.addEventListener('input', function () {
            var query = searchInput.value.trim().toLowerCase();
            var anyVisible = false;
            grid.querySelectorAll('.member-card').forEach(function (card) {
                var show = !query || card.dataset.userName.toLowerCase().indexOf(query) !== -1;
                card.style.display = show ? '' : 'none';
                if (show) anyVisible = true;
            });
            if (noMatch) noMatch.style.display = (grid.children.length && !anyVisible) ? '' : 'none';
        });
    })();
    </script>

    <div class="tab-panel" data-panel="defects">
        <div class="task-toolbar">
            <div class="search-bar" style="min-width:200px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="defectsSearch" placeholder="Search defects…">
            </div>
            <select class="filter-select" id="defectsStatusFilter">
                <option value="">All statuses</option>
                <?php foreach ($defect_statuses as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filter-select" id="defectsSeverityFilter">
                <option value="">All severities</option>
                <?php foreach ($defect_severities as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="filter-select" id="defectsDateFilter">
                <option value="all">All time</option>
                <option value="1">Today</option>
                <option value="7">This week</option>
                <option value="30">This month</option>
            </select>
            <div class="task-toolbar-spacer"></div>
            <?php if ($can_edit): ?>
            <button type="button" id="openAddDefectModal" class="pill-btn pill-btn-lg">+ Add Defect</button>
            <?php endif; ?>
        </div>
        <div id="defectListWrap"><?= render_defect_list($defects) ?></div>
        <div id="defectsNoMatch" class="empty-state" style="display:none;">No defects match these filters.</div>
    </div>

    <script>
    (function () {
        var searchInput = document.getElementById('defectsSearch');
        var statusFilter = document.getElementById('defectsStatusFilter');
        var severityFilter = document.getElementById('defectsSeverityFilter');
        var dateFilter = document.getElementById('defectsDateFilter');
        var noMatch = document.getElementById('defectsNoMatch');
        if (!searchInput) return;

        function applyDefectFilters() {
            var query = searchInput.value.trim().toLowerCase();
            var status = statusFilter.value;
            var severity = severityFilter.value;
            var days = dateFilter.value;
            var cutoff = null;
            if (days !== 'all') {
                cutoff = new Date();
                cutoff.setDate(cutoff.getDate() - parseInt(days, 10));
            }
            var list = document.getElementById('defectList');
            var anyVisible = false;
            if (list) {
                list.querySelectorAll('.defect-list-row').forEach(function (row) {
                    var title = row.querySelector('.defect-list-title');
                    var matchesQuery = !query || (title && title.textContent.toLowerCase().indexOf(query) !== -1);
                    var matchesStatus = !status || row.dataset.status === status;
                    var matchesSeverity = !severity || row.dataset.severity === severity;
                    var matchesDate = !cutoff || new Date(row.dataset.created) >= cutoff;
                    var show = matchesQuery && matchesStatus && matchesSeverity && matchesDate;
                    row.style.display = show ? '' : 'none';
                    if (show) anyVisible = true;
                });
            }
            if (noMatch) noMatch.style.display = (list && !anyVisible) ? '' : 'none';
        }

        searchInput.addEventListener('input', applyDefectFilters);
        statusFilter.addEventListener('change', applyDefectFilters);
        severityFilter.addEventListener('change', applyDefectFilters);
        dateFilter.addEventListener('change', applyDefectFilters);
    })();
    </script>

    <div class="tab-panel" data-panel="documents">
        <?php if ($can_edit): ?>
        <div class="task-toolbar">
            <div class="task-toolbar-spacer"></div>
            <label class="pill-btn pill-btn-lg" for="documentFileInput" style="cursor:pointer;">+ Upload Document</label>
            <input type="file" id="documentFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.png,.jpg,.jpeg,.zip" style="display:none;">
        </div>
        <?php endif; ?>
        <div id="documentsEmpty" class="empty-state" style="<?= empty($documents) ? '' : 'display:none;' ?>">No documents uploaded yet.</div>
        <div id="documentsList" style="<?= empty($documents) ? 'display:none;' : '' ?>">
            <?php foreach ($documents as $d): ?>
                <?= render_document_row($d, $project_id, $can_edit) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tab-panel" data-panel="updates">
        <div class="updates-page">
        <div class="task-toolbar">
            <select class="filter-select" id="updatesCategory">
                <option value="all">All activity</option>
                <option value="tasks">Tasks</option>
                <option value="comments">Comments</option>
                <option value="status">Status</option>
            </select>
            <select class="filter-select" id="updatesDateRange">
                <option value="all">All time</option>
                <option value="1">Today</option>
                <option value="7">This week</option>
                <option value="30">This month</option>
            </select>
            <div class="task-toolbar-spacer"></div>
            <div class="search-bar" style="min-width:220px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="updatesSearch" placeholder="Search updates…">
            </div>
        </div>
        <?php if (empty($project_updates)): ?>
        <div class="empty-state">No activity yet.</div>
        <?php else: ?>
        <div id="updatesGroups">
            <?php foreach ($project_update_groups as $label => $rows): ?>
            <div class="ov-update-group">
                <h4 class="ov-update-group-heading"><?= htmlspecialchars($label) ?></h4>
                <?php foreach ($rows as $upd): ?>
                    <?= render_project_update_row($upd, true) ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="empty-state" id="updatesEmpty" style="display:none;">No updates match these filters.</div>
        <?php endif; ?>
        </div>
    </div>

</div>

<script>
(function () {
    var categorySelect = document.getElementById('updatesCategory');
    var dateRangeSelect = document.getElementById('updatesDateRange');
    var searchInput = document.getElementById('updatesSearch');
    var groupsWrap = document.getElementById('updatesGroups');
    var emptyMsg = document.getElementById('updatesEmpty');
    if (!groupsWrap) return;

    function applyUpdatesFilter() {
        var category = categorySelect.value;
        var days = dateRangeSelect.value;
        var query = searchInput.value.trim().toLowerCase();
        var anyVisible = false;

        groupsWrap.querySelectorAll('.ov-update-row').forEach(function (row) {
            var matchesCategory = category === 'all' || row.dataset.category === category;
            var matchesDate = days === 'all' || (Date.now() - new Date(row.dataset.created.replace(' ', 'T')).getTime()) <= days * 24 * 60 * 60 * 1000;
            var matchesQuery = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
            var show = matchesCategory && matchesDate && matchesQuery;
            row.style.display = show ? '' : 'none';
            if (show) anyVisible = true;
        });
        groupsWrap.querySelectorAll('.ov-update-group').forEach(function (group) {
            var groupVisible = Array.prototype.slice.call(group.querySelectorAll('.ov-update-row')).some(function (r) { return r.style.display !== 'none'; });
            group.style.display = groupVisible ? '' : 'none';
        });
        groupsWrap.style.display = anyVisible ? '' : 'none';
        if (emptyMsg) emptyMsg.style.display = anyVisible ? 'none' : '';
    }

    categorySelect.addEventListener('change', applyUpdatesFilter);
    dateRangeSelect.addEventListener('change', applyUpdatesFilter);
    searchInput.addEventListener('input', applyUpdatesFilter);
})();
</script>

<script>
(function () {
    function wireViewAll(btnId, tab) {
        var btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', function () {
            var tabBtn = document.querySelector('#projectTabs .tab-btn[data-tab="' + tab + '"]');
            if (tabBtn) tabBtn.click();
        });
    }
    wireViewAll('ovViewAllUpdates', 'updates');
    wireViewAll('ovViewAllDocuments', 'documents');
    wireViewAll('ovGoToDocuments', 'documents');
})();
</script>

<script>
window.PAGE_CONFIG = {
    projectId: <?= (int)$project_id ?>,
    projectName: <?= json_encode($project['name']) ?>,
    projectCode: <?= json_encode($project['project_code']) ?>,
    projectStatus: <?= json_encode($project['status']) ?>,
    projectDescription: <?= json_encode($project['description'] ?? '') ?>,
    projectDepartment: <?= json_encode($project['department'] ?? '') ?>,
    projectDueDate: <?= json_encode($project['due_date'] ?? '') ?>,
    documentsHtml: <?= json_encode(implode('', array_map(fn($d) => render_document_row($d, $project_id, $can_edit), $documents))) ?>,
    canManage: <?= $can_manage ? 'true' : 'false' ?>,
    canEdit: <?= $can_edit ? 'true' : 'false' ?>,
    isArchived: <?= $is_archived ? 'true' : 'false' ?>,
    isAdmin: <?= $u['role'] === 'admin' ? 'true' : 'false' ?>,
    managerId: <?= (int)$project['manager_id'] ?>,
    managerName: <?= json_encode($project['manager_name']) ?>,
    currentUserId: <?= (int)$u['id'] ?>,
    statusLabels: <?= json_encode($task_statuses) ?>,
};
window.PAGE_STATE = {
    members: <?= json_encode(array_map(fn($m) => [
        'id' => (int)$m['id'], 'name' => $m['name'], 'email' => $m['email'],
        'employee_code' => $m['employee_code'] ?? null,
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
        'created_at' => $t['created_at'], 'updated_at' => $t['updated_at'],
    ], $all_tasks)) ?>,
    defects: <?= json_encode(array_map(fn($d) => [
        'id' => (int)$d['id'], 'code' => $d['code'], 'title' => $d['title'], 'description' => $d['description'],
        'severity' => $d['severity'], 'status' => $d['status'],
        'assigned_to' => $d['assigned_to'] ? (int)$d['assigned_to'] : null,
        'assignee_name' => $d['assignee_name'], 'assignee_role' => $d['assignee_role'],
        'assignee_has_photo' => !empty($d['assignee_photo_filename']),
        'reporter_name' => $d['reporter_name'],
        'created_at' => $d['created_at'], 'updated_at' => $d['updated_at'],
    ], $defects)) ?>,
};
</script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/pages/project-detail.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-detail.js') ?>"></script>
<script src="assets/js/pages/project-detail-views.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-detail-views.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
