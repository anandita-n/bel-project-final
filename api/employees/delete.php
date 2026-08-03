<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

$u = require_role_json(['admin']);

$body = request_body();
$id = (int)($body['id'] ?? 0);
$action = $body['action'] ?? 'deactivate';

if (!$id) {
    json_error('Missing employee id.');
}
if ($id === (int)$u['id']) {
    json_error('You cannot remove your own account.');
}
if (!in_array($action, ['deactivate', 'hard_delete'], true)) {
    json_error('Invalid delete action.');
}

$users = new UserRepository();

if ($action === 'hard_delete') {
    $counts = $users->linkedRecordCounts($id);
    if (array_sum($counts) > 0) {
        json_error('This employee still has linked records and cannot be permanently deleted. Deactivate instead.');
    }
    $users->hardDelete($id);
} else {
    $users->softDelete($id);
}

json_out(['ok' => true, 'action' => $action]);
