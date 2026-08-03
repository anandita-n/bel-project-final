<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\ProjectDocumentRepository;

$u = require_login_json();
$projects = new ProjectRepository();
$documents = new ProjectDocumentRepository();

// 'download' is a plain GET link (browser navigation), everything else is JSON POST.
$action = $_GET['action'] ?? (request_body()['action'] ?? '');

function render_document(array $d): array {
    return [
        'id' => (int)$d['id'],
        'original_filename' => $d['original_filename'],
        'size_bytes' => (int)$d['size_bytes'],
        'uploader_name' => $d['uploader_name'],
        'uploader_id' => (int)$d['user_id'],
        'created_at' => $d['created_at'],
    ];
}

if ($action === 'download') {
    $id = (int)($_GET['id'] ?? 0);
    $document = $documents->find($id);
    if (!$document) {
        http_response_code(404);
        exit('Document not found.');
    }
    if (!$projects->userHasAccess((int)$document['project_id'], $u)) {
        http_response_code(403);
        exit('Not permitted.');
    }
    $path = project_document_upload_dir() . $document['stored_filename'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('File missing.');
    }
    header('Content-Type: ' . ($document['mime_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . basename($document['original_filename']) . '"');
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

if ($action === 'list') {
    json_out(['ok' => true, 'documents' => array_map('render_document', $documents->forProject($projectId))]);
} elseif ($action === 'upload') {
    if (!$canManage) {
        json_error('Not permitted to upload documents for this project.', 403);
    }
    if (empty($_FILES['file'])) {
        json_error('No file uploaded or upload failed.');
    }
    if ($_FILES['file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['file']['error'] === UPLOAD_ERR_FORM_SIZE) {
        json_error('File is too large (50MB max).');
    }
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_error('No file uploaded or upload failed.');
    }
    $file = $_FILES['file'];
    if ($file['size'] > project_document_max_upload_bytes()) {
        json_error('File is too large (50MB max).');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, allowed_document_extensions(), true)) {
        json_error('Only PDF, Word, Excel, PowerPoint, TXT, CSV, PNG, JPG, or ZIP files are allowed.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, allowed_document_mime_types(), true)) {
        json_error('Only PDF, Word, Excel, PowerPoint, TXT, CSV, PNG, JPG, or ZIP files are allowed.');
    }

    $dir = project_document_upload_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $storedFilename)) {
        json_error('Could not save the file.', 500);
    }

    $documentId = $documents->create($projectId, (int)$u['id'], $file['name'], $storedFilename, (int)$file['size'], $mime);
    $created = $documents->find($documentId);
    $created['uploader_name'] = $u['name'];
    json_out(['ok' => true, 'document' => render_document($created)]);
} elseif ($action === 'delete') {
    if (!$canManage) {
        json_error('Not permitted to remove documents for this project.', 403);
    }
    $id = (int)($body['id'] ?? 0);
    $document = $documents->find($id);
    if (!$document || (int)$document['project_id'] !== $projectId) {
        json_error('Document not found.', 404);
    }
    $path = project_document_upload_dir() . $document['stored_filename'];
    $documents->delete($id);
    if (is_file($path)) {
        unlink($path);
    }
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
