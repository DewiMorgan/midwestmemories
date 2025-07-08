<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use MidwestMemories\Path;

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
}
