<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Class to handle moving metadata between ini files, DB, web display, and web form.
 */
class Metadata
{
    private array $dirKeyNames = [
        'displayname',
        'source',
        'writtennotes',
        'visitornotes',
        'location',
        'startdate',
        'enddate',
        'photographer',
        'people',
        'keywords',
    ];
    private array $fileKeyNames = [
        'displayname',
        'date',
        'writtennotes',
        'visitornotes',
        'location',
        'photographer',
        'people',
        'keywords',
    ];

    /**
     * @var array Dict of string properties, and for folders, sub-arrays for the files.
     */
    private array $data;

    /**
     * @param string $path Promoted property.
     */
    public function __construct(public string $path)
    {
        // Initialize the keys in our directory data.
        $this->data = ['/' => []];
        foreach ($this->dirKeyNames as $key) {
            $this->data['/'][$key] = null;
        }
    }

    /**
     * Load in our data from an Ini file.
     */
    public function loadFromIni(): void
    {
        $iniFilePath = $this->path . '/index.txt';

        if (!file_exists($iniFilePath)) {
            Log::warn('loadFromIni found no ini file', $this->path);
            Index::showError('No ini file for this folder.');
            return;
        }

        $iniFileData = parse_ini_file($iniFilePath, true);

        if (false === $iniFileData) {
            Log::error('loadFromIni failed to parse ini file', $this->path);
            Index::showError('Failed to parse ini file for this folder.');
            die(1);
        }

        $this->data = $this->cleanDirData($iniFileData);
    }

    /**
     * Write out to an Ini file
     * @param string $iniPath The file to write to. Should have already been sanity-checked.
     */
    public function saveToIni(string $iniPath, bool $overwrite = false): void
    {
        if ($overwrite || !file_exists($iniPath)) {
            $iniString = $this->getIniString('/', $this->data);
echo "<pre>$iniString</pre>"; // DELETEME DEBUG
return; // DELETEME DEBUG
            file_put_contents($iniPath, $iniString);
        } else {
            // If we're not overwriting, then read in the existing data from the file, if any.
            $oldData = new Metadata($iniPath);
            $oldData->loadFromIni();
            $oldData->updateFromObject($this);
            $oldData->saveToIni($iniPath, true);
        }
    }

    /**
     * Generate the text for an ini file, from the data structure that represents it.
     * @param string $key The key for the current section, or the top-level section when first called.
     * @param mixed $data The data structure to recurse through to print.
     * @param int $depth How deeply we have recursed. Used to prevent infinite recursion.
     * @return string The contents of the ini fie, or the section that we've recursed into.
     */
    private function getIniString(string $key, mixed $data, int $depth = 0): string
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
                $res .= $this->getIniString($subKey, $value, $depth + 1);
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
     */
    public function loadFromMysql(): void
    {
    }

    /**
     * Write out to the Database.
     */
    public function saveToMysql(): void
    {
    }

