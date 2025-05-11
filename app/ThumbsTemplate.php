<?php

declare(strict_types=1);

namespace MidwestMemories;

/** Template to display thumbnails in a table, with titles, and a description at the top.
 * Requirements:
 *   Index::$realPath as string -> unique key identifies dir (or later, search result!)
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
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>ToDo: a folder title here</title>
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
            max-width: <?= DropboxManager::MAX_THUMB_WIDTH ?>px;
            max-height: <?= DropboxManager::MAX_THUMB_HEIGHT ?>px;
        }

        figure {
            margin: 0;
            padding: 0;
            display: block;
        }

        figcaption {
            display: block;
            margin-top: 4px;
            font-size: 0.9em;
            color: #333;
        }
    </style>
</head>
<body>
<div class="thumb-content">
    <h1 class="center">Folder title goes here</h1>
    <p>This is a description of the folder/album and its contents.</p>
    <p>This is a <a href="https://www.google.com">test link to make sure external links work.</a></p>
    <p>This is a <a href="https://www.google.com?x&i=1">test link to try breaking external links.</a></p>
</div>

<div class="thumb-pad" id="rounded">
    <div class="spacer">&nbsp;</div>
    <?php
    $items = scandir(Index::$requestUnixPath);

    $dirs = [];
    $files = [];
    foreach ($items as $item) {
        $itemPath = Index::$requestUnixPath . '/' . $item;
        if (is_dir($itemPath)) {
            if ('.' === $item || ('..' === $item && (Index::$requestUnixPath === Path::$imgBaseUnixPath))) {
                Log::debug('Ignoring folder', $itemPath);
            } else {
                $dirs[] = $item;
            }
        } elseif (is_file($itemPath)) {
            if (
                preg_match('/\/(?:\.|tn_)[^\/]+$/', $itemPath) // In blocklist.
                || !preg_match('/\.(gif|png|jpg|jpeg)$/', $itemPath) // Not in allowlist.
            ) {
                Log::debug('Ignoring file', $itemPath);
            } else {
                $files[] = $item;
            }
        } else {
            Log::debug('Ignoring unknown FS object', $itemPath);
        }
    }

    // Output
    $fileNum = 0;
    foreach (array_merge($dirs, $files) as $item) {
        $itemPath = Index::$requestUnixPath . '/' . $item;

        // Skip files without a matching thumbnail file: they have not been fully processed.
        if (is_file($itemPath)) {
            $thumbUnixPath = DropboxManager::getThumbName($itemPath);
            if (!is_file($thumbUnixPath)) {
                Log::debug("No thumb found for image: '$thumbUnixPath' from '$itemPath'");
                continue;
            }
            Log::debug("Creating thumb-link for image: '$thumbUnixPath' from '$itemPath'");
            $u_thumbUrl = Path::unixPathToUrl($thumbUnixPath, Path::LINK_RAW);
            $fileNum++;
            $h_thumbTitle = htmlspecialchars($item);
        } elseif ('..' === $item) {
            $h_thumbTitle = '<strong>..</strong> - up one folder.';
            $u_thumbUrl = Path::unixPathToUrl('./tn_folder_up.png', Path::LINK_RAW);
        } else {
            $h_thumbTitle = htmlspecialchars($item);
            $u_thumbUrl = Path::unixPathToUrl('./tn_folder.png', Path::LINK_RAW);
        }
        $u_linkUrl = Path::unixPathToUrl($itemPath, Path::LINK_INLINE);

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

</body>
</html>
