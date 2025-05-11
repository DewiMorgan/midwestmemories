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
            self::log('Debug: ' . self::getCallerInfo() . $str, $obj);
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
            self::log('Info: ' . self::getCallerInfo() . $str, $obj);
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
            self::log('Warning: ' . self::getCallerInfo() . $str, $obj);
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
            self::log('Warning: ' . self::getCallerInfo() . $str, $obj);
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
            self::log('ERROR: ' . self::getCallerInfo() . $str, $obj);
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
            file_put_contents(Conf::get(Key::LOG_FILE), $str . ".\n", FILE_APPEND);
        } else {
            file_put_contents(Conf::get(Key::LOG_FILE), $str . ': ' . var_export($obj, true) . "\n", FILE_APPEND);
        }
    }

    /**
     * Get call stack info about the external method that called this class, to prepend to log lines.
     * @return string Brief one-line human-readable description of caller.
     */
    private static function getCallerInfo(): string
    {
        $prevFile = 'Unknown File';
        $prevLine = '?';

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4) as $method) {
            // Skip lines from this class.
            if (!array_key_exists('class', $method) || __CLASS__ !== $method['class']) {
                return basename($prevFile) . "($prevLine) $method[function](): ";
            }
            $prevFile = $method['file'] ?? 'Unknown File';
            $prevLine = $method['line'] ?? '?';
        }
        return '[In global code]';
    }
}
