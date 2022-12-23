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
    public const DROPBOX_USER_ID = 16181197;
    private const MAX_PNG_SIZE = 1024 * 1024; // Max png size before resampling to jpg.
    private const MAX_THUMB_WIDTH = 64;
    private const MAX_THUMB_HEIGHT = 64;

    public function __construct() {
        $tokenRefresher = new TokenRefresher();
        $token = $tokenRefresher->getToken();

        $this->client = new Client($token);
    }

    /**
     * Get the recursive list of all files for this website. Might be LONG.
     * @return string of file details.
     */
    public function initRootCursor(): array {
        $this->iterations = 0;
        $this->entries = 1;
        $result = [];
        $endTime = time() + 20;
        try {
            $list = $this->client->listFolder('', true);
            if (array_key_exists('entries', $list)) {
                $this->iterations = 1;
                $this->saveCusorFromList($list);
                $result = $this->getListOfEntries($result, $list);
            }
            while (array_key_exists('has_more', $list) && $list['has_more'] && $this->cursor && time() < $endTime) {
                $list = $this->client->listFolderContinue($this->cursor);
                $this->saveCusorFromList($list);
                $result = $this->getListOfEntries($result, $list);
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
    public function continueRootCursor(int $entriesSoFar): array {
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
                $result = $this->getListOfEntries($result, $list);
                $this->iterations ++;
            }
            return $result;
        } catch (Exception $e) {
            die(var_export($e));
        }
        return $result;
    }

    /**
     * Get the list of updated files for the given cursor, up to a timeout.
     * @param int $entriesSoFar How many antries have already been processed in this run of the script.
     * @return string of file details.
     */
    public function checkRootCursorForUpdates(int $entriesSoFar): array {
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
                $result = $this->getListOfEntries($result, $list);
                $result = $this->saveListOfFilesToProcess($list['entries']);
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
    private function getListOfEntries($result, $list): array {
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
     * @return number of files added to list.
     */
    private function saveListOfFilesToProcess($list): int {
        $numberOfFiles = 0;
        foreach ($list as $entry) {
            // Skip anything we don't care about.
            if ('file' !== $entry['.tag'] || !preg_match('#^/midwestmemories/#', $entry['path_lower'])) {
                continue;
            }
            $numberOfFiles++;
            // Could check other .tag='file' fields, like 'is_downloadable'? But that should always be true, I think.
            // Could check 'content_hash' to see if it's unchanged? But if it's in our list, it should be changed.
            // Could check .tag='deleted' later in the list to see if it gets deleted again, but not an issue til we handle deletion anyway.
            Db::sqlExec('INSERT INTO `midmem_file_queue` (`file_name`, `full_path`, `sync_status`) VALUES (?, ?, ?)', 'sss', $entry['name'], $entry['path_display'], 'NEW');
        }
        return $numberOfFiles;
    }

    public function processFilesFromDb(): void {
        $endTime = time() + 20;
        $list = Db::sqlGetTable("SELECT * FROM `midmem_file_queue` WHERE `sync_status` = 'NEW'");
        foreach ($list as $entry) {
            // Drop out early if we hit the time limit.
            if (time() > $endTime) {
                return;
            }
            // If the dir doesn't exist, then create it.
            $dir = dirname($entry['full_path']);
            if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
                Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = 'ERROR', `error_mesage` = 'mkdir failed' WHERE full_path = ?", 's', $entry['full_path']);
                continue;
            }
            // Download the file from Dropbox. If it already exists, it might've been edited, so we get it anyway.
            $url = $this->client->getTemporaryLink($entry['full_path']);
            $result = $this->downloadUrlToPath($url, $entry['full_path']);
            // Update the DB to DOWNLOADED or ERROR.
            Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = ? WHERE full_path = ?", 'ss', ($result ? 'DOWNLOADED' : 'ERROR'), $entry['full_path']);
        }
    }

    /*
    * Add thumbnails, resample images, and parse txt files, then set status to PROCESSED.
    */
    public function downloadedfilehandler() {
        $endTime = time() + 20;
        $list = Db::sqlGetTable("SELECT * FROM `midmem_file_queue` WHERE `sync_status` = 'DOWNLOADED'");
        foreach ($list as $entry) {
            // Drop out early if we hit the time limit.
            if (time() > $endTime) {
                return;
            }
            $fullPath = $entry['full_path'];
            if (!file_exists($fullPath)) {
                Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = 'ERROR', `error_mesage` = 'file_exists failed' WHERE full_path = ?", 's', $fullPath);
                continue;
            }

            // Get the mime type.
            $mimeType = mime_content_type($fullPath);
            switch($mimeType) {
                case 'text/plain':
                    $this->processTextFile($fullPath);
                    break;
                case 'image/gif':
                    $this->processGifFile($fullPath);
                case 'image/png':
                    $this->processPngFile($fullPath);
                case 'image/jpeg':
                    $this->processJpegFile($fullPath);
                case 'directory':
                case 'application/x-empty': // Zero length file
                default:
                    $this->processUnknownFile($fullPath);
                    break;
            }
        }
    }

    /**
    * Process text file, parsing fields into the db.
    */
    private function processTextFile($fullPath): void {
        // ToDo: some parsing.
        Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = 'PROCESSED' WHERE full_path = ?", 's', $fullPath);
    }
    /** Process a png file, generating thumbnail and converting to jpg if needed.*/
    private function processPngFile($fullPath) {
        if ((filesize($fullPath) > self::MAX_PNG_SIZE)) {
            // Thumbnail generation would be faster from the new jpg so we roll this into convertToJpeg.
            $result = $this->convertToJpeg($fullPath);
        } else {
            $result = $this->makeThumb(imagecreatefrompng($fullPath), $fullPath);
        }
        Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = ? WHERE full_path = ?", 'ss', ($result ? 'PROCESSED' : 'ERROR'), $fullPath);
    }
    /** Process a gif file, generating thumbnail.*/
    private function processGifFile($fullPath) {
        $result = $this->makeThumb(imagecreatefromgif($fullPath), $fullPath);
        Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = ? WHERE full_path = ?", 'ss', ($result ? 'PROCESSED' : 'ERROR'), $fullPath);
    }
    /** Process a jpeg file, generating thumbnail.*/
    private function processJpegFile($fullPath) {
        $result = $this->makeThumb(imagecreatefromjpeg($fullPath), $fullPath);
        Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = ? WHERE full_path = ?", 'ss', ($result ? 'PROCESSED' : 'ERROR'), $fullPath);
    }
    /** Process an unknown file.*/
    private function processUnknownFile($fullPath) {
        // Nothing to do but mark it complete.
        Db::sqlExec("UPDATE `midmem_file_queue` SET `sync_status` = 'PROCESSED', `error_message`='Unknown type' WHERE full_path = ?", 's', $fullPath);
    }

    /** save the current cursor to the DB. */
    public function saveCursor(): void {
        Db::sqlExec("INSERT INTO `midmem_dropbox_users` (`user_id`, `cursor_id`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `cursor_id` = ?", 'dss', self::DROPBOX_USER_ID, $this->cursor, $this->cursor);
    }

    /** Load the current cursor from the DB. */
    private function loadCursor(): void {
        $this->cursor = Db::sqlGetItem("SELECT `cursor_id` FROM `midmem_dropbox_users` LIMIT 1", 'cursor_id');
    }

    /**
    * From: https://stackoverflow.com/questions/11376315/creating-a-thumbnail-from-an-uploaded-image
    * @param resource $sourceImage Image resource loaded from whatever image format.
    * @param string $fullPath Target full path to original file.
    * @param int $maxWidth Thumbnail max width in pixels.
    * @param int $maxHeight Thumbnail max height in pixels.
    * @return bool success
    */
    private function makeThumb($sourceImage, string $fullPath): bool {
        if (false === $sourceImage) {
            Db::adminDebug('Source image false for makeThumb', $fullPath);
            return false;
        }
        // Files that begin with a dot and have no estension, eg '.example', will get thumbs called 'tn_.jpg'.
        $basename = preg_replace('/\..+?$/', '', basename($fullPath));
        $dest = dirname($fullPath) . '/tn_' . $basename . 'jpg';
        // Read source image size.
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        if (false === $origWidth || false === $origHeight) {
            Db::adminDebug('Source image dimensions false for makeThumb', [$origWidth, $origHeight, $fullPath]);
            return false;
        }
        $newWidth = $origWidth;
        // Scale to max width if needed.
        if ($origHeight > self::MAX_THUMB_HEIGHT) {
            $newHeight = self::MAX_THUMB_HEIGHT;
            $newWidth = floor($origWidth * ($newHeight/$origHeight));
        }
        // Scale to max height if still too large.
        if ($newWidth > self::MAX_THUMB_WIDTH) {
            $newWidth = self::MAX_THUMB_WIDTH;
            $newHeight = floor($origWidth * ($newWidth/$origWidth));
        }

        /* Create a new, "virtual" image */
        $virtualImage = imagecreatetruecolor($newWidth, $newHeight);
        if (false === $virtualImage) {
            Db::adminDebug('Virtualimage dimensions false for makeThumb', $fullPath);
            return false;
        }

        /* Resize and copy source image to new image */
        if (false === imagecopyresampled($virtualImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight)) {
            Db::adminDebug('imagecopyresampled failed for makeThumb', $fullPath);
            return false;
        }

        /* Create the physical thumbnail image at its destination */
        if (false === imagejpeg($virtualImage, $dest, 70)) {
            Db::adminDebug('imagejpeg failed for makeThumb', $fullPath);
            return false;
        }

        return true;
    }

    /**
    * Convert large png files to more-compressed jpgs.
    * ToDo: How should this be reflected in the DB?
    * @param string $fullPath Full path to original file.
    * @return bool success
    */
    private function convertToJpeg(string $fullPath): bool {
        $sourceImage = imagecreatefrompng($fullPath);
        if (false === $sourceImage) {
            Db::adminDebug('Source image false for convertToJpeg', $fullPath);
            return false;
        }

        $dest = dirname($fullPath) . '/' . basename($fullPath, '.png') . '.jpg';

        /* Save as a renamed jpg at its destination */
        if (false === imagejpeg($sourceImage, $dest, 70)) {
            Db::adminDebug('imagejpeg failed for convertToJpeg', $fullPath);
            return false;
        }
        // Try to delete the huge file. If we can't, no big loss.
        @unlink($fullPath);

        // Because it's slightly faster to create the thumbnail from here.
        return $this->makeThumb(imagecreatefrompng($dest));
    }


    /**
    From https://stackoverflow.com/questions/6409462/downloading-a-large-file-using-curl
    */
    public function downloadUrlToPath(string $url, string $fullPath): bool {
        set_time_limit(0);
        $success = false;
        $ch = false;
        $fp = false;

        // Check and log each step of downloading the file. Daisychained elseif ensures handles get closed at the end.
        if (false === $fp = fopen($fullPath, 'w+')) {
            Db::adminDebug('fopen failed for downloadUrlToPath', [$url, $fullPath]);
        } elseif (false === $ch = curl_init($url)) {
            Db::adminDebug('curl_init failed for downloadUrlToPath', [$url, $fullPath]);
        } elseif (false === (
            // if timeout (seconds) is too low, download will be interrupted
            curl_setopt($ch, CURLOPT_TIMEOUT, 600)
            && curl_setopt($ch, CURLOPT_FILE, $fp) // Write curl response to file
            && curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true)
            // These two only if https certificate isn't recognized.
            // && curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0)
            // && curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0)
        )) {
            Db::adminDebug('curl_setopt failed for downloadUrlToPath', [$url, $fullPath, curl_error($ch)]);
        } elseif (false === curl_exec($ch)) { // Get curl response
            Db::adminDebug('curl_exec failed for downloadUrlToPath', [$url, $fullPath, curl_error($ch)]);
        } else if (false === file_exists($fullPath)) {
            Db::adminDebug('File cretion failed for downloadUrlToPath', [$url, $fullPath]);
        } else {
            Db::adminDebug('Success: downloadUrlToPath', [$url, $fullPath]);
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