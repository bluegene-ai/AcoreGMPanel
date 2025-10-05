<?php
/**
 * File: app/Core/Config.php
 * Purpose: Defines class Config for the app/Core module.
 * Classes:
 *   - Config
 * Functions:
 *   - init()
 *   - get()
 *   - set()
 *   - loadDirectory()
 *   - mergeConfig()
 */

declare(strict_types=1);

namespace Acme\Panel\Core;

class Config
{
    private static array $data = [];

    public static function init(string $configDir): void
    {
        $configDir = rtrim($configDir, '/');
        self::$data = [];

        $baseData = self::loadDirectory($configDir);
        self::$data = $baseData;

        $generatedDir = $configDir . '/generated';
        if (is_dir($generatedDir)) {
            $generatedData = self::loadDirectory($generatedDir);
            self::$data = self::mergeConfig(self::$data, $generatedData);
        }
    }

    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $current = self::$data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public static function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $reference =& self::$data;

        foreach ($segments as $segment) {
            if (!isset($reference[$segment]) || !is_array($reference[$segment])) {
                $reference[$segment] = [];
            }

            $reference =& $reference[$segment];
        }

        $reference = $value;
    }

    private static function loadDirectory(string $configDir): array
    {
        $configDir = rtrim($configDir, '/');
        if (!is_dir($configDir)) {
            return [];
        }

        $data = [];
        $core = ['app', 'database', 'auth', 'soap'];

        foreach ($core as $file) {
            $path = $configDir . "/{$file}.php";

            if (is_file($path)) {
                ob_start();
                try {
                    $ret = require $path;
                } finally {
                    ob_end_clean();
                }

                $data[$file] = is_array($ret) ? $ret : [];
                continue;
            }

            $data[$file] = [];
        }

        foreach ((glob($configDir . '/*.php') ?: []) as $phpFile) {
            $name = basename($phpFile, '.php');

            if (isset($data[$name])) {
                continue;
            }

            ob_start();
            try {
                $ret = require $phpFile;
            } catch (\Throwable $exception) {
                $ret = [];
            } finally {
                ob_end_clean();
            }

            $data[$name] = is_array($ret) ? $ret : [];
        }

        return $data;
    }

    private static function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}

