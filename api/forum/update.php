<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;

$u = require_login_json();

$body = request_body();
$questionId = (int)($body['question_id'] ?? 0);
$title = trim($body['title'] ?? '');
$questionBody = trim($body['body'] ?? '');
$tagsRaw = trim($body['tags'] ?? '');
$tags = $tagsRaw === '' ? [] : array_filter(array_map('trim', explode(',', $tagsRaw)));

if (!$questionId) {
    json_error('Missing question id.');
}
if ($title === '' || $questionBody === '') {
    json_error('Title and body are required.');
}

$repo = new ForumRepository();
$ok = $repo->updateQuestion($questionId, (int)$u['id'], $title, $questionBody, $tags);
if (!$ok) {
    json_error('Only the question author can edit this question.', 403);
}

json_out(['ok' => true]);
