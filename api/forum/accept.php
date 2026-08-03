<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;

$u = require_login_json();

$body = request_body();
$questionId = (int)($body['question_id'] ?? 0);
$answerId = (int)($body['answer_id'] ?? 0);

if (!$questionId || !$answerId) {
    json_error('Missing question or answer id.');
}

$repo = new ForumRepository();
$ok = $repo->acceptAnswer($questionId, $answerId, (int)$u['id']);

if (!$ok) {
    json_error('Only the question author can accept an answer.', 403);
}

json_out(['ok' => true]);
