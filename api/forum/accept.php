<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;

$u = require_login_json();

$body = request_body();
$action = $body['action'] ?? 'accept';
$questionId = (int)($body['question_id'] ?? 0);

if (!$questionId) {
    json_error('Missing question id.');
}

$repo = new ForumRepository();

if ($action === 'unaccept') {
    $ok = $repo->unacceptAnswer($questionId, (int)$u['id']);
    if (!$ok) {
        json_error('Only the question author can unaccept an answer.', 403);
    }
    json_out(['ok' => true]);
}

$answerId = (int)($body['answer_id'] ?? 0);
if (!$answerId) {
    json_error('Missing answer id.');
}
$ok = $repo->acceptAnswer($questionId, $answerId, (int)$u['id']);
if (!$ok) {
    json_error('Only the question author can accept an answer.', 403);
}
json_out(['ok' => true]);
