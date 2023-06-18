<?php
declare(strict_types=1);
/**
 * Callback for changes in DropBox. Only have 10 seconds to respond, so make it fast!
 * ToDo: refactor to a class, and move the class into the app/ folder.
 */

namespace MidwestMemories;
date_default_timezone_set('US/Central');
require_once('app/autoload.php');

// If it's a validation, validate and exit.
validate();

// Otherwise, log the query.
use MidwestMemories\Db;

require_once('app/Db.php');

// Tag the user's cursor as "dirty".
recordHook();

// If caller is human, output readable text.
friendlyOutput();

exit();


/**
 * Build a string to describe the incoming query, and log it.
 * @return string The data that was logged.
 * Note: MUST NOT echo anything to headers or stdout, or validate() will break.
 */
function logQuery(): string
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
function validate(): void
{
    if (array_key_exists('challenge', $_REQUEST)) {
        header('Content-Type: text/plain');
        header('X-Content-Type-Options: nosniff');
        echo $_REQUEST['challenge'];
        logQuery();
        exit;
    }
}

/** Record the timestamp that this hook was called in the DB. */
function recordHook(): void
{
    $body = file_get_contents('php://input');
    if ($body && $decoded = json_decode($body, true)) {
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
        Db::sqlExec('UPDATE `midmem_dropbox_users` SET `webhook_timestamp` = NOW() WHERE 1');
    }
}

/**
 * Show a nice message for humans calling this page.
 */
function friendlyOutput(): void
{
    $requestHeaders = apache_request_headers();
    if (array_key_exists('X-Dropbox-Signature', $requestHeaders)) {
        return;
    }
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
        $data = logQuery();
        $h_data = htmlspecialchars($data);
        echo $h_data;
        ?>
    </pre>
    </body>
    </html>
    <?php
}
