<?php

declare(strict_types=1);

namespace MidwestMemories;

use Spatie\Dropbox\Client;

/**
 * Manage a dropbox connection, and the operations on dropbox files.
 */
class DropboxManager
{
    /** @var Client $client Dropbox Client object. */
    private Client $client;

    /** @var string $cursor The current position in a read of the file status. Long-lived, persistent, gets updates. */
    public string $cursor;

    /** @var int $entries How many valid files were found. Used only for reporting. */
    public int $entries = 0;

    /** @var int $iterations How many times we got the file list from the cursor because it responded "has more".
     * Only used for reporting display.
     */
    public int $iterations = 0;

    /** @const int MAX_PNG_BYTES - Max PNG size in bytes before we resample to JPG. */
    private const MAX_PNG_BYTES = 1024 * 1024;

    /** @const int MAX_THUMB_WIDTH - Max image width in pixels before scaling down for thumbnail. */
    private const MAX_THUMB_WIDTH = 64;

    /** @const int MAX_THUMB_HEIGHT - Max image height in pixels before scaling down for thumbnail. */
    private const MAX_THUMB_HEIGHT = 64;

    /** @const string VALID_FILE_PATH_REGEX - The sub-folder within the dropbox repo that we're interested in. */
    private const VALID_FILE_PATH_REGEX = '#^/midwestmemories/#';

    public function __construct()
    {
        $tokenRefresher = new TokenRefresher();
        $token = $tokenRefresher->getToken();

        $this->client = new Client($token);
    }

    /**
     * Get the recursive list of all files for this website. Might be LONG.
     * @return array List of file details.
     */
    public function initRootCursor(): array
    {
        $this->iterations = 0;
        $this->entries = 1;
        $result = [];
        $endTime = time() + 20;
        $list = $this->client->listFolder('', true);
        if (array_key_exists('entries', $list)) {
            $this->iterations = 1;
            $this->setNewCursor($list['cursor']);
            $result = $this->addValidEntries($result, $list['entries']);
        }
        return $this->readCursorToEnd($list, $endTime, $result);
    }

    /**
     * Get the list of updated files for the given cursor, up to a timeout.
     * @param int $entriesSoFar How many entries have already been processed in this run of the script.
     * @return array List of file details.
     */
    public function resumeRootCursor(int $entriesSoFar): array
    {
        self::loadCursor();
        $this->iterations = 0;
        $this->entries = $entriesSoFar;
        $result = [];
        $endTime = time() + 20;
        $list = ['has_more' => true];
        return $this->readCursorToEnd($list, $endTime, $result);
    }

    /**
     * Get the list of updated files for the given cursor, up to a timeout.
     * @param int $entriesSoFar How many entries have already been processed in this run of the script.
     * @return array List of file details.
     */
    public function readCursorUpdate(int $entriesSoFar): array
    {
        self::loadCursor();
        $this->iterations = 0;
        $this->entries = $entriesSoFar;
        $result = ['numFilesQueued' => 0, 'numFilesProcessed' => 0];
        $endTime = time() + 20;
        $list = ['has_more' => true];
        while (array_key_exists('has_more', $list) && $list['has_more'] && $this->cursor && time() < $endTime) {
            $list = $this->client->listFolderContinue($this->cursor);
            $this->setNewCursor($list['cursor']);
            $result = $this->addValidEntries($result, $list['entries']);
            $this->iterations++;
            $result['numFilesQueued'] += $this->saveFileQueue($list['entries']);
            $result['numFilesProcessed'] += count($list['entries']);
        }
        return $result;
    }

    /**
     * Filters a list of Dropbox file entries, appending those that match our valid file path with the known good ones.
     * @param array $knownGoodFiles The current list of matching entries, which will be merged with.
     * @param array $suspectFiles A list of file details to validate.
     * @return array The filtered list of entries.
     */
    private function addValidEntries(array $knownGoodFiles, array $suspectFiles): array
    {
        $filteredEntries = array_filter($suspectFiles, function ($fileEntry) {
            return preg_match(self::VALID_FILE_PATH_REGEX, $fileEntry['path_lower']);
        });

        $this->entries += count($filteredEntries);
        return array_merge($knownGoodFiles, $filteredEntries);
    }

