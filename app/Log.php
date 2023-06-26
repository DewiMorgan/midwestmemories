<?php

declare(strict_types=1);

namespace MidwestMemories;


/**
 * Static logger.
 * @noinspection PhpClassNamingConventionInspection
 */
class Log
{

    /**
     * Helper: Log as a debug line.
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     */
    public static function debug(string $str, mixed $obj = null): void
    {
        if (Conf::get(Key::LOG_LEVEL) <= LogLevel::debug->value) {
            self::log('Debug: ' . $str, $obj);
        }
    }

    /**
     * Helper: Log as an info line.
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     * @noinspection PhpUnused
     */
    public static function info(string $str, mixed $obj = null): void
    {
        if (Conf::get(Key::LOG_LEVEL) <= LogLevel::info->value) {
            self::log('Info: ' . $str, $obj);
        }
    }

    /**
     * Helper: Log as a warning line.
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     * @noinspection PhpUnused
     */
    public static function warn(string $str, mixed $obj = null): void
    {
        if (Conf::get(Key::LOG_LEVEL) <= LogLevel::warn->value) {
            self::log('Warning: ' . $str, $obj);
        }
    }

    /**
     * Helper: Log as a warning line. Alias of warn().
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     * @noinspection PhpUnused
     */
    public static function warning(string $str, mixed $obj = null): void
    {
        if (Conf::get(Key::LOG_LEVEL) <= LogLevel::warn->value) {
            self::log('Warning: ' . $str, $obj);
        }
    }

    /**
     * Helper: Log as an error line.
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     * @noinspection PhpUnused
     */
    public static function error(string $str, mixed $obj = null): void
    {
        if (Conf::get(Key::LOG_LEVEL) <= LogLevel::error->value) {
            self::log('ERROR: ' . $str, $obj);
        }
    }

    /**
     * Log the given string and optional object to the logfile.
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     * @noinspection PhpMethodNamingConventionInspection
     */
    private static function log(string $str, mixed $obj = null): void
    {
        if (is_null($obj)) {
            file_put_contents(Conf::get(Key::LOG_FILE), $str . '.', FILE_APPEND);
        } else {
            file_put_contents(Conf::get(Key::LOG_FILE), $str . ': ' . var_export($obj, true), FILE_APPEND);
        }
    }

    /**
     * Log a message and an optional object to the log and maybe to the screen.
     * @param string $str
     * @param mixed $obj
     */
    public static function adminDebug(string $str, mixed $obj = null): void
    {
        global $connection;
        $message = "A-DBG: $str" . (is_null($obj) ? '.' : ': ' . var_export($obj, true));
        file_put_contents('error_log', "$message\n", FILE_APPEND);
        if (isset($connection) && $connection->isSuperAdmin) {
            echo "<pre>$message</pre>\n";
        }
    }
}