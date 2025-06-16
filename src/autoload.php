<?php

declare(strict_types=1);

// Third party autoloader first.
require_once __DIR__ . '/../vendor/autoload.php';

// Then our own.
spl_autoload_register(static function (string $class): void {
    $file = str_replace('\\', '/', $class) . '.php';
    $file = preg_replace('/^\w+/', 'src', $file);
    if (file_exists($file)) {
        include $file;
    }
});
