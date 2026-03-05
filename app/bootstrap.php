<?php

declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');

$root = dirname(__DIR__);

if (file_exists($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $trimmed, 2));
        $_ENV[$k] = $v;
    }
}

require_once $root . '/app/config/database.php';
require_once $root . '/app/helpers/common.php';
require_once $root . '/app/middleware/auth.php';

if (file_exists($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

$GLOBALS['app_config'] = require $root . '/app/config/app.php';
