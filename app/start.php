<?php
namespace app;

require __DIR__ . '/../vendor/autoload.php';
require 'TokenRefresher.php';

use \Exception;
use Spatie\Dropbox\Client;

$tokenRefresher = new TokenRefresher();
$token = $tokenRefresher->getToken();

$client = new Client($token);

try {
    $list = $client->listFolder('/midwestmemories', false);
    var_export($list);
} catch (Exception $e) {
    var_export($e);
}

