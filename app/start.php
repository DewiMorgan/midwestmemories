<?php
namespace app;

require __DIR__ . '/../vendor/autoload.php';
require 'TokenRefresher.php';

use \Exception;
use Spatie\Dropbox\Client;

$client = initDropbox();
$list = getRecursiveList($client);
/*
foreach ($list as $fileEntry) {
    switch ($fileEntry['.tag']) {
        case 'folder';
            echo "Folder: {$fileEntry['name']}\n";
            break;

        case 'file':
            echo "File: {$fileEntry['name']}\n";

            break;

        default:
            Db::adminDebug('Unknown value for $fileEntry[.tag]', $fileEntry);
            break;
    }
}
*/
var_export($list);

function initDropbox() {
    $tokenRefresher = new TokenRefresher();
    $token = $tokenRefresher->getToken();

    $client = new Client($token);
    return $client;
}

/**
 * Get the recursive list of all files for this website. Might be LONG.
 * @param Client $client
 * @return string of file details.
 */
function getRecursiveList(Client $client): array {
    try {
        $cursor = '';
        $list = $client->listFolder('/midwestmemories', true);
        if (array_key_exists('entries', $list)) {
            $result = $list['entries'];
            $result['iterations'] = 1;
            $result['cursor'] = $list['cursor'];
        }
        while (array_key_exists('has_more', $list) && $list['has_more'] && $result['cursor']) {
            $list = $client->listFolderContinue($result['cursor']);
            if (array_key_exists('entries', $list)) {
                $result = array_merge($result, $list['entries']);
                $result['iterations'] ++;
            }
        }
    } catch (Exception $e) {
        die(var_export($e));
    }
    return $result;
}
