<?php

declare(strict_types=1);

namespace MidwestMemories;

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
     * @param array $params as `['file_id' => '1', 'comment_text' => 'text', path' => 'comments', ...]`.
     * @return array The output of the API call, not yet converted to JSON.
     * ToDo: verification that the file exists.
     */
    public static function addComment(array $params): array
    {
Log::debug(''); // DELETEME DEBUG
        // Params should be validated already, but we still give defaults.
        $fileId = intval($params['file_id']) ?? 0;
        $commentText = $params['comment_text'] ?? '';

        Log::debug("Adding comment to file $fileId", $commentText);
        $userName = User::getInstance()->username;
Log::debug($userName); // DELETEME DEBUG
        if (empty($commentText)) {
            Log::warning('Ignoring empty comment string from ' . $params['path'], $commentText);
            return ['status' => 400, 'data' => 'Failed to save comment: empty comment'];
        } else {
            Log::debug('Valid data found from ' . $params['path'], $commentText);
            $data = self::execPostComment($fileId, $commentText, $userName);
        }
Log::debug(' - Returned:', $data); // DELETEME DEBUG
Log::debug(
    ' - Db After:',
    Db::sqlGetRow('SELECT * FROM `' . Db::TABLE_COMMENTS . '` WHERE `id` = ?', 'i', $data['id'])
); // DELETEME DEBUG
        return ['status' => 200, 'data' => $data];
    }

    /**
     * Helper to add a comment to an image's page.
     * @param int $fileId Foreign key into midmem_file_queue.
     * @param string $userName Username who made the comment.
     * @param string $commentText The text they are inserting.
     * @return array The output of the API call, not yet converted to JSON.
     */
    private static function execPostComment(int $fileId, string $commentText, string $userName): array
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
        Log::debug("Db::sqlExec('$insertSql', 'ssii', '$userName', '$commentText', '$nextSeq', '$fileId')");
        $insertResult = Db::sqlExec($insertSql, 'ssii', $userName, $commentText, $nextSeq, $fileId);

        if (empty($insertResult) || (0 === ($insertResult['rows'] ?? 0)) || (0 === ($insertResult['id'] ?? 0))) {
            Log::debug("Failed to add comment by $userName on $fileId", $commentText);
            Log::debug('Insert result', $insertResult);
            return ['status' => 500, 'data' => 'Server error: Failed to save comment'];
        } else {
            Log::debug("Added comment by $userName on $fileId", $commentText);
            return ['status' => 200, 'data' => self::getCommentById($insertResult['id'])];
        }
    }

    /**
     * DELETE `/api/v1.0/comment` endpoint marks a comment as soft-deleted.
     * @param int $commentId
     * @return array The output of the API call, not yet converted to JSON.
     */
    public static function deleteComment(int $commentId): array
    {
Log::debug('Deleting comment', $commentId);
Log::debug(' - Before:',
    var_export(Db::sqlGetRow('SELECT * FROM `' . Db::TABLE_COMMENTS . '` WHERE `id` = ?', 'i', $commentId), true)
); // DELETEME DEBUG
        $sql = 'UPDATE `' . Db::TABLE_COMMENTS . '` SET `hidden` = true WHERE `id` = ?';
        $success = Db::sqlExec($sql, 'i', $commentId);
Log::debug(' - After:',
    var_export(Db::sqlGetRow('SELECT * FROM `' . Db::TABLE_COMMENTS . '` WHERE `id` = ?', 'i', $commentId), true)
); // DELETEME DEBUG
        if ($success) {
            return ['status' => 200, 'data' => 'OK'];
        }
        return ['status' => 500, 'data' => 'Server error: Failed to update comment'];
    }

    /**
     * PUT `/api/v1.0/comment`: edit a comment.
     * @param int $commentId The database `id` field of the comment to edit.
     * @param string $newCommentText The new text for the comment.
     * @return array The output of the API call, not yet converted to JSON.
     */
    public static function editComment(int $commentId, string $newCommentText): array
    {
Log::debug("Editing comment: $commentId", $newCommentText);
Log::debug(
    ' - Before:',
    var_export(Db::sqlGetRow('SELECT * FROM `' . Db::TABLE_COMMENTS . '` WHERE `id` = ?', 'i', $commentId), true)
); // DELETEME DEBUG
        $sql = 'UPDATE `' . Db::TABLE_COMMENTS . '` SET `body_text` = ? WHERE `id` = ?';
        $success = Db::sqlExec($sql, 'si', $newCommentText, $commentId);
Log::debug(
    ' - After:',
    var_export(Db::sqlGetRow('SELECT * FROM `' . Db::TABLE_COMMENTS . '` WHERE `id` = ?', 'i', $commentId), true)
); // DELETEME DEBUG
        if ($success) {
            return ['status' => 200, 'data' => 'OK'];
        }
        return ['status' => 500, 'data' => 'Server error: Failed to update comment'];
    }

    /**
     * GET `/api/v1.0/comment`: return one page of comments for the current file.
     * @param array $params as `['file_id' => '1', 'page_id' => '2', ...]`.
     * @param int $pageSize Only there for unit tests. API uses default value.
     * @return array One page of comments as:
     *      ['status' => 200, 'data' => [sequence, date_created, user, body_text, num_pages]].
     * Note: `page_id` is capped to 1000.
     * ToDo: increase `$pageSize` default to 100.
     */
    public static function getComments(array $params, int $pageSize = 2): array
    {
        // Params should be validated already, but we still give defaults.
        $fileId = $params['file_id'] ?? 0;
        $pageId = $params['page_id'] ?? 0;

        $startItem = $pageId * $pageSize;
        $startItemCapped = max(0, min(1000, $startItem));
Log::debug("Getting comments: file $fileId, page $pageId, size $pageSize, start $startItem, capped $startItemCapped");

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
        $result = Db::sqlGetTable($sql, 'sssss', $pageSize, $fileId, $fileId, $pageSize, $startItemCapped);
Log::debug('Result', $result);
        return ['status' => 200, 'data' => $result];
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
                c.`id`, 
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
