<?php
require_once 'includes/bootstrap.php';
require_role(['admin', 'employee']);

use App\Repositories\AssetRepository;

$page_title = 'Asset Management';
$u = current_user();
$is_admin = $u['role'] === 'admin';
$is_employee = $u['role'] === 'employee';

$repo = new AssetRepository();

// Employees only ever see their own small "My Assets" list — no department segregation needed
// there. Admins/managers get a department index (cheap GROUP BY) + paginated drill-down, same
// pattern as Staff/Projects, since the full asset list can grow large.
$department = $is_employee ? '' : trim($_GET['department'] ?? '');
$page_title = $department !== '' ? 'Asset Management — ' . $department : 'Asset Management';

if ($is_employee) {
    $rows = $repo->search('', '', '', (int)$u['id']);
} elseif ($department === '') {
    $departments = $repo->departmentSummary();
} else {
    $perPage = 50;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $rows = $repo->search('', '', '', null, $department, $page, $perPage);
    $total = $repo->countSearch('', '', '', null, $department);
    $totalPages = max(1, (int)ceil($total / $perPage));
}

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<?php if ($is_admin): ?>
<script src="assets/js/emp-picker.js?v=<?= filemtime(__DIR__ . '/assets/js/emp-picker.js') ?>"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<script src="assets/js/dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown.js') ?>"></script>
<?php endif; ?>

<?php if (!$is_employee && $department === ''): ?>
<?php if (empty($departments)): ?>
<div class="empty-state">No assets yet.</div>
<?php else: ?>
<div class="dept-page">
<div class="standalone-panel-head">
    <h3>Assets by Department</h3>
    <div class="panel-head-tools">
        <div class="search-bar">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="deptSearchInput" placeholder="Search departments…">
        </div>
    </div>
</div>
<div class="dept-list" id="deptList">
    <?php foreach ($departments as $d): ?>
    <a class="dept-list-row" href="assets.php?department=<?= urlencode($d['department']) ?>">
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
<?php if (!$is_employee): ?>
<div class="breadcrumb"><a href="assets.php">Asset Management</a> / <?= htmlspecialchars($department) ?></div>
<?php endif; ?>

<div class="panel panel-table">
    <div class="panel-head">
        <h3><?= $is_employee ? 'My Assets' : htmlspecialchars($department) ?></h3>
        <div class="panel-head-tools">
            <div class="search-bar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="assetSearchInput" placeholder="Search asset ID, name, serial, employee…">
            </div>
            <select id="assetCategoryFilter" class="filter-select">
                <option value="">All categories</option>
                <?php foreach (AssetRepository::CATEGORIES as $key => $label): ?>
                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="assetStatusFilter" class="filter-select">
                <option value="">All statuses</option>
                <?php foreach (AssetRepository::STATUSES as $key => $label): ?>
                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="panel-body" style="padding:0;overflow:visible;">
        <div id="assetsEmpty" class="empty-state" style="<?= empty($rows) ? '' : 'display:none;' ?>">No assets found.</div>
        <table id="assetsTable" style="<?= empty($rows) ? 'display:none;' : '' ?>">
            <thead>
                <tr>
                    <th>Asset ID</th><th>Name</th><th>Category</th><th>Serial Number</th>
                    <th>Assigned To</th><th>Department</th><th>Status</th>
                    <?php if ($is_admin): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="assetsTbody">
                <?php foreach ($rows as $a): ?>
                <?= render_asset_row($a, $is_admin) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if (!$is_employee): ?>
<div id="assetsPagination" class="pagination-bar" style="<?= $totalPages <= 1 ? 'display:none;' : '' ?>"></div>
<?php endif; ?>
<?php endif; ?>

<script>
window.PAGE_CONFIG = {
    isAdmin: <?= $is_admin ? 'true' : 'false' ?>,
    categories: <?= json_encode(AssetRepository::CATEGORIES) ?>,
    statuses: <?= json_encode(AssetRepository::STATUSES) ?>,
    department: <?= json_encode($department) ?>,
    paginated: <?= !$is_employee ? 'true' : 'false' ?>,
};
</script>
<?php if ($is_employee || $department !== ''): ?>
<script src="assets/js/pages/assets.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/assets.js') ?>"></script>
<?php endif; ?>

<?php require 'includes/layout_bottom.php'; ?>
