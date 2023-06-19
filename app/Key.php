<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Possible key names for the Conf class.
 * @noinspection PhpClassNamingConventionInspection
 */
enum Key: string
{
    // A bunch of static keys.
    case LOG_FILE = 'log_file';
    case LOG_LEVEL = 'log_level';
    case MYSQL_HOST = 'mysql_host';
    case MYSQL_PORT = 'mysql_port';
    case MYSQL_NAME = 'mysql_name';
    case MYSQL_USER = 'mysql_user';
    case MYSQL_PASS = 'mysql_pass';
    case DROPBOX_KEY = 'dropbox_key';
    case DROPBOX_SECRET = 'dropbox_secret';
    case DROPBOX_REFRESH_TOKEN = 'dropbox_refresh_token';
    case DROPBOX_USER_ID = 'dropbox_user_id';
}
