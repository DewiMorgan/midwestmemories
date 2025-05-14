<?php

declare(strict_types=1);

namespace MidwestMemories;

use JsonException;

/**
 * The class for the main index.php file.
 */
class Index
{
    // Constants.
    public const MM_BASE_URL = 'https://midwestmemories.dewimorgan.com';

    // Full user-requested path relative to hdd /. If set, exists in Path::$imageBasePath. No trailing slash on folders.
    public static string $requestUnixPath;
    public static string $requestWebPath;

    public function __construct()
    {
        self::handleLogouts();
        Path::validateBaseDir();
        self::initSession();

        self::$requestWebPath = $_REQUEST['path'] ?? '/';
        self::$requestUnixPath = Path::webToUnixPath(self::$requestWebPath); // Dies if not correct.

        self::showPage();
    }

    /**
     * Handle logout requests.
     */
    private static function handleLogouts(): void
    {
        // Handle logouts.
        if (array_key_exists('logout', $_REQUEST) && $_REQUEST['logout'] && 'true' === $_SESSION['login']) {
            header('HTTP/1.1 401 Unauthorized');
            $_SESSION['login'] = 'false';
            echo "<!DOCTYPE html>\n";
            echo '<html lang="en"><head><title>Logout</title></head><body><h1>Logged out</h1><p><a href="'
                . $_SERVER['PHP_SELF'] . '">Click here to log back in.</a></p></body></html>' . "\n";
            exit(0);
        }
    }

    /**
     * Handle session and connection.
     */
    private static function initSession(): void
    {
        $_SESSION['login'] = 'true';
        $_SESSION['name'] = $_SERVER['PHP_AUTH_USER'];

        $connection = new Connection();

        // Log this login.
        // ToDo: this is very low level, and should probably be wrapped in a connectionLogger.
        Db::sqlExec(
            'INSERT INTO midmem_visitors (`request`, `main_ip`, `all_ips_string`, `user`, `agent`)'
            . ' VALUES (?, ?, ?, ?, ?)',
            'sssss',
            $connection->request,
            $connection->ip,
            $connection->ipList,
            $connection->user,
            $connection->agent
        );
    }

    /**
     * Handle displaying the requested page.
     */
    private static function showPage(): void
    {
        // Inline requires are internal requests, rather than user requests.
        // blank = user or API request.
        // 1 = inline thing (file or folder sub-template).
        // 2 = raw thing (image). ToDo: sniff this by type?
        // 3 = search view.
        $isInlineRequest = isset($_REQUEST['i']);

        if (Path::isApiPath(self::$requestWebPath)) {
            // We're outputting an API call.
            echo static::execApiCall();
        } elseif (!$isInlineRequest) {
            // This is a request by a user, perhaps to a bookmark.
            // Load the tree view, which will then call us back for the inline version of the pointed-at $path resource.
            include(__DIR__ . '/TreeTemplate.php');
        } elseif (2 === (int)$_REQUEST['i']) {
            // We're showing raw file view, such as for an img link.
            include(__DIR__ . '/RawTemplate.php');
        } elseif (3 === (int)$_REQUEST['i']) {
            // We're showing an inline search view, by choice.
            include(__DIR__ . '/SearchTemplate.php');
        } elseif (is_dir(static::$requestUnixPath)) {
            // We're showing an inline folder view; a list of thumbnails.
            include(__DIR__ . '/ThumbsTemplate.php');
        } elseif (is_file(static::$requestUnixPath)) {
            // We're showing an inline file view.
            include(__DIR__ . '/FileTemplate.php');
        } else {
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
                'Messages', 'Metaphor', 'Meteor', 'Mistakes', 'Mondays', 'Mornings', 'Moaning', 'Mystery'
            ]
        );
        return 'Midwest ' . $a;
    }

    /**
     * Looks at the request method and the first element of the path, to generate an endpoint string.
     * So "GET /messages/test" => "getMessages". Then performs an operation depending on that string.
     * @return string The output of the API call, as JSON.
     */
    private static function execApiCall(): string
    {
        $firstPart = preg_split('#/#', self::$requestWebPath, 2, PREG_SPLIT_NO_EMPTY);
        if (is_array($firstPart)) {
            $endpoint = strtolower($_SERVER['REQUEST_METHOD']) . ucwords($firstPart[0]);
            $param1 = $firstPart[1] ?? null;
            $data = match ($endpoint) {
                'getMessage' => self::execGetMessages(intval($param1)),
            };
            try {
                $encoded = json_encode($data, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                Log::error('Failed to encode data', self::$requestWebPath);
                $encoded = "{'error':'Failed to encode data'}";
            }
        } else {
            Log::error('Bad API request path', self::$requestWebPath);
            $encoded = "{'error':'Bad API request path'}";
        }
        Log::debug('Returning', $encoded);
        return $encoded;
    }

    /**
     * @param int $fileId The `id` field of the file that we want comments for.
     * @return array Comments as a list of [sequence, date_created, user, body_text].
     */
    private static function execGetMessages(int $fileId): array
    {
        $sql = '
            SELECT sequence, date_created, user, body_text FROM midmem_comments c
            WHERE c.fk_file = ?
            AND hidden != true
            ORDER BY c.sequence
         ';
        return Db::sqlGetTable($sql, $fileId);
    }
}
