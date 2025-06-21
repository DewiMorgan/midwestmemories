<?php

declare(strict_types=1);

namespace MidwestMemories;

use JetBrains\PhpStorm\NoReturn;

/**
 * The class for the main Admin page.
 */
class Admin
{
    public function __construct()
    {
        // Auth and session management. Must not output anything.
        static::initSession();
        static::dieIfNotAdmin();
        static::showAdminTemplate();
    }

    /**
     * Handle session and connection.
     */
    private static function initSession(): void
    {
        $connection = Connection::getInstance();
        $user = User::getInstance();

        // Handle login form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username']) && !empty($_POST['password'])) {
            $user->handleUserLogin();
        }

        // Log this access. No error handling if we fail.
        Db::sqlExec(
            'INSERT INTO `' . Db::TABLE_VISITORS . '` (`request`, `main_ip`, `all_ips_string`, `user`, `agent`)'
            . ' VALUES (?, ?, ?, ?, ?)',
            'sssss',
            $connection->request,
            $connection->ip,
            $connection->ipList,
            $user->isLoggedIn ? $user->username : 'guest',
            $connection->agent
        );
    }

    /**
     * Verify that we are only being accessed by an admin user.
     */
    private static function dieIfNotAdmin(): void
    {
        $user = User::getInstance();

        if (!$user->isAdmin && !$user->isSuperAdmin) {
            if (!$user->isLoggedIn) {
                self::showLoginForm();
            } else {
                http_response_code(403);
                die('Access denied: Insufficient privileges');
            }
        }
    }

    /**
     * Display the login form template.
     * @param string|null $error Optional error message to display
     */
    #[NoReturn] private static function showLoginForm(?string $error = null): void
    {
        // Set error message if login was attempted and failed
        if (isset($_POST['username']) && $error === null) {
            $error = 'Invalid username or password';
        }

        // Include the template file
        require __DIR__ . '/templates/login-form.php';
        exit();
    }

    /**
     * Show the admin dashboard template.
     */
    private static function showAdminTemplate(): void
    {
        $user = User::getInstance();

        $templateVars = [
            'pageTitle' => 'Admin: Midwest Memories',
            'userRole' => $user->isSuperAdmin ? 'SuperAdmin' : 'Admin',
            'username' => $user->username
        ];

        // Extract variables for the template
        extract($templateVars);

        // Include the template file
        require __DIR__ . '/templates/admin-dashboard.php';
    }
}
