<?php
/** @noinspection PhpMethodNamingConventionInspection - test names may be longer than our standard. */

declare(strict_types=1);

namespace MidwestMemories\Test;

use MidwestMemories\Metadata;
use MidwestMemories\Path;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MidwestMemories\Metadata
 */
class MetadataTest extends TestCase
{
    private const TEST_DIR = __DIR__ . '/test_data';
    private const SUB_DIR = __DIR__ . '/test_data/sub_dir';
    private const SUB_DIR_INI = __DIR__ . '/test_data/sub_dir/index.txt';
    private const TEST_INI_PATH = __DIR__ . '/test_data/index.txt';
    private const TEST_CSV_PATH = __DIR__ . '/test_data/test_metadata.csv';
    private const READONLY_DIR = __DIR__ . '/test_data/readonly';
    private string $origImgBasePath;

    /**
     * This method is called before each test.
     * @codeCoverageIgnore
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize the Path class with a test directory
        Path::validateBaseDir();

        // Store and replace the original image base path.
        $this->origImgBasePath = Path::$imgBaseUnixPath;
        Path::$imgBaseUnixPath = self::TEST_DIR;

        // Create test directory if it doesn't exist.
        if (!file_exists(self::TEST_DIR)) {
            mkdir(self::TEST_DIR, 0777, true);
        }

        // Create a test CSV file.
        $csvContent = <<<CSV
"Filename YYYYMMDD - Origin - #","Origin","Number","Bundle","Slide Txt","ICE?","Directory"
"20230719 - Test - 1","Origin Setup()","1","Test Bundle","Test Notes","No","/"
CSV;
        file_put_contents(self::TEST_CSV_PATH, $csvContent);
    }

    /**
     * This method is called after each test.
     * @codeCoverageIgnore
     */
    protected function tearDown(): void
    {
        // Clean up test files.
        foreach (
            [
                self::TEST_INI_PATH,
                self::SUB_DIR_INI,
                self::READONLY_DIR,
                self::TEST_CSV_PATH,
                self::TEST_DIR, // Added as a file, as some tests create it as a file.
            ] as $file
        ) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        // Clean up test directories. Be sure to delete innermost directories first.
        foreach ([self::SUB_DIR, self::TEST_DIR] as $dir) {
            if (file_exists($dir) && is_dir($dir)) {
                rmdir($dir);
            }
        }

        // Restore the original image base path.
        Path::$imgBaseUnixPath = $this->origImgBasePath;

        parent::tearDown();
    }

    /**
     * Ensure writeIniFiles creates INI files from CSV metadata.
     */
    public function testWriteIniFiles_CreatesIniFiles(): void
    {
        // Parse the test CSV file.
        $csv = Metadata::parseCsvMetadata(self::TEST_CSV_PATH);
        static::assertNotFalse($csv, 'Failed to parse test CSV file');

        // Ensure writeIniFiles returns true on success.
        $result = Metadata::writeIniFiles($csv);
        static::assertTrue($result, 'writeIniFiles should return true on success');

        // Verify the INI file was created with the expected content.
        static::assertFileExists(self::TEST_INI_PATH, 'INI file was not created');

        $expectedContent = <<<INI
[20230719 - Test - 1.jpg]
date = "2023-07-19"
displayname = "20230719 - Test - 1"
slideorigin = "Origin Setup()"
slidenumber = "1"
slidesubsection = "Test Bundle"
writtennotes = "Test Notes"
filtered = "No"
INI;

        $actualContent = file_get_contents(self::TEST_INI_PATH);

        static::assertStringContainsString(trim($expectedContent), trim($actualContent));
    }

