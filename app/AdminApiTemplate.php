<?php
declare(strict_types=1);

/*
 * Mostly javascript functions for handling API responses.
 */
?>
<div id="messages"></div>

<script>
    // Helper to log a new message line.
    function logMessage(message) {
        const messagesDiv = document.getElementById('messages');
        const p = document.createElement('p');
        p.textContent = message;
        messagesDiv.appendChild(p);
        return p;
    }

    // Helper to append to the last message.
    function updateMessage(element, appendText) {
        element.textContent += appendText;
    }

    // Generic file task handler.
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


    async function handleListUsers(listEndpoint) {
        const actionName = 'list_users';
        logMessage(`Getting list of users for ${actionName}...`);

        const listResponse = await fetch(listEndpoint);
        if (listResponse.ok) {
            const users = await listResponse.json();
            const numUsers = users.length;
            if (0 === numUsers) {
                logMessage(`Got zero users for ${actionName}.`);
            }
            const table = createUserTable(users);
            const messagesDiv = document.getElementById('messages');
            messagesDiv.appendChild(table);
            logMessage(`${actionName} complete!`);
        } else {
            logMessage(`Failed to get list of users for ${actionName}.`);
        }
    }

    function createButton(text, className) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = text;
        if (className) {
            btn.className = className;
        }
        return btn;
    }

    // Cancel editing: restore original password and edit button
    function cancelEdit(row, originalPassword) {
        const passCell = row.cells[1];
        const editCell = row.cells[2];
        passCell.textContent = originalPassword;
        editCell.textContent = '';
        const editBtn = createButton('🖉', 'edit-btn');
        editBtn.addEventListener('click', () => startEdit(row));
        editCell.appendChild(editBtn);
    }

    // Start editing a row
    function startEdit(row) {
        const username = row.cells[0].textContent;
        const passCell = row.cells[1];
        const editCell = row.cells[2];
        const originalPassword = passCell.textContent;

        // Replace password cell content with an input
        const input = document.createElement('input');
        input.type = 'text';
        input.value = originalPassword;
        passCell.textContent = '';
        passCell.appendChild(input);

        // Replace edit cell content with save and cancel buttons
        editCell.textContent = '';

        const saveBtn = createButton('💾', 'save-btn');
        saveBtn.addEventListener('click', function() {
            handleSaveClick(username, input, passCell, editCell, row);
        });

        const cancelBtn = createButton('❌', 'cancel-btn');
        cancelBtn.addEventListener('click', () => cancelEdit(row, originalPassword));

        editCell.appendChild(saveBtn);
        editCell.appendChild(cancelBtn);

        input.focus();
    }

    function handleDeleteClick(username, userCell, editCell, deleteBtn) {
        const confirmation = prompt(`Delete user "${username}"? Are you sure? (y/n)`);
        if (confirmation && 'y' === confirmation.toLowerCase()) {
            // Strike through the username
            userCell.style.textDecoration = 'line-through';

            // Disable the edit button if present
            const editBtn = editCell.querySelector('.edit-btn');
            if (editBtn) {
                editBtn.disabled = true;
                editBtn.style.opacity = 0.5;
                editBtn.style.cursor = 'default';
            }
            deleteBtn.disabled = true;
            deleteBtn.style.opacity = 0.5;
            deleteBtn.style.cursor = 'default';
        }
    }

    function handleSaveClick(username, input, passCell, editCell, row) {
        const confirmation = prompt('Save password: are you sure? (y/n)');
        if (confirmation && 'y' === confirmation.toLowerCase()) {
            const newPass = input.value;
            savePassword(username, newPass);
            passCell.textContent = newPass;

            // Restore edit button
            editCell.textContent = '';
            const editBtn = createButton('✏️', 'edit-btn');
            editBtn.addEventListener('click', () => startEdit(row));
            editCell.appendChild(editBtn);
        }
        // Else: do nothing, remain in edit mode
    }

    function createUserHeaderRow() {
        const headerRow = document.createElement('tr');
        const headers = ['Del', 'Username', 'Password', 'Edit'];
        for (let i = 0; 4 > i; i++) {
            const th = document.createElement('th');
            th.textContent = headers[i];
            headerRow.appendChild(th);
        }
        return headerRow;
    }

    function createUserTable(userList) {
        const table = document.createElement('table');
        const headerRow = createUserHeaderRow();
        table.appendChild(headerRow);
        table.border = '1';

        // Populate rows
        for (let i = 0; i < userList.length; i++) {
            const [username, password] = userList[i];
            const row = document.createElement('tr');
            const deleteCell = document.createElement('td');
            const userCell = document.createElement('td');
            const passCell = document.createElement('td');
            const editCell = document.createElement('td');
            const deleteBtn = createButton('🗑️', 'save-btn');
            const editBtn = createButton('✏️', 'edit-btn');
            deleteBtn.addEventListener('click', () => startDelete(row));
            deleteCell.appendChild(deleteBtn);
            userCell.textContent = username;
            passCell.textContent = password;
            editBtn.addEventListener('click', () => startEdit(row));
            editCell.appendChild(editBtn);
            row.appendChild(deleteCell);
            row.appendChild(userCell);
            row.appendChild(passCell);
            row.appendChild(editCell);
            table.appendChild(row);
        }

        return table;
    }


    async function handleDropboxPolling(url) {
        try {
            while (true) {
                const response = await fetch(url);
                const data = await response.json();

                // Ensure HTTP-level success (status code 2xx).
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

    // Run prepare and then process.
    async function runAllInits() {
        // Get the initial cursor.
        await handleDropboxPolling('./admin.php?action=init_root');
        // Get the remainder of the cursor.
        await handleDropboxPolling('./admin.php?action=continue_root');
    }

    // Run prepare and then process.
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
        } else if ("handle_list_users" === action) {
            handleListUsers('./admin.php?action=list_users');
        }
    }

    runRelevantTask();
</script>
