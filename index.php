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

// Log this login.
Db::sqlExec(
    'INSERT INTO midmem_visitors (`request`, `main_ip`, `all_ips_string`, `user`, `agent`) VALUES (?, ?, ?, ?, ?)',
    'sssss',
    $connection->request, $connection->ip,  $connection->ipList, $connection->user, $connection->agent
);

define('BASEDIR', reapath(__DIR__ . '/midwestmemories/'));
if (empty(BASEDIR)) {
    Db::adminDebug("BASEDIR was empty. Not safe to continue.");
    http_response_code(500); // Internal Server Error.
    die();
}
$path = $_REQUEST['path'] ?? '/';
$h_path = htmlspecialchars($path);
$realPath = realpath(BASEDIR . '/' . $path);
if (!str_starts_with($realPath, BASEDIR)) {
    Db::adminDebug("Requested path was not valid: $path");
    http_response_code(501); // Not implemented.
    die();
}

if ('/' == $path) {
    include('app/TreeTemplate.php'); // Temporary: this template should get merged in with the thumbs one.
} elseif(str_ends_with($path, '/')) {
    echo "$path: should load app/ThumbsTemplate.php";
    // include('app/ThumbsTemplate.php');
} else {
    echo "$path: should load app/FileTemplate.php";
    // include('app/FileTemplate.php');
}
