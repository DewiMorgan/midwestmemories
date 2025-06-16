<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Enum representing parameter types that are accepted by endpoints.
 */
enum ParamTypes: string
{
    case INT = 'int';
    case STRING = 'string';
    case BOOL = 'bool';
    case FLOAT = 'float';
    case EMAIL = 'email';
    case IP = 'ip';
    case URL = 'url';
}
