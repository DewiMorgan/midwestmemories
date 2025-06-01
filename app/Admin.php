<?php

declare(strict_types=1);

namespace MidwestMemories;

use JsonException;

/**
 * The class for the main Admin page.
 */
class Admin
{
    public function __construct()
    {
        // Auth and session management. Must not output anything.
        static::handleLogouts();
        static::initSession();
        static::dieIfNotAdmin();

        // Handle as JSON API call if one is requested: default to web page request.
        $formAction = $_REQUEST['action'] ?? null;
        if ($formAction) {
            self::getApiResponse($formAction);
        } else {
            static::showAdminTemplate();
        }
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

        // Log this login. No error handling if we fail.
        // ToDo: this is very low level, and duplicated code. Should probably be wrapped in a connectionLogger.
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
     * Handle API calls. Nothing must be output before these, and they must not output anything.
     * Any list_* endpoints must return a list.
     * Any actions should return `$error ? "Error: $error" : 'OK';`.
     * @param string $formAction
     */
    public static function getApiResponse(string $formAction): void
    {
        Log::debug("Action", $formAction);
        $result = match ($formAction) {
            'init_root' => DropboxManager::getInstance()->initRootCursor(),
            'continue_root' => DropboxManager::getInstance()->readCursorUpdate(),
            'list_files_to_download' => FileProcessor::getInstance()->listFilesByStatus(
                SyncStatus::NEW
            ),
            'list_files_to_process' => FileProcessor::getInstance()->listFilesByStatus(
                SyncStatus::DOWNLOADED
            ),
            'download_one_file' => FileProcessor::getInstance()->downloadOneFile(),
            'process_one_file' => FileProcessor::getInstance()->processOneFile(),
            'list_users' => UserManager::getInstance()->getUsers(),
            'add_user' => UserManager::getInstance()->addUser(
                $_REQUEST['username'] ?? '',
                $_REQUEST['password'] ?? ''
            ),
            'change_password' => UserManager::getInstance()->changePassword(
                $_REQUEST['username'] ?? '',
                $_REQUEST['password'] ?? ''
            ),
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
                Log::debug('Returning list of ' . count($result) . " items from $formAction.", $result);
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
    private static function showAdminTemplate(): void
    {
        echo "<!DOCTYPE html>\n"; // As an echo to prevent any leading whitespace.
        ?>
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
            <style>
                #messages {
                    max-height: calc(1em * 25); /* Adjust line height if needed */
                    line-height: 1.2; /* Tighter line spacing */
                    overflow-y: auto;
                    font-family: monospace; /* Optional for consistent line height */
                    white-space: pre-wrap;
                }

                #messages p {
                    margin: 0; /* Remove default vertical spacing */
                    padding: 0; /* Optional: remove padding if applied */
                }
            </style>
        </head>
        <body>
        <h1>Midwest Memories - admin (<?= $_SESSION['name'] ?>)</h1>
        <h2>Users</h2>
        <div id="user-list"></div>
        <hr>

        <h2>Background Task Output</h2>
        <div id="messages"></div>
        <br>
        <input type="checkbox" id="autoscroll" name="autoscroll" checked>
        <label for="autoscroll">Auto scroll</label><br>
        <hr>

        <h2>Admin Actions</h2>
        <ul>
            <li><a href="/admin.php?user-action=handle_init_root">Re-get missed files (slow, rarely needed)</a></li>
        </ul>
        <br>
        <?php
        // ToDo: turn this into a script tag. Not yet, though, as including it avoids problems with script caching.
        include(__DIR__ . '/AdminApiTemplate.php');
        ?>
        </body>
        </html>
        <?php
    }
}
