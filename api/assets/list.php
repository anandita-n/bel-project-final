<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\AssetRepository;

$u = require_login_json();

$q = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

// Employees can only ever see their own assigned assets, regardless of any employeeId param sent.
$employeeId = $u['role'] === 'employee' ? (int)$u['id'] : null;

$repo = new AssetRepository();
$rows = $repo->search($q, $category, $status, $employeeId);

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

json_out(['results' => $results]);
