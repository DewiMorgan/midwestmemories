<?php

/** This is the template to display the tree navigation. */
declare(strict_types=1);

namespace MidwestMemories;

$isLiveSite = str_contains(__DIR__, 'midwestmemoriesfamily');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($isLiveSite) { ?>
        <link rel="shortcut icon" href="/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="manifest" href="/site.webmanifest">
    <?php } else { ?>
        <link rel="icon" href="/favicon-test.ico" type="image/x-icon">
    <?php } ?>
    <title><?php echo $isLiveSite ? '<!-- Live Site -->' : 'Test Site'; ?><?php IndexGateway::getSiteName(); ?>
        - Folder Tree</title>
    <!--suppress HtmlUnknownTarget -->
    <link rel="stylesheet" href="/raw/user.css">
    <!--suppress HtmlUnknownTarget -->
    <script src="/raw/user.js"></script>
</head>
<body onload="setupTemplate()">
<div class="flex-container" id="parent-container">
    <div class="tree-view left-column">
        Welcome, <?= htmlspecialchars(User::getInstance()->username) ?>!
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="logout">
            <button type="submit" style="margin-left: 10px;">Logout</button>
        </form>

        <?php
        // Set the root directory to display in the tree view.
        $root = Path::$imgBaseUnixPath;

        /* Alternatives and options for image files include:
         * U+1F4C1 📁 File Folder
         * U+1F4C2 📂 Open File Folder
         * U+1F5BF 🖿 Black Folder
         * U+1F5C0 🗀 Folder
         * U+1F5C1 🗁 Open Folder
         * U+1F4F7 📷 Camera
         * U+1F4C4 📄 Page Facing Up
         * U+1F5BB 🖻 Document with Picture.
         */
        const ICON_EXPANDED = '📂'; // U+1F4C2 Open File Folder
        const ICON_COLLAPSED = '📁'; // U+1F4C1 File Folder

        // This is the treeview component.
        echo '<ul>';
        echo "<li class='folder'><a href='/?i=1' class='path-link'>Home</a></li>";
        scanDirectory($root, IndexGateway::$requestUnixPath);
        echo '</ul>';

        /**
         * Recursively scan a directory and output its contents in a format appropriate for this template.
         * ToDo: Expand to, and select, currently passed $path.
         * ToDo: make it accept one or more callbacks to say how to recurse into, skip, or display entries.
         * @param string $scanUnixDir Full path to the dir being scanned. When first calling, pass the root of the tree.
         * @param string $targetUnixPath The current item selected/expanded/viewed by the user.
         */
        function scanDirectory(string $scanUnixDir, string $targetUnixPath = ''): void
        {
Log::debug("scanDirectory in $scanUnixDir", $targetUnixPath);
            $items = scandir($scanUnixDir);
Log::debug('Before sort', $items);
            // Sort to natural order, with directories first.
            usort($items, [Path::class, 'sortFolder']);
Log::debug('After sort', $items);

            // Loop through the items and output a list item for each one.
            $files = '';
            foreach ($items as $item) {
                $itemUnixPath = "$scanUnixDir/$item";

                // Validation.
                if (is_dir($itemUnixPath)) {
                    if (!Path::canListDirname($itemUnixPath)) {
                        continue;
                    }
                } elseif (!Path::canListFilename($itemUnixPath)) {
                    continue;
                }

                $h_item = htmlspecialchars($item);
                $itemUnixPath = "$scanUnixDir/$item";
                $u_linkUrl = Path::unixPathToUrl($itemUnixPath, Path::LINK_INLINE);
                $h_selectClass = ($itemUnixPath === $targetUnixPath) ? 'selected' : '';
                // If the item is a directory, output a list item with a nested ul element.
                if (is_dir($itemUnixPath)) {
                    // Collapse, unless our target path is within this branch.
                    $h_expandClass = Path::isChildInPath($targetUnixPath, $itemUnixPath) ? 'expanded' : 'collapsed';
                    $h_expandIcon = ('expanded' === $h_expandClass) ? ICON_EXPANDED : ICON_COLLAPSED;
                    echo "<li class='folder $h_expandClass $h_selectClass'>";
                    echo "<span class='expand-collapse'>$h_expandIcon</span>";
                    echo " <a href='$u_linkUrl' class='path-link'>$h_item</a>";
                    echo "<ul>\n";
                    // ToDo: If dir is empty, we make an empty UL. Output to a var, and only print if var has data.
                    scanDirectory($itemUnixPath, $targetUnixPath);
                    echo "</ul></li>\n";
                } else {
                    $files .= "<li class='file $h_selectClass'>"
                        . "<a href='$u_linkUrl' class='path-link'>$h_item</a>"
                        . "</li>\n";
                }
            }
            echo $files;
        }

        ?>
    </div>
    <div class="drag-bar"></div>
    <div class="content right-column" id="content">Hello, world!</div>
    <?php
    $u_linkUrl = Path::unixPathToUrl($_REQUEST['path'] ?? '/', Path::LINK_INLINE);
    ?>
    <script>
        // Initialize the TreeView.
        function setupTemplate() {
            console.log("Fetching comments...");
            const tv = TreeView.init();
            tv.openLinkInline('<?= $u_linkUrl ?>');
        }
    </script>
</div>
</body>
</html>
