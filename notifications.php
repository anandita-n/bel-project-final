<?php
require_once 'includes/bootstrap.php';
require_login();

use App\Repositories\MemberReviewRepository;
use App\Repositories\NotificationRepository;

$page_title = 'Notifications';
$u = current_user();

$reviewRepo = new MemberReviewRepository();
$reviews = $reviewRepo->forUser((int)$u['id'], 100);

$notifRepo = new NotificationRepository();
// Forum-comment notifications are intentionally excluded — this page is for project/task activity.
$notifs = array_filter($notifRepo->forUser((int)$u['id'], 100), fn($n) => $n['type'] !== 'forum_comment');

$notifications = array_merge(
    array_map(fn($r) => [
        'project_id' => $r['project_id'],
        'project_code' => $r['project_code'],
        'from' => $r['author_name'],
        'message' => $r['comment'],
        'created_at' => $r['created_at'],
        'is_read' => (bool)$r['is_read'],
    ], $reviews),
    array_map(fn($n) => [
        'project_id' => $n['project_id'],
        'project_code' => $n['project_code'],
        'from' => $n['actor_name'] ?? 'System',
        'message' => $n['message'],
        'created_at' => $n['created_at'],
        'is_read' => (bool)$n['is_read'],
    ], $notifs)
);
usort($notifications, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

$reviewHadUnread = false;
foreach ($reviews as $r) {
    if (!$r['is_read']) { $reviewHadUnread = true; break; }
}
if ($reviewHadUnread) {
    $reviewRepo->markAllRead((int)$u['id']);
}
$notifHadUnread = false;
foreach ($notifs as $n) {
    if (!$n['is_read']) { $notifHadUnread = true; break; }
}
if ($notifHadUnread) {
    $notifRepo->markAllRead((int)$u['id']);
}

require 'includes/layout_top.php';
?>

<div class="standalone-panel-head">
    <h3>Notifications (<?= count($notifications) ?>)</h3>
</div>

<?php if (empty($notifications)): ?>
    <div class="empty-state">No notifications yet.</div>
<?php else: ?>
<div class="notif-list">
    <?php foreach ($notifications as $n): ?>
    <div class="notif-row">
        <div class="notif-project"><a href="project_detail.php?id=<?= $n['project_id'] ?>"><?= htmlspecialchars($n['project_code']) ?></a></div>
        <div class="notif-from"><?= htmlspecialchars($n['from']) ?></div>
        <div class="notif-message"><?= nl2br(htmlspecialchars($n['message'])) ?> <?php if (!$n['is_read']): ?><span class="tag tag-high">New</span><?php endif; ?></div>
        <div class="notif-date"><?= htmlspecialchars(date('d M Y', strtotime($n['created_at']))) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require 'includes/layout_bottom.php'; ?>
