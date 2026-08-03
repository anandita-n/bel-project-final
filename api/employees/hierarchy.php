<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_login_json();

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    json_out(['found' => false]);
}

$repo = new UserRepository();
$result = $repo->searchOneWithHierarchy($q);
if (!$result) {
    json_out(['found' => false]);
}

$employee = $result['employee'];
$manager = $result['manager'];

json_out([
    'found' => true,
    'employee' => [
        'id' => (int)$employee['id'],
        'name' => $employee['name'],
        'employee_code' => $employee['employee_code'],
        'role' => $employee['role'],
        'department' => $employee['department'],
        'manager_id' => $employee['manager_id'] ? (int)$employee['manager_id'] : null,
        'telephone' => $employee['telephone'],
        'email' => $employee['email'],
        'stream' => $employee['stream'],
        'user_group' => $employee['user_group'],
        'has_photo' => !empty($employee['photo_filename']),
    ],
    'manager' => $manager ? [
        'id' => (int)$manager['id'],
        'name' => $manager['name'],
        'employee_code' => $manager['employee_code'],
        'role' => $manager['role'],
        'department' => $manager['department'],
        'manager_id' => $manager['manager_id'] ? (int)$manager['manager_id'] : null,
        'telephone' => $manager['telephone'],
        'has_photo' => !empty($manager['photo_filename']),
    ] : null,
    'direct_reports' => array_map(fn($r) => [
        'id' => (int)$r['id'],
        'name' => $r['name'],
        'employee_code' => $r['employee_code'],
        'role' => $r['role'],
        'department' => $r['department'],
        'manager_id' => $r['manager_id'] ? (int)$r['manager_id'] : null,
        'telephone' => $r['telephone'],
        'has_photo' => !empty($r['photo_filename']),
    ], $result['direct_reports']),
]);
