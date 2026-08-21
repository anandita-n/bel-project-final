<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\MemberReviewRepository;
use App\Repositories\NotificationRepository;

$u = require_login_json();

$body = request_body();
$items = is_array($body['items'] ?? null) ? $body['items'] : [];

$reviewRepo = new MemberReviewRepository();
$notifRepo = new NotificationRepository();

foreach ($items as $item) {
    $id = (int)($item['id'] ?? 0);
    $type = $item['type'] ?? '';
    if (!$id || !in_array($type, ['review', 'notification'], true)) {
        continue;
    }
    if ($type === 'review') {
        $reviewRepo->deleteOne($id, (int)$u['id']);
    } else {
        $notifRepo->deleteOne($id, (int)$u['id']);
    }
}

json_out(['ok' => true]);
