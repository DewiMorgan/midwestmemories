<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Api\ApiGateway;

/**
 * All JSON APIs go through this endpoint.
 */

date_default_timezone_set('America/Chicago');

session_start();
header('Content-Type: application/json');

// Prevent caching any API responses.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once(__DIR__ . '/src/autoload.php');

$api = new ApiGateway();
$api->handleApiCall();
