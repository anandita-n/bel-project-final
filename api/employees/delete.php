<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

$u = require_role_json(['admin']);

$body = request_body();
$id = (int)($body['id'] ?? 0);

if (!$id) {
    json_error('Missing employee id.');
}
if ($id === (int)$u['id']) {
    json_error('You cannot remove your own account.');
}

(new UserRepository())->softDelete($id);

json_out(['ok' => true]);
