/* jshint esversion: 6 */
window.UserTable = class {
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

        const deleteBtn = HtmlUtils.createButton(iconDelete, 'delete-button', Users.disableUser, row);
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
            saveButton = HtmlUtils.createButton(iconSave, 'save-button', Users.changePassword, row, true);
        } else {
            saveButton = HtmlUtils.createButton(iconSave, 'save-button', Users.addUser, row, true);
        }
        const actionCell = document.createElement('td');
        const editButton = HtmlUtils.createButton(iconEdit, 'edit-button', UserTable.toggleEditMode, row);
        const cancelButton = HtmlUtils.createButton(iconCancel, 'cancel-button', UserTable.toggleEditMode, row, true);
        actionCell.append(editButton, saveButton, cancelButton);
        return actionCell;
    }
};
