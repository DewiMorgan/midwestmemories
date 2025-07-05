<?php
declare(strict_types=1);

namespace MidwestMemories\Api;

use MidwestMemories\CommentManager;
use MidwestMemories\DropboxManager;
use MidwestMemories\Enum\EndpointKey;
use MidwestMemories\Enum\EndpointPath;
use MidwestMemories\Enum\HttpMethod;
use MidwestMemories\Enum\ParamTypes;
use MidwestMemories\FileProcessor;
use MidwestMemories\Log;
use MidwestMemories\User;
use ValueError;

/**
 * API endpoint property management.
 */
class EndpointRegistry
{
    /**
     * Returns the route definition for a given HTTP method and path.
     *
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param string $path The endpoint path ("/user", "/comment", etc.)
     * @return array|null An array with keys: 'auth', 'params', 'rate_limit', 'callback'
     * @noinspection PhpMethodNamingConventionInspection "too short".
     * @throws ValueError
     */
    public static function get(string $method, string $path): ?array
    {
        try {
            $method = HttpMethod::from($method);
            $path = EndpointPath::from(trim($path, '/'));
            $key = EndpointKey::from(strtoupper($method->value) . '#' . $path->value);
        } catch (ValueError) {
            Log::warn("Couldn't match enums for $method $path");
            return null;
        }

        // Endpoint definitions keyed by ApiEndpoint enum.
        // Each value includes auth level, parameters, and the callback.
        // The callback returns an array ['status'=>HTTP status, 'data'=>payload].
        // Status defaults to 200, payload to empty.
        // Todo: some errors return in 'data', some in 'error'. Make consistent.
        return match ($key) {
            // Admin-only endpoints.
            EndpointKey::POST_CURSOR => [
                'auth' => 'admin',
                'params' => [],
                'callback' => DropboxManager::initRootCursor(...),
                'responseType' => 'object', // Returns an associative array with status, etc.
            ],
            EndpointKey::GET_CURSOR => [
                'auth' => 'admin',
                'params' => [],
                'callback' => DropboxManager::readCursorUpdate(...),
                'responseType' => 'object', // Returns an associative array with status, etc.
            ],
            EndpointKey::GET_DOWNLOAD => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::listNewFiles(...),
                'responseType' => 'array', // Returns a list of items.
            ],
            EndpointKey::POST_DOWNLOAD => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::downloadNextFile(...),
                'responseType' => 'object', // Returns an associative array with status, etc.
            ],
            EndpointKey::GET_PROCESS => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::listDownloadedFiles(...),
                'responseType' => 'array', // Returns a list of items.
            ],
            EndpointKey::POST_PROCESS => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::processNextFile(...),
                'responseType' => 'object', // Returns an associative array with status, etc.
            ],
            EndpointKey::GET_USER => [
                'auth' => 'admin',
                'params' => [],
                'callback' => User::getUsers(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => [0 => ['username' => '/^.+$/', 'comment' => '/^.*$/']]],
                    403 => ['success' => false, 'error' => 'Admin access required']
                ]
            ],
            EndpointKey::POST_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::addUser(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => 'OK'],
                    409 => ['success' => false, 'data' => '/^Error: Conflict\. .*/']
                ]
            ],
            EndpointKey::PUT_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::changePassword(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => 'OK']
                ]
            ],
            EndpointKey::DELETE_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING],
                'callback' => User::delete(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => 'OK']
                ]
            ],
            // Universally accessible endpoints.
            EndpointKey::POST_LOGIN => [
                'auth' => 'none',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::handleUserLogin(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => 'OK'],
                    403 => ['success' => false, 'data' => 'Error: access denied'],
                    409 => ['success' => false, 'data' => '/^Error: Conflict\. .*/']
                ],
            ],

            // User-accessible comment endpoints with rate limiting
            EndpointKey::GET_COMMENT => [
                'auth' => 'user',
                'params' => ['file_id' => ParamTypes::INT, 'page_id' => ParamTypes::INT],
                'rate_limit' => ['limit' => 30, 'window' => 60],
                'callback' => CommentManager::getComments(...),
                'responseType' => 'object', // Returns an associative array with status, etc.
            ],
            EndpointKey::POST_COMMENT => [
                'auth' => 'user',
                'params' => ['file_id' => ParamTypes::INT, 'comment_text' => ParamTypes::STRING],
                'rate_limit' => ['limit' => 20, 'window' => 60],
                'callback' => CommentManager::addComment(...),
                'responseType' => [
                    // TODO: Data here is wrong.
                    200 => ['success' => true, 'data' => []],
                    429 => ['success' => false, 'error' => 'Rate limit exceeded'],
                    500 => ['success' => false, 'error' => '/Server error: .+/']
                ]
            ],
            EndpointKey::PUT_COMMENT => [
                'auth' => 'user',
                'params' => ['comment_id' => ParamTypes::INT, 'new_comment_text' => ParamTypes::STRING],
                'rate_limit' => ['limit' => 20, 'window' => 60],
                'callback' => CommentManager::editComment(...),
                'responseType' => [
                    // ToDo: missing success case.
                    400 => ['success' => false, 'error' => 'Missing parameters: .+']
                ]
            ],
            EndpointKey::DELETE_COMMENT => [
                'auth' => 'user',
                'params' => ['comment_id' => ParamTypes::INT],
                'rate_limit' => ['limit' => 20, 'window' => 60],
                'callback' => CommentManager::deleteComment(...),
                'responseType' => [
                    // ToDo: missing success case.
                    400 => ['success' => false, 'error' => '/.+/']
                ]
            ]
        };
    }
}
