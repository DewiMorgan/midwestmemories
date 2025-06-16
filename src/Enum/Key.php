<?php

declare(strict_types=1);

namespace MidwestMemories\Enum;

/**
 * Possible key names for the Conf class.
 * @noinspection PhpClassNamingConventionInspection
 */
enum Key: string
{
    // A bunch of static keys for the Conf class.
    case BASE_URL = 'base_url';
    case DEFAULT_TIMEOUT = 'default_timeout';
    case DROPBOX_KEY = 'dropbox_key';
    case DROPBOX_REFRESH_TOKEN = 'dropbox_refresh_token';
    case DROPBOX_SECRET = 'dropbox_secret';
    case DROPBOX_PATH_PREFIX = 'dropbox_path_prefix';
    case DROPBOX_USER_ID = 'dropbox_user_id';
    case IMAGE_DIR = 'image_dir';
    case LOG_FILE = 'log_file';
    case LOG_LEVEL = 'log_level';
    case MAX_PNG_BYTES = 'max_png_bytes';
    case MAX_THUMB_HEIGHT = 'max_thumb_height';
    case MAX_THUMB_WIDTH = 'max_thumb_width';
    case MYSQL_HOST = 'mysql_host';
    case MYSQL_NAME = 'mysql_name';
    case MYSQL_PASS = 'mysql_pass';
    case MYSQL_PORT = 'mysql_port';
    case MYSQL_USER = 'mysql_user';
    case PASSWORD_FILE = 'password_file';
}
