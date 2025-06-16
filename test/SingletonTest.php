<?php
/** @noinspection PhpUnnecessaryStaticReferenceInspection */
/** @noinspection PhpMethodNamingConventionInspection */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use MidwestMemories\Singleton;

/**
 * Concrete test subclass of Singleton for testing.
 */
class TestSingleton extends Singleton
{
    public int $value = 0;

    /** Allow stubbing of a manual instance. */
    public static function createForTest(): self
    {
        return new self();
    }
}

/**
 * Test the Singleton parent class.
 */
final class SingletonTest extends TestCase
{
    /**
     * This method is called before each test.
     *
     * @codeCoverageIgnore
     */
    protected function setUp(): void
    {
        // Reset singleton state before each test
        TestSingleton::resetInstance();
        parent::setUp();
    }

    /**
     * @return void
     */
    public function testGetInstanceReturnsSameObject(): void
    {
        $a = TestSingleton::getInstance();
        $b = TestSingleton::getInstance();

        static::assertSame($a, $b, 'getInstance should return the same object');
    }

    /**
     * @return void
     */
    public function testResetInstanceCreatesNewObject(): void
    {
        $a = TestSingleton::getInstance();
        $a->value = 42;

        TestSingleton::resetInstance();
        $b = TestSingleton::getInstance();

        static::assertNotSame($a, $b, 'resetInstance should discard the old instance');
        static::assertSame(0, $b->value, 'New instance should have default state');
    }

    /**
     * @return void
     */
    public function testSetInstanceOverridesInstance(): void
    {
        $mock = TestSingleton::createForTest();
        $mock->value = 99;

        TestSingleton::setInstance($mock);

        $retrieved = TestSingleton::getInstance();

        static::assertSame($mock, $retrieved, 'setInstance should override the current instance');
        static::assertSame(99, $retrieved->value);
    }

    /**
     * @return void
     */
    public function testSetInstanceRejectsWrongType(): void
    {
        static::expectException(InvalidArgumentException::class);
        static::expectExceptionMessage('Instance must be of type');

        // Object of the wrong type
        TestSingleton::setInstance(new class {});
    }
}
