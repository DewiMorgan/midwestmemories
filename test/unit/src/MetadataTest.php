<?php
/** @noinspection PhpMethodNamingConventionInspection - test names may be longer than our standard. */

declare(strict_types=1);

namespace MidwestMemories\Test;

use MidwestMemories\Metadata;
use MidwestMemories\Path;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \MidwestMemories\Metadata
 */
class MetadataTest extends TestCase
{
    private string $testDir = __DIR__ . '/test_data';
    private string $testFilePath;
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
        Path::$imgBaseUnixPath = $this->testDir;

        // Create test directory if it doesn't exist.
        if (!file_exists($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        // Create a test CSV file.
        $this->testFilePath = $this->testDir . '/test_metadata.csv';
        $csvContent = <<<CSV
"Filename YYYYMMDD - Origin - #","Origin","Number","Bundle","Slide Txt","ICE?","Directory"
"20230719 - Test - 1","Test Origin","1","Test Bundle","Test Notes","No","/test_dir"
CSV;
        file_put_contents($this->testFilePath, $csvContent);
    }

    /**
     * This method is called after each test.
     * @codeCoverageIgnore
     */
    protected function tearDown(): void
    {
        // Clean up test files.
        if (file_exists($this->testDir . '/test_dir/index.txt')) {
            unlink($this->testDir . '/test_dir/index.txt');
            rmdir($this->testDir . '/test_dir');
        }
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
        if (file_exists($this->testDir . '/test_merge/index.txt')) {
            unlink($this->testDir . '/test_merge/index.txt');
            rmdir($this->testDir . '/test_merge');
        }
        if (file_exists($this->testDir . '/readonly')) {
            rmdir($this->testDir . '/readonly');
        }
        if (file_exists($this->testDir) && is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            if ($files !== false && count($files) === 0) {
                rmdir($this->testDir);
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
        $csv = Metadata::parseCsvMetadata($this->testFilePath);
        static::assertNotFalse($csv, 'Failed to parse test CSV file');

        // Ensure writeIniFiles returns true on success.
        $result = Metadata::writeIniFiles($csv);
        static::assertTrue($result, 'writeIniFiles should return true on success');

        // Verify the INI file was created with the expected content.
        $iniPath = $this->testDir . '/test_dir/index.txt';
        static::assertFileExists($iniPath, 'INI file was not created');

        $expectedContent = <<<INI
[20230719 - Test - 1.jpg]
date = 2023-07-19
displayname = 20230719 - Test - 1
slideorigin = Test Origin
slidenumber = 1
slidesubsection = Test Bundle
writtennotes = Test Notes
filtered = No
INI;

        $actualContent = file_get_contents($iniPath);

        static::assertStringContainsString(trim($expectedContent), trim($actualContent));
    }

    /**
     * Ensure writeIniFiles merges with existing INI file.
     */
    public function testWriteIniFiles_MergesWithExistingIni(): void
    {
        // Create a directory, and an existing INI file.
        $testDir = $this->testDir . '/test_merge';
        mkdir($testDir, 0777, true);
        $existingIni = $testDir . '/index.txt';

        // Add an existing entry.
        $existingContent = "[existing_file.jpg]\ndate = 2023-01-01\ndisplayname = Existing File\n";
        file_put_contents($existingIni, $existingContent);

        // Parse the test CSV file.
        $csv = Metadata::parseCsvMetadata($this->testFilePath);
        static::assertNotFalse($csv, 'Failed to parse test CSV file');

        // Update the path in the CSV to point to our test merge directory.
        /** @noinspection PhpAutovivificationOnFalseValuesInspection - we already checked it isn't false. */
        $csv[$testDir] = $csv[$this->testDir . '/test_dir'];
        unset($csv[$this->testDir . '/test_dir']);

        // Ensure writeIniFiles returns true on success.
        $result = Metadata::writeIniFiles($csv);
        static::assertTrue($result, 'writeIniFiles should return true on success');

        // Verify both entries exist in the INI file.
        $actualContent = file_get_contents($existingIni);
        static::assertStringContainsString('[existing_file.jpg]', $actualContent);
        static::assertStringContainsString('date = 2023-01-01', $actualContent);
        static::assertStringContainsString('[20230719 - Test - 1.jpg]', $actualContent);

        // Clean up.
        unlink($existingIni);
        rmdir($testDir);
    }

    /**
     * Ensure writeIniFiles handles directory creation failure.
     */
    public function testWriteIniFiles_HandlesDirectoryCreationFailure(): void
    {
        // Create a file so that we can't create a directory with the same name.
        $readOnlyDir = $this->testDir . '/readonly';
        touch($readOnlyDir);

        // Parse the test CSV file.
        $csv = Metadata::parseCsvMetadata($this->testFilePath);
        static::assertNotFalse($csv, 'Failed to parse test CSV file');

        // Update the path in the CSV to point to our read-only directory.
        /** @noinspection PhpAutovivificationOnFalseValuesInspection - we already checked it isn't false. */
        $csv[$readOnlyDir . '/subdir'] = $csv[$this->testDir . '/test_dir'];

        // Ensure writeIniFiles returns false on failure.
        $result = Metadata::writeIniFiles($csv);
        static::assertFalse($result, 'writeIniFiles should return false on directory creation failure');

        // Clean up.
        unlink($readOnlyDir);
    }

    /**
     * Test parsing a valid CSV file with multiple entries.
     */
    public function testParseCsvMetadata_WithValidData(): void
    {
        $csvContent = <<<CSV
"Filename YYYYMMDD - Origin - #","Origin","Number","Bundle","Slide Txt","ICE?","Directory",""
"20230719 - Test - 1","Test Origin","1","Test Bundle","Test Notes","No","/test_dir",""
"20230720 - Another - 2","Another Origin","2","Another Bundle","More Notes","Yes","/another_dir",""
CSV;
        file_put_contents($this->testFilePath, $csvContent);

        $result = Metadata::parseCsvMetadata($this->testFilePath);
        static::assertIsArray($result, 'Should return an array on success');
        static::assertCount(2, $result, 'Should parse all rows from CSV');

        // Verify first entry.
        $firstDir = $this->testDir . '/test_dir';
        static::assertArrayHasKey($firstDir, $result, 'Should create entry for first directory');
        static::assertCount(1, $result[$firstDir], 'Should have one file in first directory');

        $firstFile = $result[$firstDir];
        static::assertArrayHasKey('20230719 - Test - 1.jpg', $firstFile, 'Should create entry for first file');
        $firstFileData = $firstFile['20230719 - Test - 1.jpg'];
        static::assertSame('2023-07-19', $firstFileData['date'], 'Should parse date from filename');
        static::assertSame('Test Origin', $firstFileData['slideorigin'], 'Should parse origin');
        static::assertSame('1', $firstFileData['slidenumber'], 'Should parse slide number');
        static::assertSame('Test Bundle', $firstFileData['slidesubsection'], 'Should parse subsection/bundle');
        static::assertSame('Test Notes', $firstFileData['writtennotes'], 'Should parse notes');
        static::assertSame('No', $firstFileData['filtered'], 'Should parse ICE flag');

        // Verify second entry.
        $secondDir = $this->testDir . '/another_dir';
        static::assertArrayHasKey($secondDir, $result, 'Should create entry for second directory');
    }

    /**
     * Test parsing a non-existent CSV file.
     */
    public function testParseCsvMetadata_WithNonExistentFile(): void
    {
        $result = Metadata::parseCsvMetadata($this->testDir . '/nonexistent.csv');
        static::assertFalse($result, 'Should return false for non-existent file');
    }

    /**
     * Test parsing an empty CSV file.
     */
    public function testParseCsvMetadata_WithEmptyFile(): void
    {
        file_put_contents($this->testFilePath, '');
        $result = Metadata::parseCsvMetadata($this->testFilePath);
        static::assertEmpty($result, 'Should return empty array for empty file');
    }

    /**
     * Test parsing a CSV file with only a header row.
     */
    public function testParseCsvMetadata_WithOnlyHeader(): void
    {
        $csvContent = "'Filename YYYYMMDD - Origin - #','Origin','Number','Bundle','Slide Txt','ICE?','Directory',,\n";
        file_put_contents($this->testFilePath, $csvContent);

        $result = Metadata::parseCsvMetadata($this->testFilePath);
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
"20230719 - Test - 1","Test Origin","1","Test Bundle","Test Notes","No","/test_dir"
"invalid row","missing","fields"


"20230720 - Another - 2","Another Origin","2","Another Bundle","More Notes","Yes","/another_dir"
CSV;
        file_put_contents($this->testFilePath, $csvContent);
        $result = Metadata::parseCsvMetadata($this->testFilePath);
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
"20230719 - Test - 1","Test Origin","1","Test Bundle","Test Notes","No","/test//dir/../dir",""
CSV;
        file_put_contents($this->testFilePath, $csvContent);

        $result = Metadata::parseCsvMetadata($this->testFilePath);
        static::assertIsArray($result);
        $expectedPath = $this->testDir . '/test/dir/./dir';
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
            'slideorigin' => 'Test <Origin>',
            'writtennotes' => 'Notes with "quotes" & special chars',
            'slidenumber' => '1',
            'date' => ['dateString' => '2023-01-01']
        ];

        $result = Metadata::htmlEscape($input);

        static::assertSame('Test &amp; Image', $result['displayname']);
        static::assertSame('Test &lt;Origin&gt;', $result['slideorigin']);
        static::assertSame('Notes with &quot;quotes&quot; &amp; special chars', $result['writtennotes']);
        static::assertSame('1', $result['slidenumber']);
        static::assertSame('2023-01-01', $result['date']);
    }
}
