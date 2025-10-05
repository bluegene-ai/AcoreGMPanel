<?php
/**
 * File: bootstrap/autoload.php
 * Purpose: Provides functionality for the bootstrap module.
 */

declare(strict_types=1);

$rootPath = dirname(__DIR__);

$composerAutoload = $rootPath . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(
    static function (string $class) use ($rootPath): void {
        $prefix = 'Acme\\Panel\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = $rootPath . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($path)) {
            require $path;
        }
    }
);

