<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;

$u = require_login_json();

$body = request_body();
$answerId = (int)($body['answer_id'] ?? 0);

if (!$answerId) {
    json_error('Missing answer id.');
}

$repo = new ForumRepository();
$answer = $repo->findAnswer($answerId);
if (!$answer) {
    json_error('Answer not found.', 404);
}

$helpful = $repo->toggleHelpful((int)$u['id'], $answerId);

json_out(['ok' => true, 'helpful' => $helpful, 'count' => $repo->helpfulCountForAnswer($answerId)]);
