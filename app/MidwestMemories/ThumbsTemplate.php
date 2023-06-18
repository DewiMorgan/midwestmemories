<?php

declare(strict_types=1);

use app\DropboxManager;
use app\MidwestMemories\Index;

/** Template to display thumbnails in a table, with titles, and a description at the top.
 * Requirements:
 *   $dirPath as string -> unique key identifies dir (or later, search result!)
 * This can be used to generate:
 *   $h_pageTitle as h_string
 *   $h_pageTopContent as h_string
 *   $dirNav as [
 *     'previousDir'=>Url,
 *     'currentDir'=>Url,
 *     'nextDir'=>Url,
 *     'parentDir'=>Url,
 *     'pageNum'=>Int,
 *     'numPages'=>Int,
 *     'numPerPage'=>Int
 *   ]
 *   $listOfThumbs as [description=>h_string, 'thumbUrl'=>Url, 'imageUrl'=>Url]
 * 1 +- 2 +/- 3 * 4 = ?
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Thumbs</title>
    <style>
        body {
            font: 10pt sans-serif;
            color: #000;
            background-color: lightblue;
            text-align: justify;
        }

        a {
            text-decoration: underline;
        }

        a:hover {
            text-decoration: none;
        }

        .center {
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }

        div.spacer {
            clear: both;
        }

        div.thumb-pad { /* The containing rectangle for the thumbs. Without it, the bottom right looks wrong. */
            border-radius: 30px;
            background-color: #BDCEFB;
            /* margin:0px 10px 0px 10px; /* Spacing around it on the page. */
        }

        div.thumb { /* Each thumbnail's rectangle of text and image. */
            float: left; /* Makes them grid instead of vertical. */
            padding: 0 0 0 10px; /* Padding against left of thumb-pad */
            text-align: center;
            width: 170px; /* Keeps 'em aligned on the grid */
        }

        div.thumb p { /* Reduces spacing around the thumb text */
            margin: 0;
            border: 0;
            padding: 0;
        }

        div.thumb img {
            margin: 4px 6px 6px 4px; /* Positioning relative to text */
            background-color: #fff; /* White border */
            padding: 4px; /* White border */
            border: 1px solid #a9a9a9; /* Black border outside the white one. */
            box-shadow: 10px 5px 5px #777777; /* Drop shadow */
        }
    </style>
</head>
<body>
<div class="thumb-content">
    <h1 class="center">Folder title goes here</h1>
    <p>This is a description of the folder/album and its contents.</p>
</div>

<div class="thumb-pad" id="rounded">
    <div class="spacer">&nbsp;</div>
    <?php
    global $realPath;
    $items = scandir($realPath);
    foreach ($items as $item) {
        // Todo: folders first.

        // Skip files we're uninterested in.
        if (
            !is_file($item)
            || str_starts_with($item, 'tn_')
            || str_starts_with($item, '.')
            || !preg_match('/\.(gif|png|jpg|jpeg)$/', $item)
        ) {
            continue;
        }
        // Skip files without a matching thumbnail file: they have not been fully processed.
        $thumbName = DropboxManager::getThumbName($item);
        if (!is_file($thumbName)) {
            continue;
        }
        $h_item = htmlspecialchars($item);
        $u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode($item) . '&amp;i=1';
        $u_thumbUrl = Index::MM_BASE_URL . '?path=' . urlencode($thumbName) . '&amp;i=1';

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