<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\TaskAttachmentRepository;

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10MB
const ALLOWED_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'csv', 'zip'];
const ALLOWED_MIME_TYPES = [
    'application/pdf', 'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain', 'text/csv', 'image/png', 'image/jpeg', 'image/gif',
    'application/zip', 'application/x-zip-compressed',
];

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
    $attachmentProject = $task ? $projects->findById((int)$task['project_id']) : null;
    if (!$task || !$attachmentProject || !$projects->hasFullAccess($attachmentProject, $u)) {
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
if (!$projects->hasFullAccess($project, $u)) {
    json_error('Not permitted.', 403);
}
$canManage = $u['role'] === 'admin' || (int)$u['id'] === (int)$project['manager_id'];

if ($action === 'list_for_task') {
    $taskId = (int)($body['task_id'] ?? 0);
    if (!$tasks->find($taskId, $projectId)) {
        json_error('Task not found.', 404);
    }
    json_out(['ok' => true, 'attachments' => array_map('render_attachment', $attachments->forTask($taskId))]);
} elseif ($action === 'upload') {
    require_project_active($project);
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
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($ext, ALLOWED_EXTENSIONS, true) || !in_array($mime, ALLOWED_MIME_TYPES, true)) {
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

    $attachmentId = $attachments->create($taskId, (int)$u['id'], $file['name'], $storedFilename, (int)$file['size'], $mime ?: null);

    if (!empty($task['assigned_to'])) {
        notify_project_event((int)$task['assigned_to'], (int)$u['id'], $projectId, $taskId, 'attachment_uploaded', $u['name'] . ' uploaded "' . $file['name'] . '" to "' . $task['title'] . '"');
    }

    $created = $attachments->find($attachmentId);
    $created['uploader_name'] = $u['name'];
    json_out(['ok' => true, 'attachment' => render_attachment($created)]);
} elseif ($action === 'delete') {
    require_project_active($project);
    $id = (int)($body['id'] ?? 0);
    $attachment = $attachments->find($id);
    if (!$attachment) {
        json_error('Attachment not found.', 404);
    }
    // Re-derive the attachment's real project from its task rather than trusting the
    // caller-supplied project_id, so a manager of one project can't delete another's file.
    $attachmentTask = $tasks->find((int)$attachment['task_id'], $projectId);
    if (!$attachmentTask) {
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
