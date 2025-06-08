<?php
declare(strict_types=1);

namespace MidwestMemories\Api;

use MidwestMemories\DropboxManager;
use MidwestMemories\Enum\EndpointKey;
use MidwestMemories\Enum\EndpointPath;
use MidwestMemories\Enum\HttpMethod;
use MidwestMemories\Enum\ParamTypes;
use MidwestMemories\FileProcessor;
use MidwestMemories\UserManager;
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
        $method = HttpMethod::from($method);
        $path = EndpointPath::from(trim($path, '/'));
        $key = EndpointKey::from(strtoupper($method->value) . '#' . $path->value);

        // Endpoint definitions keyed by ApiEndpoint enum.
        // Each value includes auth level, parameters, and the callback.
        return match ($key) {
            // Admin-only endpoints
            EndpointKey::POST_CURSOR => [
                'auth' => 'admin',
                'params' => [],
                'callback' => DropboxManager::initRootCursor(...),
            ],
            EndpointKey::GET_CURSOR => [
                'auth' => 'admin',
                'params' => [],
                'callback' => DropboxManager::readCursorUpdate(...),
            ],
            EndpointKey::GET_DOWNLOAD => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::listNewFiles(...),
            ],
            EndpointKey::POST_DOWNLOAD => [
                'auth' => 'admin',
                'params' => ['file_id' => ParamTypes::INT],
                'callback' => FileProcessor::downloadOneFile(...),
            ],
            EndpointKey::GET_PROCESS => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::listDownloadedFiles(...),
            ],
            EndpointKey::POST_PROCESS => [
                'auth' => 'admin',
                'params' => ['file_id' => ParamTypes::INT],
                'callback' => FileProcessor::processOneFile(...),
            ],
            EndpointKey::GET_USER => [
                'auth' => 'admin',
                'params' => [],
                'callback' => UserManager::getUsers(...),
            ],
            EndpointKey::POST_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => UserManager::addUser(...),
            ],
            EndpointKey::PUT_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => UserManager::changePassword(...),
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
