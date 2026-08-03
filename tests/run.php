<?php

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/framework.php';

$files = glob(__DIR__ . '/*Test.php');
sort($files);
foreach ($files as $file) {
    require $file;
}

$r = $GLOBALS['__test_results'];
echo "\n" . str_repeat('-', 44) . "\n";
echo "Passed: {$r['pass']}   Failed: {$r['fail']}\n";

if ($r['fail'] > 0) {
    echo "\nFailures:\n";
    foreach ($r['failures'] as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
