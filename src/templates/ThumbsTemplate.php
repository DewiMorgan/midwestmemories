<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>ToDo: a folder title here</title>
    <!-- Style elements that require dynamic PHP values -->
    <style>
        div.thumb img {
            max-width: <?= Conf::get(Key::MAX_THUMB_WIDTH) ?>px;
            max-height: <?= Conf::get(Key::MAX_THUMB_HEIGHT) ?>px;
        }
    </style>
</head>
<body>
<div id="template-content">
    <div class="thumb-content">
        <h1 class="center">ToDo: Folder title goes here</h1>
        <p>ToDo: description of the folder/album and its contents.</p>
    </div>

    <div class="thumb-pad" id="rounded">
        <div class="spacer">&nbsp;</div>
        <?php Path::generateThumbs(); ?>
        <div class="spacer">&nbsp;</div>
    </div><!-- thumb-content -->
</div><!-- End template-content div-->
<script id="template-script">
    function cleanupTemplate() {
        console.log("Cleaned up thumbs...");
    }

    function setupTemplate() {
        console.log("Loaded thumbs...");
    }
</script>
</body>
</html>
