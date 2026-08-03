<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;

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
} elseif ($action === 'delete') {
    if ($u['role'] !== 'admin') {
        json_error('Only an admin can delete a project.', 403);
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
