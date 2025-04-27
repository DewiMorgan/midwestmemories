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
<?php
// ToDo: Style this.
// ToDo: Add edit button.
// ToDo: Add form input fields.
// ToDo: Add next/prev buttons.

$u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode(Path::filePathToWeb(Index::$requestUnixPath)) . '&amp;i=2';
$fileDetails = Metadata::getFileDataByUnixPath(Index::$requestUnixPath);

// Escape the whole details array.
$h_fd = cleanFileDetails($fileDetails);

// Special cases.
$h_slide = $h_fd['slideorigin'] . ':' . $h_fd['slidenumber'] . ':' . $h_fd['slidesubsection'];
$h_altText = $h_fd['displayname'];
?>
<h1 class="center"><?= $h_fd['displayName'] ?></h1>
<p><?= $h_fd['writtennotes'] ?></p>


<img src="<?= $u_linkUrl ?>" alt="<?= $h_altText ?>">
<table>
    <tr>
        <td>Name:</td>
        <td><?= $h_fd['displayName'] ?></td>
    </tr>
    <tr>
        <td>Slide:</td>
        <td><?= $h_slide ?></td>
    </tr>
    <tr>
        <td>Date:</td>
        <td><?= $h_fd['date'] ?></td>
    </tr>
    <tr>
        <td>Notes:</td>
        <td><?= $h_fd['writtennotes'] ?></td>
    </tr>
    <tr>
        <td>Location:</td>
        <td><?= $h_fd['location'] ?></td>
    </tr>
    <tr>
        <td>Photographer:</td>
        <td><?= $h_fd['photographer'] ?></td>
    </tr>
    <tr>
        <td>People:</td>
        <td><?= $h_fd['people'] ?></td>
    </tr>
    <tr>
        <td>Keywords:</td>
        <td><?= $h_fd['keywords'] ?></td>
    </tr>
    <tr>
        <td>Visitor Notes:</td>
        <td><?= $h_fd['visitornotes'] ?></td>
    </tr>
</table>

<!--
<form>
    <label>
        <input type="text">
    </label>
</form>
-->
<?php
// DELETEME DEBUG
echo '<hr><h3>Debugging stuff below this line</h3>';

$u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode(Index::$requestUnixPath) . '&amp;i=2';
?><img src="<?= $u_linkUrl ?>" alt="<?= $h_altText ?>"><br><?= $h_fd['displayName'] ?><?php

echo '<pre>' . basename(Index::$requestUnixPath) . " file details:\n" . var_export($fileDetails, true) . "</pre>\n";


// END DELETEME DEBUG

/**
 * Convert the raw file details into a HTML escaped version.
 * @param array $fileDetails Array from which to html escape all fields.
 * @return array The resulting escaped array.
 */
function cleanFileDetails(array $fileDetails): array {
    $h_fd = [];
    foreach ($fileDetails as $key => $fileDetail) {
        if (is_array($fileDetail)) {
            if ('date' === $key) {
                $h_fd[$key] = htmlspecialchars($fileDetail['dateString']);
                echo 'Using dateString ' . var_export($fileDetail, true) . ' to ' . var_export($h_fd[$key], true) . "<br>\n"; // DELETEME DEBUG
            } else {
                $fileDetail = htmlspecialchars(implode(', ', $fileDetail));
                echo 'Converting array ' . var_export($fileDetail, true) . ' to ' . var_export($h_fd[$key], true) . "<br>\n"; // DELETEME DEBUG
            }
        } elseif (is_numeric($fileDetail)) {
            $h_fd[$key] = $fileDetail;
            echo 'Setting number ' . var_export($fileDetail, true) . ' to ' . var_export($h_fd[$key], true) . "<br>\n"; // DELETEME DEBUG
        } elseif (is_string($fileDetail) && strlen($fileDetail)) {
            $h_fd[$key] = htmlspecialchars($fileDetail);
            echo 'Escaping valid ' . var_export($fileDetail, true) . ' to ' . var_export($h_fd[$key], true) . "<br>\n"; // DELETEME DEBUG
        } else {
            $h_fd[$key] = match ($key) {
                'slideorigin', 'slidenumber', 'slidesubsection' => '?',
                'displayname' => 'unknown image',
                default => 'unknown',
            };
            echo 'Defaulting empty ' . var_export($fileDetail, true) . ' to ' . var_export($h_fd[$key], true) . "<br>\n"; // DELETEME DEBUG
        }
    }
    return $h_fd;
}

?>

</body>
</html>
