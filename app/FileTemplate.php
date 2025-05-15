<?php

declare(strict_types=1);

namespace MidwestMemories;

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
<div id="templateContent">
    <?php
    // ToDo: Style this.
    // ToDo: Add edit button.
    // ToDo: Add form input fields.
    // ToDo: Add next/prev buttons.

    $u_linkUrl = Path::unixPathToUrl(Index::$requestUnixPath, Path::LINK_RAW);
    $fileDetails = Metadata::getFileDataByUnixPath(Index::$requestUnixPath);

    // Escape the details array.
    $h_fd = cleanFileDetails($fileDetails);

    // Special cases.
    $h_slide = $h_fd['slideorigin'] . ':' . $h_fd['slidenumber'] . ':' . $h_fd['slidesubsection'];
    $h_altText = $h_fd['displayname'];
    ?>
    <h1 class="center"><?= $h_fd['displayname'] ?></h1>

    <img src="<?= $u_linkUrl ?>" alt="<?= $h_altText ?>" class="file">
    <p><?= $h_fd['writtennotes'] ?></p>
    <table>
        <tr>
            <td>Slide:</td>
            <td><?= $h_slide ?></td>
        </tr>
        <tr>
            <td>Date:</td>
            <td><?= $h_fd['date'] ?></td>
        </tr>
        <tr>
            <td>Location:</td>
            <td><?= $h_fd['location'] ?></td>
        </tr>
        <tr>
            <td>Photographer:</td>
            <td><?= $h_fd['photographer'] ?></td>
        </tr>
        <tr>
            <td>People:</td>
            <td><?= $h_fd['people'] ?></td>
        </tr>
        <tr>
            <td>Keywords:</td>
            <td><?= $h_fd['keywords'] ?></td>
        </tr>
        <tr>
            <td>Visitor Notes:</td>
            <td><?= $h_fd['visitornotes'] ?></td>
        </tr>
    </table>

    <!--
    <form>
        <label>
            <input type="text">
        </label>
    </form>
    -->
    <?php
    // DELETEME DEBUG
    echo '<hr><h3>Debugging stuff below this line</h3>';

    echo '<pre>' . basename(Index::$requestUnixPath) . " file details:\n" . var_export($fileDetails, true) . "</pre>\n";

    // END DELETEME DEBUG

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

    ?>
    <div id="comments"></div>
</div><!-- End templateContent div-->
<script id="templateScript">
    async function fetchAllComments(imageId) {
        const allComments = [];
        let currentPage = 0; // Pages start at zero.
        let totalPages = 1; // start assuming only 1 page until we know otherwise.

        do {
            const response = await fetch(`/v1/comment/${imageId}/${currentPage}`);
            if (!response.ok) {
                throw new Error(`Failed to fetch page ${currentPage}: ${response.statusText}`);
            }

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


    async function displayComments(imageId) {
        const oldCommentDiv = document.getElementById('comments');
        const commentsContainer = clearCommentDiv(oldCommentDiv);

        try {
            console.log("Awaiting the comments.");
            const comments = await fetchAllComments(imageId);
            console.log("Rendering the comments.");
            renderComments(comments);
            console.log("Displayed comments!");
        } catch (error) {
            console.log("Error displaying the comments.");
            commentsContainer.textContent = 'Failed to load comments.';
            console.error('Error displaying comments:', error);
        }
    }

    /** Safely clear the div using the DOM, so all event handlers are cleanly killed without memory leaks. */
    function clearCommentDiv(oldCommentDiv) {
        console.log("Clearing old comments!");
        // Find the parent element (where the div is located)
        const parent = document.getElementById('parent-container');

        // Remove the old content div
        let nextSibling = null;
        if (oldCommentDiv) {
            nextSibling = oldCommentDiv.nextSibling;
            oldCommentDiv.remove(); // Remove the div along with its children and event listeners
        }

        // Create the new content div, with the same properties as the original.
        const newCommentDiv = document.createElement('div');
        newCommentDiv.id = 'comment';

        // Insert the new div at the same position
        if (nextSibling) {
            parent.insertBefore(newCommentDiv, nextSibling); // Insert it before the next sibling of the old div.
        } else {
            parent.appendChild(newCommentDiv); // If no next sibling (so, the last child), append the new div.
        }
        return newCommentDiv;
    }

    function renderComments(comments, commentsContainer) {
        console.log("Render comments:");
        for (const comment of comments) {
            console.log("Single comments:");
            renderSingleComment(comment, commentsContainer);
        }
    }

    function renderSingleComment(comment, commentsContainer) {
        console.log("Single comment rendering.");
        const commentDiv = document.createElement('div');
        commentDiv.className = 'comment';

        const userElem = document.createElement('strong');
        userElem.textContent = comment.user;

        const dateElem = document.createElement('span');
        dateElem["style"].marginLeft = '10px';
        dateElem.textContent = '(' + comment['date_created'] + ')';

        const bodyElem = document.createElement('pre'); // preserves formatting
        bodyElem.textContent = comment['body_text'];

        const brElem = document.createElement('br');

        commentDiv.appendChild(userElem);
        commentDiv.appendChild(dateElem);
        commentDiv.appendChild(brElem);
        commentDiv.appendChild(bodyElem);

        commentsContainer.appendChild(commentDiv);
        console.log("Comment div added to commentsContainer.");
    }

    function setupTemplate() {
        console.log("Fetching comments...");
        displayComments(<?= 6 ?>);
    }

    function cleanupTemplate() {
        console.log("Cleaned up files...");
    }
</script>

</body>
</html>
