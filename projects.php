<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ProjectRepository;

$page_title = 'Projects';
$u = current_user();
$is_admin = $u['role'] === 'admin';
$repo = new ProjectRepository();

$mine = !empty($_GET['mine']);
$archived = !empty($_GET['archived']);
$department = trim($_GET['department'] ?? '');
$page_title = $archived
    ? ($department !== '' ? 'Archived Projects — ' . $department : 'Archived Projects')
    : ($mine
        ? ($department !== '' ? 'My Projects — ' . $department : 'My Projects')
        : ($department !== '' ? 'Projects — ' . $department : 'Projects'));

if ($archived) {
    $perPage = 50;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $archived_projects = $repo->listForUser($u, '', '', $department, $page, $perPage, true);
    $total = $repo->countForUser($u, '', '', $department, true);
    $totalPages = max(1, (int)ceil($total / $perPage));
} elseif ($mine) {
    // A user can be a manager/member on projects spread across several departments, so "My
    // Projects" gets the same department-index-then-drilldown shape as "View All" — just scoped
    // to their own projects instead of the whole org. The set is small enough to group in PHP
    // rather than a second query.
    $my_projects = array_values(array_filter($repo->projectsForEmployee((int)$u['id']), fn($p) => empty($p['archived_at'])));
    if ($department === '') {
        $deptCounts = [];
        foreach ($my_projects as $p) {
            $deptName = ($p['department'] ?? '') !== '' ? $p['department'] : 'Unassigned';
            $deptCounts[$deptName] = ($deptCounts[$deptName] ?? 0) + 1;
        }
        ksort($deptCounts);
        $departments = array_map(fn($name, $count) => ['department' => $name, 'project_count' => $count], array_keys($deptCounts), $deptCounts);
    } else {
        $my_projects = array_values(array_filter($my_projects, function ($p) use ($department) {
            $deptName = ($p['department'] ?? '') !== '' ? $p['department'] : 'Unassigned';
            return $deptName === $department;
        }));
    }
} elseif ($department === '') {
    // Department index: cheap GROUP BY, no per-project data loaded until a department is picked.
    $departments = $repo->departmentSummaryForUser($u);
} else {
    $perPage = 50;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $projects = $repo->listForUser($u, '', '', $department, $page, $perPage);
    $total = $repo->countForUser($u, '', '', $department);
    $totalPages = max(1, (int)ceil($total / $perPage));
}

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script>window.PAGE_CONFIG = { isAdmin: <?= $is_admin ? 'true' : 'false' ?>, department: <?= json_encode($department) ?> };</script>

<?php if ($archived): ?>
<div class="breadcrumb"><a href="projects.php?archived=1">Archived Projects</a><?= $department !== '' ? ' / ' . htmlspecialchars($department) : '' ?></div>
<div class="standalone-panel-head">
    <h3>Archived Projects</h3>
    <div class="panel-head-tools">
        <div class="search-bar">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="archivedSearchInput" placeholder="Search archived projects…">
        </div>
    </div>
</div>
<?php if (empty($archived_projects)): ?>
<div class="empty-state">No archived projects<?= $department !== '' ? ' in this department' : '' ?>.</div>
<?php else: ?>
<div class="panel panel-table">
    <div class="panel-body" style="padding:0;">
        <table class="project-table archived-project-table" id="archivedProjectsTable">
            <thead>
                <tr><th>Code</th><th>Project Name</th><th>Department</th><th>Manager</th><th>Archived</th></tr>
            </thead>
            <tbody id="archivedProjectsTbody">
                <?php foreach ($archived_projects as $p): ?>
                <?= render_archived_project_row($p) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div id="archivedNoMatch" class="empty-state" style="display:none;">No archived projects match your search.</div>
<script>
(function () {
    var input = document.getElementById('archivedSearchInput');
    var rows = document.querySelectorAll('#archivedProjectsTbody tr');
    var noMatch = document.getElementById('archivedNoMatch');
    var table = document.getElementById('archivedProjectsTable');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        var anyVisible = false;
        rows.forEach(function (row) {
            var show = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
            row.style.display = show ? '' : 'none';
            if (show) anyVisible = true;
        });
        table.style.display = anyVisible ? '' : 'none';
        noMatch.style.display = anyVisible ? 'none' : '';
    });
})();
</script>
<?php endif; ?>

