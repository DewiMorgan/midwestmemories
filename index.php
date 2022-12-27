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

const MM_BASEURL = 'https://midwestmemories.dewimorgan.com';
define('IMAGEDIR', 'midwestmemories');
define('MM_BASEDIR', realpath(__DIR__ . '/' . IMAGEDIR . '/'));
if (empty(MM_BASEDIR)) {
    Db::adminDebug('MM_BASEDIR was empty. Not safe to continue.');
    http_response_code(500); // Internal Server Error.
    die();
}

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

$path = $_REQUEST['path'] ?? '/';
$h_path = htmlspecialchars($path);
$realPath = realpath(MM_BASEDIR . '/' . $path);
if (false === $realPath) {
    Db::adminDebug("Requested path was not found: $path");
    http_response_code(404); // Not found.
    die();
}
if (!str_starts_with($realPath, MM_BASEDIR)) {
    Db::adminDebug("Requested path was not within MM_BASEDIR: $path");
    http_response_code(404); // Not found.
    die();
}
$isInlineRequest = isset($_REQUEST['i']);

if (!$isInlineRequest) {
    // This is a request by a user, perhaps to a bookmark.
    // So, load the tree view, which will then call us back for the inline version of the the pointed-at $path resource.
    include('app/TreeTemplate.php');
} elseif (is_dir($realPath)) {
    // We're showing an inline folder view; a list of thumbnails.
    include('app/ThumbsTemplate.php');
} elseif (is_file($realPath)) {
    // ToDo: We're showing an inline file view.
    // include('app/FileTemplate.php');
    echo 'File view not yet implemented';
    http_response_code(501); // Not implemented.
} else {
    // ToDo: We're showing an inline search view.
    // include('app/FileTemplate.php');
    echo 'Search view not yet implemented';
    http_response_code(501); // Not implemented.
}
