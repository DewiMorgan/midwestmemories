<?php

declare(strict_types=1);

namespace MidwestMemories;

RawTemplate::showFile();

/** Template to display the literal binary content of a file, such as an image for an img tag.
 * Requirements:
 *   Index::$realPath as string -> unique key identifies dir, file, or later, search result!
 */
class RawTemplate
{
    /**
     * Output the binary data of the chosen file directly to stdout.
     */
    public static function showFile(): void
    {
        $itemPath = Index::$requestUnixPath;

        // DELETEME DEBUG
        if (!is_file($itemPath)) {
            Log::adminDebug("Not a file: $itemPath");
        } elseif (str_starts_with($itemPath, 'tn_')) {
            Log::adminDebug("Starts with tn_: $itemPath");
        } elseif (str_starts_with($itemPath, '.')) {
            Log::adminDebug("Hidden dot file: $itemPath");
        } elseif (!preg_match('/\.(gif|png|jpg|jpeg)$/', $itemPath)) {
            Log::adminDebug("Not an image file: $itemPath");
        }

        readfile($itemPath);
    }
}
