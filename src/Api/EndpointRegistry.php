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
            $key = EndpointKey::from(strtoupper($method) . '#' . trim($path, '/'));
        } catch (ValueError) {
            Log::warn("Couldn't match enums for $method $path");
            return null;
        }

        // Endpoint definitions keyed by ApiEndpoint enum.
        // Each value includes auth level, parameters, list of possible response structures, and the callback.
        // The callback returns an array `['status'=>int HTTP status, 'data'=>string or array payload]`.
        // Status defaults to 200, and the data payload to empty.
        // This will be output by `ApiGateway::jsonResponse` as Json, either:
        //  `{'success':true,'data': <payload>}` or `{'success':false,'error': <payload>}`
        return match ($key) {
            // Universal default API error responses.
            EndpointKey::ANY_ERROR => [
                'auth' => 'none',
                'params' => [],
                'callback' => self::apiError(...),
                'responseType' => [
                    400 => ['success' => false, 'error' => ParamTypes::STRING], // Bad request (API version, etc).
                    403 => ['success' => false, 'error' => ParamTypes::STRING], // Bad access level.
                    404 => ['success' => false, 'error' => ParamTypes::STRING], // Bad API path.
                    429 => ['success' => false, 'error' => ParamTypes::STRING], // Rate limit exceeded.
                    500 => ['success' => false, 'error' => ParamTypes::STRING], // Internal Server error.
                ],
            ],
            // Admin-only endpoints.
            EndpointKey::POST_CURSOR => [
                'auth' => 'admin',
                'params' => [],
                'callback' => DropboxManager::initRootCursor(...),
                'responseType' => [
                    200 => [ // From handleFileList
                        'success' => true,
                        'data' => [
                            'numAddedFiles' => ParamTypes::INT,
                            'numTotalFiles' => ParamTypes::INT,
                            'moreFilesToGo' => ParamTypes::BOOL
                        ]
                    ],
                ],
            ],
            EndpointKey::GET_CURSOR => [
                'auth' => 'admin',
                'params' => [],
                'callback' => DropboxManager::readCursorUpdate(...),
                'responseType' => [
                    200 => [ // From handleFileList
                        'success' => true,
                        'data' => [
                            'numAddedFiles' => ParamTypes::INT,
                            'numTotalFiles' => ParamTypes::INT,
                            'moreFilesToGo' => ParamTypes::BOOL
                        ]
                    ],
                ],
            ],
            EndpointKey::GET_DOWNLOAD => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::listNewFiles(...),
                'responseType' => [
                    // Array of string file paths, or empty.
                    200 => ['success' => true, 'data' => ParamTypes::STRING_ARRAY],
                ]
            ],
            EndpointKey::POST_DOWNLOAD => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::downloadNextFile(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK],
                ]
            ],
            EndpointKey::GET_PROCESS => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::listDownloadedFiles(...),
                'responseType' => [
                    // Array of string file paths, or empty.
                    200 => ['success' => true, 'data' => ParamTypes::STRING_ARRAY],
                ]
            ],
            EndpointKey::POST_PROCESS => [
                'auth' => 'admin',
                'params' => [],
                'callback' => FileProcessor::processNextFile(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK],
                ]
            ],
            EndpointKey::GET_USER => [
                'auth' => 'admin',
                'params' => [],
                'callback' => User::getUsers(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => [0 => [
                        'username' => ParamTypes::STRING,
                        'comment' => ParamTypes::STRING
                    ]]],
                ]
            ],
            EndpointKey::POST_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::addUser(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK],
                    409 => ['success' => false, 'data' => '/^Error: Conflict\. .*/'],
                    422 => ['success' => false, 'data' => '/^Error: Unprocessable content\. .*/'],
                ]
            ],
            EndpointKey::PUT_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::changePassword(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK]
                ]
            ],
            EndpointKey::DELETE_USER => [
                'auth' => 'admin',
                'params' => ['username' => ParamTypes::STRING],
                'callback' => User::delete(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK]
                ]
            ],
            // Universally accessible endpoints.
            EndpointKey::POST_LOGIN => [
                'auth' => 'none',
                'params' => ['username' => ParamTypes::STRING, 'password' => ParamTypes::STRING],
                'callback' => User::handleUserLogin(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK],
                    409 => ['success' => false, 'data' => '/^Error: Conflict\. .*/']
                ],
            ],

            // User-accessible comment endpoints with rate limiting
            EndpointKey::GET_COMMENT => [
                'auth' => 'user',
                'params' => ['file_id' => ParamTypes::INT, 'page_id' => ParamTypes::INT],
                'rate_limit' => ['limit' => 30, 'window' => 60],
                'callback' => CommentManager::getComments(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => [0 => [
                        'id' => ParamTypes::INT,
                        'date_created' => ParamTypes::DATE,
                        'user' => ParamTypes::INT,
                        'comment_text' => ParamTypes::STRING,
                        'num_pages' => ParamTypes::INT,
                    ]]],
                ]
            ],
            EndpointKey::POST_COMMENT => [
                'auth' => 'user',
                'params' => ['file_id' => ParamTypes::INT, 'comment_text' => ParamTypes::STRING],
                'rate_limit' => ['limit' => 20, 'window' => 60],
                'callback' => CommentManager::addComment(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => [ // Returns the posted comment.
                        'id' => ParamTypes::INT,
                        'date_created' => ParamTypes::DATE,
                        'user' => ParamTypes::INT,
                        'comment_text' => ParamTypes::STRING,
                        'num_pages' => ParamTypes::INT,
                    ]],
                ]
            ],
            EndpointKey::PUT_COMMENT => [
                'auth' => 'user',
                'params' => ['id' => ParamTypes::INT, 'new_comment_text' => ParamTypes::STRING],
                'rate_limit' => ['limit' => 20, 'window' => 60],
                'callback' => CommentManager::editComment(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK],
                ]
            ],
            EndpointKey::DELETE_COMMENT => [
                'auth' => 'user',
                'params' => ['id' => ParamTypes::INT],
                'rate_limit' => ['limit' => 20, 'window' => 60],
                'callback' => CommentManager::deleteComment(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK],
                ]
            ],
            EndpointKey::GET_IMAGE_TYPE => [
                'auth' => 'user',
                'params' => [],
                'callback' => User::getImageType(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ['image_type' => ParamTypes::STRING]],
                ],
            ],
            EndpointKey::POST_IMAGE_TYPE => [
                'auth' => 'user',
                'params' => ['image_type' => ParamTypes::STRING],
                'callback' => User::setImageType(...),
                'responseType' => [
                    200 => ['success' => true, 'data' => ParamTypes::OK],
                    400 => ['success' => false, 'error' => ParamTypes::STRING]
                ],
            ],
        };
    }

    /**
     * Placeholder method for API calls that failed.
     * @return array
     */
    public static function apiError(): array
    {
        $error = 'API Error method was somehow actually called.';
        Log::error($error);
        return ['status' => 500, 'data' => $error];
    }
}
