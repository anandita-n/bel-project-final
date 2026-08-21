<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TaskDependencyRepository;

$u = require_login_json();
$projects = new ProjectRepository();
$tasks = new TaskRepository();
$deps = new TaskDependencyRepository();

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
$canManage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];

if ($action === 'list_for_task') {
    $taskId = (int)($body['task_id'] ?? 0);
    if (!$tasks->find($taskId, $projectId)) {
        json_error('Task not found.', 404);
    }
    json_out(['ok' => true, 'dependencies' => $deps->forTask($taskId)]);
} elseif ($action === 'add') {
    if (!$canManage) {
        json_error('Not permitted to edit dependencies on this project.', 403);
    }
    require_project_active($project);
    $taskId = (int)($body['task_id'] ?? 0);
    $relatedTaskId = (int)($body['related_task_id'] ?? 0);
    $type = $body['type'] ?? '';
    if (!in_array($type, ['blocked_by', 'depends_on', 'related'], true)) {
        json_error('Invalid dependency type.');
    }
    if ($taskId === $relatedTaskId) {
        json_error('A task cannot depend on itself.');
    }
    // Both tasks must belong to this same project — find() is project-scoped, so this
    // also rejects any related_task_id that isn't actually in the project.
    if (!$tasks->find($taskId, $projectId) || !$tasks->find($relatedTaskId, $projectId)) {
        json_error('Both tasks must belong to this project.', 404);
    }
    $deps->add($taskId, $relatedTaskId, $type);
    json_out(['ok' => true, 'dependencies' => $deps->forTask($taskId)]);
} elseif ($action === 'remove') {
    if (!$canManage) {
        json_error('Not permitted to edit dependencies on this project.', 403);
    }
    require_project_active($project);
    $taskId = (int)($body['task_id'] ?? 0);
    $relatedTaskId = (int)($body['related_task_id'] ?? 0);
    $type = $body['type'] ?? '';
    if (!$tasks->find($taskId, $projectId) || !$tasks->find($relatedTaskId, $projectId)) {
        json_error('Both tasks must belong to this project.', 404);
    }
    $deps->remove($taskId, $relatedTaskId, $type);
    json_out(['ok' => true, 'dependencies' => $deps->forTask($taskId)]);
} else {
    json_error('Unknown action.');
}
