<script>
    /*
     * Javascript functions for handling API responses.
     */

    const iconAddNew = '➕';
    const iconCancel = '❌';
    const iconDelete = '🗑️';
    const iconEdit = '✏️';
    const iconSave = '💾';
    const disabledOpacity = '0.5';

    /**
     * Helper to log a new message line.
     * @param {string} message
     * @returns {HTMLParagraphElement}
     */
    function logMessage(message) {
        const messagesDiv = document.getElementById('messages');
        /** @type {HTMLParagraphElement} */
        const p = document.createElement('p');
        p.textContent = message;
        messagesDiv.appendChild(p);

        /** @type {HTMLInputElement} */
        const autoscroll = document.getElementById('autoscroll');
        if (autoscroll.checked) {
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        return p;
    }

    /**
     * Helper to append to the last message.
     * @param {HTMLParagraphElement} element
     * @param {string} appendText
     */
    function updateMessage(element, appendText) {
        element.textContent += appendText;
    }

    /**
     * Enable or disable a button element.
     * @param {HTMLButtonElement|null} buttonElement
     * @param {boolean} isEnabled
     */
    function setButtonEnabled(buttonElement, isEnabled) {
        if (buttonElement) {
            if ('disabled' in buttonElement) {
                /** @type {{ disabled: boolean }} */ (buttonElement).disabled = !isEnabled;
            }

            buttonElement.style.opacity = isEnabled ? '' : disabledOpacity;
            buttonElement.style.cursor = isEnabled ? '' : 'default';
        }
    }

    /**
     * Create a user table with editable passwords.
     * @param {Array<{username: string, comment: string}>} userList
     * @returns {HTMLTableElement}
     */
    function createUserTable(userList) {
        const table = document.createElement('table');
        const thead = createUserHeaderRow();
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        tbody.className = 'user-table';
        for (let i = 0; i < userList.length; i++) {
            const username = userList[i]['username'];
            const password = userList[i]['comment'];
            const newRow = createUserTableRow(username, password);
            tbody.appendChild(newRow);
        }

        const footer = createUserFooterRow();
        tbody.appendChild(footer);

        table.appendChild(tbody);
        return table;
    }

    /**
     * Create the table header row.
     * @returns {HTMLTableSectionElement}
     */
    function createUserHeaderRow() {
        /** @type {HTMLTableSectionElement} */
        const thead = document.createElement('thead');
        /** @type {HTMLTableRowElement} */
        const headerRow = document.createElement('tr');
        const headers = ['Del', 'Username', 'Password', 'Edit'];
        for (let i = 0; 4 > i; i++) {
            /** @type {HTMLTableCellElement} */
            const th = document.createElement('th');
            th.textContent = headers[i];
            headerRow.appendChild(th);
        }
        thead.appendChild(headerRow);
        return thead;
    }

    function createTd(content) {
        const cell = document.createElement('td');
        if (content) {
            cell.appendChild(content);
        }
        return cell;
    }

    function createTextSpan(className, text) {
        const span = document.createElement('span');
        span.className = className;
        span.textContent = text;
        return span;
    }

    function createInput(className, value = '') {
        /** @type {HTMLInputElement} */
        const input = document.createElement('input');
        input.type = 'text';
        input.className = className;
        input.value = value;
        input.style.display = 'none';
        return input;
    }

    function createActionButtons(row, isCreating) {
        let saveButton;
        if (isCreating) {
            saveButton = createButton(iconSave, 'save-button', changePassword, row, true);
        } else {
            saveButton = createButton(iconSave, 'save-button', addUser, row, true);
        }
        const actionCell = document.createElement('td');
        const editButton = createButton(iconEdit, 'edit-button', toggleEditMode, row);
        const cancelButton = createButton(iconCancel, 'cancel-button', toggleEditMode, row, true);
        actionCell.append(editButton, saveButton, cancelButton);
        return actionCell;
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
    function createButton(labelText, className, handler = null, row = null, hidden = false) {
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

    /**
     * Create one row from a table list.
     * @param {string} username
     * @param {string} password
     * @returns {HTMLTableRowElement}
     */
    function createUserTableRow(username, password) {
        /** @type {HTMLTableRowElement} */
        const row = document.createElement('tr');

        const deleteBtn = createButton(iconDelete, 'delete-button', disableUser, row);
        const deleteCell = createTd(deleteBtn);

        const usernameSpan = createTextSpan('username-text', username);
        const usernameCell = createTd(usernameSpan);

        const passwordSpan = createTextSpan('password-text', password);
        const passwordInput = createInput('password-input', password);
        const passwordCell = createTd();
        passwordCell.append(passwordSpan, passwordInput);

        const editCell = createActionButtons(row, true);

        row.append(deleteCell, usernameCell, passwordCell, editCell);

        if ('DISABLED' === password) {
            disableUsersRowInTable(row);
        }

        return row;
    }

    function createUserFooterRow() {
        const row = document.createElement('tr');
        const blankCell = createTd();

        const usernameText = createTextSpan('username-text', '(Add new user)');
        const usernameInput = createInput('username-input');
        const usernameCell = createTd();
        usernameCell.append(usernameText, usernameInput);

        const passwordSpan = createTextSpan('password-text', '');
        const passwordInput = createInput('password-input');
        const passwordCell = createTd();
        passwordCell.append(passwordSpan, passwordInput);

        const controlCell = createActionButtons(row, false);

        row.append(blankCell, usernameCell, passwordCell, controlCell);

        return row;
    }

    /**
     * Run all steps of listing, downloading, and postprocessing files.
     * @returns {Promise<void>}
     */
    async function runAllUpdates() {
        // Get and queue updates from Dropbox.
        await handleDropboxPolling('./admin.php?action=continue_root', 'get the next page of files');
        // Download queued downloads.
        await handleFileTask(
            'Downloading', './admin.php?action=list_files_to_download', './admin.php?action=download_one_file'
        );
        // Generate queued thumbnails.
        await handleFileTask(
            'Postprocessing', './admin.php?action=list_files_to_process', './admin.php?action=process_one_file'
        );
    }

    /**
     * API wrapper for dropbox polling endpoints. Call the endpoint until all items are processed.
     * @param {string} url
     * @param {string} actionName
     * @returns {Promise<void>}
     */
    async function handleDropboxPolling(url, actionName) {
        logMessage(`Asking Dropbox to ${actionName}...`);
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
                data.numAddedFiles ??= 0;
                data.numTotalFiles ??= 0;

                if ("OK" !== data.error) {
                    logMessage(data.error);
                    return;
                }

                if (true === data.moreFilesToGo) {
                    logMessage(`= Got ${data.numAddedFiles} new files of ${data.numTotalFiles} total, more to come...`);
                } else {
                    logMessage(`= Got ${data.numAddedFiles} new files of ${data.numTotalFiles} total, finished!`);
                    return;
                }
            }
        } catch (err) {
            logMessage(`= Request failed: ${err.message}`);
        }
    }

    /**
     * Generic file task handler. Get a list, then iterate over it to process each item.
     * @param {string} actionName Just for logging, what API action we're performing.
     * @param {string} listEndpoint The endpoint to call to get a list of items.
     * @param {string} fileEndpoint The endpoint to call to process each item from the list.
     * @returns {Promise<void>}
     */
    async function handleFileTask(actionName, listEndpoint, fileEndpoint) {
        logMessage(`Getting list of files for ${actionName}...`);

        const listResponse = await fetch(listEndpoint);
        if (listResponse.ok) {
            const files = await listResponse.json();
            const numFiles = files.length;
            if (0 === numFiles) {
                logMessage(`= Got zero files for ${actionName}.`);
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
            logMessage(`= ${actionName} complete!`);
        } else {
            logMessage(`= Failed to get list of files for ${actionName}.`);
        }
    }

    /**
     * Handle a request to list the users.
     * @param {string} listEndpoint The endpoint to call to get a list of items.
     * @returns {Promise<void>}
     */
    async function listUsers(listEndpoint) {
        logMessage(`Getting list of users...`);

        const listResponse = await fetch(listEndpoint);
        if (listResponse.ok) {
            const jsonResponse = await listResponse.json();

            if (!jsonResponse.hasOwnProperty('data')) {
                logMessage("= Response JSON does not contain a 'data' property.");
                return;
            } else if (!Array.isArray(jsonResponse.data)) {
                logMessage("= 'data' property is not an array.");
                return;
            }
            const users = jsonResponse.data;
            const numUsers = users.length;

            if (0 === numUsers) {
                logMessage(`= Got zero users! There should probably be more.`);
            } else {
                logMessage(`= Got ${numUsers} users!`);
            }
            console.log('Users:', users);

            const table = createUserTable(users);

            const userListDiv = document.getElementById('user-list');
            userListDiv.appendChild(table);
        } else {
            logMessage(`= Failed to get list of users: status ${listResponse.status}`);
        }
    }

    /**
     * Save a password.
     * @param {HTMLTableRowElement} row
     */
    async function changePassword(row) {
        const usernameText = row.querySelector('.username-text');
        const username = usernameText.textContent;
        if (confirm(`Really change the password for user, "${username}"?`)) {
            const passwordInput = row.querySelector('.password-input');
            const password = passwordInput.value;
            const endpoint = './admin.php?action=change_password';
            const apiResult = await callUserAction(endpoint, username, password);
            if (apiResult) {
                // Unstrike through the username.
                usernameText.style.textDecoration = 'none';
                usernameText.style.fontStyle = 'normal';
                // Set the password to the new one.
                const passwordText = row.querySelector('.password-text');
                passwordText.textContent = passwordInput.value;
                toggleEditMode(row);
            }
        }
        // Else: do nothing, remain in edit mode
    }

    /**
     * Handle a click to delete an existing user.
     * @param {HTMLTableRowElement} row
     */
    async function disableUser(row) {
        const usernameText = row.querySelector('.username-text');
        const username = usernameText.textContent;
        if (confirm(`Really disable the existing user, "${username}"?`)) {
            const password = '';
            const endpoint = './admin.php?action=change_password';
            const apiResult = await callUserAction(endpoint, username, password);
            if (apiResult) {
                disableUsersRowInTable(row);
            }
        }
        // Else: do nothing.
    }

    /**
     * Handle a click to save a new user.
     * @param {HTMLTableRowElement} row
     */
    async function addUser(row) {
        const usernameInput = row.querySelector('.username-input');
        const username = usernameInput.value;
        if (confirm(`Really create the new user, "${username}"?`)) {
            const passwordInput = row.querySelector('.password-input');
            const password = passwordInput.value;
            const endpoint = './admin.php?action=add_user';
            const apiResult = await callUserAction(endpoint, username, password);
            if (apiResult) {
                console.log("apiResult true, adding row");
                addUserRowToTable(username, password);
            }
        }
        // Else: do nothing, remain in edit mode
    }

    /**
     * Call a user action endpoint with the username and password.
     * @param {string} endpoint The base endpoint URL.
     * @param {string} username
     * @param {string} password
     * @returns {Promise<boolean>} True on success, false on failure.
     */
    async function callUserAction(endpoint, username, password) {
        const actionName = endpoint.replace(/^.*action=/, ''); // e.g., "change_password"
        logMessage(`Calling ${actionName} for user "${username}"...`);

        const url = `${endpoint}&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`;

        try {
            const response = await fetch(url);
            if (response.ok) {
                logMessage(`${actionName} succeeded for "${username}".`);
                return true;
            } else {
                logMessage(`${actionName} failed for "${username}".`);
                return false;
            }
        } catch (error) {
            logMessage(`Error calling ${actionName} for "${username}": ${error}`);
            return false;
        }
    }

    /**
     * Append a user row to the table, just before the 'add user' row.
     * @param {string} username
     * @param {string} password
     */
    function addUserRowToTable(username, password) {
        const table = document.querySelector('.user-table');
        if (table) {
            console.log("table found, adding row");
            const newRow = createUserTableRow(username, password);
            const lastRow = table.rows[table.rows.length - 1];
            table.insertBefore(newRow, lastRow);
        }
    }

    /**
     * Mark the user as deleted from the table.
     * @param {HTMLTableRowElement} row
     */
    function disableUsersRowInTable(row) {
        const usernameText = row.querySelector('.username-text');
        // Strike through the username.
        usernameText.style.textDecoration = 'line-through';
        usernameText.style.fontStyle = 'italic';

        // Disable the edit and delete buttons.
        const deleteButton = row.querySelector('.delete-button');
        setButtonEnabled(deleteButton, false);
    }

    /**
     * Toggle edit mode for a row based on current visibility.
     * @param {HTMLTableRowElement} row
     */
    function toggleEditMode(row) {
        const passwordInput = row.querySelector('.password-input');
        const enteringEditMode = 'none' === passwordInput.style.display;
        const onWhenEditing = enteringEditMode ? '' : 'none';
        const offWhenEditing = enteringEditMode ? 'none' : '';

        const passwordText = row.querySelector('.password-text');
        // Populate the text field.
        passwordInput.value = passwordText.textContent;
        passwordInput.style.display = onWhenEditing;
        passwordText.style.display = offWhenEditing;

        const usernameInput = row.querySelector('.username-input');
        if (usernameInput) {
            usernameInput.style.display = onWhenEditing;
            row.querySelector('.username-text').style.display = offWhenEditing;
        }

        row.querySelector('.edit-button').style.display = offWhenEditing;
        row.querySelector('.save-button').style.display = onWhenEditing;
        row.querySelector('.cancel-button').style.display = onWhenEditing;
        const deleteButton = row.querySelector('.delete-button');
        setButtonEnabled(deleteButton, !enteringEditMode);
    }

    /**
     * Call whichever task the template has been asked for.
     */
    function runRelevantTasks() {
        const params = new URLSearchParams(window.location.search);

        // Populate the user list.
        listUsers('/api/v1.0/user');

        // Only if requested by the user.
        if ("handle_init_root" === params.get("user-action")) {
            // Get the initial page of files, resetting the cursor.
            handleDropboxPolling('./admin.php?action=init_root', 'get the very first page of files');
        }

        // Get all remaining pages.
        runAllUpdates();
    }

    runRelevantTasks();
</script>
