<?php

declare(strict_types=1);

namespace MidwestMemories;

/**
 * Template to display a single file and its details.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>File</title>
</head>
<body>
<h1 class="center">File title goes here</h1>
<p>This is a description of the file.</p>


<?php
// ToDo: Style this.
// ToDo: Add edit button.
// ToDo: Add for input fields.
// DELETEME DEBUG
echo '<pre>Index::$requestedPath -> ' . var_export(Index::$requestedPath, true) . "</pre>\n";

$dir = dirname(Index::$requestedPath);
echo '<pre>$dir = dirname(Index::$requestedPath); -> ' . var_export($dir, true) . "</pre>\n";

$file = basename(Index::$requestedPath);
echo '<pre>$file = basename(Index::$requestedPath); -> ' . var_export($file, true) . "</pre>\n";

$webDir = str_replace(Path::$imageBasePath, '', $dir);
echo '<pre>$webDir = str_replace(Path::$imageBasePath, "", "$dir"); -> ' . var_export($webDir, true) . "</pre>\n";

$metadata = new Metadata($webDir);
$metadata->loadFromInis();
echo "<p>metadata before printed</p>\n";
echo '<pre>$metadata = new Metadata($webDir); -> ' . var_export($metadata, true) . "</pre>\n";
echo "<p>metadata printed</p>\n";

$fileDetails = $metadata->getFileDetails($file);
echo "<p>getFileDetails completed</p>\n";
echo "<pre>$dir(/)$file:\n" . var_export($fileDetails, true) . "</pre>\n";
// END DELETEME DEBUG

$u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode(Path::filePathToWeb(Index::$requestedPath)) . '&amp;i=2';
echo "<img src=\"$u_linkUrl\" alt=\"TODO: alt text\">\n";
?>

</body>
</html>
