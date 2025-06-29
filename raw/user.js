
/* Source: Api.js */
/* jshint esversion: 6 */
window.Api = class {
    /**
     * API wrapper to call an endpoint and return the data object, or an exception on error.
     * @template T
     * @param {string} url - The API endpoint to fetch.
     * @param {string} [method='GET'] - The HTTP method.
     * @param {Object|null} [payload=null] - Optional payload for POST/PUT/PATCH.
     * @param {'string'|'object'|'array'} expectedType - Expected type for the `data` payload.
     * @returns {Promise<T>} - The validated data response.
     * @throws {Error} - If the response status or data type is incorrect.
     */
    // noinspection FunctionWithMoreThanThreeNegationsJS
    static async fetchApiData(url, method = 'GET', expectedType = 'array', payload = null) {
        const options = {
            method,
            headers: {
                'Accept': 'application/json',
            }
        };

        if (null !== payload && ['POST', 'PUT', 'PATCH'].includes(method)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(payload);
        }

        console.log(`Making API call ${method} ${url} ${expectedType}`, options); // DELETEME DEBUG

        const response = await fetch(url, options);

        if (!response.ok) {
            throw new Error(`HTTP error: ${response.status}`);
        }

        const jsonResponse = await response.json();

        if (!jsonResponse.hasOwnProperty('data')) {
            throw new Error("Response JSON does not contain a 'data' property.");
        }

        const data = jsonResponse.data;

        const actualType = Array.isArray(data) ? 'array' : typeof data;
        if (actualType !== expectedType) {
            console.log(`Received ${actualType}, expected ${expectedType}`, data);
            throw new Error(`Expected 'data' to be ${expectedType}, but got ${actualType}.`);
        }

        if (jsonResponse.hasOwnProperty('error') && "OK" !== jsonResponse.error) {
            throw new Error(jsonResponse.error);
        }

        return data;
    }
};


/* Source: HtmlUtils.js */
/* jshint esversion: 6 */
window.HtmlUtils = class {
    static disabledOpacity = '0.5';

    /**
     * Enable or disable a button element.
     * @param {HTMLButtonElement|null} buttonElement
     * @param {boolean} isEnabled
     */
    static setButtonEnabled(buttonElement, isEnabled) {
        if (buttonElement) {
            if ('disabled' in buttonElement) {
                /** @type {{ disabled: boolean }} */ (buttonElement).disabled = !isEnabled;
            }

            buttonElement.style.opacity = isEnabled ? '' : this.disabledOpacity;
            buttonElement.style.cursor = isEnabled ? '' : 'default';
        }
    }

    static createTd(content) {
        const cell = document.createElement('td');
        if (content) {
            cell.appendChild(content);
        }
        return cell;
    }

    static createTextSpan(className, text) {
        const span = document.createElement('span');
        span.className = className;
        span.textContent = text;
        return span;
    }

    static createInput(className, value = '') {
        /** @type {HTMLInputElement} */
        const input = document.createElement('input');
        input.type = 'text';
        input.className = className;
        input.value = value;
        input.style.display = 'none';
        return input;
    }

    /**
     * Create a styled HTML button component.
     * @param {string} labelText Text or icon to display.
     * @param {string} className
     * @param {(event: MouseEvent) => void} [handler] Onclick callback.
     * @param {HTMLTableRowElement} [row]
     * @param {boolean} [hidden=false]
     * @returns {HTMLButtonElement}
     */
    static createButton(labelText, className, handler = null, row = null, hidden = false) {
        /** @type {HTMLButtonElement} */
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = labelText;
        button.className = className;
        if (handler) {
            const boundHandler = row ? handler.bind(null, row) : handler;
            button.addEventListener('click', boundHandler);
        }
        if (hidden) {
            button.style.display = 'none';
        }
        return button;
    }

};


/* Source: DragBar.js */
/* jshint esversion: 6 */

/**
 * Manages the vertical draggable bar between left and right columns for resizing.
 */
window.DragBar = class {
    /**
     * Initialize the drag bar functionality.
     */
    constructor() {
        this.dragBar = document.querySelector('.drag-bar');
        this.leftColumn = document.querySelector('.left-column');
        this.rightColumn = document.querySelector('.right-column');

        this.isDragging = false;
        this.currentX = 0;
        this.leftColumnWidth = 0;
        this.rightColumnWidth = 0;

        // Bind event handlers to maintain 'this' context
        this.handleMouseMove = this.handleMouseMove.bind(this);
        this.handleMouseDown = this.handleMouseDown.bind(this);
        this.handleMouseUp = this.handleMouseUp.bind(this);

        this.initializeEventListeners();
    }

    /**
     * Set up event listeners for the drag bar.
     */
    initializeEventListeners() {
        this.dragBar.addEventListener('mousedown', this.handleMouseDown);
        document.addEventListener('mousemove', this.handleMouseMove);
        document.addEventListener('mouseup', this.handleMouseUp);
    }

    /**
     * Handle mouse move events for dragging.
     * @param {MouseEvent} e - The mouse event.
     */
    handleMouseMove(e) {
        if (this.isDragging) {
            e.preventDefault();
            const deltaX = e.clientX - this.currentX;
            const newLeftColumnWidth = Math.max(50, this.leftColumnWidth + deltaX);
            const newRightColumnWidth = Math.max(50, this.rightColumnWidth - deltaX);

            this.leftColumn.style.width = `${newLeftColumnWidth}px`;
            this.rightColumn.style.width = `${newRightColumnWidth}px`;
        }
    }

    /**
     * Handle mouse down event on the drag bar.
     * @param {MouseEvent} e - The mouse event.
     */
    handleMouseDown(e) {
        // Reselect right column as it may have been recreated
        this.rightColumn = document.querySelector('.right-column');
        this.isDragging = true;
        this.currentX = e.clientX;
        this.leftColumnWidth = this.leftColumn.offsetWidth;
        this.rightColumnWidth = this.rightColumn.offsetWidth;
    }

    /**
     * Handle mouse up event to stop dragging.
     */
    handleMouseUp() {
        this.isDragging = false;
    }

    /**
     * Static method to initialize the drag bar.
     * This can be used as an event handler directly.
     */
    static init() {
        // noinspection ObjectAllocationIgnored
        new DragBar();
    }
};

// Initialize the drag bar when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', DragBar.init);


/* Source: Log.js */
/* jshint esversion: 6 */

/**
 * Handles logging messages to the admin interface.
 */
window.Log = class {
    /**
     * Log a message to the `messages` container.
     * @param {string} message - The message to log.
     * @returns {HTMLParagraphElement} The created paragraph element.
     */
    static message(message) {
        const messagesDiv = document.getElementById('messages');
        if (!messagesDiv) {
            console.warn('Messages container not found');
            return null;
        }

        const p = document.createElement('p');
        p.textContent = message;
        messagesDiv.appendChild(p);

        const autoscroll = document.getElementById('autoscroll');
        if (autoscroll?.checked) {
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        return p;
    }

    /**
     * Clear all messages from the messages container.
     */
    static clear() {
        const messagesDiv = document.getElementById('messages');
        if (messagesDiv) {
            messagesDiv.replaceChildren();
        }
    }
};


/* Source: UserPage.js */
/**
 * UserPage.js
 * Handle functionality for non-admin pages.
 */

///////////////////
// Tree view event listeners to handle expand/collapse behavior.
///////////////////

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

///////////////////
// Tree view event listeners to handle link-click behavior, and the initial onLoad().
///////////////////

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

