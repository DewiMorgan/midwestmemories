/* jshint esversion: 6 */

/**
 * TreeView.js
 * Handles tree view navigation and content loading functionality.
 */
window.TreeView = class {
    /**
     * Initialize the TreeView functionality.
     */
    constructor() {
        // Bind methods to maintain 'this' context
        this.handleNavigation = this.handleNavigation.bind(this);
        this.handleFoldClick = this.handleFoldClick.bind(this);
        this.handleLinkClick = this.handleLinkClick.bind(this);

        this.initializeEventListeners();
    }

    // noinspection FunctionWithMultipleLoopsJS
    /**
     * Set up event listeners for the tree view.
     */
    initializeEventListeners() {
        // Set up folder expand/collapse handlers

        // Get the clickable elements from the tree view.
        const folders = document.querySelectorAll('.expand-collapse');
        const links = document.querySelectorAll('.path-link');

        // Add listeners to them.
        folders.forEach(this.addFoldClickHandler);
        links.forEach(this.addLinkClickHandler);

        // Add a listener to handle browser back/forward buttons.
        window.onpopstate = this.handleNavigation;
    }

    addFoldClickHandler(folder) {
        console.log("Adding onClick to fold: " + folder.textContent);
        folder.addEventListener('click', foldElement);
    }


    /**
     * Handle folder expand/collapse click.
     * @param {Event} e - The click event.
     */
    handleFoldClick(e) {
        const target = e.currentTarget;
        const parent = target.parentElement;

        // Toggle the expand/collapse state.
        parent.classList.toggle("expanded");
        parent.classList.toggle("collapsed");

        // Toggle the icon.
        if ('<?= ICON_EXPANDED ?>' === target.textContent) {
            target.textContent = '<?= ICON_COLLAPSED ?>';
        } else if ('<?= ICON_COLLAPSED ?>' === target.textContent) {
            target.textContent = '<?= ICON_EXPANDED ?>';
        }

        e.stopPropagation();
    }

    /**
     * Handle navigation via browser back/forward buttons.
     * @param {PopStateEvent} e - The popstate event.
     */
    handleNavigation(e) {
        if (e.state) {
            // noinspection JSIgnoredPromiseFromCall
            this.openLinkInline(e.state.html + "?i=1", false);
            document.title = e.state.pageTitle;
        }
    }

    /**
     * Open a clicked link and load its content inline into the content div.
     * @param {string} url - The link URL to load.
     * @param {boolean} saveHistory True to add the followed link to browser history.
     * false for the default back/forward button handling.
     * @returns {Promise<void>}
     */
    async openLinkInline(url, saveHistory = true) {
        console.log("Opening link inline: " + url);

        const content = document.getElementById("content");
        const newContent = this.clearContentDiv(content);
        this.clearAddedStyles();

        let title;
        try {
            const doc = await this.fetchRemoteDocument(url);
            title = doc.querySelector('title')?.innerText;
            this.importRemoteStyles(doc.head);
            this.importRemoteContent(doc.body, newContent);
            console.log("Inline content loaded.");
        } catch (error) {
            // Report our failure.
            console.error(error);
            title = 'Error loading page';
            const element = document.createElement('h1');
            element.textContent = title;
            newContent.appendChild(element);
        }
        document.title = this.getSiteName() + ' - ' + title;

        // Ensure our handler loads all child links in the content div.
        this.addLinksToContent(newContent);

        // Ensure that history will work.
        if (saveHistory) {
            const historyUrl = url.replace(/(?:\?|&(?:amp;)?)i=\d+/, '');
            console.log("Updating URL to '" + historyUrl + "'.");
            window.history.pushState({"html": historyUrl, "pageTitle": title}, '', historyUrl);
        }
    }

    /**
     * Fetch and parse a remote HTML document.
     * @param {string} url - The URL to fetch.
     * @returns {Promise<Document>}
     */
    async fetchRemoteDocument(url) {
        const response = await fetch(url);
        const html = await response.text();
        const parser = new DOMParser();
        return parser.parseFromString(html, 'text/html');
    }

    /**
     * Clear all styles form the document's head, except the first one.
     * Used to remove any styles we loaded from a previous page load.
     */
    clearAddedStyles() {
        const stylesAndLinks = document.head.querySelectorAll('style, link[rel="stylesheet"]');
        for (let i = 1; i < stylesAndLinks.length; i++) {
            stylesAndLinks[i].remove();
        }
    }

    /**
     * Import and append all styles from a remote document's head into the current document.
     * @param {HTMLElement} remoteHead - The head element of the remote document.
     */
    importRemoteStyles(remoteHead) {
        const remoteStylesAndLinks = remoteHead.querySelectorAll('style, link[rel="stylesheet"]');
        for (const el of remoteStylesAndLinks) {
            const clonedChild = el.cloneNode(true);
            document.head.appendChild(clonedChild);
        }
    }

    /**
     * Import predefined template elements (content and scripts) from the remote page to the target container.
     * @param {HTMLElement} remoteBody - The body element of the remote document.
     * @param {HTMLElement} targetContainer - The container to import content into.
     */
    importRemoteContent(remoteBody, targetContainer) {
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

        // The script is used to handle any template-specific setup.
        if (script) {
            const newScript = document.createElement('script');
            newScript.textContent = script.textContent;
            targetContainer.appendChild(newScript);

            // Wait for DOM update and script execution.
            // Use a small timeout to ensure the script has time to define setupTemplate.
            console.log("Waiting to call setup");
            setTimeout(this.callSetupTemplate, 100);
        }
    }

    /**
     * Call the setupTemplate function if it exists.
     */
    callSetupTemplate() {
        console.log("Checking setup exists");
        if ('function' === typeof window.setupTemplate) {
            console.log("Calling setup");
            window.setupTemplate();
        } else {
            console.log("Setup not found");
        }
    }

    /**
     * Add click handlers to all links in the specified container, so our handler can manage them.
     * @param {HTMLElement} content - The container to search for links.
     */
    addLinksToContent(content) {
        const links = content.querySelectorAll('a');
        links.forEach(this.addLinkClickHandler);
    }

    /**
     * Clear and recreate the content div.
     * Uses the DOM to ensure event listeners are safely removed, avoiding memory leaks.
     * @param {HTMLElement} oldContentDiv - The content div to replace.
     * @returns {HTMLElement} The new content div.
     */
    clearContentDiv(oldContentDiv) {
        const parent = document.getElementById('parent-container');

        // Remove the old content div
        let nextSibling = null;
        if (oldContentDiv) {
            nextSibling = oldContentDiv.nextSibling;
            oldContentDiv.remove(); // Remove the div along with its children and event listeners
        }

        // Create the new content div, with the same properties as the original.
        const newContentDiv = document.createElement('div');
        newContentDiv.classList.add('content', 'right-column');
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
     * Add click handler to a link if it should be handled by the TreeView.
     * @param {HTMLLinkElement} link - The link element to add the handler to.
     */
    addLinkClickHandler(link) {
        const attr = link.getAttribute("href");
        if (attr && attr.includes('?i=1')) {
            console.log("Adding onClick to child link: " + attr);
            link.addEventListener('click', this.handleLinkClick);
        } else {
            console.log("Not adding onClick to primary link: " + attr);
        }
    }

    /**
     * Handle link click events.
     * @param {MouseEvent} e - The click event.
     */
    handleLinkClick(e) {
        console.log("Onclick link.");
        // Prevent link from navigating.
        e.preventDefault();

        // Remove 'selected' from any previously selected li and apply to current.
        const selectedItems = document.querySelectorAll('li.selected');
        selectedItems.forEach(this.removeSelectedClass);

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

        const attr = e.currentTarget.getAttribute("href");
        // noinspection JSIgnoredPromiseFromCall
        this.openLinkInline(attr);
    }

    removeSelectedClass(listItem) {
        listItem.classList.remove('selected');
    }

    /**
     * Just to troll my wife, get a random name for the site.
     * @returns {string} A random site name.
     */
    getSiteName() {
        const a = [
            'Memories', 'Mayhem', 'Merriment', 'Madness', 'Moonshine', 'Mountains', 'Mastery', 'Machines',
            'Messages', 'Metaphor', 'Meteor', 'Mistakes', 'Mondays', 'Mornings', 'Moaning', 'Mystery'
        ];
        return 'Midwest ' + a[~~(Math.random() * a.length)];
    }

    /**
     * Static method to initialize the TreeView.
     * This can be used as an event handler directly.
     */
    static init() {
        // noinspection ObjectAllocationIgnored
        return new TreeView();
    }
};
