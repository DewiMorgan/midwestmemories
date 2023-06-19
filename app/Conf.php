<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Config manager.
 */
class Conf
{
    private const MYSQL_INI_FILE = 'MySqlAuth.ini';
    private const DROPBOX_INI_FILE = 'DropboxAuth.ini';
    private const GENERAL_INI_FILE = 'MidwestMemories.ini';

    private static ?array $data = null;

    /**
     * Retrieve a config value, given the key.
     * @param Key $key The key, as an item from the Key enum.
     * @return ?string The value requested, or null if not found.
     * @noinspection PhpMethodNamingConventionInspection
     */
    public static function get(Key $key): ?string
    {
        if (is_null(self::$data)) {
            self::initialize();
        }
        if (array_key_exists($key->value, self::$data)) {
            return self::$data[$key->value];
        } else {
            return null;
        }
    }

    /**
     * Set a config value from ini file data, given the key.
     * @param Key $key The enum to look for as a key in the ini file data.
     * @param array $iniFileData The parsed ini file data to search through for the key.
     * @param ?mixed $default A default value to give if value not found. If not set, missing values die with an error.
     */
    private static function parseIniFileKey(Key $key, array $iniFileData, mixed $default = null): void
    {
        if (array_key_exists($key->value, $iniFileData)) {
            self::$data[$key->value] = $iniFileData[$key->value];
        } elseif (!is_null($default)) {
            self::$data[$key->value] = $default;
        } else {
            Log::adminDebug('Config entry could not be found and there was no default:' . $key->value);
            die();
        }
    }

    /**
     * Populate the configuration class.
     */
    private static function initialize(): void
    {
        // Parse the general config stuff.
        self::$data = self::readIniInParents(self::GENERAL_INI_FILE);

        // Parse the MySQL INI file.
        if (!$mysqlConfig = self::readIniInParents(self::MYSQL_INI_FILE)) {
            Log::adminDebug('MySQL Auth information could not be read.');
            die();
        }
        self::parseIniFileKey(Key::MYSQL_HOST, $mysqlConfig);
        self::parseIniFileKey(Key::MYSQL_PORT, $mysqlConfig, 3306);
        self::parseIniFileKey(Key::MYSQL_NAME, $mysqlConfig);
        self::parseIniFileKey(Key::MYSQL_USER, $mysqlConfig);
        self::parseIniFileKey(Key::MYSQL_PASS, $mysqlConfig);

        // Parse the Dropbox INI file.
        if (!$dropboxConfig = self::readIniInParents(self::DROPBOX_INI_FILE)) {
            Log::adminDebug('Dropbox Auth information could not be read.');
            die();
        }
        self::parseIniFileKey(Key::DROPBOX_KEY, $dropboxConfig);
        self::parseIniFileKey(Key::DROPBOX_SECRET, $dropboxConfig);
        self::parseIniFileKey(Key::DROPBOX_USER_ID, $dropboxConfig);
        self::parseIniFileKey(Key::DROPBOX_REFRESH_TOKEN, $dropboxConfig);
    }


    /**
     * Parse a named folder from the current working directory, or any parent folder.
     * @param string $filename The filename to find in the current folder or any parent folder.
     * @return array|false Array of data parsed from the ini file, or false on failure.
     */
    private static function readIniInParents(string $filename): array|false
    {
        $dir = getcwd();
        while ($dir != '/') {
            if (file_exists($dir . '/' . $filename)) {
                return parse_ini_file($dir . '/' . $filename);
            }
            $dir = dirname($dir);
        }
        return false;
    }

}