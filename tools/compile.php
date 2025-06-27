#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Generate a single JS file from multiple JS files.
 */
require_once(__DIR__ . '/../src/JsCompiler.php');
compileAllFiles();
echo(0);

/**
 * @return void
 */
function compileAllFiles(): void
{
    $outputDir = __DIR__ . '/../raw';

// Get initial checksums.
    $initialChecksums = getFileChecksums($outputDir);

    echo 'Compiling...';
    MidwestMemories\JsCompiler::compileAllIfNeeded();
    echo "complete.\n\n";

// Get checksums after compilation.
    $finalChecksums = getFileChecksums($outputDir);
    $changedFiles = getChangedFiles($initialChecksums, $finalChecksums);

    if (empty($changedFiles)) {
        echo "No files were modified.\n";
    } else {
        echo "Modified files:\n" . implode("\n", $changedFiles) . "\n";
    }
}

/**
 * @param array $before The checksums before compilation.
 * @param array $after The checksums after compilation.
 * @return array The list of changed files.
 */
function getChangedFiles(array $before, array $after): array
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

/**
 * @param string $dir The directory to scan.
 * @return array The checksums of the files in the directory.
 */
function getFileChecksums(string $dir): array
{
    $checksums = [];
    foreach (glob($dir . '/*.{css,js}', GLOB_BRACE) as $file) {
        $checksums[$file] = md5_file($file);
    }
    return $checksums;
}
