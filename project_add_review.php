<?php
require_once 'includes/bootstrap.php';
require_role(['admin','manager']);

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;

$u = current_user();
$project_id = (int)($_GET['id'] ?? 0);
$projects = new ProjectRepository();
$tasksRepo = new TaskRepository();

$project = $projects->findById($project_id);
if (!$project) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:40px;">Project not found. <a href="projects.php">Back to projects</a></div>');
}
$can_manage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];
if (!$can_manage) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;">You do not have access to this project.</div>');
}

$members = $projects->members($project_id);
$tasks = $tasksRepo->listForProject($project_id);

$page_title = 'New Project — Review';
require 'includes/layout_top.php';
?>

<div class="breadcrumb"><a href="projects.php">Projects</a> / <?= htmlspecialchars($project['name']) ?> / Review</div>

<div class="pa-page">
    <div class="pa-header">
        <h2><?= htmlspecialchars($project['name']) ?></h2>
        <p class="pa-header-sub">Step 4 of 5 — review everything before creating the project.</p>
    </div>

    <?= render_wizard_stepper(4) ?>

    <div class="panel pa-panel">
        <div class="panel-head"><h3>Project Summary</h3></div>
        <div class="panel-body">
            <div class="review-summary">
                <div class="review-row">
                    <span class="review-label">Project Name</span>
                    <span class="review-value"><?= htmlspecialchars($project['name']) ?></span>
                </div>
                <div class="review-row">
                    <span class="review-label">Project Code</span>
                    <span class="review-value"><?= htmlspecialchars($project['project_code']) ?></span>
                </div>
                <div class="review-row">
                    <span class="review-label">Department</span>
                    <span class="review-value"><?= htmlspecialchars($project['department'] ?: '—') ?></span>
                </div>
                <div class="review-row">
                    <span class="review-label">Manager</span>
                    <span class="review-value"><?= htmlspecialchars($project['manager_name']) ?></span>
                </div>
                <div class="review-row">
                    <span class="review-label">Priority</span>
                    <span class="review-value"><span class="tag tag-<?= htmlspecialchars($project['priority']) ?>"><?= htmlspecialchars(ucfirst($project['priority'])) ?></span></span>
                </div>
                <div class="review-row">
                    <span class="review-label">Start Date</span>
                    <span class="review-value"><?= $project['start_date'] ? htmlspecialchars(date('d M Y', strtotime($project['start_date']))) : '—' ?></span>
                </div>
                <div class="review-row">
                    <span class="review-label">Target Completion</span>
                    <span class="review-value"><?= $project['due_date'] ? htmlspecialchars(date('d M Y', strtotime($project['due_date']))) : '—' ?></span>
                </div>
                <div class="review-row">
                    <span class="review-label">Team Members</span>
                    <span class="review-value"><?= count($members) ?></span>
                </div>
                <div class="review-row">
                    <span class="review-label">Initial Tasks</span>
                    <span class="review-value"><?= count($tasks) ?></span>
                </div>
                <?php if ($project['description']): ?>
                <div class="review-row">
                    <span class="review-label">Description</span>
                    <span class="review-value"><?= nl2br(htmlspecialchars($project['description'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="pa-actions">
        <a href="project_add_success.php?id=<?= $project_id ?>" class="btn btn-lg">Create Project</a>
        <a href="project_add_tasks.php?id=<?= $project_id ?>" class="btn btn-lg btn-secondary">Back</a>
    </div>
</div>

<?php require 'includes/layout_bottom.php'; ?>
