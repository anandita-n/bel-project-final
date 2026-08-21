<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;

$u = require_login_json();

$repo = new ProjectRepository();
$rows = $repo->departmentSummaryForUser($u);

$results = array_map(fn($r) => [
    'department' => $r['department'],
    'count' => (int)$r['project_count'],
], $rows);

json_out(['results' => $results]);
