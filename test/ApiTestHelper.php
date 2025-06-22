<?php
declare(strict_types=1);

/**
 * Helper to spin up the API server for testing.
 */
class ApiTestHelper
{
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

        // Give server time to boot
        usleep(500_000);
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
     * @param string $username
     * @param string $password
     * @return void
     */
    public static function loginAs(string $username, string $password): void
    {
        self::request('POST', '/api/v1.0/login', [
            'username' => $username,
            'password' => $password
        ]);
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
}
