
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

        if (jsonResponse.hasOwnProperty('error') && "OK" !== jsonResponse.error) {
            throw new Error(jsonResponse.error);
        }

        return data;
    }
};


/* Source: Dropbox.js */
/* jshint esversion: 6 */
window.Dropbox = class {
    /**
     * API wrapper for dropbox polling endpoint. Call the endpoint until all items are processed.
     * @returns {Promise<void>}
     */
    static async handleDropboxPolling() {
        Log.message(`Asking Dropbox to get the next page of files...`);
        try {
            while (true) {
                /**
                 * @typedef {{numAddedFiles:int, numTotalFiles:int, hasMoreFiles:boolean, error:string}} FileStatus
                 * @type {Promise<FileStatus>}
                 */
                const data = await Api.fetchApiData('/api/v1.0/cursor', 'GET', 'object');

                // Enforce syntax.
                data.moreFilesToGo ??= false;
                data.numAddedFiles ??= 0;
                data.numTotalFiles ??= 0;

                if (true === data.moreFilesToGo) {
                    Log.message(`= Got ${data.numAddedFiles} new files of ${data.numTotalFiles} total, more coming...`);
                } else {
                    Log.message(`= Got ${data.numAddedFiles} new files of ${data.numTotalFiles} total, finished!`);
                    return;
                }
            }
        } catch (err) {
            Log.message(`= Listing new files failed: ${err.message}`);
        }
    }

    /**
     * Generic file task handler. GET a list from an API endpoint, then POST to the same endpoint to process each item.
     * @param {string} actionName Just for logging, what API action we're performing.
     * @param {string} endpoint The endpoint to `GET` for a list, and POST for each item.
     * @returns {Promise<void>}
     */
    static async handleFileTask(actionName, endpoint) {
        Log.message(`Getting list of files for ${actionName}...`);
        try {
            const files = await Api.fetchApiData(endpoint, 'GET', 'array');
            const numFiles = files.length;
            if (0 === numFiles) {
                Log.message(`= Got zero files for ${actionName}.`);
            }
            for (const [index, filename] of files.entries()) {
                Log.message(`= ${index + 1}/${numFiles} ${actionName} ${filename}...`);
                await Api.fetchApiData(endpoint, 'POST', 'object');
            }
            Log.message(`= ${actionName} complete!`);
        } catch (err) {
            Log.message(`= ${actionName} failed: ${err.message}`);
        }
    }

    initializeCursor() {
        // Get the initial page of files, resetting the cursor.
        Log.message('Reinitializing Dropbox list...');
        try {
            // noinspection VoidExpressionJS
            void Api.fetchApiData('/api/v1.0/cursor', 'POST', 'object');
            Log.message(`= Reinitializing Dropbox list succeeded.`);
        } catch (err) {
            Log.message(`= Reinitializing Dropbox list failed: ${err.message}`);
        }

        // Get all remaining pages and process them.
        // noinspection VoidExpressionJS
        void Dropbox.runAllUpdates()
    }

    /**
     * @returns {Promise<void>}
     */
    static async runAllUpdates() {
        // Get and queue updates from Dropbox.
        await Dropbox.handleDropboxPolling();
        // Download queued downloads.
        await Dropbox.handleFileTask('Downloading', '/api/v1.0/download');
        // Generate queued thumbnails.
        await Dropbox.handleFileTask('Postprocessing', '/api/v1.0/process');
    }
};


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


