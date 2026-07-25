<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_role_json(['admin']);

$body = request_body();
$employee_code = trim($body['employee_code'] ?? '');
$name = trim($body['name'] ?? '');
$email = trim($body['email'] ?? '');
$password = $body['password'] ?? '';
$role = $body['role'] ?? 'employee';
$department = trim($body['department'] ?? '');
$manager_id = !empty($body['manager_id']) ? (int)$body['manager_id'] : null;

$users = new UserRepository();

if ($employee_code === '' || $name === '' || $email === '' || $password === '') {
    json_error('Employee code, name, email and password are required.');
}
if (!in_array($role, ['admin', 'manager', 'employee'], true)) {
    json_error('Invalid role selected.');
}
if ($users->emailOrCodeExists($email, $employee_code)) {
    json_error('An employee with this email or employee code already exists.');
}

$id = $users->create([
    'employee_code' => $employee_code,
    'name' => $name,
    'email' => $email,
    'password' => $password,
    'role' => $role,
    'department' => $department,
    'manager_id' => $manager_id,
]);

$manager = $manager_id ? $users->findById($manager_id) : null;

json_out(['ok' => true, 'employee' => [
    'id' => $id,
    'name' => $name,
    'employee_code' => $employee_code,
    'email' => $email,
    'role' => $role,
    'department' => $department,
    'manager_id' => $manager_id,
    'manager_name' => $manager['name'] ?? null,
]]);
