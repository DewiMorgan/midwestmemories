<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * File processing class for DropboxManager.
 */
class FileProcessor extends Singleton
{

    /**
     * Process the first downloaded file from the file queue table.
     * Add thumbnails, resample images, and parse txt files, then set status to PROCESSED.
     * @return string "OK" or "Error: ...", depending on the result.
     */
    public function processOneFile(): string
    {
        $entry = $this->listFirstFileByStatus(SyncStatus::DOWNLOADED);
        Log::debug('Processing', $entry);
        $fullPath = ltrim($entry, '/\\');
        if (!file_exists($fullPath)) {
            $error = 'file_exists failed';
            $this->setSyncStatus($fullPath, SyncStatus::ERROR, $error);
            return "Error: $error";
        }

        // Get the mime type.
        $mimeType = mime_content_type($fullPath);
        echo "Processing as $mimeType: $fullPath<br>\n";
        $error = match ($mimeType) {
            'text/plain' => $this->processTextFile($fullPath),
            'image/gif' => $this->processGifFile($fullPath),
            'image/png' => $this->processPngFile($fullPath),
            'image/jpeg' => $this->processJpegFile($fullPath),
            default => $this->processOtherFile($fullPath),
        };
        return $error ? "Error: $error" : 'OK';
    }

    /**
     * Process text file, parsing fields into the db.
     * @return bool Success.
     */
    private function processTextFile($fullPath): bool
    {
        // ToDo: some parsing.
        $result = Db::sqlExec(
            '
                UPDATE `midmem_file_queue` 
                SET `sync_status` = ?
                WHERE full_path = ?
            ',
            'ss',
            SyncStatus::PROCESSED->value,
            $fullPath
        );
        return is_null($result);
    }

    /**
     * Process a PNG file, generating thumbnail and converting to JPG if needed.
     * @return bool Success.
     */
    private function processPngFile(string $fullPath): bool
    {
        if ((filesize($fullPath) > Conf::get(Key::MAX_PNG_BYTES))) {
            // Thumbnail generation would be faster from the new JPG, so we roll this into convertToJpeg.
            $thumbResult = $this->convertToJpeg($fullPath);
        } else {
            $thumbResult = $this->makeThumb(imagecreatefrompng($fullPath), $fullPath);
        }
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = $this->setSyncStatus($fullPath, $status, 'Processed as PNG.');
        return $thumbResult && $syncResult;
    }

    /**
     * Process a GIF file, generating thumbnail.
     * @return bool Success.
     */
    private function processGifFile(string $fullPath): bool
    {
        $thumbResult = $this->makeThumb(imagecreatefromgif($fullPath), $fullPath);
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = $this->setSyncStatus($fullPath, $status, 'Processed as GIF.');
        return $thumbResult && $syncResult;
    }

    /**
     * Process a JPG file, generating thumbnail.
     * @return bool Success.
     */
    private function processJpegFile(string $fullPath): bool
    {
        if (str_ends_with($fullPath, '-ICE.jpg')) {
            Log::debug('Processing (skip ICE thumb)', $fullPath);
            $thumbResult = true;
        } else {
            Log::debug('Processing', $fullPath);
            $thumbResult = $this->makeThumb(imagecreatefromjpeg($fullPath), $fullPath);
        }
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = $this->setSyncStatus($fullPath, $status, 'Processed as JPG.');
        return $thumbResult && $syncResult;
    }

    /**
     * Process an unknown file.
     * @return bool Success.
     */
    private function processOtherFile(string $fullPath): bool
    {
        // Nothing to do but mark it complete.
        $result = Db::sqlExec(
            "
                UPDATE `midmem_file_queue` 
                SET `sync_status` = ?, `error_message`='Unknown type' 
                WHERE full_path = ?
            ",
            'ss',
            SyncStatus::PROCESSED->value,
            $fullPath
        );
        return is_null($result);
    }

