<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\AssetRepository;

require_role_json(['admin']);

$body = request_body();
$id = (int)($body['id'] ?? 0);
$status = $body['status'] ?? '';

if (!$id) {
    json_error('Missing asset id.');
}
if (!array_key_exists($status, AssetRepository::STATUSES)) {
    json_error('Invalid status.');
}

$assets = new AssetRepository();
$asset = $assets->findById($id);
if (!$asset) {
    json_error('Asset not found.', 404);
}

$assets->setStatus($id, $status);

json_out(['ok' => true, 'asset' => $assets->findById($id)]);
