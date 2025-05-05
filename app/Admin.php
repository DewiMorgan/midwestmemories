<?php

declare(strict_types=1);

namespace MidwestMemories;

use JsonException;

/**
 * The class for the main index.php file.
 */
class Admin
{

    private static string $cursor;
    private static int $entriesSoFar = 0;

    public function __construct()
    {
        static::handleLogouts();
        static::initSession();
        static::dieIfNotAdmin();

        try {
            static::getUserInput();
        } catch (JsonException $e) {
            die('["Error: Could not encode list"]');
        }
        static::showHeader();
        static::showContent();
    }

    /**
     * Handle logout requests.
     */
    private static function handleLogouts(): void
    {
        // Handle logouts.
        if (array_key_exists('logout', $_REQUEST) && $_REQUEST['logout'] && 'true' === $_SESSION['login']) {
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
     * Verify that we are only being accessed by an admin user.
     */
    private static function dieIfNotAdmin(): void
    {
        global $connection;

        if (!isset($connection) || !$connection->isAdmin) {
            die('Access denied');
        }
    }

    /**
     * Wrapper for debugging info. Likely to call a logging system in the future.
     * @param string $str The string to log.
     */
    private static function debug(string $str): void
    {
        Log::debug($str);
        echo($str);
    }

    /** Parse the input from the admin form.
     * @throws JsonException
     */
    private static function getUserInput(): void
    {
        // Parse all the params we can look for in the request.
        $cursor = $_REQUEST['cursor'] ?? '';
        $entriesSoFar = (int)($_REQUEST['entries_so_far'] ?? 0);
        $formAction = $_REQUEST['action'] ?? null;

        $fp = new DropboxManager();
        $list = [];

        Log::debug("Starting. Cursor='$cursor', Request", $_REQUEST);

        // Handle API calls. Nothing must be output before these, and they must not output anything.
        switch ($formAction) {
            case 'list_files_to_download':
                header('Content-Type: application/json');
                $list = $fp->listFilesByStatus(DropboxManager::SYNC_STATUS_NEW);
                Log::debug('Returning list of ' . count($list) . ' files to download.');
                echo json_encode($list, JSON_THROW_ON_ERROR);
                exit(0);
            case 'list_files_to_process':
                header('Content-Type: application/json');
                $list = $fp->listFilesByStatus(DropboxManager::SYNC_STATUS_DOWNLOADED);
                Log::debug('Returning list of ' . count($list) . ' files to process.');
                echo json_encode($list, JSON_THROW_ON_ERROR);
                exit(0);
            case 'download_one_file':
                header('Content-Type: application/json');
                Log::debug('Downloading one file from the DB queue...');
                $result = $fp->downloadOneFile();
                echo json_encode($result, JSON_THROW_ON_ERROR);
                exit(0);
            case 'process_one_file':
                Log::debug('Processing one file from the DB queue...');
                $result = $fp->processOneFile();
                echo json_encode($result, JSON_THROW_ON_ERROR);
                exit(0);
        }

        static::showHeader();

        // Handle web page calls. These are after the showHeader, so can print whatever we like.
        switch ($formAction) {
            case 'init_root':
                static::debug("<h2>Initializing root cursor</h2>\n");
                $list = $fp->initRootCursor();
                break;
            case 'continue_root':
                static::debug("<h2>Continuing with root cursor init</h2>\n");
                $list = $fp->resumeRootCursor($entriesSoFar);
                break;
            case 'check_cursor':
                static::debug("<h2>Checking cursor for updates...</h2>\n");
                $list = $fp->readCursorUpdate($entriesSoFar);
                break;
            case 'handle_queued_files':
                static::debug("<h2>Handling queued files.</h2>\n");
                include('AdminDownloadTemplate.php');
            /*
            case 'download_files':
                static::debug("<h2>Downloading files from the DB queue...</h2>\n");
                $numFiles = $fp->downloadFiles();
                $list = ['NumberOfFilesDownloaded' => $numFiles];
                break;
            case 'process_downloaded':
                static::debug("<h2>Processing downloaded files...</h2>\n");
                [$numFiles, $totalFiles] = $fp->processDownloads();
                $list = ['NumberOfFilesProcessed' => $numFiles, 'TotalFilesToProcess' => $totalFiles];
                break;
            */
            default:
                static::debug("<h2>No command yet given.</h2>\n");
                break;
        }

        $entriesChange = $fp->entries - static::$entriesSoFar;
        echo '<p>Finished reading.<br>';
        if (empty($fp->cursor)) {
            echo 'Cursor was not set in client.<br>';
            static::$cursor = '';
        } elseif (static::$cursor === $fp->cursor) {
            echo 'Cursor unchanged.<br>';
        } else {
            $cursor = $fp->cursor;
            echo "Cursor reassigned to '$cursor'.<br>";
        }
        echo "Iterations: $fp->iterations.<br>Entries: $fp->entries (+$entriesChange).</p>";
        echo '<pre>' . var_export($list, true) . '</pre>';
        static::$entriesSoFar = $fp->entries ?? static::$entriesSoFar;
    }

    /**
     * Show the HTML page.
     * ToDo: make this a template.
     */
    private static function showHeader(): void
    {
        global $connection;
        if ($connection->pageStarted) {
            return;
        }
        $connection->pageStarted = true;
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <link rel="shortcut icon" href="/favicon.ico">
            <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
            <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
            <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
            <link rel="icon" href="/favicon.ico">
            <link rel="manifest" href="/site.webmanifest">
            <title>Admin: Midwest Memories</title>
            <meta charset="UTF-8">
        </head>
        <body>
        <h1>Midwest Memories - admin</h1>
        <?php
    }

    /**
     * Show the admin page itself.
     * ToDo: make this a template.
     */
    private static function showContent(): void
    {
        ?>
        <form method="post">
            <button type="submit" name="action" value="init_root">Initialize new root cursor</button>
        </form>
        <br>
        <form method="post">
            <label>Entries:
                <input type="text" name="entries_so_far" value="<?= static::$entriesSoFar ?>">
            </label>
            <label>Cursor:
                <input type="text" name="cursor" value="<?= htmlspecialchars(static::$cursor) ?>">
            </label>
            <button type="submit" name="action" value="continue_root">Continue initializing root cursor</button>
        </form>
        <form method="post">
            <label>Entries:
                <input type="text" name="entries_so_far" value="<?= static::$entriesSoFar ?>">
            </label>
            <label>Cursor:
                <input type="text" name="cursor" value="<?= htmlspecialchars(static::$cursor) ?>">
            </label>
            <button type="submit" name="action" value="check_cursor">Get latest cursor updates into DB</button>
        </form>
        <form method="post">
            <!-- <button type="submit" name="action" value="download_files">Download files from DB queue</button>
            <br>
            <button type="submit" name="action" value="process_downloaded">Process downloaded files</button>
            <br> -->
            <button type="submit" name="action" value="handle_queued_files">Handle Queued files</button>
        </form>
        <br>
        </body>
        </html>
        <?php
    }
}
