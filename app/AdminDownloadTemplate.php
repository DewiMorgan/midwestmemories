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

    const defaultTimeout = 10000;

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

    // Run prepare and then process
    async function runAll() {
        // Run prepare and then process
        await handleFileTask(
            'Downloading', './admin.php?action=list_files_to_download', './admin.php?action=download_one_file'
        );
        await handleFileTask(
            'Postprocessing', './admin.php?action=list_files_to_process', './admin.php?action=process_one_file'
        );
    }
    runAll();
</script>
