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
    public static function processGifFile(string $unixPath): bool
    {
        $thumbResult = FileProcessor::makeThumbs(imagecreatefromgif($unixPath), $unixPath);
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = FileProcessor::setSyncStatus($unixPath, $status, 'Processed as GIF.');
        return $thumbResult && $syncResult;
    }

    /**
     * Process text file, parsing fields into the db.
     * @return bool Success.
     */
    public static function processTextFile($unixPath): bool
    {
        return FileProcessor::setSyncStatus($unixPath, SyncStatus::PROCESSED, 'Processed as TXT.');
    }

    /**
     * Process a CSV file, generating/updating the per-folder INI files from it.
     * @return bool Success.
     */
    public static function processCsvFile(string $unixPath): bool
    {
        $iniResult = Metadata::csvToIniFiles($unixPath);
        $status = ($iniResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = FileProcessor::setSyncStatus($unixPath, $status, 'Processed as CSV.');
        return $iniResult && $syncResult;
    }

    /**
     * Process an unknown file.
     * @return bool Success.
     */
    public static function processOtherFile(string $unixPath): bool
    {
        // Nothing to do but mark it complete.
        return FileProcessor::setSyncStatus($unixPath, SyncStatus::PROCESSED, 'Unknown type');
    }

    /**
     * Process a PNG file, generating thumbnail and converting to JPG if needed.
     * @return bool Success.
     */
    public static function processPngFile(string $unixPath): bool
    {
        if ((filesize($unixPath) > Conf::get(Key::MAX_PNG_BYTES))) {
            // Thumbnail generation would be faster from the new JPG, so we roll this into convertToJpeg.
            $thumbResult = FileProcessor::convertToJpeg($unixPath);
        } else {
            $thumbResult = FileProcessor::makeThumbs(imagecreatefrompng($unixPath), $unixPath);
        }
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = FileProcessor::setSyncStatus($unixPath, $status, 'Processed as PNG.');
        return $thumbResult && $syncResult;
    }

    /**
     * Process a JPG file, generating thumbnail.
     * @return bool Success.
     */
    public static function processJpegFile(string $unixPath): bool
    {
        if (str_ends_with($unixPath, '-ICE.jpg')) {
            Log::debug('Processing (skip ICE thumb)', $unixPath);
            $thumbResult = true;
        } else {
            Log::debug('Processing', $unixPath);
            $thumbResult = FileProcessor::makeThumbs(imagecreatefromjpeg($unixPath), $unixPath);
        }
        $status = ($thumbResult ? SyncStatus::PROCESSED : SyncStatus::ERROR);
        $syncResult = FileProcessor::setSyncStatus($unixPath, $status, 'Processed as JPG.');
        return $thumbResult && $syncResult;
    }
}
