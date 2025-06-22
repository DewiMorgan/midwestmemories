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
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
        }

        .login-form {
            margin-top: 50px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        button:hover {
            background-color: #45a049;
        }

        .error {
            color: #d32f2f;
            margin: 15px 0;
            padding: 10px;
            background-color: #ffebee;
            border: 1px solid #ef9a9a;
            border-radius: 4px;
        }

        h2 {
            margin-top: 0;
            color: #333;
        }
    </style>
</head>
<body>
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