    // Privates

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
            Log::debug('Source image false for convertToJpeg', $fullPath);
            return false;
        }

        $newFullPath = dirname($fullPath) . '/' . basename($fullPath, '.png') . '.jpg';

        /* Save as a renamed JPG at its destination */
        if (false === imagejpeg($sourceImage, $newFullPath, 70)) {
            Log::debug('imagejpeg failed for convertToJpeg', $fullPath);
            return false;
        }
        // Try to delete the huge file. If we can't, no big loss.
        unlink($fullPath);

        // Because it is slightly faster to create the thumbnail from here.
        return $this->makeThumb(imagecreatefrompng($newFullPath), $newFullPath);
    }

    /**
     * From: https://stackoverflow.com/questions/11376315/creating-a-thumbnail-from-an-uploaded-image
     * @param resource $sourceImage Image resource loaded from whatever image format.
     * @param string $fullPath Target full path to original file.
     * @return bool success
     */
    private function makeThumb($sourceImage, string $fullPath): bool
    {
        Log::debug('Processing', $fullPath);
        if (false === $sourceImage) {
            Log::debug('Source image false for makeThumb', $fullPath);
            return false;
        }
        $dest = self::getThumbName($fullPath);
        // Read source image size.
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        if (false === $origWidth || false === $origHeight) {
            Log::debug('Source image dimensions false for makeThumb', [$origWidth, $origHeight, $fullPath]);
            return false;
        }
        $newWidth = $origWidth;
        $newHeight = $origHeight;
        // Scale to max height if needed.
        $maxHeight = Conf::get(Key::MAX_THUMB_HEIGHT);
        if ($origHeight > $maxHeight) {
            $newHeight = $maxHeight;
            $newWidth = floor($origWidth * ($newHeight / $origHeight));
        }
        // Scale further to max width if still too large.
        $maxWidth = Conf::get(Key::MAX_THUMB_WIDTH);
        if ($newWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = floor($origWidth * ($newWidth / $origWidth));
        }
        Log::debug(
            "vars: origWidth = $origWidth, origHeight = $origHeight, "
            . "newWidth = (i)$newWidth, newHeight = (i)$newHeight, dest = $dest."
        );

        $newWidth = (int)$newWidth;
        $newHeight = (int)$newHeight;
        /* Create a new, "virtual" image */
        $virtualImage = imagecreatetruecolor($newWidth, $newHeight);
        if (false === $virtualImage) {
            Log::debug('Virtual image dimensions false for makeThumb', $fullPath);
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
            Log::debug('imagecopyresampled failed for makeThumb', $fullPath);
            return false;
        }

        /* Create the physical thumbnail image at its destination */
        if (false === imagejpeg($virtualImage, $dest, 70)) {
            Log::debug('imagejpeg failed for makeThumb', $fullPath);
            return false;
        }
        Log::debug(
            "vars: origWidth = $origWidth, origHeight = $origHeight, "
            . "newWidth = $newWidth, newHeight = $newHeight, dest = $dest."
        );

        return true;
    }

    /**
     * Set the sync status of a given entry in the file queue.
     * @param string $fullPath The path (unique key) of the record to change.
     * @param SyncStatus $status The new status to give this record.
     * @param string $errorMessage Optional error message to log.
     * @return bool Success.
     */
    public function setSyncStatus(string $fullPath, SyncStatus $status, string $errorMessage = ''): bool
    {
        $result = Db::sqlExec(
            'UPDATE `midmem_file_queue` SET `sync_status` = ?, error_message = ? WHERE full_path = ?',
            'sss',
            $status->value,
            $errorMessage,
            $fullPath
        );
        return is_null($result);
    }


    /**
     * Get the list of files in a certain `sync_status`.
     * @return string[] List of file paths.
     */
    public function listFilesByStatus(SyncStatus $status): array
    {
        return Db::sqlGetList(
            'full_path',
            '
                SELECT `full_path` 
                FROM `midmem_file_queue`
                WHERE `sync_status` = ?
                ORDER BY `id`
            ',
            's',
            $status->value
        );
    }

    /**
     * Get the first of a list of files in a certain `sync_status`.
     * @return string List of file paths.
     */
    public function listFirstFileByStatus(SyncStatus $status): string
    {
        return Db::sqlGetItem(
            '
                SELECT `full_path`
                FROM `midmem_file_queue`
                WHERE `sync_status` = ?
                ORDER BY `id`
                LIMIT 1
            ',
            'full_path',
            's',
            $status->value
        );
    }

    /**
     * Download the first file from the file queue table.
     * @return string "OK" or "Error: ...", depending on the result.
     */
    public function downloadOneFile(): string
    {
        $dropbox = DropboxManager::getInstance();
        $untrimmedPath = $this->listFirstFileByStatus(SyncStatus::NEW);

        $fullPath = ltrim($untrimmedPath, '/\\');
        // If the dir doesn't exist, then create it.
        $dir = dirname($fullPath);
        // Repeat is_dir() check twice to ensure it either exists, or got created.
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            $error = "mkdir($dir,0700,true) failed";
            $this->setSyncStatus($fullPath, SyncStatus::ERROR, $error);
            return "Error: $error";
        }
        // Download the file from Dropbox. If it already exists, it might've been edited, so we get it anyway.
        $url = $dropbox->getTemporaryLink($untrimmedPath); // Requires NON-trimmed full path!
        $result = $dropbox->downloadFromUrl($url, $fullPath);
        // Update the DB to DOWNLOADED or ERROR.
        if ($result) {
            $status = SyncStatus::DOWNLOADED;
            $error = '';
        } else {
            $status = SyncStatus::ERROR;
            $error = 'False result from downloadFromUrl.';
        }
        $this->setSyncStatus($fullPath, $status, $error);
        return $error ? "Error: $error" : 'OK';
    }

    /**
     * Convert an image filename to a thumbnail filename, like 'foo/bar.png' => 'foo/tn_bar.jpg'.
     * Note: Files that begin with a dot and have no extension, like '.example', will get thumbs called 'tn_.jpg'.
     * @param string $imagePath Path and filename of the source image.
     * @return string The resulting filename.
     */
    public static function getThumbName(string $imagePath): string
    {
        return preg_replace('#([^/]*)\.[^/.]+?$#', 'tn_$1.jpg', $imagePath);
    }

}
