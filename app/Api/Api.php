<?php

declare(strict_types=1);

namespace MidwestMemories\Api;

use DateInterval;
use DateTime;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use MidwestMemories\Connection;
use MidwestMemories\Db;
use MidwestMemories\Enum\ParamTypes;
use MidwestMemories\Log;
use ValueError;

/**
 * Handle all JSON API calls.
 * REST-ful, CRUD, and other buzzwords apply here.
 * @noinspection PhpClassNamingConventionInspection
 */
class Api
{
    private string $method;
    private string $path;

    public function __construct()
    {
        // Get method and request path.
        $this->method = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $baseUri = dirname($_SERVER['SCRIPT_NAME']);
        $this->path = '/' . trim(str_replace($baseUri, '', $requestUri), '/');
    }

    /**
     * Called to handle an API call. Having as a public method may be unnecessary - could be called from constructor.
     * @return void
     */
    public function handleApiCall(): void
    {
        try {
            $endpointDef = $this->getEndpointDefinition();

            $this->authorize($endpointDef);
            $this->rateLimit($endpointDef);

//            $input = $this->getJsonInput();
//            $routeParams = $match['params'];
//            $params = array_merge($routeParams, $input);
            $params = $this->getJsonParams();
            $this->validateRequiredParams($endpointDef, $params);

            $callback = $endpointDef['callback'];

            Log::info("Calling $callback for $this->method $this->path");
            $result = call_user_func($callback, $params);

            $this->jsonResponse($result['status'] ?? 200, ['data' => $result['data'] ?? null]);
        } catch (Exception $e) {
            Log::error("API Exception: {$e->getMessage()}");
            $this->jsonResponse(500, ['error' => 'Server error: ' . $e->getMessage()]);
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
            Log::debug('URI', $_SERVER['REQUEST_URI']);

            $this->jsonResponse(404, ['error' => 'Endpoint not found']);
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

        if ($auth === 'admin' && !Connection::getInstance()->isAdmin) {
            Log::warn("Forbidden: admin required for $this->method $this->path");
            $this->jsonResponse(403, ['error' => 'Admin access required']);
        }

        // Anyone using the API should be at least an authenticated user.
        if ($auth === 'user' && !Connection::getInstance()->isUser) {
            Log::warn("Forbidden: login required for $this->method $this->path");
            $this->jsonResponse(403, ['error' => 'User access required']);
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
                $this->jsonResponse(429, ['error' => 'Rate limit exceeded']);
            }
        }
    }

    /**
     * @return array
     */
    private function getJsonParams(): array
    {
        if (!in_array($this->method, ['POST', 'PUT', 'PATCH'])) {
            return [];
        }

        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warn('Invalid JSON input: ' . json_last_error_msg());
            $this->jsonResponse(400, ['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        }

        if (!is_array($decoded)) {
            Log::warn('Expected JSON object, got something else.');
            $this->jsonResponse(400, ['error' => 'Expected JSON object.']);
        }

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

            if (!$valid) {
                $actual = gettype($value);
                $invalid[] = "$name (expected $expectedType->value, got $actual)";
            }
        }

        if ($missing) {
            Log::warn('Missing parameters: ' . implode(', ', $missing));
            $this->jsonResponse(400, ['error' => 'Missing parameters: ' . implode(', ', $missing)]);
        }

        if ($invalid) {
            Log::warn('Invalid parameter types: ' . implode(', ', $invalid));
            $this->jsonResponse(400, ['error' => 'Invalid parameter types: ' . implode(', ', $invalid)]);
        }
    }

    /**
     * @param int $statusCode
     * @param array $body
     * @return void
     */
    #[NoReturn] private function jsonResponse(int $statusCode, array $body): void
    {
        http_response_code($statusCode);
        echo json_encode(['success' => $statusCode < 400] + $body);
        exit;
    }

    /**
     * A unique key for rate limiting a user on an endpoint.
     * @return string The generated key.
     */
    private function rateLimitKey(): string
    {
        return Connection::getInstance()->username . ":$this->path";
    }

    /**
     * Check and update rate limit for a user + endpoint string.
     */
    private function checkRateLimit(string $key, int $limit, int $window): bool
    {
        $userId = Connection::getInstance()->userId;
        $now = new DateTime('now');
        $windowInterval = new DateInterval("PT{$window}S"); // e.g., PT60S = 60 seconds

        // Try to fetch the current rate limit entry
        $row = Db::sqlGetRow(
            'SELECT * FROM `' . Db::TABLE_RATE_LIMIT . '` WHERE user_id = ? AND rate_key = ?',
            'ds', $userId, $key
        );

        if ($row) {
            try {
                $windowStart = new DateTime($row['window_start']);
            } catch (Exception $e) {
                Log::error("Invalid datetime in rate_limits for user $userId, key $key: " . $e->getMessage());

                // Reset the rate limit to recover automatically, since this is our error.
                Db::sqlExec(
                    'UPDATE ' . Db::TABLE_RATE_LIMIT .
                    ' SET window_start = ?, request_count = 1 WHERE user_id = ? AND rate_key = ?',
                    'sds', $now->format('Y-m-d H:i:s'), $userId, $key
                );

                return true; // Gracefully allow the request and reset the limit
            }

            $requestCount = (int)$row['request_count'];

            if ($now >= (clone $windowStart)->add($windowInterval)) {
                // Reset window and count
                Db::sqlExec(
                    'UPDATE ' . Db::TABLE_RATE_LIMIT .
                    ' SET window_start = ?, request_count = 1 WHERE user_id = ? AND rate_key = ?',
                    'sds', $now->format('Y-m-d H:i:s'), $userId, $key
                );
                return true;
            }

            if ($requestCount < $limit) {
                Db::sqlExec(
                    'UPDATE ' . Db::TABLE_RATE_LIMIT .
                    ' SET request_count = request_count + 1 WHERE user_id = ? AND rate_key = ?',
                    'ds', $userId, $key
                );
                return true;
            }

            // Rate limit exceeded
            return false;
        } else {
            // First-time entry: insert new row
            Db::sqlExec(
                'INSERT INTO ' . Db::TABLE_RATE_LIMIT .
                ' (`request_count`, `user_id`, `rate_key`, `window_start`)
                    VALUES (1, ?, ?, ?)',
                'dss', $userId, $key, $now->format('Y-m-d H:i:s')
            );
            return true;
        }
    }
}
