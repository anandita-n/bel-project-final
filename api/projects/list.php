<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;

$u = require_login_json();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$repo = new ProjectRepository();
$rows = $repo->listForUser($u, $q, $status);

$results = array_map(fn($p) => [
    'id' => (int)$p['id'],
    'project_code' => $p['project_code'],
    'name' => $p['name'],
    'manager_id' => (int)$p['manager_id'],
    'manager_name' => $p['manager_name'],
    'manager_role' => $p['manager_role'],
    'manager_has_photo' => !empty($p['manager_photo_filename']),
    'member_count' => (int)$p['member_count'],
    'status' => $p['status'],
], $rows);

json_out(['results' => $results, 'query' => $q]);
