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
        use MidwestMemories\Index;

        $root = Index::$baseDir;

        echo "<li class='folder'><a href='/' class='path-link'>Home</a></li>";

        scanDirectory($root);
        echo '</ul>';

        /**
         * Recursively scan a directory and output its contents in a format appropriate for this template.
         * ToDo: Expand to, and select, currently passed $path.
         * ToDo: make it accept one or more callbacks to say how to recurse into, skip, or display entries.
         * @param string $dir The full path to the dir being scanned. When first calling, pass the root of the tree.
         */
        function scanDirectory(string $dir): void
        {
            $items = scandir($dir);
            // Loop through the items and output a list item for each one.
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
                    echo "<li class='folder'><span class='expand-collapse '>+</span>";
                    echo "<a href='$u_linkUrl' class='path-link'>$h_item</a>";
                    echo "<ul style='display:none;'>";
                    scanDirectory("$dir/$item");
                    echo '</ul></li>';
                } else {
                    // Otherwise, output a list item for the file
                    echo "<li class='file'><a href='$u_linkUrl' class='path-link'>$h_item</a></li>";
                }
            }
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
        folder.addEventListener('click', function (e) {
            // Get the span element that was clicked: should probably be a class rather than just span.
            const span = folder.querySelector('span');
            // Toggle the expand/collapse state of the folder
            if ('+' === span.textContent) {
                span.textContent = '-';
                folder.querySelector('ul').style.display = 'block';
            } else if ('-' === span.textContent) {
                span.textContent = '+';
                folder.querySelector('ul').style.display = 'none';
            }
            e.stopPropagation();
        });
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

    // Method to Handle link clicking.
    function openLinkInline(url) {
        console.log("Opening link inline: " + url);
        fetch(url)
            .then(response => response.text())
            .then(data => {
                // set the content of the div to the fetched data
                console.log("Got to writing.");
                removeAllChildNodes(content); // Ensure event listeners are removed.
                document.getElementById("content").innerHTML = data;
                console.log("Updating URL to '" + url + "'.");
                window.history.pushState({"html": url, "pageTitle": "Todo: Title"}, '', url); // ToDo: page title.
                document.title
            })
            .catch(error => {
                const content = document.getElementById("content");
                removeAllChildNodes(content); // Ensure event listeners are removed.
                document.getElementById("content").innerHTML = error;
                console.error(error);
            });
    }


    function addLinkClickHandler(link) {
        console.log("Adding onClick to link: " + link.getAttribute("href"));
        link.addEventListener('click', function (e) {
            console.log("Onclick link.");
            e.preventDefault();
            openLinkInline(this.getAttribute("href"));
        });
    }

    function removeAllChildNodes(parent) {
        while (parent.firstChild) {
            parent.removeChild(parent.firstChild);
        }
    }
</script>

</body>
</html>