    /**
     * Clean up a directory array, removing anything "dirty"/unexpected, parsing non-string types, etc.
     * @param array $data The data to clean.
     * @return array The cleaned data.
     */
    private function cleanDirData(array $data): array
    {
        // Initialize the keys in our data.
        $newDirData = [];
        foreach ($this->dirKeyNames as $key) {
            $newDirData[$key] = null;
        }

        $names = [];
        foreach ($data['/'] as $key => $item) {
            $strippedKey = strtolower(preg_replace('/[^a-z0-9.]/', '', $key));

            Log::debug("Cleaning dir key '$key' as '$strippedKey':"); // DELETEME DEBUG

            if (!in_array($strippedKey, $this->dirKeyNames)) {
                // Todo: handle the versioned comment history keys.
                Log::warn("Unrecognized dir-level property $key as $strippedKey");
                continue;
            }

            switch ($strippedKey) {
                case 'displayname':
                case 'source':
                    $newDirData[$strippedKey] = $this->cleanString($item, 255);
                    break;
                case 'writtennotes':
                case 'visitornotes':
                case 'location':
                    $newDirData[$strippedKey] = $this->cleanString($item);
                    break;
                case 'startdate':
                case 'enddate':
                    $newDirData[$strippedKey] = $this->cleanDate($item);
                    break;
                case 'photographer':
                    $names[$strippedKey] = $this->cleanString($item, 255);
                    break;
                case 'people':
                    $names[$strippedKey] = $this->cleanCsvLine($item);
                    break;
                case 'keywords':
                    $keywords = $this->cleanCsvLine($item);
                    $newDirData[$strippedKey] = $this->cleanKeywords($keywords);
                    break;
                default:
                    Log::warn('dir-level property default:', $key);
            }
        }

        // Swap the dates if they're in the wrong order.
        if ($newDirData['startdate'] > $newDirData['enddate']) {
            Log::warn('Start date later than end date: swapping');
            $tmp = $newDirData['startdate'];
            $newDirData['startdate'] = $newDirData['enddate'];
            $newDirData['enddate'] = $tmp;
        }
        $this->cleanNamesInData($newDirData, $names);

        // Clean file sub-arrays, and remove non-array keys.
        $newData = ['/' => $newDirData];
        foreach ($data as $key => $value) {
            if ('/' !== $key && is_array($value)) {
                // Filenames may contain only space, hyphen and dot, plus underscore and alphanumerics.
                $strippedKey = preg_replace('/[^- .\w]/', '', $key);

                // For safety, this file may not be listed.
                if ('index.txt' !== $strippedKey) {
                    $newData[$strippedKey] = $this->cleanFileData($value);
                }
            }
        }

        return $newData;
    }

    /**
     * Clean up a file array, removing anything "dirty"/unexpected, parsing non-string types, etc.
     * @param array $data The data to clean. Modified in place.
     * @return array The cleaned data.
     */
    private function cleanFileData(array $data): array
    {
        // Initialize the keys in our data.
        $newFileData = [];
        foreach ($this->fileKeyNames as $key) {
            $newFileData[$key] = null;
        }

        $names = [];
        foreach ($data as $key => $item) {
            $strippedKey = strtolower(preg_replace('/[^a-z0-9.]/', '', $key));

            Log::debug("Cleaning file key '$key' as '$strippedKey':"); // DELETEME DEBUG

            if (!in_array($strippedKey, $this->fileKeyNames)) {
                // Todo: handle the versioned comment history keys.
                Log::warn('Unrecognized file-level property', $key);
                continue;
            }
            switch ($strippedKey) {
                case 'displayname':
                    $newFileData[$strippedKey] = $this->cleanString($item, 255);
                    break;
                case 'writtennotes':
                case 'visitornotes':
                case 'location':
                    $newFileData[$strippedKey] = $this->cleanString($item);
                    break;
                case 'date':
                    $newFileData[$strippedKey] = $this->cleanDate($item);
                    break;
                case 'photographer':
                    $names[$strippedKey] = $this->cleanString($item, 255);
                    break;
                case 'people':
                    $names[$strippedKey] = $this->cleanCsvLine($item);
                    break;
                case 'keywords':
                    $keywords = $this->cleanCsvLine($item);
                    $newFileData[$strippedKey] = $this->cleanKeywords($keywords);
                    break;
                default:
                    Log::warn('file-level property default:', $key);
            }
        }
        $this->cleanNamesInData($newFileData, $names);
        return $newFileData;
    }

    /**
     * Given a string, make sure it's trimmed, and truncate it to the max length, if any.
     * @param mixed $item The item to clean as a string.
     * @param ?int $maxLength optional max length to truncate to.
     * @param bool $parseSlashes Whether to parse C slashes (\f, \n, \r, \t, \v, numeric escapes) in the string.
     * @return string The cleaned string, which may be empty.
     */
    private function cleanString(mixed $item, int $maxLength = null, bool $parseSlashes = false): string
    {
        Log::debug(__METHOD__ . ", Parsing item $item"); // DELETEME DEBUG
        if (!is_string($item)) {
            Log::warn('String property was not string', $item);
            return '';
        }
        $trimmed = trim($item);
        if (!is_null($maxLength) && strlen($trimmed) > $maxLength) {
            Log::warn('String property was too long', $trimmed);
            $trimmed = substr($trimmed, 0, $maxLength);
        }
        if ($parseSlashes) {
            Log::debug("Parsing slashes"); // DELETEME DEBUG
            // This is a hack, since stripcslashes() will remove a slash that precedes a non-special character.
            // Instead, this only escapes the special characters \f, \n, \r, \t, \v, and octal and hex escapes.
            $trimmed = preg_replace_callback(
                '/\\\\([fnrtv\\\\$"]|[0-7]{1,3}|\x[0-9A-Fa-f]{1,2})/',
                fn($matches) => stripcslashes($matches[0]),
                $trimmed
            );
        }
        Log::debug(__METHOD__ . ", returning $trimmed"); // DELETEME DEBUG
        return $trimmed;
    }

