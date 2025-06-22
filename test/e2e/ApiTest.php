<?php
/** @noinspection PhpUnnecessaryStaticReferenceInspection */
/** @noinspection PhpMethodNamingConventionInspection */
/** @noinspection PhpEnforceDocCommentInspection */
/** @noinspection DuplicatedCode */
// Above because tests have different code standards.

declare(strict_types=1);

use MidwestMemories\Db;
use MidwestMemories\Enum\UserAccess;
use PHPUnit\Framework\TestCase;

require_once('test/ApiTestHelper.php');

/**
 * Test API endpoints.
 */
class ApiTest extends TestCase
{
    /**
     * This method is called before the first test of this test class is run.
     * @codeCoverageIgnore
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ApiTestHelper::startServer();
    }

    /**
     * This method is called after the last test of this test class is run.
     * @codeCoverageIgnore
     */
    public static function tearDownAfterClass(): void
    {
        ApiTestHelper::stopServer();
        parent::tearDownAfterClass();
    }

    /**
     * This method is called before each test.
     * @codeCoverageIgnore
     */
    protected function setUp(): void
    {
        parent::setUp();
        // TestDbSeeder::resetDatabase();
    }

    public function testDeleteUserAsAdmin(): void
    {
        $username = 'test_admin';
        $password = 'test_pass';
        Db::sqlExec(
            '
            INSERT INTO midmem_users (username, password_hash, access_level, is_disabled) VALUES (?, ?, ?, ?)',
            'ssii',
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            UserAccess::ADMIN->value,
            0
        );
        ApiTestHelper::loginAs($username, $password);
        $response = ApiTestHelper::request('DELETE', '/api/v1.0/user', [
            'username' => 'bob'
        ]);
        // Cleanup before the asserts.
        Db::sqlExec('DELETE FROM midmem_users WHERE username = ?', 's', $username);
var_export($response);
        static::assertSame(200, $response['status']);
        static::assertSame('OK', $response['body']);
    }

    public function testSuccessfulLoginReturnsOk(): void
    {
        // Insert test user beforehand, or ensure it exists in your test DB.
        // Username: 'test_user', Password: 'test_pass' (password must be pre-hashed in the DB)
        Db::sqlExec(
            '
            INSERT INTO midmem_users (username, password_hash, access_level, is_disabled) VALUES (?, ?, ?, ?)',
            'ssii',
            'test_user',
            password_hash('test_pass', PASSWORD_DEFAULT),
            UserAccess::USER->value,
            0
        );

        $response = ApiTestHelper::request('POST', '/api/v1.0/login', [
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);
        // Cleanup before the asserts.
        Db::sqlExec('DELETE FROM midmem_users WHERE username = ?', 's', 'test_user');

        static::assertEquals(200, $response['status'], 'Login should return 200 on success');

        $data = json_decode($response['data'], true);
        static::assertIsArray($data);
        static::assertEquals('OK', $data['data'] ?? '');
    }

    public function testLoginFailsWithBadPassword(): void
    {
        $response = ApiTestHelper::request('POST', '/api/v1.0/login', [
            'username' => 'test_user',
            'password' => 'wrong_pass'
        ]);

        static::assertEquals(403, $response['status'], 'Access denied response.');

        $data = json_decode($response['data'], true);
        static::assertIsArray($data);
        static::assertStringStartsWith('Error:', $data['data'] ?? '');
    }

    public function testLoginFailsWithUnknownUser(): void
    {
        $response = ApiTestHelper::request('POST', '/api/v1.0/login', [
            'username' => 'unknown_user',
            'password' => 'irrelevant'
        ]);

        static::assertEquals(403, $response['status'], 'Unknown user should return 403');

        $data = json_decode($response['data'], true);
        static::assertIsArray($data);
        static::assertStringStartsWith('Error:', $data['data'] ?? '');
    }
}
