<?php

declare(strict_types=1);

namespace MidwestMemories;

use DirectoryIterator;
use MidwestMemories\Enum\Key;

/**
 * Class for various path-handling helper methods.
 */
class Path
{
    /* Alternatives and options for image files include:
     * U+1F4C1 📁 File Folder
     * U+1F4C2 📂 Open File Folder
     * U+1F5BF 🖿 Black Folder
     * U+1F5C0 🗀 Folder
     * U+1F5C1 🗁 Open Folder
     * U+1F4F7 📷 Camera
     * U+1F4C4 📄 Page Facing Up
     * U+1F5BB 🖻 Document with Picture.
     */
    private const ICON_EXPANDED = '📂'; // U+1F4C2 Open File Folder
    private const ICON_COLLAPSED = '📁'; // U+1F4C1 File Folder

    public const LINK_INLINE = '1';
    public const LINK_RAW = '2';
    /** @noinspection PhpUnused */
    public const LINK_SEARCH = '3';
    public const LINK_USER = '';
    // Hidden file name for the cache prevents it from getting listed.
    private const TREE_CACHE_FILE = '.tree.dat';

    /** Full filesystem path to image folder, no trailing slash. We forbid access to files outside this folder. */
    public static string $imgBaseUnixPath;

    /**
     * Test whether file should be listed in tree and folder views.
     * @param string $filename the filename to check: may include leading path elements.
     * @return bool true if the file may be listed to users.
     */
    public static function canListFilename(string $filename): bool
    {
        // Skip any hidden and index files, and alternate image versions.
        $basename = basename($filename);
        return (
            !preg_match('/^(\.|index\.)|-(ICE|WEB|TN|B)\.[^.]+$/', $basename)
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
        // Allow thumbnails, ICE files and so on to be viewed if directly requested.
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
        $baseDir = realpath(Path::join(__DIR__, '..', $imageDir));
        if (empty($baseDir)) {
            Log::debug('MM_BASE_DIR empty from "' . __DIR__ . ' + /../ + ' . $imageDir . '"');
            Log::debug('Not safe to continue');
            http_response_code(500); // Internal Server Error.
            die(1);
        }
        self::$imgBaseUnixPath = $baseDir;
    }

    /**
     * Take a filesystem path of an object on the filesystem, and return an absolute URL.
     * @param string $fileUnixPath The filesystem path to convert.
     * @return string The converted path, or a string like 'PATH-ERROR-...' on failure, to avoid exploits.
     */
    public static function unixPathToUrl(string $fileUnixPath, $linkType = self::LINK_USER): string
    {
        if (!str_starts_with($fileUnixPath, self::$imgBaseUnixPath)) {
            Log::debug('Prepending MM_BASE_DIR', $fileUnixPath);
            $fileUnixPath = self::join(self::$imgBaseUnixPath, $fileUnixPath);
        }
        $realPath = realpath($fileUnixPath);
        if (!$realPath) {
            Log::warn('Converted path was not found', $fileUnixPath);
            return 'PATH-ERROR-404';
        }
        if (!str_starts_with($realPath, self::$imgBaseUnixPath)) {
            Log::warn("Converted path was not within MM_BASE_DIR: '$realPath' from '$fileUnixPath'");
            return 'PATH-ERROR-401';
        }

        // Strip self::$imgBaseUnixPath and any extra slashes from start of path, and replace with single leading slash.
        $result = preg_replace('#^' . preg_quote(rtrim(self::$imgBaseUnixPath, '/')) . '/*#', '/', $realPath);

        if (!$result) {
            Log::warn('Converted path gave an empty string or error', $fileUnixPath);
            return 'PATH-ERROR-BAD';
        }

        // Folder names may need escaping, but the slashes must remain.
        $result = Path::join(Conf::get(Key::BASE_URL), str_replace('%2F', '/', urlencode($result)));
        if (self::LINK_USER !== (string)$linkType) {
            $result .= '?i=' . $linkType;
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
        if (!str_starts_with($webPath, self::$imgBaseUnixPath)) {
            $joined = self::join(self::$imgBaseUnixPath, $webPath);
        } else {
            $joined = $webPath;
        }
        $realPath = realpath($joined);
        if (false === $realPath) {
            if (true === $mustExist) {
                Log::warn("Validated path '$webPath' was not found as", $joined);
                Log::warn(var_export(debug_backtrace(), true));
                http_response_code(404); // Not found.
                die(1);
            }

            // Try to build it as a canonical path within a real folder, even though the file itself doesn't exist.
            $file = basename($joined);
            $fullFolder = dirname($joined);
            $realPath = realpath($fullFolder);
            if (false === $realPath) {
                Log::warn("Validated folder '$webPath' was not found as '$fullFolder'", $fullFolder);
                Log::warn(var_export(debug_backtrace(), true));
                http_response_code(404); // Not found.
                die(1);
            }
            $realPath = self::join($realPath, $file);
        }
        if (!str_starts_with($realPath, self::$imgBaseUnixPath)) {
            Log::warn('Validated path was not within MM_BASE_DIR', $webPath);
            http_response_code(404); // Not found.
            die(1);
        }
        // Log::debug("Validated path: '$webPath' as '$realPath'"); // DELETEME DEBUG
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
     * Comparison function for natural sorting of directory items
     *
     * @param array $a First item
     * @param array $b Second item
     * @return int Comparison result for usort
     */
    public static function sortFolder(array $a, array $b): int
    {
        // First, sort directories before files
        if ($a['isDir'] && !$b['isDir']) {
            return -1;
        }
        if (!$a['isDir'] && $b['isDir']) {
            return 1;
        }

        // If both are directories or both are files, sort naturally by name.
        return strnatcasecmp($a['name'], $b['name']);
    }

    /**
     * Get all the listable items in a directory, naturally sorted, folders first.
     * @param string $unixPath
     * @return array
     */
    public static function getDirItems(string $unixPath): array
    {
        $items = [];
        $dir = new DirectoryIterator($unixPath);

        foreach ($dir as $fileInfo) {
            $filename = $fileInfo->getFilename();
            $fullUnixPath = Path::join($unixPath, $filename);
            if ($fileInfo->isDir()) {
                if (Path::canListDirname($fullUnixPath)) {
                    $items[] = [
                        'unixPath' => $fullUnixPath,
                        'name' => $filename,
                        'isDir' => true
                    ];
                } else {
                    //Log::debug('Ignoring unlistable folder', $fullUnixPath);
                }
            } elseif (is_file($fullUnixPath)) {
                if (Path::canListFilename($fullUnixPath)) {
                    $items[] = [
                        'unixPath' => $fullUnixPath,
                        'name' => $filename,
                        'isDir' => false
                    ];
                } else {
                    //Log::debug('Ignoring unlistable file', $fullUnixPath);
                }
            } else {
                //Log::debug('Ignoring unknown FS object', $fullUnixPath);
            }
        }

        // Sort naturally, folders first.
        usort($items, [Path::class, 'sortFolder']);

        return $items;
    }

    /**
     * Build and cache the directory tree structure.
     * @return bool success.
     */
    public static function cacheTree(): bool
    {
        $result = self::recurseTree(self::$imgBaseUnixPath);
        $cacheFile = self::join(self::$imgBaseUnixPath, self::TREE_CACHE_FILE);
        if (false === file_put_contents($cacheFile, $result)) {
            Log::error('Failed to cache tree');
            return false;
        }
        Log::debug("Built tree cache at: '$cacheFile'.");
        return true;
    }

    /**
     * Recursively builds the directory tree structure as a series of li items.
     *
     * @param string $baseDir The folder being read at each recursion level. Initially the root of the tree.
     * @return string the tree structure as a list of records like, for folders:
     *      <li class='folder collapsed'><span class='expand-collapse'>📁</span>
     *      <a href='LINK_URL' class='path-link'>LINK_TEXT</a><ul> ... recurse ... </ul></li>
     * or for files:
     *     <li class='file'><a href='LINK_URL' class='path-link'>LINK_TEXT</a></li>
     */
    private static function recurseTree(string $baseDir): string
    {
        // This gets the items correctly sorted already.
        $items = self::getDirItems($baseDir);
        $entry = '';

        foreach ($items as $item) {
            $h_item = htmlspecialchars($item['name']);
            $u_linkUrl = $item['unixPath'] . '?i=' . Path::LINK_INLINE;

            if ($item['isDir']) {
                $entry .= "<li class='folder collapsed'>";
                $entry .= "<span class='expand-collapse'>" . self::ICON_COLLAPSED . '</span>';
                $entry .= " <a href='$u_linkUrl' class='path-link'>$h_item</a>";
                $entry .= "<ul>\n";
                $entry .= self::recurseTree($item['unixPath']);
                $entry .= "</ul></li>\n";
            } else {
                $entry .= "<li class='file'><a href='$u_linkUrl' class='path-link'>$h_item</a></li>\n";
            }
        }

        return $entry;
    }

    /**
     * Render the collapsable HTML nav tree from the cache.
     */
    public static function buildTree(): void
    {
        readfile(self::TREE_CACHE_FILE);
    }

    /**
     * Generate the list of thumbnails for the folder view.
     */
    public static function generateThumbs(): void
    {
        $items = Path::getDirItems(IndexGateway::$requestUnixPath);

        // Add the 'up one folder' item, unless we're at the root.
        if ('/' !== IndexGateway::$requestWebPath) {
            Path::addThumb(
                Path::unixPathToUrl(Path::join(IndexGateway::$requestUnixPath, '..')),
                '/raw/folder_up-TN.png',
                '<strong>..</strong> - up one folder.'
            );
        }

        // Output.
        $fileNum = 0;
        foreach ($items as $item) {
            $filename = $item['name'];
            $isDir = $item['isDir'];
            $itemPath = $item['unixPath'];

            // Skip files without a matching thumbnail file: they have not been fully processed.
            if ($isDir) {
                $h_thumbTitle = htmlspecialchars($filename);
                $u_thumbUrl = '/raw/folder-TN.png';
            } else {
                $thumbUnixPath = FileProcessor::getThumbName($itemPath, 'TN');
                if (!is_file($thumbUnixPath)) {
                    Log::debug("No thumb found for image: '$thumbUnixPath' from '$itemPath'");
                    continue;
                }
                Log::debug("Creating thumb-link for image: '$thumbUnixPath' from '$itemPath'");
                $u_thumbUrl = Path::unixPathToUrl($thumbUnixPath, Path::LINK_RAW);
                $fileNum++;
                $h_thumbTitle = htmlspecialchars($filename);
            }
            $u_linkUrl = Path::unixPathToUrl($itemPath, Path::LINK_INLINE);

            Path::addThumb($u_linkUrl, $u_thumbUrl, $h_thumbTitle, $fileNum);
        }
    }

    /**
     * Add one thumbnail link to the page.
     * @param string $u_linkUrl URL-escaped link to the file.
     * @param string $u_thumbUrl URL-escaped link to the thumbnail.
     * @param string $h_thumbTitle HTML-escaped title of the thumbnail.
     * @param int $fileNum The ordinal position of the file within the folder.
     * @return void
     */
    public static function addThumb(string $u_linkUrl, string $u_thumbUrl, string $h_thumbTitle, int $fileNum = 0): void
    {
        echo("<div class='thumb'><figure>");

        echo("<a href='$u_linkUrl'><img src='$u_thumbUrl' title='$h_thumbTitle' alt='$h_thumbTitle'></a>");
        echo('<figcaption>');
        if ($fileNum) {
            echo("<strong>$fileNum: </strong>");
        }
        echo("<a href='$u_linkUrl'>$h_thumbTitle</a></figcaption>");
        echo('</figure></div>');
    }

    /**
     * Get a script's version number.
     * @param string $unixPath The script file to read the version number from.
     * @return int The version, or 0 if the file does not exist yet.
     */
    public static function getScriptVersion(string $unixPath): int
    {
        // If it doesn't exist, we can't get the version.
        if (!file_exists($unixPath)) {
            return 0;
        }

        // Read one line.
        $file = fopen($unixPath, 'r');
        $line = fgets($file);
        fclose($file);

        // Parse and return the version number.
        return intval(preg_match('/(\d+)/', $line, $matches) ? $matches[1] : 0);
    }

    /**
     * Join path elements together with exactly one directory separator between them.
     * Preserves a leading slash on the first element if present, but removes all other slashes.
     * Note: cannot use other classes in this (like Log::debug) as it is called at a very low level.
     * @param string ...$parts The path parts to join.
     * @return string The joined path with no trailing slash, and exactly one directory separator between elements.
     */
    public static function join(string ...$parts): string
    {
        if (empty($parts)) {
            return '';
        }

        // Check for leading slash on the first part.
        $hasLeadingSlash = $parts[0] !== '' && str_starts_with($parts[0], '/');
        // Trim all parts and filter out empty ones.
        $parts = array_map(static fn($part) => trim($part, '/'), $parts);
        $parts = array_filter($parts, static fn($part) => $part !== '');

        // Join with single slashes and add back leading slash if needed.
        $result = implode('/', $parts);
        return ($hasLeadingSlash ? '/' : '') . $result;
    }
}
