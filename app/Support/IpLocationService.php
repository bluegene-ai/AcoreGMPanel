<?php
/**
 * File: app/Support/IpLocationService.php
 * Purpose: Defines class IpLocationService for the app/Support module.
 * Classes:
 *   - IpLocationService
 * Functions:
 *   - __construct()
 *   - lookup()
 *   - cachePath()
 *   - readCache()
 *   - writeCache()
 *   - fetchFromProvider()
 *   - isPrivate()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;

class IpLocationService
{
    private string $cacheDir;
    private int $ttl;
    private string $provider;

    public function __construct(?string $cacheDir = null, ?int $ttlSeconds = null)
    {
    $this->cacheDir = $cacheDir ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'ip_geo';
        $this->ttl = $ttlSeconds ?? 86400;
        $this->provider = 'ip-api';
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

        $now = time();
        $path = $this->cachePath($ip);
        $cached = $this->readCache($path);
        if ($cached && ($cached['expires_at'] ?? 0) >= $now) {
            return ['success' => true, 'text' => $cached['text'] ?? Lang::get('app.support.ip_location.labels.unknown'), 'cached' => true, 'provider' => $cached['provider'] ?? $this->provider];
        }

        [$text, $raw, $error] = $this->fetchFromProvider($ip);
        if ($text === null) {
            if ($cached) {
                return [
                    'success' => true,
                    'text' => $cached['text'] ?? Lang::get('app.support.ip_location.labels.unknown'),
                    'cached' => true,
                    'provider' => $cached['provider'] ?? $this->provider,
                    'stale' => true,
                    'message' => $error ?? Lang::get('app.support.ip_location.errors.failed'),
                ];
            }
            return ['success' => false, 'message' => $error ?? Lang::get('app.support.ip_location.errors.failed')];
        }

        $payload = [
            'ip' => $ip,
            'text' => $text,
            'provider' => $this->provider,
            'fetched_at' => $now,
            'expires_at' => $now + $this->ttl,
            'raw' => $raw,
        ];
        $this->writeCache($path, $payload);

        return ['success' => true, 'text' => $text, 'cached' => false, 'provider' => $this->provider];
    }

    private function cachePath(string $ip): string
    {
        $hash = sha1($ip);
        return $this->cacheDir . DIRECTORY_SEPARATOR . substr($hash, 0, 2) . DIRECTORY_SEPARATOR . $hash . '.json';
    }

    private function readCache(string $path): ?array
    {
        if (!is_file($path)) return null;
        try {
            $raw = file_get_contents($path);
            if ($raw === false) return null;
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function writeCache(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return;
        @file_put_contents($path, $json, LOCK_EX);
    }

    private function fetchFromProvider(string $ip): array
    {
        $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?lang=zh-CN';
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true,
                'header' => [
                    'Accept: application/json',
                    'User-Agent: AcoreGMPanel/1.0 (+https://github.com/bluegene-ai/AcoreGMPanel)'
                ]
            ],
        ]);
        try {
            $body = @file_get_contents($url, false, $context);
            if ($body === false) {
                return [null, null, Lang::get('app.support.ip_location.errors.provider_unreachable')];
            }
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                return [null, $body, Lang::get('app.support.ip_location.errors.response_invalid')];
            }
            if (($decoded['status'] ?? '') !== 'success') {
                $msg = $decoded['message'] ?? ('status=' . ($decoded['status'] ?? 'unknown'));
                return [null, $decoded, Lang::get('app.support.ip_location.errors.failed_reason', ['message'=>$msg])];
            }
            $parts = [];
            $country = trim((string)($decoded['country'] ?? ''));
            $region = trim((string)($decoded['regionName'] ?? ''));
            $city = trim((string)($decoded['city'] ?? ''));
            $isp = trim((string)($decoded['isp'] ?? ''));
            if ($country !== '') $parts[] = $country;
            if ($region !== '' && (!count($parts) || $parts[count($parts)-1] !== $region)) $parts[] = $region;
            if ($city !== '' && (!count($parts) || $parts[count($parts)-1] !== $city)) $parts[] = $city;
            if (!count($parts) && $isp !== '') $parts[] = $isp;
            $text = count($parts) ? implode(' ', $parts) : Lang::get('app.support.ip_location.labels.unknown');
            return [$text, $decoded, null];
        } catch (\Throwable $e) {
            return [null, null, Lang::get('app.support.ip_location.errors.failed_reason', ['message'=>$e->getMessage()])];
        }
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

