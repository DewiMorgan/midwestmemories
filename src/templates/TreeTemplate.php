<?php

/** This is the template to display the tree navigation. */
declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

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
    <link rel="stylesheet" href="/raw/user.css">
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

        // This is the treeview component.
        echo '<ul>';
        echo "<li class='folder'><a href='/?i=1' class='path-link'>Home</a></li>";
        Path::buildTree($root, IndexGateway::$requestUnixPath);
        echo '</ul>';
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
