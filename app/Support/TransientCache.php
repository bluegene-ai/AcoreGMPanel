<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

final class TransientCache
{
    private static array $memory = [];

    public static function get(string $namespace, string $key)
    {
        $cacheKey = self::cacheKey($namespace, $key);
        if (array_key_exists($cacheKey, self::$memory)) {
            $entry = self::$memory[$cacheKey];
            if (($entry['expires_at'] ?? 0) >= time())
                return $entry['value'] ?? null;

            unset(self::$memory[$cacheKey]);
        }

        $path = self::filePath($namespace, $key);
        if (!is_file($path))
            return null;

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '')
            return null;

        $entry = @unserialize($raw);
        if (!is_array($entry) || !array_key_exists('expires_at', $entry))
            return null;

        if (($entry['expires_at'] ?? 0) < time()) {
            @unlink($path);
            return null;
        }

        self::$memory[$cacheKey] = $entry;

        return $entry['value'] ?? null;
    }

    public static function set(string $namespace, string $key, $value, int $ttlSeconds): void
    {
        $ttlSeconds = max(1, $ttlSeconds);
        $entry = [
            'expires_at' => time() + $ttlSeconds,
            'value' => $value,
        ];

        $cacheKey = self::cacheKey($namespace, $key);
        self::$memory[$cacheKey] = $entry;

        $dir = self::dirPath($namespace);
        if (!is_dir($dir))
            @mkdir($dir, 0775, true);

        $path = self::filePath($namespace, $key);
        @file_put_contents($path, serialize($entry), LOCK_EX);
    }

    public static function delete(string $namespace, string $key): void
    {
        $cacheKey = self::cacheKey($namespace, $key);
        unset(self::$memory[$cacheKey]);

        $path = self::filePath($namespace, $key);
        if (is_file($path))
            @unlink($path);
    }

    public static function clearNamespace(string $namespace): void
    {
        $prefix = $namespace . ':';
        foreach (array_keys(self::$memory) as $cacheKey) {
            if (str_starts_with($cacheKey, $prefix))
                unset(self::$memory[$cacheKey]);
        }

        $dir = self::dirPath($namespace);
        if (!is_dir($dir))
            return;

        $files = @scandir($dir);
        if (!is_array($files))
            return;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..')
                continue;

            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path))
                @unlink($path);
        }

        @rmdir($dir);
    }

    public static function remember(string $namespace, string $key, int $ttlSeconds, callable $resolver)
    {
        $cached = self::get($namespace, $key);
        if ($cached !== null)
            return $cached;

        $value = $resolver();
        if ($value !== null)
            self::set($namespace, $key, $value, $ttlSeconds);

        return $value;
    }

    private static function cacheKey(string $namespace, string $key): string
    {
        return $namespace . ':' . $key;
    }

    private static function dirPath(string $namespace): string
    {
        $safeNamespace = preg_replace('/[^A-Za-z0-9_.-]/', '_', $namespace) ?? 'default';

        return LogPath::rootDir() . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'panel'
            . DIRECTORY_SEPARATOR . $safeNamespace;
    }

    private static function filePath(string $namespace, string $key): string
    {
        $safeKey = preg_replace('/[^A-Za-z0-9_.-]/', '_', $key) ?? 'cache';

        return self::dirPath($namespace) . DIRECTORY_SEPARATOR . $safeKey . '.cache';
    }
}