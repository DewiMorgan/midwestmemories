<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * The class for the main Admin page.
 */
class Admin
{
    public function __construct()
    {
        // Auth and session management. Must not output anything.
        static::initSession();
        static::dieIfNotAdmin();
        static::showAdminTemplate();
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
            'INSERT INTO `' . Db::TABLE_VISITORS . '` (`request`, `main_ip`, `all_ips_string`, `user`, `agent`)'
            . ' VALUES (?, ?, ?, ?, ?)',
            'sssss',
            $connection->request,
            $connection->ip,
            $connection->ipList,
            $connection->usernameGuesses,
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
        <h1>Midwest Memories -
            <?= Connection::getInstance()->isSuperAdmin ? 'SuperAdmin' : 'Admin'; ?>
            (<?= $_SESSION['name'] ?>)
        </h1>
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
        <button onclick="initializeCursor()">Initialize Cursor</button>
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
