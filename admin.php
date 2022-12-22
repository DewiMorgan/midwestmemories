<?php
date_default_timezone_set("US/Central");
session_start();


// Handle logouts.
if(array_key_exists('logout', $_REQUEST) && $_REQUEST['logout'] && 'true' == $_SESSION['login']){
    header("HTTP/1.1 401 Unauthorized");
    $_SESSION['login'] = 'false';
    echo "<!DOCTYPE html>\n";
    echo '<html><head><title>Logout</title><head><body><h1>Logged out</h1><p><a href="' . $_SERVER['PHP_SELF'] . '">Click here to log back in.</a></p></body></html>'."\n";
    exit;
}
$_SESSION['login'] = 'true';
$_SESSION['name'] = $_SERVER['PHP_AUTH_USER'];

use app\Db;
use app\Connection;
use app\DropboxManager;

require_once __DIR__ . '/vendor/autoload.php';
require_once('app/Db.php');
require_once('app/Connection.php');
require_once('app/DropboxManager.php');
$connection = new Connection();

// Log this login.
Db::sqlExec(
    'INSERT INTO midmem_visitors (`request`, `main_ip`, `all_ips_string`, `user`, `agent`) VALUES (?, ?, ?, ?, ?)',
    'sssss',
    $connection->request, $connection->ip,  $connection->ipList, $connection->user, $connection->agent
);
?>
<!DOCTYPE html>
<html>
  <head>
    <link rel="shortcut icon" href ="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" href ="/favicon.ico">
    <link rel="manifest" href="/site.webmanifest">
    <title>Admin: Midwest Memories</title>
    <meta charset="UTF-8">
  </head>
  <body>
    <h1>Midwest Memories - admin</h1>
    <?php
    $cursor = $_REQUEST['cursor'] ?? 'AAGEBZi4qpzGvbL2PyF-FGkAj4kG8Lh-c4S9yv_vOIJYTdIzzcBoJ-0kMxCtgpz3v2OxNFbjCb4rQcHRCeGTuUR8bDORsfOqmI04YMfpFjL5V4ZZ4rTbiqAxyJh040tXFj0KccZVfj3s3ONr8CCaiICo0xRGDpUdSpqUj94gHuorl5GcPZImSec6F9CS9L_dEq5jTHSv1k2Pblu9SbEjYruikIuNlkQcLQB4z9vhi2HN6dcVNBeEWCgZ4Q5T5_0Qy1IeAZvmifMMIZsdpQ1gsEfRUerfwu3SPyV--57wWyCjPEeCG0Xq8iInmFFQPFjORbmEvi6dZgCIyD5SliAL94YR89Oq3c_VT6aJQkWQX8ffvjGxOGSvWHMD-uo0IJcmIZkOQy4MoYZnhTJWnPM5cfk1c8DifGY67boVQ4uYnNRToftg7liJlp5PDHV7U0ebrbpsNfMOLRar6np16GS4OHwXZaKjnQPSu_Kn3sY1gj93aD8c265wGP8B0_XA6rZELhb1d30Elml7eb4VrrfKAI2iMWY3Q71Jj8eXLgV40uU1BdW9RYLg3nTa8HrfuGffFUsCZRNDIJbTyCqJGLADJoYseJRH5MC3xUtZ0_EoJUPqkPGP7VPjyVh9ReFkrfh3xO36hQ0dZGp90Eu6wL9wsyrn6s0jI5MnBB24aIhFlhu698gb_-BX0p2kUF-aHgaFn-4s7ze0Wj-_QkNYYNK8h_Fbto51u1WKaMoxEgH7oEdh68dq8bBDBM-pEKjA7lBL7GXJHo60wTRRMfuqViKPZmowSOReztH9VH0HVmX3wCUQPDUqdPa8sDJbAdfU4aVk8mEhP-ew-4Xwsn1zEJ8wFLfRg0OsNQTXAaytsZWKvYxzfM3lYJE5LmkYcUMKZQpWMvE';
    $processFiles = $_REQUEST['processfiles'] ?? false;
    $initRoot = $_REQUEST['initroot'] ?? false;
    $continueRoot = $_REQUEST['continueroot'] ?? false;
    $entriessofar = intval($_REQUEST['entriessofar'] ?? 0 );
    echo "<p>Starting. Cursor='$cursor', Request=".var_export($_REQUEST,true)."</p>";
    $fp = new DropboxManager();
    $list = [];
    if($initRoot) {
        echo "<h2>Initializing root cursor</h2>\n";
        $list = $fp->initRootCursor();
    } elseif($continueRoot) {
        echo "<h2>Continuing with the root cursor</h2>\n";
        $list = $fp->continueRootCursor($entriessofar);
    } elseif($processFiles) {
        echo "<h2>Processing files from the DB...</h2>\n";
        $fp->processFilesFromDb();
    } else {
        echo "<h2>No command yet given.</h2>\n";
    }
    $entriesChange = $fp->entries - $entriessofar;
    echo "<p>Finished reading.<br>";
    if ($cursor == $fp->cursor) {
        echo "Cursor unchanged.<br>";
    } elseif (empty($fp->cursor)) {
        echo "Cursor was not set in client.<br>";
    } else {
        $cursor = $fp->cursor;
        echo "Cursor reassigned to '$cursor'.<br>";
        $fp->saveCursor();
        echo "Cursor saved to DB.<br>";
    }
    echo "Iterations: {$fp->iterations}.<br>Entries: {$fp->entries} (+$entriesChange).</p>";
    echo "<pre>" . var_export($list, true) . "</pre>";
    $entriessofar = $fp->entries ?? $entriessofar;
    ?>
    <form method="post">
        <input type="hidden" name="processfiles" value="1"></input>
        <button type="submit">Process files from DB</button>
    </form><br>
    <form method="post">
        <input type="hidden" name="initroot" value="1"></input>
        <button type="submit">Initialize new root cursor</button>
    </form><br>
    <form method="post">
        <input type="text" name="entriessofar" value="<?=htmlspecialchars($entriessofar)?>"></input>
        <input type="text" name="cursor" value="<?=htmlspecialchars($cursor)?>"></input>
        <input type="hidden" name="continueroot" value="1"></input>
        <button type="submit">Continue initializing root cursor, or get latest updates</button>
    </form>
  </body>
</html>
