<?php
declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\UserAccess;

/*
 *  ToDo:
 *    ShowLoginForm()
 *    HandleUserChangePassword()
 *    HandleUserResetPassword()
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

    /**
     * Get a string representation of the user object, primarily for debugging.
     * @return string Serialized user data.
     */
    public function __toString(): string
    {
        return var_export([
            'userId' => $this->userId,
            'username' => $this->username,
            'isLoggedIn' => $this->isLoggedIn,
            'isUser' => $this->isUser,
            'isAdmin' => $this->isAdmin,
            'isSuperAdmin' => $this->isSuperAdmin
        ], true);
    }

    /**
     * Adds a new user to the users table.
     * Sets status 500 if the user already exists, or an error occurs.
     * @param array $params ['username' => {string}, 'password' => {string}]
     * @return array ['status' => {int Http Status}, 'data' => 'OK' or 'Error: {string reason}'].
     */
    public static function addUser(array $params): array
    {
        $username = trim($params['username'] ?? '');
        $password = $params['password'] ?? '';

        if ($username === '' || $password === '') {
            return ['status' => 400, 'data' => 'Error: Missing username or password'];
        }

        // Check for existing user
        $existing = Db::sqlGetRow('SELECT id FROM midmem_users WHERE username = ?', 's', $username);
        if ($existing) {
            return ['status' => 500, 'data' => 'Error: User already exists: ' . var_export($existing, true)];
        }

        // Hash password
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user with default access level
        $ok = Db::sqlExec(
            'INSERT INTO midmem_users (username, password_hash, access_level) VALUES (?, ?, ?)',
            'ssi',
            $username,
            $hash,
            UserAccess::USER->value
        );

        if (!$ok) {
            return ['status' => 500, 'data' => 'Error: Could not add user'];
        }

        return ['status' => 200, 'data' => 'OK'];
    }

    /**
     * Updates or inserts a user's password in the `.htpasswd` file.
     *
     * @param array $params ['username' => {string}, 'password' => {string}]
     * @return array ['status' => {int Http Status}, 'data' => 'OK' or 'Error: {string reason}'].
     */
    public static function changePassword(array $params): array
    {
        $username = trim($params['username'] ?? '');
        $password = $params['password'] ?? '';

        if ($username === '' || $password === '') {
            return ['status' => 400, 'data' => 'Error: Missing username or password'];
        }

        // Confirm user exists
        $user = Db::sqlGetRow('SELECT `id` FROM `midmem_users` WHERE `username` = ?', 's', $username);
        if (!$user) {
            return ['status' => 404, 'data' => 'Error: User not found'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $ok = Db::sqlExec(
            'UPDATE `midmem_users` SET `password_hash` = ? WHERE `username` = ?',
            'ss',
            $hash,
            $username
        );

        if (!$ok) {
            return ['status' => 500, 'data' => 'Error: Failed to update password'];
        }

        return ['status' => 200, 'data' => 'OK'];
    }

    /**
     * Delete/disable a user.
     * @param array $params ['username' => {string}]
     * @return array ['status' => {int Http Status}, 'data' => 'OK' or 'Error: {string reason}'].
     */
    public static function delete(array $params): array
    {
        $username = trim($params['username'] ?? '');

        if ($username === '') {
            return ['status' => 400, 'data' => 'Error: Missing username'];
        }

        // Check if user exists
        $user = Db::sqlGetRow('SELECT id FROM midmem_users WHERE username = ?', 's', $username);
        if (!$user) {
            return ['status' => 404, 'data' => 'Error: User not found'];
        }

        // Soft delete: set is_disabled = 1
        $ok = Db::sqlExec(
            'UPDATE midmem_users SET is_disabled = 1 WHERE username = ?',
            's',
            $username
        );

        if (!$ok) {
            return ['status' => 500, 'data' => 'Error: Failed to disable user'];
        }

        return ['status' => 200, 'data' => 'OK'];
    }

    /**
     * Get all users, including disabled ones.
     * @return array `['status' => 200, 'data' => [['username'=>"...", 'comment'=>"..." ]...]]`
     * On failure, `['status' => 500, 'data' => 'Error: ...']` or similar.
     */
    public static function getUsers(): array
    {
        $rows = Db::sqlGetTable(
            "SELECT
                username,
                IF(is_disabled = 1, 'DISABLED', '') AS comment
            FROM midmem_users
            ORDER BY username"
        );

        if (!$rows) {
            return ['status' => 500, 'data' => 'Error: Failed to fetch users'];
        }

        return ['status' => 200, 'data' => $rows];
    }

}
