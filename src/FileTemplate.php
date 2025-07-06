<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

/**
 * Template to display a single file and its details.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>ToDo: a file title here</title>
</head>
<body>
<div id="template-content">
    <?php
    // ToDo: Style this.
    // ToDo: Add page title.
    // ToDo: Add edit button.
    // ToDo: Add form input fields.
    // ToDo: Add next/prev buttons.

    $u_linkUrl = Path::unixPathToUrl(IndexGateway::$requestUnixPath, Path::LINK_RAW);
    $fileDetails = Metadata::getEscapedByUnixPath(IndexGateway::$requestUnixPath);

    // Escape the details array.
    $h_fd = Metadata::htmlEscape($fileDetails);

    // Special cases.
    $h_slide = ($h_fd['slideorigin'] ?? '')
        . ':' . ($h_fd['slidenumber'] ?? '')
        . ':' . ($h_fd['slidesubsection'] ?? '');
    $h_altText = $h_fd['displayname'] ?? '';

    ?>
    <img src="<?= $u_linkUrl ?>" alt="<?= $h_altText ?>" class="file">
    <table>
        <tr>
            <td>Slide:</td>
            <td><?= $h_slide ?></td>
        </tr>
        <tr>
            <td>Written notes:</td>
            <td><?= $h_fd['writtennotes'] ?? '' ?></td>
        </tr>
        <tr>
            <td>Date:</td>
            <td><?= $h_fd['date'] ?? '' ?></td>
        </tr>
        <tr>
            <td>Location:</td>
            <td><?= $h_fd['location'] ?? '' ?></td>
        </tr>
        <tr>
            <td>Photographer:</td>
            <td><?= $h_fd['photographer'] ?? '' ?></td>
        </tr>
        <tr>
            <td>People:</td>
            <td><?= $h_fd['people'] ?? '' ?></td>
        </tr>
        <tr>
            <td>Keywords:</td>
            <td><?= $h_fd['keywords'] ?? '' ?></td>
        </tr>
    </table>
    <div id="comments"></div>
</div><!-- End template-content div-->

<script id="template-script">
    // Initialize the TreeView.
    function setupTemplate() {
        console.log("In FileTemplate.setupTemplate()");
        const comments = new Comments(<?= Metadata::getFileId() ?>);
        comments.setupTemplate();
    }
</script>
</body>
</html>
