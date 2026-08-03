<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ProjectRepository;
use App\Repositories\MemberReviewRepository;
use App\Repositories\TaskRepository;

// Dashboard has been retired in favor of Projects as the landing page for every role.
header('Location: projects.php');
exit;

$page_title = 'Dashboard';
$u = current_user();

$projectRepo = new ProjectRepository();
$reviewRepo = new MemberReviewRepository();
$tasksRepo = new TaskRepository();

$projects = $projectRepo->recentForUser($u, 8);

$my_tasks = $tasksRepo->listOpenForAssignee((int)$u['id'], 8);
$today = date('Y-m-d');

$notifications = $reviewRepo->forUser((int)$u['id']);
$notif_preview = array_slice($notifications, 0, 3);

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first_name = trim(explode(' ', $u['name'])[0]);

require 'includes/layout_top.php';
?>

<div class="greeting-header">
    <h2><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($first_name) ?></h2>
    <div class="greeting-date"><?= htmlspecialchars(date('l, d F Y')) ?></div>
</div>

<div class="dashboard-grid">
    <div class="panel">
        <div class="panel-head">
            <h3>My Tasks</h3>
        </div>
        <div class="panel-body" style="padding:0;">
            <?php if (empty($my_tasks)): ?>
                <div class="empty-state">No open tasks assigned to you. Nice and clear.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr><th>Task</th><th>Project</th><th>Priority</th><th>Status</th><th>Due</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($my_tasks as $t): $overdue = $t['due_date'] && $t['due_date'] < $today; ?>
                    <tr>
                        <td><a href="project_detail.php?id=<?= $t['project_id'] ?>#board"><?= htmlspecialchars($t['title']) ?></a></td>
                        <td><a href="project_detail.php?id=<?= $t['project_id'] ?>"><?= htmlspecialchars($t['project_code']) ?></a></td>
                        <td><span class="tag tag-<?= htmlspecialchars($t['priority']) ?>"><?= htmlspecialchars(ucfirst($t['priority'])) ?></span></td>
                        <td><span class="tag tag-<?= htmlspecialchars($t['status']) ?>"><?= htmlspecialchars(TaskRepository::STATUSES[$t['status']]) ?></span></td>
                        <td<?= $overdue ? ' style="color:var(--danger);font-weight:700;"' : '' ?>><?= $t['due_date'] ? htmlspecialchars(date('d M', strtotime($t['due_date']))) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h3>Notifications</h3>
            <a href="notifications.php">View all</a>
        </div>
        <div class="panel-body" style="padding:0;">
            <?php if (empty($notif_preview)): ?>
                <div class="empty-state">No comments or reviews yet.</div>
            <?php else: ?>
                <?php foreach ($notif_preview as $n): ?>
                <div class="overview-activity-row">
                    <span><?= render_avatar(['id' => $n['author_id'], 'name' => $n['author_name'], 'role' => $n['author_role'] ?? 'employee', 'photo_filename' => $n['author_photo_filename'] ?? null], 'avatar-sm activity-avatar') ?><a href="project_detail.php?id=<?= $n['project_id'] ?>"><?= htmlspecialchars($n['project_code']) ?></a> — <?= htmlspecialchars($n['author_name']) ?>: <?= htmlspecialchars(mb_strimwidth($n['comment'], 0, 60, '…')) ?> <?php if (!$n['is_read']): ?><span class="tag tag-high">New</span><?php endif; ?></span>
                    <span class="overview-row-date"><?= htmlspecialchars(date('d M', strtotime($n['created_at']))) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                        <?= render_project_row($p) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/layout_bottom.php'; ?>
