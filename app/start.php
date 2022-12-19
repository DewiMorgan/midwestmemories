<?php

require __DIR__ . '/../vendor/autoload.php';
require 'TokenRefresher.php';

$tokenRefresher = new TokenRefresher();
$token = $tokenRefresher->getToken();

$client = new Spatie\Dropbox\Client($token);

try {
    $list = $client->listFolder('/midwestmemories', false);
    var_export($list);
} catch (Exception $e) {
    var_export($e);
}

