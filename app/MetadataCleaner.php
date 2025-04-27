<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Class vor validation and cleaning of Metadata.
 */
class MetadataCleaner
{
    private const DIR_KEY_NAMES = [
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
    private const FILE_KEY_NAMES = [
        'displayname',
        'slideorigin', // Where the slide was kept.
        'slidenumber', // Where the slide was kept.
        'slidesubsection', // Where the slide was kept.
        'unfiltered', // Boolean, false if ICE.
        'date',
        'writtennotes', // Slide text.
        'visitornotes',
        'location',
        'photographer',
        'people',
        'keywords',
    ];

    /**
     * Clean up a directory array, removing anything "dirty"/unexpected, parsing non-string types, etc.
     * @param array $data The data to clean.
     * @return array The cleaned data.
     */
    public static function cleanDirData(array $data): array
    {
        // Initialize the keys in our data.
        $newDirData = [];
        foreach (self::DIR_KEY_NAMES as $key) {
            $newDirData[$key] = null;
        }

        $names = [];
        foreach ($data['/'] as $key => $item) {
            $strippedKey = strtolower(preg_replace('/[^a-z0-9.]/', '', $key));

            Log::debug("Cleaning dir key '$key' as '$strippedKey':"); // DELETEME DEBUG

            if (!in_array($strippedKey, self::DIR_KEY_NAMES)) {
                // Todo: handle the versioned comment history keys.
                Log::warn("Unrecognized dir-level property $key as $strippedKey");
                continue;
            }

            switch ($strippedKey) {
                case 'displayname':
                case 'source':
                    $newDirData[$strippedKey] = self::cleanString($item, 255);
                    break;
                case 'writtennotes':
                case 'visitornotes':
                case 'location':
                    $newDirData[$strippedKey] = self::cleanString($item);
                    break;
                case 'startdate':
                case 'enddate':
                    $newDirData[$strippedKey] = self::cleanDate($item);
                    break;
                case 'photographer':
                    $names[$strippedKey] = self::cleanString($item, 255);
                    break;
                case 'people':
                    $names[$strippedKey] = self::cleanCsvLine($item);
                    break;
                case 'keywords':
                    $keywords = self::cleanCsvLine($item);
                    $newDirData[$strippedKey] = self::cleanKeywords($keywords);
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
        self::cleanNamesInData($newDirData, $names);

        // Clean file sub-arrays, and remove non-array keys.
        $newData = ['/' => $newDirData];
        foreach ($data as $key => $value) {
            if ('/' !== $key && is_array($value)) {
                // Filenames may contain only space, hyphen and dot, plus underscore and alphanumerics.
                $strippedKey = preg_replace('/[^- .\w]/', '', $key);

                // For safety, this file may not be listed.
                if ('index.txt' !== $strippedKey) {
                    $newData[$strippedKey] = self::cleanFileData($value);
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
    private static function cleanFileData(array $data): array
    {
        // Initialize the keys in our data.
        $newFileData = [];
        foreach (self::FILE_KEY_NAMES as $key) {
            $newFileData[$key] = null;
        }

        $names = [];
        foreach ($data as $key => $item) {
            $strippedKey = strtolower(preg_replace('/[^a-z0-9.]/', '', $key));

            Log::debug("Cleaning file key '$key' as '$strippedKey':"); // DELETEME DEBUG

            if (!in_array($strippedKey, self::FILE_KEY_NAMES)) {
                // ToDo: handle the versioned comment history keys.
                Log::warn('Unrecognized file-level property', $key);
                continue;
            }
            switch ($strippedKey) {
                case 'displayname':
                case 'slideorigin':
                    $newFileData[$strippedKey] = self::cleanString($item, 255);
                    break;
                case 'writtennotes':
                case 'visitornotes':
                case 'location':
                    $newFileData[$strippedKey] = self::cleanString($item);
                    break;
                case 'date':
                    $newFileData[$strippedKey] = self::cleanDate($item);
                    break;
                case 'photographer':
                    $names[$strippedKey] = self::cleanString($item, 255);
                    break;
                case 'people':
                    $names[$strippedKey] = self::cleanCsvLine($item);
                    break;
                case 'keywords':
                    $keywords = self::cleanCsvLine($item);
                    $newFileData[$strippedKey] = self::cleanKeywords($keywords);
                    break;
                case 'slidenumber':
                case 'slidesubsection':
                    $newFileData[$strippedKey] = self::cleanInt($item);
                    break;
                case 'unfiltered':
                    $newFileData[$strippedKey] = self::cleanBool($item);
                    break;
                default:
                    Log::warn('file-level property default:', $key);
            }
        }
        self::cleanNamesInData($newFileData, $names);
        return $newFileData;
    }

    /**
     * Given input that represents a boolean, return a valid boolean, or null.
     *   Null if it can't be converted to a string.
     *   Null if it converts to empty string.
     *   False if string representation contains "0", "n" or "f" (eg, "no", "n/a", "false").
     *   True otherwise.
     * @param mixed $item The item to try parsing as a boolean.
     * @return bool|null The valid boolean, or null if it could not be parsed.
     */
    private static function cleanBool(mixed $item): ?bool
    {
        Log::debug(__METHOD__ . ', Parsing bool ' . var_export($item, true)); // DELETEME DEBUG
        if (
            is_array($item) ||
            (is_object($item) && !method_exists($item, '__toString')) ||
            (!is_object($item) && !settype($item, 'string')) ||
            '' === (string)$item
        ) {
            $result = null;
        } elseif (preg_match('/[0nf]/', $item)) {
            $result = false;
        } else {
            $result = true;
        }
        Log::debug(__METHOD__ . ", returning $result"); // DELETEME DEBUG
        return $result;
    }

    /**
     * Given input that represents a number, return a valid integer, or null.
     * @param mixed $item The item to try parsing for a number.
     * @return int|null The valid number, or null if it could not be parsed.
     */
    private static function cleanInt(mixed $item): ?int
    {
        Log::debug(__METHOD__ . ', Parsing int ' . var_export($item, true)); // DELETEME DEBUG
        if (is_numeric($item)) {
            $result = (int)$item; // Casting to int always parses as decimal.
        } else {
            $result = null;
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
     */
    private static function cleanCsvLine(mixed $item, int $maxLength = null, bool $parseSlashes = true): array
    {
        Log::debug(__METHOD__ . ', Parsing CSV ' . var_export($item, true)); // DELETEME DEBUG
        if (!is_string($item)) {
            Log::warn('CSV property was not string', $item);
            return [];
        }
        if ('' === $item) {
            Log::warn('CSV property was empty', $item);
            return [];
        }
        $parsed = str_getcsv($item);
        foreach ($parsed as $key => $csvSegment) {
            $parsed[$key] = self::cleanString($csvSegment, $maxLength, $parseSlashes);
        }
        Log::debug(__METHOD__ . ', returning ' . implode('#,#', $parsed)); // DELETEME DEBUG
        return $parsed;
    }

    /**
     * Given a date string, return a valid timestamp, or null.
     * @param mixed $item The item to try parsing for a date.
     * @return int|null The valid timestamp, or null if it could not be parsed.
     */
    private static function cleanDate(mixed $item): ?int
    {
        Log::debug(__METHOD__ . ', Parsing date ' . var_export($item, true)); // DELETEME DEBUG
        $result = strtotime($item);
        if (false === $result) {
            Log::warn(__METHOD__ . ', unable to parse, returning NULL'); // DELETEME DEBUG
            return null;
        }
        Log::debug(__METHOD__ . ", returning $result"); // DELETEME DEBUG
        return $result;
    }

    /**
     * Clean a list of keywords.
     * @param array $keywords The keywords to clean.
     * @return array
     * @ToDo: Currently a no-op. Maybe:
     *   - optional param $checkExists to accept only pre-existing keywords?
     *   - restrict the characters that can be in a keyword? Remove <script>, etc.
     */
    private static function cleanKeywords(array $keywords): array
    {
        Log::debug(__METHOD__ . ', returning ' . implode('#,#', $keywords)); // DELETEME DEBUG
        return $keywords;
    }

    /**
     * Take lists of name-lists, and ensure that they are valid, in some sense.
     * @param array $newDirData A data array that should have the name lists inserted/updated into.
     * @param array $nameLists A list of name-lists, keyed by their key within the data array.
     * @ToDo: Currently a no-op. Maybe:
     *   - Optional param $checkExists to accept only pre-existing names?
     *   - This is why they're all bundled together, so it can be done in one query.
     *   - Restrict the characters that can be in a name? Remove <script>, etc.
     */
    private static function cleanNamesInData(array &$newDirData, array $nameLists): void
    {
        foreach ($nameLists as $key => $nameList) {
            $newDirData[$key] = $nameList;
        }
    }

    /**
     * Given a string, make sure it's trimmed, and truncate it to the max length, if any.
     * @param mixed $item The item to clean as a string.
     * @param ?int $maxLength optional max length to truncate to.
     * @param bool $parseSlashes Whether to parse C slashes (\f, \n, \r, \t, \v, numeric escapes) in the string.
     * @return string The cleaned string, which may be empty.
     */
    private static function cleanString(mixed $item, int $maxLength = null, bool $parseSlashes = false): string
    {
        Log::debug(__METHOD__ . ', Parsing string ' . var_export($item, true)); // DELETEME DEBUG
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
            Log::debug('Parsing slashes'); // DELETEME DEBUG
            // This is a hack, since stripcslashes() will remove a slash that precedes a non-special character.
            // Instead, this only escapes the special characters \f, \n, \r, \t, \v, and octal and hex escapes.
            $trimmed = preg_replace_callback(
                '/\\\\([fnrtv\\\\$"]|[0-7]{1,3}|\x[0-9A-Fa-f]{1,2})/',
                static fn($matches) => stripcslashes($matches[0]),
                $trimmed
            );
        }
        Log::debug(__METHOD__ . ", returning $trimmed"); // DELETEME DEBUG
        return $trimmed;
    }
}
