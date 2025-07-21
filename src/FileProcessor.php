<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;
use MidwestMemories\Enum\SyncStatus;

/**
 * File processing class for DropboxManager.
 * Fully static. OOP might save us from passing $unixPath around, but nothing else is shared.
 */
class FileProcessor
{
    /**
     * Endpoint: POST `/api/v1.0/process`. Process the first downloaded file from the file queue table.
     * Add thumbnails, resample images, and parse txt files, then set status to PROCESSED.
     * @return array ['status'=>200 or 500, 'data'=>"OK" or "Error: ..."], depending on the result.
     */
    public static function processNextFile(): array
    {
        $entry = self::listFirstFileByStatus(SyncStatus::DOWNLOADED);
        Log::debug('Processing', $entry);
        $unixPath = ltrim($entry, '/\\');
        if (!file_exists($unixPath)) {
            $error = 'file_exists failed';
            self::setSyncStatus($unixPath, SyncStatus::ERROR, $error);
            return ['status' => 500, 'data' => "Server Error: $error"];
        }

        // Get the mime type.
        $mimeType = mime_content_type($unixPath);
        Log::debug("Processing as $mimeType", $unixPath);
        $success = match ($mimeType) {
            'text/csv' => FileProcessTypes::processCsvFile($unixPath),
            'image/gif' => FileProcessTypes::processGifFile($unixPath),
            'image/jpeg' => FileProcessTypes::processJpegFile($unixPath),
            'image/png' => FileProcessTypes::processPngFile($unixPath),
            'text/plain' => FileProcessTypes::processTextFile($unixPath),
            default => FileProcessTypes::processOtherFile($unixPath),
        };
        $data = $success ? 'OK' : "Error: failed to process file as $mimeType";
        $status = $success ? 200 : 500;
        return ['status' => $status, 'data' => $data];
    }

    /**
     * Convert large PNG files to more-compressed jpgs.
     * ToDo: How should this be reflected in the DB?
     * @param string $unixPath Full path to original file.
     * @return bool success
     */
    public static function convertToJpeg(string $unixPath): bool
    {
        $sourceImage = imagecreatefrompng($unixPath);
        if (false === $sourceImage) {
            Log::debug('Source image false for convertToJpeg', $unixPath);
            return false;
        }

        $newFullPath = dirname($unixPath) . '/' . basename($unixPath, '.png') . '.jpg';

        /* Save as a renamed JPG at its destination */
        if (false === imagejpeg($sourceImage, $newFullPath, 70)) {
            Log::debug('imagejpeg failed for convertToJpeg', $unixPath);
            return false;
        }
        // Try to delete the huge file. If we can't, no big loss.
        unlink($unixPath);

        // Because it is slightly faster to create the thumbnail from here.
        return self::makeThumb(imagecreatefrompng($newFullPath), $newFullPath);
    }

    /**
     * From: https://stackoverflow.com/questions/11376315/creating-a-thumbnail-from-an-uploaded-image
     * @param resource $sourceImage Image resource loaded from whatever image format.
     * @param string $unixPath Target full path to original file.
     * @return bool success
     */
    public static function makeThumb($sourceImage, string $unixPath): bool
    {
        Log::debug('Processing', $unixPath);
        if (false === $sourceImage) {
            Log::debug('Source image false for makeThumb', $unixPath);
            return false;
        }
        $dest = self::getThumbName($unixPath);
        // Read source image size.
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        if (false === $origWidth || false === $origHeight) {
            Log::debug('Source image dimensions false for makeThumb', [$origWidth, $origHeight, $unixPath]);
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
            Log::debug('Virtual image dimensions false for makeThumb', $unixPath);
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
            Log::debug('imagecopyresampled failed for makeThumb', $unixPath);
            return false;
        }

        /* Create the physical thumbnail image at its destination */
        if (false === imagejpeg($virtualImage, $dest, 70)) {
            Log::debug('imagejpeg failed for makeThumb', $unixPath);
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
     * @param string $unixPath The path (unique key) of the record to change.
     * @param SyncStatus $status The new status to give this record.
     * @param string $errorMessage Optional error message to log.
     * @return bool Success.
     */
    public static function setSyncStatus(string $unixPath, SyncStatus $status, string $errorMessage = ''): bool
    {
        $result = Db::sqlExec(
            'UPDATE `' . Table::file_queue() . '` SET `sync_status` = ?, error_message = ? WHERE full_path = ?',
            'sss',
            $status->value,
            $errorMessage,
            $unixPath
        );
        return !empty($result);
    }

    /**
     * Endpoint: GET `/api/v1.0/download`. Get the list of new files to download.
     * @return array ['data' => List of file paths.]
     */
    public static function listNewFiles(): array
    {
        return self::listFilesByStatus(SyncStatus::NEW);
    }

    /**
     * Endpoint: GET `/api/v1.0/download`. Get the list of files downloaded and ready for postprocessing.
     * @return array ['data' => List of file paths.]
     */
    public static function listDownloadedFiles(): array
    {
        return self::listFilesByStatus(SyncStatus::DOWNLOADED);
    }

    /**
     * Get the list of files in a certain `sync_status`.
     * @return string[] List of file paths.
     */
    public static function listFilesByStatus(SyncStatus $status): array
    {
        $data = Db::sqlGetList(
            'full_path',
            '
                SELECT `full_path` 
                FROM `' . Table::file_queue() . '`
                WHERE `sync_status` = ?
                ORDER BY `id`
            ',
            's',
            $status->value
        );
        return ['data' => $data];
    }

    /**
     * Get the first of a list of files in a certain `sync_status`.
     * @return string List of file paths.
     */
    private static function listFirstFileByStatus(SyncStatus $status): string
    {
        return Db::sqlGetValue(
            'full_path',
            '
                SELECT `full_path`
                FROM `' . Table::file_queue() . '`
                WHERE `sync_status` = ?
                ORDER BY `id`
                LIMIT 1
            ',
            's',
            $status->value
        );
    }

    /**
     * Endpoint: POST `/api/v1.0/download`. Download the first pending file from the file queue table.
     * @return array API response array, not yet JSON encoded.
     */
    public static function downloadNextFile(): array
    {
        $dropbox = DropboxManager::getInstance();
        $untrimmedPath = self::listFirstFileByStatus(SyncStatus::NEW);

        $unixPath = ltrim($untrimmedPath, '/\\');
        // If the dir doesn't exist, then create it.
        $dir = dirname($unixPath);
        // Repeat is_dir() check twice to ensure it either exists, or got created.
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            $error = "mkdir($dir,0700,true) failed";
            self::setSyncStatus($unixPath, SyncStatus::ERROR, $error);
            Log::error($error, $unixPath);
            return ['status' => 500, 'data' => "Error: $error"];
        }
        // Download the file from Dropbox. If it already exists, it might've been edited, so we get it anyway.
        $url = $dropbox->getTemporaryLink($untrimmedPath); // Requires NON-trimmed full path!
        $result = $dropbox->downloadFromUrl($url, $unixPath);
        // Update the DB to DOWNLOADED or ERROR.
        if ($result) {
            $syncStatus = SyncStatus::DOWNLOADED;
            $error = '';
            $httpStatus = 200;
        } else {
            $syncStatus = SyncStatus::ERROR;
            $error = 'False result from downloadFromUrl.';
            $httpStatus = 500;
        }
        self::setSyncStatus($unixPath, $syncStatus, $error);
        $data = $error ? "Error: $error" : 'OK';
        return ['status' => $httpStatus, 'data' => $data];
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
