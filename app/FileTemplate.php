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
$dir = dirname(Index::$requestedPath);
$file = basename(Index::$requestedPath);
$metadata = new Metadata($dir);
$metadata->loadFromIni();
$fileDetails = $metadata->getFileDetails($file);
echo "<pre>$dir(/)$file:\n" . var_export($fileDetails, true) . "</pre>\n";
// END DELETEME DEBUG

$u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode(Path::filePathToWeb(Index::$requestedPath)) . '&amp;i=2';
echo "<img src=\"$u_linkUrl\" alt=\"TODO: alt text\">\n";
?>

</body>
</html>