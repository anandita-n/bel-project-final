<?php

/**
 * Single include point for every page. Replaces the old repeated block of
 * require_once('includes/auth.php') + require_once('src/autoload.php') + require_once('includes/helpers.php')
 * that used to appear at the top of every file.
 */

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
