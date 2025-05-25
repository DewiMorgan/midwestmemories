<?php
declare(strict_types=1);
?>
<div id="messages"></div>

<script>
    // Helper to log a new message line
    function logMessage(message) {
        const messagesDiv = document.getElementById('messages');
        const p = document.createElement('p');
        p.textContent = message;
        messagesDiv.appendChild(p);
        return p;
    }

    // Helper to append to the last message
    function updateMessage(element, appendText) {
        element.textContent += appendText;
    }

    // Generic file task handler
    async function handleFileTask(actionName, listEndpoint, fileEndpoint) {
        logMessage(`Getting list of files for ${actionName}...`);

        const listResponse = await fetch(listEndpoint);
        if (listResponse.ok) {
            const files = await listResponse.json();
            const numFiles = files.length;
            if (0 === numFiles) {
                logMessage(`Got zero files for ${actionName}.`);
            }
            // for (const filename of files) {
            for (const [index, filename] of files.entries()) {
                const messageElement = logMessage(`${index + 1}/${numFiles} ${actionName} ${filename}...`);
                const fileResponse = await fetch(fileEndpoint);
                if (fileResponse.ok) {
                    updateMessage(messageElement, ' OK');
                } else {
                    updateMessage(messageElement, ' Failed');
                }
            }
            logMessage(`${actionName} complete!`);
        } else {
            logMessage(`Failed to get list of files for ${actionName}.`);
        }
    }

    async function handleDropboxPolling(url) {
        try {
            while (true) {
                const response = await fetch(url);
                const data = await response.json();

                // Ensure HTTP-level success (status code 2xx)
                if (!response.ok) {
                    logMessage(`HTTP error: ${response.status}`);
                    return;
                }

                // Enforce syntax.
                data.moreFilesToGo ??= false;
                data.numValidFiles ??= 0;
                data.numTotalFiles ??= 0;

                if ("OK" !== data.error) {
                    logMessage(data.error);
                    return;
                }

                if (true === data.moreFilesToGo) {
                    logMessage(`${data.numValidFiles} of ${data.numTotalFiles}, more to come...`);
                } else {
                    logMessage(`${data.numValidFiles} of ${data.numTotalFiles}, finished!`);
                    return;
                }
            }
        } catch (err) {
            logMessage(`Request failed: ${err.message}`);
        }
    }

    // Run prepare and then process
    async function runAllInits() {
        // Get the initial cursor.
        await handleDropboxPolling('./admin.php?action=init_root');
        // Get the remainder of the cursor.
        await handleDropboxPolling('./admin.php?action=continue_root');
    }

    // Run prepare and then process
    async function runAllUpdates() {
        // Get and queue updates from Dropbox.
        await handleDropboxPolling('./admin.php?action=update_dropbox_status');
        // Download queued downloads.
        await handleFileTask(
            'Downloading', './admin.php?action=list_files_to_download', './admin.php?action=download_one_file'
        );
        // Generate queued thumbnails.
        await handleFileTask(
            'Postprocessing', './admin.php?action=list_files_to_process', './admin.php?action=process_one_file'
        );
    }

    function runRelevantTask() {
        const params = new URLSearchParams(window.location.search);
        const action = params.get("action");

        if ("handle_init_root" === action) {
            runAllInits();
        } else if ("handle_queued_files" === action) {
            runAllUpdates();
        }
    }

    runRelevantTask();
</script>
