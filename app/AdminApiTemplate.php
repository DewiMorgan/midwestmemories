<?php
declare(strict_types=1);

/*
 * Mostly javascript functions for handling API responses.
 */
?>
<div id="messages"></div>

<script>
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
     * Create a styled HTML button component.
     * @param {string} labelText
     * @param {string} className
     * @returns {HTMLButtonElement}
     */
    function createButton(labelText, className) {
        /** @type {HTMLButtonElement} */
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = labelText;
        button.className = className;
        return button;
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

    /**
     * Create one row from a table list.
     * @param {string} username
     * @param {string} password
     * @returns {HTMLTableRowElement}
     */
    function createUserTableRow(username, password) {
        /** @type {HTMLTableRowElement} */
        const row = document.createElement('tr');

        // Delete button
        /** @type {HTMLTableCellElement} */
        const deleteCell = document.createElement('td');
        const deleteUserButton = createButton(iconDelete, 'delete-button');
        const deleteUserHandler = disableUser.bind(null, row);
        deleteUserButton.addEventListener('click', deleteUserHandler);
        deleteCell.appendChild(deleteUserButton);

        // Username cell
        /** @type {HTMLTableCellElement} */
        const usernameCell = document.createElement('td');
        usernameCell.className = 'username-text';
        usernameCell.textContent = username;

        // Password cell
        /** @type {HTMLTableCellElement} */
        const passwordCell = document.createElement('td');
        /** @type {HTMLSpanElement} */
        const passwordText = document.createElement('span');
        passwordText.className = 'password-text';
        passwordText.textContent = password;

        /** @type {HTMLInputElement} */
        const passwordInput = document.createElement('input');
        passwordInput.className = 'password-input';
        passwordInput.type = 'text';
        passwordInput.value = password;
        passwordInput.style.display = 'none';

        passwordCell.appendChild(passwordText);
        passwordCell.appendChild(passwordInput);

        // Edit cell
        /** @type {HTMLTableCellElement} */
        const editCell = document.createElement('td');
        const editButton = createButton(iconEdit, 'edit-button');
        const editHandler = toggleEditMode.bind(null, row);
        editButton.addEventListener('click', editHandler);

        const saveButton = createButton(iconSave, 'save-button');
        saveButton.style.display = 'none';
        const changePasswordHandler = changePassword.bind(null, row);
        saveButton.addEventListener('click', changePasswordHandler);

        const cancelButton = createButton(iconCancel, 'cancel-button');
        cancelButton.style.display = 'none';
        const cancelPasswordHandler = toggleEditMode.bind(null, row);
        cancelButton.addEventListener('click', cancelPasswordHandler);

        editCell.appendChild(editButton);
        editCell.appendChild(saveButton);
        editCell.appendChild(cancelButton);

        // Add all cells to the row
        row.appendChild(deleteCell);
        row.appendChild(usernameCell);
        row.appendChild(passwordCell);
        row.appendChild(editCell);

        return row;
    }

    /**
     * Appends a final "Add new user" row to the given user table.
     * @returns {HTMLTableRowElement}
     */
    function createUserFooterRow() {
        /** @type {HTMLTableRowElement} */
        const row = document.createElement('tr');

        // Column 0: blank
        /** @type {HTMLTableCellElement} */
        const emptyCell = document.createElement('td');
        row.appendChild(emptyCell);

        // Column 1: (Add new user) in italics and hidden input
        /** @type {HTMLTableCellElement} */
        const usernameCell = document.createElement('td');
        /** @type {HTMLSpanElement} */
        const usernameText = document.createElement('span');
        usernameText.textContent = '(Add new user)';
        usernameText.className = 'username-text';

        /** @type {HTMLInputElement} */
        const usernameInput = document.createElement('input');
        usernameInput.type = 'text';
        usernameInput.className = 'username-input';
        usernameInput.style.display = 'none';
        usernameCell.appendChild(usernameText);
        usernameCell.appendChild(usernameInput);
        row.appendChild(usernameCell);

        // Column 2: hidden password input
        /** @type {HTMLTableCellElement} */
        const passwordCell = document.createElement('td');
        /** @type {HTMLSpanElement} */
        const passwordText = document.createElement('span');
        passwordText.className = 'password-text';
        passwordText.textContent = '';
        /** @type {HTMLInputElement} */
        const passwordInput = document.createElement('input');
        passwordInput.type = 'text';
        passwordInput.className = 'password-input';
        passwordInput.style.display = 'none';
        passwordCell.appendChild(passwordText);
        passwordCell.appendChild(passwordInput);
        row.appendChild(passwordCell);

        // Column 3: Add, Save, and Cancel buttons
        /** @type {HTMLTableCellElement} */
        const controlCell = document.createElement('td');

        const addButton = createButton(iconAddNew, 'edit-button');
        const toggleEditHandler = toggleEditMode.bind(null, row);
        addButton.addEventListener('click', toggleEditHandler);

        const saveButton = createButton(iconSave, 'save-button');
        saveButton.style.display = 'none';
        const saveNewUserHandler = addUser.bind(null, row);
        saveButton.addEventListener('click', saveNewUserHandler);

        const cancelButton = createButton(iconCancel, 'cancel-button');
        cancelButton.style.display = 'none';
        cancelButton.addEventListener('click', toggleEditHandler);

        controlCell.appendChild(addButton);
        controlCell.appendChild(saveButton);
        controlCell.appendChild(cancelButton);
        row.appendChild(controlCell);

        return row;
    }

    /**
     * Run prepare and then process for initialization.
     * @returns {Promise<void>}
     */
    async function runAllInits() {
        // Get the initial cursor.
        await handleDropboxPolling('./admin.php?action=init_root');
        // Get the remainder of the cursor.
        await handleDropboxPolling('./admin.php?action=continue_root');
    }

    /**
     * Run all steps of listing, downloading, and postprocessing files.
     * @returns {Promise<void>}
     */
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

    /**
     * Call whichever task the template has been asked for.
     */
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

    // ================ HANDLERS - called by listeners.

    /**
     * API wrapper for dropbox polling endpoints. Call the endpoint until all items are processed.
     * @param {string} url
     * @returns {Promise<void>}
     */
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

    /**
     * Handle a request to list the users.
     * @param {string} listEndpoint The endpoint to call to get a list of items.
     * @returns {Promise<void>}
     */
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
                removeUsersRowFromTable(row);
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
     * Call a user action endpoint with username and password.
     * @param {string} endpoint The base endpoint URL
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
    function removeUsersRowFromTable(row) {
        const usernameText = row.querySelector('.username-text');
        // Strike through the username.
        usernameText.style.textDecoration = 'line-through';

        // Disable the edit and delete buttons.
        const editButton = row.querySelector('.edit-button');
        setButtonEnabled(editButton, false);
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


    /*
     * List of actions to listen for:
     * - Delete user.
     * - Save new password.
     * - Save new user.
    */

    runRelevantTask();
</script>
