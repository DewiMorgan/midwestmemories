<?php

/** A helper that creates a fake web server */
declare(strict_types=1);

namespace MidwestMemories;

// Enable all errors for test visibility.
use function PHPUnit\Framework\stringStartsWith;

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Include the relevant bootstrap file.
if (stringStartsWith('/api/', $_SERVER['REQUEST_URI'])) {
    require_once(__DIR__ . '/../api.php');
} elseif (stringStartsWith('/admin', $_SERVER['REQUEST_URI'])) {
    require_once(__DIR__ . '/../admin.php');
} else {
    require_once __DIR__ . '/../index.php';
}
