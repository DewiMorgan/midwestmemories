<?php
/** @noinspection PhpMethodNamingConventionInspection */
/** @noinspection PhpEnforceDocCommentInspection */
declare(strict_types=1);

namespace Test\Unit;

use MidwestMemories\JsCompiler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MidwestMemories\JsCompiler
 */
class JsCompilerTest extends TestCase
{
    private const TEST_JS_DIR = __DIR__ . '/../../test-js';
    private const OUTPUT_DIR = __DIR__ . '/../../test-output';

    /**
     * This method is called before each test.
     *
     * @codeCoverageIgnore
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test directories if they don't exist
        if (!is_dir(self::TEST_JS_DIR)) {
            mkdir(self::TEST_JS_DIR, 0755, true);
        }
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0755, true);
        }

        // Create test JS files
        file_put_contents(self::TEST_JS_DIR . '/test1.js', "// Test file 1\nconst test1 = 1;");
        file_put_contents(self::TEST_JS_DIR . '/test2.js', "// Test file 2\nconst test2 = 2;");
    }

    /**
     * This method is called after each test.
     *
     * @codeCoverageIgnore
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // If test directories exist, delete them and clean up test files.
        if (is_dir(self::TEST_JS_DIR) && is_dir(self::OUTPUT_DIR)) {
            $files = array_merge(
                glob(self::TEST_JS_DIR . '/*.js') ?: [],
                glob(self::OUTPUT_DIR . '/*.js') ?: []
            );

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir(self::TEST_JS_DIR);
            rmdir(self::OUTPUT_DIR);
        }
    }

    public function testCompileSingleFile(): void
    {
        $outputFile = self::OUTPUT_DIR . '/output.js';
        $result = JsCompiler::compile(
            ['test1.js'],
            $outputFile,
            self::TEST_JS_DIR . '/' // Override default JS directory for testing
        );

        static::assertTrue($result);
        static::assertFileExists($outputFile);
        static::assertStringContainsString('// Test file 1', file_get_contents($outputFile));
    }

    public function testCompileMultipleFiles(): void
    {
        $outputFile = self::OUTPUT_DIR . '/output.js';
        $result = JsCompiler::compile(
            ['test1.js', 'test2.js'],
            $outputFile,
            self::TEST_JS_DIR . '/'
        );

        static::assertTrue($result);
        static::assertFileExists($outputFile);
        $content = file_get_contents($outputFile);
        static::assertStringContainsString('// Test file 1', $content);
        static::assertStringContainsString('// Test file 2', $content);
        static::assertStringContainsString('/* Source: test1.js */', $content);
    }

    public function testCompileNonExistentFile(): void
    {
        $outputFile = self::OUTPUT_DIR . '/output.js';
        $result = JsCompiler::compile(
            ['nonexistent.js'],
            $outputFile,
            self::TEST_JS_DIR . '/'
        );

        static::assertFalse($result);
        static::assertFileDoesNotExist($outputFile);
    }

    public function testCompileToNonWritableDirectory(): void
    {
        $outputFile = '/non/existent/path/output.js';
        $result = JsCompiler::compile(
            ['test1.js'],
            $outputFile,
            self::TEST_JS_DIR . '/'
        );

        static::assertFalse($result);
    }

    public function testIsFileCompiledWhenOutputDoesNotExist(): void
    {
        $outputFile = self::OUTPUT_DIR . '/nonexistent.js';
        $result = JsCompiler::isFileOutdated(
            ['test1.js'],
            $outputFile,
            self::TEST_JS_DIR . '/'
        );

        static::assertTrue($result, 'Should return false when output file does not exist');
    }

    public function testIsFileCompiledWhenInputDoesNotExist(): void
    {
        $outputFile = self::OUTPUT_DIR . '/output.js';
        file_put_contents($outputFile, 'test');

        $result = JsCompiler::isFileOutdated(
            ['nonexistent.js'],
            $outputFile,
            self::TEST_JS_DIR . '/'
        );

        static::assertTrue($result, 'Should return false when any input file does not exist');
    }

    public function testIsFileCompiledWhenInputIsNewer(): void
    {
        $outputFile = self::OUTPUT_DIR . '/output.js';
        $inputFile = self::TEST_JS_DIR . '/test1.js';

        // Create output file first.
        file_put_contents($outputFile, 'test');
        touch($outputFile, time() - 3600); // Set modification time to 1 hour ago.

        // Update input file to be newer than output.
        touch($inputFile);

        $result = JsCompiler::isFileOutdated(
            ['test1.js'],
            $outputFile,
            self::TEST_JS_DIR . '/'
        );

        static::assertTrue($result, 'Should return false when any input file is newer than output');
    }

    public function testIsFileCompiledWhenUpToDate(): void
    {
        $outputFile = self::OUTPUT_DIR . '/output.js';
        file_put_contents($outputFile, 'test');

        // Make sure output file is newer than input files.
        touch($outputFile, time() + 3600);

        $result = JsCompiler::isFileOutdated(
            ['test1.js', 'test2.js'],
            $outputFile,
            self::TEST_JS_DIR . '/'
        );

        static::assertFalse($result, 'Should return true when all input files are older than output');
    }
}
