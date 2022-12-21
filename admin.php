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
use app\InitDropbox;

require_once __DIR__ . '/vendor/autoload.php';
require_once('app/Db.php');
require_once('app/Connection.php');
require_once('app/InitDropbox.php');
$db = new Db();
$connection = new Connection();

// Log this login.
$db->sqlExec(
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
    <title>Midwest Memories</title>
    <meta charset="UTF-8">
  </head>
  <body>
    <h1>Midwest Memories - admin</h1>
    <?php
    global $client;
    $cursor = $_REQUEST['cursor'] ?? null;
    $fp = new InitDropbox();
    if ($cursor) {
        echo "<h2>Updates from cursor</h2>\n";
        $list = $fp->getUpdates($client, $cursor);
    } else {
        echo "<h2>Full List</h2>\n";
        $list = $fp->getRecursiveList($client);
    }
    $cursor = $fp->cursor ?? $cursor;

    echo "<pre>" . var_export($list, true) . "</pre>";

    ?>
        <form method="post">
            <input type="text" name="cursor" value="<?=htmlspecialchars($cursor)?>"></input>
            <button type="submit">Full List</button>
        </form>
        <form method="post">
            <button type="submit">Updates Only</button>
        </form>
  </body>
</html>
