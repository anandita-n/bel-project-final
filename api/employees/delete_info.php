<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\UserRepository;

require_role_json(['admin']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    json_error('Missing employee id.');
}

$counts = (new UserRepository())->linkedRecordCounts($id);

json_out(['ok' => true, 'counts' => $counts]);
