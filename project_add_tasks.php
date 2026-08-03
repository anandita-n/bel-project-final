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

$tasks = $tasksRepo->listForProject($project_id);
$task_statuses = TaskRepository::STATUSES;

$page_title = 'New Project — Tasks';
require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/emp-picker.js"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>
<script src="assets/js/dropdown.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdown.js') ?>"></script>

<div class="breadcrumb"><a href="projects.php">Projects</a> / <?= htmlspecialchars($project['name']) ?> / Tasks</div>

<div class="pa-page">
    <div class="pa-header">
        <h2><?= htmlspecialchars($project['name']) ?></h2>
        <p class="pa-header-sub">Step 3 of 5 — decide how you want to start populating tasks.</p>
    </div>

    <?= render_wizard_stepper(3) ?>

    <div id="pageError" class="error-msg" style="display:none;"></div>

    <div id="taskStartChoices" style="<?= empty($tasks) ? '' : 'display:none;' ?>">
        <div class="choice-card-grid">
            <label class="choice-card" id="choiceCreateNow">
                <input type="radio" class="choice-card-input" name="task_start" value="create">
                <span class="choice-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </span>
                <span class="choice-card-title">Create Tasks Now</span>
                <span class="choice-card-desc">Add your first tasks directly, right here.</span>
            </label>
            <label class="choice-card" id="choiceSkip">
                <input type="radio" class="choice-card-input" name="task_start" value="skip">
                <span class="choice-card-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </span>
                <span class="choice-card-title">Skip For Now</span>
                <span class="choice-card-desc">Add tasks later from the project board.</span>
            </label>
        </div>
    </div>

    <div id="taskCreatePanel" style="<?= empty($tasks) ? 'display:none;' : '' ?>">
        <div class="panel pa-panel">
            <div class="panel-head"><h3>Add Task</h3></div>
            <div class="panel-body">
                <div class="form-grid">
                    <div class="field">
                        <label>Task Title</label>
                        <input type="text" id="taskTitle">
                    </div>
                    <div class="field">
                        <label>Assign To</label>
                        <div id="assigneePicker"></div>
                    </div>
                    <div class="field">
                        <label>Priority</label>
                        <select id="taskPriority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Start Date</label>
                        <input type="date" id="taskStartDate">
                    </div>
                    <div class="field">
                        <label>Due Date</label>
                        <input type="date" id="taskDueDate">
                    </div>
                </div>
                <button type="button" id="addTaskBtn" class="btn">+ Add Task</button>
            </div>
        </div>

        <div class="panel pa-panel">
            <div class="panel-head"><h3>Tasks (<?= count($tasks) ?>)</h3></div>
            <div class="panel-body">
                <div id="tasksEmpty" class="empty-state" style="<?= empty($tasks) ? '' : 'display:none;' ?>">No tasks added yet.</div>
                <div class="member-card-grid" id="taskCardGrid" style="<?= empty($tasks) ? 'display:none;' : '' ?>">
                    <?php foreach ($tasks as $t): ?>
                        <?= render_task_card($t, $task_statuses, true, $u) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="pa-actions">
        <a href="project_add_review.php?id=<?= $project_id ?>" class="btn btn-lg" id="continueToReviewBtn">Continue to Review</a>
        <a href="project_add_team.php?id=<?= $project_id ?>" class="btn btn-lg btn-secondary">Back</a>
    </div>
</div>

<script>
window.PAGE_CONFIG = { projectId: <?= (int)$project_id ?>, statusLabels: <?= json_encode($task_statuses) ?> };
</script>
<script src="assets/js/pages/project-add-tasks.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-add-tasks.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
