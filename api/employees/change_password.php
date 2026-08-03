<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

$u = require_login_json();
$users = new UserRepository();

$body = request_body();
$currentPassword = $body['current_password'] ?? '';
$newPassword = $body['new_password'] ?? '';
$confirmPassword = $body['confirm_password'] ?? '';

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    json_error('All fields are required.');
}

$user = $users->findById((int)$u['id']);
if (!$user || !password_verify($currentPassword, $user['password'])) {
    json_error('Current password is incorrect.');
}

if ($newPassword !== $confirmPassword) {
    json_error('New password and confirmation do not match.');
}

if (!is_valid_password($newPassword)) {
    json_error('Password must be 8–32 characters and include an uppercase letter, a lowercase letter, a number, a special character, and no spaces.');
}

$users->updatePassword((int)$u['id'], $newPassword, false);
$_SESSION['user']['must_change_password'] = false;

json_out(['ok' => true]);