<?php elseif ($mine && $department === ''): ?>
<?php if (empty($departments)): ?>
<div class="empty-state">You're not involved in any projects yet.</div>
<?php else: ?>
<div class="dept-page">
<div class="standalone-panel-head">
    <h3>My Projects</h3>
</div>
<div class="dept-list" id="deptList">
    <?php foreach ($departments as $d): ?>
    <a class="dept-list-row" href="projects.php?mine=1&department=<?= urlencode($d['department']) ?>">
        <span><?= htmlspecialchars($d['department']) ?></span>
        <svg class="dept-list-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php elseif ($mine): ?>
<div class="breadcrumb"><a href="projects.php?mine=1">My Projects</a> / <?= htmlspecialchars($department) ?></div>
<div class="standalone-panel-head">
    <h3><?= htmlspecialchars($department) ?></h3>
</div>
<?php if (empty($my_projects)): ?>
<div class="empty-state">No projects in this department.</div>
<?php else: ?>
<div class="panel panel-table">
    <div class="panel-body" style="padding:0;">
        <table class="project-table">
            <thead>
                <tr><th>Code</th><th>Project Name</th><th>Manager</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($my_projects as $p): ?>
                <?= render_project_row($p) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php elseif ($department === ''): ?>
<?php if (empty($departments)): ?>
<div class="empty-state">No projects yet.</div>
<?php else: ?>
<div class="dept-page">
<div class="standalone-panel-head">
    <h3>Projects by Department</h3>
    <div class="panel-head-tools">
        <div class="search-bar">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="deptSearchInput" placeholder="Search departments…">
        </div>
    </div>
</div>
<div class="dept-list" id="deptList">
    <?php foreach ($departments as $d): ?>
    <a class="dept-list-row" href="projects.php?department=<?= urlencode($d['department']) ?>">
        <span><?= htmlspecialchars($d['department']) ?></span>
        <svg class="dept-list-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <?php endforeach; ?>
</div>
</div>
<script>
(function () {
    var input = document.getElementById('deptSearchInput');
    var rows = document.querySelectorAll('#deptList .dept-list-row');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        rows.forEach(function (row) {
            var name = row.querySelector('span').textContent.toLowerCase();
            row.style.display = name.includes(q) ? '' : 'none';
        });
    });
})();
</script>
<?php endif; ?>

<?php else: ?>
<div class="breadcrumb"><a href="projects.php">Projects</a> / <?= htmlspecialchars($department) ?></div>

<div class="standalone-panel-head">
    <h3><?= htmlspecialchars($department) ?></h3>
    <div class="panel-head-tools">
        <select id="statusFilter" class="filter-select">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="on_hold">On Hold</option>
            <option value="completed">Completed</option>
        </select>
        <div class="search-bar">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="searchInput" placeholder="Search name, code, manager…">
            <a class="clear-link" id="clearSearch" href="#" style="display:none;">Clear</a>
        </div>
    </div>
</div>
<div id="searchMeta" class="search-meta" style="display:none;"></div>
<div id="projectsEmpty" class="empty-state" style="<?= empty($projects) ? '' : 'display:none;' ?>">
    <div class="empty-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
    No projects found.
</div>
<div class="panel panel-table" id="projectsPanel" style="<?= empty($projects) ? 'display:none;' : '' ?>">
    <div class="panel-body" style="padding:0;">
        <table id="projectsTable" class="project-table">
            <thead>
                <tr><th>Code</th><th>Project Name</th><th>Manager</th><th>Status</th></tr>
            </thead>
            <tbody id="projectsTbody">
                <?php foreach ($projects as $p): ?>
                <?= render_project_row($p) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div id="projectsPagination" class="pagination-bar" style="<?= $totalPages <= 1 ? 'display:none;' : '' ?>"></div>
<?php endif; ?>

<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<?php if ($department !== ''): ?>
<script src="assets/js/pages/projects.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/projects.js') ?>"></script>
<?php endif; ?>

<?php require 'includes/layout_bottom.php'; ?>
