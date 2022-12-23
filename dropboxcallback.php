<?php
/* Callback for changes in DropBox. Only have 10 seconds to respond, so make it fast!
 */

// If it's a validation, validate and exit.
validate();
// Otherwise, log the query.
$data = logQuery();
// ToDo: this all seems silly. Make Connection a singleton, and hide it ALL behind a single autoloader.
use app\Db;
use app\Connection;
use app\DropboxManager;
require_once __DIR__ . '/vendor/autoload.php';
require_once('app/Db.php');
require_once('app/Connection.php');
require_once('app/DropboxManager.php');
Db::sqlExec("INSERT INTO `midmem_dropbox_users` (`user_id`, `cursor_id`, `webhook_timestamp`) VALUES (?, '', NOW()) ON DUPLICATE KEY UPDATE `webhook_timestamp` = NOW()", 'd', DropboxManager::DROPBOX_USER_ID);
$connection = new Connection();

// If it's a human, output readable text.
friendlyOutput($data);
// Tag the user's cursor as "dirty".

exit();

/**
 * Build a string to describe the inoming query, and log it.
 * @return string The data that was logged.
 * Note: MUST NOT echo anything to headers or stdout, or validate() will break.
 */
function logQuery(): string {
    $filename = 'callback.out';
    $timestamp = date('Y-m-d H:i:s: ');
    $data = "=== $timestamp ===\n";
    $data .= "REQUEST: " . var_export($_REQUEST, true) . "\n";
    $data .= "HEADERS: " . var_export(apache_request_headers(), true) . "\n";
    $data .= "BODY: " . file_get_contents('php://input') . "\n";
    $data .= "SERVER: " . var_export($_SERVER, true) . "\n";
    file_put_contents($filename, $data, FILE_APPEND);
    return $data;
}

/**
 * Validate that we are the script Dropbox wants to call.
 * Required for when we set up the callback, for it to verify us.
 * May not have any headers already sent.
 */
function validate(): void {
    if (array_key_exists('challenge', $_REQUEST)) {
        header('Content-Type: text/plain');
        header('X-Content-Type-Options: nosniff');
        echo $_REQUEST['challenge'];
        logQuery();
        exit;
    }
}

/**
 * Show a nice mesage for humans calling this page.
 * @param string $data String to display - probably the request dat that was logged.
 */
function friendlyOutput(string $data): void {
    $requestHeaders = apache_request_headers();
    if (array_key_exists('X-Dropbox-Signature', $requestHeaders)) {
        return;
    }
    ?>
    <!DOCTYPE html>
    <html>
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

/*
userid
cursor


/*
    Full request info of the callback is (Important parts prefixed w '*'):
    === 2022-12-20 03:09:56:  ===
    REQUEST: array (
    )
    HEADERS: array (
      'X-Forwarded-For' => '34.234.69.189, 172.31.5.213',
      'X-Forwarded-Proto' => 'https',
      'X-Forwarded-Port' => '443',
      'Host' => 'midwestmemories.dewimorgan.com',
      'X-Amzn-Trace-Id' => 'Root=1-63a12784-1ee4d34c723814fe6509a00c',
      'Content-Length' => '107',
      'X-Forwarded-Host' => 'midwestmemories.dewimorgan.com',
      'X-Forwarded-Server' => 'midwestmemories.dewimorgan.com',
      'X-Real-IP' => '34.234.69.189',
*     'User-Agent' => 'DropboxWebhooks/1.0',
      'Accept' => '* /*',
      'Accept-Encoding' => 'gzip,deflate',
      'X-Dropbox-Signature' => 'e1e3fcf08b883f795b2db4bb941260f49c5700d0bc4bd0931654933db31f929b',
*     'Content-Type' => 'application/json',
    )
*   BODY: {"delta": {"users": [16181197]}, "list_folder": {"accounts": ["dbid:AAADKhvkNzHDp5Zp3O9UOLVlwrFGl4p7Yss"]}}
    SERVER: array (
      'PATH' => '/usr/local/bin:/bin:/usr/bin',
      'HTTP_ACCEPT' => '* /*',
      'HTTP_ACCEPT_ENCODING' => 'gzip,deflate',
*     'CONTENT_TYPE' => 'application/json',
      'CONTENT_LENGTH' => '107',
      'HTTP_HOST' => 'midwestmemories.dewimorgan.com',
*     'HTTP_USER_AGENT' => 'DropboxWebhooks/1.0',
      'HTTP_X_FORWARDED_FOR' => '34.234.69.189, 172.31.5.213',
      'HTTP_X_FORWARDED_PROTO' => 'https',
      'HTTP_X_FORWARDED_PORT' => '443',
      'HTTP_X_AMZN_TRACE_ID' => 'Root=1-63a12784-1ee4d34c723814fe6509a00c',
      'HTTP_X_FORWARDED_HOST' => 'midwestmemories.dewimorgan.com',
      'HTTP_X_FORWARDED_SERVER' => 'midwestmemories.dewimorgan.com',
      'HTTP_X_REAL_IP' => '34.234.69.189',
      'HTTP_X_DROPBOX_SIGNATURE' => 'e1e3fcf08b883f795b2db4bb941260f49c5700d0bc4bd0931654933db31f929b',
      'DOCUMENT_ROOT' => '/data0/ulixamvtuwwyaykg/public_html/midwestmemories',
      'REMOTE_ADDR' => '34.234.69.189',
      'REMOTE_PORT' => '44240',
      'SERVER_ADDR' => '172.31.43.45',
      'SERVER_NAME' => 'midwestmemories.dewimorgan.com',
      'SERVER_ADMIN' => 'webmaster@midwestmemories.dewimorgan.com',
      'SERVER_PORT' => '443',
      'REQUEST_SCHEME' => 'https',
      'REQUEST_URI' => '/dropboxcallback.php',
      'PROXY_REMOTE_ADDR' => '172.31.34.26',
      'HTTPS' => 'on',
      'SSL_PROTOCOL' => 'TLSv1.2',
      'SSL_CIPHER' => 'ECDHE-RSA-AES256-GCM-SHA384',
      'SSL_CIPHER_USEKEYSIZE' => '256',
      'SSL_CIPHER_ALGKEYSIZE' => '256',
      'SCRIPT_FILENAME' => '/data0/ulixamvtuwwyaykg/public_html/midwestmemories/dropboxcallback.php',
      'QUERY_STRING' => '',
      'SCRIPT_URI' => 'https://midwestmemories.dewimorgan.com/dropboxcallback.php',
      'SCRIPT_URL' => '/dropboxcallback.php',
      'SCRIPT_NAME' => '/dropboxcallback.php',
      'SERVER_PROTOCOL' => 'HTTP/1.1',
      'SERVER_SOFTWARE' => 'LiteSpeed',
*     'REQUEST_METHOD' => 'POST',
      'X-LSCACHE' => 'on',
      'PHP_SELF' => '/dropboxcallback.php',
      'REQUEST_TIME_FLOAT' => 1671505796.359852,
      'REQUEST_TIME' => 1671505796,
    )
*/
