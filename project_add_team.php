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

$page_title = 'New Project — Team';
require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/emp-picker.js"></script>

<div class="breadcrumb"><a href="projects.php">Projects</a> / <?= htmlspecialchars($project['name']) ?> / Team</div>

<div class="pa-page">
    <div class="pa-header">
        <h2><?= htmlspecialchars($project['name']) ?></h2>
        <p class="pa-header-sub">Step 2 of 5 — add the people working on this project. You can always add more later.</p>
    </div>

    <?= render_wizard_stepper(2) ?>

    <div id="pageError" class="error-msg" style="display:none;"></div>

    <div class="panel pa-panel">
        <div class="panel-head"><h3>Add Team Member</h3></div>
        <div class="panel-body">
            <div class="form-grid">
                <div class="field">
                    <label>Employee</label>
                    <div id="addMemberPicker"></div>
                </div>
                <div class="field">
                    <label>Project Role</label>
                    <input type="text" id="addMemberRole" placeholder="e.g. Developer, Tester">
                </div>
                <div class="field">
                    <label>Permission</label>
                    <select id="addMemberPermission">
                        <option value="member" selected>Member</option>
                        <option value="lead">Lead</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
            </div>
            <div class="pa-actions" style="margin-top:0;">
                <button type="button" id="addMemberBtn" class="btn">+ Add to Team</button>
            </div>
        </div>
    </div>

    <div class="panel pa-panel">
        <div class="panel-head"><h3>Project Members (<?= count($members) ?>)</h3></div>
        <div class="panel-body">
            <div id="membersEmpty" class="empty-state" style="<?= empty($members) ? '' : 'display:none;' ?>">No team members added yet.</div>
            <div style="<?= empty($members) ? 'display:none;' : '' ?>" class="table-scroll">
                <table class="team-table" id="teamTable">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Project Role</th>
                            <th>Permission</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $m): ?>
                        <tr data-user-id="<?= (int)$m['id'] ?>">
                            <td>
                                <div class="team-table-employee">
                                    <?= render_avatar(['id' => $m['id'], 'name' => $m['name'], 'role' => $m['system_role'], 'photo_filename' => $m['photo_filename'] ?? null], 'avatar-sm') ?>
                                    <span><?= htmlspecialchars($m['name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($m['department'] ?? '—') ?></td>
                            <td><input type="text" class="team-role-input" value="<?= htmlspecialchars($m['role_in_project']) ?>"></td>
                            <td>
                                <select class="team-permission-select">
                                    <option value="member" <?= $m['permission_level'] === 'member' ? 'selected' : '' ?>>Member</option>
                                    <option value="lead" <?= $m['permission_level'] === 'lead' ? 'selected' : '' ?>>Lead</option>
                                    <option value="manager" <?= $m['permission_level'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                </select>
                            </td>
                            <td><button type="button" class="btn-icon-danger team-remove-btn" title="Remove">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="pa-actions">
        <a href="project_add_tasks.php?id=<?= $project_id ?>" class="btn btn-lg">Continue to Tasks</a>
        <a href="project_add.php" class="btn btn-lg btn-secondary">Back</a>
    </div>
</div>

<script>
window.PAGE_CONFIG = { projectId: <?= (int)$project_id ?> };
</script>
<script src="assets/js/pages/project-add-team.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/project-add-team.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
