<?php
declare(strict_types=1);
?>
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
        $root = MM_BASEURL . IMAGEDIR;

        echo '<ul>';
        scanDirectory($root);
        echo '</ul>';

        /**
         * Recursively scan a directory and output its contents in a format appropriate for this template.
         * ToDo: Expand to, and select, currently passed $path.
         * ToDo: make it accept one or more callbacks to say how to recurse into, skip, or display entries.
         * @param string $dir The full path to the dir being scanned. When first calling, pass the root of the tree.
         */
        function scanDirectory(string $dir): void {
            $items = scandir($dir);
            // Loop through the items and output a list item for each one.
            foreach ($items as $item) {
                // Skip the current and parent directories, and any hidden ones.
                if (str_starts_with($item, '.')) {
                    continue;
                }
                $h_item = htmlspecialchars($item);
                $webDir = str_replace(MM_BASEDIR, '', "$dir/$item");
                $u_linkUrl = MM_BASEURL . '?path=' . urlencode($webDir) . '&amp;i=1';

                // If the item is a directory, output a list item with a nested ul element.
                if (is_dir("$dir/$item")) {
                    echo "<li class='folder'><span class='expand-collapse '>+</span>";
                    echo "<a href='$u_linkUrl' class='.path-link'>$h_item</a>";
                    echo "<ul style='display:none;'>";
                    scanDirectory("$dir/$item");
                    echo '</ul></li>';
                }
                // Otherwise, output a list item for the file
                else {
                    echo "<li class='file'><a href='$u_linkUrl' class='.path-link'>$h_item</a></li>";
                }
            }
        }
        ?>
    </div>

    <div class="drag-bar"></div>
    <div class="content right-column">Hello, world!</div>
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

    dragBar.addEventListener('mousedown', function(e) {
        isDragging = true;
        currentX = e.clientX;
        leftColumnWidth = leftColumn.offsetWidth;
        rightColumnWidth = rightColumn.offsetWidth;
    });

    document.addEventListener('mousemove', function(e) {
        if (isDragging) {
            e.preventDefault();
            const deltaX = e.clientX - currentX;
            const newLeftColumnWidth = Math.max(50, leftColumnWidth + deltaX);
            const newRightColumnWidth = Math.max(50, rightColumnWidth - deltaX);
            leftColumn.style.width = newLeftColumnWidth + 'px';
            rightColumn.style.width = newRightColumnWidth + 'px';
        }
    });

    document.addEventListener('mouseup', function() {
        isDragging = false;
    });
</script>

<script>
    // Tree view event listeners to handle expand/collapse behavior.

    // Get all the folder elements in the tree view.
    const folders = document.querySelectorAll('.folder');

    // Add a click event listener to each folder
    folders.forEach(folder => {
        folder.addEventListener('click', function(e) {
            // Get the span element that was clicked: should probably be a class rather than just span.
            const span = folder.querySelector('span');
            // Toggle the expand/collapse state of the folder
            if ('+' === span.textContent) {
                span.textContent = '-';
                folder.querySelector('ul').style.display = 'block';
            }
            else if ('-' === span.textContent)  {
                span.textContent = '+';
                folder.querySelector('ul').style.display = 'none';
            }
            e.stopPropagation();
        });
    });
</script>

<!--suppress InnerHTMLJS -->
<script>
    // Tree view event listeners to handle link-click behavior.
    // Tree view event listeners to handle expand/collapse behavior.

    // Get all the folder elements in the tree view.
    const links = document.querySelectorAll('.path-link');

    // Method to Handle link clicking.
    function openLinkInline(url) {
        const request = new XMLHttpRequest();
        request.open("GET", url, true);
        request.send();
        request.onload = function() {
            if (200 <= request.status && 400 > request.status) {
                // Success!
                document.getElementById("content").innerHTML = request.responseText;
            } else {
                document.getElementById("content").innerHTML = 'Server returned an error.';
            }
        };
        request.onerror = function() {
            document.getElementById("content").innerHTML = 'There was a connection error of some sort.';
        };
    }

    // Add a click event listener to each folder
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            openLinkInline(this.href);
        });
    });
    <?php global $h_path; ?>
    openLinkInline('<?= $h_path; ?>');
</script>

</body>
</html>
