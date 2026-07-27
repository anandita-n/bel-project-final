<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;

$u = require_login_json();
$projects = new ProjectRepository();
$tasks = new TaskRepository();

$body = request_body();
$action = $body['action'] ?? '';
$projectId = (int)($body['project_id'] ?? 0);

$project = $projectId ? $projects->findById($projectId) : null;
if (!$project) {
    json_error('Project not found.', 404);
}

$canManage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];

function render_task(array $t): array
{
    return [
        'id' => (int)$t['id'],
        'title' => $t['title'],
        'description' => $t['description'],
        'status' => $t['status'],
        'priority' => $t['priority'],
        'start_date' => $t['start_date'],
        'due_date' => $t['due_date'],
        'assigned_to' => $t['assigned_to'] ? (int)$t['assigned_to'] : null,
        'assignee_name' => $t['assignee_name'] ?? null,
        'assignee_role' => $t['assignee_role'] ?? null,
    ];
}

if ($action === 'create') {
    if (!$canManage) {
        json_error('Not permitted to add tasks on this project.', 403);
    }
    $title = trim($body['title'] ?? '');
    if ($title === '') {
        json_error('Task title is required.');
    }
    $priority = in_array($body['priority'] ?? '', ['low', 'medium', 'high'], true) ? $body['priority'] : 'medium';

    $taskId = $tasks->create([
        'project_id' => $projectId,
        'title' => $title,
        'description' => trim($body['description'] ?? ''),
        'assigned_to' => $body['assigned_to'] ?? null,
        'priority' => $priority,
        'start_date' => $body['start_date'] ?? null,
        'due_date' => $body['due_date'] ?? null,
        'created_by' => $u['id'],
    ]);

    json_out(['ok' => true, 'task' => render_task($tasks->findWithAssignee($taskId))]);
} elseif ($action === 'update') {
    if (!$canManage) {
        json_error('Not permitted to edit tasks on this project.', 403);
    }
    $taskId = (int)($body['task_id'] ?? 0);
    $task = $tasks->find($taskId, $projectId);
    if (!$task) {
        json_error('Task not found.', 404);
    }
    $title = trim($body['title'] ?? '');
    if ($title === '') {
        json_error('Task title is required.');
    }
    $priority = in_array($body['priority'] ?? '', ['low', 'medium', 'high'], true) ? $body['priority'] : 'medium';

    $tasks->update($taskId, $projectId, [
        'title' => $title,
        'description' => trim($body['description'] ?? ''),
        'assigned_to' => $body['assigned_to'] ?? null,
        'priority' => $priority,
        'start_date' => $body['start_date'] ?? null,
        'due_date' => $body['due_date'] ?? null,
    ]);

    json_out(['ok' => true, 'task' => render_task($tasks->findWithAssignee($taskId))]);
} elseif ($action === 'update_status') {
    $taskId = (int)($body['task_id'] ?? 0);
    $status = $body['status'] ?? '';
    if (!array_key_exists($status, \App\Repositories\TaskRepository::STATUSES)) {
        json_error('Invalid status.');
    }
    $task = $tasks->find($taskId, $projectId);
    if (!$task) {
        json_error('Task not found.', 404);
    }
    if (!$canManage && (int)$task['assigned_to'] !== (int)$u['id']) {
        json_error('Not permitted to update this task.', 403);
    }
    $tasks->updateStatus($taskId, $status);
    json_out(['ok' => true, 'task' => render_task($tasks->findWithAssignee($taskId))]);
} elseif ($action === 'delete') {
    if (!$canManage) {
        json_error('Not permitted to delete tasks on this project.', 403);
    }
    $taskId = (int)($body['task_id'] ?? 0);
    $tasks->delete($taskId, $projectId);
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
