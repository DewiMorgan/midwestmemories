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
     * If the output files are outdated, compile the JS files and copy the CSS.
     * @return bool Success.
     */
    public static function compileAllIfNeeded(): bool
    {
        $outputDir = __DIR__ . '/../' . Conf::get(Key::IMAGE_DIR);
        $result = true;
        // Handle Js.
        if (self::isFileOutdated(self::$adminFiles, "$outputDir/admin.js")) {
            $result = self::compile(self::$adminFiles, "$outputDir/admin.js");
        }
        if (self::isFileOutdated(self::$userFiles, "$outputDir/user.js")) {
            $result = $result && self::compile(self::$userFiles, "$outputDir/user.js");
        }
        // Handle Css.
        $cssDir = __DIR__ . '/Css/';
        if (self::isFileOutdated(['admin.css'], "$outputDir/admin.css", $cssDir)) {
            $result = $result && copy("$cssDir/admin.css", "$outputDir/admin.css");
        }
        if (self::isFileOutdated(['user.css'], "$outputDir/user.css", $cssDir)) {
            $result = $result && copy("$cssDir/admin.css", "$outputDir/admin.css");
        }
        return $result;
    }

    /**
     * Check if an output file needs reinstalling or recompiling.
     * @param array $inputFiles Array of filenames relative to /src/Js/
     * @param string $outputFile Absolute path to the output file
     * @param string|null $jsDir Folder to find source JS files in, including trailing slash.
     * @return bool If file is outdated or missing.
     */
    public static function isFileOutdated(array $inputFiles, string $outputFile, string $jsDir = null): bool
    {
        if (!file_exists($outputFile)) {
            return true;
        }
        foreach ($inputFiles as $file) {
            $inputFilePath = $jsDir . ltrim($file, '/');
            if (!file_exists($inputFilePath)) {
                return true;
            }
            if (filemtime($inputFilePath) > filemtime($outputFile)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate all the JavaScript files.
     * @return bool Success.
     */
    public static function compileAll(): bool
    {
        $outputDir = __DIR__ . '/../' . Conf::get(Key::IMAGE_DIR);
        return self::compile(self::$adminFiles, "$outputDir/admin.js")
            && self::compile(self::$userFiles, "$outputDir/user.js");
    }

    /**
     * Concatenates multiple JavaScript files into a single output file.
     *
     * @param string[] $inputFiles Array of filenames relative to /src/Js/
     * @param string $outputFile The target output file path.
     * @param string|null $jsDir Folder to find source JS files in, including trailing slash.
     * @return bool True on success, false on failure
     */
    public static function compile(array $inputFiles, string $outputFile, string $jsDir = null): bool
    {
        $jsDir = $jsDir ?? __DIR__ . '/Js/';
        $output = '';

        // First, verify all files exist.
        foreach ($inputFiles as $file) {
            $inputFilePath = $jsDir . ltrim($file, '/');
            if (!file_exists($inputFilePath)) {
                Log::error('JsCompiler: Input file not found: ' . $inputFilePath);
                return false;
            }
        }

        // Then process them.
        foreach ($inputFiles as $file) {
            $inputFilePath = $jsDir . ltrim($file, '/');
            $content = file_get_contents($inputFilePath);
            if ($content === false) {
                Log::error('JsCompiler: Could not read input file: ' . $inputFilePath);
                return false;
            }

            // Add file header.
            $output .= "\n/* Source: " . basename($inputFilePath) . " */\n";
            $output .= $content . "\n";
        }

        // If the output directory doesn't exist, fail.
        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            Log::error('JsCompiler: Output directory not found: ' . $outputDir);
            return false;
        }

        return file_put_contents($outputFile, $output, LOCK_EX) !== false;
    }
}
