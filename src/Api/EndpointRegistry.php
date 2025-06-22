<?php
declare(strict_types=1);

namespace MidwestMemories\Api;

use MidwestMemories\DropboxManager;
use MidwestMemories\Enum\EndpointKey;
use MidwestMemories\Enum\EndpointPath;
use MidwestMemories\Enum\HttpMethod;
use MidwestMemories\Enum\ParamTypes;
use MidwestMemories\FileProcessor;
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
file_put_contents('/tmp/deleteme', date('Y-m-d H:i:s') . __FILE__ . __LINE__ . "== $method\n", FILE_APPEND); // DELETEME DEBUG
            $method = HttpMethod::from($method);
file_put_contents('/tmp/deleteme', date('Y-m-d H:i:s') . __FILE__ . __LINE__ . "== $method->value $path\n", FILE_APPEND); // DELETEME DEBUG
            $path = EndpointPath::from(trim($path, '/'));
file_put_contents('/tmp/deleteme', date('Y-m-d H:i:s') . __FILE__ . __LINE__ . "== $method->value $path->value\n", FILE_APPEND); // DELETEME DEBUG
            $key = EndpointKey::from(strtoupper($method->value) . '#' . $path->value);
file_put_contents('/tmp/deleteme', date('Y-m-d H:i:s') . __FILE__ . __LINE__ . "== $method->value $path->value $key->value\n", FILE_APPEND); // DELETEME DEBUG
        } catch (ValueError $e) {
file_put_contents('/tmp/deleteme', date('Y-m-d H:i:s') . __FILE__ . __LINE__ . "\n", FILE_APPEND); // DELETEME DEBUG
            return null;
        }

        // Endpoint definitions keyed by ApiEndpoint enum.
        // Each value includes auth level, parameters, and the callback.
        // The callback returns an array ['status'=>HTTP status, 'data'=>payload].
        // Status defaults to 200, payload to empty.
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
                'responseType' => 'array', // Returns a list of items.
            ],
            EndpointKey::POST_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::addUser(...),
                'responseType' => 'string', // Returns a string 'OK' or 'Error: ...'.
            ],
            EndpointKey::PUT_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::changePassword(...),
                'responseType' => 'string', // Returns a string 'OK' or 'Error: ...'.
            ],
            EndpointKey::DELETE_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING],
                'callback' => User::delete(...),
                'responseType' => 'string', // Returns a string 'OK' or 'Error: ...'.
            ],
            EndpointKey::POST_LOGIN => [
                'auth' => 'none',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::handleUserLogin(...),
                'responseType' => 'object', // Returns a string 'OK' or 'Error: ...'.
            ],

            // User-accessible comment endpoints with rate limiting
//            EndpointKey::GET_COMMENT => [
//                'auth' => 'user',
//                'params' => ['image_id' => ParamTypes::INT],
//                'rate_limit' => ['limit' => 30, 'window' => 60],
//                'callback' => CommentManager::getComments(...),
//            ],
//            EndpointKey::POST_COMMENT => [
//                'auth' => 'user',
//                'params' => ['image_id' => ParamTypes::INT, 'comment_text' => ParamTypes::STRING],
//                'rate_limit' => ['limit' => 20, 'window' => 60],
//                'callback' => CommentManager::addComment(...),
//            ],
//            EndpointKey::PUT_COMMENT => [
//                'auth' => 'user',
//                'params' => ['comment_id' => ParamTypes::INT, 'new_comment_text' => ParamTypes::STRING],
//                'rate_limit' => ['limit' => 20, 'window' => 60],
//                'callback' => CommentManager::editComment(...),
//            ],
//            EndpointKey::DELETE_COMMENT => [
//                'auth' => 'user',
//                'params' => ['comment_id' => ParamTypes::INT],
//                'rate_limit' => ['limit' => 20, 'window' => 60],
//                'callback' => CommentManager::deleteComment(...),
//            ],

            default => null,
        };
    }
}
