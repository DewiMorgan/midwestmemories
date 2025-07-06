
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

        if (jsonResponse.hasOwnProperty('error')) {
            throw new Error(jsonResponse.error);
        }

        return data;
    }
};


/* Source: Comments.js */
/* jshint esversion: 6 */

/**
 * Handles comment functionality including fetching, displaying, and posting comments.
 */
window.Comments = class {
    constructor(fileId) {
        this.fileId = fileId;
        // Bind methods to maintain 'this' context
        this.handleSubmitComment = this.handleSubmitComment.bind(this);
        this.handleCancelComment = this.handleCancelComment.bind(this);
        this.showCommentEditor = this.showCommentEditor.bind(this);
    }

    /**
     * This comment defines the typedef for the API response that contains a comment.
     * @typedef {Object} Comment
     * @property {string} error
     * @property {string} body_text
     * @property {string} user
     * @property {string} date_created
     */

    /**
     * Fetch all the comments for this file from the API.
     * @return {Promise<Array>} Array of comments.
     */
    async fetchAllComments() {
        const allComments = [];
        const fileId = this.getFileId();
        let currentPage = 0; // Pages start at zero.
        let totalPages = 1; // Start assuming only 1 page until we know otherwise.

        do {
            const response = await fetch(`/api/v1.0/comment?file_id=${fileId}&page_id=${currentPage}`);
            if (!response.ok) {
                throw new Error(`Failed to fetch page ${currentPage}: ${response.statusText}`);
            }

            /** @type {{ success: boolean, data?: Comment[] }} */
            const result = await response.json();
            if (result.success && Array.isArray(result.data)) {
                const comments = result.data;
                allComments.push(...comments);
                // Update `num_pages` from latest comment objects, as more pages may be added as we get the first ones.
                if (0 !== comments.length) {
                    totalPages = comments[0]["num_pages"];
                }
            } else {
                throw new Error(`Unexpected comment response format for page ${currentPage}`);
            }

            currentPage++;
        } while (currentPage < totalPages);
        console.log("Returning comments...", allComments);

        return allComments;
    }

    /**
     * Post a new comment to the server.
     * @param {string} bodyText - The text of the comment to post.
     * @return {Promise<Comment>} The server's response.
     */
    async postComment(bodyText) {
        const fileId = this.getFileId();
        const response = await fetch(`/api/v1.0/comment?file_id=${fileId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({body_text: bodyText})
        });

        if (!response.ok) {
            const errorMessage = `HTTP error ${response.status}`;
            console.error('Failed to post comment:', errorMessage);
            return {error: 'Error', body_text: errorMessage, user: '', date_created: ''};
        }

        /** @type {Comment} */
        const result = await response.json();
        console.log("Awaited response: ", result);

        if ('OK' !== result.error) {
            const errorMessage = result.error || 'Unknown error from server';
            console.error('Failed to post comment:', errorMessage);
            return {error: 'Error', body_text: errorMessage, user: '', date_created: ''};
        }

        return result;
    }

    /**
     * Clear and recreate the comments div.
     * Uses the DOM to ensure event listeners are safely removed, avoiding memory leaks.
     * @return {HTMLElement} The new container for comments.
     */
    clearCommentDiv() {
        console.log("Clearing old comments!");
        // Find the parent element (where the div is located)
        const oldCommentDiv = document.getElementById('comments');
        const parent = document.getElementById('template-content');

        // Remove the old comment div
        if (oldCommentDiv) {
            oldCommentDiv.remove(); // Remove the div along with its children and event listeners.
        }

        // Create the new comment div, with the same properties as the original.
        const newCommentDiv = document.createElement('div');
        newCommentDiv.id = 'comments';

        // Insert the new div.
        parent.appendChild(newCommentDiv);
        return newCommentDiv;
    }

    /**
     * Clear and recreate the comment controls div.
     * Uses the DOM to ensure event listeners are safely removed, avoiding memory leaks.
     * @return {HTMLElement} The new comment controls container.
     */
    clearCommentControlDiv() {
        console.log("Clearing old comment control!");

        // Remove the old comment control div
        const oldCommentControlDiv = document.getElementById('comment-controls');
        if (oldCommentControlDiv) {
            oldCommentControlDiv.remove(); // Remove the div along with its children and event listeners.
        }

        // Create the new comment control div.
        const commentControlDiv = document.createElement('div');
        commentControlDiv.id = 'comment-controls';

        const commentsDiv = document.getElementById('comments');
        // ToDo: sometimes this gives an error, because comments wasn't found, when switching to a thumbs template. Why?
        commentsDiv.appendChild(commentControlDiv);

        return commentControlDiv;
    }

    /**
     * Add the comment control UI elements.
     * @param {HTMLElement} commentControlDiv - The container for the controls.
     */
    addCommentControlUI(commentControlDiv) {
        const addButton = document.createElement('button');
        addButton.textContent = 'Add Comment';
        addButton.onclick = this.showCommentEditor;
        commentControlDiv.appendChild(addButton);
    }

    /**
     * Render a single comment to the DOM.
     * @param {Comment} comment - The comment data to render.
     * @param {HTMLElement} commentsContainer - The container to add the comment to.
     * @return {HTMLElement} The created comment element.
     */
    renderSingleComment(comment, commentsContainer) {
        console.log("Single comment rendering.");
        const commentDiv = document.createElement('div');
        commentDiv.className = 'comment';

        const userElem = document.createElement('strong');
        userElem.textContent = comment.user;

        const dateElem = document.createElement('span');
        dateElem["style"].marginLeft = '10px';
        dateElem.textContent = '(' + comment.date_created + ')';

        const bodyElem = document.createElement('pre'); // preserves formatting
        bodyElem.textContent = comment.body_text;

        const brElem = document.createElement('br');

        commentDiv.appendChild(userElem);
        commentDiv.appendChild(dateElem);
        commentDiv.appendChild(brElem);
        commentDiv.appendChild(bodyElem);

        commentsContainer.appendChild(commentDiv);
        console.log("Comment div added to commentsContainer.");
        return commentDiv;
    }

    /**
     * Show the comment editor UI.
     */
    showCommentEditor() {
        const commentControlDiv = this.clearCommentControlDiv(); // clear controls
        const cols = 60;
        const rows = 4;
        const textarea = document.createElement('textarea');
        textarea.rows = rows;
        textarea.cols = cols;
        textarea.autofocus = true;
        textarea.id = 'comment-textarea';

        const submitButton = document.createElement('button');
        submitButton.textContent = 'Submit';

        /** @type {HTMLButtonElement} */
        const cancelButton = document.createElement('button');
        cancelButton.textContent = 'Cancel';
        cancelButton.style.marginLeft = '10px';

        /** @type {HTMLDivElement} */
        const errorDiv = document.createElement('div');
        errorDiv.style.color = 'red';
        errorDiv.style.marginTop = '5px';
        errorDiv.id = 'comment-error';
        const brElem = document.createElement('br');

        commentControlDiv.appendChild(textarea);
        commentControlDiv.appendChild(brElem);
        commentControlDiv.appendChild(submitButton);
        commentControlDiv.appendChild(cancelButton);
        commentControlDiv.appendChild(errorDiv);

        submitButton.onclick = this.handleSubmitComment;
        cancelButton.onclick = this.handleCancelComment;

        // A little time before changing focus.
        setTimeout(Comments.focusTextarea, 0);
    }

    /**
     * Focus the comment textarea.
     */
    static focusTextarea() {
        /** @type {HTMLTextAreaElement} */
        const textarea = document.getElementById('comment-textarea');
        textarea.focus();
        textarea.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    /**
     * Handle the submit comment button click.
     */
    async handleSubmitComment() {
        const textarea = document.getElementById('comment-textarea');
        const errorDiv = document.getElementById('comment-error');

        const commentText = textarea.value.trim();
        if (!commentText) {
            errorDiv.textContent = 'Comment cannot be empty.';
            return;
        }

        try {
            errorDiv.textContent = 'Submitting...';
            /** @type {Comment} */
            const result = await this.postComment(commentText);
            console.log("Result from postComment: ", result);

            if ('OK' === result.error) {
                // Append this comment.
                const commentsDiv = document.getElementById('comments');
                this.renderSingleComment(result, commentsDiv);

                // Clear the editor and reset the UI.
                const commentControlDiv = this.clearCommentControlDiv();
                this.addCommentControlUI(commentControlDiv);

                // Scroll to show the new comment
                commentControlDiv.scrollIntoView({behavior: 'smooth', block: 'start'});
            } else {
                errorDiv.textContent = result.body_text || 'Failed to post comment.';
            }
        } catch (error) {
            console.error('Error posting comment:', error);
            errorDiv.textContent = 'An error occurred while posting the comment.';
        }
    }

    /**
     * Handle the cancel comment button click.
     */
    handleCancelComment() {
        const commentControlDiv = this.clearCommentControlDiv();
        this.addCommentControlUI(commentControlDiv);
    }

    /**
     * Get the current file ID from the URL.
     * @return {string} The file ID.
     */
    getFileId() {
        return this.fileId;
    }

    /**
     * Display all comments for the current file.
     */
    async displayComments() {
        const commentsContainer = this.clearCommentDiv();
        try {
            console.log("Awaiting the comments.");
            const comments = await this.fetchAllComments();
            console.log("Rendering the comments.");
            for (const comment of comments) {
                console.log("Single comments:");
                this.renderSingleComment(comment, commentsContainer);
            }

            // Add the "Add Comment" button
            console.log("Adding add-comment button:");
            const commentControlDiv = this.clearCommentControlDiv();
            this.addCommentControlUI(commentControlDiv);
            console.log("Displayed comments!");
        } catch (error) {
            console.error('Error displaying comments:', error);
            commentsContainer.textContent = 'Failed to load comments.';
        }
    }

    /**
     * Initialize the commenting functionality.
     */
    setupTemplate() {
        console.log("Fetching comments...");
        // noinspection JSIgnoredPromiseFromCall
        this.displayComments();
    }

    /**
     * Clean up event listeners and resources.
     */
    cleanupTemplate() {
        // Any cleanup needed when the template is being removed
        console.log("Cleaned up files...");
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


/* Source: TreeView.js */
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
        this.addFoldClickHandler = this.addFoldClickHandler.bind(this);
        this.addLinkClickHandler = this.addLinkClickHandler.bind(this);
        this.initializeEventListeners();
    }

    /**
     * Add click handler to a folder if it should be handled by the TreeView.
     * @param {HTMLSpanElement} folder
     */
    addFoldClickHandler = (folder) => {
        console.log("Adding onClick to fold: " + folder.textContent);
        folder.addEventListener('click', this.handleFoldClick);
    };

    /**
     * Add click handler to a link if it should be handled by the TreeView.
     * @param {HTMLLinkElement} link - The link element to add the handler to.
     */
    addLinkClickHandler = (link) => {
        const attr = link.getAttribute("href");
        if (attr && attr.includes('?i=1')) {
            console.log("Adding onClick to child link: " + attr);
            link.addEventListener('click', this.handleLinkClick);
        } else {
            console.log("Not adding onClick to primary link: " + attr);
        }
    };

    /**
     * Add click handlers to all links in the specified container, so our handler can manage them.
     * @param {HTMLElement} content - The container to search for links.
     */
    addLinksToContent = (content) => {
        const links = content.querySelectorAll('a');
        links.forEach(this.addLinkClickHandler);
    };

    /**
     * Call the setupTemplate function if it exists.
     */
    callSetupTemplate = () => {
        console.log("Checking setup exists");
        if ('function' === typeof window.setupTemplate) {
            console.log("Calling setup");
            window.setupTemplate();
        } else {
            console.log("Setup not found");
        }
    };

    /**
     * Clear all styles form the document's head, except the first one.
     * Used to remove any styles we loaded from a previous page load.
     */
    clearAddedStyles = () => {
        const stylesAndLinks = document.head.querySelectorAll('style, link[rel="stylesheet"]');
        for (let i = 1; i < stylesAndLinks.length; i++) {
            stylesAndLinks[i].remove();
        }
    };

    /**
     * Clear and recreate the content div.
     * Uses the DOM to ensure event listeners are safely removed, avoiding memory leaks.
     * @param {HTMLElement} oldContentDiv - The content div to replace.
     * @returns {HTMLElement} The new content div.
     */
    clearContentDiv = (oldContentDiv) => {
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
    };

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
     * Just to troll my wife, get a random name for the site.
     * @returns {string} A random site name.
     */
    getSiteName = () => {
        const a = [
            'Memories', 'Mayhem', 'Merriment', 'Madness', 'Moonshine', 'Mountains', 'Mastery', 'Machines',
            'Messages', 'Metaphor', 'Meteor', 'Mistakes', 'Mondays', 'Mornings', 'Moaning', 'Mystery'
        ];
        return 'Midwest ' + a[~~(Math.random() * a.length)];
    };

    /**
     * Handle folder expand/collapse click.
     * @param {Event} e - The click event.
     */
    handleFoldClick = (e) => {
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
    };

    /**
     * Handle link click events.
     * @param {MouseEvent} e - The click event.
     */
    handleLinkClick = (e) => {
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
    };

    /**
     * Handle navigation via browser back/forward buttons.
     * @param {PopStateEvent} e - The popstate event.
     */
    handleNavigation = (e) => {
        if (e.state) {
            // noinspection JSIgnoredPromiseFromCall
            this.openLinkInline(e.state.html + "?i=1", false);
            document.title = e.state.pageTitle;
        }
    };

    /**
     * Import predefined template elements (content and scripts) from the remote page to the target container.
     * @param {HTMLElement} remoteBody - The body element of the remote document.
     * @param {HTMLElement} targetContainer - The container to import content into.
     */
    importRemoteContent = (remoteBody, targetContainer) => {
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
    };

    /**
     * Import and append all styles from a remote document's head into the current document.
     * @param {HTMLElement} remoteHead - The head element of the remote document.
     */
    importRemoteStyles = (remoteHead) => {
        const remoteStylesAndLinks = remoteHead.querySelectorAll('style, link[rel="stylesheet"]');
        for (const el of remoteStylesAndLinks) {
            const clonedChild = el.cloneNode(true);
            document.head.appendChild(clonedChild);
        }
    };

    /**
     * Static method to initialize the TreeView.
     * This can be used as an event handler directly.
     */
    static init() {
        // noinspection ObjectAllocationIgnored
        return new TreeView();
    }

    // noinspection FunctionWithMultipleLoopsJS
    /**
     * Set up event listeners for the tree view.
     */
    initializeEventListeners = () => {
        // Set up folder expand/collapse handlers

        // Get the clickable elements from the tree view.
        const folders = document.querySelectorAll('.expand-collapse');
        const links = document.querySelectorAll('.path-link');

        // Add listeners to them.
        folders.forEach(this.addFoldClickHandler);
        links.forEach(this.addLinkClickHandler);

        // Add a listener to handle browser back/forward buttons.
        window.onpopstate = this.handleNavigation;
    };

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
     * Remove the 'selected' class from the specified list item.
     * @param listItem
     */
    removeSelectedClass = (listItem) => {
        listItem.classList.remove('selected');
    }
};

