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

$u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode(Path::filePathToWeb(Index::$requestUnixPath)) . '&amp;i=2';
echo "<img src=\"$u_linkUrl\" alt=\"TODO: alt text\">\n";

// DELETEME DEBUG
//echo '<hr><h3>Debugging stuff below this line</h3>';
//$webDir = str_replace(Path::$imageBasePath, '', dirname(Index::$requestedPath));
//$file = basename(Index::$requestedPath);
//$webFilePath = $webDir . '/' . $file;
//$fileDetails = Metadata::getFileDetails($webFilePath);
//echo "<pre>$file file details:\n" . var_export($fileDetails, true) . "</pre>\n";

//$fileDetails2 = Metadata::getFileDetails(str_replace(Path::$imageBasePath, '', Index::$requestedPath));
//echo "<pre>$file file details:\n" . var_export($fileDetails2, true) . "</pre>\n";

echo("Why strip '" . Path::$imageBasePath . "' off '" . Index::$requestUnixPath . "'?");
$file = '';

$fileDetails3 = Metadata::getFileDetails(Index::$requestUnixPath);
echo "<pre>$file file details:\n" . var_export($fileDetails3, true) . "</pre>\n";

// END DELETEME DEBUG
?>

</body>
</html>
