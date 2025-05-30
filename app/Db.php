<?php

declare(strict_types=1);

namespace MidwestMemories;

use mysqli;
use Exception;
use mysqli_result;

/**
 * Database connection singleton class.
 * @noinspection PhpClassNamingConventionInspection
 */
class Db extends Singleton
{
    private const TYPE_EXEC = 0; // `TYPE_EXEC` returns [1] on success.
    private const TYPE_RESULT = 1; // `TYPE_RESULT` returns a result that must be freed.
    private const TYPE_ROW = 2; // `TYPE_ROW` is an associative array for the row.

    private mysqli $db;

    /**
     * Protected singleton constructor.
     */
    protected function __construct()
    {
        parent::__construct();
        // Create the DB connection using the auth info from the INI file.
        try {
            $this->db = new mysqli(
                Conf::get(Key::MYSQL_HOST),
                Conf::get(Key::MYSQL_USER),
                Conf::get(Key::MYSQL_PASS),
                Conf::get(Key::MYSQL_NAME),
                (int)Conf::get(Key::MYSQL_PORT)
            );
        } catch (Exception $e) {
            Log::debug('DB connection failed: ' . $e->getMessage());
            die(1);
        }
    }

    /**
     * Execute a SQL statement.
     * @param string $sql The query to execute.
     * @param int|string ...$items type-string, then variables, like 'sd', 'foo', 1.
     * @return int|null The inserted ID on success, else null.
     */
    public static function sqlExec(string $sql, int|string ...$items): ?int
    {
        Log::debug('Exec...', [$sql, $items]);
        $result = self::getQueryResult(self::TYPE_EXEC, $sql, ...$items);
        if (empty($result)) {
            return null;
        }
        return intval($result[0]);
    }

    /**
     * Return the requested item or null.
     * @param string $sql Query with values replaced by '?'.
     * @param string $field Field name to extract and return from the result.
     * @param int|string ...$items A string describing the types of all following values, then the values.
     * @return null|string The retrieved value, or null on error.
     */
    public static function sqlGetItem(string $sql, string $field, int|string ...$items): ?string
    {
        Log::debug('Start...', [$sql, $items]);
        if (!($row = self::getQueryResult(self::TYPE_ROW, $sql, ...$items))) {
            return null;
        }
        if (!array_key_exists($field, $row)) {
            Log::warn("get item failed, field '$field' not found in row", $row);
            return null;
        }
        Log::debug("...success: '$row[$field]' is in", $row);
        return (string)$row[$field];
    }

    /**
     * Return the first row from the results, or an empty array.
     * @param string $sql Query with values replaced by '?'.
     * @param int|string ...$items A string describing the types of all following values, then the values.
     * @return array The retrieved associative array of field to value, or empty array on error.
     * @noinspection PhpUnused
     */
    public static function sqlGetRow(string $sql, int|string ...$items): array
    {
        Log::debug('Start...', [$sql, $items]);
        if (!($row = self::getQueryResult(self::TYPE_ROW, $sql, ...$items))) {
            return [];
        }
        Log::debug('...success', $row);
        return $row;
    }

    /**
     * Return the all the results as a 2d array, or an empty array.
     * @param string $sql Query with values replaced by '?'.
     * @param int|string ...$items A string describing the types of all following values, then the values.
     * @return array The retrieved list of associative arrays of field to value, or empty array on error.
     */
    public static function sqlGetTable(string $sql, int|string ...$items): array
    {
        Log::debug('Start...', [$sql, $items]);
        if (!($result = self::getQueryResult(self::TYPE_RESULT, $sql, ...$items))) {
            return [];
        }
        $table = [];
        while ($row = $result->fetch_assoc()) {
            $table [] = $row;
        }
        $result->free();
        return $table;
    }

    /**
     * Return one column of results as a 1d array, or an empty array.
     * @param string $fieldName The field to populate the list from.
     * @param string $sql Query with values replaced by '?'.
     * @return array A list of all values returned for that field, or empty.
     */
    public static function sqlGetList(string $fieldName, string $sql, int|string ...$items): array
    {
        Log::debug('Start...', [$sql, $items]);
        if (!($result = self::getQueryResult(self::TYPE_RESULT, $sql, ...$items))) {
            return [];
        }
        $list = [];
        while ($row = $result->fetch_assoc()) {
            $list [] = $row[$fieldName];
        }
        $result->free();
        return $list;
    }

    /**
     * Perform a safe query. Helper used by other functions.
     * @param int $queryType - one of the `TYPE_` constants.
     * @param string $sql - SQL query, with unknowns as '?'s.
     * @param int|string ...$items - The items to replace the unknowns from the SQL query.
     * @return mysqli_result|array Empty array on error.
     */
    private static function getQueryResult(int $queryType, string $sql, int|string ...$items): mysqli_result|array
    {
        $db = self::getInstance()->db;
        if (!($query = $db->prepare($sql))) {
            Log::warn('prepare failed, db error', $db->error);
            return [];
        }
        if (!empty($items) && !$query->bind_param(...$items)) {
            Log::warn('bind_param failed, db error', $db->error);
            Log::warn('bind_param failed, sql error', $query->error);
            return [];
        }
        if (!$query->execute()) {
            Log::warn('execute failed, db error', $db->error);
            Log::warn('execute failed, sql error', $query->error);
            return [];
        }
        if (self::TYPE_EXEC == $queryType) {
            return [$db->insert_id];
        }
        if (!($result = $query->get_result())) {
            Log::warn('get_result failed, db error', $db->error);
            Log::warn('get_result failed, sql error', $query->error);
            return [];
        }
        if (self::TYPE_RESULT == $queryType) {
            return $result;
        }
        if (!($row = $result->fetch_assoc())) {
            Log::warn('fetch_assoc failed, db error', $db->error);
            Log::warn('fetch_assoc failed, sql error', $query->error);
            $result->free();
            return [];
        }
        $result->free();
        return $row;
    }

    /**
     * Escape a string using the current DB's default escaping.
     * @param string $str
     * @return string
     * @noinspection PhpUnused
     */
    public static function escape(string $str): string
    {
        return self::getInstance()->db->real_escape_string($str);
    }
}
