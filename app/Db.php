<?php
namespace app;

use \mysqli;
use Exception;

class Db {
    private $db;

    function __construct() {
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
    function sqlExec($sql, string ...$items) {
        if ($query = $this->db->prepare($sql)) {
            call_user_func_array(array($query, "bind_param"), self::mkRefArray($items));
            $query->execute();
        }
    }

    // Return the requested item or null.
    function sqlGetItem($sql, $field, string ...$items) {
        self::adminDebug('sqlGetItem', $sql);
        if (!($query = $this->db->prepare($sql))) {
            self::adminDebug('prepare failed, dberr', $this->db->error);
            return null;
        }
        if (!call_user_func_array(array($query, "bind_param"), self::mkRefArray($items))) {
            self::adminDebug('bind_param failed, dberr', $this->db->error);
            self::adminDebug('bind_param failed, qerr', $query->error);
            return null;
        }
        if (!$query->execute()) {
            self::adminDebug('execute failed, dberr', $this->db->error);
            self::adminDebug('execute failed, qerr', $query->error);
            return null;
        }
        if (!($result = $query->get_result())) {
            self::adminDebug('get_result failed, dberr', $this->db->error);
            self::adminDebug('get_result failed, qerr', $query->error);
            return [];
        }
        if (!($row = $result->fetch_assoc())) {
            self::adminDebug('fetch_assoc failed, dberr', $this->db->error);
            self::adminDebug('fetch_assoc failed, qerr', $query->error);
            self::adminDebug('fetch_assoc failed, rerr', $result->error);
            $result->free();
            return null;
        }

        $result->free();
        self::adminDebug('sqlGetItem success: ' . $row[$field] . ' is $field of', $row);
        return $row[$field];
    }

    function sqlGetRow($sql, string ...$items) {
        self::adminDebug('sqlGetRow', $sql);
        if (!($query = $this->db->prepare($sql))) {
            self::adminDebug('prepare failed, dberr', $this->db->error);
            return [];
        }
        if (!call_user_func_array(array($query, "bind_param"), self::mkRefArray($items))) {
            self::adminDebug('bind_param failed, dberr', $this->db->error);
            self::adminDebug('bind_param failed, qerr', $query->error);
            return [];
        }
        if (!$query->execute()) {
            self::adminDebug('execute failed, dberr', $this->db->error);
            self::adminDebug('execute failed, qerr', $query->error);
            return [];
        }
        if (!($result = $query->get_result())) {
            self::adminDebug('get_result failed, dberr', $this->db->error);
            self::adminDebug('get_result failed, qerr', $query->error);
            return [];
        }

        $row = $result->fetch_assoc();
        $result->free();
        self::adminDebug('sqlGetRow success', $row);
        return $row;
    }

    function sqlGetTable($sql, string ...$items) {
        self::adminDebug('sqlGetTable', $sql);
        if ($result = $this->db->query($sql)) {
            $table = [];
            while ($row = $result->fetch_assoc()) {
                $table []= $row;
            }
            $result->free();
            return $table;
        } else {
            self::adminDebug('query failed, dberr', $this->db->error);
        }
        return [];
    }

    // Sadly, bind_param expects references, which makes passing arrays harder.
    private static function mkRefArray($input) {
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

    public static function adminDebug($str, $obj = null) {
        global $connection;
        $message = "ADBG: $str" . (is_null($obj) ? "." : ": " . var_export($obj, true));
        fwrite(STDERR, "$message\n");
        if ($connection->isSuperAdmin) {
            echo "<pre>$message</pre>\n";
        }
    }

/* Overcomplicated, we never use params.
    function sqlGetTable($sql, string ...$items) {
        $query='unused';
        $result='unused';
        if ($query = $this->db->prepare($sql)) {
            if (empty($items) || call_user_func_array(array($query, "bind_param"), self::mkRefArray($items))) {
                if ($result = $query->get_result()) {
                    $table = [];
                    while ($row = $result->fetch_assoc()) {
                        $table []= $row;
                    }
echo "<pre>sGTable($sql):\n" . var_export($table, true) . "</pre>\n";
echo "<pre>sGTable() dberr:\n" . var_export($this->db->error, true) . "</pre>\n";
echo "<pre>sGTable() qerr:\n" . var_export($query->error, true) . "</pre>\n";
echo "<pre>sGTable() rerr:\n" . var_export($result->error, true) . "</pre>\n";
                    $result->free();
                    return $table;
                } else {
echo "<pre>sGTable($sql):\nget_result failed.</pre>\n";
echo "<pre>sGTable() dberr:\n" . var_export($this->db->error, true) . "</pre>\n";
echo "<pre>sGTable() qerr:\n" . var_export($query->error, true) . "</pre>\n";
echo "<pre>sGTable() rerr:\n" . var_export($result->error, true) . "</pre>\n";
                }
            } else {
echo "<pre>sGTable($sql):\nbind_param failed.</pre>\n";
echo "<pre>sGTable() dberr:\n" . var_export($this->db->error, true) . "</pre>\n";
echo "<pre>sGTable() qerr:\n" . var_export($query->error, true) . "</pre>\n";
echo "<pre>sGTable() rerr:\n" . var_export($result->error, true) . "</pre>\n";
            }
        } else {
echo "<pre>sGTable($sql):\nprepare failed.</pre>\n";
echo "<pre>sGTable() dberr:\n" . var_export($this->db->error, true) . "</pre>\n";
echo "<pre>sGTable() qerr:\n" . var_export($query->error, true) . "</pre>\n";
echo "<pre>sGTable() rerr:\n" . var_export($result->error, true) . "</pre>\n";
        }
echo "<pre>sGTable():\nnull return. Params:" . var_export($items, true) . "</pre>\n";
echo "<pre>sGTable():\nRef Params:" . var_export(self::mkRefArray($items), true) . "</pre>\n";
        return [];
    }
*/
}