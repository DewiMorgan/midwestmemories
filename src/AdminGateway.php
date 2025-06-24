<?php

declare(strict_types=1);

namespace MidwestMemories;

use JetBrains\PhpStorm\NoReturn;

/**
 * The class for the main Admin page.
 */
class AdminGateway
{
    public function __construct()
    {
        // Handle logout if requested
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
            $this->handleLogout();
        }

        // Auth and session management. Must not output anything.
        static::initSession();
        static::dieIfNotAdmin();
        static::showAdminTemplate();
    }

    /**
     * Handle user logout.
     */
    #[NoReturn] private function handleLogout(): void
    {
        User::handleUserLogout();

        // Clear session data
        $_SESSION = [];

        // Redirect to admin page to show login form
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    /**
     * Handle session and connection.
     */
    private static function initSession(): void
    {
        $connection = Connection::getInstance();

        // Handle login form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username']) && !empty($_POST['password'])) {
            User::handleUserLogin();
        }

        // Log this access. No error handling if we fail.
        $user = User::getInstance();
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
     */
    #[NoReturn] private static function showLoginForm(): void
    {
        // Include the template file
        require __DIR__ . '/templates/AdminLoginTemplate.php';
        exit();
    }

    /**
     * Show the admin dashboard template.
     */
    private static function showAdminTemplate(): void
    {
        $user = User::getInstance();
        // Compile JavaScript if necessary.
        JsCompiler::compileAllIfNeeded();

        $templateVars = [
            'pageTitle' => 'Admin: Midwest Memories',
            'userRole' => $user->isSuperAdmin ? 'SuperAdmin' : 'Admin',
            'username' => $user->username
        ];

        // Extract variables for the template
        extract($templateVars);

        // Include the template file
        require __DIR__ . '/templates/AdminPageTemplate.php';
    }
}
