<?php
declare(strict_types=1);

use MidwestMemories\Db;
use MidwestMemories\Enum\UserAccess;

/**
 * Helper to spin up the API server for testing.
 */
class TestHelper
{
    public const DISABLED_NAME = 'disabled_user';
    public const USER_NAME = 'test_user';
    public const ADMIN_NAME = 'test_admin';
    public const SUPERADMIN_NAME = 'test_superadmin';
    public const PASSWORD = 'test_pass';

    private static string $host = '127.0.0.1';
    private static int $port = 8081;
    private static string $docRoot = __DIR__ . '/..';
    private static string $entryFile = 'test/test-server.php';
    private static mixed $process = null;
    private static string $cookieJar;

    /**
     * @return void
     */
    public static function startServer(): void
    {
        if (self::$process !== null) {
            return;
        }

        self::$cookieJar = tempnam(sys_get_temp_dir(), 'cookie');

        $cmd = sprintf(
            'php -S %s:%d -t %s %s > /var/log/php_api_server.log 2>&1 & echo $!',
            self::$host,
            self::$port,
            escapeshellarg(self::$docRoot),
            escapeshellarg(self::$entryFile)
        );

        $output = [];
        exec($cmd, $output);
        $pid = (int)$output[0];
        self::$process = $pid;

        // Give server time to boot. Raise if tests fail.
        usleep(10_000);
    }

    /**
     * @return void
     */
    public static function stopServer(): void
    {
        if (self::$process) {
            exec('kill ' . self::$process);
            self::$process = null;
        }

        if (file_exists(self::$cookieJar)) {
            unlink(self::$cookieJar);
        }
    }

    /**
     * Logs in a user and throws an exception if the login fails.
     *
     * @param string $username
     * @param string $password
     * @return void
     * @throws RuntimeException if the login fails.
     */
    public static function loginAs(string $username, string $password): void
    {
        $response = self::request('POST', '/api/v1.0/login', [
            'username' => $username,
            'password' => $password
        ]);

        if (200 !== $response['status']) {
            throw new RuntimeException(
                sprintf(
                    "Login failed for user '%s' with status %d. Body: %s",
                    $username,
                    $response['status'],
                    $response['data']
                )
            );
        }

        $data = json_decode($response['data'], true);
        if ('OK' !== ($data['data'] ?? '')) {
            throw new RuntimeException(
                sprintf(
                    "Login API call returned 200, but body was not OK for user '%s'. Body: %s",
                    $username,
                    $response['data']
                )
            );
        }
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $params
     * @param array $headers
     * @return array
     */
    public static function request(string $method, string $uri, array $params = [], array $headers = []): array
    {
        /** @noinspection HttpUrlsUsage */
        $url = sprintf('http://%s:%d%s', self::$host, self::$port, $uri);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if (in_array(strtoupper($method), ['POST', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt(
            $ch, CURLOPT_HTTPHEADER, array_merge([
                'Content-Type: application/x-www-form-urlencoded'
            ], $headers)
        );

        curl_setopt($ch, CURLOPT_COOKIEFILE, self::$cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEJAR, self::$cookieJar);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $httpCode, 'data' => $body];
    }

    /**
     * Helper to recreate users before each test to ensure isolation.
     */
    public static function insertTestUsers(): void
    {
        $passwordHash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        self::removeTestUsers();
        Db::sqlExec(
            "
                INSERT INTO `midmem_users` (`username`, `password_hash`, `access_level`, `is_disabled`) VALUES 
                ('" . self::DISABLED_NAME . "', '$passwordHash', " . UserAccess::USER->value . ", 1),
                ('" . self::USER_NAME . "', '$passwordHash', " . UserAccess::USER->value . ", 0),
                ('" . self::ADMIN_NAME . "', '$passwordHash', " . UserAccess::ADMIN->value . ", 0),
                ('" . self::SUPERADMIN_NAME . "', '$passwordHash', " . UserAccess::SUPER_ADMIN->value . ', 0)
            '
        );
    }

    /**
     * Helper to clear users after each test to avoid DB clutter.
     */
    public static function removeTestUsers(): void
    {
        Db::sqlExec(
            "
                DELETE FROM `midmem_users`
                WHERE `username` IN (
                   '" . self::DISABLED_NAME . "', 
                   '" . self::USER_NAME . "', 
                   '" . self::ADMIN_NAME . "', 
                   '" . self::SUPERADMIN_NAME . "'
                )
            "
        );
    }
}
