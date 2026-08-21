<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_role_json(['admin']);

$body = request_body();
$id = (int)($body['id'] ?? 0);
if (!$id) {
    json_error('Missing employee id.');
}

try {
    (new UserRepository())->reactivate($id);
} catch (\RuntimeException $e) {
    json_error($e->getMessage());
}

json_out(['ok' => true]);
