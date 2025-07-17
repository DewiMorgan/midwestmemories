<?php

declare(strict_types=1);

namespace MidwestMemories;

use JetBrains\PhpStorm\NoReturn;

/**
 * The class for the main index.php file.
 */
class IndexGateway
{
    // Full user-requested path relative to hdd /. If set, exists in Path::$imageBasePath. No trailing slash on folders.
    public static string $requestUnixPath;
    public static string $requestWebPath;

    public function __construct()
    {
        // Output no-cache headers.
        self::setNoCacheHeaders();

        // Handle logout if requested
        User::handleHtmlLogout();

        // Auth and session management. Must not output anything.
        User::handleHtmlSession();
        Path::validateBaseDir();
        static::dieIfNotUser();

        self::$requestWebPath = $_REQUEST['path'] ?? '/';
        self::$requestUnixPath = Path::webToUnixPath(self::$requestWebPath); // Dies if not correct.

        // static::showUserTemplate();
        self::showPage();
    }

    /**
     * Verify that we are only being accessed by an authorized user.
     */
    private static function dieIfNotUser(): void
    {
        $user = User::getInstance();
        if (!$user->isLoggedIn && !$user->isUser) {
            self::showLoginForm();
        }
    }

    /**
     * Display the login form template.
     */
    #[NoReturn] private static function showLoginForm(): void
    {
        // Include the template file
        require __DIR__ . '/templates/UserLoginTemplate.php';
        exit();
    }

    /**
     * Handle displaying the requested page.
     */
    private static function showPage(): void
    {
        Log::debug('Search "' . self::$requestUnixPath . '"', self::$requestWebPath);
        // Inline requires are internal requests, rather than user requests.
        // blank = user request.
        // 1 = inline thing (file or folder sub-template).
        // 2 = raw thing (image). ToDo: sniff this by type?
        // 3 = search view.
        $isInlineRequest = isset($_REQUEST['i']);

        if (!$isInlineRequest) {
            Log::debug('User');
            // This is a request by a user, perhaps to a bookmark.
            // Load the tree view, which will then call us back for the inline version of the pointed-at $path resource.
            include(__DIR__ . '/templates/TreeTemplate.php');
        } elseif (2 === (int)$_REQUEST['i']) {
            // We're showing raw file view, such as for an img link.
            include(__DIR__ . '/RawTemplate.php');
        } elseif (3 === (int)$_REQUEST['i']) {
            // We're showing an inline search view, by choice.
            include(__DIR__ . '/SearchTemplate.php');
        } elseif (is_dir(self::$requestUnixPath)) {
            // We're showing an inline folder view; a list of thumbnails.
            include(__DIR__ . '/templates/ThumbsTemplate.php');
        } elseif (is_file(self::$requestUnixPath)) {
            // We're showing an inline file view.
            include(__DIR__ . '/templates/FileTemplate.php');
        } else {
            Log::debug('Search');
            // We're showing an inline search view, because we've nothing else to show.
            include(__DIR__ . '/SearchTemplate.php');
        }
    }

    /**
     * Display an error in the error box on the page.
     * @param string $string
     * @ToDo: use some kinda push tech to do this with Javascript.
     */
    public static function showError(string $string): void
    {
        echo $string;
    }

    /**
     * Purely to troll my wife, it generates a random name for the site each time it is called.
     * @return string
     */
    public static function getSiteName(): string
    {
        $a = array_rand(
            [
                'Memories', 'Mayhem', 'Merriment', 'Madness', 'Moonshine', 'Mountains', 'Mastery', 'Machines',
                'Messages', 'Metaphors', 'Meteors', 'Mistakes', 'Mondays', 'Mornings', 'Moaning', 'Mysteries'
            ]
        );
        return 'Midwest ' . $a;
    }

    /**
     * These headers prevent the browser from caching the page.
     * This is important for updates and session-handling.
     */
    private static function setNoCacheHeaders(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}
