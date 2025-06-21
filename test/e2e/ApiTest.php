<?php
/** @noinspection PhpUnnecessaryStaticReferenceInspection */
/** @noinspection PhpMethodNamingConventionInspection */
/** @noinspection PhpEnforceDocCommentInspection */
/** @noinspection DuplicatedCode */
// Above because tests have different code standards.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

echo `pwd`;
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
        ApiTestHelper::loginAs('admin', 'secret');
        $response = ApiTestHelper::request('DELETE', '/api/v1.0/user', [
            'username' => 'bob'
        ]);

        static::assertSame(200, $response['status']);
        static::assertSame('OK', trim($response['body']));
    }

    public function testSuccessfulLoginReturnsOk(): void
    {
        // Insert test user beforehand, or ensure it exists in your test DB.
        // Username: 'test_user', Password: 'test_pass' (password must be pre-hashed in the DB)

        $response = ApiTestHelper::request('POST', '/api/v1.0/login', [
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);

        static::assertEquals(200, $response['status'], 'Login should return 200 on success');

        $data = json_decode($response['body'], true);
        static::assertIsArray($data);
        static::assertEquals('OK', $data['data'] ?? '');
    }

    public function testLoginFailsWithBadPassword(): void
    {
        $response = ApiTestHelper::request('POST', '/api/v1.0/login', [
            'username' => 'test_user',
            'password' => 'wrong_pass'
        ]);

        static::assertEquals(401, $response['status'], 'Bad password should return 401');

        $data = json_decode($response['body'], true);
        static::assertIsArray($data);
        static::assertStringStartsWith('Error:', $data['data'] ?? '');
    }

    public function testLoginFailsWithUnknownUser(): void
    {
        $response = ApiTestHelper::request('POST', '/api/v1.0/login', [
            'username' => 'unknown_user',
            'password' => 'irrelevant'
        ]);

        static::assertEquals(404, $response['status'], 'Unknown user should return 404');

        $data = json_decode($response['body'], true);
        static::assertIsArray($data);
        static::assertStringContainsString('not found', strtolower($data['data'] ?? ''));
    }

}
