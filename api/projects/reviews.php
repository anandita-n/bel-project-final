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
// Commenting on a fellow member (or the manager) isn't a manage-only action — any current
// project member/manager/admin with real access to the workspace can do it, same as the manager
// always could. hasFullAccess() is exactly "admin, this project's manager, or an actual
// project_members row", which is the right membership check here.
$hasAccess = $projects->hasFullAccess($project, $u);

/** True for an actual project_members row OR the project's manager (who isn't a members row). */
function is_valid_comment_target(ProjectRepository $projects, int $projectId, array $project, int $userId): bool {
    return $userId === (int)$project['manager_id'] || $projects->isMember($projectId, $userId);
}

if ($action === 'create') {
    if (!$hasAccess) {
        json_error('Not permitted to leave comments on this project.', 403);
    }
    require_project_active($project);
    $userId = (int)($body['user_id'] ?? 0);
    $comment = trim($body['comment'] ?? '');

    if (!$userId) {
        json_error('Please select a team member.');
    }
    if ($comment === '') {
        json_error('Comment cannot be empty.');
    }
    if (!is_valid_comment_target($projects, $projectId, $project, $userId)) {
        json_error('That person is not part of this project.');
    }

    $reviews->create($projectId, $userId, (int)$u['id'], $comment);
    json_out(['ok' => true]);
} elseif ($action === 'list_for_member') {
    $userId = (int)($body['user_id'] ?? 0);
    if (!$userId) {
        json_error('Missing employee id.');
    }
    // Looking at your own card shows every comment anyone left about you — that's the whole
    // point of being notified about it. Looking at someone else's card only shows the comments
    // *you* wrote about them (your own working notes), not everyone's.
    if ($userId === (int)$u['id']) {
        $rows = $reviews->forProjectMember($projectId, $userId);
        json_out(['ok' => true, 'reviews' => array_map(fn($r) => [
            'id' => (int)$r['id'],
            'comment' => $r['comment'],
            'created_at' => $r['created_at'],
            'author_name' => $r['author_name'],
        ], $rows)]);
    }
    if (!$hasAccess) {
        json_error('Not permitted to view these comments.', 403);
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
