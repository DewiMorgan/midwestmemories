<?php

declare(strict_types=1);

namespace MidwestMemories;

use JetBrains\PhpStorm\NoReturn;

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
        $itemPath = IndexGateway::$requestUnixPath;

        $mimeType = mime_content_type($itemPath);

        // Validate.
        if (!is_file($itemPath)) {
            Log::debug("Not a file: $itemPath");
            static::show404Page();
        } elseif (!Path::canViewFilename($itemPath)) {
            Log::debug("Not an viewable file: $itemPath");
            static::show404Page();
        }

        // Only show the file on success.
        header("Content-Type: $mimeType");
        readfile($itemPath);
    }

    /**
     * If we can't or don't want to show a file, we can show a 404 page with the correct response code, and exit.
     */
    #[NoReturn] private static function show404Page(): void
    {
        http_response_code(404);
        include($_SERVER['DOCUMENT_ROOT'] . '/nonexistent.file'); // Triggers Apache's 404
        exit;
    }
}
