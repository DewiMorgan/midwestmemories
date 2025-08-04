<?php

declare(strict_types=1);

namespace MidwestMemories\Api;

use DateInterval;
use DateTime;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use MidwestMemories\Db;
use MidwestMemories\Enum\ParamTypes;
use MidwestMemories\Log;
use MidwestMemories\Path;
use MidwestMemories\Table;
use MidwestMemories\User;
use ValueError;

/**
 * Handle all JSON API calls.
 * REST-ful, CRUD, and other buzzwords apply here.
 */
class ApiGateway
{
    private string $method;
    private string $path;
    private mixed $apiVersion;
    /**
     * @var string
     */
    private string $pathParams;

    public function __construct()
    {
//Log::debug('Starting up API gateway', $_REQUEST); // DELETEME DEBUG
        // Get method and request path.
        $this->method = $_SERVER['REQUEST_METHOD'];
        // API version gets put into the GET params by .htaccess mod_rewrite.
        $this->apiVersion = $_GET['apiversion'] ?? 'v0.0';
        if ('v1.0' !== $this->apiVersion) {
            Log::error('Unsupported API version', $this->apiVersion);
            $this->jsonResponse(400, "Unsupported API version: $this->apiVersion");
        }
        $this->path = $_GET['path'] ?? '';
        $parts = preg_split('#[?/]+#', $this->path, 2, PREG_SPLIT_NO_EMPTY);
        $this->path = $parts[0] ?? '';
        $this->pathParams = $parts[1] ?? '';
        if (!preg_match('/^\w+$/', $this->path)) {
            Log::error('Path not found', $this->path);
            $this->jsonResponse(404, "Path not found: $this->path");
        }
    }

    /**
     * Called to handle an API call. Having as a public method may be unnecessary - could be called from constructor.
     * All callbacks must return an array with the keys 'status' (int HTTP status code) and 'data' (array or string).
     * @return void
     */
    public function handleApiCall(): void
    {
        // Make sure we know what our paths are.
        Path::validateBaseDir();
        try {
            $endpointDef = $this->getEndpointDefinition();
            $this->authorize($endpointDef);
            $this->rateLimit($endpointDef);

            // Get our parameters from every possible source. Later sources overwrite earlier ones.
            $params = array_merge(
                $this->getPathParams($endpointDef),
                $_GET,
                $this->getJsonParams()
            );
            $this->validateRequiredParams($endpointDef, $params);

            /** @var Callable $callback */
            $callback = $endpointDef['callback'];

            Log::info("Calling callback for $this->method /$this->path");
            $result = call_user_func($callback, $params);
            Log::debug('Result', $result);

            $this->jsonResponse($result['status'] ?? 200, $result['data'] ?? []);
        } catch (Exception $e) {
            Log::error("API Exception: {$e->getMessage()}");
            $this->jsonResponse(500, 'Server error: ' . $e->getMessage());
        }
    }

    /**
     * Get the properties of this endpoint.
     * @return array
     */
    private function getEndpointDefinition(): array
    {
        try {
            return EndpointRegistry::get($this->method, $this->path);
        } catch (ValueError) {
            Log::warn("No match for $this->method $this->path");
            Log::debug('Request', $_REQUEST);
            Log::debug('Get', $_GET);
            Log::debug('URI', $_SERVER['REQUEST_URI']);
            Log::debug('Server', $_SERVER);

            $this->jsonResponse(404, 'Endpoint not found');
        }
    }

    /**
     * Verify that the caller has permission to call this endpoint.
     * @param array $endpointDef
     * @return void
     */
    private function authorize(array $endpointDef): void
    {
        $auth = $endpointDef['auth'] ?? null;

        $user = User::getInstance();
        if ($auth === 'admin' && !$user->isAdmin) {
            Log::warn("Forbidden: admin required for $this->method $this->path");
            $this->jsonResponse(403, 'Admin access required');
        }

        // Anyone using the API should be at least an authenticated user.
        if ($auth === 'user' && !$user->isUser) {
            Log::warn("Forbidden: login required for $this->method $this->path");
            $this->jsonResponse(403, 'User access required');
        }
    }

