<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

$u = require_role_json(['admin']);
$users = new UserRepository();

$body = request_body();
$id = (int)($body['id'] ?? 0);
$newPassword = $body['new_password'] ?? '';

$employee = $users->findActiveById($id);
if (!$employee) {
    json_error('Employee not found.', 404);
}

if (!is_valid_password($newPassword)) {
    json_error('Password must be 8–32 characters and include an uppercase letter, a lowercase letter, a number, a special character, and no spaces.');
}

$users->updatePassword($id, $newPassword, true);

json_out(['ok' => true]);
