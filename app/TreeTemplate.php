<?php

/** This is the template to display the tree navigation. */
declare(strict_types=1);

namespace MidwestMemories;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Folder navigation</title>
    <style>
        /* Page layout. */
        body {
            overflow: hidden;
            border: 0;
            padding: 0;
            margin: 0;
        }

        .flex-container {
            display: flex;
            height: 100vh;
        }

        /* Drag-bar layout. */
        .left-column {
            width: 25%;
            overflow: auto;
        }

        .right-column {
            width: 75%;
            overflow: auto;
        }

        .drag-bar {
            width: 10px;
            background-color: darkgrey;
            cursor: col-resize;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Tree-view styling. */
        .tree-view {
            background-color: lightgrey;
        }

        /* Folders in the tree view. */
        .folder {
            cursor: pointer;
        }

        /* Expand/collapse span. */
        .expand-collapse {
            font-weight: bold;
        }

        .collapsed ul {
            display = 'none';
        }

        .expanded ul {
            display = 'block';
        }

        /* Files in the tree view. */
        .file {
            cursor: default;
        }

        /* Content styling. */
        .content {
            background-color: lightblue;
        }
    </style>
</head>
<body>
<div class="flex-container">
    <div class="tree-view left-column">
        <?php

        // Set the root directory to display in the tree view.
        $root = Index::$baseDir;

        echo '<ul>';
        echo "<li class='folder'><a href='?path=%2F&amp;i=1' class='path-link'>Home</a></li>";
        scanDirectory($root);
        echo '</ul>';

        /**
         * Recursively scan a directory and output its contents in a format appropriate for this template.
         * ToDo: Expand to, and select, currently passed $path.
         * ToDo: make it accept one or more callbacks to say how to recurse into, skip, or display entries.
         * @param string $dir The full path to the dir being scanned. When first calling, pass the root of the tree.
         * @param string $targetPath The current item selected/expanded/viewed by the user.
         */
        function scanDirectory(string $dir, string $targetPath = ''): void
        {
            $items = scandir($dir);

            // Loop through the items and output a list item for each one.
            $files = '';
            foreach ($items as $item) {
                // Skip the current and parent directories, and any hidden ones.
                if (str_starts_with($item, '.')) {
                    continue;
                }
                $h_item = htmlspecialchars($item);
                $webDir = str_replace(Index::$baseDir, '', "$dir/$item");
                $u_linkUrl = Index::MM_BASE_URL . '?path=' . urlencode($webDir) . '&amp;i=1';
                // If the item is a directory, output a list item with a nested ul element.
                if (is_dir("$dir/$item")) {
                    // Collapse, unless our target path is within this branch.
                    $expandClass = Path::isChildInPath($targetPath, "$dir/$item") ? 'expanded' : 'collapsed';
                    Log::debug("$expandClass : $dir/$item"); // DELETEME DEBUG
                    echo "<li class='folder $expandClass'><span class='expand-collapse '>+</span>";
                    echo "<a href='$u_linkUrl' class='path-link'>$h_item</a>";
                    echo "<ul style='display:none;'>\n";
                    scanDirectory("$dir/$item");
                    echo "</ul></li>\n";
                } else {
                    // Otherwise, append to the list of files.
                    $files .= "<li class='file'><a href='$u_linkUrl' class='path-link'>$h_item</a></li>\n";
                }
            }
            echo $files;
        }

        ?>
    </div>

    <div class="drag-bar"></div>
    <div class="content right-column" id="content">Hello, world!</div>
</div>

<script>
    // DragBar behavior.

    const dragBar = document.querySelector('.drag-bar');
    const leftColumn = document.querySelector('.left-column');
    const rightColumn = document.querySelector('.right-column');

    let isDragging = false;
    let currentX;
    let leftColumnWidth;
    let rightColumnWidth;

    dragBar.addEventListener('mousedown', handleDragBarMouseDown);
    document.addEventListener('mousemove', handleDragBarMouseMove);
    document.addEventListener('mouseup', handleDragBarMouseUp);

    function handleDragBarMouseDown(e) {
        isDragging = true;
        currentX = e.clientX;
        leftColumnWidth = leftColumn.offsetWidth;
        rightColumnWidth = rightColumn.offsetWidth;
    }

    function handleDragBarMouseMove(e) {
        if (isDragging) {
            e.preventDefault();
            const deltaX = e.clientX - currentX;
            const newLeftColumnWidth = Math.max(50, leftColumnWidth + deltaX);
            const newRightColumnWidth = Math.max(50, rightColumnWidth - deltaX);
            leftColumn.style.width = newLeftColumnWidth + 'px';
            rightColumn.style.width = newRightColumnWidth + 'px';
        }
    }

    function handleDragBarMouseUp() {
        isDragging = false;
    }
</script>

<script>
    // Tree view event listeners to handle expand/collapse behavior.

    // Get all the folder elements in the tree view.
    const folders = document.querySelectorAll('.folder');

    // Add a click event listener to each folder
    folders.forEach(addFoldClickHandler);

    function addFoldClickHandler(folder) {
        console.log("Adding onClick to fold: " + folder.textContent);
        folder.addEventListener('click', foldElement);
    }

    function foldElement(e) {
        // Get the span element that was clicked: should probably be a class rather than just span.
        const span = this.querySelector('span');
        // Toggle the expand/collapse state of the folder
        this.classList.toggle("expanded");
        this.classList.toggle("collapsed");
        if ('+' === span.textContent) {
            span.textContent = '-';
        } else if ('-' === span.textContent) {
            span.textContent = '+';
        }
        e.stopPropagation();
    }
</script>

<!--suppress InnerHTMLJS -->
<script>
    // Tree view event listeners to handle link-click behavior.

    // Get all the folder elements in the tree view.
    const links = document.querySelectorAll('.path-link');

    // Add a click event listener to each folder
    links.forEach(addLinkClickHandler);

    window.onpopstate = handleNavigation;

    function handleNavigation(e) {
        if (e.state) {
            document.getElementById("content").innerHTML = e.state.html;
            document.title = e.state.pageTitle;
        }
    }

    /**
     * Handle link clicking, to load content into the content div
     * @param {string} url The link to load.
     * @returns {Promise<void>}
     */
    async function openLinkInline(url) {
        console.log("Opening link inline: " + url);

        const content = document.getElementById("content");
        removeAllChildNodes(content); // Ensure event listeners are removed.
        try {
            const response = await fetch(url);
            content.innerHTML = await response.text();
            //ToDo: set document title.
            console.log("Got to writing.");
        } catch (error) {
            document.title = 'Error loading page';
            content.innerHTML = error;
            console.error(error);
        }

        // set the content of the div to the fetched data
        const historyUrl = url.replace(/&(?:amp;)?i=\d+/, ''); // Strip out "inline" instruction.
        console.log("Updating URL to '" + historyUrl + "'.");
        window.history.pushState({"html": historyUrl, "pageTitle": "Todo: Title"}, '', historyUrl);
    }

    /**
     * Add an onclick handler to a link.
     * @param {Element} link
     */
    function addLinkClickHandler(link) {
        const attr = link.getAttribute("href");
        console.log("Adding onClick to link: " + attr);
        link.addEventListener('click', clickLink);
    }

    function clickLink(e) {
        console.log("Onclick link.");
        e.preventDefault();
        const attr = this.getAttribute("href");
        openLinkInline(attr);
    }

    /**
     * Clear all child nodes from a parent.
     * @param {HTMLElement} parent
     */
    function removeAllChildNodes(parent) {
        while (parent.firstChild) {
            parent.removeChild(parent.firstChild);
        }
    }
</script>

</body>
</html>
