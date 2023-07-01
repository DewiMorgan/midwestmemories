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
<h1 class="center">Folder title goes here</h1>
<p>This is a description of the folder/album and its contents.</p>


<?php
// ToDo: Style this.
// ToDo: Add edit button.
// ToDo: Add for input fields.
$dir = dirname(Index::$requestedPath);
$file = basename(Index::$requestedPath);
$metadata = new Metadata($dir);
$fileDetails = $metadata->getFileDetails($file);
echo "<pre>$dir(/)$file:\n" . var_export($fileDetails, true) . "</pre>\n";

$u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode(Path::filePathToWeb(Index::$requestedPath)) . '&amp;i=2';
echo "<img src=\"$u_linkUrl\" alt=\"TODO: alt text\">\n";
?>

</body>
</html>