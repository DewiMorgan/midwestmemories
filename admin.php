<?php
/**
 * Management page for admins.
 * ToDo:
 *  Chain all these processes up from the web hook handler, using a single timeout time.
 *  Maybe have them re-trigger each other or something.
 *  Maybe a web cron to hit the webhook? Or does cpanel allow cron jobs? Edit crontab manually?
 */

declare(strict_types=1);
date_default_timezone_set('US/Central');
session_start();

// Handle logouts.
if(array_key_exists('logout', $_REQUEST) && $_REQUEST['logout'] && 'true' == $_SESSION['login']){
    header('HTTP/1.1 401 Unauthorized');
    $_SESSION['login'] = 'false';
    echo "<!DOCTYPE html>\n";
    echo '<html lang="en"><head><title>Logout</title></head><body><h1>Logged out</h1><p><a href="'
        . $_SERVER['PHP_SELF'] . '">Click here to log back in.</a></p></body></html>'."\n";
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
<html lang="en">
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
    $cursor = $_REQUEST['cursor'] ?? '';
    $initRoot = $_REQUEST['init_root'] ?? false;
    $continueRoot = $_REQUEST['continue_root'] ?? false;
    $checkCursor = $_REQUEST['check_cursor'] ?? false;
    $processFiles = $_REQUEST['download_files'] ?? false;
    $processDownloads = $_REQUEST['process_downloaded'] ?? false;
    $entriesSoFar = intval($_REQUEST['entries_so_far'] ?? 0 );

    echo "<p>Starting. Cursor='$cursor', Request=".var_export($_REQUEST,true). '</p>';
    $fp = new DropboxManager();
    $list = [];
    if($initRoot) {
        echo "<h2>Initializing root cursor</h2>\n";
        $list = $fp->initRootCursor();
    } elseif($continueRoot) {
        echo "<h2>Continuing with root cursor init</h2>\n";
        $list = $fp->resumeRootCursor($entriesSoFar);
    } elseif($checkCursor) {
        echo "<h2>Checking cursor for updates...</h2>\n";
        $list = $fp->readCursorUpdate($entriesSoFar);
    } elseif($processFiles) {
        echo "<h2>Downloading files from the DB queue...</h2>\n";
        $numFiles = $fp->downloadFiles();
        $list = ['NumberOfFilesDownloaded' => $numFiles];
    } elseif($processDownloads) {
        echo "<h2>Processing downloaded files...</h2>\n";
        $numFiles = $fp->processDownloads();
        $list = ['NumberOfFilesProcessed' => $numFiles];
    } else {
        echo "<h2>No command yet given.</h2>\n";
    }
    $entriesChange = $fp->entries - $entriesSoFar;
    echo '<p>Finished reading.<br>';
    if ($cursor == $fp->cursor) {
        echo 'Cursor unchanged.<br>';
    } elseif (empty($fp->cursor)) {
        echo 'Cursor was not set in client.<br>';
    } else {
        $cursor = $fp->cursor;
        echo "Cursor reassigned to '$cursor'.<br>";
    }
    echo "Iterations: $fp->iterations.<br>Entries: $fp->entries (+$entriesChange).</p>";
    echo '<pre>' . var_export($list, true) . '</pre>';
    $entriesSoFar = $fp->entries ?? $entriesSoFar;
    ?>
    <form method="post">
        <input type="hidden" name="init_root" value="1">
        <button type="submit">Initialize new root cursor</button>
    </form><br>
    <form method="post">
        <label>Entries:
            <input type="text" name="entries_so_far" value="<?= $entriesSoFar ?>">
        </label>
        <label>Cursor:
            <input type="text" name="cursor" value="<?=htmlspecialchars($cursor)?>">
        </label>
        <input type="hidden" name="continue_root" value="1">
        <button type="submit">Continue initializing root cursor</button>
    </form>
    <form method="post">
        <label>Entries:
            <input type="text" name="entries_so_far" value="<?= $entriesSoFar ?>">
        </label>
        <label>Cursor:
            <input type="text" name="cursor" value="<?=htmlspecialchars($cursor)?>">
        </label>
        <input type="hidden" name="check_cursor" value="1">
        <button type="submit">Get latest cursor updates into DB</button>
    </form>
    <form method="post">
        <input type="hidden" name="download_files" value="1">
        <button type="submit">Download files from DB queue</button>
    </form><br>
    <form method="post">
        <input type="hidden" name="process_downloaded" value="1">
        <button type="submit">Process downloaded files</button>
    </form><br>
  </body>
</html>
