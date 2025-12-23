<?php
/**
 * File: app/Support/IpLocationService.php
 * Purpose: Defines class IpLocationService for the app/Support module.
 * Classes:
 *   - IpLocationService
 * Functions:
 *   - __construct()
 *   - lookup()
 *   - lookupMmdb()
 *   - lookupMmdbViaExtension()
 *   - pickName()
 *   - isPrivate()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;

class IpLocationService
{
    private string $driver;
    private string $mmdbPath;
    private string $locale;
    private $reader = null;
    private $mmdbHandle = null;

    public function __construct(?string $mmdbPath = null, ?string $locale = null)
    {
        $this->driver = (string)Config::get('ip_location.driver', 'mmdb');
        $this->mmdbPath = $mmdbPath ?? (string)Config::get(
            'ip_location.mmdb_path',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'ip_geo' . DIRECTORY_SEPARATOR . 'GeoLite2-City.mmdb'
        );
        $this->locale = $locale ?? (string)Config::get('ip_location.locale', 'zh-CN');
    }

    public function lookup(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '') {
            return ['success' => false, 'message' => Lang::get('app.support.ip_location.errors.empty')];
        }
        if ($this->isPrivate($ip)) {
            return ['success' => true, 'text' => Lang::get('app.support.ip_location.labels.private'), 'cached' => true, 'provider' => 'private'];
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => Lang::get('app.support.ip_location.errors.invalid')];
        }

        $result = $this->lookupMmdb($ip);
        if ($result !== null) {
            return $result;
        }

        // Non-fatal fallback: keep UI usable even if mmdb is not configured.
        return [
            'success' => true,
            'text' => Lang::get('app.support.ip_location.labels.unknown'),
            'cached' => true,
            'provider' => 'mmdb',
            'message' => Lang::get('app.support.ip_location.errors.mmdb_unavailable'),
        ];
    }

    private function lookupMmdb(string $ip): ?array
    {
        if ($this->driver !== 'mmdb') {
            return null;
        }

        // Prefer the PHP extension if available (no Composer/vendor needed).
        if (function_exists('maxminddb_open') && function_exists('maxminddb_get')) {
            return $this->lookupMmdbViaExtension($ip);
        }

        if (!class_exists('MaxMind\\Db\\Reader')) {
            return [
                'success' => true,
                'text' => Lang::get('app.support.ip_location.labels.unknown'),
                'cached' => true,
                'provider' => 'mmdb',
                'message' => Lang::get('app.support.ip_location.errors.mmdb_reader_missing'),
            ];
        }
        if (!is_file($this->mmdbPath)) {
            return [
                'success' => true,
                'text' => Lang::get('app.support.ip_location.labels.unknown'),
                'cached' => true,
                'provider' => 'mmdb',
                'message' => Lang::get('app.support.ip_location.errors.mmdb_file_missing'),
            ];
        }

        try {
            if ($this->reader === null) {
                $cls = 'MaxMind\\Db\\Reader';
                $this->reader = new $cls($this->mmdbPath);
            }
            $record = $this->reader->get($ip);
            if (!is_array($record)) {
                return [
                    'success' => true,
                    'text' => Lang::get('app.support.ip_location.labels.unknown'),
                    'cached' => false,
                    'provider' => 'mmdb',
                ];
            }

            $text = $this->formatLocationFromRecord($record);
            return [
                'success' => true,
                'text' => $text,
                'cached' => false,
                'provider' => 'mmdb',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => true,
                'text' => Lang::get('app.support.ip_location.labels.unknown'),
                'cached' => true,
                'provider' => 'mmdb',
                'message' => Lang::get('app.support.ip_location.errors.failed_reason', ['message' => $e->getMessage()]),
            ];
        }
    }

    private function lookupMmdbViaExtension(string $ip): array
    {
        if (!is_file($this->mmdbPath)) {
            return [
                'success' => true,
                'text' => Lang::get('app.support.ip_location.labels.unknown'),
                'cached' => true,
                'provider' => 'mmdb',
                'message' => Lang::get('app.support.ip_location.errors.mmdb_file_missing'),
            ];
        }
        try {
            if ($this->mmdbHandle === null) {
                $this->mmdbHandle = @maxminddb_open($this->mmdbPath);
            }
            if (!$this->mmdbHandle) {
                return [
                    'success' => true,
                    'text' => Lang::get('app.support.ip_location.labels.unknown'),
                    'cached' => true,
                    'provider' => 'mmdb',
                    'message' => Lang::get('app.support.ip_location.errors.mmdb_open_failed'),
                ];
            }
            $record = @maxminddb_get($this->mmdbHandle, $ip);
            if (!is_array($record)) {
                return [
                    'success' => true,
                    'text' => Lang::get('app.support.ip_location.labels.unknown'),
                    'cached' => false,
                    'provider' => 'mmdb',
                ];
            }

            $text = $this->formatLocationFromRecord($record);
            return [
                'success' => true,
                'text' => $text,
                'cached' => false,
                'provider' => 'mmdb',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => true,
                'text' => Lang::get('app.support.ip_location.labels.unknown'),
                'cached' => true,
                'provider' => 'mmdb',
                'message' => Lang::get('app.support.ip_location.errors.failed_reason', ['message' => $e->getMessage()]),
            ];
        }
    }

    private function pickName($names, string $preferredLocale): ?string
    {
        if (!is_array($names) || !$names) return null;
        if ($preferredLocale !== '' && isset($names[$preferredLocale]) && is_string($names[$preferredLocale])) {
            return $names[$preferredLocale];
        }
        if (isset($names['zh-CN']) && is_string($names['zh-CN'])) return $names['zh-CN'];
        if (isset($names['en']) && is_string($names['en'])) return $names['en'];
        foreach ($names as $v) {
            if (is_string($v) && trim($v) !== '') return $v;
        }
        return null;
    }

    private function formatLocationFromRecord(array $record): string
    {
        // Format 1: Standard MaxMind GeoIP2 schema (GeoLite2-City).
        $loc = $this->locale !== '' ? $this->locale : 'zh-CN';
        $hasGeoip2 = (isset($record['country']) && is_array($record['country']))
            || (isset($record['city']) && is_array($record['city']))
            || (isset($record['subdivisions']) && is_array($record['subdivisions']));
        if ($hasGeoip2) {
            $country = $this->pickName($record['country']['names'] ?? null, $loc);
            $region = null;
            if (!empty($record['subdivisions']) && is_array($record['subdivisions'])) {
                $first = $record['subdivisions'][0] ?? null;
                if (is_array($first)) {
                    $region = $this->pickName($first['names'] ?? null, $loc);
                }
            }
            $city = $this->pickName($record['city']['names'] ?? null, $loc);
            $parts = [];
            foreach ([$country, $region, $city] as $p) {
                $p = trim((string)$p);
                if ($p === '') continue;
                if (!count($parts) || $parts[count($parts) - 1] !== $p) {
                    $parts[] = $p;
                }
            }
            return count($parts) ? implode(' ', $parts) : Lang::get('app.support.ip_location.labels.unknown');
        }

        // Format 2: Common CN-only IP DB schema (province/city/districts/isp/net).
        $parts = [];
        foreach (['province', 'city', 'districts'] as $k) {
            $v = trim((string)($record[$k] ?? ''));
            if ($v === '') continue;
            if (!count($parts) || $parts[count($parts) - 1] !== $v) {
                $parts[] = $v;
            }
        }
        if (!count($parts)) {
            $isp = trim((string)($record['isp'] ?? ''));
            if ($isp !== '') $parts[] = $isp;
        }
        if (!count($parts)) {
            $net = trim((string)($record['net'] ?? ''));
            if ($net !== '') $parts[] = $net;
        }

        return count($parts) ? implode(' ', $parts) : Lang::get('app.support.ip_location.labels.unknown');
    }

    private function isPrivate(string $ip): bool
    {
        $lower = strtolower($ip);
        if (str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.') || str_starts_with($ip, '127.')) return true;
        if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) return true;
        if ($lower === '::1' || str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd')) return true;
        return false;
    }
}

