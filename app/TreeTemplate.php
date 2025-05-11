<?php

/** This is the template to display the tree navigation. */
declare(strict_types=1);

namespace MidwestMemories;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php Index::getSiteName(); ?> - Folder Tree</title>
    <!--suppress CssUnusedSymbol -->
    <style>
        img.file {
            /* Sane size */
            max-width: 100%;
            max-height: 70vh; /* % of visible height */
            width: auto;
            height: auto;
            /* Center */
            display: block;
            margin: 0 auto;
        }

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

        li.collapsed > ul {
            display: none;
        }

        li.expanded > ul {
            display: block;
        }

        /* Bolding for selected items. */
        li.selected > .path-link {
            font-weight: bold;
        }

        /* Override bolding for children of selected items. */
        .path-link {
            font-weight: normal;
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
<?php
$u_linkUrl = Index::MM_BASE_URL . ($_REQUEST['path'] ?? '/') . '?i=1';
?>
<body onload="openLinkInline('<?= $u_linkUrl ?>')">
<div class="flex-container" id="parent-container">
    <div class="tree-view left-column">
        <?php
        // Set the root directory to display in the tree view.
        $root = Path::$imgBaseUnixPath;
        const ICON_EXPANDED = '(-)';
        const ICON_COLLAPSED = '(+)';

        // This is the treeview component.
        echo '<ul>';
        echo "<li class='folder'><a href='/?i=1' class='path-link'>Home</a></li>";
        scanDirectory($root, Index::$requestUnixPath);
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
            $items = scandir($scanUnixDir);

            // Loop through the items and output a list item for each one.
            $files = '';
            foreach ($items as $item) {
                // Apply blacklist even to folders. Skip the current and parent directories, and any hidden ones.
                // Also skip thumbnails, index files, and ICE files.
                // ToDo: This blocklist and later allowlist is repeated code in thumbsTemplate, etc. Centralize it.
                if (preg_match('/^(\.|tn_|index\.)|-ICE.jpg$/', $item)) {
                    continue;
                }

                $h_item = htmlspecialchars($item);
                $itemUnixPath = "$scanUnixDir/$item";
                $u_linkUrl = Path::unixPathToUrl($itemUnixPath, Path::LINK_INLINE);
                $selectClass = ($itemUnixPath === $targetUnixPath) ? 'selected' : '';
                // If the item is a directory, output a list item with a nested ul element.
                if (is_dir($itemUnixPath)) {
                    // Collapse, unless our target path is within this branch.
                    $expandClass = Path::isChildInPath($targetUnixPath, $itemUnixPath) ? 'expanded' : 'collapsed';
                    Log::debug(
                        "Folder: expand='$expandClass', select='$selectClass'"
                        . " : $itemUnixPath from $targetUnixPath"
                    ); // DELETEME DEBUG
                    $h_expandIcon = ('expanded' === $expandClass) ? ICON_EXPANDED : ICON_COLLAPSED;
                    echo "<li class='folder $expandClass $selectClass'>";
                    echo "<span class='expand-collapse'>$h_expandIcon</span>";
                    echo " <a href='$u_linkUrl' class='path-link'>$h_item</a>";
                    echo "<ul>\n";
                    // ToDo: If dir is empty, we make an empty UL. Output to a var, and only print if var has data.
                    scanDirectory($itemUnixPath, $targetUnixPath);
                    echo "</ul></li>\n";
                } elseif (preg_match('/\.(gif|png|jpg|jpeg)$/', $item)) {
                    // Append whitelisted filetypes to the list of files.
                    $files .= "<li class='file $selectClass'><a href='$u_linkUrl' class='path-link'>$h_item</a></li>\n";
                    Log::debug("Filing: select='$selectClass' : $itemUnixPath from $targetUnixPath"); // DELETEME DEBUG
                }
            }
            echo $files;

            // DELETEME DEBUG
            $webDir = str_replace(Path::$imgBaseUnixPath, '', $scanUnixDir);
            Metadata::loadFromInis($webDir);
//            echo "<pre>$dir:\n" . var_export(Metadata::getData(), true) . '</pre>';
//            Metadata::saveToIni('x', true);
            // echo 'IniFile:<br><pre>' . Metadata::getIniString('/', Metadata::getData()) . '</pre>';
            // echo 'ArrayDump:<br><pre>' . var_export(Metadata::getData(), true) . '</pre>';
            // END DELETEME DEBUG
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
    let rightColumn = document.querySelector('.right-column');

    let isDragging = false;
    let currentX;
    let leftColumnWidth;
    let rightColumnWidth;

    dragBar.addEventListener('mousedown', handleDragBarMouseDown);
    document.addEventListener('mousemove', handleDragBarMouseMove);
    document.addEventListener('mouseup', handleDragBarMouseUp);

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

    function handleDragBarMouseDown(e) {
        // Reselect this as it may have been recreated.
        rightColumn = document.querySelector('.right-column');
        isDragging = true;
        currentX = e.clientX;
        leftColumnWidth = leftColumn.offsetWidth;
        rightColumnWidth = rightColumn.offsetWidth;
    }

    function handleDragBarMouseUp() {
        isDragging = false;
    }
</script>

<script>
    // Tree view event listeners to handle expand/collapse behavior.

    // Get all the folder elements in the tree view.
    const folders = document.querySelectorAll('.expand-collapse');

    // Add a click event listener to each folder
    folders.forEach(addFoldClickHandler);

    function addFoldClickHandler(folder) {
        console.log("Adding onClick to fold: " + folder.textContent);
        folder.addEventListener('click', foldElement);
    }

    function foldElement(e) {
        // Get the span element that was clicked: should probably be a class rather than just span.
        // Toggle the expand/collapse state of the folder.
        this.parentElement.classList.toggle("expanded");
        this.parentElement.classList.toggle("collapsed");
        if ('<?= ICON_EXPANDED ?>' === this.textContent) {
            this.textContent = '<?= ICON_COLLAPSED ?>';
        } else if ('<?= ICON_COLLAPSED ?>' === this.textContent) { // Explicit elseif lets OTHER spans to go untouched.
            this.textContent = '<?= ICON_EXPANDED ?>';
        }

        e.stopPropagation();
    }
</script>

<script>
    // Tree view event listeners to handle link-click behavior, and the initial onLoad().

    // Get all the folder elements in the tree view.
    const links = document.querySelectorAll('.path-link');

    // Add a click event listener to each folder
    links.forEach(addLinkClickHandler);

    // Add a listener to handle browser back/forward buttons.
    window.onpopstate = handleNavigation;

    function handleNavigation(e) {
        if (e.state) {
            openLinkInline(e.state.html + "?i=1", false);
            document.title = e.state.pageTitle;
        }
    }

    /**
     * Handle link clicking, to load content into the content div
     * @param {string} url The link to load.
     * @param saveHistory True to add the followed link to browser history: false for back/forward button handling.
     * @returns {Promise<void>}
     */
    async function openLinkInline(url, saveHistory = true) {
        console.log("Opening link inline: " + url);

        const content = document.getElementById("content");
        const newContent = clearContentDiv(content); // Ensure event listeners are removed.
        clearAddedStyles(); // Remove any styles we loaded from a previous page load.

        let title;
        try {
            // Import the title, body and styles from the loaded document.
            const doc = await fetchRemoteDocument(url);
            title = doc.querySelector('title')?.innerText;
            importRemoteStyles(doc.head);
            importRemoteContent(doc.body, newContent);
            console.log("Got to writing.");
        } catch (error) {
            // Import the title, body and styles from the loaded document.
            console.error(error);
            title = 'Error loading page';
            const element = document.createElement('h1');
            element.textContent = title;
            newContent.appendChild(element);
        }
        document.title = getSiteName() + ' - ' + title;

        // Ensure our handler loads all child links in the content div.
        addLinksToContent(newContent);

        // Ensure that history will work.
        if (saveHistory) {
            const historyUrl = url.replace(/(?:\?|&(?:amp;)?)i=\d+/, ''); // Strip out "inline" instruction.
            console.log("Updating URL to '" + historyUrl + "'.");
            window.history.pushState({"html": historyUrl, "pageTitle": title}, '', historyUrl);
        }
    }

    /** Fetch and parse the HTML document from a URL. */
    async function fetchRemoteDocument(url) {
        const response = await fetch(url);
        const html = await response.text();
        const parser = new DOMParser();
        return parser.parseFromString(html, 'text/html');
    }

    /** Remove all <style> and <link rel="stylesheet"> tags from the document's <head> node, except the first one. */
    function clearAddedStyles() {
        const stylesAndLinks = document.head.querySelectorAll('style, link[rel="stylesheet"]');
        for (let i = 1; i < stylesAndLinks.length; i++) {
            stylesAndLinks[i].remove();
        }
    }

    /** Append all <style> and <link rel="stylesheet"> elements from a <head> element into the current document. */
    function importRemoteStyles(remoteHead) {
        const remoteStylesAndLinks = remoteHead.querySelectorAll('style, link[rel="stylesheet"]');
        for (const el of remoteStylesAndLinks) {
            const clonedChild = el.cloneNode(true);
            document.head.appendChild(clonedChild);
        }
    }

    /** Clone and append all children from the remote <body> to the target container. */
    function importRemoteContent(remoteBody, targetContainer) {
        for (const child of remoteBody.children) {
            const clone = child.cloneNode(true);
            targetContainer.appendChild(clone);
        }
    }

    /** Ensure our handler loads all child links in the content div. */
    function addLinksToContent(content) {
        const links = content.querySelectorAll('a');
        links.forEach(addLinkClickHandler);
    }

    /** Safely clear the div using the DOM, so all event handlers are cleanly killed without memory leaks. */
    function clearContentDiv(oldContentDiv) {
        // Find the parent element (where the div is located)
        const parent = document.getElementById('parent-container');

        // Remove the old content div
        let nextSibling = null;
        if (oldContentDiv) {
            nextSibling = oldContentDiv.nextSibling;
            oldContentDiv.remove(); // Remove the div along with its children and event listeners
        }

        // Create the new content div, with the same properties as the original.
        const newContentDiv = document.createElement('div');
        newContentDiv.classList.add('content');
        newContentDiv.classList.add('right-column');
        newContentDiv.id = 'content';

        // Insert the new div at the same position
        if (nextSibling) {
            parent.insertBefore(newContentDiv, nextSibling); // Insert it before the next sibling of the old div.
        } else {
            parent.appendChild(newContentDiv); // If no next sibling (so, the last child), append the new div.
        }
        return newContentDiv;
    }

    /**
     * Add an onclick handler to a link.
     * @param {Element} link
     */
    function addLinkClickHandler(link) {
        const attr = link.getAttribute("href");
        if (attr.includes('?i=1')) {
            console.log("Adding onClick to child link: " + attr);
            link.addEventListener('click', clickLink);
        } else {
            console.log("Not adding onClick to primary link: " + attr);
        }
    }

    function clickLink(e) {
        console.log("Onclick link.");
        // Prevent link from navigating.
        e.preventDefault();

        // Remove 'selected' from any previously selected li and apply to current.
        const selectedItems = document.querySelectorAll('li.selected');
        selectedItems.forEach(removeSelectedClass);

        // Can't just use this.parent, as it might be from a link in a template.
        const selectedParent = document.querySelector(`li > a[href="${targetUrl}"]`)?.parentElement;
        if (selectedParent) {
            selectedParent.classList.add('selected'); // Assumes the href is an immediate child of the li.
        }

        const attr = this.getAttribute("href");
        openLinkInline(attr);
    }

    function removeSelectedClass(listItem) {
        listItem.classList.remove('selected');
    }

    /** Just to troll my wife, get a random name for the site. */
    function getSiteName() {
        const a = [
            'Memories', 'Mayhem', 'Merriment', 'Madness', 'Moonshine', 'Mountains', 'Mastery', 'Machines',
            'Messages', 'Metaphor', 'Meteor', 'Mistakes', 'Mondays', 'Mornings', 'Moaning', 'Mystery'
        ];
        return 'Midwest ' + a[~~(Math.random() * a.length)];
    }
</script>

</body>
</html>
