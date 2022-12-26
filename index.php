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

require_once('app/autoload.php');
use app\Db;
use app\Connection;
//use app\DropboxManager;

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
if (empty($path)) {
    include('app/TreeTemplate.php');
} elseif(str_ends_with($path, '/')) {
    echo "$path: should load app/ThumbsTemplate.php";
    // include('app/ThumbsTemplate.php');
} else {
    echo "$path: should load app/FileTemplate.php";
    // include('app/FileTemplate.php');
}
