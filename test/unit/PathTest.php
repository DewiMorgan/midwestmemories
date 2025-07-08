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
            ['name' => 'Bundle 20', 'is_dir' => true],
            ['name' => 'Bundle 1', 'is_dir' => true],
            ['name' => 'Bundle 3', 'is_dir' => true],
            ['name' => 'file10.txt', 'is_dir' => false],
            ['name' => 'dir2', 'is_dir' => true],
            ['name' => 'file2.txt', 'is_dir' => false],
            ['name' => 'dir10', 'is_dir' => true],
            ['name' => 'file1.txt', 'is_dir' => false],
            ['name' => 'dir1', 'is_dir' => true],
        ];

        /** @noinspection DuplicatedCode */
        $expected = [
            ['name' => 'Bundle 1', 'is_dir' => true],
            ['name' => 'Bundle 3', 'is_dir' => true],
            ['name' => 'Bundle 20', 'is_dir' => true],
            ['name' => 'dir1', 'is_dir' => true],
            ['name' => 'dir2', 'is_dir' => true],
            ['name' => 'dir10', 'is_dir' => true],
            ['name' => 'file1.txt', 'is_dir' => false],
            ['name' => 'file2.txt', 'is_dir' => false],
            ['name' => 'file10.txt', 'is_dir' => false],
        ];

        usort($items, [Path::class, 'sortFolder']);

        self::assertSame($expected, $items);
    }
}
