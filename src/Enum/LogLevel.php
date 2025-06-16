<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Possible logging levels for the Log class.
 */
enum LogLevel: int
{
    case debug = 1;
    case info = 2;
    case warn = 3;
    case error = 4;
    case never = 5;
}
