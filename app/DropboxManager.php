<?php

declare(strict_types=1);

namespace MidwestMemories;

use Spatie\Dropbox\Client;

/**
 * Manage a dropbox connection, and the operations on dropbox files.
 */
class DropboxManager extends Singleton
{
    /** Dropbox Client object. */
    private Client $client;

    /** The current position in a read of the file status. Long-lived, persistent, gets updates. */
    public string $cursor;

    public const KEY_VALID_FILES = 'numValidFiles';
    public const KEY_TOTAL_FILES = 'numTotalFiles';
    public const KEY_MORE_FILES = 'moreFilesToGo';
    public const KEY_ERROR = 'error';

    /**
     * Protected singleton constructor.
     */
    protected function __construct()
    {
        parent::__construct();
        $tokenRefresher = new TokenRefresher();
        $token = $tokenRefresher->getToken();

        $this->client = new Client($token);
    }

    /**
     * Initialize the cursor, and get the start of the list of all files for this website. Might be LONG.
     * @return array List of file details.
     */
    public function initRootCursor(): array
    {
        $list = $this->client->listFolder('', true);
        if (array_key_exists('cursor', $list)) {
            $this->setNewCursor($list['cursor']);
            $error = 'OK';
        } else {
            $error = 'Error: Root cursor not set';
        }
        $result = [
            static::KEY_TOTAL_FILES => count($list['entries'] ?? 0),
            static::KEY_VALID_FILES => count($list['entries'] ?? 0),
            static::KEY_MORE_FILES => $list['has_more'] ?? false,
            static::KEY_ERROR => $error
        ];
        Log::debug('Result:', $result);
        return $result;
    }

    /**
     * Get the list of updated files for the given cursor, up to a timeout.
     * @return array Details of what was done.
     */
    public function resumeRootCursor(): array
    {
        $this->loadCursor();
        $list = [];
        if ($this->cursor) {
            $list = $this->client->listFolderContinue($this->cursor);
            if (array_key_exists('cursor', $list)) {
                $this->setNewCursor($list['cursor']);
            }
            $error = 'OK';
        } else {
            $error = 'Error: Root cursor not initially set';
        }
        $result = [
            static::KEY_TOTAL_FILES => count($list['entries'] ?? 0),
            static::KEY_VALID_FILES => count($list['entries'] ?? 0),
            static::KEY_MORE_FILES => $list['has_more'] ?? false,
            static::KEY_ERROR => $error
        ];
        Log::debug('Result:', $result);
        return $result;
    }

    /**
     * Read a chunk of the list of updated files for the given DropBox cursor, and queue it in the MySQL.
     * @return array of count read from DropBox & written to MySQL, more can be read, and any errors.
     */
    public function readOneCursorUpdate(): array
    {
        $this->loadCursor();
        if ($this->cursor) {
            $list = $this->client->listFolderContinue($this->cursor);
            $this->setNewCursor($list['cursor']);
            $numValidFiles = $this->saveFileQueue($list['entries']);
            Log::debug('List', $list);
            $result = [
                static::KEY_VALID_FILES => $numValidFiles,
                static::KEY_TOTAL_FILES => count($list['entries']),
                static::KEY_MORE_FILES => $list['has_more'] ?? false,
                static::KEY_ERROR => 'OK'
            ];
        } else {
            $result = [
                static::KEY_VALID_FILES => 0,
                static::KEY_TOTAL_FILES => 0,
                static::KEY_MORE_FILES => false,
                static::KEY_ERROR => 'Error: Root cursor not set'
            ];
        }

        Log::debug('Result:', $result);
        return $result;
    }

    /**
     * Persistently set the cursor to a new value.
     * @param string $cursor The cursor string.
     */
    private function setNewCursor(string $cursor): void
    {
        if (!empty($cursor) && $this->cursor !== $cursor) {
            $this->cursor = $cursor;
            Db::sqlExec(
                'INSERT INTO `midmem_dropbox_users` (`user_id`, `cursor_id`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `cursor_id` = ?',
                'dss',
                Conf::get(Key::DROPBOX_USER_ID),
                $cursor,
                $cursor
            );
        }
    }

