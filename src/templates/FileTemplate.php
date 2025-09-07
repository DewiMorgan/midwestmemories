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
    $h_fd = Metadata::getEscapedByUnixPath(IndexGateway::$requestUnixPath);

    // Special cases.
    $h_slide = ($h_fd['slideorigin'] ?? '')
            . ':' . ($h_fd['slidenumber'] ?? '')
            . ':' . ($h_fd['slidesubsection'] ?? '');
    $h_altText = $h_fd['displayname'] ?? '';

    ?>
    <img src="<?= $u_linkUrl ?>" alt="<?= $h_altText ?>" class="file">
    <div class="lds-roller">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>
    <img src="/raw/brokenimage.png" alt="Broken Image" class="broken-image">

    <!-- Image Type Selector -->
    <div class="image-type-selector" style="margin: 10px 0;">
        <label for="image-type-selector">Image Type: </label>
        <select id="image-type-selector">
            <?php
            $currentType = User::getImageType()['data']['image_type'] ?? 'web';
            $types = ['web', 'original', 'back', 'ice', 'thumbnail'];
            foreach ($types as $type):
                $selected = $currentType === $type ? 'selected' : '';
                echo "<option value=\"$type\" $selected>$type</option>";
            endforeach;
            ?>
        </select>
    </div>

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
    // Initialize the page components.
    function setupTemplate() {
        console.log("In FileTemplate.setupTemplate()");
        const comments = new Comments(<?= Metadata::getFileId() ?>);
        comments.setupTemplate();

        // Initialize the image type selector
        const currentType = '<?= $currentType ?>';
        ImageTypes.init('image-type-selector', currentType);
    }
</script>
</body>
</html>
