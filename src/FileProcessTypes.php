<?php
declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;
use MidwestMemories\Enum\SyncStatus;

/**
 * Handler for the various filetypes that need post-processing.
 * Just a way to extract the logic from FileProcessor.
 * In both classes, the logic is all static.
 */
class FileProcessTypes
{

    /**
     * Process a GIF file, generating thumbnail.
     * @return bool Success.
     */
    public static function processGifFile(string $fullPath): bool
    {
        $thumbResult = FileProcessor::makeThumb(imagecreatefromgif($fullPath), $fullPath);
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = FileProcessor::setSyncStatus($fullPath, $status, 'Processed as GIF.');
        return $thumbResult && $syncResult;
    }

    /**
     * Process text file, parsing fields into the db.
     * @return bool Success.
     */
    public static function processTextFile($fullPath): bool
    {
        return FileProcessor::setSyncStatus($fullPath, SyncStatus::PROCESSED, 'Processed as TXT.');
    }

    /**
     * Process a GIF file, generating thumbnail.
     * @return bool Success.
     */
    public static function processCsvFile(string $fullPath): bool
    {
        $thumbResult = FileProcessor::makeThumb(imagecreatefromgif($fullPath), $fullPath);
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = FileProcessor::setSyncStatus($fullPath, $status, 'Processed as GIF.');
        return $thumbResult && $syncResult;
    }

    /**
     * Process an unknown file.
     * @return bool Success.
     */
    public static function processOtherFile(string $fullPath): bool
    {
        // Nothing to do but mark it complete.
        return FileProcessor::setSyncStatus($fullPath, SyncStatus::PROCESSED, 'Unknown type');
    }

    /**
     * Process a PNG file, generating thumbnail and converting to JPG if needed.
     * @return bool Success.
     */
    public static function processPngFile(string $fullPath): bool
    {
        if ((filesize($fullPath) > Conf::get(Key::MAX_PNG_BYTES))) {
            // Thumbnail generation would be faster from the new JPG, so we roll this into convertToJpeg.
            $thumbResult = FileProcessor::convertToJpeg($fullPath);
        } else {
            $thumbResult = FileProcessor::makeThumb(imagecreatefrompng($fullPath), $fullPath);
        }
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = FileProcessor::setSyncStatus($fullPath, $status, 'Processed as PNG.');
        return $thumbResult && $syncResult;
    }

    /**
     * Process a JPG file, generating thumbnail.
     * @return bool Success.
     */
    public static function processJpegFile(string $fullPath): bool
    {
        if (str_ends_with($fullPath, '-ICE.jpg')) {
            Log::debug('Processing (skip ICE thumb)', $fullPath);
            $thumbResult = true;
        } else {
            Log::debug('Processing', $fullPath);
            $thumbResult = FileProcessor::makeThumb(imagecreatefromjpeg($fullPath), $fullPath);
        }
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = FileProcessor::setSyncStatus($fullPath, $status, 'Processed as JPG.');
        return $thumbResult && $syncResult;
    }
}