    /**
     * Ensure writeIniFiles merges with existing INI file.
     */
    public function testWriteIniFiles_MergesWithExistingIni(): void
    {
        // Add an existing entry.
        $existingContent = "[existing_file.jpg]\ndate = 2023-01-01\ndisplayname = Existing File\n";
        file_put_contents(self::TEST_INI_PATH, $existingContent);

        // Parse the test CSV file.
        $csv = Metadata::parseCsvMetadata(self::TEST_CSV_PATH);
        static::assertNotFalse($csv, 'Failed to parse test CSV file');

        // Ensure writeIniFiles returns true on success.
        $result = Metadata::writeIniFiles($csv);
        static::assertTrue($result, 'writeIniFiles should return true on success');

        // Verify both entries exist in the INI file.
        $actualContent = file_get_contents(self::TEST_INI_PATH);
        static::assertStringContainsString('[existing_file.jpg]', $actualContent);
        static::assertStringContainsString('date = 2023-01-01', $actualContent);
        static::assertStringContainsString('[20230719 - Test - 1.jpg]', $actualContent);
    }

    /**
     * Ensure writeIniFiles handles directory creation failure.
     */
    public function testWriteIniFiles_HandlesDirectoryCreationFailure(): void
    {
        // Set the CSV to write to the subdirectory.
        $csvContent = <<<CSV
"Filename YYYYMMDD - Origin - #","Origin","Number","Bundle","Slide Txt","ICE?","Directory",""
"TestFile","testWriteIniFiles_HandlesDirectoryCreationFailure","2","Test Bundle","Test Notes","Yes","/sub_dir",""
CSV;
        file_put_contents(self::TEST_CSV_PATH, $csvContent);

        // Create a file so that we can't create a subdirectory with the same name.
        touch(self::SUB_DIR);

        // Parse the test CSV file.
        $csv = Metadata::parseCsvMetadata(self::TEST_CSV_PATH);
        static::assertNotFalse($csv, 'Failed to parse test CSV file');

        // Ensure writeIniFiles returns false on failure.
        $result = Metadata::writeIniFiles($csv);
        static::assertFalse($result, 'writeIniFiles should return false on directory creation failure');
    }

    /**
     * Test parsing a valid CSV file with multiple entries.
     */
    public function testParseCsvMetadata_WithValidData(): void
    {
        $csvContent = <<<CSV
"Filename YYYYMMDD - Origin - #","Origin","Number","Bundle","Slide Txt","ICE?","Directory",""
"20230719 - Test - 1","WithValidData1","1","Test Bundle","Test Notes","No","/",""
"20230720 - Another - 2","WithValidData2","2","Another Bundle","More Notes","Yes","/sub_dir",""
CSV;
        file_put_contents(self::TEST_CSV_PATH, $csvContent);

        $result = Metadata::parseCsvMetadata(self::TEST_CSV_PATH);
        static::assertIsArray($result, 'Should return an array on success');
        static::assertCount(2, $result, 'Should parse all rows from CSV');

        // Verify first entry.
        static::assertArrayHasKey(self::TEST_DIR, $result, 'Should create entry for first directory');
        static::assertCount(1, $result[self::TEST_DIR], 'Should have one file in first directory');

        $firstFile = $result[self::TEST_DIR];
        static::assertArrayHasKey('20230719 - Test - 1.jpg', $firstFile, 'Should create entry for first file');
        $firstFileData = $firstFile['20230719 - Test - 1.jpg'];
        static::assertSame('2023-07-19', $firstFileData['date'], 'Should parse date from filename');
        static::assertSame('WithValidData1', $firstFileData['slideorigin'], 'Should parse origin');
        static::assertSame('1', $firstFileData['slidenumber'], 'Should parse slide number');
        static::assertSame('Test Bundle', $firstFileData['slidesubsection'], 'Should parse subsection/bundle');
        static::assertSame('Test Notes', $firstFileData['writtennotes'], 'Should parse notes');
        static::assertSame('No', $firstFileData['filtered'], 'Should parse ICE flag');

        // Verify second entry.
        static::assertArrayHasKey(self::SUB_DIR, $result, 'Should create entry for second directory');
    }

    /**
     * Test parsing a non-existent CSV file.
     */
    public function testParseCsvMetadata_WithNonExistentFile(): void
    {
        $result = Metadata::parseCsvMetadata(self::TEST_DIR . '/nonexistent.csv');
        static::assertFalse($result, 'Should return false for non-existent file');
    }

