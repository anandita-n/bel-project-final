<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\AssetRepository;

$u = require_role_json(['admin', 'employee']);

$q = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$department = trim($_GET['department'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

// Employees can only ever see their own assigned assets, regardless of any employeeId param sent.
$employeeId = $u['role'] === 'employee' ? (int)$u['id'] : null;

$repo = new AssetRepository();
$rows = $repo->search($q, $category, $status, $employeeId, $department, $page, $perPage);
$total = $repo->countSearch($q, $category, $status, $employeeId, $department);

$results = array_map(fn($a) => [
    'id' => (int)$a['id'],
    'asset_code' => $a['asset_code'],
    'name' => $a['name'],
    'category' => $a['category'],
    'serial_number' => $a['serial_number'],
    'assigned_to' => $a['assigned_to'] ? (int)$a['assigned_to'] : null,
    'assignee_name' => $a['assignee_name'],
    'assignee_code' => $a['assignee_code'],
    'department' => $a['department'],
    'purchase_date' => $a['purchase_date'],
    'warranty_expiry' => $a['warranty_expiry'],
    'status' => $a['status'],
], $rows);

json_out([
    'results' => $results,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'total_pages' => max(1, (int)ceil($total / $perPage)),
]);
