<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TaskCommentRepository;

$u = require_login_json();
$projects = new ProjectRepository();
$tasks = new TaskRepository();
$comments = new TaskCommentRepository();

$body = request_body();
$action = $body['action'] ?? '';
$projectId = (int)($body['project_id'] ?? 0);

$project = $projectId ? $projects->findById($projectId) : null;
if (!$project) {
    json_error('Project not found.', 404);
}
if (!$projects->hasFullAccess($project, $u)) {
    json_error('Not permitted.', 403);
}

function render_comment(array $c): array {
    return [
        'id' => (int)$c['id'],
        'comment' => $c['comment'],
        'created_at' => $c['created_at'],
        'author_id' => (int)$c['user_id'],
        'author_name' => $c['author_name'],
        'author_role' => $c['author_role'],
        'has_photo' => !empty($c['author_photo_filename']),
        'mention_ids' => array_map('intval', $c['mention_ids']),
    ];
}

if ($action === 'list_for_task') {
    $taskId = (int)($body['task_id'] ?? 0);
    if (!$tasks->find($taskId, $projectId)) {
        json_error('Task not found.', 404);
    }
    json_out(['ok' => true, 'comments' => array_map('render_comment', $comments->forTask($taskId))]);
} elseif ($action === 'create') {
    require_project_active($project);
    $taskId = (int)($body['task_id'] ?? 0);
    $task = $tasks->find($taskId, $projectId);
    if (!$task) {
        json_error('Task not found.', 404);
    }
    $text = trim($body['comment'] ?? '');
    if ($text === '') {
        json_error('Comment cannot be empty.');
    }

    // Only allow mentioning actual members/manager of this project.
    $memberIds = array_map(fn($m) => (int)$m['id'], $projects->members($projectId));
    $memberIds[] = (int)$project['manager_id'];
    $requestedMentions = array_map('intval', (array)($body['mention_ids'] ?? []));
    $mentionIds = array_values(array_intersect($requestedMentions, $memberIds));

    $commentId = $comments->create($taskId, (int)$u['id'], $text);
    $comments->setMentions($commentId, $mentionIds);

    $preview = mb_strimwidth($text, 0, 60, '…');
    if (!empty($task['assigned_to'])) {
        notify_project_event((int)$task['assigned_to'], (int)$u['id'], $projectId, $taskId, 'comment_added', $u['name'] . ' commented on "' . $task['title'] . '": ' . $preview);
    }
    foreach ($mentionIds as $mentionedId) {
        notify_project_event($mentionedId, (int)$u['id'], $projectId, $taskId, 'mentioned', $u['name'] . ' mentioned you on "' . $task['title'] . '": ' . $preview);
    }

    $all = $comments->forTask($taskId);
    $created = end($all);
    json_out(['ok' => true, 'comment' => render_comment($created)]);
} else {
    json_error('Unknown action.');
}
