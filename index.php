<?php
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
//use app\DropboxManager;

require_once __DIR__ . '/vendor/autoload.php';
require_once('app/Db.php');
require_once('app/Connection.php');
//require_once('app/DropboxManager.php');
$connection = new Connection();

// ToDo: better user input validation of this.
$path = $_REQUEST['path'] ?? '/';
$h_path = htmlspecialchars($path);
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
    <title>Midwest Memories</title>
    <meta charset="UTF-8">
  </head>
  <body>
    <h1><?= $h_path ?></h1>
    <?php

    ?>
  </body>
</html>
