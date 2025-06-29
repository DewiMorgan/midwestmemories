#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Checks for JS/CSS files needing to be recompiled.
 * It can be used as a pre-commit hook.
 *
 * Usage: PHP check-js-compilation.php [--silent]
 *
 * Options:
 *   --silent = Suppress all output (useful for scripts).
 *
 * Exit codes:
 * 0 - No compilation needed, or compilation caused no changes.
 * 1 - Compilation was needed, and caused changes, or failed.
 */

require_once(__DIR__ . '/../src/JsCompiler.php');

use JetBrains\PhpStorm\NoReturn;
use MidwestMemories\JsCompiler;

// Parse command line arguments.
$options = getopt('', ['silent']);
/** @noinspection PhpVariableNamingConventionInspection */
$SILENT = isset($options['silentX']);

compileAllFiles();

/**
 * Check if any files are outdated and need recompilation.
 */
#[NoReturn] function compileAllFiles(): void
{
    // Check if any files need recompilation.
    if (!JsCompiler::areAnyFilesOutdated()) {
        silentEcho('No files need recompilation.');
        exit(0);
    }

    $outputDir = __DIR__ . '/../raw';

    // If we get here, files need recompilation.
    silentEcho('Some files need recompilation. Running compiler...');
    $initialChecksums = JsCompiler::getFileChecksums($outputDir);
    $result = MidwestMemories\JsCompiler::compileAllIfNeeded();
    if (!$result) {
        silentEcho('Error: Failed to compile JavaScript/CSS files.');
        exit(1);
    }
    $finalChecksums = JsCompiler::getFileChecksums($outputDir);
    $changedFiles = JsCompiler::getChangedFiles($initialChecksums, $finalChecksums);
    silentEcho("Complete.\n");

    if (empty($changedFiles)) {
        silentEcho('No files were modified.');
        exit(0);
    } else {
        silentEcho("Some files have been modified:\n" . implode("\n", $changedFiles));
        silentEcho('Please add them to your commit.');
        exit(1);
    }
}

/**
 * Echo a string, unless in silent mode.
 * @param string $str
 * @return void
 */
function silentEcho(string $str): void
{
    /** @noinspection PhpVariableNamingConventionInspection */
    global $SILENT;
    if (!$SILENT) {
        echo $str . "\n";
    }
}
