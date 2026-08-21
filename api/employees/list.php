<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_role_json(['admin']);

$q = trim($_GET['q'] ?? '');
$department = trim($_GET['department'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$repo = new UserRepository();
$rows = $repo->listActiveWithManager($q, $department, $page, $perPage);
$total = $repo->countActiveWithManager($q, $department);

$results = array_map(fn($e) => [
    'id' => (int)$e['id'],
    'name' => $e['name'],
    'employee_code' => $e['employee_code'],
    'email' => $e['email'],
    'role' => $e['role'],
    'department' => $e['department'],
    'telephone' => $e['telephone'],
    'manager_id' => $e['manager_id'] ? (int)$e['manager_id'] : null,
    'manager_name' => $e['manager_name'],
    'has_photo' => !empty($e['photo_filename']),
], $rows);

json_out([
    'results' => $results,
    'query' => $q,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => max(1, (int)ceil($total / $perPage)),
]);
