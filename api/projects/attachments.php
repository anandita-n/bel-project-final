<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TaskAttachmentRepository;

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10MB
const ALLOWED_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'csv', 'zip'];

$u = require_login_json();
$projects = new ProjectRepository();
$tasks = new TaskRepository();
$attachments = new TaskAttachmentRepository();

// 'download' is a plain GET link (browser navigation), everything else is JSON POST.
$action = $_GET['action'] ?? (request_body()['action'] ?? '');

function render_attachment(array $a): array {
    return [
        'id' => (int)$a['id'],
        'original_filename' => $a['original_filename'],
        'size_bytes' => (int)$a['size_bytes'],
        'uploader_name' => $a['uploader_name'],
        'uploader_id' => (int)$a['user_id'],
        'created_at' => $a['created_at'],
    ];
}

if ($action === 'download') {
    $id = (int)($_GET['id'] ?? 0);
    $attachment = $attachments->find($id);
    if (!$attachment) {
        http_response_code(404);
        exit('Attachment not found.');
    }
    // find() is scoped to (task_id, project_id) together, so a mismatched project_id
    // (guessed or wrong) simply fails to find the task rather than leaking it.
    $task = $tasks->find((int)$attachment['task_id'], (int)($_GET['project_id'] ?? 0));
    if (!$task || !$projects->userHasAccess((int)$task['project_id'], $u)) {
        http_response_code(403);
        exit('Not permitted.');
    }
    $path = attachment_upload_dir() . $attachment['stored_filename'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('File missing.');
    }
    header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($attachment['original_filename']) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$body = request_body();
$projectId = (int)($body['project_id'] ?? 0);
$project = $projectId ? $projects->findById($projectId) : null;
if (!$project) {
    json_error('Project not found.', 404);
}
if (!$projects->userHasAccess($projectId, $u)) {
    json_error('Not permitted.', 403);
}
$canManage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];

if ($action === 'list_for_task') {
    $taskId = (int)($body['task_id'] ?? 0);
    json_out(['ok' => true, 'attachments' => array_map('render_attachment', $attachments->forTask($taskId))]);
} elseif ($action === 'upload') {
    $taskId = (int)($body['task_id'] ?? 0);
    $task = $tasks->find($taskId, $projectId);
    if (!$task) {
        json_error('Task not found.', 404);
    }
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_error('No file uploaded or upload failed.');
    }
    $file = $_FILES['file'];
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        json_error('File is too large (10MB max).');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        json_error('File type not allowed.');
    }

    $dir = attachment_upload_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $storedFilename)) {
        json_error('Could not save the file.', 500);
    }

    $attachmentId = $attachments->create($taskId, (int)$u['id'], $file['name'], $storedFilename, (int)$file['size'], $file['type'] ?: null);

    if (!empty($task['assigned_to'])) {
        notify_project_event((int)$task['assigned_to'], (int)$u['id'], $projectId, $taskId, 'attachment_uploaded', $u['name'] . ' uploaded "' . $file['name'] . '" to "' . $task['title'] . '"');
    }

    $created = $attachments->find($attachmentId);
    $created['uploader_name'] = $u['name'];
    json_out(['ok' => true, 'attachment' => render_attachment($created)]);
} elseif ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    $attachment = $attachments->find($id);
    if (!$attachment) {
        json_error('Attachment not found.', 404);
    }
    if (!$canManage && (int)$attachment['user_id'] !== (int)$u['id']) {
        json_error('Not permitted to remove this attachment.', 403);
    }
    $path = attachment_upload_dir() . $attachment['stored_filename'];
    $attachments->delete($id);
    if (is_file($path)) {
        unlink($path);
    }
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
