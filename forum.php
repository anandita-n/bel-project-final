<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ForumRepository;

$page_title = 'Discussion Forum';
$u = current_user();
$repo = new ForumRepository();
$questions = $repo->listQuestions();
$tags = $repo->allTags();
$departments = $repo->departmentsWithQuestions();

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>

<div class="forum-page-head">
    <h2>Discussion Forum</h2>
    <a href="forum_ask.php" class="btn btn-lg btn-outline">Ask Question</a>
</div>

<div class="forum-toolbar">
    <div class="search-bar forum-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="forumSearchInput" placeholder="Search questions…">
    </div>
    <select id="forumStatusFilter">
        <option value="">All statuses</option>
        <option value="open">Open</option>
        <option value="solved">Solved</option>
    </select>
    <select id="forumDepartmentFilter">
        <option value="">All departments</option>
        <?php foreach ($departments as $d): ?>
        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="forumTagFilter">
        <option value="">All tags</option>
        <?php foreach ($tags as $t): ?>
        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="forumSortSelect" class="forum-sort-select">
        <option value="recent">Most Recent</option>
        <option value="most_helpful">Most Helpful</option>
        <option value="updated">Recently Updated</option>
    </select>
</div>

<div id="forumEmpty" class="empty-state" style="<?= empty($questions) ? '' : 'display:none;' ?>">No questions yet. Be the first to ask one.</div>
<div id="forumList" class="forum-list" style="<?= empty($questions) ? 'display:none;' : '' ?>">
    <?php foreach ($questions as $q): ?>
        <?= render_forum_row($q) ?>
    <?php endforeach; ?>
</div>
<div id="forumPagination" class="forum-pagination" style="display:none;"></div>

<script src="assets/js/pages/forum.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/forum.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
