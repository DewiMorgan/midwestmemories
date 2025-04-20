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
// ToDo: Add form input fields.
// ToDo: Add next/prev buttons.

// DELETEME DEBUG
$webDir = str_replace(Path::$imageBasePath, '', dirname(Index::$requestedPath));
$file = basename(Index::$requestedPath);
$webPath = $webDir . '/' . $file;
$fileDetails = Metadata::getFileDetails($webPath);
echo "<pre>$file file details:\n" . var_export($fileDetails, true) . "</pre>\n";
// END DELETEME DEBUG

$u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode(Path::filePathToWeb(Index::$requestedPath)) . '&amp;i=2';
echo "<img src=\"$u_linkUrl\" alt=\"TODO: alt text\">\n";

?>

</body>
</html>
