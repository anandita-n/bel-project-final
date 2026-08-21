<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_role_json(['admin']);

$repo = new UserRepository();
$rows = $repo->departmentSummary();

$results = array_map(fn($r) => [
    'department' => $r['department'],
    'count' => (int)$r['employee_count'],
], $rows);

json_out(['results' => $results]);
