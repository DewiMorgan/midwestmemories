<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Possible key names for the Conf class.
 */
enum SyncStatus: string
{
    // A bunch of static keys.

    /** The item has just been added from the Dropbox list. */
    case NEW = 'NEW';

    /** The item has just been downloaded from Dropbox. */
    case DOWNLOADED = 'DOWNLOADED';

    /** The item has been completely processed and no further work is needed on it. */
    case PROCESSED = 'PROCESSED';

    /** The item has some permanent error, and needs manual remediation. */
    case ERROR = 'ERROR';
}
