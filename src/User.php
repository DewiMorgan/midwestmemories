<?php
declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\UserAccess;

/*
 *  ToDo:
 *    ShowLoginForm()
 *    HandleUserChangePassword()
 *    HandleUserResetPassword()
 *    HandleAdminCreateUser()
 *    HandleAdminDisableUser()
 *    HandleAdminChangeUserPassword()
 */

/**
 * Handle user auth, passwords management, access levels, etc.
 */
class User extends Singleton
{
    public bool $isLoggedIn = false;
    public int $userId = 0;
    public string $username = '';
    public bool $isUser = false;
    public bool $isAdmin = false;
    public bool $isSuperAdmin = false;

    /**
     * Get the userid from the session, then load the user info from that.
     */
    public function loadFromSession(): void
    {
        // Check if userId present in session and valid
        if (!isset($_SESSION['userId']) || !is_int($_SESSION['userId']) || $_SESSION['userId'] <= 0) {
            $this->handleUserLogout();
            return; // no valid userId, so remain logged out
        }

        $userId = $_SESSION['userId'];

        // Fetch user data from the DB.
        $sql = 'SELECT * FROM `midmem_users` WHERE `id` = ?';
        $user = Db::sqlGetRow($sql, 'd', $userId);
        $this->populateUser($user);
    }

    /**
     * Attempt to authenticate the user using POST data.
     */
    public function handleUserLogin(): void
    {
        // Validate input.
        if (empty($_POST['username']) || empty($_POST['password'])) {
            // Missing credentials.
            $this->handleUserLogout();
            return;
        }

        $username = trim($_POST['username']);
        $password = $_POST['password'];
        // Try to authenticate.
        $sql = 'SELECT * FROM `midmem_users` WHERE `username` = ?';
        $user = Db::sqlGetRow($sql, 's', $username);

        if ($user && password_verify($password, $user['password_hash'] ?? '')) {
            $this->populateUser($user);
        } else {
            $this->handleUserLogout();
        }
    }

    /**
     * Set internal flags and properties based on user identity and access.
     */
    private function populateUser(array $user): void
    {
        $accessLevel = $user['access_level'] ?? 0;
        $this->userId = $user['id'] ?? 0;
        $this->username = $user['username'] ?? 'Guest';
        $this->isUser = $accessLevel >= UserAccess::USER->value;
        $this->isAdmin = $accessLevel >= UserAccess::ADMIN->value;
        $this->isSuperAdmin = $accessLevel >= UserAccess::SUPER_ADMIN->value;
        // Users with no access level can't log in.
        $this->isLoggedIn = $this->userId > 0 && $this->isUser;
        // Set session user (or zero it, if login failed).
        $_SESSION['userId'] = $this->userId;
    }

    /**
     * Handle a request from the user to log them out.
     */
    public function handleUserLogout(): void
    {
        $this->populateUser([]);
        unset($_SESSION['userId']);
        // We can also completely clobber the session, but that will break CSRF, etc, too.
        // $_SESSION = [];
        // session_destroy();
    }
}
