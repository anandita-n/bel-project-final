<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;

$u = require_login_json();

$body = request_body();
$type = $body['type'] ?? '';
$id = (int)($body['id'] ?? 0);

if (!in_array($type, ['question', 'answer', 'question_comment', 'answer_comment'], true) || !$id) {
    json_error('Invalid delete request.');
}

$repo = new ForumRepository();

if ($type === 'question') {
    $question = $repo->findQuestion($id);
    if (!$question) {
        json_error('Question not found.', 404);
    }
    if ($u['role'] !== 'admin' && (int)$question['user_id'] !== (int)$u['id']) {
        json_error('Not permitted to delete this question.', 403);
    }
    $repo->deleteQuestion($id);
} elseif ($type === 'answer') {
    $answer = $repo->findAnswer($id);
    if (!$answer) {
        json_error('Answer not found.', 404);
    }
    if ($u['role'] !== 'admin' && (int)$answer['user_id'] !== (int)$u['id']) {
        json_error('Not permitted to delete this answer.', 403);
    }
    $repo->deleteAnswer($id);
} elseif ($type === 'question_comment') {
    $comment = $repo->findQuestionComment($id);
    if (!$comment) {
        json_error('Comment not found.', 404);
    }
    if ($u['role'] !== 'admin' && (int)$comment['user_id'] !== (int)$u['id']) {
        json_error('Not permitted to delete this comment.', 403);
    }
    $repo->deleteQuestionComment($id);
} else {
    $comment = $repo->findAnswerComment($id);
    if (!$comment) {
        json_error('Comment not found.', 404);
    }
    if ($u['role'] !== 'admin' && (int)$comment['user_id'] !== (int)$u['id']) {
        json_error('Not permitted to delete this comment.', 403);
    }
    $repo->deleteAnswerComment($id);
}

json_out(['ok' => true]);
