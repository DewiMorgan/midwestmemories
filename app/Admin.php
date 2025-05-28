<?php

declare(strict_types=1);

namespace MidwestMemories;

use JsonException;

/**
 * The class for the main Admin page.
 */
class Admin
{
    private static string $cursor;

    public function __construct()
    {
        static::handleLogouts();
        static::initSession();
        static::dieIfNotAdmin();

        try {
            static::getUserInput();
        } catch (JsonException) {
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
        $connection = Connection::getInstance();

        $_SESSION['login'] = 'true';
        $_SESSION['name'] = $_SERVER['PHP_AUTH_USER'];

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
        $connection = Connection::getInstance();

        if (!$connection->isAdmin) {
            die('Access denied');
        }
    }

    /**
     * Wrapper for debugging info. Likely to call a logging system in the future.
     * @param string $str The string to log.
     */
    private static function printAndLog(string $str): void
    {
        Log::debug($str);
        echo($str);
    }

    /** Parse the input from the admin form.
     * @throws JsonException
     */
    private static function getUserInput(): void
    {
        $dropbox = new DropboxManager();

        // Parse request params
        $cursor = $_REQUEST['cursor'] ?? '';
        $formAction = $_REQUEST['action'] ?? null;
        Log::debug("Starting. Cursor='$cursor', Request", $_REQUEST);

        self::getApiResponse($dropbox);

        // Handle web page calls. These are after the showHeader, so can print whatever we like.
        static::showHeader();
        switch ($formAction) {
            case 'handle_init_root':
                static::printAndLog("<h2>Handling init.</h2>\n");
                include(__DIR__ . '/AdminApiTemplate.php');
                break;
            case 'handle_list_users':
                static::printAndLog("<h2>Handling user list.</h2>\n");
                include(__DIR__ . '/AdminApiTemplate.php');
                break;
            case 'handle_queued_files':
                static::printAndLog("<h2>Handling queued files.</h2>\n");
                include(__DIR__ . '/AdminApiTemplate.php');
                break;
            default:
                static::printAndLog("<h2>No command yet given.</h2>\n");
                break;
        }

        echo '<p>Finished reading.</p>';
        if (empty($dropbox->cursor)) {
            echo 'Cursor was not set in the client.<br>';
            static::$cursor = '';
        } elseif (static::$cursor === $dropbox->cursor) {
            echo 'Cursor unchanged.<br>';
        } else {
            $cursor = $dropbox->cursor;
            echo "Cursor reassigned to '$cursor'.<br>";
        }
    }

    /**
     * Handle API calls. Nothing must be output before these, and they must not output anything.
     * Any list_* endpoints must return a list.
     * Any actions should return `$error ? "Error: $error" : 'OK';`.
     * @param DropboxManager $dropbox
     */
    public static function getApiResponse(DropboxManager $dropbox): void
    {
        $formAction = $_REQUEST['action'] ?? null;
        $username = $_REQUEST['username'] ?? '';
        $password = $_REQUEST['password'] ?? '';
        $users = new UserManager();

        $result = match ($formAction) {
            'update_dropbox_status' => $dropbox->readOneCursorUpdate(),
            'init_root' => $dropbox->initRootCursor(),
            'continue_root' => $dropbox->resumeRootCursor(),
            'list_files_to_download' => $dropbox->listFilesByStatus(DropboxManager::SYNC_STATUS_NEW),
            'list_files_to_process' => $dropbox->listFilesByStatus(DropboxManager::SYNC_STATUS_DOWNLOADED),
            'download_one_file' => $dropbox->downloadOneFile(),
            'process_one_file' => $dropbox->processOneFile(),
            'list_users' => $users->getUsers(),
            'add_user' => $users->addUser($username, $password),
            'change_password' => $users->changePassword($username, $password),
            default => '',
        };

        if ('' !== $result) {
            header('Content-Type: application/json');
            try {
                echo json_encode($result, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                echo '"Error: could not encode result"';
                Log::error("From $formAction, failed to json encode: " . $e->getMessage(), $result);
            }
            if (str_starts_with($formAction, 'list_')) {
                Log::debug('Returning list of ' . count($result) . ' items from $formAction.', $result);
            } else {
                Log::debug("From $formAction", $result);
            }
            exit(0);
        }
    }

    /**
     * Show the HTML page.
     * ToDo: make this a template.
     */
    private static function showHeader(): void
    {
        if (Connection::$pageStarted) {
            return;
        }
        Connection::$pageStarted = true;
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
            <button type="submit" name="action" value="handle_queued_files">Handle queued files (click me!)</button>
            <br>
            <button type="submit" name="action" value="handle_init_root">Replace root cursor (dangerous)</button>
        </form>
        <br>
        </body>
        </html>
        <?php
    }
}
