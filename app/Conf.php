<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Config manager.
 */
class Conf extends Singleton
{
    private const MYSQL_INI_FILE = 'MySqlAuth.ini';
    private const DROPBOX_INI_FILE = 'DropboxAuth.ini';
    private const GENERAL_INI_FILE = 'MidwestMemories.ini';

    protected array $data;

    /**
     * Protected singleton constructor.
     */
    protected function __construct()
    {
        parent::__construct();

        // Parse the general config stuff.
        $this->data = self::readIniInParents(self::GENERAL_INI_FILE);

        // Parse the MySQL INI file.
        if (!$mysqlConfig = self::readIniInParents(self::MYSQL_INI_FILE)) {
            Log::debug('MySQL Auth information could not be read.');
            die(1);
        }
        self::parseIniFileKey(Key::MYSQL_HOST, $mysqlConfig);
        self::parseIniFileKey(Key::MYSQL_PORT, $mysqlConfig, 3306);
        self::parseIniFileKey(Key::MYSQL_NAME, $mysqlConfig);
        self::parseIniFileKey(Key::MYSQL_USER, $mysqlConfig);
        self::parseIniFileKey(Key::MYSQL_PASS, $mysqlConfig);

        // Parse the Dropbox INI file.
        if (!$dropboxConfig = self::readIniInParents(self::DROPBOX_INI_FILE)) {
            Log::debug('Dropbox Auth information could not be read.');
            die(1);
        }
        self::parseIniFileKey(Key::DROPBOX_KEY, $dropboxConfig);
        self::parseIniFileKey(Key::DROPBOX_SECRET, $dropboxConfig);
        self::parseIniFileKey(Key::DROPBOX_USER_ID, $dropboxConfig);
        self::parseIniFileKey(Key::DROPBOX_REFRESH_TOKEN, $dropboxConfig);
    }

    /**
     * Retrieve a config value, given the key.
     * @param Key $key The key, as an item from the Key enum.
     * @return ?string The value requested, or null if not found.
     * @noinspection PhpMethodNamingConventionInspection "too short".
     */
    public static function get(Key $key): ?string
    {
        return self::getInstance()->getValue($key);
    }

    /**
     * A private helper to retrieve a config value, given the key.
     * @param Key $key The key, as an item from the Key enum.
     * @return ?string The value requested, or null if not found.
     */
    private function getValue(Key $key): ?string
    {
        return $this->data[$key->value] ?? null;
    }

    /**
     * Set a config value from ini file data, given the key.
     * @param Key $key The enum to look for as a key in the ini file data.
     * @param array $iniFileData The parsed ini file data to search through for the key.
     * @param ?mixed $default A default value to give if value not found. If not set, missing values die with an error.
     */
    private function parseIniFileKey(Key $key, array $iniFileData, mixed $default = null): void
    {
        if (array_key_exists($key->value, $iniFileData)) {
            $this->data[$key->value] = $iniFileData[$key->value];
        } elseif (!is_null($default)) {
            $this->data[$key->value] = $default;
        } else {
            Log::debug('Config entry could not be found and there was no default:' . $key->value);
            die(1);
        }
    }

    /**
     * Parse a named folder from the current working directory, or any parent folder.
     * @param string $filename The filename to find in the current folder or any parent folder.
     * @return array Array of data parsed from the ini file, or empty array on failure.
     */
    private function readIniInParents(string $filename): array
    {
        $dir = getcwd();
        while ($dir !== '/') {
            if (file_exists($dir . '/' . $filename)) {
                return parse_ini_file($dir . '/' . $filename);
            }
            $dir = dirname($dir);
        }
        return [];
    }

}
