<?php
/**
 * File: app/Core/Lang.php
 * Purpose: Defines class Lang for the app/Core module.
 * Classes:
 *   - Lang
 * Functions:
 *   - init()
 *   - setLocale()
 *   - locale()
 *   - fallbackLocale()
 *   - available()
 *   - get()
 *   - getFromLocale()
 *   - loadFile()
 *   - splitKey()
 *   - normalizeLocale()
 */

declare(strict_types=1);

namespace Acme\Panel\Core;

final class Lang
{
    private static string $locale = 'en';
    private static string $fallbackLocale = 'en';

    private static array $cache = [];


    private static array $available = [];

    public static function init(): void
    {
        $configLocale = (string) Config::get('app.locale', 'en');
        $fallback = (string) Config::get('app.fallback_locale', 'en');
        $available = Config::get('app.available_locales', []);
        if (!is_array($available)) {
            $available = [];
        }
        $available = array_merge($available, [$configLocale, $fallback]);
        self::$available = array_values(array_unique(array_filter(array_map('strval', $available))));
        self::$fallbackLocale = self::normalizeLocale($fallback) ?? 'en';
        self::setLocale($configLocale);
    }

    public static function setLocale(string $locale): void
    {
        $normalized = self::normalizeLocale($locale);
        if ($normalized === null) {
            $normalized = self::normalizeLocale(self::$fallbackLocale) ?? 'en';
        }
        self::$locale = $normalized;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function fallbackLocale(): string
    {
        return self::$fallbackLocale;
    }

    public static function available(): array
    {
        return self::$available;
    }

    public static function get(string $key, array $replace = [], ?string $default = null): string
    {
        [$file, $pathSegments] = self::splitKey($key);
        $value = self::getFromLocale(self::$locale, $file, $pathSegments);
        if ($value === null && self::$fallbackLocale !== self::$locale) {
            $value = self::getFromLocale(self::$fallbackLocale, $file, $pathSegments);
        }
        if ($value === null) {
            $value = $default ?? $key;
        }
        if ($replace) {
            foreach ($replace as $search => $rep) {
                $value = str_replace(':' . $search, (string) $rep, $value);
            }
        }
        return $value;
    }

    public static function getArray(string $key, ?array $default = null): array
    {
        [$file, $pathSegments] = self::splitKey($key);
        $value = self::getFromLocaleArray(self::$locale, $file, $pathSegments);
        if ($value === null && self::$fallbackLocale !== self::$locale) {
            $value = self::getFromLocaleArray(self::$fallbackLocale, $file, $pathSegments);
        }
        if ($value === null) {
            return $default ?? [];
        }
        return $value;
    }

    private static function getFromLocale(string $locale, string $file, array $segments): ?string
    {
        $bundle = self::loadFile($locale, $file);
        if ($bundle === null) {
            return null;
        }
        $value = $bundle;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        return null;
    }

    private static function getFromLocaleArray(string $locale, string $file, array $segments): ?array
    {
        $bundle = self::loadFile($locale, $file);
        if ($bundle === null) {
            return null;
        }
        $value = $bundle;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }
            return null;
        }
        return is_array($value) ? $value : null;
    }

    private static function loadFile(string $locale, string $file): ?array
    {
        $cacheKey = $locale . ':' . $file;
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }
        $path = dirname(__DIR__, 2) . '/resources/lang/' . $locale . '/' . $file . '.php';
        if (!is_file($path)) {
            self::$cache[$cacheKey] = null;
            return null;
        }
        $data = require $path;
        if (is_array($data) && $file === 'app') {
            $data = self::normalizeAppSections($locale, $data);
        }
        self::$cache[$cacheKey] = is_array($data) ? $data : null;
        return self::$cache[$cacheKey];
    }

    private static function normalizeAppSections(string $locale, array $data): array
    {
        if (!isset($data['smartai']) || !is_array($data['smartai'])) {
            return $data;
        }

        $migratable = ['realm', 'setup', 'mass_mail', 'mail', 'item_owner', 'logs', 'quest', 'audit'];
        foreach ($migratable as $section) {
            if (!isset($data[$section]) && isset($data['smartai'][$section]) && is_array($data['smartai'][$section])) {
                $data[$section] = $data['smartai'][$section];
                unset($data['smartai'][$section]);
            }
        }

        return $data;
    }

    private static function splitKey(string $key): array
    {
        $parts = explode('.', $key);
        if (count($parts) <= 1) {
            return [$parts[0] ?? 'app', []];
        }
        $file = array_shift($parts);
        return [$file, $parts];
    }

    private static function normalizeLocale(string $locale): ?string
    {
        $locale = trim($locale);
        if ($locale === '') {
            return null;
        }
        foreach (self::$available as $available) {
            if (strcasecmp($available, $locale) === 0) {
                return $available;
            }
        }
        if (in_array($locale, self::$available, true)) {
            return $locale;
        }
        return null;
    }
}

