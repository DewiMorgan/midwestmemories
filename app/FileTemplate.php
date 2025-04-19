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
echo '<p>requestedPath:\n</p><pre>Index::$requestedPath -> ' . var_export(Index::$requestedPath, true) . "</pre>\n";

$dir = dirname(Index::$requestedPath);
echo '<p>dir:\n</p><pre>$dir = dirname(Index::$requestedPath); -> ' . var_export($dir, true) . "</pre>\n";

$file = basename(Index::$requestedPath);
echo '<p>file:\n</p><pre>$file = basename(Index::$requestedPath); -> ' . var_export($file, true) . "</pre>\n";

$webDir = str_replace(Path::$imageBasePath, '', $dir);
echo '<p>webDir:\n</p><pre>$webDir = str_replace(Path::$imageBasePath, "", "$dir"); -> '
    . var_export($webDir, true) . "</pre>\n";

$metadata = new Metadata($webDir);
$metadata->loadFromInis();
echo '<p>metadata:\n</p><pre>$metadata = new Metadata($webDir); -> ' . var_export($metadata, true) . "</pre>\n";

$fileDetails = $metadata->getFileDetails($file);
echo "<pre>$dir(/)$file:\n" . var_export($fileDetails, true) . "</pre>\n";
// END DELETEME DEBUG

$u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode(Path::filePathToWeb(Index::$requestedPath)) . '&amp;i=2';
echo "<img src=\"$u_linkUrl\" alt=\"TODO: alt text\">\n";
?>

</body>
</html>