    /**
     * Test parsing an empty CSV file.
     */
    public function testParseCsvMetadata_WithEmptyFile(): void
    {
        file_put_contents(self::TEST_CSV_PATH, '');
        $result = Metadata::parseCsvMetadata(self::TEST_CSV_PATH);
        static::assertEmpty($result, 'Should return empty array for empty file');
    }

    /**
     * Test parsing a CSV file with only a header row.
     */
    public function testParseCsvMetadata_WithOnlyHeader(): void
    {
        $csvContent = "'Filename YYYYMMDD - Origin - #','Origin','Number','Bundle','Slide Txt','ICE?','Directory',,\n";
        file_put_contents(self::TEST_CSV_PATH, $csvContent);

        $result = Metadata::parseCsvMetadata(self::TEST_CSV_PATH);
        static::assertIsArray($result, 'Should return an array even with only header');
        static::assertEmpty($result, 'Should return empty array for CSV with only header');
    }

    /**
     * Test parsing a CSV file with malformed rows.
     */
    public function testParseCsvMetadata_WithMalformedRows(): void
    {
        $csvContent = <<<CSV
"Filename YYYYMMDD - Origin - #","Origin","Number","Bundle","Slide Txt","ICE?","Directory"
"20230719 - Test - 1","WithMalformedRows1","1","Test Bundle","Test Notes","No","/"
"invalid row","missing","fields"


"20230720 - Another - 2","WithMalformedRows2","2","Another Bundle","More Notes","Yes","/sub_dir"
CSV;
        file_put_contents(self::TEST_CSV_PATH, $csvContent);
        $result = Metadata::parseCsvMetadata(self::TEST_CSV_PATH);
        static::assertIsArray($result, 'Should return an array even with malformed rows');
        static::assertCount(2, $result, 'Should parse valid rows and skip invalid ones');
    }

    /**
     * Test handling of directory path normalization, to ensure path traversal is prevented.
     */
    public function testParseCsvMetadata_DirectoryPathNormalization(): void
    {
        $csvContent = <<<CSV
"Filename YYYYMMDD - Origin - #","Origin","Number","Bundle","Slide Txt","ICE?","Directory",""
"20230719 - Test - 1","DirectoryPathNormalization","1","Test Bundle","Test Notes","No","/test//dir/../dir",""
CSV;
        file_put_contents(self::TEST_CSV_PATH, $csvContent);

        $result = Metadata::parseCsvMetadata(self::TEST_CSV_PATH);
        static::assertIsArray($result);
        $expectedPath = self::TEST_DIR . '/test/dir/./dir';
        $keys = array_keys($result);
        static::assertContains($expectedPath, $keys, 'Should normalize directory path');
    }

    /**
     * Test getting file data when the file doesn't exist and loadIfNotFound is false.
     */
    public function testGetFileDataByWebPath_NonExistentFile(): void
    {
        // Test with loadIfNotFound = false
        $result = Metadata::getFileDataByWebPath('/non/existent/file.jpg', false);

        // Should return an empty array when not found.
        static::assertSame([], $result);
    }

    /**
     * Verify htmlEscape correctly escapes HTML special characters.
     */
    public function testHtmlEscape_WithSpecialCharacters(): void
    {
        $input = [
            'displayname' => 'Test & Image',
            'slideorigin' => 'Origin <testHtmlEscape_WithSpecialCharacters>',
            'writtennotes' => 'Notes with "quotes" & special chars',
            'slidenumber' => '1',
            'date' => ['dateString' => '2023-01-01']
        ];

        $result = Metadata::htmlEscape($input);

        static::assertSame('Test &amp; Image', $result['displayname']);
        static::assertSame('Origin &lt;testHtmlEscape_WithSpecialCharacters&gt;', $result['slideorigin']);
        static::assertSame('Notes with &quot;quotes&quot; &amp; special chars', $result['writtennotes']);
        static::assertSame('1', $result['slidenumber']);
        static::assertSame('2023-01-01', $result['date']);
    }
}
