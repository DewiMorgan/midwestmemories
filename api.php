<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Api\Api;

/**
 * All JSON APIs go through this endpoint.
 */

date_default_timezone_set('US/Central');

session_start();
header('Content-Type: application/json');

require_once(__DIR__ . '/src/autoload.php');

$api = new Api();
$api->handleApiCall();
