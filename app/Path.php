<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Class for various path-handling helper methods.
 */
class Path
{
    public const IMAGE_DIR = 'midwestmemories';

    // The full filesystem path to the image folder. We don't allow access to files outside this folder.
    public static string $imageBasePath;

    /**
     * Handle base dir: being empty could allow arbitrary file access, so check it very early on.
     */
    public static function validateBaseDir(): void
    {
        $baseDir = realpath(__DIR__ . '/../' . Path::IMAGE_DIR . '/');
        if (empty($baseDir)) {
            Log::adminDebug('MM_BASE_DIR empty from "' . __DIR__ . ' + /../ + ' . Path::IMAGE_DIR . ' + /".');
            Log::adminDebug('Not safe to continue');
            http_response_code(500); // Internal Server Error.
            die(1);
        }
        Path::$imageBasePath = $baseDir;
    }

    /**
     * Take a filesystem path of an object on the filesystem, and return an absolute web path, from the document root.
     * @param string $filePath The filesystem path to convert.
     * @return string The converted path, or a string like 'PATH_ERROR_...' on failure, to avoid exploits.
     */
    public static function filePathToWeb(string $filePath): string
    {
        $realPath = realpath($filePath);
        if (!$realPath) {
            Log::adminDebug("Converted path was not found: $filePath");
            return 'PATH_ERROR_404';
        }
        $result = preg_replace('#^' . preg_quote(Path::$imageBasePath) . '#', '/', $realPath);
        if (!$result) {
            Log::adminDebug("Converted path gave an empty string or error: $filePath");
            return 'PATH_ERROR_BAD';
        }
        if (!str_starts_with($realPath, Path::$imageBasePath)) {
            Log::adminDebug("Converted path was not within MM_BASE_DIR: $realPath from $filePath");
            return 'PATH_ERROR_401';
        }
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
        Log::debug(__METHOD__ . "($childPath, $parentPath)"); // DELETEME DEBUG

        // Check they both exist.
        $realChildPath = realpath($childPath);
        if (false === $realChildPath) {
            Log::adminDebug("Child path was not found: $childPath");
            http_response_code(404); // Not found.
            die(1);
        }
        $realParentPath = realpath($parentPath);
        if (false === $realParentPath) {
            Log::adminDebug("Parent path was not found: $parentPath");
            http_response_code(404); // Not found.
            die(1);
        }

        // Only need to check that parent is in basedir.
        if (!str_starts_with($realParentPath, Path::$imageBasePath)) {
            Log::adminDebug("Parent path was not within MM_BASE_DIR: $parentPath");
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
     * Safely convert a web path to a unix filesystem path, or die if it's not within MM_BASE_DIR.
     * @param string $webPath The web path to validate and correct, relative to MM_BASE_DIR.
     * @param bool $mustExist True (default) if the file must exist on the folder (folder must always exist!
     * @return string The converted path, relative to filesystem root.
     */
    public static function webToUnixPath(string $webPath, bool $mustExist = true): string
    {
        $realPath = realpath(Path::$imageBasePath . '/' . $webPath);
        if (false === $realPath) {
            if (true === $mustExist) {
                Log::adminDebug("Validated path was not found: $webPath");
                http_response_code(404); // Not found.
                die(1);
            } else {
                $folder = Path::$imageBasePath . '/' . dirname($webPath);
                $file = basename($webPath);
                $realPath = realpath($folder);
                if (false === $realPath) {
                    Log::adminDebug("Validated folder was not found: $webPath");
                    http_response_code(404); // Not found.
                    die(1);
                }
                $realPath = "$realPath/$file";
            }
        }
        if (!str_starts_with($realPath, Path::$imageBasePath)) {
            Log::adminDebug("Validated path was not within MM_BASE_DIR: $webPath");
            http_response_code(404); // Not found.
            die(1);
        }
        Log::adminDebug("Validated path: $webPath as $realPath");
        return $realPath;
    }
}