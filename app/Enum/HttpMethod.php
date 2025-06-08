<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Enum representing all valid HTTP methods.
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
}
