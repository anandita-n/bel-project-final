<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\MemberReviewRepository;

$u = require_login_json();
$projects = new ProjectRepository();
$reviews = new MemberReviewRepository();

$body = request_body();
$action = $body['action'] ?? '';
$projectId = (int)($body['project_id'] ?? 0);

$project = $projectId ? $projects->findById($projectId) : null;
if (!$project) {
    json_error('Project not found.', 404);
}

$canManage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];
if (!$canManage) {
    json_error('Not permitted to leave comments on this project.', 403);
}

if ($action === 'create') {
    $userId = (int)($body['user_id'] ?? 0);
    $comment = trim($body['comment'] ?? '');

    if (!$userId) {
        json_error('Please select a team member.');
    }
    if ($comment === '') {
        json_error('Comment cannot be empty.');
    }
    if (!$projects->isMember($projectId, $userId)) {
        json_error('That person is not a member of this project.');
    }

    $reviews->create($projectId, $userId, (int)$u['id'], $comment);
    json_out(['ok' => true]);
} elseif ($action === 'list_for_member') {
    $userId = (int)($body['user_id'] ?? 0);
    if (!$userId) {
        json_error('Missing employee id.');
    }
    $rows = $reviews->forProjectMemberByAuthor($projectId, $userId, (int)$u['id']);
    json_out(['ok' => true, 'reviews' => array_map(fn($r) => [
        'id' => (int)$r['id'],
        'comment' => $r['comment'],
        'created_at' => $r['created_at'],
    ], $rows)]);
} else {
    json_error('Unknown action.');
}
