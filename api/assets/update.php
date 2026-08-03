<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\AssetRepository;

require_role_json(['admin']);

$body = request_body();
$id = (int)($body['id'] ?? 0);
$name = trim($body['name'] ?? '');
$category = $body['category'] ?? '';

if (!$id) {
    json_error('Missing asset id.');
}
if ($name === '' || !array_key_exists($category, AssetRepository::CATEGORIES)) {
    json_error('Name and a valid category are required.');
}

$assets = new AssetRepository();
$existing = $assets->findById($id);
if (!$existing) {
    json_error('Asset not found.', 404);
}

$assets->update($id, [
    'name' => $name,
    'category' => $category,
    'serial_number' => trim($body['serial_number'] ?? ''),
    'department' => trim($body['department'] ?? ''),
    'purchase_date' => $body['purchase_date'] ?? '',
    'warranty_expiry' => $body['warranty_expiry'] ?? '',
]);

json_out(['ok' => true, 'asset' => $assets->findById($id)]);
