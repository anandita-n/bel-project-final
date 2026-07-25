<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;

$u = require_login_json();

$q = trim($_GET['q'] ?? '');
$repo = new ProjectRepository();
$rows = $repo->listForUser($u, $q);

$results = array_map(fn($p) => [
    'id' => (int)$p['id'],
    'project_code' => $p['project_code'],
    'name' => $p['name'],
    'manager_name' => $p['manager_name'],
    'member_count' => (int)$p['member_count'],
    'status' => $p['status'],
], $rows);

json_out(['results' => $results, 'query' => $q]);
