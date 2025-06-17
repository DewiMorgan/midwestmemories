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
use MidwestMemories\UserManager;
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
                'id' => 3, 'username' => 'claire', 'access_level' => UserAccess::SUPER_ADMIN->value,
                'password_hash' => $hash
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
    private Db $dbStub;

    /**
     * This method is called before each test.
     * @codeCoverageIgnore
     */
    protected function setUp(): void
    {
        $_POST = [];
        $_SESSION = []; // Reset session for isolation
        User::resetInstance(); // Ensure a clean singleton for each test.

        $this->dbStub = DbStub::createInstance();
        parent::setUp();
    }

    private function useRealDb(): void
    {
        Db::resetInstance();
        Db::sqlExec('DELETE FROM midmem.midmem_users WHERE TRUE');
    }

    public function testNotLoggedInBeforeLogin(): void
    {
        Db::setInstance($this->dbStub);
        $user = User::getInstance();
        UserTest::assertFalse($user->isLoggedIn);
    }

    public function testIsLoggedInAfterLogin(): void
    {
        Db::setInstance($this->dbStub);
        $_SESSION['userId'] = 1;

        $user = User::getInstance();
        $user->loadFromSession();

        UserTest::assertTrue($user->isLoggedIn);
    }

    public function testLoadFromSessionWithNoUserId(): void
    {
        Db::setInstance($this->dbStub);
        $_SESSION = []; // no userId

        $user = User::getInstance();
        $user->loadFromSession();

        static::assertFalse($user->isLoggedIn);
    }

    public function testLoadFromSessionWithInvalidUserId(): void
    {
        Db::setInstance($this->dbStub);
        $_SESSION['userId'] = 'invalid'; // non-integer

        $user = User::getInstance();
        $user->loadFromSession();

        static::assertFalse($user->isLoggedIn);
    }

    public function testLoadFromSessionResetsStateWhenSessionCleared(): void
    {
        Db::setInstance($this->dbStub);
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
        Db::setInstance($this->dbStub);
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
        Db::setInstance($this->dbStub);
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
        Db::setInstance($this->dbStub);
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
        Db::setInstance($this->dbStub);
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
        Db::setInstance($this->dbStub);
        $_POST['username'] = 'alice';
        $_POST['password'] = 'wrong-password';

        $user = User::getInstance();
        $user->handleUserLogin();

        self::assertFalse($user->isLoggedIn);
        self::assertArrayNotHasKey('userId', $_SESSION);
    }

    public function testHandleUserLoginUnknownUser(): void
    {
        Db::setInstance($this->dbStub);
        $_POST['username'] = 'notfound';
        $_POST['password'] = 'anything';

        $user = User::getInstance();
        $user->handleUserLogin();

        self::assertFalse($user->isLoggedIn);
        self::assertArrayNotHasKey('userId', $_SESSION);
    }

    public function testHandleUserLogout(): void
    {
        Db::setInstance($this->dbStub);
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

    public function testAddUserSuccess(): void
    {
        $this->useRealDb();
        $params = ['username' => 'new_user', 'password' => 'securePassword'];

        $result = UserManager::addUserNew($params);
        self::assertIsArray($result);
        self::assertSame(200, $result['status']);
        self::assertSame('OK', $result['data']);
    }

    public function testAddUserMissingUsername(): void
    {
        $this->useRealDb();
        $params = ['password' => 'securePassword'];

        $result = UserManager::addUserNew($params);

        self::assertIsArray($result);
        self::assertSame(400, $result['status']);
        self::assertStringStartsWith('Error:', $result['data']);
    }

    public function testAddUserMissingPassword(): void
    {
        $this->useRealDb();
        $params = ['username' => 'new_user'];

        $result = UserManager::addUserNew($params);

        self::assertIsArray($result);
        self::assertSame(400, $result['status']);
        self::assertStringStartsWith('Error:', $result['data']);
    }

    public function testAddUserAlreadyExists(): void
    {
        $this->useRealDb();
        $params = ['username' => 'existing_user', 'password' => 'whatever'];

        // First add should succeed
        UserManager::addUserNew($params);

        // Second add should fail
        $result = UserManager::addUserNew($params);

        self::assertIsArray($result);
        self::assertSame(500, $result['status']);
        self::assertStringStartsWith('Error:', $result['data']);
    }

    public function testAddUserStoresUserInDatabase(): void
    {
        $this->useRealDb();
        $username = 'new_user';
        $password = 'secret';

        $result = UserManager::addUserNew(['username' => $username, 'password' => $password]);

        static::assertIsArray($result);
        static::assertSame(200, $result['status']);
        static::assertSame('OK', $result['data']);

        // Check user is now in the database (via mock or test DB)
        $user = Db::sqlGetRow('SELECT * FROM midmem_users WHERE username = ?', 's', $username);
        static::assertNotEmpty($user);
        static::assertTrue(password_verify($password, $user['password_hash']));
    }

    public function testDeleteUserSuccess(): void
    {
        $username = 'temp_user';

        // Precondition: user must exist to delete successfully
        UserManager::addUser(['username' => $username, 'password' => 'irrelevant']);

        $result = UserManager::delete(['username' => $username]);

        static::assertIsArray($result);
        static::assertSame(200, $result['status']);
        static::assertSame('OK', $result['data']);
    }

    public function testDeleteUserMissingUsername(): void
    {
        $result = UserManager::delete([]); // No username key

        static::assertIsArray($result);
        static::assertSame(500, $result['status']);
        static::assertStringStartsWith('Error:', $result['data']);
    }

    public function testDeleteNonexistentUser(): void
    {
        $result = UserManager::delete(['username' => 'ghost_user']);

        static::assertIsArray($result);
        static::assertSame(500, $result['status']);
        static::assertStringStartsWith('Error:', $result['data']);
    }

    public function testDeleteUserSetsDisabledFlag(): void
    {
        $this->useRealDb();
        // Prepopulate user
        $username = 'deleteme';
        $passwordHash = password_hash('irrelevant', PASSWORD_DEFAULT);

        Db::sqlExec(
            'INSERT INTO midmem_users (username, password_hash, access_level, is_disabled) VALUES (?, ?, ?, 0)',
            'ssi',
            $username,
            $passwordHash,
            UserAccess::USER->value
        );

        // Act
        $result = UserManager::deleteNew(['username' => $username]);

        // Assert
        static::assertSame(200, $result['status']);
        static::assertSame('OK', $result['data']);

        $row = Db::sqlGetRow('SELECT is_disabled FROM midmem_users WHERE username = ?', 's', $username);
        static::assertNotEmpty($row);
        static::assertSame(1, (int)$row['is_disabled']);
    }

    public function testChangePasswordSuccess(): void
    {
        $username = 'charlie';
        $originalPassword = 'oldPass';
        $newPassword = 'newSecurePass';

        // Ensure user exists
        UserManager::addUser(['username' => $username, 'password' => $originalPassword]);

        $result = UserManager::changePassword(['username' => $username, 'password' => $newPassword]);

        static::assertIsArray($result);
        static::assertSame(200, $result['status']);
        static::assertSame('OK', $result['data']);
    }

    public function testChangePasswordForNonexistentUser(): void
    {
        $result = UserManager::changePassword(['username' => 'nonexistent', 'password' => 'whatever']);
        static::assertIsArray($result);
        static::assertSame(500, $result['status']);
        static::assertStringStartsWith('Error:', $result['data']);
    }

    public function testChangePasswordMissingUsername(): void
    {
        $result = UserManager::changePassword(['password' => 'whatever']);

        static::assertIsArray($result);
        static::assertSame(500, $result['status']);
        static::assertStringStartsWith('Error:', $result['data']);
    }

    public function testChangePasswordMissingPassword(): void
    {
        $result = UserManager::changePassword(['username' => 'alice']);

        static::assertIsArray($result);
        static::assertSame(500, $result['status']);
        static::assertStringStartsWith('Error:', $result['data']);
    }

    public function testGetUsersIncludesAddedUser(): void
    {
        $username = 'dave';
        $password = 'hunter2';

        UserManager::addUser(['username' => $username, 'password' => $password]);

        $result = UserManager::getUsers();

        static::assertIsArray($result);
        static::assertSame(200, $result['status']);
        static::assertIsArray($result['data']);

        $usernames = array_column($result['data'], 'username');
        static::assertContains($username, $usernames);
    }

    public function testGetUsersIncludesCommentField(): void
    {
        $username = 'ellen';
        $password = 'abc123';

        UserManager::addUser(['username' => $username, 'password' => $password]);

        $result = UserManager::getUsers();

        static::assertIsArray($result);
        static::assertSame(200, $result['status']);

        $user = array_filter($result['data'], fn($u) => $u['username'] === $username);
        $user = array_values($user)[0] ?? null;

        static::assertNotNull($user, 'User was not found in list');
        static::assertArrayHasKey('comment', $user);
    }
}
