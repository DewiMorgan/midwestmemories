<?php

declare(strict_types=1);

namespace MidwestMemories;

use Exception;
use MidwestMemories\Enum\Key;
use SplFileObject;

/**
 * Purely static class to handle moving metadata between ini files, DB, web display, and web form.
 * Metadata can be inherited from parent folders, etc. So we need to store the entire tree, lazy-loaded.
 * At the same time, we don't want to be passing in the path we're talking about all the time.
 * So we should have a static tree object, and each instance has its own path object.
 * Then to save it out, we need to mark the stuff that changed, and write out only the dirty folders.
 * ToDo: loadFromMysql(), saveToMysql().
 */
class Metadata
{
    /**
     * Tree of folders in web path, starting from root, each with a 'data' element from the ini file for that folder.
     * That's a dict of string properties for the folder, and sub-arrays for properties for each file in the folder.
     * If a folder has a 'dirty' element that's true, then it has been modified.
     */
    private static array $folderTree = [];

    /**
     * Get the directory's metadata entry for the given absolute file or folder, escaped for HTML.
     * @param string $requestUnixPath The absolute unix file to get the information for.
     * @return array Subarray from the metadata in self::$folderTree.
     * @see getFileDataByWebPath for more info.
     */
    public static function getEscapedByUnixPath(string $requestUnixPath): array
    {
        $path = Path::unixToWebPath($requestUnixPath);
        return self::htmlEscape(self::getFileDataByWebPath($path));
    }

    /**
     * Get the directory's metadata entry for the given absolute file, or folder.
     * Since filenames are inserted in the "data" element, "path/to/file.txt" matches:
     *   ['path'=>['to'=>['data'=>['file.txt'=>[the array that gets returned]]]]]
     * However, folders are inserted in the '/' element, so "path/to/folder/" matches:
     *   ['path'=>['to'=>['folder'=>['/'=>[the array that gets returned]]]]]
     * @param string $webFilePath The absolute web file to get the information for. NOT relative!
     * @param bool $loadIfNotFound True (default) if we can try loading the file if the folder is not yet loaded.
     * @return array Subarray from the metadata in self::$folderTree.
     * ToDo: this does not yet handle inherited data.
     *       Data from all parent folders isn't loaded at all.
     *       Saving inherited data: do we save it only if it was modified? Seems sensible.
     *       How can the caller distinguish inherited data in the returned data structure? Do they need to?
     *       Should I instead have a getInheritedValue($filename, $key), for templates to call for missing values?
     */
    public static function getFileDataByWebPath(string $webFilePath, bool $loadIfNotFound = true): array
    {
        $segments = explode('/', trim($webFilePath, '/'));
        Log::debug('Segments 1: ', $segments);

        if ($webFilePath[-1] === '/') {
            // This is a folder, as last character is slash, so append a slash element.
            $segments[] = '/';
        } else {
            // This is a file, so insert the 'data' element.
            array_splice($segments, -1, 0, ['data']);
        }

        // Reference to traverse the array.
        $currentLevel = self::$folderTree;

        foreach ($segments as $segment) {
            if (is_array($currentLevel) && array_key_exists($segment, $currentLevel)) {
                $currentLevel = $currentLevel[$segment]; // Go one level deeper
            } else {
                if ($loadIfNotFound) {
                    self::loadFromInis(dirname($webFilePath));
                    return self::getFileDataByWebPath($webFilePath, false);
                }
                return [];
            }
        }

        if ($currentLevel === self::$folderTree) {
            Log::warning('Never iterated over segments: ', $segments);
        }

        if (is_array($currentLevel)) {
            return $currentLevel;
        }
        Log::warning("File entry at '$webFilePath' was not an array: returning empty.", $currentLevel);
        return [];
    }

    /**
     * Get the ID of the displayed file.
     * @return int
     */
    public static function getFileId(): int
    {
        $webPath = IndexGateway::$requestWebPath;
        $dropboxPath = Conf::get(Key::IMAGE_DIR) . $webPath;
        $sql = 'SELECT `id` FROM `' . Table::file_queue() . '` WHERE `full_path` = ?';
        return intval(Db::sqlGetValue('id', $sql, 's', $dropboxPath));
    }

    /**
     * Convert the raw file details into an HTML-escaped version.
     * @param array $fileDetails Array from which to HTML escape all fields.
     * @return array The resulting escaped array.
     */
    public static function htmlEscape(array $fileDetails): array
    {
        $htmlEscaped = [];
        foreach ($fileDetails as $key => $fileDetail) {
            if (is_array($fileDetail)) {
                if ('date' === $key) {
                    $htmlEscaped[$key] = htmlspecialchars($fileDetail['dateString']);
                } else {
                    $htmlEscaped[$key] = htmlspecialchars(implode(', ', $fileDetail));
                }
            } elseif (is_numeric($fileDetail)) {
                $htmlEscaped[$key] = $fileDetail;
            } elseif (is_string($fileDetail) && '' !== $fileDetail) {
                $htmlEscaped[$key] = htmlspecialchars($fileDetail);
            } else {
                $htmlEscaped[$key] = match ($key) {
                    'slideorigin', 'slidenumber', 'slidesubsection' => '?',
                    'displayname' => 'unknown image',
                    default => 'unknown',
                };
            }
        }
        return $htmlEscaped;
    }

