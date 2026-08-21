<?php
require_once 'includes/bootstrap.php';
require_role(['admin']);

use App\Repositories\UserRepository;

$page_title = 'Deactivated Employees';
$repo = new UserRepository();
$inactive = $repo->listInactive();

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>

<div class="breadcrumb"><a href="employees.php">Staff</a> / Deactivated</div>

<div class="panel panel-table">
    <div class="panel-head">
        <h3>Deactivated Employees</h3>
    </div>
    <div class="panel-body" style="padding:0;overflow:visible;">
        <div id="deactivatedEmpty" class="empty-state" style="<?= empty($inactive) ? '' : 'display:none;' ?>">
            No deactivated employees.
        </div>
        <table id="deactivatedTable" style="<?= empty($inactive) ? 'display:none;' : '' ?>">
            <thead>
                <tr>
                    <th>Staff</th><th>ID</th><th>Email</th><th>Role</th><th>Department</th><th></th>
                </tr>
            </thead>
            <tbody id="deactivatedTbody">
                <?php foreach ($inactive as $e): ?>
                <tr data-id="<?= (int)$e['id'] ?>">
                    <td><div class="row-name"><?= render_avatar($e) ?><span><?= htmlspecialchars($e['name']) ?></span></div></td>
                    <td><?= htmlspecialchars(preg_replace('/-DEL\d+$/', '', $e['employee_code'])) ?></td>
                    <td><?= htmlspecialchars(preg_replace('/\.deleted\d+$/', '', $e['email'])) ?></td>
                    <td><span class="dir-badge dir-badge-<?= htmlspecialchars($e['role']) ?>"><?= htmlspecialchars(ucfirst($e['role'])) ?></span></td>
                    <td class="dept-cell"><?= htmlspecialchars($e['department'] ?: '—') ?></td>
                    <td><button type="button" class="pill-btn pill-btn-sm reactivate-btn">Reactivate</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const tbody = document.getElementById('deactivatedTbody');
    tbody.addEventListener('click', function (ev) {
        const btn = ev.target.closest('.reactivate-btn');
        if (!btn) return;
        const row = btn.closest('tr');
        const id = row.dataset.id;
        if (!confirm('Reactivate this employee? They will be able to log in again.')) return;

        btn.disabled = true;
        apiPost('api/employees/reactivate.php', { id: id }).then(function () {
            row.remove();
            if (!document.querySelector('#deactivatedTbody tr')) {
                document.getElementById('deactivatedTable').style.display = 'none';
                document.getElementById('deactivatedEmpty').style.display = '';
            }
        }).catch(function (err) {
            alert(err.message);
            btn.disabled = false;
        });
    });
})();
</script>

<?php require 'includes/layout_bottom.php'; ?>
