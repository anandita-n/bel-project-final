<?php

/** Central place for environment-specific settings. Swap these for getenv() calls when deploying beyond a single XAMPP box. */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'bel_pms',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
];