    /**
     * Given a date string, return a valid timestamp, or null.
     * @param mixed $item The item to try parsing for a date.
     * @return int|null The valid timestamp, or null if it could not be parsed.
     */
    private function cleanDate(mixed $item): ?int
    {
        Log::debug(__METHOD__ . ", Parsing item $item"); // DELETEME DEBUG
        $result = strtotime($item);
        if (false === $result) {
            return null;
        }
        Log::debug(__METHOD__ . ", returning $result"); // DELETEME DEBUG
        return $result;
    }

    /**
     * Given a single line of CSV, return an array of items, each truncated to a max length, if any.
     * @param mixed $item The item to parse into CSV items, if possible.
     * @param ?int $maxLength optional max length to truncate to.
     * @param bool $parseSlashes Whether to parse C slashes (\n, \r, \t, \v, \f, numeric escapes) in the string.
     * @return array
     * @noinspection PhpSameParameterValueInspection
     */
    private function cleanCsvLine(mixed $item, int $maxLength = null, bool $parseSlashes = true): array
    {
        Log::debug(__METHOD__ . ", Parsing item $item"); // DELETEME DEBUG
        if (!is_string($item)) {
            Log::warn('CSV property was not string', $item);
            return [];
        }
        if (0 === strlen($item)) {
            Log::warn('CSV property was empty', $item);
            return [];
        }
        $parsed = str_getcsv($item);
        foreach ($parsed as &$item) {
            $item = $this->cleanString($item, $maxLength, $parseSlashes);
        }
        Log::debug(__METHOD__ . ", returning " . implode('#,#', $parsed)); // DELETEME DEBUG
        return $parsed;
    }

    /**
     * Clean a list of keywords.
     * @param array $keywords The keywords to clean.
     * @return array
     * @ToDo: Currently a no-op. Maybe:
     *   - optional param $checkExists to accept only pre-existing keywords?
     *   - restrict the characters that can be in a keyword?
     */
    private function cleanKeywords(array $keywords): array
    {
        Log::debug(__METHOD__ . ", returning " . implode('#,#', $keywords)); // DELETEME DEBUG
        return $keywords;
    }

    /**
     * Take lists of name-lists, and ensure that they are valid, in some sense.
     * @param array $newDirData A data array that should have the name lists inserted/updated into.
     * @param array $nameLists A list of name-lists, keyed by their key within the data array.
     * @ToDo: Currently does no cleaning.
     *   - Optional param $checkExists to accept only pre-existing names?
     *     This is why they're all bundled together, so it can be done in one query.
     *   - Restrict the characters that can be in a name? Remove <script>, etc.
     */
    private function cleanNamesInData(array &$newDirData, array $nameLists): void
    {
        foreach ($nameLists as $key => $nameList) {
            $newDirData[$key] = $nameList;
        }
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
                        $this->data[$key][$subKey] = $subEntry;
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
        return $this->data;
    }

    /**
     * Get the directory's metadata entry for the given file.
     * @param string $filename The file to get the information for.
     * @return array
     */
    public function getFileDetails(string $filename): array
    {
        $basename = basename($filename);
        if (array_key_exists($basename, $this->data)) {
            if (is_array($this->data[$basename])) {
                return $this->data[$basename];
            } else {
                Log::warn('Non-array file details found for file', $basename);
                return [];
            }
        } else {
            Log::warn('File details requested for unknown file', $basename);
            return [];
        }
    }
}
