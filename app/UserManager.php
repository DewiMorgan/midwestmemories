<?php
declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

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
     * @param string $username
     * @param string $password
     * @return array ['status'=>Http status, 'data'=>'OK' or 'Error: {reason}'].
     */
    public static function changePassword(string $username, string $password): array
    {
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
            return ['data' => 'OK'];
        }
        return ['status' => 500, 'data' => 'Error: could not save new password'];
    }

    /**
     * Delete/disable a user.
     * @param string $username Who to delete
     * @return array ['status'=>Http status, 'data'=>'OK' or 'Error: {reason}'].
     */
    public static function delete(string $username): array
    {
        return self::changePassword($username, '');
    }

    /**
     * Adds a new user to the `.htpasswd` file.
     * Returns false if the user already exists, or an error occurs.
     *
     * @param string $username
     * @param string $password
     * @return array ['status' => Http Status, 'data' => 'OK' or 'Error: {reason}'].
     */
    public static function addUser(string $username, string $password): array
    {
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
            return ['status' => 200, 'data' => 'OK'];
        }
        return ['status' => 500, 'data' => 'Error: could not save user']; // User already exists
    }

    /**
     * @return array[]
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
            return ['data' => $data];
        }
    }
}
