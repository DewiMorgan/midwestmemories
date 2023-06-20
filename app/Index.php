<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Connection;
use MidwestMemories\Db;

/**
 * The class for the main index.php file.
 */
class Index
{
    // Constants.
    public const MM_BASE_URL = 'https://midwestmemories.dewimorgan.com';
    public const IMAGE_DIR = 'midwestmemories';

    // We don't allow accessing files outside this folder.
    public static string $baseDir;
    // The path that the user requested, HTML-escaped.
    public static string $h_requestedPath;
    // The actual path to the thing the user requested. If set, the thing exists.
    public static string $realPath;

    public function __construct()
    {
        static::handleLogouts();
        static::validateBaseDir();
        static::initSession();

        $requestedPath = $_REQUEST['path'] ?? '/';
        static::validatePath($requestedPath);
        static::$h_requestedPath = htmlspecialchars($requestedPath);

        static::showPage();
    }

    /**
     * Handle logout requests.
     */
    private static function handleLogouts(): void
    {
        // Handle logouts.
        if (array_key_exists('logout', $_REQUEST) && $_REQUEST['logout'] && 'true' == $_SESSION['login']) {
            header('HTTP/1.1 401 Unauthorized');
            $_SESSION['login'] = 'false';
            echo "<!DOCTYPE html>\n";
            echo '<html lang="en"><head><title>Logout</title></head><body><h1>Logged out</h1><p><a href="'
                . $_SERVER['PHP_SELF'] . '">Click here to log back in.</a></p></body></html>' . "\n";
            exit(0);
        }
    }

    /**
     * Handle session and connection.
     */
    private static function initSession(): void
    {
        global $connection;

        $_SESSION['login'] = 'true';
        $_SESSION['name'] = $_SERVER['PHP_AUTH_USER'];

        $connection = new Connection();

        // Log this login.
        // ToDo: this is very low level, and should probably be wrapped in a connectionLogger.
        Db::sqlExec(
            'INSERT INTO midmem_visitors (`request`, `main_ip`, `all_ips_string`, `user`, `agent`)'
            . ' VALUES (?, ?, ?, ?, ?)',
            'sssss',
            $connection->request,
            $connection->ip,
            $connection->ipList,
            $connection->user,
            $connection->agent
        );
    }

    /**
     * Handle base dir: being empty could allow arbitrary file access, so check it very early on.
     */
    private static function validateBaseDir(): void
    {
        $baseDir = realpath(__DIR__ . '/../' . Index::IMAGE_DIR . '/');
        if (empty($baseDir)) {
            Log::adminDebug('MM_BASE_DIR empty from "' . __DIR__ . ' + /../ + ' . Index::IMAGE_DIR . ' + /".');
            Log::adminDebug('Not safe to continue');
            http_response_code(500); // Internal Server Error.
            die();
        }
        static::$baseDir = $baseDir;
    }

    /**
     * Check the file request we were given.
     */
    private static function validatePath($requestedPath): void
    {
        $realPath = realpath(static::$baseDir . '/' . $requestedPath);
        if (false === $realPath) {
            Log::adminDebug("Requested path was not found: $requestedPath");
            http_response_code(404); // Not found.
            die();
        }
        if (!str_starts_with($realPath, static::$baseDir)) {
            Log::adminDebug("Requested path was not within MM_BASE_DIR: $requestedPath");
            http_response_code(404); // Not found.
            die();
        }
        static::$realPath = $realPath;
    }

    /**
     * Handle displaying the requested page.
     */
    private static function showPage(): void
    {
        // Inline requires are internal requests, rather than user requests.
        $isInlineRequest = isset($_REQUEST['i']);

        if (!$isInlineRequest) {
            // This is a request by a user, perhaps to a bookmark.
            // Load the tree view, which will then call us back for the inline version of the pointed-at $path resource.
            include('app/TreeTemplate.php');
        } elseif (is_dir(static::$realPath)) {
            // We're showing an inline folder view; a list of thumbnails.
            include('app/ThumbsTemplate.php');
        } elseif (is_file(static::$realPath)) {
            // ToDo: We're showing an inline file view.
            // include('app/FileTemplate.php');
            echo 'File view not yet implemented';
            http_response_code(501); // Not implemented.
        } else {
            // ToDo: We're showing an inline search view.
            // include('app/SearchTemplate.php');
            echo 'Search view not yet implemented';
            http_response_code(501); // Not implemented.
        }
    }
}