    /**
     * Load in our data from a CSV file into the singleton's $folderTree datastore.
     * @param string $unixFilePath The web path to load the file from.
     * Header can contain:
     * 'Filename YYYYMMDD - Origin - #','Origin','Number','Bundle','Slide Txt','ICE?','Directory',,
     * 'Filename YYYYMMDD - Origin - #','Origin','Number','Subsection','Slide Txt','ICE?','Directory',,
     * // TODO: split this into two functions: readCsvFile and write/updateIniFiles.
     */
    public static function csvToIniFiles(string $unixFilePath): void
    {
        $csv = [];
        $rows = array_map('str_getcsv', file('myfile.csv'));
        array_shift($rows); // Skip the header row.

        // Build a list of all file details, parsed-ini-file style, grouped by their directory.
        foreach ($rows as $row) {
            $filename = $row[0];              // "Filename" csv column.
            $date = preg_replace('/^(....)(..)(..).*$/', '$1-$2-$3', $filename);
            $fileDetails = [];
            $fileDetails["$filename.jpg"] = [
                'date' => $date,
                'displayname' => $filename,
                'slideorigin' => $row[1],     // "Origin" csv column.
                'slidenumber' => $row[2],     // "Number" csv column.
                'slidesubsection' => $row[3], // "Bundle" or "Subsection" csv column.
                'writtennotes' => $row[4],    // "Slide Txt" csv column.
                'filtered' => $row[5],        // "ICE?" csv column.
            ];
            // "Directory" csv column. Clean up slashes and dots.
            $unixPath = preg_replace(['#[/\\\]+#', '#\.\.+#'], ['/', '.'], Path::$imgBaseUnixPath . $row[6]);
            // Create the entry for the directory if it doesn't exist.
            if (!array_key_exists($unixPath, $csv)) {
                $csv[$unixPath] = [];
            }
            // Add this file's details to the directory's entry.
            $csv[$unixPath][] = $fileDetails;
        }

        // Write the INI files.
        foreach ($csv as $unixPath => $fileDetailList) {
            // Create the directory if it doesn't exist.
            if (!file_exists($unixPath)) {
                mkdir($unixPath, 0777, true);
            }

            // If the ini file already exists, replace conflicting entries and append new ones.
            if (file_exists($unixPath . '/index.txt')) {
                $existingContent = parse_ini_file($unixPath . '/index.txt', true);
                if ($existingContent) {
                    $fileDetailList = array_merge($existingContent, $fileDetailList);
                }
            }

            // Write the updated (or new) INI file.
            file_put_contents($unixPath . '/index.txt', implode("\n\n", $fileDetailList), FILE_APPEND);
        }
    }


    /**
     * Load in our data from an Ini file, and all parents, into the singleton's $folderTree datastore.
     * @param string $webPath The web path to load the folder tree down to, from the root.
     */
    public static function loadFromInis(string $webPath): void
    {
        $webPathSoFar = '';
        $currentNode = &self::$folderTree;
        // "/var/www/path/to/file" => '/path/to/file' => ['', 'path', 'to', 'file']
        foreach (explode('/', $webPath) as $pathElement) {
            // Build the folder tree to the branch we're interested in.
            if ($pathElement !== '') {
                if (!array_key_exists($pathElement, $currentNode)) {
                    $currentNode[$pathElement] = [];
                }
                $currentNode = &$currentNode[$pathElement];
            }
            // Create the data only if it doesn't already exist.
            if (!array_key_exists('data', $currentNode)) {
                $currentNode['data'] = [];
            }
            $webPathSoFar .= '/' . $pathElement;
            $webPathSoFar = preg_replace('#//#', '/', $webPathSoFar);
            if (empty($currentNode['data'])) {
                $currentNode['data'] = self::loadOneFolderIni($webPathSoFar);
            }
        }
    }

    /**
     * Load data block for a single folder, and return it without changing the singleton's datastore.
     * @param string $webPath Web path to parse.
     * @return array|array[] The array structure read for that folder.
     */
    private static function loadOneFolderIni(string $webPath): array
    {
        $iniUnixPath = Path::webToUnixPath(preg_replace('#//#', '/', "$webPath/index.txt"), false);
        if (!file_exists($iniUnixPath)) {
            Log::debug("loadFolderIni found no ini from webPath $webPath at unix path", $iniUnixPath);
            // Can't print this as we call it for every parent/ancestor folder, too.
            // Index::showError('No ini file for this folder.');
            return [];
        }
        Log::debug("loadFolderIni found ini from webPath $webPath at unix path", $iniUnixPath);

        $iniFileData = parse_ini_file($iniUnixPath, true);

        if (false === $iniFileData) {
            Log::error('loadFolderIni failed to parse ini file', $webPath);
            IndexGateway::showError('Failed to parse ini file for this folder.');
            die(1);
        }

        return MetadataCleaner::cleanDirData($iniFileData);
    }
}