    /**
     * Persistently set the cursor to a new value.
     * @param string $cursor The cursor string.
     */
    private function setNewCursor(string $cursor): void
    {
        if (!empty($cursor) && $this->cursor != $cursor) {
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
                'NEW'
            );
        }
        return $numberOfFiles;
    }

    /**
     * Download all files from the file queue table.
     * @return int How many files were processed.
     */
    public function downloadFiles(): int
    {
        $endTime = time() + 20;
        $list = Db::sqlGetTable("SELECT * FROM `midmem_file_queue` WHERE `sync_status` = 'NEW'");
        $numProcessed = 0;
        foreach ($list as $entry) {
            // Drop out early if we hit the time limit.
            if (time() > $endTime) {
                return $numProcessed;
            }
            $numProcessed++;
            $fullPath = ltrim($entry['full_path'], '/\\');
            // If the dir doesn't exist, then create it.
            $dir = dirname($fullPath);
            if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
                Db::sqlExec(
                    "UPDATE `midmem_file_queue` SET `sync_status` = 'ERROR', `error_message` = ? WHERE full_path = ?",
                    'ss',
                    "mkdir($dir,0700,true) failed",
                    $fullPath
                );
                continue;
            }
            // Download the file from Dropbox. If it already exists, it might've been edited, so we get it anyway.
            $url = $this->client->getTemporaryLink($entry['full_path']); // Requires NON-trimmed full path!
            $result = $this->downloadFromUrl($url, $fullPath);
            // Update the DB to DOWNLOADED or ERROR.
            Db::sqlExec(
                'UPDATE `midmem_file_queue` SET `sync_status` = ? WHERE full_path = ?',
                'ss',
                ($result ? 'DOWNLOADED' : 'ERROR'),
                $fullPath
            );
        }
        return $numProcessed;
    }

    /**
     * Add thumbnails, resample images, and parse txt files, then set status to PROCESSED.
     * @return int How many files were processed.
     */
    public function processDownloads(): int
    {
        $endTime = time() + 20;
        $list = Db::sqlGetTable("SELECT * FROM `midmem_file_queue` WHERE `sync_status` = 'DOWNLOADED'");
        $numProcessed = 0;
        foreach ($list as $entry) {
            // Drop out early if we hit the time limit.
            if (time() > $endTime) {
                return $numProcessed;
            }
            $numProcessed++;
            $fullPath = ltrim($entry['full_path'], '/\\');
            if (!file_exists($fullPath)) {
                Db::sqlExec(
                    "UPDATE `midmem_file_queue` SET `sync_status` = 'ERROR', `error_message` = 'file_exists failed' 
                        WHERE full_path = ?",
                    's',
                    $fullPath
                );
                continue;
            }

            // Get the mime type.
            $mimeType = mime_content_type($fullPath);
            switch ($mimeType) {
                case 'text/plain':
                    $this->processTextFile($fullPath);
                    break;
                case 'image/gif':
                    $this->processGifFile($fullPath);
                    break;
                case 'image/png':
                    $this->processPngFile($fullPath);
                    break;
                case 'image/jpeg':
                    $this->processJpegFile($fullPath);
                    break;
                case 'directory':
                case 'application/x-empty': // Zero length file
                default:
                    $this->processOtherFile($fullPath);
                    break;
            }
        }
        return $numProcessed;
    }

    /**
     * Process text file, parsing fields into the db.
     */
    private function processTextFile($fullPath): void
    {
        // ToDo: some parsing.
        Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = 'PROCESSED' WHERE full_path = ?", 's', $fullPath);
    }

    /** Process a PNG file, generating thumbnail and converting to JPG if needed.*/
    private function processPngFile(string $fullPath): void
    {
        if ((filesize($fullPath) > self::MAX_PNG_BYTES)) {
            // Thumbnail generation would be faster from the new JPG, so we roll this into convertToJpeg.
            $result = $this->convertToJpeg($fullPath);
        } else {
            $result = $this->makeThumb(imagecreatefrompng($fullPath), $fullPath);
        }
        $this->setSyncStatus($fullPath, ($result ? 'PROCESSED' : 'ERROR'), 'Processed as PNG.');
    }

    /** Process a GIF file, generating thumbnail.*/
    private function processGifFile(string $fullPath): void
    {
        $result = $this->makeThumb(imagecreatefromgif($fullPath), $fullPath);
        $this->setSyncStatus($fullPath, ($result ? 'PROCESSED' : 'ERROR'), 'Processed as GIF.');
    }

    /** Process a JPG file, generating thumbnail.*/
    private function processJpegFile(string $fullPath): void
    {
        $result = $this->makeThumb(imagecreatefromjpeg($fullPath), $fullPath);
        $this->setSyncStatus($fullPath, ($result ? 'PROCESSED' : 'ERROR'), 'Processed as JPG.');
    }

    /** Process an unknown file.*/
    private function processOtherFile(string $fullPath): void
    {
        // Nothing to do but mark it complete.
        Db::sqlExec(
            "UPDATE `midmem_file_queue` 
                SET `sync_status` = 'PROCESSED', `error_message`='Unknown type' 
                WHERE full_path = ?",
            's',
            $fullPath
        );
    }

    /** Load the current cursor from the DB. */
    private function loadCursor(): void
    {
        $this->cursor = Db::sqlGetItem('SELECT `cursor_id` FROM `midmem_dropbox_users` LIMIT 1', 'cursor_id');
    }

    /**
     * Convert an image filename to a thumbnail filename, eg 'foo/bar.png' => 'foo/tn_bar.jpg'.
     * Note: Files that begin with a dot and have no extension, e.g. '.example', will get thumbs called 'tn_.jpg'.
     * @param string $imagePath Path and filename of the source image.
     * @return string The resulting filename.
     */
    public static function getThumbName(string $imagePath): string
    {
        return preg_replace('#([^/]*)\.[^/.]+?$#', 'tn_$1.jpg', $imagePath);
    }

    /**
     * From: https://stackoverflow.com/questions/11376315/creating-a-thumbnail-from-an-uploaded-image
     * @param resource $sourceImage Image resource loaded from whatever image format.
     * @param string $fullPath Target full path to original file.
     * @return bool success
     */
    private function makeThumb($sourceImage, string $fullPath): bool
    {
        if (false === $sourceImage) {
            Log::adminDebug('Source image false for makeThumb', $fullPath);
            return false;
        }
        $dest = self::getThumbName($fullPath);
        // Read source image size.
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        if (false === $origWidth || false === $origHeight) {
            Log::adminDebug('Source image dimensions false for makeThumb', [$origWidth, $origHeight, $fullPath]);
            return false;
        }
        $newWidth = $origWidth;
        $newHeight = $origHeight;
        // Scale to max height if needed.
        if ($origHeight > self::MAX_THUMB_HEIGHT) {
            $newHeight = self::MAX_THUMB_HEIGHT;
            $newWidth = floor($origWidth * ($newHeight / $origHeight));
        }
        // Scale further to max width if still too large.
        if ($newWidth > self::MAX_THUMB_WIDTH) {
            $newWidth = self::MAX_THUMB_WIDTH;
            $newHeight = floor($origWidth * ($newWidth / $origWidth));
        }

        /* Create a new, "virtual" image */
        $virtualImage = imagecreatetruecolor($newWidth, $newHeight);
        if (false === $virtualImage) {
            Log::adminDebug('Virtual image dimensions false for makeThumb', $fullPath);
            return false;
        }

        /* Resize and copy source image to new image */
        if (false === imagecopyresampled(
                $virtualImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $origWidth,
                $origHeight
            )) {
            Log::adminDebug('imagecopyresampled failed for makeThumb', $fullPath);
            return false;
        }

        /* Create the physical thumbnail image at its destination */
        if (false === imagejpeg($virtualImage, $dest, 70)) {
            Log::adminDebug('imagejpeg failed for makeThumb', $fullPath);
            return false;
        }

        return true;
    }

    /**
     * Convert large PNG files to more-compressed jpgs.
     * ToDo: How should this be reflected in the DB?
     * @param string $fullPath Full path to original file.
     * @return bool success
     */
    private function convertToJpeg(string $fullPath): bool
    {
        $sourceImage = imagecreatefrompng($fullPath);
        if (false === $sourceImage) {
            Log::adminDebug('Source image false for convertToJpeg', $fullPath);
            return false;
        }

        $newFullPath = dirname($fullPath) . '/' . basename($fullPath, '.png') . '.jpg';

        /* Save as a renamed JPG at its destination */
        if (false === imagejpeg($sourceImage, $newFullPath, 70)) {
            Log::adminDebug('imagejpeg failed for convertToJpeg', $fullPath);
            return false;
        }
        // Try to delete the huge file. If we can't, no big loss.
        unlink($fullPath);

        // Because it is slightly faster to create the thumbnail from here.
        return $this->makeThumb(imagecreatefrompng($newFullPath), $newFullPath);
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
        if (false === $fp = fopen($fullPath, 'w+')) {
            Log::adminDebug('fopen failed for downloadUrlToPath', [$url, $fullPath]);
        } elseif (false === $ch = curl_init($url)) {
            Log::adminDebug('curl_init failed for downloadUrlToPath', [$url, $fullPath]);
        } elseif (false === (
                // if timeout (seconds) is too low, download will be interrupted
                curl_setopt($ch, CURLOPT_TIMEOUT, 600)
                && curl_setopt($ch, CURLOPT_FILE, $fp) // Write curl response to file
                && curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true)
                // These two only if https certificate isn't recognized.
                // && curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0)
                // && curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0)
            )) {
            Log::adminDebug('curl_setopt failed for downloadUrlToPath', [$url, $fullPath, curl_error($ch)]);
        } elseif (false === curl_exec($ch)) { // Get curl response
            Log::adminDebug('curl_exec failed for downloadUrlToPath', [$url, $fullPath, curl_error($ch)]);
        } elseif (false === file_exists($fullPath)) {
            Log::adminDebug('File creation failed for downloadUrlToPath', [$url, $fullPath]);
        } else {
            Log::adminDebug('Success: downloadUrlToPath', [$url, $fullPath]);
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
     * Set the sync status of a given entry in the file queue.
     * @param string $fullPath The path (unique key) of the record to change.
     * @param string $status The new status to give this record.
     * @param string $errorMessage Optional error message to log.
     */
    public function setSyncStatus(string $fullPath, string $status, string $errorMessage = ''): void
    {
        Db::sqlExec(
            'UPDATE `midmem_file_queue` SET `sync_status` = ?, error_message = ? WHERE full_path = ?',
            'sss',
            $status,
            $errorMessage,
            $fullPath
        );
    }

    /**
     * Read from the cursor until it no longer response "has_more", filtering out files we're uninterested in.
     * @param array $list The list of files or other responses from the cursor's listFolder call.
     * @param int $endTime Maximum time() we can loop before we time out.
     * @param array $knownGoodFiles The current list of matching entries, which will be merged with.
     * @return array The list of files from the folder we're interested in.
     */
    public function readCursorToEnd(array $list, int $endTime, array $knownGoodFiles): array
    {
        while (array_key_exists('has_more', $list) && $list['has_more'] && $this->cursor && time() < $endTime) {
            $list = $this->client->listFolderContinue($this->cursor);
            $this->setNewCursor($list['cursor']);
            $knownGoodFiles = $this->addValidEntries($knownGoodFiles, $list['entries']);
            $this->iterations++;
        }
        return $knownGoodFiles;
    }

    /**
     * Get the recursive list of all files for this website, up to a timeout.
     * @return array List of file details.
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
                Log::Error("", $e);
                die(1);
            }
        }
    */
    /*
     foreach ($list as $fileEntry) {
     }
     */
}