<?php
declare(strict_types=1);

/**
 * Template for the admin dashboard.
 *
 * @var string $h_userRole The role of the current user (Admin/SuperAdmin)
 * @var string $h_username The current user's username.
 * @var bool $isLiveSite True on the live site, false on the test site.
 * @var int $i_scriptVersion The version of the admin.js file.
 */

use MidwestMemories\Path;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if ($isLiveSite) { ?>
        <link rel="shortcut icon" href="/favicon.ico">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="manifest" href="/site.webmanifest">
    <?php } else { ?>
        <link rel="icon" href="/favicon-test.ico" type="image/x-icon">
    <?php } ?>
    <title>Admin: Midwest Memories - <?= ($isLiveSite ? 'Live Site' : 'Test Site') ?></title>
    <meta charset="UTF-8">
    <!--suppress HtmlUnknownTarget -->
    <link rel="stylesheet" href="/raw/admin.css">
    <!--suppress HtmlUnknownTarget -->
    <script src="/raw/admin.js?v=<?= $i_scriptVersion ?>"></script>
</head>
<body>
<div class="header">
    <h1>Midwest Memories <?= $h_userRole ?> - <?= ($isLiveSite ? 'Live Site' : 'Test Site') ?></h1>
    <div class="user-info">
        Logged in as: <?= $h_username ?> (<?= $h_userRole ?>)
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="logout">
            <button type="submit" style="margin-left: 10px;">Logout</button>
        </form>
    </div>
</div>

<h2>Users</h2>
<div id="user-list"><!-- User table goes here. --></div>
<hr>

<h2>Background Task Output</h2>
<div id="messages"><!-- Output text goes here. --></div>
<br>
<input type="checkbox" id="autoscroll" name="autoscroll" checked>
<label for="autoscroll">Auto scroll</label><br>
<hr>

<h2>Admin Actions</h2>
<button onclick="initializeCursor()">Initialize Cursor</button>
<br>
</body>
</html>
