<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\MemberReviewRepository;
use App\Repositories\NotificationRepository;

$u = require_login_json();

(new MemberReviewRepository())->markAllRead((int)$u['id']);
(new NotificationRepository())->markAllRead((int)$u['id']);

json_out(['ok' => true]);
