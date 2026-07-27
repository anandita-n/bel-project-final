<?php
require_once 'includes/bootstrap.php';
require_role(['admin','manager']);

use App\Repositories\UserRepository;

$page_title = 'Organisation';
$users = new UserRepository();

$staffId = trim($_GET['staff_id'] ?? '');
$error = '';
$employee = null;
$chain = [];
$children = [];

if ($staffId !== '') {
    $employee = $users->findByEmployeeCode($staffId);

    if (!$employee) {
        $error = 'No employee found with Staff ID "' . htmlspecialchars($staffId) . '".';
    } else {
        $chain[] = $employee;
        $current = $employee;
        while (!empty($current['manager_id'])) {
            $mgr = $users->findById((int)$current['manager_id']);
            if (!$mgr) break;
            $chain[] = $mgr;
            $current = $mgr;
        }
        $chain = array_reverse($chain);
        $children = $users->directReports((int)$employee['id']);
    }
}

require 'includes/layout_top.php';
?>

<div class="panel">
    <div class="panel-head"><h3>Find Employee</h3></div>
    <div class="panel-body">
        <form method="GET" action="organisation.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <div class="field" style="margin-bottom:0; flex:1; min-width:220px; max-width:280px;">
                <label for="staff_id">Staff ID</label>
                <input type="text" id="staff_id" name="staff_id" value="<?= htmlspecialchars($staffId) ?>" placeholder="e.g. BEL0002" required autofocus>
            </div>
            <button type="submit" class="btn">Display</button>
        </form>
        <?php if ($error): ?><div class="error-msg" style="margin-top:14px;"><?= $error ?></div><?php endif; ?>
    </div>
</div>

<?php if ($employee): ?>
<div class="panel">
    <div class="panel-head"><h3>Organisation Tree — <?= htmlspecialchars($employee['name']) ?> (<?= htmlspecialchars($employee['employee_code']) ?>)</h3></div>
    <div class="panel-body">
        <div class="org-chart">
            <?php foreach ($chain as $i => $node):
                $isSelf = $node['id'] === $employee['id'];
            ?>
                <div class="org-node <?= $isSelf ? 'self' : '' ?>">
                    <span class="avatar org-avatar <?= avatar_class($node['role']) ?>"><?= htmlspecialchars(initials($node['name'])) ?></span>
                    <?php if ($isSelf): ?>
                        <span class="org-name"><?= htmlspecialchars($node['name']) ?></span>
                    <?php else: ?>
                        <a href="employee_detail.php?id=<?= $node['id'] ?>" class="org-name"><?= htmlspecialchars($node['name']) ?></a>
                    <?php endif; ?>
                    <span class="org-role"><?= htmlspecialchars($node['employee_code']) ?> &middot; <?= htmlspecialchars(ucfirst($node['role'])) ?><?= $node['department'] ? ' &middot; ' . htmlspecialchars($node['department']) : '' ?></span>
                </div>
                <?php if ($i < count($chain) - 1 || !empty($children)): ?>
                    <div class="org-stem"></div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (!empty($children)): ?>
                <div class="org-children">
                    <?php foreach ($children as $c): ?>
                    <div class="org-child-col">
                        <div class="org-node">
                            <span class="avatar org-avatar <?= avatar_class($c['role']) ?>"><?= htmlspecialchars(initials($c['name'])) ?></span>
                            <a href="employee_detail.php?id=<?= $c['id'] ?>" class="org-name"><?= htmlspecialchars($c['name']) ?></a>
                            <span class="org-role"><?= htmlspecialchars($c['employee_code']) ?> &middot; <?= htmlspecialchars(ucfirst($c['role'])) ?><?= $c['department'] ? ' &middot; ' . htmlspecialchars($c['department']) : '' ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="org-no-reports">No direct reports</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require 'includes/layout_bottom.php'; ?>
