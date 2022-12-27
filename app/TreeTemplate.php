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
        $root = __DIR__ . '/../';

        echo '<ul>';
        scanDirectory($root);
        echo '</ul>';

        /**
         * Recursively scan a directory and output its contents.
         * @param string $dir The full path to the dir being scanned.
         */
        function scanDirectory(string $dir): void {
            $items = scandir($dir);
            // Loop through the items and output a list item for each one.
            foreach ($items as $item) {
                // Skip the current and parent directories, and any hidden ones.
                if (str_starts_with($item, '.')) {
                    continue;
                }
                // If the item is a directory, output a list item with a nested ul element.
                if (is_dir("$dir/$item")) {
                    echo "<li class='folder'><span class='expand-collapse '>+</span> $item<ul>";
                    scanDirectory("$dir/$item");
                    echo '</ul></li>';
                }
                // Otherwise, output a list item for the file
                else {
                    echo "<li class='file'>$item</li>";
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
        if (!isDragging) { return; }
        e.preventDefault();
        const deltaX = e.clientX - currentX;
        const newLeftColumnWidth = Math.max(50, leftColumnWidth + deltaX);
        const newRightColumnWidth = Math.max(50, rightColumnWidth - deltaX);
        leftColumn.style.width = newLeftColumnWidth + 'px';
        rightColumn.style.width = newRightColumnWidth + 'px';
    });

    document.addEventListener('mouseup', function() {
        isDragging = false;
    });
</script>

<script>
    // Tree view event listeners and handle expand/collapse behavior.

    // Get all the folder elements in the tree view.
    const folders = document.querySelectorAll('.folder');

    // Add a click event listener to each folder
    folders.forEach(folder => {
        folder.addEventListener('click', function(e) {
            // Get the span element that was clicked: should probably be a
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
</body>
</html>
