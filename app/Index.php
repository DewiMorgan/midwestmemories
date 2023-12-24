<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * The class for the main index.php file.
 */
class Index
{
    // Constants.
    public const MM_BASE_URL = 'https://midwestmemories.dewimorgan.com';

    // User's requested path. If set, exists and is relative to in Path::$imageBasePath. No trailing slash on folders.
    public static string $requestedPath;

    // HTML-escaped path that the user requested.
    public static string $h_requestedPath;

    public function __construct()
    {
        static::handleLogouts();
        Path::validateBaseDir();
        static::initSession();

        $requestedPath = $_REQUEST['path'] ?? '/';
        Path::validatePath($requestedPath); // Dies if not correct.
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
        } elseif (2 == $_REQUEST['i']) {
            // We're showing raw file view, such as for an img link.
            include('app/RawTemplate.php');
        } elseif (3 == $_REQUEST['i']) {
            // We're showing an inline search view, by choice.
            include('app/RawTemplate.php');
        } elseif (is_dir(static::$requestedPath)) {
            // We're showing an inline folder view; a list of thumbnails.
            include('app/ThumbsTemplate.php');
        } elseif (is_file(static::$requestedPath)) {
            // We're showing an inline file view.
            include('app/FileTemplate.php');
        } else {
            // We're showing an inline search view, because we've nothing else to show.
            include('app/SearchTemplate.php');
        }
    }

    /**
     * Display an error in the error box on the page.
     * @param string $string
     * @ToDo: use some kinda push tech to do this with Javascript.
     */
    public static function showError(string $string): void
    {
        echo $string;
    }
}
