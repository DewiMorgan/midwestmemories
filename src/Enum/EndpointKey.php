<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Enum representing all API endpoints and their methods in the system.
 * Possibly redundant, as it is only for a lookup in one match statement.
 */
enum EndpointKey: string
{
    case ANY_ERROR = 'ANY#error';
    case DELETE_COMMENT = 'DELETE#comment';
    case DELETE_USER = 'DELETE#user';
    case GET_COMMENT = 'GET#comment';
    case GET_CURSOR = 'GET#cursor';
    case GET_DOWNLOAD = 'GET#download';
    case GET_IMAGE_TYPE = 'GET#image_type';
    case GET_PROCESS = 'GET#process';
    case GET_USER = 'GET#user';
    case POST_COMMENT = 'POST#comment';
    case POST_CURSOR = 'POST#cursor';
    case POST_DOWNLOAD = 'POST#download';
    case POST_IMAGE_TYPE = 'POST#image_type';
    case POST_LOGIN = 'POST#login';
    case POST_PROCESS = 'POST#process';
    case POST_USER = 'POST#user';
    case PUT_COMMENT = 'PUT#comment';
    case PUT_USER = 'PUT#user';
}
