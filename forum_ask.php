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

<div class="breadcrumb"><a href="forum.php">Discussion Forum</a> / Ask a Question</div>

<div class="panel" style="max-width:760px;">
    <div class="panel-head"><h3>Ask a Question</h3></div>
    <div class="panel-body">
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" action="forum_ask.php">
            <div class="field">
                <label>Title</label>
                <input type="text" name="title" required placeholder="Be specific — e.g. How do I debounce a search input in vanilla JS?" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Details</label>
                <textarea name="body" rows="8" required placeholder="Describe what you've tried and what you're seeing."><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label>Tags</label>
                <input type="text" name="tags" placeholder="comma, separated, tags" value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-lg">Post Your Question</button>
            <a href="forum.php" class="btn btn-lg btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php require 'includes/layout_bottom.php'; ?>
