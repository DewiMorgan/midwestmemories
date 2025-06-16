<?php
/** @noinspection PhpConstantNamingConventionInspection enums can be short! */

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Enum representing user access levels.
 */
enum UserAccess: int
{
    case NONE = 0;
    case USER = 1;
    case ADMIN = 2;
    case SUPER_ADMIN = 3;
}
