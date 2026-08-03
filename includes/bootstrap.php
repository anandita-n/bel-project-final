<?php

/**
 * Single include point for every page. Replaces the old repeated block of
 * require_once('includes/auth.php') + require_once('src/autoload.php') + require_once('includes/helpers.php')
 * that used to appear at the top of every file.
 */

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// Every page here is session-gated and renders session-specific data (e.g. window.PAGE_CONFIG
// embeds the current user's id). Without this, a browser's back/forward cache can restore a
// fully stale copy of the page — including a stale embedded user id — without re-running PHP.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
