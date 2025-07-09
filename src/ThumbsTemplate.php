<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

/** Template to display thumbnails in a table, with titles, and a description at the top.
 * Requirements:
 *   Index::$realPath as string -> unique key identifies dir (or later, search result!)
 * This can be used to generate:
 *   $h_pageTitle as h_string
 *   $h_pageTopContent as h_string
 *   $dirNav as
 *    ['previousDir'=>Url,
 *     'currentDir'=>Url,
 *     'nextDir'=>Url,
 *     'parentDir'=>Url,
 *     'pageNum'=>Int,
 *     'numPages'=>Int,
 *     'numPerPage'=>Int]
 *   $listOfThumbs as [description=>h_string, 'thumbUrl'=>Url, 'imageUrl'=>Url]
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>ToDo: a folder title here</title>
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
        <?php
        $items = Path::getDirItems(IndexGateway::$requestUnixPath);

        // Add the 'up one folder' item, unless we're at the root.
        if ('/' !== IndexGateway::$requestWebPath) {
            addThumb(
                Path::unixPathToUrl(IndexGateway::$requestUnixPath . '/..'),
                '/raw/tn_folder_up.png',
                '<strong>..</strong> - up one folder.',
                0
            );
        }

        // Output.
        $fileNum = 0;
        foreach ($items as $item) {
            $filename = $item['name'];
            $isDir = $item['isDir'];
            $itemPath = $item['unixPath'];

            // Skip files without a matching thumbnail file: they have not been fully processed.
            if ($isDir) {
                $h_thumbTitle = htmlspecialchars($filename);
                $u_thumbUrl = '/raw/tn_folder.png';
            } else {
                $thumbUnixPath = FileProcessor::getThumbName($itemPath);
                if (!is_file($thumbUnixPath)) {
                    Log::debug("No thumb found for image: '$thumbUnixPath' from '$itemPath'");
                    continue;
                }
                Log::debug("Creating thumb-link for image: '$thumbUnixPath' from '$itemPath'");
                $u_thumbUrl = Path::unixPathToUrl($thumbUnixPath, Path::LINK_RAW);
                $fileNum++;
                $h_thumbTitle = htmlspecialchars($filename);
            }
            $u_linkUrl = Path::unixPathToUrl($itemPath, Path::LINK_INLINE);

            addThumb($u_linkUrl, $u_thumbUrl, $h_thumbTitle, $fileNum);
        }

        /**
         * Add one thumbnail to the page.
         * @param string $u_linkUrl URL-escaped link to the file.
         * @param string $u_thumbUrl URL-escaped link to the thumbnail.
         * @param string $h_thumbTitle HTML-escaped title of the thumbnail.
         * @param int $fileNum The ordinal position of the file within the folder.
         * @return void
         */
        function addThumb(string $u_linkUrl, string $u_thumbUrl, string $h_thumbTitle, int $fileNum): void
        {
            echo("<div class='thumb'><figure>");

            echo("<a href='$u_linkUrl'><img src='$u_thumbUrl' title='$h_thumbTitle' alt='$h_thumbTitle'></a>");
            echo('<figcaption>');
            if ($fileNum) {
                echo("<strong>$fileNum: </strong>");
            }
            echo("<a href='$u_linkUrl'>$h_thumbTitle</a></figcaption>");
            echo('</figure></div>');
        }

        ?>
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
