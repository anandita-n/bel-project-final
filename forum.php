<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ForumRepository;

$page_title = 'Discussion Forum';
$u = current_user();
$repo = new ForumRepository();
$questions = $repo->listQuestions();
$tags = $repo->allTags();

require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>

<div class="forum-page-wrap">

<div class="forum-hero">
    <div class="forum-hero-text">
        <h2>Discussion Forum</h2>
    </div>
    <a href="forum_ask.php" class="pill-btn pill-btn-lg forum-ask-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
        Ask Question
    </a>
</div>

<div class="forum-toolbar">
    <div class="search-bar forum-search">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="forumSearchInput" placeholder="Search questions…">
    </div>
    <select id="forumStatusFilter" class="filter-select">
        <option value="">All statuses</option>
        <option value="open">Open</option>
        <option value="solved">Solved</option>
    </select>
    <select id="forumTagFilter" class="filter-select">
        <option value="">All tags</option>
        <?php foreach ($tags as $t): ?>
        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="forumSortSelect" class="filter-select forum-sort-select">
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

</div>

<script src="assets/js/pages/forum.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/forum.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
