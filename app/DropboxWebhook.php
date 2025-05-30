<?php

declare(strict_types=1);

namespace MidwestMemories;

use JetBrains\PhpStorm\NoReturn;
use JsonException;

/**
 * Webhook Callback for changes in DropBox. Only have 10 seconds to respond, so make it fast!
 * https://www.dropbox.com/developers/reference/webhooks has more info.
 */
class DropboxWebhook
{
    #[NoReturn] public function __construct()
    {
        // If it is a validation, validate and exit.
        self::validate();

        if (array_key_exists('X-Dropbox-Signature', apache_request_headers())) {
            // Tag the user's cursor as "dirty".
            self::recordHook();
        } else {
            // If caller is human, output readable text.
            self::friendlyOutput();
        }

        exit(0);
    }

    /**
     * Build a string to describe the incoming query, and log it.
     * @return string The data that was logged.
     * Note: MUST NOT echo anything to headers or stdout, or validate() will break.
     */
    private static function logQuery(): string
    {
        $filename = 'callback.out';
        $timestamp = date('Y-m-d H:i:s: ');
        $data = "=== $timestamp ===\n";
        $data .= 'REQUEST: ' . var_export($_REQUEST, true) . "\n";
        $data .= 'HEADERS: ' . var_export(apache_request_headers(), true) . "\n";
        $data .= 'BODY: ' . file_get_contents('php://input') . "\n";
        $data .= 'SERVER: ' . var_export($_SERVER, true) . "\n";
        file_put_contents($filename, $data, FILE_APPEND);
        return $data;
    }

    /**
     * Validate that we are the script Dropbox wants to call.
     * Required for when we set up the callback, for it to verify us.
     * May not have any headers already sent.
     */
    private static function validate(): void
    {
        if (array_key_exists('challenge', $_REQUEST)) {
            header('Content-Type: text/plain');
            header('X-Content-Type-Options: nosniff');
            echo $_REQUEST['challenge'];
            self::logQuery();
            exit(0);
        }
    }

    /** Record the timestamp that this hook was called in the DB.
     */
    private static function recordHook(): void
    {
        $body = file_get_contents('php://input');
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            echo $e->getMessage();
            die("Fatal error. Couldn't json_decode the body.");
        }

        if ($body && $decoded) {
            if (array_key_exists('delta', $decoded)
                && array_key_exists('users', $decoded['delta'])
                && count($decoded['delta']['users']) > 0
            ) {
                foreach ($decoded['delta']['users'] as $userId) {
                    Db::sqlExec(
                        "
                        INSERT INTO `midmem_dropbox_users` (`user_id`, `cursor_id`, `webhook_timestamp`)
                        VALUES (?, '', NOW())
                        ON DUPLICATE KEY UPDATE `webhook_timestamp` = NOW()",
                        'd',
                        $userId
                    );
                }
            }
        } else {
            // On failure, we set the webhook timestamp to now(). I don't know why.
            Db::sqlExec('UPDATE `midmem_dropbox_users` SET `webhook_timestamp` = NOW() WHERE 1');
        }
    }

    /** Show a nice message for humans calling this page. */
    private static function friendlyOutput(): void
    {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
            <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
            <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
            <link rel="manifest" href="/site.webmanifest">
            <title>Midwest Memories</title>
            <meta charset="UTF-8">
        </head>
        <body>
        <h1>Midwest Memories - callback</h1>
        <pre>
            <?php
            $data = self::logQuery();
            $h_data = htmlspecialchars($data);
            echo $h_data;
            ?>
        </pre>
        </body>
        </html>
        <?php
    }
}
