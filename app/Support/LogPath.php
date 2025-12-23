<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

final class LogPath
{
    public static function rootDir(): string
    {
        // app/Support -> app -> project root
        return dirname(__DIR__, 2);
    }

    public static function logsDir(bool $ensure = true, int $mode = 0775): string
    {
        $dir = self::rootDir() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if ($ensure && !is_dir($dir)) {
            @mkdir($dir, $mode, true);
        }
        return $dir;
    }

    public static function logFile(string $filename, bool $ensureDir = true, int $mode = 0775): string
    {
        $filename = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filename);
        $filename = basename($filename);
        if ($filename === '') {
            $filename = 'app.log';
        }
        return self::logsDir($ensureDir, $mode) . DIRECTORY_SEPARATOR . $filename;
    }

    public static function appendLine(string $filename, string $line, bool $ensureNewline = true, int $mode = 0777): void
    {
        $path = self::logFile($filename, true, $mode);
        self::appendTo($path, $line, $ensureNewline, $mode);
    }

    public static function appendTo(string $path, string $line, bool $ensureNewline = true, int $mode = 0777): void
    {
        try {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, $mode, true);
            }

            if ($ensureNewline && $line !== '') {
                $last = substr($line, -1);
                if ($last !== "\n") {
                    $line .= "\n";
                }
            }

            @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // swallow
        }
    }
}
