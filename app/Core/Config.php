<?php
/**
 * File: app/Core/Config.php
 * Purpose: Defines class Config for the app/Core module.
 */

declare(strict_types=1);

namespace Acme\Panel\Core;

class Config
{
    /** 这几个 section 即使没有对应文件也保持空数组（键的形状稳定） */
    private const CORE_SECTIONS = ['app', 'database', 'auth', 'soap'];

    private static array $data = [];

    /** @var array<string, true> 已从磁盘求值过的 section */
    private static array $loaded = [];

    private static string $configDir = '';

    public static function init(string $configDir): void
    {
        self::$configDir = rtrim(str_replace('\\', '/', $configDir), '/');
        self::$data = [];
        self::$loaded = [];

        foreach (self::CORE_SECTIONS as $section) {
            self::$data[$section] = [];
        }
    }

    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);
        self::loadSection((string) $segments[0]);

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
        // 先让真实配置就位，再覆盖，避免稍后加载 section 时把这次赋值冲掉
        self::loadSection((string) $segments[0]);

        $reference =& self::$data;

        foreach ($segments as $segment) {
            if (!isset($reference[$segment]) || !is_array($reference[$segment])) {
                $reference[$segment] = [];
            }

            $reference =& $reference[$segment];
        }

        $reference = $value;
    }

    /**
     * 按需加载一个 section：config/<name>.php 打底，config/generated/<name>.php 覆盖。
     *
     * 目录扫描会连带求值 25 个配置文件（约 250 KB），其中多数与当前请求无关；
     * 这里只对实际被读到的 section 做两次文件存在性判断。
     */
    private static function loadSection(string $section): void
    {
        if ($section === '' || isset(self::$loaded[$section])) {
            return;
        }
        self::$loaded[$section] = true;

        $strict = in_array($section, self::CORE_SECTIONS, true);
        $base = self::requireSection(self::$configDir . '/' . $section . '.php', $strict);
        $generated = self::requireSection(self::$configDir . '/generated/' . $section . '.php', $strict);

        if ($base === null && $generated === null) {
            if (array_key_exists($section, self::$data)) {
                self::$data[$section] = [];
            }

            return;
        }

        self::$data[$section] = self::mergeConfig($base ?? [], $generated ?? []);
    }

    /**
     * 求值一个 section 文件；文件不存在返回 null（区别于「存在但返回非数组」的 []）。
     * @return array<mixed>|null
     */
    private static function requireSection(string $path, bool $strict): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        ob_start();
        try {
            $ret = require $path;
        } catch (\Throwable $exception) {
            ob_end_clean();
            if ($strict) {
                throw $exception;
            }

            return [];
        }
        ob_end_clean();

        return is_array($ret) ? $ret : [];
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
