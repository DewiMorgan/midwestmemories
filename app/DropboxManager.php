<?php
namespace app;

require_once 'TokenRefresher.php';

use \Exception;
use Spatie\Dropbox\Client;

class DropboxManager {
    private $client;
    public $cursor;
    public $entries = 0;
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
     * @param int $entriesSoFar How many antries have already been processed in this run of the script.
     * @return string of file details.
     */
    function continueRootCursor(int $entriesSoFar): array {
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
                $result = $this->handleEntries($result, $list);
                $this->iterations ++;
            }
            return $result;
        } catch (Exception $e) {
            die(var_export($e));
        }
        return $result;
    }

    /**
     * Do whatever is needed with an entry within the midwestmemories subfolder.
     * @param array $result The result to append to
     * @param array $list The data array to pull the entries from.
     * @return array
     */
    private function handleEntries($result, $list): array {
        if (array_key_exists('entries', $list)) {
            foreach ($list['entries'] as $fileEntry) {
                $this->entries++;
                if (preg_match('#^/midwestmemories/#', $fileEntry['path_lower'])) {
                    $result []= $fileEntry;
                }
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

    /**
     * Save files to the queue, to process later.
     * @param array $list Array of entries, each an array with elements '.tag', 'name', 'path_lower', 'path_display' and maybe more.
     * .tag is file, folder, delete, or more.
     */
    private function saveListOfFilesToProcess($list): void {
        foreach ($list as $entry) {
            // Skip anything we don't care about.
            if ('file' !== $entry['.tag'] || !preg_match('#^/midwestmemories/#', $entry['path_lower'])) {
                continue;
            }
            // Could check other .tag='file' fields, like 'is_downloadable'? But that should always be true, I think.
            // Could check 'content_hash' to see if it's unchanged? But if it's in our list, it should be changed.
            // Could check .tag='deleted' later in the list to see if it gets deleted again, but not an issue til we handle deletion anyway.
            Db::sqlExec('INSERT INTO `midmem_file_queue` (`file_name`, `full_path`) VALUES (?, ?)', 'ss', $entry['name'], $entry['path_display']);
        }
    }

    public function processFilesFromDb(): void {
        $endTime = time() + 20;
        $cwd = getcwd();
echo "<p>Starting to process files. Endtime set to:$endTime, running in $cwd.<br>\n";
        $list = Db::sqlGetTable("SELECT * FROM `midmem_file_queue`");
echo "<p>Obtained list from database:<br><pre>" . var_export($list, true) . "</pre><br>\n";
        foreach ($list as $entry) {
            // Drop out early if we hit the time limit.
            if (time() < $endTime) {
echo "<p>$endTime has passed, at " . time() . "<br>\n";
                return;
            }
echo "<p>Not yet at timeout of $endTime, only at " . time() . "<br>\n";
            // If the dir doesn't exist, then create it.
            $dir = dirname($entry['full_path']);
echo "<p>Extracted directory name '$dir' from full path '{$entry['full_path']}'.<br>\n";
            if (!dir_exist($dir)) {
echo "<p>It didn't exist: creating it.<br>\n";
                if (mkdir($dir, 0700, true)) {
echo "<p>Successfully created $dir in $cwd.<br>\n";
                } else {
echo "<p>Failed to create the folder $dir in $cwd.<br>\n";
                }
            } else {
echo "<p>Directory $dir already exists in $cwd.<br>\n";
            }
            // Download the file from Dropbox. If it already exists, it might've been edited, so we get it anyway.
echo "<p>Calling download API.<br>\n";
            $file = $this->client->download($entry['full_path']);
            //Save file contents to disk
            $fileContents = $file->getContents();
echo "<p>Read file contents:<br><pre>" . var_export($fileContents, true) . "</pre><br>\n";
echo "<p>Saving file contents to {$entry['full_path']}.<br>\n";
            $result = file_put_contents($entry['full_path'], $fileContents);
echo "<p>Got result '" . var_export($result, true) . "'.<br>\n";
echo "<p>Saving file metadata to {$entry['full_path']}.txt.<br>\n";
            //Save File Metadata
            file_put_contents($entry['full_path'].".txt", $file->getMetadata());
echo "<p>Got result '" . var_export($result, true) . "'.<br>\n";
        }
    }

    /** save the current cursor to the DB. */
    function saveCursor(): void {
        Db::sqlExec("INSERT INTO `midmem_dropbox_users` (`user_id`, `cursor_id`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `cursor_id` = ?", 'dss', self::DROPBOX_USER_ID, $this->cursor, $this->cursor);
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
     }
     */
}