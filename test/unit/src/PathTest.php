<?php
declare(strict_types=1);

namespace MidwestMemories;

use PHPUnit\Framework\TestCase;

/**
 * Test natural sorting of filenames.
 */
class PathTest extends TestCase
{
    /**
     * Test mixed directories and files with numeric names.
     */
    public function testMixedItems(): void
    {
        /** @noinspection DuplicatedCode */
        $items = [
            ['name' => 'Bundle 20', 'isDir' => true],
            ['name' => 'Bundle 1', 'isDir' => true],
            ['name' => 'Bundle 3', 'isDir' => true],
            ['name' => 'file10.txt', 'isDir' => false],
            ['name' => 'dir2', 'isDir' => true],
            ['name' => 'file2.txt', 'isDir' => false],
            ['name' => 'dir10', 'isDir' => true],
            ['name' => 'file1.txt', 'isDir' => false],
            ['name' => 'dir1', 'isDir' => true],
        ];

        /** @noinspection DuplicatedCode */
        $expected = [
            ['name' => 'Bundle 1', 'isDir' => true],
            ['name' => 'Bundle 3', 'isDir' => true],
            ['name' => 'Bundle 20', 'isDir' => true],
            ['name' => 'dir1', 'isDir' => true],
            ['name' => 'dir2', 'isDir' => true],
            ['name' => 'dir10', 'isDir' => true],
            ['name' => 'file1.txt', 'isDir' => false],
            ['name' => 'file2.txt', 'isDir' => false],
            ['name' => 'file10.txt', 'isDir' => false],
        ];

        usort($items, [Path::class, 'sortFolder']);

        self::assertSame($expected, $items);
    }

    /**
     * Test joining path elements with proper slash handling.
     */
    public function testJoin(): void
    {
        // Test basic path joining.
        self::assertSame('path/to/folder', Path::join('path', 'to', 'folder'));

        // Test with leading slash on first element.
        self::assertSame('/path/to/folder', Path::join('/path', 'to', 'folder'));

        // Test with multiple slashes between elements.
        self::assertSame('path/to/folder', Path::join('path/', '/to/', '/folder'));

        // Test with all slashes.
        self::assertSame('/path/to/folder', Path::join('/path/', '/to/', '/folder/'));

        // Test with empty elements.
        self::assertSame('path/to/folder', Path::join('path', '', 'to', '', 'folder'));

        // Test with first element empty, second element starting with a slash.
        self::assertSame('lose/leading/slash', Path::join('', '/lose', 'leading', 'slash/', ''));

        // Test with single element.
        self::assertSame('path', Path::join('path'));

        // Test with single element with slashes.
        self::assertSame('/path', Path::join('/path/'));

        // Test with all empty strings.
        self::assertSame('', Path::join('', '', ''));
    }
}