/* Source: Users.js */
/* jshint esversion: 6 */
window.Users = class {

    /**
     * Handle a request to list the users.
     * @returns {Promise<void>}
     */
    static async listUsers() {
        Log.message(`Getting list of users...`);
        try {
            const users = await Api.fetchApiData('/api/v1.0/user', 'GET', 'array');
            const numUsers = users.length;

            if (0 === numUsers) {
                Log.message(`= Got zero users! There should probably be more.`);
            } else {
                Log.message(`= Got ${numUsers} users!`);
            }
            console.log('Users:', users);

            UserTable.populateUserTable(users);
        } catch (err) {
            Log.message(`= Listing users failed: ${err.message}`);
        }
    }

    /**
     * Save a password.
     * @param {HTMLTableRowElement} row
     */
    static async changePassword(row) {
        const usernameText = row.querySelector('.username-text');
        const username = usernameText.textContent;
        if (confirm(`Really change the password for user, "${username}"?`)) {
            const passwordInput = row.querySelector('.password-input');
            const password = passwordInput.value;
            const endpoint = '/api/v1.0/user';
            const apiResult = await this.callUserAction('Change password', endpoint, 'PUT', username, password);
            if (apiResult) {
                // Unstrike through the username.
                usernameText.style.textDecoration = 'none';
                usernameText.style.fontStyle = 'normal';
                // Set the password to the new one.
                const passwordText = row.querySelector('.password-text');
                passwordText.textContent = passwordInput.value;
                UserTable.toggleEditMode(row);
            }
        }
        // Else: do nothing, remain in edit mode
    }

    /**
     * Handle a click to delete an existing user.
     * @param {HTMLTableRowElement} row
     */
    static async disableUser(row) {
        const usernameText = row.querySelector('.username-text');
        const username = usernameText.textContent;
        if (confirm(`Really disable the existing user, "${username}"?`)) {
            const endpoint = `/api/v1.0/user/${username}`;
            const apiResult = await this.callUserAction('Disable user', endpoint, 'DELETE');
            if (apiResult) {
                UserTable.disableUsersRowInTable(row);
            }
        }
        // Else: do nothing.
    }

    /**
     * Handle a click to save a new user.
     * @param {HTMLTableRowElement} row
     */
    static async addUser(row) {
        const usernameInput = row.querySelector('.username-input');
        const username = usernameInput.value;
        if (confirm(`Really create the new user, "${username}"?`)) {
            const passwordInput = row.querySelector('.password-input');
            const password = passwordInput.value;
            const endpoint = '/api/v1.0/user';
            const apiResult = await this.callUserAction('Create user', endpoint, 'POST', username, password);
            if (apiResult) {
                console.log("apiResult true, adding row");
                UserTable.addUserRowToTable(username, password);
            }
        }
        // Else: do nothing, remain in edit mode
    }

    /**
     * Call a user action endpoint with the username and password.
     * @param {string} readableAction What we're doing, for human-readable logging/display.
     * @param {string} endpoint The base endpoint URL.
     * @param {string} httpMethod GET, POST, DELETE, etc.
     * @param {string} username Optional username.
     * @param {string} password Optional password.
     * @returns {Promise<boolean>} True on success, false on failure.
     */
    static async callUserAction(readableAction, endpoint, httpMethod, username = '', password = '') {
        let user = username;
        if ('' === user) {
            const parts = endpoint.split('/');
            user = parts[parts.length - 1];
        }
        Log.message(`Calling ${readableAction} for user "${user}"...`);

        try {
            await Api.fetchApiData(endpoint, httpMethod, 'string', {username: user, password});
            Log.message(`= ${readableAction} succeeded for "${user}".`);
            return true;
        } catch (error) {
            Log.message(`= ${readableAction} failed for "${user}": ${error}`);
            return false;
        }
    }
};


