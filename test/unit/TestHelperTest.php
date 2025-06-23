<?php
/** @noinspection PhpMethodNamingConventionInspection */
/** @noinspection PhpClassNamingConventionInspection */
/** @noinspection PhpEnforceDocCommentInspection */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once 'test/TestHelper.php';

/**
 * Test the ApiTestHelper class.
 */
class TestHelperTest extends TestCase
{
    /**
     * This method is called before the first test of this test class is run.
     * @codeCoverageIgnore
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TestHelper::startServer();
    }

    /**
     * This method is called after the last test of this test class is run.
     * @codeCoverageIgnore
     */
    public static function tearDownAfterClass(): void
    {
        TestHelper::stopServer();
        parent::tearDownAfterClass();
    }

    /**
     * This method is called before each test.
     * @codeCoverageIgnore
     */
    protected function setUp(): void
    {
        parent::setUp();
        TestHelper::insertTestUsers();
    }

    /**
     * This method is called after each test.
     *
     * @codeCoverageIgnore
     */
    protected function tearDown(): void
    {
        TestHelper::removeTestUsers();
        parent::tearDown();
    }

    public function testLoginAsSucceedsWithValidCredentials(): void
    {
        TestHelper::loginAs(TestHelper::USER_NAME, TestHelper::PASSWORD);
        static::assertTrue(true, 'Login should succeed with valid credentials.');
        $cookieFiles = glob('/tmp/cookie*');
        static::assertNotEmpty($cookieFiles, 'No files starting with "cookie" found in /tmp');
    }

    public function testLoginAsThrowsExceptionWithInvalidPassword(): void
    {
        static::expectException(RuntimeException::class);
        static::expectExceptionMessage("Login failed for user 'test_user' with status 403");

        TestHelper::loginAs(TestHelper::USER_NAME, 'wrong_password');
    }

    public function testLoginAsThrowsExceptionWithUnknownUser(): void
    {
        static::expectException(RuntimeException::class);
        static::expectExceptionMessage("Login failed for user 'unknown_user' with status 403");

        TestHelper::loginAs('unknown_user', 'any_password');
    }
}
