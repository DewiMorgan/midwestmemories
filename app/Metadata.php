<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Class to handle moving metadata between ini files, DB, web display, and web form.
 * Metadata can be inherited from parent folders, etc. So we need to store the entire tree, lazy-loaded.
 * At the same time, we don't want to be passing in the path we're talking about all the time.
 * So we should have a static tree object, and each instance has its own path object.
 * Then to save it out, we need to mark the stuff that changed, and write out only the dirty folders.
 */
class Metadata
{

    /**
     * @var array Tree of folders in web path, each containing a 'data' element from the ini file for that folder.
     * That's a dict of string properties for the folder, and sub-arrays for properties for each file in the folder.
     * If a folder has an 'isDirty' element that's true, then it has been modified.
     */
    private static array $folderTree = [];

    /**
     * @param string $webPath Web path. Promoted property. This is assumed validated and sanitized.
     */
    public function __construct(public string $webPath)
    {
    }

    /**
     * Load in our data from an Ini file, and all parents.
     * @param string|null $webPath The web path to load build and load the folder tree down to, from the root.
     */
    public function loadFromInis(string $webPath = null): void
    {
        $webPathSoFar = '';
        if (is_null($webPath)) {
            $webPath = $this->webPath;
        }
        $currentNode = &self::$folderTree;
        // "/var/www/path/to/file' => '/path/to/file' => ['', 'path', 'to', 'file']
        foreach (explode('/', $webPath) AS $pathElement) {
            // Build the folder tree to the branch we're interested in.
            if ($pathElement != '') {
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
                $currentNode['data'] = $this->loadFolderIni($webPathSoFar);
            }
        }
    }

    /**
     * Load data block for a single folder.
     * @param string $webPath Web path to parse.
     * @return array|array[] The array structure read for that folder.
     */
    private function loadFolderIni(string $webPath): array
    {
        $iniUnixPath = Path::webToUnixPath(preg_replace('#//#', '/', "$webPath/index.txt"), false);
        if (!file_exists($iniUnixPath)) {
            Log::warn('loadFolderIni found no ini file', $webPath);
            Index::showError('No ini file for this folder.');
            return [];
        }

        $iniFileData = parse_ini_file($iniUnixPath, true);

        if (false === $iniFileData) {
            Log::error('loadFolderIni failed to parse ini file', $webPath);
            Index::showError('Failed to parse ini file for this folder.');
            die(1);
        }

        return MetadataCleaner::cleanDirData($iniFileData);
    }

    /**
     * Write out to an Ini file
     * @param string $webPath The folder to add/update the ini file for. Should have already been sanity-checked.
     */
    public function saveToInis(string $webPath): void
    {
        $pathSoFar = '';
        foreach (explode('/', $webPath) AS $pathElement) {
            $pathSoFar = preg_replace('#//#', '/', "$pathSoFar/$pathElement");
            if (array_key_exists('dirty', self::$folderTree[$webPath]) && !empty(self::$folderTree[$webPath]['data'])) {
                $iniUnixPath = Path::webToUnixPath("$pathSoFar/index.txt", false);
                $iniString = $this->getIniString('/', self::$folderTree[$webPath]['data']);
                file_put_contents($iniUnixPath, $iniString);
            }
        }
    }

    /**
     * Generate the text for an ini file, from the data structure that represents it.
     * @param string $key The key for the current section, or the top-level section when first called.
     * @param mixed $data The data structure to recurse through to print.
     * @param int $depth How deeply we have recursed. Used to prevent infinite recursion.
     * @return string The contents of the ini fie, or the section that we've recursed into.
     */
    public function getIniString(string $key, mixed $data, int $depth = 0): string
    {
        // Prevent infinite recursion.
        if ($depth > 2) {
            return '';
        }

        // Don't save empty data.
        if (is_null($data)) {
            return '';
        }

        // Recurse into sub-arrays.
        if (is_array($data)) {
            $res = "[$key]\n";
            foreach ($data as $subKey => $value) {
                $res .= $this->getIniString("$subKey", $value, $depth + 1);
            }
            return $res . "\n";
        }

        // Handle all other data types.
        if (is_numeric($data)) {
            return "$key = $data\n";
        } elseif (is_bool($data)) {
            return "$key = " . ($data ? 'yes' : 'no') . "\n";
        } elseif (is_string($data)) {
            $escaped = addcslashes($data, "\0..\37\177..\377\\");
            return "$key = \"$escaped\"\n";
        } elseif (is_object($data) && method_exists($data, '__toString')) {
            // This will have problems if __toString() doesn't return a string.
            $escaped = addcslashes($data->__toString(), "\0..\37\177..\377\\");
            return "$key = \"$escaped\"\n";
        } else {
            Log::error("Unknown datatype for '$key'", $data);
            return '';
        }
    }

    /**
     * Load in from the Database.
     * ToDo: This.
     */
    public function loadFromMysql(): void
    {
    }

    /**
     * Write out to the Database.
     * ToDo: This.
     */
    public function saveToMysql(): void
    {
    }

    /** Populate and overwrite our values with the values from another object.
     * @param Metadata $sourceObject The Metadata object to copy from.
     */
    private function updateFromObject(Metadata $sourceObject): void
    {
        foreach ($sourceObject->getData() as $key => $entry) {
            // Go into the first level of arrays and copy their contents.
            if (is_array($entry)) {
                foreach ($entry as $subKey => $subEntry) {
                    if (!is_null($subEntry)) {
                        self::$folderTree[$key][$subKey] = $subEntry;
                    }
                }
            }
        }
    }

    /**
     * Get the data structure for ths metadata.
     * @return array
     */
    public function getData(): array
    {
        return self::$folderTree;
    }

    /**
     * Get the directory's metadata entry for the given file.
     * @param string $webFilePath The file to get the information for.
     * @return array
     */
    public function getFileDetails(string $webFilePath): array
    {
        $webDirPath = dirname($webFilePath);
        if (!array_key_exists($webDirPath, self::$folderTree)) {
            $this->loadFolderIni($webDirPath);
        }

        if (array_key_exists($webDirPath, self::$folderTree)) {
            $basename = basename($webFilePath);
            if (!array_key_exists($basename, self::$folderTree[$webDirPath]['data'])) {
                Log::warn('File details requested for unknown file', $basename);
                return [];
            }
            return self::$folderTree[$webDirPath]['data'];
        } else {
            Log::warn('File details requested for unknown folder', $webDirPath);
            return [];
        }
    }

    /**
     * Get the directory's metadata entry for the given file, plus all inherited data for everything above.
     * @param string $filename The file to get the information for.
     * @return array
     */
    public function getFileDetailsWithInherits(string $filename): array
    {
        // ToDo: this does not yet handle inherited data.
        //       Data from all parent folders isn't loaded at all.
        //       Saving inherited data: do we save it only if it was modified? Seems sensible.
        //       How can the caller distinguish inherited data in the returned data structure? Do they need to?
        //       Should I instead have a getInheritedValue($filename, $key), for templates to call for missing values?
        return ($this->getFileDetails($filename));
    }
}