/* Source: UserTable.js */
/* jshint esversion: 6 */
window.UserTable = class {
    static iconAddNew = '➕';
    static iconCancel = '❌';
    static iconDelete = '🗑️';
    static iconEdit = '✏️';
    static iconSave = '💾';

    /** @type {HTMLTableElement} */
    static table;

    constructor() {
        /** @type {HTMLTableElement} */
        UserTable.table = this.#createUserTable();
    }

    /**
     * Fill the table with users.
     * @param {Array<{username: string, comment: string}>} userList
     */
    static populateUserTable(userList) {
        for (let i = 0; i < userList.length; i++) {
            const username = userList[i]['username'];
            const password = userList[i]['comment'];
            this.addUserRowToTable(username, password);
        }
    }


    /**
     * Append a user row to the table, just before the 'add user' row.
     * @param {string} username
     * @param {string} password
     */
    static addUserRowToTable(username, password) {
        const newRow = this.#createUserTableRow(username, password);
        const lastRow = UserTable.table.rows[UserTable.table.rows.length - 1];
        UserTable.table.insertBefore(newRow, lastRow);
    }

    /**
     * Mark the user as deleted from the table.
     * @param {HTMLTableRowElement} row
     */
    static disableUsersRowInTable(row) {
        const usernameText = row.querySelector('.username-text');
        // Strike through the username.
        usernameText.style.textDecoration = 'line-through';
        usernameText.style.fontStyle = 'italic';

        // Disable the edit and delete buttons.
        const deleteButton = row.querySelector('.delete-button');
        HtmlUtils.setButtonEnabled(deleteButton, false);
    }

    /**
     * Toggle edit mode for a row based on current visibility.
     * @param {HTMLTableRowElement} row
     */
    static toggleEditMode(row) {
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
        HtmlUtils.setButtonEnabled(deleteButton, !enteringEditMode);
    }

    /**
     * Create the HTML table for listing the users, with a header and footer, but no users yet.
     * @returns {HTMLTableElement}
     */
    #createUserTable() {
        const table = document.createElement('table');
        const thead = this.#createUserHeaderRow();
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        tbody.className = 'user-table';
        const footer = this.#createUserFooterRow();
        tbody.appendChild(footer);
        table.appendChild(tbody);
        return table;
    }

    /**
     * Create the table header row.
     * @returns {HTMLTableSectionElement}
     */
    #createUserHeaderRow() {
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
    #createUserTableRow(username, password) {
        /** @type {HTMLTableRowElement} */
        const row = document.createElement('tr');

        const deleteBtn = HtmlUtils.createButton(UserTable.iconDelete, 'delete-button', Users.disableUser, row);
        const deleteCell = HtmlUtils.createTd(deleteBtn);

        const usernameSpan = HtmlUtils.createTextSpan('username-text', username);
        const usernameCell = HtmlUtils.createTd(usernameSpan);

        const passwordSpan = HtmlUtils.createTextSpan('password-text', password);
        const passwordInput = HtmlUtils.createInput('password-input', password);
        const passwordCell = HtmlUtils.createTd();
        passwordCell.append(passwordSpan, passwordInput);

        const editCell = this.#createActionButtons(row, true);

        row.append(deleteCell, usernameCell, passwordCell, editCell);

        if ('DISABLED' === password) {
            UserTable.disableUsersRowInTable(row);
        }

        return row;
    }

    #createUserFooterRow() {
        const row = document.createElement('tr');
        const blankCell = HtmlUtils.createTd();

        const usernameText = HtmlUtils.createTextSpan('username-text', '(Add new user)');
        const usernameInput = HtmlUtils.createInput('username-input');
        const usernameCell = HtmlUtils.createTd();
        usernameCell.append(usernameText, usernameInput);

        const passwordSpan = HtmlUtils.createTextSpan('password-text', '');
        const passwordInput = HtmlUtils.createInput('password-input');
        const passwordCell = HtmlUtils.createTd();
        passwordCell.append(passwordSpan, passwordInput);

        const controlCell = this.#createActionButtons(row, false);

        row.append(blankCell, usernameCell, passwordCell, controlCell);

        return row;
    }

    #createActionButtons(row, isCreating) {
        let saveButton;
        if (isCreating) {
            saveButton = HtmlUtils.createButton(UserTable.iconSave, 'save-button', Users.changePassword, row, true);
        } else {
            saveButton = HtmlUtils.createButton(UserTable.iconSave, 'save-button', Users.addUser, row, true);
        }
        const actionCell = document.createElement('td');
        const editButton = HtmlUtils.createButton(UserTable.iconEdit, 'edit-button', UserTable.toggleEditMode, row);
        const cancelButton = HtmlUtils.createButton(
            UserTable.iconCancel,
            'cancel-button',
            UserTable.toggleEditMode,
            row,
            true
        );
        actionCell.append(editButton, saveButton, cancelButton);
        return actionCell;
    }
};


/* Source: AdminPage.js */
/**
 * Call the various API tasks that we run automatically when displaying the admin page.
 * First, list all users in a table to let them be edited.
 * Then, process any pending Dropbox updates.
 */
function runAdminTasks() {
    // noinspection VoidExpressionJS
    void new UserTable();
    const userListDiv = document.getElementById('user-list');
    userListDiv.appendChild(UserTable.table);

    // Populate the user list.
    Users.listUsers();

    // Do any pending Dropbox activities.
    Dropbox.runAllUpdates();
}

runAdminTasks();

