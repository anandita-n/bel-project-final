<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_role_json(['admin']);

$body = request_body();
$id = (int)($body['id'] ?? 0);
$name = trim($body['name'] ?? '');
$role = $body['role'] ?? '';
$department = trim($body['department'] ?? '');
$telephone = trim($body['telephone'] ?? '');
$manager_id = !empty($body['manager_id']) ? (int)$body['manager_id'] : null;

if (!$id) {
    json_error('Missing employee id.');
}
if ($name === '') {
    json_error('Name is required.');
}
if (!in_array($role, ['admin', 'manager', 'employee'], true)) {
    json_error('Invalid role selected.');
}
if ($manager_id === $id) {
    json_error('An employee cannot be their own manager.');
}

$users = new UserRepository();
$employee = $users->findActiveById($id);
if (!$employee) {
    json_error('Employee not found.', 404);
}
if ($manager_id !== null && !$users->findActiveById($manager_id)) {
    json_error('Selected manager is not an active employee.');
}

$users->updateProfile($id, $name, $role, $department, $manager_id, $telephone);

$manager = $manager_id ? $users->findById($manager_id) : null;

json_out(['ok' => true, 'employee' => [
    'id' => $id,
    'name' => $name,
    'role' => $role,
    'department' => $department,
    'telephone' => $telephone,
    'manager_name' => $manager['name'] ?? null,
]]);
