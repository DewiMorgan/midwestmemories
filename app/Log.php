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
        if (Conf::get(Conf::LOG_LEVEL) <= LogLevel::debug) {
            self::log('Debug: ' . $str, $obj);
        }
    }

    /**
     * Helper: Log as an info line.
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     */
    public static function info(string $str, mixed $obj = null): void
    {
        if (Conf::get(Conf::LOG_LEVEL) <= LogLevel::info) {
            self::log('Info: ' . $str, $obj);
        }
    }

    /**
     * Helper: Log as a warning line.
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     */
    public static function warn(string $str, mixed $obj = null): void
    {
        if (Conf::get(Conf::LOG_LEVEL) <= LogLevel::warn) {
            self::log('Warning: ' . $str, $obj);
        }
    }

    /**
     * Helper: Log as a warning line. Alias of warn().
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     */
    public static function warning(string $str, mixed $obj = null): void
    {
        if (Conf::get(Conf::LOG_LEVEL) <= LogLevel::warn) {
            self::log('Warning: ' . $str, $obj);
        }
    }

    /**
     * Helper: Log as an error line.
     * @param string $str The string to log.
     * @param mixed|null $obj The object to serialize and log, if any.
     */
    public static function error(string $str, mixed $obj = null): void
    {
        if (Conf::get(Conf::LOG_LEVEL) <= LogLevel::error) {
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
            file_put_contents(Conf::get(Conf::LOG_FILE), $str . '.');
        } else {
            file_put_contents(Conf::get(Conf::LOG_FILE), $str . ': ' . var_export($obj, true));
        }
    }
}