<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Singleton parent class. Inherit to have getInstance().
 */
abstract class Singleton
{
    /** @var array<class-string, object> Stores instances of subclasses */
    private static array $instances = [];

    /**
     * Prevent external construction.
     */
    protected function __construct() {}

    /**
     * Prevent cloning.
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function __wakeup() {}

    /**
     * Get the singleton instance of the subclass.
     * @return static
     */
    public static function getInstance(): static
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        /** @var static */
        return self::$instances[$class];
    }
}
