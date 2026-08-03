<?php
require_once 'includes/bootstrap.php';
require_role(['admin','manager']);

use App\Repositories\ProjectRepository;

$u = current_user();
$project_id = (int)($_GET['id'] ?? 0);
$projects = new ProjectRepository();

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

$page_title = 'Project Created';
require 'includes/layout_top.php';
?>

<div class="pa-page">
    <?= render_wizard_stepper(5) ?>

    <div class="success-page">
        <div class="success-icon">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h2 class="success-title">Project Created Successfully</h2>
        <p class="success-sub">"<?= htmlspecialchars($project['name']) ?>" is ready to go.</p>
        <p class="success-sub success-project-id">Project ID: <?= htmlspecialchars($project['project_code']) ?></p>

        <div class="panel pa-panel success-summary">
            <div class="panel-body">
                <div class="review-summary">
                    <div class="review-row">
                        <span class="review-label">Project Name</span>
                        <span class="review-value"><?= htmlspecialchars($project['name']) ?></span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Manager</span>
                        <span class="review-value"><?= htmlspecialchars($project['manager_name']) ?></span>
                    </div>
                    <div class="review-row">
                        <span class="review-label">Team Members</span>
                        <span class="review-value"><?= count($members) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="success-actions">
            <a href="project_detail.php?id=<?= $project_id ?>" class="btn btn-lg">Open Project</a>
            <a href="project_add.php" class="btn btn-lg btn-secondary">Create Another Project</a>
            <a href="projects.php" class="btn btn-lg btn-secondary">Back to Projects</a>
        </div>
    </div>
</div>

<?php require 'includes/layout_bottom.php'; ?>
