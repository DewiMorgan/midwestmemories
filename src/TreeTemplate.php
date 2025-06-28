<?php

/** This is the template to display the tree navigation. */
declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php IndexGateway::getSiteName(); ?> - Folder Tree</title>
    <!--suppress HtmlUnknownTarget -->
    <link rel="stylesheet" href="/raw/user.css">
    <!--suppress HtmlUnknownTarget -->
    <script src="/raw/user.js"></script>
</head>
<?php
$u_linkUrl = Path::unixPathToUrl($_REQUEST['path'] ?? '/', Path::LINK_INLINE);
?>
<body onload="openLinkInline('<?= $u_linkUrl ?>')">
Welcome, <?= htmlspecialchars(User::getInstance()->username) ?>!
<form method="post" style="display: inline;">
    <input type="hidden" name="action" value="logout">
    <button type="submit" style="margin-left: 10px;">Logout</button>
</form>

<div class="flex-container" id="parent-container">
    <div class="tree-view left-column">
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
            $items = scandir($scanUnixDir);

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
            // Report our failure.
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

    /** Get predefined template elements from the remote page to the target container.
     * @param remoteBody The downloaded web page.
     * @param targetContainer The div we should put the body text into.
     */
    function importRemoteContent(remoteBody, targetContainer) {
        // Cleanup from previous template
        if ('function' === typeof window.cleanupTemplate) {
            window.cleanupTemplate();
            window.cleanupTemplate = undefined;
        }

        // Load new content
        const content = remoteBody.querySelector('#template-content');
        const script = remoteBody.querySelector('#template-script');

        if (content) {
            const clonedNode = content.cloneNode(true);
            targetContainer.appendChild(clonedNode);
        }

        if (script) {
            const newScript = document.createElement('script');
            newScript.textContent = script.textContent;
            targetContainer.appendChild(newScript);

            // Wait for DOM update and script execution.
            // Use a small timeout to ensure the script has time to define setupTemplate.
            console.log("Waiting to call setup");
            setTimeout(callSetupTemplate, 100);
        }
    }

    function callSetupTemplate() {
        console.log("Checking setup exists");
        if ('function' === typeof window.setupTemplate) {
            console.log("Calling setup");
            window.setupTemplate();
        } else {
            console.log("Setup not found");
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
        const targetUrl = e.currentTarget.href;
        const selectedParent = document.querySelector(`li > a[href="${targetUrl}"]`)?.parentElement;
        if (selectedParent) {
            selectedParent.classList.add('selected'); // Assumes the href is an immediate child of the li.
            if (selectedParent.classList.contains('collapsed')) {
                const child = selectedParent.querySelector('.expand-collapse');
                if (child && 'function' === typeof child.click) {
                    child.click(); // Call the click handler of the expander, to expand and swap icons.
                }
            }
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
