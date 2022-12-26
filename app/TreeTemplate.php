<html lang="en">
<head>
    <title>Folder navigation</title>
    <style>
        .container {
            display: flex;
        }
        .tree-view {
            width: 200px;
            background-color: lightgrey;
        }
        .hello-world {
            flex: 1;
            background-color: lightblue;
        }
        /* Style the folders in the tree view. */
        .folder {
            cursor: pointer;
        }
        /* Style the expand/collapse span. */
        .expand-collapse {
            font-weight: bold;
        }
        /* Style the files in the tree view. */
        .file {
            cursor: default;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="tree-view">
        <?php
        // Set the root directory to display in the tree view.
        $root = __DIR__ . '/../';

        // Use scandir() to get a list of files and directories in the root directory.
        $items = scandir($root);

        // Loop through the items and output a list item for each one.
        echo '<ul>';
        foreach ($items as $item) {
            // Skip the current and parent directories
            if ($item == '.' || $item == '..') {
                continue;
            }

            // If the item is a directory, output a list item with a nested ul element.
            if (is_dir("$root/$item")) {
                echo "<li class='folder'><span class='expand-collapse'>+</span> $item<ul>";
                // Recursively scan the subdirectory and output its contents.
                scanDirectory("$root/$item");
                echo '</ul></li>';
            }

            // Otherwise, output a list item for the file
            else {
                echo "<li class='file'>$item</li>";
            }
        }
        echo '</ul>';

        /**
         * Recursively scan a directory and output its contents.
         * @param string $dir The full path to the dir being scanned.
         */
        function scanDirectory(string $dir): void {
            $items = scandir($dir);
            foreach ($items as $item) {
                if ($item == '.' || $item == '..') {
                    continue;
                }
                if (is_dir("$dir/$item")) {
                    echo "<li class='folder'><span class='expand-collapse'>+</span> $item<ul>";
                    scanDirectory("$dir/$item");
                    echo '</ul></li>';
                }
                else {
                    echo "<li class='file'>$item</li>";
                }
            }
        }
        ?>
    </div>

    <div class="hello-world">Hello, world!</div>
</div>

<script>
    // Add event listeners and handle expand/collapse behavior.

    // Get all the folder elements in the tree view.
    const folders = document.querySelectorAll('.folder');

    // Add a click event listener to each folder
    folders.forEach(folder => {
        folder.addEventListener('click', event => {
            // Get the span element that was clicked
            const span = event.target;

            // Toggle the expand/collapse state of the folder
            if ('+' === span.textContent) {
                span.textContent = '-';
                folder.querySelector('ul').style.display = 'block';
            }
            else {
                span.textContent = '+';
                folder.querySelector('ul').style.display = 'none';
            }
        });
    });
</script>
</body>
</html>
