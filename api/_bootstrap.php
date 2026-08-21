<?php

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Prevent uncaught exceptions/errors (e.g. a DB constraint violation) from leaking a stack
// trace, file paths, or raw SQL to the client — always respond with a generic JSON error.
set_exception_handler(function (): void {
    http_response_code(500);
    echo json_encode(['error' => 'Server error.']);
    exit;
});
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_out(['error' => $message], $status);
}

function require_login_json(): array
{
    $u = current_user();
    if (!$u) {
        json_error('Not authenticated.', 401);
    }
    return $u;
}

function require_role_json(array $roles): array
{
    $u = require_login_json();
    if (!in_array($u['role'], $roles, true)) {
        json_error('Not permitted.', 403);
    }
    return $u;
}

/** Reads JSON body if present, else falls back to $_POST (so plain form posts still work). */
function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}
