<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;

$u = require_login_json();
$projects = new ProjectRepository();
$users = new UserRepository();

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

if ($action === 'add') {
    $userId = (int)($body['user_id'] ?? 0);
    $role = trim($body['role_in_project'] ?? '') ?: 'Team Member';
    if (!$userId) {
        json_error('Please select an employee.');
    }
    $employee = $users->findActiveById($userId);
    if (!$employee) {
        json_error('Employee not found.', 404);
    }
    $projects->addMember($projectId, $userId, $role);

    json_out([
        'ok' => true,
        'member' => [
            'id' => $employee['id'],
            'name' => $employee['name'],
            'email' => $employee['email'],
            'system_role' => $employee['role'],
            'employee_code' => $employee['employee_code'],
            'department' => $employee['department'],
            'role_in_project' => $role,
        ],
    ]);
} elseif ($action === 'remove') {
    $userId = (int)($body['user_id'] ?? 0);
    if (!$userId) {
        json_error('Missing employee id.');
    }
    $projects->removeMember($projectId, $userId);
    json_out(['ok' => true]);
} else {
    json_error('Unknown action.');
}
