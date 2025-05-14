<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Class for various path-handling helper methods.
 */
class Path
{
    public const IMAGE_DIR = 'midwestmemories';

    public const LINK_INLINE = '1';
    public const LINK_RAW = '2';
    public const LINK_SEARCH = '3';
    public const LINK_USER = '';

    // The full filesystem path to the image folder. We don't allow access to files outside this folder.
    public static string $imgBaseUnixPath;

    /**
     * Handle base dir: being empty could allow arbitrary file access, so check it very early on.
     */
    public static function validateBaseDir(): void
    {
        /** @noinspection RealpathInStreamContextInspection */
        $baseDir = realpath(__DIR__ . '/../' . self::IMAGE_DIR . '/');
        if (empty($baseDir)) {
            Log::debug('MM_BASE_DIR empty from "' . __DIR__ . ' + /../ + ' . self::IMAGE_DIR . ' + /".');
            Log::debug('Not safe to continue');
            http_response_code(500); // Internal Server Error.
            die(1);
        }
        self::$imgBaseUnixPath = $baseDir;
    }

    /**
     * Take a filesystem path of an object on the filesystem, and return an absolute URL.
     * @param string $filePath The filesystem path to convert.
     * @return string The converted path, or a string like 'PATH-ERROR-...' on failure, to avoid exploits.
     */
    public static function unixPathToUrl(string $filePath, $linkType = self::LINK_USER): string
    {
        if (!str_starts_with($filePath, self::$imgBaseUnixPath)) {
            Log::debug('Prepending MM_BASE_DIR', $filePath);
            $filePath = self::$imgBaseUnixPath . $filePath;
        }
        $realPath = realpath($filePath);
        if (!$realPath) {
            Log::debug('Converted path was not found', $filePath);
            return 'PATH-ERROR-404';
        }
        if (!str_starts_with($realPath, self::$imgBaseUnixPath)) {
            Log::debug("Converted path was not within MM_BASE_DIR: '$realPath' from '$filePath'");
            return 'PATH-ERROR-401';
        }
        $result = preg_replace('#^' . preg_quote(self::$imgBaseUnixPath, '#') . '/*#', '/', $realPath);
        if (!$result) {
            Log::debug('Converted path gave an empty string or error', $filePath);
            return 'PATH-ERROR-BAD';
        }

        // Folder names may need escaping, but the slashes must remain.
        $result = Index::MM_BASE_URL . str_replace('%2F', '/', urlencode($result));
        if (self::LINK_USER !== (string)$linkType) {
            $result .= '?i=' . $linkType;
        }
        Log::debug("$result from $filePath");
        return $result;
    }

    /**
     * Verify a requested path contains a child element, and both exist. Used for example when generating the tree view,
     * to find paths that contain the current item, so should be expanded.
     * @param string $parentPath The filesystem path that should contain the child.
     * @param string $childPath The filesystem path that should be within the parent.
     * @return bool True if the child is within the parent.
     */
    public static function isChildInPath(string $childPath, string $parentPath): bool
    {
        Log::debug("($childPath, $parentPath)"); // DELETEME DEBUG

        // Check they both exist.
        $realChildPath = realpath($childPath);
        if (false === $realChildPath) {
            Log::debug("Child path was not found: $childPath");
            http_response_code(404); // Not found.
            die(1);
        }
        $realParentPath = realpath($parentPath);
        if (false === $realParentPath) {
            Log::debug("Parent path was not found: $parentPath");
            http_response_code(404); // Not found.
            die(1);
        }

        // Only need to check that parent is in basedir.
        if (!str_starts_with($realParentPath, self::$imgBaseUnixPath)) {
            Log::debug("Parent path was not within MM_BASE_DIR: $parentPath");
            http_response_code(404); // Not found.
            die(1);
        }

        // Prevent /pa/ from matching /path/to/file.
        if (strlen($realParentPath) < strlen($realChildPath)) {
            Log::debug('Expanding short parent.'); // DELETEME DEBUG
            $realParentPath .= '/';
        }

        Log::debug('Result: ' . (str_starts_with($realChildPath, $realParentPath) ? 'y' : 'n')); // DELETEME DEBUG

        // Return whether the parent contains the child.
        return str_starts_with($realChildPath, $realParentPath);
    }

    /**
     * Safely convert a web path to a unix filesystem path, or die if it isn't within MM_BASE_DIR.
     * @param string $webPath The web path to validate and correct, relative to MM_BASE_DIR.
     * @param bool $mustExist True (default) if the file must exist in the folder (folder must always exist!)
     * @return string The converted path, relative to filesystem root.
     */
    public static function webToUnixPath(string $webPath, bool $mustExist = true): string
    {
        if (self::isApiPath($webPath)) {
            return 'NO UNIX PATH FOR API CALLS';
        }

        $realPath = realpath(self::$imgBaseUnixPath . '/' . $webPath);
        if (false === $realPath) {
            if (true === $mustExist) {
                Log::debug('Validated path was not found', $webPath);
                http_response_code(404); // Not found.
                die(1);
            }
            $folder = dirname($webPath);
            $fullFolder = self::$imgBaseUnixPath . $folder;
            $file = basename($webPath);
            $realPath = realpath($fullFolder);
            if (false === $realPath) {
                // DEBUG DELETEME
                Log::debug("Validating missing $fullFolder via $folder & $file, folder not found", $webPath);
                Log::debug('Backtrace', debug_backtrace());
                // End DEBUG DELETEME
                Log::debug('Validated folder was not found', $webPath);
                http_response_code(404); // Not found.
                die(1);
            }
            $realPath = "$realPath/$file";
        }
        if (!str_starts_with($realPath, self::$imgBaseUnixPath)) {
            Log::debug('Validated path was not within MM_BASE_DIR', $webPath);
            http_response_code(404); // Not found.
            die(1);
        }
        Log::debug("Validated path: '$webPath' as '$realPath'");
        return $realPath;
    }

    /**
     * Convert a unix path to a web path, or return empty string if it isn't within the web folder.
     * @param string $unixPath The path to convert to a web path.
     * @return string The converted path, relative to document root ($imgBaseUnixPath), or empty string.
     */
    public static function unixToWebPath(string $unixPath): string
    {
        if (str_contains($unixPath, self::$imgBaseUnixPath)) {
            return str_replace(self::$imgBaseUnixPath, '', $unixPath);
        }
        return '';
    }

    /**
     * @param mixed $requestWebPath A path that either begins like v1/comments (an API path), or does not.
     * @return bool Whether it matched.
     */
    public static function isApiPath(mixed $requestWebPath): bool
    {
        return (bool)preg_match('#^/?v\d+(/\w+)*/?$#', $requestWebPath);
    }
}
