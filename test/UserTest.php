<?php
/** @noinspection PhpUnnecessaryStaticReferenceInspection */
/** @noinspection PhpMethodNamingConventionInspection */
/** @noinspection PhpEnforceDocCommentInspection */
/** @noinspection DuplicatedCode */
// Above because tests have different code standards.

declare(strict_types=1);

use MidwestMemories\Db;
use MidwestMemories\Enum\UserAccess;
use MidwestMemories\User;
use PHPUnit\Framework\TestCase;

/**
 * Stub for Db.
 */
class DbStub extends Db
{
    /**
     * Prevent parent constructor from running.
     * @noinspection PhpMissingParentConstructorInspection
     */
    private function __construct() {}

    /** Allow stubbing of the Singleton. */
    public static function createInstance(): self
    {
        return new self();
    }

    /**
     * Override the Helper for stubbing.
     * @noinspection PhpMissingParentCallCommonInspection
     */
    public function runSqlGetRow(string $sql, int|string ...$params): array
    {
        $hash = password_hash('correct-password', PASSWORD_DEFAULT);
        $dummyUsers = [
            1 => [
                'id' => 1, 'username' => 'alice', 'access_level' => UserAccess::USER->value, 'password_hash' => $hash
            ],
            2 => [
                'id' => 2, 'username' => 'bob', 'access_level' => UserAccess::ADMIN->value, 'password_hash' => $hash
            ],
            3 => [
                'id' => 3, 'username' => 'c', 'access_level' => UserAccess::SUPER_ADMIN->value, 'password_hash' => $hash
            ],
        ];

        switch ($sql) {
            case 'SELECT * FROM `midmem_users` WHERE `username` = ?':
                $userName = $params[1];
                $array = array_filter($dummyUsers, fn($u) => $u['username'] === $userName);
                return array_pop($array) ?? [];
            case 'SELECT * FROM `midmem_users` WHERE `id` = ?':
                $userId = $params[1];
                return $dummyUsers[$userId] ?? [];
            default:
                return ['Unexpected SQL in test stub'];
        }
    }
}

/**
 * Test User class.
 */
final class UserTest extends TestCase
{
    /**
     * This method is called before each test.
     * @codeCoverageIgnore
     */
    protected function setUp(): void
    {
        $_POST = [];
        $_SESSION = []; // Reset session for isolation
        User::resetInstance(); // Ensure a clean singleton for each test.

        Db::setInstance(DbStub::createInstance());
        parent::setUp();
    }

    public function testNotLoggedInBeforeLogin(): void
    {
        $user = User::getInstance();
        UserTest::assertFalse($user->isLoggedIn);
    }

    public function testIsLoggedInAfterLogin(): void
    {
        $_SESSION['userId'] = 1;

        $user = User::getInstance();
        $user->loadFromSession();

        UserTest::assertTrue($user->isLoggedIn);
    }

    public function testLoadFromSessionWithNoUserId(): void
    {
        $_SESSION = []; // no userId

        $user = User::getInstance();
        $user->loadFromSession();

        static::assertFalse($user->isLoggedIn);
    }

    public function testLoadFromSessionWithInvalidUserId(): void
    {
        $_SESSION['userId'] = 'invalid'; // non-integer

        $user = User::getInstance();
        $user->loadFromSession();

        static::assertFalse($user->isLoggedIn);
    }

    public function testLoadFromSessionResetsStateWhenSessionCleared(): void
    {
        // Simulate logged in first
        $_SESSION['userId'] = 1;

        $user = User::getInstance();
        $user->loadFromSession();
        static::assertTrue($user->isLoggedIn);

        // Clear session
        $_SESSION = [];
        $user->loadFromSession();

        static::assertFalse($user->isLoggedIn);
    }

    public function testLoadFromSessionWithUser(): void
    {
        $_SESSION['userId'] = 1;

        $user = User::getInstance();
        $user->loadFromSession();

        self::assertTrue($user->isLoggedIn);
        self::assertEquals(1, $user->userId);
        self::assertEquals('alice', $user->username);
        self::assertTrue($user->isUser);
        self::assertFalse($user->isAdmin);
        self::assertFalse($user->isSuperAdmin);
    }

    public function testLoadFromSessionWithAdmin(): void
    {
        $_SESSION['userId'] = 2;

        $user = User::getInstance();
        $user->loadFromSession();
        self::assertTrue($user->isLoggedIn);
        self::assertEquals(2, $user->userId);
        self::assertEquals('bob', $user->username);
        self::assertTrue($user->isUser);
        self::assertTrue($user->isAdmin);
        self::assertFalse($user->isSuperAdmin);
    }

    public function testLoadFromSessionWithSuperAdmin(): void
    {
        $_SESSION['userId'] = 3;

        $user = User::getInstance();
        $user->loadFromSession();
        self::assertTrue($user->isLoggedIn);
        self::assertEquals(3, $user->userId);
        self::assertEquals('claire', $user->username);
        self::assertTrue($user->isUser);
        self::assertTrue($user->isAdmin);
        self::assertTrue($user->isSuperAdmin);
    }

    public function testHandleUserLoginSuccess(): void
    {
        $_POST['username'] = 'alice';
        $_POST['password'] = 'correct-password';

        $user = User::getInstance();
        $user->handleUserLogin();

        self::assertTrue($user->isLoggedIn);
        self::assertEquals(1, $user->userId);
        self::assertEquals('alice', $user->username);
        self::assertTrue($_SESSION['userId'] === 1);
    }

    public function testHandleUserLoginWrongPassword(): void
    {
        $_POST['username'] = 'alice';
        $_POST['password'] = 'wrong-password';

        $user = User::getInstance();
        $user->handleUserLogin();

        self::assertFalse($user->isLoggedIn);
        self::assertArrayNotHasKey('userId', $_SESSION);
    }

    public function testHandleUserLoginUnknownUser(): void
    {
        $_POST['username'] = 'notfound';
        $_POST['password'] = 'anything';

        $user = User::getInstance();
        $user->handleUserLogin();

        self::assertFalse($user->isLoggedIn);
        self::assertArrayNotHasKey('userId', $_SESSION);
    }

    public function testHandleUserLogout(): void
    {
        // First log in
        $_POST['username'] = 'alice';
        $_POST['password'] = 'correct-password';
        $user = User::getInstance();
        $user->handleUserLogin();

        self::assertTrue($user->isLoggedIn);
        self::assertArrayHasKey('userId', $_SESSION);

        // Now log out
        $user->handleUserLogout();

        self::assertFalse($user->isLoggedIn);
        self::assertArrayNotHasKey('userId', $_SESSION);
    }
}
