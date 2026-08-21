<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\MemberReviewRepository;
use App\Repositories\NotificationRepository;

$u = require_login_json();

$reviewRepo = new MemberReviewRepository();
$notifRepo = new NotificationRepository();

// Fetch a generous window from each source (unread notifications are typically a small subset
// of the total), then filter to unread and keep only the 5 most recent for the popup.
$items = array_merge(
    array_map(fn($r) => [
        'id' => (int)$r['id'],
        'type' => 'review',
        'project_id' => (int)$r['project_id'],
        'project_code' => $r['project_code'],
        'from' => $r['author_name'],
        'message' => $r['comment'],
        'created_at' => $r['created_at'],
        'is_read' => (bool)$r['is_read'],
    ], $reviewRepo->forUser((int)$u['id'], 50)),
    array_map(fn($n) => [
        'id' => (int)$n['id'],
        'type' => 'notification',
        'project_id' => $n['project_id'] !== null ? (int)$n['project_id'] : null,
        'project_code' => $n['project_code'],
        'from' => $n['actor_name'] ?? 'System',
        'message' => $n['message'],
        'created_at' => $n['created_at'],
        'is_read' => (bool)$n['is_read'],
    ], $notifRepo->forUser((int)$u['id'], 50))
);
$items = array_values(array_filter($items, fn($n) => !$n['is_read']));
usort($items, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$items = array_slice($items, 0, 5);

json_out([
    'items' => $items,
    'unread_count' => $reviewRepo->unreadCountForUser((int)$u['id']) + $notifRepo->unreadCountForUser((int)$u['id']),
]);
