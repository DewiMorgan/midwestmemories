<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Enum representing different versions of each image that may be viewed.
 * Note: backed by the midmem_users.file_type enum, which must be kept in sync.
 */
enum ImageTypes: string
{
    public const DEFAULT = ImageTypes::WEB;
    case ICE = 'ice';
    case ORIGINAL = 'original';
    case THUMBNAIL = 'thumbnail';
    case WEB = 'web';
    case BACK = 'back';
}
