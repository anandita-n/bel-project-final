<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;

$u = require_login_json();

$body = request_body();
$questionId = (int)($body['question_id'] ?? 0);
$answerBody = trim($body['body'] ?? '');

if (!$questionId || $answerBody === '') {
    json_error('Answer text is required.');
}

$repo = new ForumRepository();
$question = $repo->findQuestion($questionId);
if (!$question) {
    json_error('Question not found.', 404);
}

$answerId = $repo->createAnswer($questionId, (int)$u['id'], $answerBody);

json_out(['ok' => true, 'answer' => [
    'id' => $answerId,
    'body' => $answerBody,
    'author_name' => $u['name'],
    'author_role' => $u['role'],
    'helpful_count' => 0,
    'user_id' => (int)$u['id'],
    'created_at' => date('Y-m-d H:i:s'),
]]);
