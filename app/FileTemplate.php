<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

/**
 * Template to display a single file and its details.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>ToDo: a file title here</title>
</head>
<body>
<div id="template-content">
    <?php
    // ToDo: Style this.
    // ToDo: Add edit button.
    // ToDo: Add form input fields.
    // ToDo: Add next/prev buttons.

    $u_linkUrl = Path::unixPathToUrl(Index::$requestUnixPath, Path::LINK_RAW);
    $fileDetails = Metadata::getFileDataByUnixPath(Index::$requestUnixPath);
    Log::debug('File data found', $fileDetails);

    // Escape the details array.
    $h_fd = cleanFileDetails($fileDetails);

    // Special cases.
    $h_slide = ($h_fd['slideorigin'] ?? '')
        . ':' . ($h_fd['slidenumber'] ?? '')
        . ':' . ($h_fd['slidesubsection'] ?? '');
    $h_altText = $h_fd['displayname'] ?? '';
    ?>
    <img src="<?= $u_linkUrl ?>" alt="<?= $h_altText ?>" class="file">
    <table>
        <tr>
            <td>Slide:</td>
            <td><?= $h_slide ?></td>
        </tr>
        <tr>
            <td>Written notes:</td>
            <td><?= $h_fd['writtennotes'] ?? '' ?></td>
        </tr>
        <tr>
            <td>Date:</td>
            <td><?= $h_fd['date'] ?? '' ?></td>
        </tr>
        <tr>
            <td>Location:</td>
            <td><?= $h_fd['location'] ?? '' ?></td>
        </tr>
        <tr>
            <td>Photographer:</td>
            <td><?= $h_fd['photographer'] ?? '' ?></td>
        </tr>
        <tr>
            <td>People:</td>
            <td><?= $h_fd['people'] ?? '' ?></td>
        </tr>
        <tr>
            <td>Keywords:</td>
            <td><?= $h_fd['keywords'] ?? '' ?></td>
        </tr>
    </table>
    <?php

    /**
     * Convert the raw file details into an HTML-escaped version.
     * @param array $fileDetails Array from which to HTML escape all fields.
     * @return array The resulting escaped array.
     */
    function cleanFileDetails(array $fileDetails): array
    {
        $h_fd = [];
        foreach ($fileDetails as $key => $fileDetail) {
            if (is_array($fileDetail)) {
                if ('date' === $key) {
                    $h_fd[$key] = htmlspecialchars($fileDetail['dateString']);
                } else {
                    $h_fd[$key] = htmlspecialchars(implode(', ', $fileDetail));
                }
            } elseif (is_numeric($fileDetail)) {
                $h_fd[$key] = $fileDetail;
            } elseif (is_string($fileDetail) && '' !== $fileDetail) {
                $h_fd[$key] = htmlspecialchars($fileDetail);
            } else {
                $h_fd[$key] = match ($key) {
                    'slideorigin', 'slidenumber', 'slidesubsection' => '?',
                    'displayname' => 'unknown image',
                    default => 'unknown',
                };
            }
        }
        return $h_fd;
    }

    /**
     * Get the ID of the displayed file.
     * @return int
     */
    function getFileId(): int
    {
        $webPath = Index::$requestWebPath;
        $dropboxPath = Conf::get(Key::IMAGE_DIR) . $webPath;
        $sql = 'SELECT `id` FROM `' . Db::TABLE_FILE_QUEUE . '` WHERE `full_path` = ?';
        return intval(Db::sqlGetItem($sql, 'id', 's', $dropboxPath));
    }

    ?>
    <div id="comments"></div>
</div><!-- End template-content div-->

