<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\AssetRepository;
use App\Repositories\UserRepository;

require_role_json(['admin']);

$body = request_body();
$id = (int)($body['id'] ?? 0);
$userId = !empty($body['user_id']) ? (int)$body['user_id'] : null;

if (!$id) {
    json_error('Missing asset id.');
}

$assets = new AssetRepository();
$asset = $assets->findById($id);
if (!$asset) {
    json_error('Asset not found.', 404);
}

if ($userId) {
    $employee = (new UserRepository())->findActiveById($userId);
    if (!$employee) {
        json_error('Employee not found.', 404);
    }
}

$assets->assign($id, $userId);

json_out(['ok' => true, 'asset' => $assets->findById($id)]);
