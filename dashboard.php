<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\UserRepository;
use App\Repositories\ProjectRepository;

$page_title = 'Dashboard';
$u = current_user();

$users = new UserRepository();
$projectRepo = new ProjectRepository();

$emp_count = $users->countActive();
$proj_count = $projectRepo->countAll();
$active_proj_count = $projectRepo->countActive();
$projects = $projectRepo->recentForUser($u, 8);

require 'includes/layout_top.php';
?>

<div class="stat-row">
    <div class="stat-card">
        <div class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div>
            <div class="num"><?= (int)$emp_count ?></div>
            <div class="lbl">Active Employees</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
        <div>
            <div class="num"><?= (int)$proj_count ?></div>
            <div class="lbl">Total Projects</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
        <div>
            <div class="num"><?= (int)$active_proj_count ?></div>
            <div class="lbl">Active Projects</div>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3><?= $u['role'] === 'admin' ? 'Recent Projects' : 'Your Projects' ?></h3>
        <a href="projects.php">View all</a>
    </div>
    <div class="panel-body" style="padding:0;">
        <?php if (empty($projects)): ?>
            <div class="empty-state">No projects to show.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Code</th><th>Project</th><th>Manager</th><th>Team</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $p): ?>
                <tr>
                    <td><a href="project_detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['project_code']) ?></a></td>
                    <td><a href="project_detail.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></td>
                    <td><?= htmlspecialchars($p['manager_name']) ?></td>
                    <td><?= (int)$p['member_count'] ?></td>
                    <td><span class="tag tag-<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_',' ', $p['status']))) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/layout_bottom.php'; ?>