<script id="template-script">
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
     * @returns {Promise<*[]>}
     */
    async function fetchAllComments() {
        const allComments = [];
        const fileId = getFileId();
        let currentPage = 0; // Pages start at zero.
        let totalPages = 1; // Start assuming only 1 page until we know otherwise.

        do {
            const response = await fetch(`/v1/comment/${fileId}/${currentPage}`);
            if (!response.ok) {
                throw new Error(`Failed to fetch page ${currentPage}: ${response.statusText}`);
            }

            /** @type {Comment[]} */
            const comments = await response.json();
            allComments.push(...comments);

            // Update `num_pages` from latest comment objects, as more pages may be added as we get the first ones.
            if (0 !== comments.length) {
                totalPages = comments[0]["num_pages"];
            }

            currentPage++;
        } while (currentPage < totalPages);
        console.log("Returning comments...", allComments);

        return allComments;
    }

    /**
     * @param bodyText
     * @returns {Promise<Comment>}
     */
    async function postComment(bodyText) {
        const fileId = getFileId();
        const response = await fetch(`/v1/comment/${fileId}`, {
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

    /** Safely clear the div using the DOM, so all event handlers are cleanly killed without memory leaks. */
    function clearCommentDiv() {
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

    /** Safely clear the div using the DOM, so all event handlers are cleanly killed without memory leaks. */
    function clearCommentControlDiv() {
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
        commentsDiv.appendChild(commentControlDiv);

        return commentControlDiv;
    }

    function addCommentControlUI(commentControlDiv) {
        const addButton = document.createElement('button');
        addButton.textContent = 'Add Comment';
        addButton.onclick = showCommentEditor;
        commentControlDiv.appendChild(addButton);
    }

    /**
     *
     * @param {Comment} comment
     * @param commentsContainer
     * @returns {Element}
     */
    function renderSingleComment(comment, commentsContainer) {
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


    function showCommentEditor() {
        const commentControlDiv = clearCommentControlDiv(); // clear controls
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

        submitButton.onclick = handleSubmitComment;
        cancelButton.onclick = handleCancelComment;

        // A little time before changing focus.
        setTimeout(focusTextarea, 0);
    }

    function focusTextarea() {
        /** @type {HTMLTextAreaElement} */
        const textarea = document.getElementById('comment-textarea');
        textarea.focus();
        textarea.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    async function handleSubmitComment() {
        const textarea = document.getElementById('comment-textarea');
        const errorDiv = document.getElementById('comment-error');
        const commentsDiv = document.getElementById('comments');

        const bodyText = textarea['value'].trim();
        if (!bodyText) {
            errorDiv.textContent = 'Comment cannot be empty.';
            return;
        }

        errorDiv.textContent = 'Submitting...';
        /** @type {Comment} */
        const result = await postComment(bodyText);
        console.log("Result from postComment: ", result);

        if ('OK' === result.error) {
            /** @type {Comment} */
            const comment = {
                'error': 'OK',
                'user': result.user,
                'date_created': result.date_created,
                'body_text': result.body_text
            };
            renderSingleComment(comment, commentsDiv);
            const commentControlDiv = clearCommentControlDiv();
            addCommentControlUI(commentControlDiv);
            commentControlDiv.scrollIntoView({behavior: 'smooth', block: 'start'});
        } else {
            errorDiv.textContent = result.error;
        }
    }

    function handleCancelComment() {
        const commentControlDiv = clearCommentControlDiv();
        addCommentControlUI(commentControlDiv);
    }

    function getFileId() {
        return <?= getFileId() ?>;
    }

    async function displayComments() {
        const commentsContainer = clearCommentDiv();

        try {
            console.log("Awaiting the comments.");
            const comments = await fetchAllComments();
            console.log("Rendering the comments.");
            for (const comment of comments) {
                console.log("Single comments:");
                renderSingleComment(comment, commentsContainer);
            }
            console.log("Adding add-comment button:");
            const commentControlDiv = clearCommentControlDiv();
            addCommentControlUI(commentControlDiv);
            console.log("Displayed comments!");
        } catch (error) {
            console.log("Error displaying the comments.");
            commentsContainer.textContent = 'Failed to load comments.';
            console.error('Error displaying comments:', error);
        }
    }

    function setupTemplate() {
        console.log("Fetching comments...");
        displayComments();
    }

    function cleanupTemplate() {
        console.log("Cleaned up files...");
    }
</script>
</body>
</html>
