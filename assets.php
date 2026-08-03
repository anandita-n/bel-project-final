<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\AssetRepository;

$page_title = 'Asset Management';
$u = current_user();
$is_admin = $u['role'] === 'admin';

$repo = new AssetRepository();
$employeeId = $u['role'] === 'employee' ? (int)$u['id'] : null;
$rows = $repo->search('', '', '', $employeeId);

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<?php if ($is_admin): ?>
<script src="assets/js/emp-picker.js"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<script src="assets/js/dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown.js') ?>"></script>
<?php endif; ?>

<div class="panel panel-table">
    <div class="panel-head">
        <h3><?= $u['role'] === 'employee' ? 'My Assets' : 'All Assets' ?></h3>
        <div class="panel-head-tools">
            <div class="search-bar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="assetSearchInput" placeholder="Search asset ID, name, serial, employee…">
            </div>
            <select id="assetCategoryFilter">
                <option value="">All categories</option>
                <?php foreach (AssetRepository::CATEGORIES as $key => $label): ?>
                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="assetStatusFilter">
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

<script>
window.PAGE_CONFIG = {
    isAdmin: <?= $is_admin ? 'true' : 'false' ?>,
    categories: <?= json_encode(AssetRepository::CATEGORIES) ?>,
    statuses: <?= json_encode(AssetRepository::STATUSES) ?>,
};
</script>
<script src="assets/js/pages/assets.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/assets.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
