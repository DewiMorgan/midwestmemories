<?php
declare(strict_types=1);

/**
 * Template for the admin login form.
 * Shows an error message if a login was attempted and failed.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - Midwest Memories Admin</title>
    <meta charset="UTF-8">
    <!--suppress HtmlUnknownTarget -->
    <link rel="stylesheet" href="/raw/admin.css">
</head>
<body class="login-page">
<div class="login-form">
    <h2>Admin Login</h2>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username'])): ?>
        <div class="error">Invalid username or password</div>
    <?php endif; ?>
    <form method="post" action="">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
