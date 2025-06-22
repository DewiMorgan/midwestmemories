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
