<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;
use MidwestMemories\Enum\SyncStatus;
use Spatie\Dropbox\Client;

/**
 * Manage a dropbox connection, and the operations on dropbox files.
 * @see https://www.dropbox.com/developers/documentation/http/documentation#files-list_folder
 */
class DropboxManager extends Singleton
{
    /** Dropbox Client object. */
    private Client $client;

    /** The current position in a read of the file status. Long-lived, persistent, gets updates. */
    public string $cursor;

    public const KEY_ADDED_FILES = 'numAddedFiles';
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
     * @param array $list
     * @param string $error
     * @return array
     */
    public function handleFileList(array $list, string $error): array
    {
        $numAddedFiles = 0;
        $hasMoreFiles = false;
        if (!empty($list)) {
            // This is a kludge for an apparent Dropbox bug. When given a path, `has_more` is always true, and it
            // will always return the path if asked.
            $hasMoreFiles = $list['has_more'] ?? false;
            if (1 === count($list['entries'])) {
                $badPath = rtrim(Conf::get(Key::DROPBOX_PATH_PREFIX), '/');
                $actualPath = $list['entries'][0]['path_display'] ?? $badPath;
                if ($actualPath === $badPath) {
                    $hasMoreFiles = false;
                }
            }

            // Otherwise, process the list.
            $numAddedFiles = $this->saveFileQueue($list['entries']);
            Log::debug('List', $list);
        }

        $result = [
            static::KEY_ADDED_FILES => $numAddedFiles,
            static::KEY_TOTAL_FILES => count($list['entries'] ?? []),
            static::KEY_MORE_FILES => $hasMoreFiles,
            static::KEY_ERROR => $error
        ];
        Log::debug('Result:', $result);
        return $result;
    }

    /**
     * Initialize the cursor, and get the start of the list of all files for this website. Might be LONG.
     * ToDo: change listFolder to use Conf::get[Key::DROPBOX_PATH_PREFIX] instead of ''.
     *       No trailing slash!
     * @return array List of file details.
     */
    public function initRootCursor(): array
    {
        $path = rtrim(Conf::get(Key::DROPBOX_PATH_PREFIX), '/');
        $list = $this->client->listFolder($path, true);
        if (array_key_exists('cursor', $list)) {
            $saveResult = $this->setNewCursor($list['cursor']);
            if (empty($saveResult)) {
                $error = 'Error: Root cursor not saved to MySQL';
                Log::error('Root cursor not saved to MySQL', $saveResult);
            } else {
                $error = 'OK';
            }
        } else {
            $error = 'Error: Root cursor not set in returned file details';
            Log::warn('Root cursor not set in returned file details', $list);
        }
        return $this->handleFileList($list, $error);
    }

    /**
     * Get a page of updated files for the given cursor.
     * @return array Details of what was done.
     */
    public function readCursorUpdate(): array
    {
        // Ensure we have a cursor.
        $this->loadCursor();
        if ($this->cursor) {
            $list = $this->client->listFolderContinue($this->cursor);
            if (array_key_exists('cursor', $list)) {
                $this->setNewCursor($list['cursor']);
            }
            $error = 'OK';
        } else {
            $list = [];
            $error = 'Error: Root cursor not initially set';
        }
        return $this->handleFileList($list, $error);
    }

    /**
     * Persistently set the cursor to a new value, if it has changed.
     * @param string $cursor The cursor string.
     * @return array ['id'=>N, 'rows'=>1] if changed, else ['id'=>N, 'rows'=>0] or [] depending on why it failed.
     */
    private function setNewCursor(string $cursor): array
    {
        if (!empty($cursor) && (!isset($this->cursor) || $this->cursor !== $cursor)) {
            $this->cursor = $cursor;
            return Db::sqlExec(
                'INSERT INTO `' . Db::TABLE_DROPBOX_USERS . '` (`user_id`, `cursor_id`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `cursor_id` = ?',
                'dss',
                Conf::get(Key::DROPBOX_USER_ID),
                $cursor,
                $cursor
            );
        }
        return [];
    }

    /**
     * Save files to the queue, to process later.
     * ToDo: Could check for .tag='deleted' later in the list, as it might be re-deleted; but handle deletion first.
     * @param array $list Array of entries, each an array with keys '.tag', 'name', 'path_lower', 'path_display', etc.
     * .tag is file, folder, delete, or more.
     * @return int How many files were added to the list.
     */
    private function saveFileQueue(array $list): int
    {
        $numberOfFiles = 0;
        $pathPrefix = Conf::get(Key::DROPBOX_PATH_PREFIX);
        foreach ($list as $entry) {
            // Skip anything we definitely don't care about.
            if ('file' !== $entry['.tag'] || !str_starts_with($entry['path_lower'], $pathPrefix)) {
                continue;
            }
            $numberOfFiles++;
            // The ON DUPLICATE KEY behavior only overwrites with updated values if the hash was changed.
            $result = Db::sqlExec(
                'INSERT INTO `' . Db::TABLE_FILE_QUEUE . "` 
                    (`file_name`, `full_path`, `sync_status`, `file_hash`, `error_message`)
                 VALUES (?, ?, ?, ?, '')
                 ON DUPLICATE KEY UPDATE 
                    file_name = IF(VALUES(file_hash) != file_hash, VALUES(file_name), file_name),
                    sync_status = IF(VALUES(file_hash) != file_hash, VALUES(sync_status), sync_status),
                    file_hash = IF(VALUES(file_hash) != file_hash, VALUES(file_hash), file_hash),
                    error_message = IF(VALUES(file_hash) != file_hash, '', error_message)",
                'ssss',
                $entry['name'],
                ltrim($entry['path_display'], '\\/'),
                SyncStatus::NEW->value,
                $entry['content_hash']
            );
            if (empty($result)) {
                Log::error('Error writing Dropbox entry to MySQL', $entry);
            } else {
                $numberOfFiles = $result['rows'];
            }
        }
        return $numberOfFiles;
    }

    /** Load the current cursor from the DB. */
    private function loadCursor(): void
    {
        $this->cursor = Db::sqlGetItem(
            'SELECT `cursor_id` FROM `' . Db::TABLE_DROPBOX_USERS . '` LIMIT 1', 'cursor_id'
        );
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
