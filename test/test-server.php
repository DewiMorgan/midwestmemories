<?php

/** A helper that creates a fake web server */
declare(strict_types=1);

namespace MidwestMemories;

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Fake the mod_rewrite rules for the internal PHP server during unit tests.
$matches = [];
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (preg_match('#^/api/(v[0-9.]+)/(.+)$#i', $requestUri, $matches)) {
    $_GET['apiversion'] = $_GET['apiversion'] ?? $matches[1];
    $_GET['path'] = $_GET['path'] ?? $matches[2];
}

// Include the relevant bootstrap file.
if (str_starts_with($_SERVER['REQUEST_URI'], '/api/' )) {
    require_once(__DIR__ . '/../api.php');
} elseif (str_starts_with($_SERVER['REQUEST_URI'], '/admin')) {
    require_once(__DIR__ . '/../admin.php');
} else {
    require_once __DIR__ . '/../index.php';
}
