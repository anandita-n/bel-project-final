<?php

/**
 * A deliberately tiny, dependency-free test harness — the bundled PEAR PHPUnit in this XAMPP
 * install predates PHP 8 and no composer/package manager is available in this environment, so
 * this stands in for it. One assertion style, one runner, no test-class ceremony.
 *
 * Each test() call runs inside a DB transaction that is always rolled back afterward, so tests
 * can freely INSERT/UPDATE against the real bel_pms database without leaving any trace.
 */

final class AssertionFailedError extends \Exception {}

function assert_true($condition, string $message = 'Expected true'): void {
    if ($condition !== true) {
        throw new AssertionFailedError($message);
    }
}

function assert_false($condition, string $message = 'Expected false'): void {
    if ($condition !== false) {
        throw new AssertionFailedError($message);
    }
}

function assert_equals($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new AssertionFailedError($message ?: sprintf('Expected %s, got %s', var_export($expected, true), var_export($actual, true)));
    }
}

function assert_null($value, string $message = 'Expected null'): void {
    if ($value !== null) {
        throw new AssertionFailedError($message);
    }
}

function assert_not_null($value, string $message = 'Expected non-null'): void {
    if ($value === null) {
        throw new AssertionFailedError($message);
    }
}

function assert_count(int $expected, array $array, string $message = ''): void {
    $actual = count($array);
    if ($actual !== $expected) {
        throw new AssertionFailedError($message ?: "Expected count $expected, got $actual");
    }
}

function assert_contains($needle, array $haystack, string $message = ''): void {
    if (!in_array($needle, $haystack, true)) {
        throw new AssertionFailedError($message ?: 'Expected array to contain ' . var_export($needle, true));
    }
}

$GLOBALS['__test_results'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

function test(string $name, callable $fn): void {
    $pdo = \App\Database::connection();
    $pdo->beginTransaction();
    try {
        $fn();
        echo "  [PASS] $name\n";
        $GLOBALS['__test_results']['pass']++;
    } catch (\Throwable $e) {
        echo "  [FAIL] $name -- " . $e->getMessage() . "\n";
        $GLOBALS['__test_results']['fail']++;
        $GLOBALS['__test_results']['failures'][] = "$name: " . $e->getMessage();
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

function test_suite(string $suiteName, callable $fn): void {
    echo "\n$suiteName\n";
    $fn();
}

/** Minimal valid disposable user row for FK-dependent tests — safe to call freely since the
 *  enclosing test() call always rolls back. */
function make_user(string $name, string $employeeCode, string $role = 'employee', ?int $managerId = null): int {
    $pdo = \App\Database::connection();
    $stmt = $pdo->prepare('INSERT INTO users (employee_code, name, email, password, role, manager_id) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$employeeCode, $name, strtolower($employeeCode) . '@test.local', password_hash('x', PASSWORD_DEFAULT), $role, $managerId]);
    return (int)$pdo->lastInsertId();
}

/** Minimal valid disposable project row for FK-dependent tests. */
function make_project(string $code, string $name, int $managerId): int {
    $pdo = \App\Database::connection();
    $stmt = $pdo->prepare('INSERT INTO projects (project_code, name, manager_id) VALUES (?, ?, ?)');
    $stmt->execute([$code, $name, $managerId]);
    return (int)$pdo->lastInsertId();
}

/**
 * -----------------------------------------------------------------------------------------
 * HTTP integration layer for api/*.php endpoints.
 *
 * The API endpoints call json_out()/json_error(), which end in exit() — that makes them
 * impossible to unit-test in-process (exit() would kill the test runner too). So these hit
 * the already-running Apache server directly over HTTP, the same way this whole project was
 * manually curl-tested throughout development.
 *
 * Because this is a genuinely separate PHP process, the test()-transaction-rollback trick
 * doesn't apply here (an uncommitted transaction in the test runner's DB connection is
 * invisible to Apache's connection) — so http_test() commits real rows and every http_test
 * is expected to clean up its own fixtures via the returned $cleanup callback.
 * -----------------------------------------------------------------------------------------
 */

const API_BASE_URL = 'http://localhost/bel-pms/';

function http_request(string $method, string $path, ?array $jsonBody, string $cookieFile): array {
    $ch = curl_init(API_BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($jsonBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $body = curl_exec($ch);
    if ($body === false) {
        throw new AssertionFailedError('HTTP request failed: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($body, true);
    return ['status' => $status, 'body' => $decoded ?? $body];
}

function http_get(string $path, string $cookieFile): array {
    return http_request('GET', $path, null, $cookieFile);
}

function http_post(string $path, array $data, string $cookieFile): array {
    return http_request('POST', $path, $data, $cookieFile);
}

/** Logs in as the given credentials via the real login.php form and returns a cookie-jar path
 *  to reuse across subsequent http_get()/http_post() calls for that session. */
function api_login(string $email, string $password, string $loginAs = 'user'): string {
    $cookieFile = tempnam(sys_get_temp_dir(), 'bel_test_cookie_');
    $ch = curl_init(API_BASE_URL . 'login.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['email' => $email, 'password' => $password, 'login_as' => $loginAs]),
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    curl_exec($ch);
    curl_close($ch);
    return $cookieFile;
}

/** A disposable user that is actually committed (not rolled back) plus a matching login cookie
 *  jar, since HTTP tests need a real row Apache's PHP process can see. Call the returned
 *  cleanup() when done. */
function make_http_user(string $name, string $employeeCode, string $role = 'employee'): array {
    $pdo = \App\Database::connection();
    $email = strtolower($employeeCode) . '@test.local';
    $stmt = $pdo->prepare('INSERT INTO users (employee_code, name, email, password, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$employeeCode, $name, $email, password_hash('testpass', PASSWORD_DEFAULT), $role]);
    $id = (int)$pdo->lastInsertId();
    $loginAs = $role === 'admin' ? 'admin' : 'user';
    // Admins log in with email; employees/managers log in with their staff ID (matches login.php).
    $identifier = $role === 'admin' ? $email : $employeeCode;
    $cookieFile = api_login($identifier, 'testpass', $loginAs);
    return [
        'id' => $id,
        'cookies' => $cookieFile,
        'cleanup' => function () use ($pdo, $id, $cookieFile) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            @unlink($cookieFile);
        },
    ];
}

/** Runs $fn with no transaction wrapper (see class doc-comment above) — $fn is responsible for
 *  cleaning up any fixtures it commits, typically via make_http_user()'s cleanup callback. */
function http_test(string $name, callable $fn): void {
    try {
        $fn();
        echo "  [PASS] $name\n";
        $GLOBALS['__test_results']['pass']++;
    } catch (\Throwable $e) {
        echo "  [FAIL] $name -- " . $e->getMessage() . "\n";
        $GLOBALS['__test_results']['fail']++;
        $GLOBALS['__test_results']['failures'][] = "$name: " . $e->getMessage();
    }
}
