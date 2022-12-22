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
    private const DROPBOX_USER_ID = 16181197;

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
                $this->saveCusorFromList($list);
                $result = $this->handleEntries($result, $list);
            }
            while (array_key_exists('has_more', $list) && $list['has_more'] && $this->cursor && time() < $endTime) {
                $list = $this->client->listFolderContinue($this->cursor);
                $this->saveCusorFromList($list);
                $result = $this->handleEntries($result, $list);
                $this->iterations ++;
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
    function continueRootCursor($entriesSoFar): array {
        self::loadCursor();
        $this->iterations = 0;
        $this->entries = $entriesSoFar;
        $result = [];
        $endTime = time() + 20;
        try {
            $list = ['has_more' => true];
            while (array_key_exists('has_more', $list) && $list['has_more'] && $this->cursor && time() < $endTime) {
                $list = $this->client->listFolderContinue($this->cursor);
                $this->saveCusorFromList($list);
                $this->handleEntries($result, $list);
                $this->iterations ++;
            }
            return $result;
        } catch (Exception $e) {
            die(var_export($e));
        }
    }

    /**
     * Do whatever is needed with an entry within the midwestmemories subfolder.
     * @param array result
     * @param array list
     * @return array
     */
    private function handleEntries($result, $list): array {
        if (array_key_exists('entries', $list)) {
            foreach ($list['entries'] as $fileEntry) {
                $this->entries++;
//                if (preg_match('#^/midwestmemories/#', $fileEntry['path_lower'])) {
                    $result []= $fileEntry;
//                }
            }
        }
        return $result;
    }

    /**
     * Save the array elenent 'cursor', if any.
     * @param list an array that might have an element 'cursor'.
     */
     private function saveCusorFromList($list): void {
        if (!empty($list['cursor']) && $this->cursor != $list['cursor']) {
            $this->cursor = $list['cursor'];
            self::saveCursor();
        }
    }

    /** save the current cursor to the DB. */
    function saveCursor(): void {
        $m_userid = Db::escape(self::DROPBOX_USER_ID);
        $m_cursor = Db::escape($this->cursor);
        Db::sqlExec("INSERT INTO `midmem_dropbox_users` (`user_id`, `cursor_id`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `cursor_id` = ?", 'dss', '$m_userid', '$m_cursor', '$m_cursor');
    }

    /** Load the current cursor from the DB. */
    function loadCursor(): void {
        $this->cursor = Db::sqlGetItem("SELECT `cursor_id` FROM `midmem_dropbox_users` LIMIT 1", 'cursor_id');
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