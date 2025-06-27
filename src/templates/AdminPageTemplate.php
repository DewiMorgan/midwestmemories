<?php
declare(strict_types=1);

/**
 * Template for the admin dashboard.
 *
 * @var string $pageTitle The title of the page
 * @var string $userRole The role of the current user (Admin/SuperAdmin)
 * @var string $username The current user's username
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta charset="UTF-8">
    <!--suppress HtmlUnknownTarget -->
    <link rel="stylesheet" href="/raw/admin.css?i=2">
    <!--suppress HtmlUnknownTarget -->
    <script src="/raw/admin.js?i=2"></script>
</head>
<body>
<div class="header">
    <h1>Midwest Memories - <?= htmlspecialchars($userRole) ?></h1>
    <div class="user-info">
        Logged in as: <?= htmlspecialchars($username) ?>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="logout">
            <button type="submit" style="margin-left: 10px;">Logout</button>
        </form>
    </div>
</div>

<h2>Users</h2>
<div id="user-list">Loading users...</div>
<hr>

<h2>Background Task Output</h2>
<div id="messages"></div>
<br>
<input type="checkbox" id="autoscroll" name="autoscroll" checked>
<label for="autoscroll">Auto scroll</label><br>
<hr>

<h2>Admin Actions</h2>
<button onclick="initializeCursor()">Initialize Cursor</button>
<br>
</body>
</html>
