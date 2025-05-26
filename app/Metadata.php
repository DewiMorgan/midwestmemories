<?php

declare(strict_types=1);

namespace MidwestMemories;

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
     * Load in our data from an Ini file, and all parents, into the singleton's $folderTree datastore.
     * @param string $webPath The web path to load build and load the folder tree down to, from the root.
     */
    public static function loadFromInis(string $webPath): void
    {
        $webPathSoFar = '';
        $currentNode = &self::$folderTree;
        // "/var/www/path/to/file" => '/path/to/file' => ['', 'path', 'to', 'file']
        foreach (explode('/', $webPath) AS $pathElement) {
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
            Index::showError('Failed to parse ini file for this folder.');
            die(1);
        }

        return MetadataCleaner::cleanDirData($iniFileData);
    }

    /**
     * Get the directory's metadata entry for the given absolute file, or folder.
     * @see getFileDataByWebPath for more info.
     * @param string $unixFilePath The absolute unix file to get the information for.
     * @return array Subarray from the metadata in self::$folderTree.
     */
    public static function getFileDataByUnixPath(string $unixFilePath): array
    {
        return self::getFileDataByWebPath(Path::unixToWebPath($unixFilePath));
    }

    /**
     * Get the directory's metadata entry for the given absolute file, or folder.
     * Since filenames are inserted in the "data" element, "path/to/file.txt" matches:
     *   ['path'=>['to'=>['data'=>['file.txt'=>[the array that gets returned]]]]]
     * However, folders are inserted in the '/' element, so "path/to/folder/" matches:
     *   ['path'=>['to'=>['folder'=>['/'=>[the array that gets returned]]]]]
     * @param string $webFilePath The absolute web file to get the information for. NOT relative!
     * @param bool $loadIfNotFound True (default) if we can try loading file if the folder is not yet loaded.
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
}
