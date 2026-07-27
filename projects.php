<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ProjectRepository;

$page_title = 'Projects';
$u = current_user();
$projects = (new ProjectRepository())->listForUser($u);

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>

<div class="panel">
    <div class="panel-head">
        <h3>Projects</h3>
        <div class="panel-head-tools">
            <div class="search-bar">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" id="searchInput" placeholder="Search name, code, manager…">
                <a class="clear-link" id="clearSearch" href="#" style="display:none;">Clear</a>
            </div>
        </div>
    </div>
    <div class="panel-body" style="padding:0;">
        <div id="searchMeta" class="search-meta" style="padding:12px 18px 0; display:none;"></div>
        <div id="projectsEmpty" class="empty-state" style="display:none;">
            <div class="empty-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
            No projects found.
        </div>
        <table id="projectsTable">
            <thead>
                <tr><th>Code</th><th>Project Name</th><th>Manager</th><th>Team Size</th><th>Status</th></tr>
            </thead>
            <tbody id="projectsTbody">
                <?php foreach ($projects as $p): ?>
                <?= render_project_row($p) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="assets/js/utils.js"></script>
<script src="assets/js/pages/projects.js"></script>

<?php require 'includes/layout_bottom.php'; ?>
