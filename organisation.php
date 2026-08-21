<?php
require_once 'includes/bootstrap.php';
require_login();

$page_title = 'Organisation';
$u = current_user();
$is_admin = $u['role'] === 'admin';

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<?php if ($is_admin): ?>
<script src="assets/js/emp-picker.js?v=<?= filemtime(__DIR__ . '/assets/js/emp-picker.js') ?>"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<?php endif; ?>

<div class="org-page">
<div class="standalone-panel-head org-panel-head">
    <h3>Organisation Structure</h3>
    <div class="panel-head-tools">
        <div class="search-bar">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" id="orgFilterInput" placeholder="Search by name or staff ID…">
        </div>
    </div>
</div>
<div id="orgSearchResult" class="org-search-result" style="display:none;"></div>
<div id="orgEmployeeInfo" class="org-info-block" style="display:none;"></div>
</div>
<div id="orgChartPrompt" class="empty-state">Search by name or employee ID above to view that employee's manager and direct reports.</div>
<div class="org-chart" id="orgChartWrap" style="display:none;">
    <div class="org-roots"></div>
</div>

<script>
window.PAGE_CONFIG = { isAdmin: <?= $is_admin ? 'true' : 'false' ?>, isEmployee: <?= $u['role'] === 'employee' ? 'true' : 'false' ?> };
</script>
<script src="assets/js/pages/organisation.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/organisation.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
