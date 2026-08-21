<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ForumRepository;

$u = current_user();
$question_id = (int)($_GET['id'] ?? 0);
$repo = new ForumRepository();

$question = $repo->findQuestion($question_id);
if (!$question) {
    http_response_code(404);
    die('<div style="font-family:sans-serif;padding:40px;">Question not found. <a href="forum.php">Back to forum</a></div>');
}

$answer_sort = in_array($_GET['sort'] ?? '', ['oldest', 'newest'], true) ? $_GET['sort'] : 'helpful';
$answers = $repo->answersForQuestion($question_id, $answer_sort);
$is_author = (int)$question['user_id'] === (int)$u['id'];
$is_admin = $u['role'] === 'admin';

$answer_ids = array_map(fn($a) => (int)$a['id'], $answers);
$answer_attachments = $repo->attachmentsForAnswers($answer_ids);
$answer_comments = $repo->commentsForAnswers($answer_ids);

$my_helpful_answers = $repo->myHelpfulAnswers((int)$u['id'], $answer_ids);

$page_title = $question['title'];
require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>

<div id="pageError" class="error-msg" style="display:none;"></div>

<div class="forum-thread-page">
    <div class="breadcrumb"><a href="forum.php">Discussion Forum</a></div>
    <div class="forum-question-head">
        <div class="forum-question-title-line">
            <h2><?= htmlspecialchars($question['title']) ?></h2>
        </div>
        <div class="forum-question-head-actions">
            <?php if ($is_author): ?>
            <button type="button" id="forumEditQuestionBtn" class="forum-edit-link">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
                Edit
            </button>
            <?php endif; ?>
            <?php if ($is_author || $is_admin): ?>
            <button type="button" class="forum-delete-link forum-delete-link-lg" data-type="question" data-id="<?= $question_id ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Delete Question
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="forum-thread-layout">
        <div class="forum-thread-main">
            <div class="forum-thread-body">
                <div class="forum-thread-content" id="forumQuestionBody">
                    <p class="forum-body-text"><?= nl2br(htmlspecialchars($question['body'])) ?></p>
                </div>
            </div>

            <div class="forum-answers-head-row">
                <h3 class="forum-answers-heading"><?= count($answers) ?> Answer<?= count($answers) === 1 ? '' : 's' ?></h3>
                <?php if (count($answers) > 0): ?>
                <label class="forum-sort-label">Sort by:
                    <select id="forumAnswerSort" class="filter-select">
                        <option value="helpful" <?= $answer_sort === 'helpful' ? 'selected' : '' ?>>Most Helpful</option>
                        <option value="oldest" <?= $answer_sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="newest" <?= $answer_sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                    </select>
                </label>
                <?php endif; ?>
            </div>

            <div id="forumAnswersList">
                <?php foreach ($answers as $a): ?>
                <?= render_forum_answer(
                    $a, $question_id, $is_author, $u,
                    $my_helpful_answers[(int)$a['id']] ?? false,
                    $answer_attachments[(int)$a['id']] ?? [],
                    $answer_comments[(int)$a['id']] ?? []
                ) ?>
                <?php endforeach; ?>
            </div>

            <div class="forum-post-answer">
                <h3>Your Answer</h3>
                <textarea id="forumAnswerBody" rows="6" placeholder="Write your answer…"></textarea>
                <button type="button" id="forumPostAnswerBtn" class="pill-btn pill-btn-lg">Post Answer</button>
            </div>
        </div>

        <div class="forum-question-details">
            <h3 class="forum-details-heading">Question Details</h3>
            <div class="forum-details-row">
                <span class="forum-details-label">Category</span>
                <span class="forum-details-value">
                    <?php if ($question['tags']): ?>
                        <?php foreach ($question['tags'] as $tag): ?>
                        <span class="tag"><?= htmlspecialchars($tag['name']) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        &mdash;
                    <?php endif; ?>
                </span>
            </div>
            <div class="forum-details-row">
                <span class="forum-details-label">Asked by</span>
                <span class="forum-details-value"><?= htmlspecialchars($question['author_name']) ?></span>
            </div>
            <div class="forum-details-row">
                <span class="forum-details-label">Department</span>
                <span class="forum-details-value"><?= htmlspecialchars($question['author_department'] ?: '—') ?></span>
            </div>
            <div class="forum-details-row">
                <span class="forum-details-label">Asked on</span>
                <span class="forum-details-value"><?= htmlspecialchars(date('M j, Y', strtotime($question['created_at']))) ?></span>
            </div>
            <div class="forum-details-row">
                <span class="forum-details-label">Status</span>
                <span class="forum-details-value"><?= render_forum_status_badge($question['status']) ?></span>
            </div>
        </div>
    </div>
</div>

<script>
window.PAGE_CONFIG = {
    questionId: <?= (int)$question_id ?>,
    isAuthor: <?= $is_author ? 'true' : 'false' ?>,
    isAdmin: <?= $is_admin ? 'true' : 'false' ?>,
    currentUserId: <?= (int)$u['id'] ?>,
    questionTitle: <?= json_encode($question['title']) ?>,
    questionBody: <?= json_encode($question['body']) ?>,
    questionTags: <?= json_encode(implode(', ', array_column($question['tags'], 'name'))) ?>,
};
</script>
<script src="assets/js/pages/forum-question.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/forum-question.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
