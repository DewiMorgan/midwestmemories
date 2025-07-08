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
        // Params should be validated already, but we still give defaults.
        $fileId = intval($params['file_id']) ?? 0;
        $commentText = $params['comment_text'] ?? '';

        Log::debug("Adding comment to file $fileId", $commentText);
        $userName = User::getInstance()->username;
        if (empty($commentText)) {
            Log::warning('Ignoring empty comment string from ' . $params['path'], $commentText);
            return ['status' => 400, 'data' => 'Failed to save comment: empty comment'];
        } else {
            Log::debug('Valid data found from ' . $params['path'], $commentText);
            $response = self::execPostComment($fileId, $commentText, $userName);
            if ($response['status'] !== 200) {
                return $response;
            }
            $data = $response['data'] ?? [];
            if (empty($data)) {
                $error = 'Server error: empty comment data once added';
                Log::error('Empty data from comment by ' . $userName . ' on ' . $fileId, $commentText);
                return ['status' => 500, 'data' => $error];
            }
        }
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
        // Insert the new comment
        $insertSql = '
        INSERT INTO `' . Table::comments() . '`
            (`date_created`, `user`, `comment_text`, `fk_file`, `hidden`)
        VALUES (NOW(), ?, ?, ?, false)
        ';
        Log::debug("Db::sqlExec('$insertSql', 'ssi', '$userName', '$commentText', '$fileId')");
        $insertResult = Db::sqlExec($insertSql, 'ssi', $userName, $commentText, $fileId);

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
     * @param array $params as `['id' => '1']`.
     * @return array The output of the API call, not yet converted to JSON.
     */
    public static function deleteComment(array $params): array
    {
        $commentId = intval($params['id'] ?? 0);

        // Verify the comment exists.
        $comment = self::getCommentById($commentId);
        if (empty($comment)) {
            return ['status' => 404, 'data' => 'Comment not found'];
        }
        // Verify the user is an admin, or owns the comment.
        if (!User::getInstance()->isAdmin && $comment['user'] !== User::getInstance()->username) {
            return ['status' => 403, 'data' => "Cannot delete another user's comment"];
        }

        Log::debug('Deleting comment', $commentId);
        $sql = 'UPDATE `' . Table::comments() . '` SET `hidden` = true WHERE `id` = ?';
        $success = Db::sqlExec($sql, 'i', $commentId);
        if ($success) {
            return ['status' => 200, 'data' => 'OK'];
        }
        return ['status' => 500, 'data' => 'Server error: Failed to update comment'];
    }

    /**
     * PUT `/api/v1.0/comment`: edit a comment.
     * @param array $params as `['id' => '1', 'new_comment_text' => 'text']`.
     * @return array The output of the API call, not yet converted to JSON.
     */
    public static function editComment(array $params): array
    {
        $commentId = intval($params['id'] ?? 0);
        $newCommentText = $params['new_comment_text'] ?? '';
        if (empty($newCommentText)) {
            return ['status' => 400, 'data' => 'Missing new comment text'];
        }

        // Verify the comment exists.
        $comment = self::getCommentById($commentId);
        if (empty($comment)) {
            return ['status' => 404, 'data' => 'Comment not found'];
        }
        // Verify the user is an admin, or owns the comment.
        if (!User::getInstance()->isAdmin && $comment['user'] !== User::getInstance()->username) {
            return ['status' => 403, 'data' => "Cannot edit another user's comment"];
        }
        $sql = 'UPDATE `' . Table::comments() . '` SET `comment_text` = ? WHERE `id` = ?';
        $success = Db::sqlExec($sql, 'si', $newCommentText, $commentId);
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
     *      ['status' => 200, 'data' => [[id, date_created, user, comment_text, num_pages]...]].
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

        $sql = '
            WITH comment_count AS (
                SELECT LEAST(CEIL(COUNT(*)/?), 1000) AS `num_pages`
                FROM `' . Table::comments() . '`
                WHERE `fk_file` = ? AND NOT `hidden`
            )
            SELECT
                c.id,
                c.`date_created`,
                c.`user`,
                c.`comment_text`,
                cc.`num_pages`
            FROM `' . Table::comments() . '` c
            CROSS JOIN comment_count cc
            WHERE c.`fk_file` = ?
            AND NOT c.`hidden`
            ORDER BY c.`id`
            LIMIT ? OFFSET ?
        ';
        $result = Db::sqlGetTable($sql, 'sssss', $pageSize, $fileId, $fileId, $pageSize, $startItemCapped);
        return ['status' => 200, 'data' => $result];
    }

    /**
     * @param int $commentId The `id` field of the comment to get.
     * @return array Comments as a list of `[id, date_created, user, comment_text, num_pages]`.
     */
    private static function getCommentById(int $commentId): array
    {
        $sql = "
            SELECT 
                'OK' AS `error`,
                c.`id`, 
                c.`date_created`, 
                c.`user`, 
                c.`comment_text`
            FROM `" . Table::comments() . '` c
            WHERE c.`id` = ? AND NOT c.`hidden`
            LIMIT 1
        ';
        return Db::sqlGetRow($sql, 'i', $commentId);
    }
}
