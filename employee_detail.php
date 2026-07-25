<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\UserRepository;
use App\Repositories\ProjectRepository;

$employee_id = (int)($_GET['id'] ?? 0);
$users = new UserRepository();

$employee = $users->findActiveById($employee_id);

if (!$employee) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:40px;">Employee not found. <a href="employees.php">Back to employees</a></div>');
}

$manager = $employee['manager_id'] ? $users->findActiveById((int)$employee['manager_id']) : null;
$reports = $users->directReports($employee_id);
$projects = (new ProjectRepository())->projectsForEmployee($employee_id);

$page_title = 'Employee: ' . $employee['name'];
require 'includes/layout_top.php';
?>

<div class="breadcrumb"><a href="employees.php">Employees</a> / <?= htmlspecialchars($employee['name']) ?></div>

<div class="panel">
    <div class="panel-head"><h3>
        <span class="row-name">
            <span class="avatar <?= avatar_class($employee['role']) ?>"><?= htmlspecialchars(initials($employee['name'])) ?></span>
            <?= htmlspecialchars($employee['name']) ?>
        </span>
    </h3>
        <span class="tag tag-<?= htmlspecialchars($employee['role']) ?>"><?= htmlspecialchars(ucfirst($employee['role'])) ?></span>
    </div>
    <div class="panel-body">
        <div class="form-grid">
            <div><strong>Employee Code:</strong> <?= htmlspecialchars($employee['employee_code']) ?></div>
            <div><strong>Email:</strong> <?= htmlspecialchars($employee['email']) ?></div>
            <div><strong>Department:</strong> <?= htmlspecialchars($employee['department'] ?? '—') ?></div>
            <div><strong>Reports To:</strong> <?= $manager ? htmlspecialchars($manager['name']) : '—' ?></div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h3>Projects (<?= count($projects) ?>)</h3></div>
    <div class="panel-body" style="padding:0;">
        <?php if (empty($projects)): ?>
            <div class="empty-state">Not currently part of any project.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Code</th><th>Project</th><th>Role</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $p): ?>
                <tr>
                    <td><a href="project_detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['project_code']) ?></a></td>
                    <td><a href="project_detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></td>
                    <td><?= htmlspecialchars($p['role_in_project']) ?></td>
                    <td><span class="tag tag-<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ', $p['status']))) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-head"><h3>Org Chart</h3></div>
    <div class="panel-body">
        <div class="org-chart">
            <?php if ($manager): ?>
                <div class="org-node">
                    <span class="avatar org-avatar <?= avatar_class($manager['role']) ?>"><?= htmlspecialchars(initials($manager['name'])) ?></span>
                    <a href="employee_detail.php?id=<?= $manager['id'] ?>" class="org-name"><?= htmlspecialchars($manager['name']) ?></a>
                    <span class="org-role"><?= htmlspecialchars(ucfirst($manager['role'])) ?><?= $manager['department'] ? ' · ' . htmlspecialchars($manager['department']) : '' ?></span>
                </div>
                <div class="org-stem"></div>
            <?php endif; ?>

            <div class="org-node self">
                <span class="avatar org-avatar <?= avatar_class($employee['role']) ?>"><?= htmlspecialchars(initials($employee['name'])) ?></span>
                <span class="org-name"><?= htmlspecialchars($employee['name']) ?></span>
                <span class="org-role"><?= htmlspecialchars(ucfirst($employee['role'])) ?><?= $employee['department'] ? ' · ' . htmlspecialchars($employee['department']) : '' ?></span>
            </div>

            <?php if (!empty($reports)): ?>
                <div class="org-stem"></div>
                <div class="org-children">
                    <?php foreach ($reports as $r): ?>
                    <div class="org-child-col">
                        <div class="org-node">
                            <span class="avatar org-avatar <?= avatar_class($r['role']) ?>"><?= htmlspecialchars(initials($r['name'])) ?></span>
                            <a href="employee_detail.php?id=<?= $r['id'] ?>" class="org-name"><?= htmlspecialchars($r['name']) ?></a>
                            <span class="org-role"><?= htmlspecialchars(ucfirst($r['role'])) ?><?= $r['department'] ? ' · ' . htmlspecialchars($r['department']) : '' ?></span>
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

<?php require 'includes/layout_bottom.php'; ?>
