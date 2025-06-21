<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Enum representing all API endpoints in the system.
 */
enum EndpointKey: string
{
    case DELETE_COMMENT = 'DELETE#comment';
    case DELETE_USER = 'DELETE#user';
    case GET_COMMENT = 'GET#comment';
    case GET_CURSOR = 'GET#cursor';
    case GET_DOWNLOAD = 'GET#download';
    case GET_PROCESS = 'GET#process';
    case GET_USER = 'GET#user';
    case POST_COMMENT = 'POST#comment';
    case POST_CURSOR = 'POST#cursor';
    case POST_DOWNLOAD = 'POST#download';
    case POST_PROCESS = 'POST#process';
    case POST_USER = 'POST#user';
    case PUT_COMMENT = 'PUT#comment';
    case PUT_USER = 'PUT#user';
    case POST_LOGIN = 'POST#login';
}
