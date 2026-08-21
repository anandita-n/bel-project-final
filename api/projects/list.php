<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\ProjectRepository;

$u = require_login_json();

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$department = trim($_GET['department'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$archived = !empty($_GET['archived']);

$repo = new ProjectRepository();
$rows = $repo->listForUser($u, $q, $status, $department, $page, $perPage, $archived);
$total = $repo->countForUser($u, $q, $status, $department, $archived);

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
    'department' => $p['department'],
    'due_date' => $p['due_date'],
    'archived_at' => $p['archived_at'],
    'archived_by' => $p['archived_by'] !== null ? (int)$p['archived_by'] : null,
    'archived_by_name' => $p['archived_by_name'] ?? null,
    'archive_reason' => $p['archive_reason'],
], $rows);

json_out([
    'results' => $results,
    'query' => $q,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => max(1, (int)ceil($total / $perPage)),
]);
