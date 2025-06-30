<?php

declare(strict_types=1);

namespace MidwestMemories;

use JsonException;

/**
 * Manage the operations on comments.
 */
class CommentManager extends Singleton
{
    /**
     * Protected singleton constructor.
     */
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * POST `/api/v1.0/comment`: add a comment.
     * @param int $fileId Foreign key into midmem_file_queue.
     * @param string $bodyText The text they are inserting.
     * @return string[] The output of the API call, not yet converted to JSON.
     */
    public static function addComment(int $fileId, string $bodyText): array
    {
        $userName = User::getInstance()->username;
        if (empty($bodyText)) {
            Log::warning('Ignoring empty comment struct from ' . IndexGateway::$requestWebPath, $bodyText);
            $data = ['error' => 'Failed to save comment 1'];
        } elseif (!is_array($bodyText)) {
            Log::warning('Ignoring non-array comment text from ' . IndexGateway::$requestWebPath, $bodyText);
            $data = ['error' => 'Failed to save comment 2'];
        } elseif (!array_key_exists('body_text', $bodyText)) {
            Log::warning('Ignoring missing body_text key from ' . IndexGateway::$requestWebPath, $bodyText);
            $data = ['error' => 'Failed to save comment 3'];
        } elseif (empty($bodyText['body_text'])) {
            Log::warning('Ignoring empty body_text from ' . IndexGateway::$requestWebPath, $bodyText);
            $data = ['error' => 'Failed to save comment 4'];
        } else {
            Log::debug('Valid data found from ' . IndexGateway::$requestWebPath, $bodyText);
            $data = self::execPostComment($fileId, $bodyText['body_text'], $userName);
        }
        return $data;
    }

    /**
     * Helper to add a comment to an image's page.
     * @param int $fileId Foreign key into midmem_file_queue.
     * @param string $userName Username who made the comment.
     * @param string $bodyText The text they are inserting.
     * @return string[]
     */
    private static function execPostComment(int $fileId, string $bodyText, string $userName): array
    {
        // Get the next sequence number for this file
        $sql = 'SELECT MAX(`sequence`) AS `seq` FROM `' . Db::TABLE_COMMENTS . '` WHERE `fk_file` = ?';
        $currentSeq = Db::sqlGetValue('seq', $sql, 'i', $fileId);
        $nextSeq = is_numeric($currentSeq) ? ((int)$currentSeq + 1) : 1;

        // Insert the new comment
        $insertSql = '
        INSERT INTO `' . Db::TABLE_COMMENTS . '`
            (`date_created`, `user`, `body_text`, `sequence`, `fk_file`, `hidden`)
        VALUES (NOW(), ?, ?, ?, ?, false)
        ';
        Log::debug("Db::sqlExec('$insertSql', 'ssii', '$userName', '$bodyText', '$nextSeq', '$fileId')");
        $insertResult = Db::sqlExec($insertSql, 'ssii', $userName, $bodyText, $nextSeq, $fileId);

        if (empty($insertResult) || (0 === ($insertResult['rows'] ?? 0)) || (0 === ($insertResult['id'] ?? 0))) {
            Log::debug("Failed to add comment by $userName on $fileId", $bodyText);
            Log::debug('Insert result', $insertResult);
            return ['error' => 'Failed to save comment 5'];
        } else {
            Log::debug("Added comment by $userName on $fileId", $bodyText);
            return self::getCommentById($insertResult['id']);
        }
    }

    /**
     * DELETE `/api/v1.0/comment` endpoint marks a comment as soft-deleted.
     * @param int $commentId
     * @return void
     */
    public static function deleteComment(int $commentId): array
    {
        $sql = 'UPDATE `' . Db::TABLE_COMMENTS . '` SET `hidden` = true WHERE `id` = ?';
        Db::sqlExec($sql, 'i', $commentId);

    }

