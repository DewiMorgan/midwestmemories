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
        logMessage(`Getting list of files to ${actionName}...`);

        const listResponse = await fetch(listEndpoint);
        if (listResponse.ok) {
            const files = await listResponse.json();
            logMessage(`Got list of ${files.length} files to ${actionName}.`);

            for (const filename of files) {
                const messageElement = logMessage(`${actionName} ${filename}...`);
                const fileResponse = await fetch(fileEndpoint);
                if (fileResponse.ok) {
                    updateMessage(messageElement, ' OK');
                } else {
                    updateMessage(messageElement, ' Failed');
                }
            }
        } else {
            logMessage(`Failed to get list of files to ${actionName}.`);
        }
    }

    // Run prepare and then process
    handleFileTask('prepare', './admin.php?action=list_files_to_download', './admin.php?action=download_one_file');
    handleFileTask('process', './admin.php?action=list_files_to_process', './admin.php?action=process_one_file');
</script>
