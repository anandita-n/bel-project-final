<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_role_json(['admin', 'manager']);

$q = trim($_GET['q'] ?? '');
$repo = new UserRepository();
$rows = $repo->listActiveWithManager($q);

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

json_out(['results' => $results, 'query' => $q]);
