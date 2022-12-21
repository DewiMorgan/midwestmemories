<?php
namespace app;

require __DIR__ . '/../vendor/autoload.php';
require 'TokenRefresher.php';

use \Exception;
use Spatie\Dropbox\Client;

$client = initDropbox();
$list = getRecursiveList($client);
var_export($list);

function initDropbox() {
    $tokenRefresher = new TokenRefresher();
    $token = $tokenRefresher->getToken();

    $client = new Client($token);
    return $client;
}

/**
 * Get the list of all files for this website. Might be LONG.
 * @param Client $client
 * @return string of file details.
 */
function getRecursiveList(Client $client): array {
    $result = ['iterations' => 0];
    $cursor = '';
    try {
        $list = ['has_more' => true];
        $list = $client->listFolder('/midwestmemories', true);
        if (array_key_exists('entries', $list)) {
            $result []= $list['entries'];
            $result['iterations'] ++;
            $cursor = $list['cursor'];
        }
        while (array_key_exists('has_more', $list) && $list['has_more'] && $cursor) {
            $list = $client->listFolderContinue($cursor);
            if (array_key_exists('entries', $list)) {
                $result []= $list['entries'];
                $result['iterations'] ++;
            }
        }
    } catch (Exception $e) {
        die(var_export($e));
    }
    return $result;
}
