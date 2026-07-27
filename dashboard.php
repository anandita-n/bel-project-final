<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\UserRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\MemberReviewRepository;
use App\Repositories\TaskRepository;

$page_title = 'Dashboard';
$u = current_user();

$users = new UserRepository();
$projectRepo = new ProjectRepository();
$reviewRepo = new MemberReviewRepository();
$tasksRepo = new TaskRepository();

$emp_count = $users->countActive();
$proj_count = $projectRepo->countAll();
$active_proj_count = $projectRepo->countActive();
$projects = $projectRepo->recentForUser($u, 8);

$my_open_task_count = $tasksRepo->countOpenForAssignee((int)$u['id']);
$my_tasks = $tasksRepo->listOpenForAssignee((int)$u['id'], 8);
$today = date('Y-m-d');

$notifications = $reviewRepo->forUser((int)$u['id']);
$hadUnread = false;
foreach ($notifications as $n) {
    if (!$n['is_read']) { $hadUnread = true; break; }
}
if ($hadUnread) {
    $reviewRepo->markAllRead((int)$u['id']);
}

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
    <div class="stat-card">
        <div class="icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <div>
            <div class="num"><?= (int)$my_open_task_count ?></div>
            <div class="lbl">My Open Tasks</div>
        </div>
    </div>
</div>

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

<div class="panel" id="notifications">
    <div class="panel-head">
        <h3>Notifications (<?= count($notifications) ?>)</h3>
    </div>
    <div class="panel-body" style="padding:0;">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">No comments or reviews yet.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Project</th><th>From</th><th>Comment</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                <tr>
                    <td><a href="project_detail.php?id=<?= $n['project_id'] ?>"><?= htmlspecialchars($n['project_code']) ?></a></td>
                    <td><?= htmlspecialchars($n['author_name']) ?></td>
                    <td><?= nl2br(htmlspecialchars($n['comment'])) ?> <?php if (!$n['is_read']): ?><span class="tag tag-high">New</span><?php endif; ?></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime($n['created_at']))) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
