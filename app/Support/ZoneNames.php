<?php
/**
 * File: app/Support/ZoneNames.php
 * Purpose: Loads zone (area) names from resources/lang/<locale>/zone_names_*.php.
 *
 * Notes:
 * - The zone name files define a `$zones` array (zoneId => name).
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;

class ZoneNames
{
    /** @var array<string, array<int, string>> */
    private static array $cache = [];

    /**
     * @return array<int, string>
     */
    public static function all(?string $locale = null): array
    {
        $locale = $locale ?: Lang::locale();
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $root = dirname(__DIR__, 2);
        $file = self::resolveFile($locale);
        $dir = is_dir($root . '/resources/lang/' . $locale) ? $locale : 'en';
        $path = $root . '/resources/lang/' . $dir . '/' . $file;

        $zones = [];
        if (is_file($path)) {
            /** @noinspection PhpIncludeInspection */
            include $path;
        }

        if (!is_array($zones)) {
            $zones = [];
        }

        self::$cache[$locale] = $zones;
        return $zones;
    }

    public static function name(int $zoneId, ?string $locale = null): ?string
    {
        if ($zoneId <= 0) {
            return null;
        }
        $zones = self::all($locale);
        return $zones[$zoneId] ?? null;
    }

    public static function label(int $zoneId, ?string $locale = null): string
    {
        $name = self::name($zoneId, $locale);
        if ($name !== null && $name !== '') {
            return $name;
        }
        return '#' . $zoneId;
    }

    private static function resolveFile(string $locale): string
    {
        $normalized = strtolower(str_replace('-', '_', $locale));
        if (str_starts_with($normalized, 'zh')) {
            return 'zone_names_chinese.php';
        }
        return 'zone_names_english.php';
    }
}
