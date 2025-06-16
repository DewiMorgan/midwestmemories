<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

/**
 * Class for various path-handling helper methods.
 */
class Path
{
    public const LINK_INLINE = '1';
    public const LINK_RAW = '2';
    /** @noinspection PhpUnused */
    public const LINK_SEARCH = '3';
    public const LINK_USER = '';

    // The full filesystem path to the image folder. We don't allow access to files outside this folder.
    public static string $imgBaseUnixPath;

    /**
     * Test whether file should be listed in tree and folder views.
     * @param string $filename the filename to check: may include leading path elements.
     * @return bool true if the file may be listed to users.
     */
    public static function canListFilename(string $filename): bool
    {
        // Skip any hidden files. Also skip thumbnails, index files, and ICE files.
        $basename = basename($filename);
        return (
            !preg_match('/^(\.|tn_|index\.)|-ICE.jpg$/', $basename)
            && preg_match('/\.(gif|png|jpg|jpeg)$/', $basename)
        );
    }

    /**
     * Test whether directory should be listed in tree and folder views.
     * @param string $dirname the filename to check: may include leading path elements.
     * @return bool true if the file may be listed to users.
     */
    public static function canListDirname(string $dirname): bool
    {
        // Skip the current and parent directories, and any hidden ones.
        $basename = basename($dirname);
        return !preg_match('/^\./', $basename);
    }

    /**
     * Test whether file should be gettable if directly requested, even if unlisted.
     * @param string $filename the filename to check: may include leading path elements.
     * There's no equivalent for directories: use canListDirname() for that.
     * @return bool true if the file may be listed to users.
     */
    public static function canViewFilename(string $filename): bool
    {
        // Skip any hidden files and index files.
        // Allow thumbnails and ICE files to be viewed if directly requested.
        $basename = basename($filename);
        return (
            !preg_match('/^(\.|index\.)/', $basename)
            && preg_match('/\.(gif|png|jpg|jpeg)$/', $basename)
        );
    }

    /**
     * Handle base dir: being empty could allow arbitrary file access, so check it very early on.
     */
    public static function validateBaseDir(): void
    {
        /** @noinspection RealpathInStreamContextInspection */
        $imageDir = Conf::get(Key::IMAGE_DIR);
        $baseDir = realpath(__DIR__ . '/../' . $imageDir . '/');
        if (empty($baseDir)) {
            Log::debug('MM_BASE_DIR empty from "' . __DIR__ . ' + /../ + ' . $imageDir . ' + /".');
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
        $result = Conf::get(Key::BASE_URL) . str_replace('%2F', '/', urlencode($result));
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
            $realParentPath .= '/';
        }

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
