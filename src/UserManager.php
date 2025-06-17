<?php
declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;
use MidwestMemories\Enum\UserAccess;

/**
 * Manager for user accounts.
 */
class UserManager extends Singleton
{
    private string $passwdFile;

    /** @var array[] $users List of ['username' => string, 'comment' => string|null] entries. */
    private array $users = [];
    /** @var string[] $lines */
    private array $lines = [];

    /**
     * Protected singleton constructor.
     */
    protected function __construct()
    {
        parent::__construct();
        $this->passwdFile = Conf::get(Key::PASSWORD_FILE);
        $this->readPasswdFile();
        $this->readUsers();
    }

    /**
     * Populate the lines from the file.
     */
    private function readPasswdFile(): void
    {
        if (
            file_exists($this->passwdFile)
            && is_readable($this->passwdFile)
            && is_writable($this->passwdFile)
        ) {
            $this->lines = file($this->passwdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
    }

    /**
     * @return bool Success.
     */
    private function putPasswdFile(): bool
    {
        return (bool)file_put_contents($this->passwdFile, implode(PHP_EOL, $this->lines) . PHP_EOL);
    }

    /**
     * @param string $line The line to append to the file.
     * @return bool Success.
     */
    private function appendToPasswdFile(string $line): bool
    {
        $this->lines[] = $line;
        return (bool)file_put_contents($this->passwdFile, $line . "\n", FILE_APPEND);
    }

    /**
     * Reads `.htpasswd` file and populates our array of usernames/passwords.
     */
    private function readUsers(): void
    {
        $users = [];
        $knownPassword = '?';

        foreach ($this->lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '#')) {
                // Save this comment to associate with the next user
                $knownPassword = ltrim(substr($line, 1)); // Remove leading hash, and trim.
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $users[] = [
                    'username' => $parts[0],
                    'comment' => $knownPassword,
                ];
                $knownPassword = '?'; // Reset after use
            }
        }
        $this->users = $users;
    }

    /**
     * Updates or inserts a user's password in the `.htpasswd` file.
     *
     * @param array $params ['username' => {string}, 'password' => {string}]
     * @return array ['status' => {int Http Status}, 'data' => 'OK' or 'Error: {string reason}'].
     */
    public static function changePassword(array $params): array
    {
        $username = $params['username'] ?? '';
        $password = $params['password'] ?? '';

        $instance = self::getInstance();
        $numToReplace = 1;
        $prevLine = '';
        $indexToReplace = null;
        foreach ($instance->lines as $i => $line) {
            if (str_starts_with(trim($line), "$username:")) {
                $indexToReplace = $i;
                // Check if the previous line is a comment.
                if ($prevLine && '#' === $prevLine[0]) {
                    $indexToReplace = $i - 1;
                    $numToReplace = 2;
                }
                break;
            }
            $prevLine = $line;
        }

        // Fail if not found.
        if (is_null($indexToReplace)) {
            return ['status' => 500, 'data' => 'Error: could not find user to update'];
        }

        if ('' === $password) {
            $newEntries = [
                '# DISABLED',
                "$username:DISABLED"
            ];
        } else {
            $newEntries = [
                "# $password",
                "$username:" . password_hash($password, PASSWORD_BCRYPT)
            ];
        }
        array_splice($instance->lines, $indexToReplace, $numToReplace, $newEntries);
        if ($instance->putPasswdFile()) {
            return ['status' => 200, 'data' => 'OK'];
        }
        return ['status' => 500, 'data' => 'Error: could not save new password'];
    }

    /**
     * Delete/disable a user.
     * @param array $params ['username' => {string}]
     * @return array ['status' => {int Http Status}, 'data' => 'OK' or 'Error: {string reason}'].
     */
    public static function deleteNew(array $params): array
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
     * Delete/disable a user.
     * @param array $params ['username' => {string}]
     * @return array ['status' => {int Http Status}, 'data' => 'OK' or 'Error: {string reason}'].
     */
    public static function delete(array $params): array
    {
        $params['password'] = '';
        return self::changePassword($params);
    }

    /**
     * Adds a new user to the users table.
     * Sets status 500 if the user already exists, or an error occurs.
     * @param array $params ['username' => {string}, 'password' => {string}]
     * @return array ['status' => {int Http Status}, 'data' => 'OK' or 'Error: {string reason}'].
     */
    public static function addUserNew(array $params): array
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
     * Adds a new user to the `.htpasswd` file.
     * Sets status 500 if the user already exists, or an error occurs.
     * @param array $params ['username' => {string}, 'password' => {string}]
     * @return array ['status' => {int Http Status}, 'data' => 'OK' or 'Error: {string reason}'].
     */
    public static function addUser(array $params): array
    {
        $username = $params['username'] ?? '';
        $password = $params['password'] ?? '';

        if ('' === $username) {
            return ['status' => 400, 'data' => 'Error: empty username'];
        }
        if ('' === $password) {
            return ['status' => 400, 'data' => 'Error: empty password'];
        }

        $instance = self::getInstance();
        Log::debug('users', $instance->users); // DELETEME DEBUG
        // Check if user already exists
        foreach ($instance->users as $user) {
            if ($username === $user['username']) {
                return ['status' => 500, 'data' => 'Error: user already exists']; // User already exists
            }
        }

        if (
            $instance->appendToPasswdFile("# $password")
            && $instance->appendToPasswdFile("$username:" . password_hash($password, PASSWORD_BCRYPT))
        ) {
            $instance->users[] = ['username' => $username, 'password' => $password];
            return ['status' => 200, 'data' => 'OK'];
        }
        return ['status' => 500, 'data' => 'Error: could not save user']; // User already exists
    }

    /**
     * @return array ['status' => 200, 'data' => [['username'=>"...", 'comment'=>"..." ]...]];
     */
    public static function getUsers(): array
    {
        Log::debug('Getting users');
        $instance = self::getInstance();
        if (Connection::getInstance()->isSuperAdmin) {
            Log::debug('Branch 1', $instance->users);
            return $instance->users;
        } else {
            Log::debug('Branch 2', $instance->users);
            $data = array_map(function ($item) {
                return [
                    'username' => $item['username'],
                    'comment' => ('DISABLED' === $item['comment']) ? 'DISABLED' : ''
                ];
            }, $instance->users);
            return ['status' => 200, 'data' => $data];
        }
    }
}
