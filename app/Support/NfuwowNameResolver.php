<?php
/**
 * File: app/Support/NfuwowNameResolver.php
 * Purpose: Resolve game object names by ID via https://db.nfuwow.com/80/ with filesystem caching.
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;

final class NfuwowNameResolver
{
    private const BASE = 'https://db.nfuwow.com/80/?';

    /**
     * @param string $type Allowed: spell, skill, achievement, achievementcriteria, quest, faction, item
     * @param int[] $ids
     * @return array<int, string|null> Map of id => name (null when not found)
     */
    public static function resolveMany(string $type, array $ids, int $timeoutSeconds = 3): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['spell', 'skill', 'achievement', 'achievementcriteria', 'quest', 'faction', 'item'], true)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v) => $v > 0)));
        if (!$ids) {
            return [];
        }

        $locale = Lang::locale();
        $cache = self::loadCache($type, $locale);
        $out = [];

        foreach ($ids as $id) {
            if (array_key_exists((string)$id, $cache)) {
                $out[$id] = $cache[(string)$id];
                continue;
            }

            $name = self::fetchTitleName($type, $id, $locale, $timeoutSeconds);
            $cache[(string)$id] = $name;
            $out[$id] = $name;
        }

        self::persistCache($type, $locale, $cache);
        return $out;
    }

    private static function fetchTitleName(string $type, int $id, string $locale, int $timeoutSeconds): ?string
    {
        $acceptLanguage = self::acceptLanguageForLocale($locale);
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => "Accept-Language: {$acceptLanguage}\r\nUser-Agent: AGMP/1.0\r\n",
            ],
        ]);

        $html = @file_get_contents(self::BASE . rawurlencode($type) . '=' . $id, false, $ctx);
        if (!$html) {
            return null;
        }

        if (!preg_match('/<title>(.*?)<\/title>/i', $html, $m)) {
            return null;
        }

        $raw = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        $title = trim(preg_split('/\s+-\s+/', $raw)[0] ?? $raw);
        return $title !== '' ? $title : null;
    }

    private static function acceptLanguageForLocale(string $locale): string
    {
        $l = strtolower($locale);
        if (str_starts_with($l, 'zh')) {
            return 'zh-CN,zh;q=0.9,en;q=0.5';
        }
        return 'en-US,en;q=0.9,zh-CN;q=0.5';
    }

    private static function cacheFile(string $type, string $locale): string
    {
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        $safeLocale = preg_replace('/[^A-Za-z0-9_\-]/', '_', $locale) ?: 'en';
        return $base . DIRECTORY_SEPARATOR . 'nfuwow_' . $type . '_' . $safeLocale . '.json';
    }

    /** @return array<string, string|null> */
    private static function loadCache(string $type, string $locale): array
    {
        $file = self::cacheFile($type, $locale);
        if (!is_file($file)) {
            return [];
        }
        $json = @file_get_contents($file);
        $data = $json ? json_decode($json, true) : null;
        return is_array($data) ? $data : [];
    }

    /** @param array<string, string|null> $cache */
    private static function persistCache(string $type, string $locale, array $cache): void
    {
        if (!$cache) {
            return;
        }

        if (count($cache) > 6000) {
            $cache = array_slice($cache, -5000, null, true);
        }

        $file = self::cacheFile($type, $locale);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($file, json_encode($cache, JSON_UNESCAPED_UNICODE));
    }
}
