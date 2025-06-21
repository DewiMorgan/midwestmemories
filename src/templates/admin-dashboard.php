<?php
/**
 * Template for the admin dashboard.
 * 
 * Variables available in this template:
 * - string $pageTitle - The title of the page
 * - string $userRole - The role of the current user (Admin/SuperAdmin)
 * - string $username - The current user's username
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" href="/favicon.ico">
    <link rel="manifest" href="/site.webmanifest">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        #messages {
            max-height: calc(1em * 25);
            line-height: 1.2;
            overflow-y: auto;
            font-family: monospace;
            white-space: pre-wrap;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 4px;
        }
        #messages p {
            margin: 0;
            padding: 2px 0;
        }
        #user-list {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            min-height: 100px;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px 0;
        }
        button:hover {
            background-color: #2980b9;
        }
        hr {
            border: 0;
            height: 1px;
            background: #dee2e6;
            margin: 20px 0;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .user-info {
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Midwest Memories - <?= htmlspecialchars($userRole) ?></h1>
        <div class="user-info">
            Logged in as: <?= htmlspecialchars($username) ?>
            <form action="/logout" method="post" style="display: inline;">
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
    <?php
    // Include the admin API template
    include(__DIR__ . '/AdminApiTemplate.php');
    ?>
</body>
</html>