    /**
     * PUT `/api/v1.0/comment`: edit a comment.
     * @param int $commentId The database `id` field of the comment to edit.
     * @param string $newCommentText The new text for the comment.
     * @return void
     */
    public static function editComment(int $commentId, string $newCommentText): void
    {
        $sql = 'UPDATE `' . Db::TABLE_COMMENTS . '` SET `body_text` = ? WHERE `id` = ?';
        Db::sqlExec($sql, 'si', $newCommentText, $commentId);
    }

    /**
     * GET `/api/v1.0/comment`: return one page of comments for the current file.
     * @param int $pageId Which page of results to return.
     * @param int $fileId The database `id` field of the file to retrieve comments for.
     * @return array One page of comments as a list of [sequence, date_created, user, body_text, num_pages].
     * Note: `num_pages` is capped to 1000.
     * ToDo: increase `$pageSize` default to 100.
     */
    public static function getComments(int $pageId, int $fileId, int $pageSize = 2): array
    {
        $startItem = $pageId * $pageSize;
        $startItemCapped = max(0, min(1000, $startItem));
        $sql = '
            WITH comment_count AS (
                SELECT LEAST(CEIL(COUNT(*)/?), 1000) AS `num_pages`
                FROM `' . Db::TABLE_COMMENTS . '`
                WHERE `fk_file` = ? AND NOT `hidden`
            )
            SELECT 
                c.`sequence`, 
                c.`date_created`, 
                c.`user`, 
                c.`body_text`,
                cc.`num_pages`
            FROM `' . Db::TABLE_COMMENTS . '` c
            CROSS JOIN comment_count cc
            WHERE c.`fk_file` = ?
            AND NOT c.`hidden`
            ORDER BY c.`sequence`
            LIMIT ? OFFSET ?
        ';
        return Db::sqlGetTable($sql, 'sssss', $pageSize, $fileId, $fileId, $pageSize, $startItemCapped);
    }

    /**
     * @param int $commentId The `id` field of the comment to get.
     * @return array Comments as a list of [sequence, date_created, user, body_text, num_pages].
     */
    private static function getCommentById(int $commentId): array
    {
        $sql = "
            SELECT 
                'OK' AS `error`,
                c.`sequence`, 
                c.`date_created`, 
                c.`user`, 
                c.`body_text`
            FROM `" . Db::TABLE_COMMENTS . '` c
            WHERE c.id = ?
            LIMIT 1
        ';
        return Db::sqlGetRow($sql, 'i', $commentId);
    }

//    /**
//     * Looks at the request method and the first element of the path, to generate an endpoint string.
//     * So "GET /api/v1.0/messages/test" => "getMessages". Then performs an operation depending on that string.
//     * @return string The output of the API call, as JSON.
//     */
//    private static function execApiCall(): string
//    {
//        Log::debug('Starting...', self::$requestWebPath);
//        $pathParts = preg_split('#/#', self::$requestWebPath, -1, PREG_SPLIT_NO_EMPTY);
//        if (is_array($pathParts)) {
//            $endpoint = strtolower($_SERVER['REQUEST_METHOD']) . ucwords($pathParts[1]);
//
//            $fileId = intval($pathParts[2] ?? 0);
//            switch ($endpoint) {
//                case 'getComment':
//                    $data = self::getComment($pathParts[3], $fileId);
//                    break;
//                case 'postComment':
//                    $data = self::postComment($fileId);
//                    break;
//                default:
//                    $data = ['error' => "Unknown endpoint $endpoint"];
//                    break;
//            }
//            try {
//                $encoded = json_encode($data, JSON_THROW_ON_ERROR);
//            } catch (JsonException) {
//                Log::error('Failed to encode data', self::$requestWebPath);
//                $encoded = "{'error':'Failed to encode data'}";
//            }
//        } else {
//            Log::warning('Bad API request path', self::$requestWebPath);
//            $encoded = "{'error':'Bad API request path'}";
//        }
//        Log::debug('...returning', $encoded);
//        return $encoded;
//    }
}
