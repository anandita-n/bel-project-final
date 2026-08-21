<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\MemberReviewRepository;
use App\Repositories\NotificationRepository;

$u = require_login_json();

$body = request_body();
$id = (int)($body['id'] ?? 0);
$type = $body['type'] ?? '';

if (!$id || !in_array($type, ['review', 'notification'], true)) {
    json_error('Invalid notification.');
}

if ($type === 'review') {
    (new MemberReviewRepository())->markOneRead($id, (int)$u['id']);
} else {
    (new NotificationRepository())->markOneRead($id, (int)$u['id']);
}

json_out(['ok' => true]);
