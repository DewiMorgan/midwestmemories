/**
 * Admin API JavaScript template.
 * This file contains JavaScript functions for handling API responses.
 */
?>
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
</script>
