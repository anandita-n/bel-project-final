<?php
require_once 'includes/bootstrap.php';
require_role(['admin']);

use App\Repositories\UserRepository;

$u = current_user();
$repo = new UserRepository();

$department = trim($_GET['department'] ?? '');
$status = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : 'active';
$page_title = $department !== '' ? 'Staff — ' . $department : 'Staff';

if ($department === '') {
    // Department index: cheap GROUP BY, no per-employee data loaded until a department is picked.
    $departments = $repo->departmentSummary();
} else {
    $perPage = 50;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $employees = $repo->listActiveWithManager('', $department, $page, $perPage, $status);
    $total = $repo->countActiveWithManager('', $department, $status);
    $totalPages = max(1, (int)ceil($total / $perPage));
}

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js?v=<?= filemtime(__DIR__ . '/assets/js/emp-picker.js') ?>"></script>
<script src="assets/js/password-rules.js?v=<?= filemtime(__DIR__ . '/assets/js/password-rules.js') ?>"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<script src="assets/js/dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown.js') ?>"></script>

<?php if ($department === ''): ?>
<?php if (empty($departments)): ?>
<div class="empty-state">No staff yet.</div>
<?php else: ?>
<div class="dept-page">
<div class="dept-hero">
    <h3>Staff by Department</h3>
    <div class="search-bar dept-hero-search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="deptSearchInput" placeholder="Search departments…">
    </div>
</div>
<div class="dept-card-list" id="deptList">
    <?php foreach ($departments as $d): ?>
    <a class="dept-card-row" href="employees.php?department=<?= urlencode($d['department']) ?>">
        <span class="dept-card-name"><?= htmlspecialchars($d['department']) ?></span>
        <svg class="dept-card-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <?php endforeach; ?>
</div>
</div>
<script>
(function () {
    var input = document.getElementById('deptSearchInput');
    var rows = document.querySelectorAll('#deptList .dept-card-row');
    input.addEventListener('input', function () {
        var q = input.value.trim().toLowerCase();
        rows.forEach(function (row) {
            var name = row.querySelector('.dept-card-name').textContent.toLowerCase();
            row.style.display = name.includes(q) ? '' : 'none';
        });
    });
})();
</script>
<?php endif; ?>

<?php else: ?>
<div class="breadcrumb"><a href="employees.php">Staff</a> / <?= htmlspecialchars($department) ?></div>

<div class="panel panel-table">
    <div class="panel-head">
        <h3><?= htmlspecialchars($department) ?></h3>
        <div class="panel-head-tools">
            <select id="statusFilter" class="filter-select">
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Deactivated</option>
            </select>
            <div class="search-bar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="searchInput" placeholder="Search name, code, email…">
                <a class="clear-link" id="clearSearch" href="#" style="display:none;">Clear</a>
            </div>
        </div>
    </div>
    <div class="panel-body" style="padding:0;overflow:visible;">
        <div id="searchMeta" class="search-meta" style="padding:12px 18px 0; display:none;"></div>
        <div id="employeesEmpty" class="empty-state" style="<?= empty($employees) ? '' : 'display:none;' ?>">
            <div class="empty-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
            <?= $status === 'inactive' ? 'No deactivated employees.' : 'No employees found.' ?>
        </div>
        <table id="employeesTable" style="<?= empty($employees) ? 'display:none;' : '' ?>">
            <thead>
                <tr>
                    <th>Staff</th><th>ID</th><th>Email</th><th>Role</th>
                    <th>Department</th><th><?= $status === 'inactive' ? '' : 'Reports To' ?></th>
                </tr>
            </thead>
            <tbody id="employeesTbody">
                <?php foreach ($employees as $e): ?>
                <?= render_employee_row($e, $status === 'inactive') ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div id="employeesPagination" class="pagination-bar" style="<?= $totalPages <= 1 ? 'display:none;' : '' ?>"></div>
<?php endif; ?>

<script>
window.PAGE_CONFIG = {
    isAdmin: <?= $u['role'] === 'admin' ? 'true' : 'false' ?>,
    currentUserId: <?= (int)$u['id'] ?>,
    department: <?= json_encode($department) ?>,
    status: <?= json_encode($status) ?>,
};
</script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<?php if ($department !== ''): ?>
<script src="assets/js/pages/employees.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/employees.js') ?>"></script>
<?php endif; ?>

<?php require 'includes/layout_bottom.php'; ?>
