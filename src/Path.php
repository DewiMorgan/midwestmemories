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

    // Full filesystem path to image folder, with trailing slash. We don't allow access to files outside this folder.
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
            Log::warn('Converted path was not found', $filePath);
            return 'PATH-ERROR-404';
        }
        if (!str_starts_with($realPath, self::$imgBaseUnixPath)) {
            Log::warn("Converted path was not within MM_BASE_DIR: '$realPath' from '$filePath'");
            return 'PATH-ERROR-401';
        }
        $result = preg_replace('#^' . preg_quote(self::$imgBaseUnixPath, '#') . '/*#', '/', $realPath);
        if (!$result) {
            Log::warn('Converted path gave an empty string or error', $filePath);
            return 'PATH-ERROR-BAD';
        }

        // Folder names may need escaping, but the slashes must remain.
        $result = Conf::get(Key::BASE_URL) . str_replace('%2F', '/', urlencode($result));
        if (self::LINK_USER !== (string)$linkType) {
            $result .= '?i=' . $linkType;
        }
//        Log::debug("$result from $filePath"); // DEBUG DELETEME
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
        $joined = self::$imgBaseUnixPath . $webPath;
        $realPath = realpath($joined);
        if (false === $realPath) {
            if (true === $mustExist) {
                Log::warn('Validated path was not found as: ' . self::$imgBaseUnixPath . ' . ' . $webPath, $joined);
                http_response_code(404); // Not found.
                die(1);
            }
            $folder = dirname($webPath);
            $fullFolder = self::$imgBaseUnixPath . $folder;
            $file = basename($webPath);
            $realPath = realpath($fullFolder);
            if (false === $realPath) {
                Log::warn('Validated folder was not found', $webPath);
                http_response_code(404); // Not found.
                die(1);
            }
            $realPath = "$realPath/$file";
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
            $fullUnixPath = "$unixPath/$filename";
            if ($fileInfo->isDir()) {
                if (Path::canListDirname($fullUnixPath)) {
                    $items[] = [
                        'unixPath' => $fullUnixPath,
                        'name' => $filename,
                        'isDir' => true
                    ];
                } else {
                    Log::debug('Ignoring unlistable folder', $fullUnixPath);
                }
            } elseif (is_file($fullUnixPath)) {
                if (Path::canListFilename($fullUnixPath)) {
                    $items[] = [
                        'unixPath' => $fullUnixPath,
                        'name' => $filename,
                        'isDir' => false
                    ];
                } else {
                    Log::debug('Ignoring unlistable file', $fullUnixPath);
                }
            } else {
                Log::debug('Ignoring unknown FS object', $fullUnixPath);
            }
        }

        // Sort naturally, folders first.
        usort($items, [Path::class, 'sortFolder']);

        return $items;
    }

    /**
     * Recursively scan a directory and output its contents in the format required for the tree view.
     * @param string $scanUnixDir Full path to the dir being scanned. When first calling, pass the root of the tree.
     * @param string $targetUnixPath The current item selected/expanded/viewed by the user.
     */
    public static function buildTree(string $scanUnixDir, string $targetUnixPath = ''): void
    {
        $items = Path::getDirItems($scanUnixDir);

        // Loop through the items and output a list item for each one.
        $files = '';
        foreach ($items as $item) {
            $filename = $item['name'];
            $isDir = $item['isDir'];
            $itemUnixPath = "$scanUnixDir/$filename";

            // Validation.
            if ($isDir) {
                if (!Path::canListDirname($itemUnixPath)) {
                    continue;
                }
            } elseif (!Path::canListFilename($itemUnixPath)) {
                continue;
            }

            $h_item = htmlspecialchars($filename);
            $itemUnixPath = "$scanUnixDir/$filename";
            $u_linkUrl = Path::unixPathToUrl($itemUnixPath, Path::LINK_INLINE);
            $h_selectClass = ($itemUnixPath === $targetUnixPath) ? 'selected' : '';
            // If the item is a directory, output a list item with a nested ul element.
            if ($isDir) {
                // Collapse, unless our target path is within this branch.
                $h_expandClass = Path::isChildInPath($targetUnixPath, $itemUnixPath) ? 'expanded' : 'collapsed';
                $h_expandIcon = ('expanded' === $h_expandClass) ? self::ICON_EXPANDED : self::ICON_COLLAPSED;
                echo "<li class='folder $h_expandClass $h_selectClass'>";
                echo "<span class='expand-collapse'>$h_expandIcon</span>";
                echo " <a href='$u_linkUrl' class='path-link'>$h_item</a>";
                echo "<ul>\n";
                // ToDo: If dir is empty, we make an empty UL. Output to a var, and only print if var has data.
                self::buildTree($itemUnixPath, $targetUnixPath);
                echo "</ul></li>\n";
            } else {
                $files .= "<li class='file $h_selectClass'>"
                    . "<a href='$u_linkUrl' class='path-link'>$h_item</a>"
                    . "</li>\n";
            }
        }
        echo $files;
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
                Path::unixPathToUrl(IndexGateway::$requestUnixPath . '/..'),
                '/raw/tn_folder_up.png',
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
                $u_thumbUrl = '/raw/tn_folder.png';
            } else {
                $thumbUnixPath = FileProcessor::getThumbName($itemPath);
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
}
