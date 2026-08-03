<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TaskCommentRepository;
use App\Repositories\TaskAttachmentRepository;
use App\Repositories\UserRepository;

$u = require_login_json();
$projects = new ProjectRepository();
$tasks = new TaskRepository();
$commentsRepo = new TaskCommentRepository();
$attachmentsRepo = new TaskAttachmentRepository();
$users = new UserRepository();

/** Rejects assigning a task to anyone who's been deactivated or is an admin. */
function require_active_assignees(UserRepository $users, array $ids): void
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return;
    }
    if (count($users->assignableIdsAmong($ids)) !== count($ids)) {
        json_error('One or more selected assignees are not eligible for task assignment (inactive or admin).');
    }
}

$body = request_body();
$action = $body['action'] ?? '';
$projectId = (int)($body['project_id'] ?? 0);

$project = $projectId ? $projects->findById($projectId) : null;
if (!$project) {
    json_error('Project not found.', 404);
}

$canManage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];

function render_task(array $t, TaskRepository $tasks, TaskCommentRepository $commentsRepo, TaskAttachmentRepository $attachmentsRepo): array
{
    $labels = $tasks->labelsForTasks([$t['id']])[(int)$t['id']] ?? [];
    $subtasks = $tasks->subtasksForTasks([$t['id']])[(int)$t['id']] ?? [];
    $assignees = $tasks->assigneesForTasks([$t['id']])[(int)$t['id']] ?? [];
    $commentCount = $commentsRepo->countsForTasks([$t['id']])[(int)$t['id']] ?? 0;
    $attachmentCount = $attachmentsRepo->countsForTasks([$t['id']])[(int)$t['id']] ?? 0;

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
        'assignees' => $assignees,
        'labels' => $labels,
        'subtasks' => $subtasks,
        'comment_count' => $commentCount,
        'attachment_count' => $attachmentCount,
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

    $assignees = isset($body['assignees']) ? array_map('intval', (array)$body['assignees']) : null;
    require_active_assignees($users, $assignees !== null ? $assignees : (!empty($body['assigned_to']) ? [(int)$body['assigned_to']] : []));

    $taskId = $tasks->create([
        'project_id' => $projectId,
        'title' => $title,
        'description' => trim($body['description'] ?? ''),
        'assigned_to' => $body['assigned_to'] ?? null,
        'assignees' => $assignees,
        'priority' => $priority,
        'start_date' => $body['start_date'] ?? null,
        'due_date' => $body['due_date'] ?? null,
        'created_by' => $u['id'],
    ]);

    $status = $body['status'] ?? '';
    if (array_key_exists($status, \App\Repositories\TaskRepository::STATUSES) && $status !== 'todo') {
        $tasks->updateStatus($taskId, $status);
    }

    $notifyIds = $assignees !== null ? $assignees : (!empty($body['assigned_to']) ? [(int)$body['assigned_to']] : []);
    foreach ($notifyIds as $assigneeId) {
        notify_project_event($assigneeId, (int)$u['id'], $projectId, $taskId, 'task_assigned', $u['name'] . ' assigned you to "' . $title . '"');
    }

    json_out(['ok' => true, 'task' => render_task($tasks->findWithAssignee($taskId), $tasks, $commentsRepo, $attachmentsRepo)]);
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

    $newAssignedTo = $body['assigned_to'] ?? null;
    $newDueDate = $body['due_date'] ?? null;
    $newAssignees = isset($body['assignees']) ? array_map('intval', (array)$body['assignees']) : null;

    $oldAssigneeIds = array_column($tasks->assigneesForTasks([$taskId])[$taskId] ?? [], 'id');
    require_active_assignees($users, $newAssignees !== null ? $newAssignees : ($newAssignedTo ? [(int)$newAssignedTo] : []));

    $tasks->update($taskId, $projectId, [
        'title' => $title,
        'description' => trim($body['description'] ?? ''),
        'assigned_to' => $newAssignedTo,
        'priority' => $priority,
        'start_date' => $body['start_date'] ?? null,
        'due_date' => $newDueDate,
    ]);

    if ($newAssignees !== null) {
        $tasks->setTaskAssignees($taskId, $newAssignees);
        foreach (array_diff($newAssignees, $oldAssigneeIds) as $addedId) {
            notify_project_event($addedId, (int)$u['id'], $projectId, $taskId, 'task_assigned', $u['name'] . ' assigned you to "' . $title . '"');
        }
    } elseif ($newAssignedTo && (int)$newAssignedTo !== (int)$task['assigned_to']) {
        $tasks->setTaskAssignees($taskId, [(int)$newAssignedTo]);
        notify_project_event((int)$newAssignedTo, (int)$u['id'], $projectId, $taskId, 'task_assigned', $u['name'] . ' assigned you to "' . $title . '"');
    }
    if (($newDueDate ?: null) !== ($task['due_date'] ?: null)) {
        foreach ($newAssignees ?? ($newAssignedTo ? [(int)$newAssignedTo] : []) as $notifyId) {
            notify_project_event((int)$notifyId, (int)$u['id'], $projectId, $taskId, 'due_date_changed', $u['name'] . ' changed the due date on "' . $title . '"');
        }
    }

    json_out(['ok' => true, 'task' => render_task($tasks->findWithAssignee($taskId), $tasks, $commentsRepo, $attachmentsRepo)]);
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
    if (!$canManage && !$tasks->isAssignee($taskId, (int)$u['id'])) {
        json_error('Not permitted to update this task.', 403);
    }
    $tasks->updateStatus($taskId, $status);
    if ($status === 'done' && $task['status'] !== 'done') {
        notify_project_event((int)$project['manager_id'], (int)$u['id'], $projectId, $taskId, 'task_completed', $u['name'] . ' completed "' . $task['title'] . '"');
    }
    json_out(['ok' => true, 'task' => render_task($tasks->findWithAssignee($taskId), $tasks, $commentsRepo, $attachmentsRepo)]);
} elseif ($action === 'delete') {
    if (!$canManage) {
        json_error('Not permitted to delete tasks on this project.', 403);
    }
    $taskId = (int)($body['task_id'] ?? 0);
    $tasks->delete($taskId, $projectId);
    json_out(['ok' => true]);
} elseif ($action === 'bulk_delete') {
    if (!$canManage) {
        json_error('Not permitted to delete tasks on this project.', 403);
    }
    $taskIds = array_map('intval', (array)($body['task_ids'] ?? []));
    if (!$taskIds) {
        json_error('No tasks selected.');
    }
    $tasks->bulkDelete($taskIds, $projectId);
    json_out(['ok' => true, 'deleted_ids' => $taskIds]);
} elseif ($action === 'bulk_update') {
    if (!$canManage) {
        json_error('Not permitted to edit tasks on this project.', 403);
    }
    $taskIds = array_map('intval', (array)($body['task_ids'] ?? []));
    if (!$taskIds) {
        json_error('No tasks selected.');
    }
    $fields = [];
    if (isset($body['status']) && $body['status'] !== '') {
        if (!array_key_exists($body['status'], \App\Repositories\TaskRepository::STATUSES)) {
            json_error('Invalid status.');
        }
        $fields['status'] = $body['status'];
    }
    if (isset($body['priority']) && $body['priority'] !== '') {
        if (!in_array($body['priority'], ['low', 'medium', 'high'], true)) {
            json_error('Invalid priority.');
        }
        $fields['priority'] = $body['priority'];
    }
    $bulkAssignTo = null;
    if (array_key_exists('assigned_to', $body) && $body['assigned_to']) {
        $bulkAssignTo = (int)$body['assigned_to'];
        require_active_assignees($users, [$bulkAssignTo]);
    }
    if (!$fields && $bulkAssignTo === null) {
        json_error('No changes to apply.');
    }

    $before = [];
    foreach ($taskIds as $id) {
        $t = $tasks->find($id, $projectId);
        if ($t) {
            $before[$id] = $t;
        }
    }

    if ($fields) {
        $tasks->bulkUpdate($taskIds, $projectId, $fields);
    }
    if ($bulkAssignTo !== null) {
        foreach ($taskIds as $id) {
            $tasks->setTaskAssignees($id, [$bulkAssignTo]);
        }
        $fields['assigned_to'] = $bulkAssignTo;
    }

    $updated = [];
    foreach ($taskIds as $id) {
        $t = $tasks->findWithAssignee($id);
        if (!$t) {
            continue;
        }
        $old = $before[$id] ?? null;
        if ($old) {
            if (!empty($fields['assigned_to']) && (int)$fields['assigned_to'] !== (int)$old['assigned_to']) {
                notify_project_event((int)$fields['assigned_to'], (int)$u['id'], $projectId, $id, 'task_assigned', $u['name'] . ' assigned you to "' . $t['title'] . '"');
            }
            if (($fields['status'] ?? null) === 'done' && $old['status'] !== 'done') {
                notify_project_event((int)$project['manager_id'], (int)$u['id'], $projectId, $id, 'task_completed', $u['name'] . ' completed "' . $t['title'] . '"');
            }
        }
        $updated[] = render_task($t, $tasks, $commentsRepo, $attachmentsRepo);
    }
    json_out(['ok' => true, 'tasks' => $updated]);
} elseif ($action === 'set_labels') {
    if (!$canManage) {
        json_error('Not permitted to edit tasks on this project.', 403);
    }
    $taskId = (int)($body['task_id'] ?? 0);
    $task = $tasks->find($taskId, $projectId);
    if (!$task) {
        json_error('Task not found.', 404);
    }
    $labelIds = array_map('intval', (array)($body['label_ids'] ?? []));
    $tasks->setTaskLabels($taskId, $labelIds);
    json_out(['ok' => true, 'task' => render_task($tasks->findWithAssignee($taskId), $tasks, $commentsRepo, $attachmentsRepo)]);
} elseif ($action === 'add_subtask') {
    $taskId = (int)($body['task_id'] ?? 0);
    $task = $tasks->find($taskId, $projectId);
    if (!$task) {
        json_error('Task not found.', 404);
    }
    if (!$canManage && !$tasks->isAssignee($taskId, (int)$u['id'])) {
        json_error('Not permitted to update this task.', 403);
    }
    $title = trim($body['title'] ?? '');
    if ($title === '') {
        json_error('Subtask title is required.');
    }
    $subtask = $tasks->addSubtask($taskId, $title);
    json_out(['ok' => true, 'subtask' => $subtask]);
} elseif ($action === 'toggle_subtask') {
    $taskId = (int)($body['task_id'] ?? 0);
    $task = $tasks->find($taskId, $projectId);
    if (!$task) {
        json_error('Task not found.', 404);
    }
    if (!$canManage && !$tasks->isAssignee($taskId, (int)$u['id'])) {
        json_error('Not permitted to update this task.', 403);
    }
    $subtaskId = (int)($body['subtask_id'] ?? 0);
    $isDone = !empty($body['is_done']);
    $tasks->toggleSubtask($subtaskId, $taskId, $isDone);
    json_out(['ok' => true]);
} elseif ($action === 'delete_subtask') {
    $taskId = (int)($body['task_id'] ?? 0);
    $task = $tasks->find($taskId, $projectId);
    if (!$task) {
        json_error('Task not found.', 404);
    }
    if (!$canManage && !$tasks->isAssignee($taskId, (int)$u['id'])) {
        json_error('Not permitted to update this task.', 403);
    }
    $subtaskId = (int)($body['subtask_id'] ?? 0);
    $tasks->deleteSubtask($subtaskId, $taskId);
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
