<?php
require_once 'includes/bootstrap.php';
require_role(['admin','manager']);

use App\Repositories\UserRepository;

$page_title = 'Employees';
$u = current_user();
$employees = (new UserRepository())->listActiveWithManager();

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js"></script>
<script src="assets/js/modal.js"></script>

<div class="panel">
    <div class="panel-head">
        <h3>All Employees</h3>
        <div style="display:flex; gap:10px; align-items:center;">
            <div class="search-bar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="searchInput" placeholder="Search name, code, email, department…">
                <a class="clear-link" id="clearSearch" href="#" style="display:none;">Clear</a>
            </div>
            <?php if ($u['role'] === 'admin'): ?>
            <button type="button" id="openAddEmployeeModal" class="btn">+ Add Employee</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="panel-body" style="padding:0;">
        <div id="searchMeta" class="search-meta" style="padding:12px 18px 0; display:none;"></div>
        <div id="employeesEmpty" class="empty-state" style="display:none;">
            <div class="empty-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
            No employees found.
        </div>
        <table id="employeesTable">
            <thead>
                <tr>
                    <th>Employee</th><th>Employee Code</th><th>Email</th><th>Role</th>
                    <th>Department</th><th>Reports To</th>
                    <?php if ($u['role'] === 'admin'): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody id="employeesTbody">
                <?php foreach ($employees as $e): ?>
                <?= render_employee_row($e, $u) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
window.PAGE_CONFIG = {
    isAdmin: <?= $u['role'] === 'admin' ? 'true' : 'false' ?>,
    currentUserId: <?= (int)$u['id'] ?>,
};
</script>
<script src="assets/js/utils.js"></script>
<script src="assets/js/pages/employees.js"></script>

<?php require 'includes/layout_bottom.php'; ?>
