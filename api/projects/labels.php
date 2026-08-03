<?php

require_once __DIR__ . '/../_bootstrap.php';

use App\Repositories\TaskRepository;

require_login_json();

$body = request_body();
$action = $body['action'] ?? '';

if ($action === 'list') {
    json_out(['ok' => true, 'labels' => (new TaskRepository())->allLabels()]);
} else {
    json_error('Unknown action.');
}
