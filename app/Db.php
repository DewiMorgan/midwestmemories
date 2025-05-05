<?php

declare(strict_types=1);

namespace MidwestMemories;

use mysqli;
use Exception;

/**
 * Database connection singleton class.
 * @noinspection PhpClassNamingConventionInspection
 */
class Db
{
    private mysqli $db;

    private static ?Db $instance = null;

    /**
     * Singleton factory.
     * @return Db
     */
    private static function getInstance(): Db
    {
        if (is_null(self::$instance)) {
            self::$instance = new Db();
        }
        return self::$instance;
    }

    private function __construct()
    {
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
            Log::adminDebug('DB connection failed: ' . $e->getMessage());
            die(1);
        }
    }

    /**
     * Execute a SQL statement.
     * @param string $sql The query to execute.
     * @param int|string ...$items type-string, then variables, eg 'sd', 'foo', 1.
     * @return bool True on success, else false.
     */
    public static function sqlExec(string $sql, int|string ...$items): bool
    {
        Log::adminDebug('sqlExec', [$sql, $items]);
        $db = self::getInstance()->db;
        $query = $db->prepare($sql);

        if ($query && (empty($items) || $query->bind_param(...$items))) {
            return $query->execute();
        }
        return false;
    }

    /**
     * Return the requested item or null.
     * @param string $sql Query with values replaced by '?'.
     * @param string $field Field name to extract and return from the result.
     * @param int|string ...$items A string describing the types of all following values, then the values.
     * @return null|string
     */
    public static function sqlGetItem(string $sql, string $field, int|string ...$items): ?string
    {
        Log::adminDebug('sqlGetItem', [$sql, $items]);
        $db = self::getInstance()->db;
        if (!($query = $db->prepare($sql))) {
            Log::adminDebug('prepare failed, db error', $db->error);
            return null;
        }
        if (!empty($items) && !$query->bind_param(...$items)) {
            Log::adminDebug('bind_param failed, db error', $db->error);
            Log::adminDebug('bind_param failed, sql error', $query->error);
            return null;
        }
        if (!$query->execute()) {
            Log::adminDebug('execute failed, db error', $db->error);
            Log::adminDebug('execute failed, sql error', $query->error);
            return null;
        }
        if (!($result = $query->get_result())) {
            Log::adminDebug('get_result failed, db error', $db->error);
            Log::adminDebug('get_result failed, sql error', $query->error);
            return null;
        }
        if (!($row = $result->fetch_assoc())) {
            Log::adminDebug('fetch_assoc failed, db error', $db->error);
            Log::adminDebug('fetch_assoc failed, sql error', $query->error);
            $result->free();
            return null;
        }

        $result->free();
        Log::adminDebug('sqlGetItem success: ' . $row[$field] . ' is $field of', $row);
        return $row[$field];
    }

    /**
     * Return the first row from the results, or an empty array.
     * @param string $sql Query with values replaced by '?'.
     * @param int|string ...$items A string describing the types of all following values, then the values.
     * @return array
     * @noinspection PhpUnused
     */
    public static function sqlGetRow(string $sql, int|string ...$items): array
    {
        Log::adminDebug('sqlGetRow', [$sql, $items]);
        $db = self::getInstance()->db;
        if (!($query = $db->prepare($sql))) {
            Log::adminDebug('prepare failed, db error', $db->error);
            return [];
        }
        if (!empty($items) && !$query->bind_param(...$items)) {
            Log::adminDebug('bind_param failed, db error', $db->error);
            Log::adminDebug('bind_param failed, sql error', $query->error);
            return [];
        }
        if (!$query->execute()) {
            Log::adminDebug('execute failed, db error', $db->error);
            Log::adminDebug('execute failed, sql error', $query->error);
            return [];
        }
        if (!($result = $query->get_result())) {
            Log::adminDebug('get_result failed, db error', $db->error);
            Log::adminDebug('get_result failed, sql error', $query->error);
            return [];
        }

        $row = $result->fetch_assoc();
        $result->free();
        Log::adminDebug('sqlGetRow success', $row);
        return $row;
    }

    /**
     * Return the all the results as a 2d array, or an empty array.
     * @param string $sql Query with values replaced by '?'.
     * @return array
     */
    public static function sqlGetTable(string $sql): array
    {
        Log::adminDebug('sqlGetTable', $sql);
        $db = self::getInstance()->db;
        if ($result = $db->query($sql)) {
            $table = [];
            while ($row = $result->fetch_assoc()) {
                $table [] = $row;
            }
            $result->free();
            return $table;
        }
        Log::adminDebug('query failed, db error', $db->error);
        return [];
    }

    /**
     * Return one column of results as a 1d array, or an empty array.
     * @param string $sql Query with values replaced by '?'.
     * @param string $fieldName The field to populate the list from.
     * @return array A list of all values returned for that field, or empty.
     */
    public static function sqlGetList(string $sql, string $fieldName): array
    {
        Log::adminDebug('sqlGetTable', $sql);
        $db = self::getInstance()->db;
        if ($result = $db->query($sql)) {
            $list = [];
            while ($row = $result->fetch_assoc()) {
                $list [] = $row[$fieldName];
            }
            $result->free();
            return $list;
        }
        Log::adminDebug('query failed, db error', $db->error);
        return [];
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