    /**
     * Save files to the queue, to process later.
     * @param array $list Array of entries, each an array with keys '.tag', 'name', 'path_lower', 'path_display', etc.
     * .tag is file, folder, delete, or more.
     * @return int How many files were added to the list.
     */
    private function saveFileQueue(array $list): int
    {
        $numberOfFiles = 0;
        foreach ($list as $entry) {
            // Skip anything we don't care about.
            if ('file' !== $entry['.tag'] || !str_starts_with($entry['path_lower'], '/midwestmemories/')) {
                continue;
            }
            $numberOfFiles++;
            // Could check other .tag='file' fields, like 'is_downloadable', though that should always be true, I think.
            // Could check 'content_hash' to see if it is unchanged, though if it is in our list, it should be changed.
            // Could check for .tag='deleted' later in the list, as it might be re-deleted; but handle deletion first.
            Db::sqlExec(
                "INSERT INTO `midmem_file_queue` (`file_name`, `full_path`, `sync_status`, `error_message`)
                    VALUES (?, ?, ?, '')",
                'sss',
                $entry['name'],
                ltrim($entry['path_display'], '\\/'),
                SyncStatus::NEW->value
            );
        }
        return $numberOfFiles;
    }

    /** Load the current cursor from the DB. */
    private function loadCursor(): void
    {
        $this->cursor = Db::sqlGetItem('SELECT `cursor_id` FROM `midmem_dropbox_users` LIMIT 1', 'cursor_id');
    }

    /**
     * From https://stackoverflow.com/questions/6409462/downloading-a-large-file-using-curl
     * @param string $url The URL to download from.
     * @param string $fullPath The path to download to.
     * @return bool Success.
     */
    public function downloadFromUrl(string $url, string $fullPath): bool
    {
        set_time_limit(0);
        $success = false;
        $ch = false;

        // Check and log each step of downloading the file. Daisy-chained elseif ensures handles get closed at the end.
        if (false === $fp = fopen($fullPath, 'wb+')) {
            Log::debug('fopen failed for downloadUrlToPath', [$url, $fullPath]);
        } elseif (false === $ch = curl_init($url)) {
            Log::debug('curl_init failed for downloadUrlToPath', [$url, $fullPath]);
        } elseif (false === (
                // if timeout (seconds) is too low, download will be interrupted
                curl_setopt($ch, CURLOPT_TIMEOUT, 600)
                && curl_setopt($ch, CURLOPT_FILE, $fp) // Write curl response to file
                && curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true)
                // These two only if https certificate isn't recognized.
                // && curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0)
                // && curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0)
            )) {
            Log::debug('curl_setopt failed for downloadUrlToPath', [$url, $fullPath, curl_error($ch)]);
        } elseif (false === curl_exec($ch)) { // Get curl response
            Log::debug('curl_exec failed for downloadUrlToPath', [$url, $fullPath, curl_error($ch)]);
        } elseif (false === file_exists($fullPath)) {
            Log::debug('File creation failed for downloadUrlToPath', [$url, $fullPath]);
        } else {
            Log::debug('Success: downloadUrlToPath', [$url, $fullPath]);
            $success = true;
        }

        // Close the handles.
        if (false !== $ch) {
            curl_close($ch);
        }
        if (false !== $fp) {
            fclose($fp);
        }

        return $success;
    }

    /**
     * Wrapper for Dropbox method.
     * @param string $untrimmedPath Requires NON-trimmed full path!
     * @return string URL
     */
    public function getTemporaryLink(string $untrimmedPath): string
    {
        return $this->client->getTemporaryLink($untrimmedPath);
    }
}
