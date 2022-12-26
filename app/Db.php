<?php
declare(strict_types=1);
namespace app;

use mysqli;
use Exception;

class Db {
    private const INI_FILE = 'MySqlAuth.ini';

    private mysqli $db;

    private static ?Db $instance = null;

    /**
     * Singleton factory.
     * @return Db
     */
    private static function getInstance(): Db {
        if (is_null(self::$instance)) {
            self::$instance = new Db();
        }
        return self::$instance;
    }

    private function __construct() {
        // Parse the INI file.
        if (!$authArray = self::readIniInParents(self::INI_FILE)) {
            self::adminDebug('DB Auth information could not be read.');
            die();
        }

        // Create the DB connection using the auth info from the INI file.
        try {
            $this->db = new mysqli(
                $authArray['host'] ?? '',
                $authArray['name'] ?? '',
                $authArray['user'] ?? '',
                $authArray['pass'] ?? '',
                $authArray['port'] ?? ''
            );
        } catch (Exception $e) {
            self::adminDebug('DB connection failed: ' . $e->getMessage());
            exit('DB connection failed.');
        }
    }

    /**
     * Execute a SQL statement.
     * @param string $sql The query to execute.
     * @param int|string ...$items type-string, then variables, eg 'sd', 'foo', 1.
     */
    public static function sqlExec(string $sql, int|string ...$items): void {
        self::adminDebug('sqlExec', [$sql, $items]);
        $db = self::getInstance()->db;
        if ($query = $db->prepare($sql)) {
            if (!empty($items)) {
                call_user_func_array([$query, 'bind_param'], self::mkRefArray($items));
            }
            $query->execute();
        }
    }

    /**
     * Parse a named folder from the current working directory, or any parent folder.
     * @param string $filename The filename to find in the current folder or any parent folder.
     * @return array|false Array of data parsed from the ini file, or false on failure.
     */
    public static function readIniInParents(string $filename): array|false {
        $dir = getcwd();
        while ($dir != '/') {
            self::adminDebug("Checking $dir / $filename.");
            if (file_exists($dir . '/' . $filename)) {
                return parse_ini_file($dir . '/' . $filename);
            }
            $dir = dirname($dir);
        }
        return false;
    }

    /**
     * Return the requested item or null.
     * @param string $sql Query with values replaced by '?'.
     * @param string $field Field name to extract and return from the result.
     * @param int|string ...$items A string describing the types of all following values, then the values..
     * @return null|string
     */
    public static function sqlGetItem(string $sql, string $field, int|string ...$items): ?string {
        self::adminDebug('sqlGetItem', [$sql, $items]);
        $db = self::getInstance()->db;
        if (!($query = $db->prepare($sql))) {
            self::adminDebug('prepare failed, db error', $db->error);
            return null;
        }
        if (!empty($items) && !call_user_func_array([$query, 'bind_param'], self::mkRefArray($items))) {
            self::adminDebug('bind_param failed, db error', $db->error);
            self::adminDebug('bind_param failed, sql error', $query->error);
            return null;
        }
        if (!$query->execute()) {
            self::adminDebug('execute failed, db error', $db->error);
            self::adminDebug('execute failed, sql error', $query->error);
            return null;
        }
        if (!($result = $query->get_result())) {
            self::adminDebug('get_result failed, db error', $db->error);
            self::adminDebug('get_result failed, sql error', $query->error);
            return null;
        }
        if (!($row = $result->fetch_assoc())) {
            self::adminDebug('fetch_assoc failed, db error', $db->error);
            self::adminDebug('fetch_assoc failed, sql error', $query->error);
            $result->free();
            return null;
        }

        $result->free();
        self::adminDebug('sqlGetItem success: ' . $row[$field] . ' is $field of', $row);
        return $row[$field];
    }

    /**
     * Return the first row from the results, or an empty array.
     * @param string $sql Query with values replaced by '?'.
     * @param int|string ...$items A string describing the types of all following values, then the values..
     * @return array
     */
    public static function sqlGetRow(string $sql, int|string ...$items): array {
        self::adminDebug('sqlGetRow', [$sql, $items]);
        $db = self::getInstance()->db;
        if (!($query = $db->prepare($sql))) {
            self::adminDebug('prepare failed, db error', $db->error);
            return [];
        }
        if (!empty($items) && !call_user_func_array([$query, 'bind_param'], self::mkRefArray($items))) {
            self::adminDebug('bind_param failed, db error', $db->error);
            self::adminDebug('bind_param failed, sql error', $query->error);
            return [];
        }
        if (!$query->execute()) {
            self::adminDebug('execute failed, db error', $db->error);
            self::adminDebug('execute failed, sql error', $query->error);
            return [];
        }
        if (!($result = $query->get_result())) {
            self::adminDebug('get_result failed, db error', $db->error);
            self::adminDebug('get_result failed, sql error', $query->error);
            return [];
        }

        $row = $result->fetch_assoc();
        $result->free();
        self::adminDebug('sqlGetRow success', $row);
        return $row;
    }

    /**
     * Return the all the results as a 2d array, or an empty array.
     * @param string $sql Query with values replaced by '?'.
     * @return array
     */
    public static function sqlGetTable(string $sql): array {
        self::adminDebug('sqlGetTable', $sql);
        $db = self::getInstance()->db;
        if ($result = $db->query($sql)) {
            $table = [];
            while ($row = $result->fetch_assoc()) {
                $table []= $row;
            }
            $result->free();
            return $table;
        } else {
            self::adminDebug('query failed, db error', $db->error);
        }
        return [];
    }

    /**
     * Escape a string using the current DB's default escaping.
     * @param string $str
     * @return string
     */
    public static function escape(string $str): string {
        $db = self::getInstance()->db;
        return $db->real_escape_string($str);
    }

    /**
     * Sadly, bind_param expects references, which makes passing arrays harder.
     * ToDo: There's apparently a `...` operator that makes this redundant: see man page.
     * @param array $input The array to convert.
     * @return array The array of references.
     */
    private static function mkRefArray(array $input): array {
        $result = [];
        if (empty($input)) {
            return [''];
        }
        foreach ($input as $key => $val) {
            if ($key > 0) {
                $result[$key] = &$input[$key];
            } else {
                $result[$key] = $val;
            }
        }
        return $result;
    }

    /**
     * Log a message and an optional object to the log and maybe to the screen.
     * @param string $str
     * @param mixed $obj
     */
    public static function adminDebug(string $str, mixed $obj = null): void {
        global $connection;
        $message = "A-DBG: $str" . (is_null($obj) ? '.' : ': ' . var_export($obj, true));
        file_put_contents('error_log', "$message\n", FILE_APPEND);
        if (isset($connection) && $connection->isSuperAdmin) {
            echo "<pre>$message</pre>\n";
        }
    }
}
