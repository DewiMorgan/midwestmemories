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

    public function testGetUsersRequiresAdmin(): void
    {
        // Regular user should be denied
        TestHelper::loginAs(self::USER_NAME, self::PASSWORD);
        $response = TestHelper::request('GET', '/api/v1.0/user');
        static::assertEquals(403, $response['status'], 'Non-admin should be denied access to list users');

        // Admin should be allowed
        TestHelper::loginAs(self::ADMIN_NAME, self::PASSWORD);
        $response = TestHelper::request('GET', '/api/v1.0/user');
        static::assertEquals(200, $response['status'], 'Admin should be able to list users');

        $data = json_decode($response['data'], true);
        static::assertIsArray($data);
        static::assertArrayHasKey('data', $data);
        static::assertIsArray($data['data']);
    }

    public function testAddUser(): void
    {
        TestHelper::loginAs(self::ADMIN_NAME, self::PASSWORD);
        $newUser = 'new_test_user_' . time();

        // Test successful user creation
        $response = TestHelper::request('POST', '/api/v1.0/user', [
            'username' => $newUser,
            'password' => 'new_user_pass123'
        ]);

        static::assertEquals(200, $response['status'], 'Should be able to add new user');
        $data = json_decode($response['data'], true);
        static::assertSame('OK', $data['data']);

        // Test duplicate user
        $response = TestHelper::request('POST', '/api/v1.0/user', [
            'username' => $newUser,
            'password' => 'another_pass'
        ]);
        static::assertEquals(400, $response['status'], 'Should not allow duplicate usernames');
    }

    public function testChangePassword(): void
    {
        $testUser = 'change_pass_user_' . time();

        // First create a test user
        TestHelper::loginAs(self::ADMIN_NAME, self::PASSWORD);
        TestHelper::request('POST', '/api/v1.0/user', [
            'username' => $testUser,
            'password' => 'initial_pass'
        ]);

        // Change the password
        $response = TestHelper::request('PUT', '/api/v1.0/user', [
            'username' => $testUser,
            'password' => 'new_secure_pass123'
        ]);

        static::assertEquals(200, $response['status'], 'Should be able to change password');
        $data = json_decode($response['data'], true);
        static::assertSame('OK', $data['data']);

        // Verify the new password works
        TestHelper::logout();
        $response = TestHelper::request('POST', '/api/v1.0/login', [
            'username' => $testUser,
            'password' => 'new_secure_pass123'
        ]);
        static::assertEquals(200, $response['status'], 'Should be able to login with new password');
    }

    public function testDeleteUser(): void
    {
        $testUser = 'delete_test_user_' . time();

        // Create a test user
        TestHelper::loginAs(self::ADMIN_NAME, self::PASSWORD);
        TestHelper::request('POST', '/api/v1.0/user', [
            'username' => $testUser,
            'password' => 'temp_pass'
        ]);

        // Delete the user
        $response = TestHelper::request('DELETE', "/api/v1.0/user/$testUser");
        static::assertEquals(200, $response['status'], 'Should be able to delete user');
        $data = json_decode($response['data'], true);
        static::assertSame('OK', $data['data']);

        // Verify user is deleted
        $response = TestHelper::request('POST', '/api/v1.0/login', [
            'username' => $testUser,
            'password' => 'temp_pass'
        ]);
        static::assertEquals(403, $response['status'], 'Deleted user should not be able to login');
    }

}
