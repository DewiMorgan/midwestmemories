<?php
namespace app;

use \mysqli;
use Exception;

class Db {
    private $db;

    private static $instance = null;

    private static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new Db();
        }
        return self::$instance;
    }

    private function __construct() {
        try {
            require_once('DbAuth.php');
            $this->db = new mysqli(DbAuth::DB_HOST, DbAuth::DB_NAME, DbAuth::DB_USER, DbAuth::DB_PASS, DbAuth::DB_PORT);
        }
        catch (Exception $e) {
            var_export($e);
            exit;
        }
        if (!$this->db) {
            exit("DB is empty: " . var_export($this->db, true));
        }
    }

    public static function sqlExec(string $sql, string ...$items): void {
        self::adminDebug('sqlExec', [$sql, $items]);
        $db = self::getInstance()->db;
        if ($query = $db->prepare($sql)) {
            if (!empty($items)) {
                call_user_func_array(array($query, "bind_param"), self::mkRefArray($items));
            }
            $query->execute();
        }
    }

    /**
     * Return the requested item or null.
     * @param string $sql Query with values replaced by '?'.
     * @param string $field Fieldname to extract and return from the result.
     * @param string ... $items a string describing the types of all following values, then the values..
     * @return null|string
     */
    public static function sqlGetItem(string $sql, string $field, string ...$items): ?string {
        self::adminDebug('sqlGetItem', [$sql, $items]);
        $db = self::getInstance()->db;
        if (!($query = $db->prepare($sql))) {
            self::adminDebug('prepare failed, dberr', $db->error);
            return null;
        }
        if (!empty($items) && !call_user_func_array(array($query, "bind_param"), self::mkRefArray($items))) {
            self::adminDebug('bind_param failed, dberr', $db->error);
            self::adminDebug('bind_param failed, qerr', $query->error);
            return null;
        }
        if (!$query->execute()) {
            self::adminDebug('execute failed, dberr', $db->error);
            self::adminDebug('execute failed, qerr', $query->error);
            return null;
        }
        if (!($result = $query->get_result())) {
            self::adminDebug('get_result failed, dberr', $db->error);
            self::adminDebug('get_result failed, qerr', $query->error);
            return [];
        }
        if (!($row = $result->fetch_assoc())) {
            self::adminDebug('fetch_assoc failed, dberr', $db->error);
            self::adminDebug('fetch_assoc failed, qerr', $query->error);
            self::adminDebug('fetch_assoc failed, rerr', $result->error);
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
     * @param string ... $items a string describing the types of all following values, then the values..
     * @return array
     */
    public static function sqlGetRow(string $sql, string ...$items): array {
        self::adminDebug('sqlGetRow', [$sql, $items]);
        $db = self::getInstance()->db;
        if (!($query = $db->prepare($sql))) {
            self::adminDebug('prepare failed, dberr', $db->error);
            return [];
        }
        if (!empty($items) && !call_user_func_array(array($query, "bind_param"), self::mkRefArray($items))) {
            self::adminDebug('bind_param failed, dberr', $db->error);
            self::adminDebug('bind_param failed, qerr', $query->error);
            return [];
        }
        if (!$query->execute()) {
            self::adminDebug('execute failed, dberr', $db->error);
            self::adminDebug('execute failed, qerr', $query->error);
            return [];
        }
        if (!($result = $query->get_result())) {
            self::adminDebug('get_result failed, dberr', $db->error);
            self::adminDebug('get_result failed, qerr', $query->error);
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
     * @param string ... $items a string describing the types of all following values, then the values..
     * @return array
     */
    public static function sqlGetTable($sql): array {
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
            self::adminDebug('query failed, dberr', $db->error);
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
    public static function adminDebug(string $str, $obj = null): void {
        global $connection;
        $message = "ADBG: $str" . (is_null($obj) ? "." : ": " . var_export($obj, true));
        file_put_contents('error_log', "$message\n", FILE_APPEND);
        if (isset($connection) && $connection->isSuperAdmin) {
            echo "<pre>$message</pre>\n";
        }
    }

/* Overcomplicated, as we never use params.
    function sqlGetTable($sql, string ...$items) {
        $query='unused';
        $result='unused';
        if ($query = $db->prepare($sql)) {
            if (empty($items) || call_user_func_array(array($query, "bind_param"), self::mkRefArray($items))) {
                if ($result = $query->get_result()) {
                    $table = [];
                    while ($row = $result->fetch_assoc()) {
                        $table []= $row;
                    }
echo "<pre>sGTable($sql):\n" . var_export($table, true) . "</pre>\n";
echo "<pre>sGTable() dberr:\n" . var_export($db->error, true) . "</pre>\n";
echo "<pre>sGTable() qerr:\n" . var_export($query->error, true) . "</pre>\n";
echo "<pre>sGTable() rerr:\n" . var_export($result->error, true) . "</pre>\n";
                    $result->free();
                    return $table;
                } else {
echo "<pre>sGTable($sql):\nget_result failed.</pre>\n";
echo "<pre>sGTable() dberr:\n" . var_export($db->error, true) . "</pre>\n";
echo "<pre>sGTable() qerr:\n" . var_export($query->error, true) . "</pre>\n";
echo "<pre>sGTable() rerr:\n" . var_export($result->error, true) . "</pre>\n";
                }
            } else {
echo "<pre>sGTable($sql):\nbind_param failed.</pre>\n";
echo "<pre>sGTable() dberr:\n" . var_export($db->error, true) . "</pre>\n";
echo "<pre>sGTable() qerr:\n" . var_export($query->error, true) . "</pre>\n";
echo "<pre>sGTable() rerr:\n" . var_export($result->error, true) . "</pre>\n";
            }
        } else {
echo "<pre>sGTable($sql):\nprepare failed.</pre>\n";
echo "<pre>sGTable() dberr:\n" . var_export($db->error, true) . "</pre>\n";
echo "<pre>sGTable() qerr:\n" . var_export($query->error, true) . "</pre>\n";
echo "<pre>sGTable() rerr:\n" . var_export($result->error, true) . "</pre>\n";
        }
echo "<pre>sGTable():\nnull return. Params:" . var_export($items, true) . "</pre>\n";
echo "<pre>sGTable():\nRef Params:" . var_export(self::mkRefArray($items), true) . "</pre>\n";
        return [];
    }
*/
}