    /**
     * Enforce rate limiting for a user on an endpoint.
     * @param array $endpoint The endpoint to enforce rate limiting on.
     * @return void
     */
    private function rateLimit(array $endpoint): void
    {
        if (isset($endpoint['rate_limit'])) {
            $key = $this->rateLimitKey(); // unique to user and endpoint
            $limit = $endpoint['rate_limit']['limit'];
            $window = $endpoint['rate_limit']['window'];

            if (!$this->checkRateLimit($key, $limit, $window)) {
                Log::warn("Rate limit exceeded for key: $key");
                $this->jsonResponse(429, 'Rate limit exceeded');
            }
        }
    }

    /**
     * Extracts path parameters after the base route and assigns names if defined in the endpoint.
     * @param array $endpoint The endpoint metadata with optional 'params' list.
     * @return array A merged array of indexed and named path parameters.
     */
    private function getPathParams(array $endpoint): array
    {
        // Example: 'fred/abc/unnamed' -> ['user', 'fred', 'abc', 'unnamed'].
        $params = array_values(array_filter(explode('/', $this->pathParams)));

        // Append optionally named entries. So from $endpoint['params'] = ['username', 'password']
        // [0=>'fred', 1=>'abc', 2=>'unnamed'] => [0=>'fred', 1=>'abc', 2=>'banana', 'user'=>'fred', 'pass'=>'abc']
        if (array_key_exists('params', $endpoint)) {
            $paramNames = array_keys($endpoint['params']);
            $numParams = count($params); // Count before we append named keys.
            for ($i = 0; $i < $numParams; $i++) {
                // Append named key if it exists in the endpoint 'params'.
                $name = $paramNames[$i] ?? null;
                if ($name) {
                    $params[$name] = $params[$i];
                }
            }
        }
        // Log::debug('= Read', $params); // DELETEME DEBUG

        return $params;
    }

    /**
     * Get JSON params from the body text
     * @return array
     */
    private function getJsonParams(): array
    {
        //Log::debug("Reading JSON params for `$this->path`"); // DELETEME DEBUG
        if (!in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            Log::warn('API method unknown: not POST/PUT/PATCH/DELETE.'); // DELETEME DEBUG
            return [];
        }

        $raw = file_get_contents('php://input');
        //Log::debug('= Raw params', $raw);// DELETEME DEBUG
        $decoded = json_decode($raw, true);
        //Log::debug('= Decoded params', $decoded);// DELETEME DEBUG

        // If it isn't JSON, try to parse it as a query string.
        if (json_last_error() !== JSON_ERROR_NONE) {
            parse_str($raw, $decoded);
        }

        if (!is_array($decoded)) {
            Log::warn('Expected JSON or encoded object, got something else.');
            $this->jsonResponse(400, 'Expected JSON object.');
        }
        //Log::debug('= Read', $decoded); // DELETEME DEBUG
        return $decoded;
    }

    /**
     * Validates required parameters and their types.
     *
     * @param array $endpoint The endpoint metadata with required params and types.
     * @param array $params The actual params passed by the user.
     * @return void
     */
    private function validateRequiredParams(array $endpoint, array $params): void
    {
        if (empty($endpoint['params'])) {
            return;
        }

        $missing = [];
        $invalid = [];

        foreach ($endpoint['params'] as $name => $expectedType) {
            if (!array_key_exists($name, $params)) {
                $missing[] = $name;
                continue;
            }

            $value = $params[$name];

            // Type checking
            $valid = match ($expectedType) {
                ParamTypes::INT => filter_var($value, FILTER_VALIDATE_INT),
                ParamTypes::FLOAT => filter_var($value, FILTER_VALIDATE_FLOAT),
                ParamTypes::BOOL => is_bool($value) || filter_var($value, FILTER_VALIDATE_BOOL),
                ParamTypes::STRING => is_string($value),
                ParamTypes::EMAIL => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL),
                ParamTypes::IP => is_string($value) && filter_var($value, FILTER_VALIDATE_IP),
                ParamTypes::URL => is_string($value) && filter_var($value, FILTER_VALIDATE_URL),
            };

            if (false === $valid) {
                $actual = gettype($value);
                $invalid[] = "$name (expected $expectedType->value, got $actual)";
            }
        }

