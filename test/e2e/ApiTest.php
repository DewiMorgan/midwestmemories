<?php
/** @noinspection PhpUnnecessaryStaticReferenceInspection */
/** @noinspection PhpMethodNamingConventionInspection */
/** @noinspection PhpEnforceDocCommentInspection */
/** @noinspection DuplicatedCode */
// Above because tests have different code standards.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test API endpoints.
 */
class ApiTest extends TestCase
{
    public const DISABLED_NAME = 'disabled_user';
    public const USER_NAME = 'test_user';
    public const ADMIN_NAME = 'test_admin';
    public const SUPERADMIN_NAME = 'test_superadmin';
    public const PASSWORD = 'test_pass';

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
     * @codeCoverageIgnore
     */
    protected function tearDown(): void
    {
        TestHelper::removeTestUsers();
        parent::tearDown();
    }

    public function testDeleteUserAsAdmin(): void
    {
        TestHelper::loginAs(self::ADMIN_NAME, self::PASSWORD);
        $response = TestHelper::request('DELETE', '/api/v1.0/user/' . self::USER_NAME);
        $data = json_decode($response['data'], true);
        static::assertSame(200, $response['status']);
        static::assertIsArray($data);
        static::assertSame('OK', $data['data']);
    }

    public function testSuccessfulLoginReturnsOk(): void
    {
        $response = TestHelper::request('POST', '/api/v1.0/login', [
            'username' => self::USER_NAME,
            'password' => self::PASSWORD
        ]);

        static::assertEquals(200, $response['status'], 'Login should return 200 on success');

        $data = json_decode($response['data'], true);
        static::assertIsArray($data);
        static::assertEquals('OK', $data['data'] ?? '');
    }

    public function testLoginFailsWithBadPassword(): void
    {
        $response = TestHelper::request('POST', '/api/v1.0/login', [
            'username' => self::USER_NAME,
            'password' => 'wrong_pass'
        ]);

        static::assertEquals(403, $response['status'], 'Access denied response.');

        $data = json_decode($response['data'], true);
        static::assertIsArray($data);
        static::assertStringStartsWith('Error:', $data['data'] ?? '');
    }

    public function testLoginFailsWithUnknownUser(): void
    {
        $response = TestHelper::request('POST', '/api/v1.0/login', [
            'username' => 'test_unknown_user',
            'password' => 'irrelevant'
        ]);

        static::assertEquals(403, $response['status'], 'Unknown user should return 403');

        $data = json_decode($response['data'], true);
        static::assertIsArray($data);
        static::assertStringStartsWith('Error:', $data['data'] ?? '');
    }
}
