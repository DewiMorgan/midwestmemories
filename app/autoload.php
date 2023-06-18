<?php

declare(strict_types=1);

// Third party autoloader first.
require_once __DIR__ . '/../vendor/autoload.php';

// Then our own.
spl_autoload_extensions('.php'); // comma-separated list
spl_autoload_register();
/*
spl_autoload_register(function (string $class): void {
    file_put_contents('./autoload.out', "Looking for '$class'\n", FILE_APPEND);
    $file = str_replace('\\', '/', $class) . '.php';
    file_put_contents('./autoload.out', "File is '$file'\n", FILE_APPEND);
    if (file_exists($file)) {
        file_put_contents('./autoload.out', "File found and included!\n", FILE_APPEND);
        include $file;
    } else {
        file_put_contents('./autoload.out', "404: File not found!\n", FILE_APPEND);
    }
});
    */