        $message = '';
        if ($missing) {
            $message = 'Missing parameters: ' . implode(', ', $missing) . '. ';
            Log::warn($message, $params);
        }

        if ($invalid) {
            $message .= 'Invalid parameter types: ' . implode(', ', $invalid) . '. ';
        }

        if ($message) {
            $this->jsonResponse(400, $message);
        }
    }

    /**
     * Exit, after outputting an API call's JSON response.
     * The output will be one of:
     * `{'success': true, 'error': ...}` if the statusCode is an HTTP error.
     * `{'success': false, 'data': ...}` if the statusCode is an HTTP success.
     * The contents of `error` or `data` are defined by the caller.
     *
     * @param int $statusCode
     * @param array|string $body
     */
    #[NoReturn] private function jsonResponse(int $statusCode, array|string $body): void
    {
        http_response_code($statusCode);
        if ($statusCode < 400) {
            $output = json_encode(['success' => true, 'data' => $body]);
        } else {
            $output = json_encode(['success' => false, 'error' => $body]);
        }
        echo $output;
        exit;
    }

    /**
     * A unique key for rate limiting a user on an endpoint.
     * @return string The generated key.
     */
    private function rateLimitKey(): string
    {
        $user = User::getInstance();
        return $user->username . ":$this->path";
    }

    /**
     * Check and update rate limit for a user + endpoint string.
     */
    private function checkRateLimit(string $key, int $limit, int $window): bool
    {
        $userId = User::getInstance()->userId;
        $now = new DateTime('now');
        $windowInterval = new DateInterval("PT{$window}S"); // e.g., PT60S = 60 seconds

        // Try to fetch the current rate limit entry
        $row = Db::sqlGetRow(
            'SELECT * FROM `' . Table::rate_limits() . '` WHERE user_id = ? AND rate_key = ?',
            'ds', $userId, $key
        );

        if ($row) {
            try {
                $windowStart = new DateTime($row['window_start']);
            } catch (Exception $e) {
                Log::error("Invalid datetime in rate_limits for user $userId, key $key: " . $e->getMessage());

                // Reset the rate limit to recover automatically, since this is our error.
                Db::sqlExec(
                    'UPDATE `' . Table::rate_limits() .
                    '` SET `window_start` = ?, `request_count` = 1 WHERE `user_id` = ? AND `rate_key` = ?',
                    'sds', $now->format('Y-m-d H:i:s'), $userId, $key
                );

                return true; // Gracefully allow the request and reset the limit
            }

            $requestCount = (int)$row['request_count'];

            if ($now >= (clone $windowStart)->add($windowInterval)) {
                // Reset window and count
                Db::sqlExec(
                    'UPDATE `' . Table::rate_limits() .
                    '` SET `window_start` = ?, `request_count` = 1 WHERE `user_id` = ? AND `rate_key` = ?',
                    'sds', $now->format('Y-m-d H:i:s'), $userId, $key
                );
                return true;
            }

            if ($requestCount < $limit) {
                Db::sqlExec(
                    'UPDATE `' . Table::rate_limits() .
                    '` SET `request_count` = `request_count` + 1 WHERE `user_id` = ? AND `rate_key` = ?',
                    'ds', $userId, $key
                );
                return true;
            }

            // Rate limit exceeded
            return false;
        } else {
            // First-time entry: insert new row
            Db::sqlExec(
                'INSERT INTO `' . Table::rate_limits() .
                '` (`request_count`, `user_id`, `rate_key`, `window_start`)
                    VALUES (1, ?, ?, ?)',
                'dss', $userId, $key, $now->format('Y-m-d H:i:s')
            );
            return true;
        }
    }
}
