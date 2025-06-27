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
    // noinspection JSIgnoredPromiseFromCall
    Users.listUsers();

    // Do any pending Dropbox activities.
    // noinspection JSIgnoredPromiseFromCall
    Dropbox.runAllUpdates();
}

// Wait for the DOM to be fully loaded before running admin tasks.
document.addEventListener('DOMContentLoaded', runAdminTasks);
