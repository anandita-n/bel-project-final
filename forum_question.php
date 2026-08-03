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

$answers = $repo->answersForQuestion($question_id);
$is_author = (int)$question['user_id'] === (int)$u['id'];
$is_admin = $u['role'] === 'admin';

$answer_ids = array_map(fn($a) => (int)$a['id'], $answers);
$answer_comments = $repo->commentsForAnswers($answer_ids);
$answer_attachments = $repo->attachmentsForAnswers($answer_ids);

$my_helpful_answers = $repo->myHelpfulAnswers((int)$u['id'], $answer_ids);

$page_title = $question['title'];
require 'includes/layout_top.php';
?>

<script src="assets/js/api.js"></script>
<script src="assets/js/utils.js?v=<?= filemtime(__DIR__ . '/assets/js/utils.js') ?>"></script>
<script src="assets/js/modal.js?v=<?= filemtime(__DIR__ . '/assets/js/modal.js') ?>"></script>

<div class="breadcrumb"><a href="forum.php">Discussion Forum</a></div>

<div id="pageError" class="error-msg" style="display:none;"></div>

<div class="forum-thread-page">
    <div class="forum-question-head">
        <div class="forum-question-title-line">
            <h2><?= htmlspecialchars($question['title']) ?></h2>
            <?= render_forum_status_badge($question['status']) ?>
        </div>
        <a href="forum_ask.php" class="btn">Ask Question</a>
    </div>
    <div class="forum-meta-line">
        Asked by <?= htmlspecialchars($question['author_name']) ?><?= !empty($question['author_department']) ? ' &middot; ' . htmlspecialchars($question['author_department']) : '' ?> on <?= htmlspecialchars(date('d M Y', strtotime($question['created_at']))) ?>
        &middot; <?= count($answers) ?> answer<?= count($answers) === 1 ? '' : 's' ?>
    </div>

    <div class="forum-thread-body">
        <div class="forum-thread-content">
            <p class="forum-body-text"><?= nl2br(htmlspecialchars($question['body'])) ?></p>
            <?php if (!empty($question['tags'])): ?>
            <div class="forum-tag-list">
                <?php foreach ($question['tags'] as $tag): ?>
                <span class="tag"><?= htmlspecialchars($tag['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($is_author || $is_admin): ?>
            <button type="button" class="forum-delete-link" data-type="question" data-id="<?= $question_id ?>">Delete question</button>
            <?php endif; ?>
        </div>
    </div>

    <h3 class="forum-answers-heading"><?= count($answers) ?> Answer<?= count($answers) === 1 ? '' : 's' ?></h3>

    <div id="forumAnswersList">
        <?php foreach ($answers as $a): ?>
        <?= render_forum_answer(
            $a, $question_id, $is_author, $u,
            $answer_comments[(int)$a['id']] ?? [],
            $my_helpful_answers[(int)$a['id']] ?? false,
            $answer_attachments[(int)$a['id']] ?? []
        ) ?>
        <?php endforeach; ?>
    </div>

    <div class="forum-post-answer">
        <h3>Your Answer</h3>
        <textarea id="forumAnswerBody" rows="6" placeholder="Write your answer…"></textarea>
        <button type="button" id="forumPostAnswerBtn" class="btn btn-lg">Post Answer</button>
    </div>
</div>

<script>
window.PAGE_CONFIG = {
    questionId: <?= (int)$question_id ?>,
    isAuthor: <?= $is_author ? 'true' : 'false' ?>,
    isAdmin: <?= $is_admin ? 'true' : 'false' ?>,
    currentUserId: <?= (int)$u['id'] ?>,
};
</script>
<script src="assets/js/pages/forum-question.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/forum-question.js') ?>"></script>

<?php require 'includes/layout_bottom.php'; ?>
