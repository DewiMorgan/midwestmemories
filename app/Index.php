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
        Log::debug('Search "' . self::$requestUnixPath . '"', self::$requestWebPath);
        // Inline requires are internal requests, rather than user requests.
        // blank = user or API request.
        // 1 = inline thing (file or folder sub-template).
        // 2 = raw thing (image). ToDo: sniff this by type?
        // 3 = search view.
        $isInlineRequest = isset($_REQUEST['i']);

        if (Path::isApiPath(self::$requestWebPath)) {
            // We're outputting an API call.
            Log::debug('API');
            echo static::execApiCall();
        } elseif (!$isInlineRequest) {
            Log::debug('User');
            // This is a request by a user, perhaps to a bookmark.
            // Load the tree view, which will then call us back for the inline version of the pointed-at $path resource.
            include(__DIR__ . '/TreeTemplate.php');
        } elseif (2 === (int)$_REQUEST['i']) {
            // We're showing raw file view, such as for an img link.
            include(__DIR__ . '/RawTemplate.php');
        } elseif (3 === (int)$_REQUEST['i']) {
            // We're showing an inline search view, by choice.
            include(__DIR__ . '/SearchTemplate.php');
        } elseif (is_dir(self::$requestUnixPath)) {
            // We're showing an inline folder view; a list of thumbnails.
            include(__DIR__ . '/ThumbsTemplate.php');
        } elseif (is_file(self::$requestUnixPath)) {
            // We're showing an inline file view.
            include(__DIR__ . '/FileTemplate.php');
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
                'Messages', 'Metaphor', 'Meteor', 'Mistakes', 'Mondays', 'Mornings', 'Moaning', 'Mystery'
            ]
        );
        return 'Midwest ' . $a;
    }

    /**
     * Looks at the request method and the first element of the path, to generate an endpoint string.
     * So "GET v1/messages/test" => "getMessages". Then performs an operation depending on that string.
     * @return string The output of the API call, as JSON.
     */
    private static function execApiCall(): string
    {
        Log::debug('Starting...', self::$requestWebPath);
        $pathParts = preg_split('#/#', self::$requestWebPath, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($pathParts)) {
            $endpoint = strtolower($_SERVER['REQUEST_METHOD']) . ucwords($pathParts[1]);

            $fileId = $pathParts[2] ?? null;
            switch ($endpoint) {
                case 'getComment':
                    $requestedPage = intval($pathParts[3] ?? 0);
                    $pageSize = 2; // ToDo: increase this to 100.
                    $startItem = $requestedPage * $pageSize;
                    $data = self::execGetComments(intval($fileId), $pageSize, $startItem);
                    break;
                case 'postComment':
                    $userName = $_SERVER['PHP_AUTH_USER'];
                    $bodyText = json_decode(file_get_contents('php://input'), true);
                    if (empty($bodyText)) {
                        Log::warning('Ignoring empty comment text from ' . self::$requestWebPath, $bodyText);
                        $data = ['error' => 'Failed to save comment'];
                    } else {
                        Log::debug('Valid data found from ' . self::$requestWebPath, $bodyText);
                        $data = self::execPostComment($fileId, $userName, $bodyText);
                    }
                    break;
                default:
                    $data = ['error' => "Unknown endpoint $endpoint"];
                    break;
            }
            try {
                $encoded = json_encode($data, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                Log::error('Failed to encode data', self::$requestWebPath);
                $encoded = "{'error':'Failed to encode data'}";
            }
        } else {
            Log::warning('Bad API request path', self::$requestWebPath);
            $encoded = "{'error':'Bad API request path'}";
        }
        Log::debug('...returning', $encoded);
        return $encoded;
    }

    /**
     * @param int $fileId The `id` field of the file that we want comments for.
     * @param int $pageSize Max quantity of records to return. Capped between 1 and 100.
     * @param int $startItem Which item in the list to start at, starting at 0. Capped between 0 and 1000.
     * @return array Comments as a list of [sequence, date_created, user, body_text, num_pages].
     */
    private static function execGetComments(int $fileId, int $pageSize, int $startItem): array
    {
        $pageSizeCapped = max(1, min(100, $pageSize));
        $startItemCapped = max(0, min(1000, $startItem));
        $sql = '
            WITH comment_count AS (
                SELECT LEAST(CEIL(COUNT(*)/?), 1000) AS `num_pages`
                FROM `midmem_comments`
                WHERE `fk_file` = ? AND NOT `hidden`
            )
            SELECT 
                c.`sequence`, 
                c.`date_created`, 
                c.`user`, 
                c.`body_text`,
                cc.`num_pages`
            FROM `midmem_comments` c
            CROSS JOIN comment_count cc
            WHERE c.`fk_file` = ?
            AND NOT c.`hidden`
            ORDER BY c.`sequence`
            LIMIT ? OFFSET ?
        ';
        return Db::sqlGetTable($sql, 'sssss', $pageSizeCapped, $fileId, $fileId, $pageSizeCapped, $startItemCapped);
    }

    /**
     * Let a user add a comment to an image's page.
     * @param int $fileId Foreign key into midmem_file_queue.
     * @param string $userName Username who made the comment.
     * @param string $bodyText The text they are inserting.
     * @return string[]
     */
    public static function execPostComment(int $fileId, string $userName, string $bodyText): array
    {
        // Get the next sequence number for this file
        $sql = 'SELECT MAX(sequence) AS seq FROM midmem_comments WHERE fk_file = ?';
        $currentSeq = Db::sqlGetItem($sql, 'seq', $fileId);
        $nextSeq = is_numeric($currentSeq) ? ((int)$currentSeq + 1) : 1;

        // Insert the new comment
        $insertSql = 'INSERT INTO midmem_comments (date_created, user, body_text, sequence, fk_file, hidden)
                  VALUES (NOW(), ?, ?, ?, ?, false)';
        Log::debug("Db::sqlExec('$insertSql', '$userName', '$bodyText', '$nextSeq', '$fileId')"); // DELETEME DEBUG
        //$result = Db::sqlExec($insertSql, $userName, $bodyText, $nextSeq, $fileId);  // UN-COMMENT-ME DEBUG

        if (!empty($result)) {
            Log::debug("Added comment by $userName on $fileId", $bodyText);
            return ['error' => 'OKx']; // DELETEME DEBUG
        } else {
            Log::debug("Failed to add comment by $userName on $fileId", $bodyText);
            return ['error' => 'Failed to save comment'];
        }
    }
}
