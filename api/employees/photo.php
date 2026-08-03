<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

$u = require_login_json();
$users = new UserRepository();

// 'view' is a plain GET link (an <img> src), everything else is JSON POST.
$action = $_GET['action'] ?? (request_body()['action'] ?? '');

if ($action === 'view') {
    $id = (int)($_GET['id'] ?? 0);
    $employee = $users->findActiveById($id);
    if (!$employee || !$employee['photo_filename']) {
        http_response_code(404);
        exit('No photo.');
    }
    $path = employee_photo_upload_dir() . $employee['photo_filename'];
    if (!is_file($path)) {
        http_response_code(404);
        exit('File missing.');
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="photo.' . $ext . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Everything below is admin-only — only admins may set or remove a staff photo.
if ($u['role'] !== 'admin') {
    json_error('Not permitted.', 403);
}

$body = request_body();

if ($action === 'upload') {
    $id = (int)($body['id'] ?? ($_POST['id'] ?? 0));
    $employee = $users->findActiveById($id);
    if (!$employee) {
        json_error('Employee not found.', 404);
    }
    if (empty($_FILES['file'])) {
        json_error('No file uploaded or upload failed.');
    }
    if ($_FILES['file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['file']['error'] === UPLOAD_ERR_FORM_SIZE) {
        json_error('Image is too large (5MB max).');
    }
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_error('No file uploaded or upload failed.');
    }
    $file = $_FILES['file'];
    if ($file['size'] > employee_photo_max_upload_bytes()) {
        json_error('Image is too large (5MB max).');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, allowed_photo_extensions(), true)) {
        json_error('Only PNG or JPG images are allowed.');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, allowed_photo_mime_types(), true)) {
        json_error('Only PNG or JPG images are allowed.');
    }

    $dir = employee_photo_upload_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $storedFilename)) {
        json_error('Could not save the image.', 500);
    }

    $oldFilename = $employee['photo_filename'];
    $users->setPhoto($id, $storedFilename);
    if ($oldFilename && is_file($dir . $oldFilename)) {
        unlink($dir . $oldFilename);
    }
    if ($id === (int)$u['id']) {
        $_SESSION['user']['has_photo'] = true;
    }

    json_out(['ok' => true, 'photo_url' => 'api/employees/photo.php?action=view&id=' . $id . '&v=' . time()]);
} elseif ($action === 'remove') {
    $id = (int)($body['id'] ?? 0);
    $employee = $users->findActiveById($id);
    if (!$employee) {
        json_error('Employee not found.', 404);
    }
    if ($employee['photo_filename']) {
        $path = employee_photo_upload_dir() . $employee['photo_filename'];
        if (is_file($path)) {
            unlink($path);
        }
    }
    $users->setPhoto($id, null);
    if ($id === (int)$u['id']) {
        $_SESSION['user']['has_photo'] = false;
    }
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
