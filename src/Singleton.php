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
     */
    private function __clone() {}

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

    /**
     * Reset the singleton instance for the subclass.
     * Useful for testing.
     */
    public static function resetInstance(): void
    {
        $class = static::class;
        unset(self::$instances[$class]);
    }

    /**
     * Manually set the singleton instance.
     * Useful for injecting mocks in tests.
     *
     * @param object $instance
     */
    public static function setInstance(object $instance): void
    {
        $class = static::class;
        if (!$instance instanceof static) {
            throw new \InvalidArgumentException("Instance must be of type {$class}");
        }
        self::$instances[$class] = $instance;
    }
}
