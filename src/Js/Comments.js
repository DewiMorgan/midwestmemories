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
     * @property {string} comment_text
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
            body: JSON.stringify({comment_text: bodyText})
        });

        if (!response.ok) {
            const errorMessage = `HTTP error ${response.status}`;
            console.error('Failed to post comment:', errorMessage);
            return {error: 'Error', comment_text: errorMessage, user: '', date_created: ''};
        }

        /** @type {Comment} */
        const result = await response.json();
        console.log("Awaited response: ", result);

        if ('OK' !== result.error) {
            const errorMessage = result.error || 'Unknown error from server';
            console.error('Failed to post comment:', errorMessage);
            return {error: 'Error', comment_text: errorMessage, user: '', date_created: ''};
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
        bodyElem.textContent = comment.comment_text;

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
                errorDiv.textContent = result.comment_text || 'Failed to post comment.';
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
