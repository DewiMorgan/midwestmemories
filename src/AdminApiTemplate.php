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
     * Call whichever task the template has been asked for.
     */
    function runRelevantTasks() {
        // noinspection VoidExpressionJS
        void new UserTable();
        const userListDiv = document.getElementById('user-list');
        userListDiv.appendChild(UserTable.table);

        // Populate the user list.
        Users.listUsers();

        // Do any pending Dropbox activities.
        Dropbox.runAllUpdates();
    }

    runRelevantTasks();
</script>
