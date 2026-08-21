<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\ForumRepository;

$page_title = 'Ask a Question';
$u = current_user();
$error = '';
$repo = new ForumRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $tagsRaw = trim($_POST['tags'] ?? '');
    $tags = $tagsRaw === '' ? [] : array_filter(array_map('trim', explode(',', $tagsRaw)));

    if ($title === '' || $body === '') {
        $error = 'Title and question details are required.';
    } else {
        $id = $repo->createQuestion((int)$u['id'], $title, $body, $tags);
        header('Location: forum_question.php?id=' . $id);
        exit;
    }
}

require 'includes/layout_top.php';
?>

<div class="pa-page-wrap">
<div class="breadcrumb"><a href="forum.php">Discussion Forum</a> / Ask a Question</div>

<div class="pa-header">
    <h2>Ask a Question</h2>
</div>

<?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" action="forum_ask.php">
<div class="pa-page">

        <div class="pa-section pa-section-plain">
            <div class="pa-section-head">
                <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <div>
                    <h3>Question Details</h3>
                </div>
            </div>
            <div class="pa-section-body">
                <div class="field">
                    <label>Title <span class="required-mark">*</span></label>
                    <input type="text" name="title" required placeholder="Be specific — e.g. How do I debounce a search input in vanilla JS?" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Details <span class="required-mark">*</span></label>
                    <textarea name="body" rows="8" required placeholder="Describe what you've tried and what you're seeing."><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="pa-section pa-section-plain">
            <div class="pa-section-head">
                <svg class="pa-section-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2.5 12.5V2.5h10L20.59 10.6a2 2 0 0 1 0 2.82Z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                <div>
                    <h3>Tags <span class="pa-optional">(Optional)</span></h3>
                </div>
            </div>
            <div class="pa-section-body">
                <div class="field" style="margin-bottom:0;">
                    <input type="text" name="tags" placeholder="comma, separated, tags" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
                    <div class="field-hint">Helps others find your question — e.g. embedded, git, testing.</div>
                </div>
            </div>
        </div>

        <div class="pa-actions">
            <a href="forum.php" class="pill-btn pill-btn-lg pill-btn-secondary">Cancel</a>
            <button type="submit" class="pill-btn pill-btn-lg">Post Your Question</button>
        </div>
</div>
</form>
</div>

<?php require 'includes/layout_bottom.php'; ?>
