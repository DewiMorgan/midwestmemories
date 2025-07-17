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
        User::handleHtmlLogout();

        // Auth and session management. Must not output anything.
        User::handleHtmlSession();
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

        // Generate the variables required by the template.
        $h_userRole = $user->isSuperAdmin ? 'SuperAdmin' : 'Admin';
        $h_username = htmlspecialchars($user->username);
        $isLiveSite = str_contains(__DIR__, 'midwestmemoriesfamily');
        $i_scriptVersion = Path::getScriptVersion(__DIR__ . '/../raw/user.js');

        // Include the template file
        require __DIR__ . '/templates/AdminPageTemplate.php';
    }
}
