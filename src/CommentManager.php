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
     * @param int $fileId The `id` field of the file that we want comments for.
     * @param int $pageSize Max quantity of records to return. Capped between 1 and 100.
     * @param int $startItem Which item in the list to start at, starting at 0. Capped between 0 and 1000.
     * @return array Comments as a list of [sequence, date_created, user, body_text, num_pages].
     */
    public static function getComments(int $fileId, int $pageSize, int $startItem): array
    {
        $pageSizeCapped = max(1, min(100, $pageSize));
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
        return Db::sqlGetTable($sql, 'sssss', $pageSizeCapped, $fileId, $fileId, $pageSizeCapped, $startItemCapped);
    }

    /**
     * @return void
     */
    public static function addComment(): void {}

    /**
     * @return void
     */
    public static function editComment(): void {}

    /**
     * @return void
     */
    public static function deleteComment(): void {}
}
