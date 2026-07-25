<?php
require_once 'includes/bootstrap.php';
require_role(['admin','manager']);

use App\Repositories\ProjectRepository;

$page_title = 'New Project';
$u = current_user();
$error = '';
$projects = new ProjectRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_code = trim($_POST['project_code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $manager_id = (int)($_POST['manager_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? null;
    $member_ids = $_POST['member_id'] ?? [];
    $member_roles = $_POST['member_role'] ?? [];

    if ($project_code === '' || $name === '' || !$manager_id) {
        $error = 'Project code, name and manager are required.';
    } elseif ($projects->codeExists($project_code)) {
        $error = 'A project with this code already exists.';
    } else {
        $project_id = $projects->create([
            'project_code' => $project_code,
            'name' => $name,
            'description' => $description,
            'manager_id' => $manager_id,
            'start_date' => $start_date,
        ]);

        $seen = [];
        foreach ($member_ids as $i => $mid) {
            $mid = (int)$mid;
            $role = trim($member_roles[$i] ?? '') ?: 'Team Member';
            if ($mid && !isset($seen[$mid])) {
                $projects->addMember($project_id, $mid, $role);
                $seen[$mid] = true;
            }
        }

        header('Location: project_detail.php?id=' . $project_id);
        exit;
    }
}

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/emp-picker.js"></script>

<div class="breadcrumb"><a href="projects.php">Projects</a> / New Project</div>

<div class="panel" style="max-width:760px;">
    <div class="panel-head"><h3>Project Details</h3></div>
    <div class="panel-body">
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" action="project_add.php" id="projectForm">
            <div class="form-grid">
                <div class="field">
                    <label>Project ID / Code</label>
                    <input type="text" name="project_code" placeholder="BEL-PRJ-001" required
                           value="<?= htmlspecialchars($_POST['project_code'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Project Name</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Manager</label>
                    <div id="managerPicker"></div>
                </div>
                <div class="field">
                    <label>Start Date</label>
                    <input type="date" name="start_date">
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label>Description</label>
                    <textarea name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>

            <h3 style="font-size:13px;color:var(--navy);margin:18px 0 8px;">Team Members</h3>
            <div id="memberRows"></div>
            <button type="button" class="btn btn-secondary" id="addMemberRow" style="margin-bottom:16px;">+ Add Team Member</button>
            <br>
            <button type="submit" class="btn">Create Project</button>
            <a href="projects.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<script src="assets/js/pages/project-add.js"></script>

<?php require 'includes/layout_bottom.php'; ?>
