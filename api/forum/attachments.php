<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ForumRepository;

const FORUM_MAX_UPLOAD_BYTES = 10 * 1024 * 1024; // 10MB
const FORUM_ALLOWED_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'txt', 'csv', 'zip'];
const FORUM_ALLOWED_MIME_TYPES = [
    'application/pdf', 'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain', 'text/csv', 'image/png', 'image/jpeg', 'image/gif',
    'application/zip', 'application/x-zip-compressed',
];

$u = require_login_json();
$repo = new ForumRepository();

// 'download' is a plain GET link (browser navigation), everything else is JSON POST.
$action = $_GET['action'] ?? (request_body()['action'] ?? '');

function render_forum_attachment(array $a): array {
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
    $attachment = $repo->findAnswerAttachment($id);
    if (!$attachment) {
        http_response_code(404);
        exit('Attachment not found.');
    }
    $path = forum_attachment_upload_dir() . $attachment['stored_filename'];
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

if ($action === 'list_for_answer') {
    $answerId = (int)($body['answer_id'] ?? 0);
    json_out(['ok' => true, 'attachments' => array_map('render_forum_attachment', $repo->attachmentsForAnswer($answerId))]);
} elseif ($action === 'upload') {
    $answerId = (int)($body['answer_id'] ?? 0);
    $answer = $repo->findAnswer($answerId);
    if (!$answer) {
        json_error('Answer not found.', 404);
    }
    if ($u['role'] !== 'admin' && (int)$answer['user_id'] !== (int)$u['id']) {
        json_error('Only the answer\'s author can add attachments to it.', 403);
    }
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_error('No file uploaded or upload failed.');
    }
    $file = $_FILES['file'];
    if ($file['size'] > FORUM_MAX_UPLOAD_BYTES) {
        json_error('File is too large (10MB max).');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($ext, FORUM_ALLOWED_EXTENSIONS, true) || !in_array($mime, FORUM_ALLOWED_MIME_TYPES, true)) {
        json_error('File type not allowed.');
    }

    $dir = forum_attachment_upload_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $storedFilename)) {
        json_error('Could not save the file.', 500);
    }

    $attachmentId = $repo->createAnswerAttachment($answerId, (int)$u['id'], $file['name'], $storedFilename, (int)$file['size'], $mime ?: null);
    $created = $repo->findAnswerAttachment($attachmentId);
    $created['uploader_name'] = $u['name'];
    json_out(['ok' => true, 'attachment' => render_forum_attachment($created)]);
} elseif ($action === 'delete') {
    $id = (int)($body['id'] ?? 0);
    $attachment = $repo->findAnswerAttachment($id);
    if (!$attachment) {
        json_error('Attachment not found.', 404);
    }
    if ($u['role'] !== 'admin' && (int)$attachment['user_id'] !== (int)$u['id']) {
        json_error('Not permitted to remove this attachment.', 403);
    }
    $path = forum_attachment_upload_dir() . $attachment['stored_filename'];
    $repo->deleteAnswerAttachment($id);
    if (is_file($path)) {
        unlink($path);
    }
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
