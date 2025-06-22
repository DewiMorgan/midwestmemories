<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Enum representing all API endpoints in the system.
 */
enum EndpointPath: string
{
    case CURSOR = 'cursor';
    case DOWNLOAD = 'download';
    case PROCESS = 'process';
    case USER = 'user';
    case COMMENT = 'comment';
    case LOGIN = 'login';
}
