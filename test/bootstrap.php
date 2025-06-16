<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

// Optional: Disable error output during testing if you log it instead
// error_reporting(E_ALL);
// ini_set('display_errors', '0');

// Optional: Start a session if your code requires it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
