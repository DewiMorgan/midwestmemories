<?php
/** @noinspection PhpMethodNamingConventionInspection */
/** @noinspection PhpEnforceDocCommentInspection */
declare(strict_types=1);

use MidwestMemories\JsCompiler;
use MidwestMemories\Path;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MidwestMemories\JsCompiler
 */
class JsCompilerTest extends TestCase
{
    private const TEST_JS_DIR = __DIR__ . '/../../test-js';
    private const OUTPUT_DIR = __DIR__ . '/../../test-output';
    private const OUTPUT_FILE = __DIR__ . '/../../test-output/output.js';
    private const INPUT_FILE_1 = __DIR__ . '/../../test-js/test1.js';
    private const INPUT_FILE_2 = __DIR__ . '/../../test-js/test2.js';
    private const NONEXISTENT_FILE = __DIR__ . '/../../nonexistent/file.js';

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
        file_put_contents(self::INPUT_FILE_1, "// Test file 1\nconst test1 = 1;");
        file_put_contents(self::INPUT_FILE_2, "// Test file 2\nconst test2 = 2;");
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
                glob(Path::join(self::TEST_JS_DIR, '*.js')) ?: [],
                glob(Path::join(self::OUTPUT_DIR, '/*.js')) ?: []
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
        $result = JsCompiler::compile(
            ['test1.js'],
            self::OUTPUT_FILE,
            self::TEST_JS_DIR // Override default JS directory for testing
        );

        static::assertTrue($result);
        static::assertFileExists(self::OUTPUT_FILE);
        static::assertStringContainsString('// Test file 1', file_get_contents(self::OUTPUT_FILE));
    }

    public function testCompileMultipleFiles(): void
    {
        $result = JsCompiler::compile(
            ['test1.js', 'test2.js'],
            self::OUTPUT_FILE,
            self::TEST_JS_DIR
        );

        static::assertTrue($result);
        static::assertFileExists(self::OUTPUT_FILE);
        $content = file_get_contents(self::OUTPUT_FILE);
        static::assertStringContainsString('// Test file 1', $content);
        static::assertStringContainsString('// Test file 2', $content);
        static::assertStringContainsString('/* Source: test1.js */', $content);
    }

    public function testCompileNonExistentFile(): void
    {
        $result = JsCompiler::compile(
            ['nonexistent.js'],
            self::OUTPUT_FILE,
            self::TEST_JS_DIR
        );
        static::assertFalse($result);
        static::assertFileDoesNotExist(self::OUTPUT_FILE);
    }

    public function testCompileToNonWritableDirectory(): void
    {
        $result = JsCompiler::compile(
            ['test1.js'],
            self::NONEXISTENT_FILE,
            self::TEST_JS_DIR
        );

        static::assertFalse($result);
    }

    public function testIsFileCompiledWhenOutputDoesNotExist(): void
    {
        $result = JsCompiler::isFileOutdated(
            ['test1.js'],
            self::NONEXISTENT_FILE,
            self::TEST_JS_DIR
        );

        static::assertTrue($result, 'Should return false when output file does not exist');
    }

    public function testIsFileCompiledWhenInputDoesNotExist(): void
    {
        file_put_contents(self::OUTPUT_FILE, 'test');

        $result = JsCompiler::isFileOutdated(
            ['nonexistent.js'],
            self::OUTPUT_FILE,
            self::TEST_JS_DIR
        );

        static::assertTrue($result, 'Should return false when any input file does not exist');
    }

    public function testIsFileCompiledWhenInputIsNewer(): void
    {
        // Create output file first.
        file_put_contents(self::OUTPUT_FILE, 'test');
        touch(self::OUTPUT_FILE, time() - 3600); // Set modification time to 1 hour ago.

        // Update input file to be newer than output.
        touch(self::INPUT_FILE_1);

        $result = JsCompiler::isFileOutdated(
            ['test1.js'],
            self::OUTPUT_FILE,
            self::TEST_JS_DIR
        );

        static::assertTrue($result, 'Should return false when any input file is newer than output');
    }

    public function testIsFileCompiledWhenUpToDate(): void
    {
        file_put_contents(self::OUTPUT_FILE, 'test');

        // Make sure output file is newer than input files.
        touch(self::OUTPUT_FILE, time() + 3600);

        $result = JsCompiler::isFileOutdated(
            ['test1.js', 'test2.js'],
            self::OUTPUT_FILE,
            self::TEST_JS_DIR
        );

        static::assertFalse($result, 'Should return true when all input files are older than output');
    }
}
