<?php

declare(strict_types=1);

namespace MidwestMemories;

use http\Params;

/**
 * Config manager.
 */
class Conf
{
    // A bunch of static keys.
    public const LOG_FILE = 'logFile';
    public const LOG_LEVEL = 'logLevel';

    private static array|null $data = null;

    /**
     * @param string $key
     * @return string|null
     * @noinspection PhpMethodNamingConventionInspection
     */
    public static function get(string $key): string|null
    {
        if (is_null(self::$data)) {
            self::initialize();
        }
        if (array_key_exists($key, self::$data)) {
            return self::$data[$key];
        } else {
            return null;
        }
    }

    /**
     * Populate the configuration class.
     */
    private static function initialize(): void
    {
        self::$data = [
            self::LOG_FILE => 'mm.log',
            self::LOG_LEVEL => LogLevel::warn,
        ];
    }
}