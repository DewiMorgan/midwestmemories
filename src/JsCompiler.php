<?php

declare(strict_types=1);

namespace MidwestMemories;

use MidwestMemories\Enum\Key;

/**
 * JsCompiler - A utility class for compiling JavaScript files.
 *
 * This class provides functionality to concatenate multiple JavaScript files
 * from the /src/Js/ directory into a single output file.
 */
class JsCompiler
{
    public static array $adminFiles = [
        'Api.js',
        'Dropbox.js',
        'HtmlUtils.js',
        'Log.js',
        'Users.js',
        'UserTable.js',
        'AdminPage.js',
    ];
    public static array $userFiles = [
        'Api.js',
        'HtmlUtils.js',
        'Log.js',
        'UserPage.js',
    ];

    /**
     * Generate all the JavaScript files.
     * @return bool
     */
    public static function compileAll(): bool
    {
        return self::compile(self::$adminFiles, __DIR__ . '/Js/admin.js')
            && self::compile(self::$userFiles, __DIR__ . '/Js/user.js');
    }

    /**
     * Concatenates multiple JavaScript files into a single output file.
     *
     * @param string[] $inputFiles Array of filenames relative to /src/Js/
     * @param string $outputFile The target output file path
     * @return bool True on success, false on failure
     */
    public static function compile(array $inputFiles, string $outputFile, string $jsDir = null): bool
    {
        $jsDir = $jsDir ?? dirname(__DIR__) . '/../' . Conf::get(Key::IMAGE_DIR) . '/';
        $output = '';

        // First, verify all files exist.
        foreach ($inputFiles as $file) {
            $filePath = $jsDir . ltrim($file, '/');
            if (!file_exists($filePath)) {
                error_log('JsCompiler: File not found: ' . $filePath);
                return false;
            }
        }

        // Then process them.
        foreach ($inputFiles as $file) {
            $filePath = $jsDir . ltrim($file, '/');
            $content = file_get_contents($filePath);
            if ($content === false) {
                error_log('JsCompiler: Could not read file: ' . $filePath);
                return false;
            }

            // Add file header.
            $output .= "\n/* Source: " . basename($filePath) . " */\n";
            $output .= $content . "\n";
        }

        // If the output directory doesn't exist, fail.
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            error_log('JsCompiler: Directory not found: ' . $outputDir);
            return false;
        }

        return file_put_contents($outputFile, $output, LOCK_EX) !== false;
    }
}
