<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_login_json();

$q = trim($_GET['q'] ?? '');
$rolesParam = $_GET['roles'] ?? '';
$rolesOnly = $rolesParam !== '' ? array_filter(explode(',', $rolesParam)) : null;
$department = trim($_GET['department'] ?? '');

$projectScope = null;
if (!empty($_GET['project_id']) && in_array($_GET['mode'] ?? '', ['members', 'available'], true)) {
    $projectScope = ['project_id' => (int)$_GET['project_id'], 'mode' => $_GET['mode']];
}

if ($q === '') {
    json_out(['results' => []]);
}

$repo = new UserRepository();
$rows = $repo->search($q, 8, $rolesOnly ?: null, $projectScope, $department !== '' ? $department : null);

$results = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'name' => $r['name'],
    'code' => $r['employee_code'],
    'role' => ucfirst($r['role']),
], $rows);

json_out(['results' => $results]);
