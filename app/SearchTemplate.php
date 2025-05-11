<?php

declare(strict_types=1);

namespace MidwestMemories;

/** Template of search/results form.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Search</title>
</head>
<body>
<div class="thumb-content">
    <h1 class="center">Search</h1>
    <p>This is a description of the search process.</p>
</div>

<div class="thumb-pad" id="rounded">
    <div class="spacer">&nbsp;</div>
    <?php
    $items = scandir(Index::$requestUnixPath);
    foreach ($items as $item) {
        // Todo: folders first.
        $itemPath = Index::$requestUnixPath . '/' . $item;

        // Skip files we're uninterested in.
        if (
            !is_file($itemPath)
            || str_starts_with($itemPath, 'tn_')
            || str_starts_with($itemPath, '.')
            || !preg_match('/\.(gif|png|jpg|jpeg)$/', $itemPath)
        ) {
            continue;
        }
        // Skip files without a matching thumbnail file: they have not been fully processed.
        $thumbName = DropboxManager::getThumbName($itemPath);
        if (!is_file($thumbName)) {
            if (!str_starts_with($thumbName, 'tn_')) { // Avoid log spam from thumbs.
                Log::adminDebug("No thumb found for image: $thumbName from $itemPath");
            }
            continue;
        }

        $u_linkUrl = Path::filePathToUrl($itemPath, Path::LINK_INLINE);
        $u_thumbUrl = Path::filePathToUrl($thumbName, Path::LINK_RAW);

        echo("<div class='thumb'><p><strong>1:</strong><a href='$u_linkUrl'></a></p>");
        // ToDo: alt texts.
        // ToDo: check width and height.
        echo("<a href='$u_linkUrl'><img src='$u_thumbUrl' title='ToDo' alt='ToDo' width='150' height='50'></a></div>");
    }
    ?>
    <div class="spacer">&nbsp;</div>
</div><!-- thumb-content -->

</body>
</html>
