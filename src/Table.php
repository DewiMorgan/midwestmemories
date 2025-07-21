<?php
declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

/**
 * Generator for table names.
 * @method static comments()
 * @method static dropbox_users()
 * @method static file_queue()
 * @method static rate_limits()
 * @method static users()
 * @method static visitors()
 */
class Table
{
    private static string $prefix = '';

    /**
     * Honestly, this is one of those few cases where magic methods are the best option.
     * Get the table name, with the correct prefix.
     * @return string The prefix for db table names.
     */
    public static function __callStatic(string $name, array $arguments): string
    {
        if (!isset(self::$prefix) || self::$prefix === '') {
            self::$prefix = Conf::get(Key::MYSQL_PREFIX) ?? 'midmem_';
            Log::debug('Set table prefix to ' . self::$prefix, Conf::get(Key::MYSQL_PREFIX)); // DELETEME DEBUG
            Log::debug('Conf gave prefix ' . var_export(Conf::get(Key::MYSQL_PREFIX), true)); // DELETEME DEBUG
        }
        return self::$prefix . $name;
    }
}
