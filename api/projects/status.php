<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;

$u = require_login_json();
$projects = new ProjectRepository();

$body = request_body();
$action = $body['action'] ?? '';
$projectId = (int)($body['project_id'] ?? 0);

$project = $projectId ? $projects->findById($projectId) : null;
if (!$project) {
    json_error('Project not found.', 404);
}

$canManage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];
if (!$canManage) {
    json_error('Not permitted to manage this project.', 403);
}

if ($action === 'update') {
    require_project_active($project);
    $status = $body['status'] ?? '';
    if (!in_array($status, ['active', 'on_hold', 'completed'], true)) {
        json_error('Invalid status.');
    }
    $projects->updateStatus($projectId, $status);

    $statusLabel = ucfirst(str_replace('_', ' ', $status));
    foreach ($projects->members($projectId) as $member) {
        notify_project_event((int)$member['id'], (int)$u['id'], $projectId, null, 'project_updated', $u['name'] . ' changed "' . $project['name'] . '" status to ' . $statusLabel);
    }

    json_out(['ok' => true, 'status' => $status]);
} elseif ($action === 'update_details') {
    require_project_active($project);
    $name = trim($body['name'] ?? '');
    $description = trim($body['description'] ?? '');
    $department = trim($body['department'] ?? '');
    $dueDate = trim($body['due_date'] ?? '');

    if ($name === '') {
        json_error('Project name is required.');
    }

    $projects->updateDetails($projectId, $name, $description, $department, $dueDate !== '' ? $dueDate : null);

    foreach ($projects->members($projectId) as $member) {
        notify_project_event((int)$member['id'], (int)$u['id'], $projectId, null, 'project_updated', $u['name'] . ' updated the details of "' . $name . '"');
    }

    json_out(['ok' => true, 'name' => $name, 'description' => $description, 'department' => $department, 'due_date' => $dueDate !== '' ? $dueDate : null]);
} elseif ($action === 'reassign_manager') {
    if ($u['role'] !== 'admin') {
        json_error('Only an admin can reassign a project\'s manager.', 403);
    }
    require_project_active($project);
    $newManagerId = (int)($body['manager_id'] ?? 0);
    $newManager = $newManagerId ? (new UserRepository())->findById($newManagerId) : null;
    if (!$newManager || !$newManager['is_active']) {
        json_error('Selected employee not found.', 404);
    }
    if (!in_array($newManager['role'], ['admin', 'manager'], true)) {
        json_error('Only an admin or manager can be assigned as project manager.');
    }

    $projects->updateManager($projectId, $newManagerId);

    foreach ($projects->members($projectId) as $member) {
        notify_project_event((int)$member['id'], (int)$u['id'], $projectId, null, 'project_updated', $u['name'] . ' reassigned "' . $project['name'] . '" to ' . $newManager['name'] . ' as manager');
    }
    if ((int)$newManagerId !== (int)$u['id']) {
        notify_project_event($newManagerId, (int)$u['id'], $projectId, null, 'project_updated', $u['name'] . ' made you the manager of "' . $project['name'] . '"');
    }

    json_out(['ok' => true, 'manager_id' => $newManagerId, 'manager_name' => $newManager['name']]);
} elseif ($action === 'archive') {
    if (!empty($project['archived_at'])) {
        json_error('This project is already archived.');
    }
    $reason = trim($body['reason'] ?? '');
    $projects->archive($projectId, (int)$u['id'], $reason !== '' ? $reason : null);

    foreach ($projects->members($projectId) as $member) {
        notify_project_event((int)$member['id'], (int)$u['id'], $projectId, null, 'project_updated', $u['name'] . ' archived "' . $project['name'] . '"');
    }

    json_out(['ok' => true, 'archived_at' => date('Y-m-d H:i:s')]);
} elseif ($action === 'restore') {
    if (empty($project['archived_at'])) {
        json_error('This project is not archived.');
    }
    $projects->restore($projectId);

    foreach ($projects->members($projectId) as $member) {
        notify_project_event((int)$member['id'], (int)$u['id'], $projectId, null, 'project_updated', $u['name'] . ' restored "' . $project['name'] . '" — it\'s active again');
    }

    json_out(['ok' => true]);
} elseif ($action === 'delete') {
    if ($u['role'] !== 'admin') {
        json_error('Only an admin can permanently delete a project.', 403);
    }

    $activity = $projects->activityCounts($projectId);
    if (array_sum($activity) > 0) {
        json_error('This project has tasks, defects, documents, or members and cannot be permanently deleted. Archive it instead to preserve its history.');
    }

    $documentsRepo = new \App\Repositories\ProjectDocumentRepository();
    foreach ($documentsRepo->forProject($projectId) as $doc) {
        $path = project_document_upload_dir() . $doc['stored_filename'];
        if (is_file($path)) {
            unlink($path);
        }
    }

    $tasksRepo = new \App\Repositories\TaskRepository();
    $attachmentsRepo = new \App\Repositories\TaskAttachmentRepository();
    foreach ($tasksRepo->listForProject($projectId) as $t) {
        foreach ($attachmentsRepo->forTask((int)$t['id']) as $attachment) {
            $path = attachment_upload_dir() . $attachment['stored_filename'];
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    $projects->delete($projectId);
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
