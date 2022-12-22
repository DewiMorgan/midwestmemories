<?php
namespace app;

require_once 'TokenRefresher.php';

use \Exception;
use Spatie\Dropbox\Client;

class InitDropbox {
    private $client;
    public $cursor;
    public $entries;
    public $iterations;
    private const DROPBOX_PATH = '/midwestmemories';

    public function __construct() {
        $tokenRefresher = new TokenRefresher();
        $token = $tokenRefresher->getToken();

        $this->client = new Client($token);
    }

    /**
     * Get the recursive list of all files for this website. Might be LONG.
     * @return string of file details.
     */
    function initRootCursor(): array {
        $this->iterations = 0;
        $this->entries = 1;
        $result = [];
        $endTime = time() + 20;
        try {
            $list = $this->client->listFolder('', true);
            if (array_key_exists('entries', $list)) {
                $this->iterations = 1;
                $this->cursor = $list['cursor'];
                foreach ($list['entries'] as $fileEntry) {
                    $this->entries++;
                    if ('file' === $fileEntry['.tag'] && preg_match('#^/midwestmemories/#', $fileEntry['path_lower'])) {
                        $result []= $fileEntry['path_display'];
                    }
                }
            }
            while (array_key_exists('has_more', $list) && $list['has_more'] && $this->cursor && time() < $endTime) {
                $list = $this->client->listFolderContinue($this->cursor);
                $this->cursor = $list['cursor'];
                $this->iterations ++;
                if (array_key_exists('entries', $list)) {
                    foreach ($list['entries'] as $fileEntry) {
                        $this->entries ++;
                        if ('file' === $fileEntry['.tag'] && preg_match('#^/midwestmemories/#', $fileEntry['path_lower'])) {
                            $result []= $fileEntry['path_display'];
                        }
                    }
                }
            }
            return $result;
        } catch (Exception $e) {
            die(var_export($e));
        }
    }

    /**
     * Get the list of updated files for the given cursor, up to a timeout.
     * @return string of file details.
     */
    function continueRootCursor($cursor, $entriesSoFar): array {
        $this->iterations = 0;
        $this->entries = $entriesSoFar;
        $this->cursor = $cursor;
        $result = [];
        $endTime = time() + 20;
        try {
            $list = ['has_more' => true];
            while (array_key_exists('has_more', $list) && $list['has_more'] && $this->cursor && time() < $endTime) {
                $list = $this->client->listFolderContinue($this->cursor);
                $this->cursor = $list['cursor'];
                $this->iterations ++;
                if (array_key_exists('entries', $list)) {
                    foreach ($list['entries'] as $fileEntry) {
                        $this->entries ++;
                        if ('file' === $fileEntry['.tag'] && preg_match('#^/midwestmemories/#', $fileEntry['path_lower'])) {
                            $result []= $fileEntry;
                        }
                    }
                }
            }
            return $result;
        } catch (Exception $e) {
            die(var_export($e));
        }
    }


    /**
     * Get the recursive list of all files for this website, up to a timeout.
     * @return string of file details.
     */
/*
    function getRecursiveList(): array {
        $this->iterations = 0;
        try {
            $list = $this->client->listFolder(self::DROPBOX_PATH, true);
            if (array_key_exists('entries', $list)) {
                $result = $list['entries'];
                $this->iterations = 1;
                $this->cursor = $list['cursor'];
            }
            while (array_key_exists('has_more', $list) && $list['has_more'] && $this->cursor) {
                $list = $this->client->listFolderContinue($this->cursor);
                $this->cursor = $list['cursor'];
                if (array_key_exists('entries', $list)) {
                    $result = array_merge($result, $list['entries']);
                    $this->iterations ++;
                }
            }
            return $result;
        } catch (Exception $e) {
            die(var_export($e));
        }
    }
*/
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
}