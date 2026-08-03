<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;
use App\Repositories\NotificationRepository;

$u = require_login_json();

$body = request_body();
$type = $body['type'] ?? '';
$id = (int)($body['id'] ?? 0);
$text = trim($body['body'] ?? '');

if (!in_array($type, ['question', 'answer'], true) || !$id) {
    json_error('Invalid comment request.');
}
if ($text === '') {
    json_error('Comment text is required.');
}
if (mb_strlen($text) > 500) {
    json_error('Comments are limited to 500 characters.');
}

$repo = new ForumRepository();

if ($type === 'question') {
    $question = $repo->findQuestion($id);
    if (!$question) {
        json_error('Question not found.', 404);
    }
    $commentId = $repo->createQuestionComment($id, (int)$u['id'], $text);
} else {
    $answer = $repo->findAnswer($id);
    if (!$answer) {
        json_error('Answer not found.', 404);
    }
    $commentId = $repo->createAnswerComment($id, (int)$u['id'], $text);

    $question = $repo->findQuestion((int)$answer['question_id']);
    if ($question) {
        $notifRepo = new NotificationRepository();
        $recipients = array_unique([(int)$answer['user_id'], (int)$question['user_id']]);
        foreach ($recipients as $recipientId) {
            $message = $recipientId === (int)$answer['user_id']
                ? $u['name'] . ' commented on your answer in "' . $question['title'] . '"'
                : $u['name'] . ' commented on an answer to your question "' . $question['title'] . '"';
            $notifRepo->create($recipientId, (int)$u['id'], null, null, 'forum_comment', $message, (int)$question['id'], $id);
        }
    }
}

json_out(['ok' => true, 'comment' => [
    'id' => $commentId,
    'body' => $text,
    'author_name' => $u['name'],
    'user_id' => (int)$u['id'],
    'created_at' => date('Y-m-d H:i:s'),
]]);
