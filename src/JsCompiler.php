<?php

declare(strict_types=1);

namespace MidwestMemories;

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
        'DragBar.js',
        'Log.js',
        'TreeView.js',
    ];

    /**
     * Check if any files are outdated and need recompilation.
     * @return bool If any files need recompilation.
     */
    public static function areAnyFilesOutdated(): bool
    {
        return self::isFileOutdated(self::$adminFiles, __DIR__ . '/../raw/admin.js')
            || self::isFileOutdated(self::$userFiles, __DIR__ . '/../raw/user.js')
            || self::isFileOutdated(['admin.css'], __DIR__ . '/../raw/admin.css')
            || self::isFileOutdated(['user.css'], __DIR__ . '/../raw/user.css');
    }

    /**
     * If the output files are outdated, compile the JS files and copy the CSS.
     * @return bool Success.
     */
    public static function compileAllIfNeeded(): bool
    {
        $outputDir = __DIR__ . '/../raw';
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
            $result = $result && copy("$cssDir/user.css", "$outputDir/user.css");
        }
        return $result;
    }

    /**
     * Check if an output file needs reinstalling or recompiling.
     * @param array $inputFiles Array of filenames relative to /src/Js/
     * @param string $outputFile Absolute path to the output file
     * @param ?string $jsDir Folder to find source JS files in, including trailing slash.
     * @return bool If file is outdated or missing.
     */
    public static function isFileOutdated(array $inputFiles, string $outputFile, ?string $jsDir = null): bool
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

    /**
     * @param string $dir The directory to scan.
     * @return array The checksums of the files in the directory.
     */
    public static function getFileChecksums(string $dir): array
    {
        $checksums = [];
        foreach (glob($dir . '/*.{css,js}', GLOB_BRACE) as $file) {
            $checksums[$file] = md5_file($file);
        }
        return $checksums;
    }

    /**
     * @param array $before The checksums before compilation.
     * @param array $after The checksums after compilation.
     * @return array The list of changed files.
     */
    public static function getChangedFiles(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $file => $newChecksum) {
            if (!isset($before[$file]) || $before[$file] !== $newChecksum) {
                $status = !isset($before[$file]) ? 'NEW' : 'MODIFIED';
                $changed[] = sprintf(
                    '%s: %s (%s bytes)',
                    $status,
                    basename($file),
                    filesize($file)
                );
            }
        }
        return $changed;
    }
}
