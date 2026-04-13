<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;

final class LoginRateLimiter
{
    public static function check(string $username, string $ip): array
    {
        if (!self::enabled())
            return ['allowed' => true, 'retry_after' => 0, 'remaining' => self::maxAttempts()];

        $state = self::load($username, $ip);
        $status = self::status($state, time());
        self::persist($username, $ip, $state);

        return $status;
    }

    public static function hit(string $username, string $ip): array
    {
        if (!self::enabled())
            return ['allowed' => true, 'retry_after' => 0, 'remaining' => self::maxAttempts()];

        $now = time();
        $state = self::load($username, $ip);
        $state['attempts'][] = $now;

        if (count($state['attempts']) >= self::maxAttempts())
            $state['blocked_until'] = max((int) ($state['blocked_until'] ?? 0), $now + self::lockoutSeconds());

        self::persist($username, $ip, $state);

        return self::status($state, $now);
    }

    public static function clear(string $username, string $ip): void
    {
        if (!self::enabled())
            return;

        $path = self::cacheFile($username, $ip);
        if (is_file($path))
            @unlink($path);
    }

    private static function load(string $username, string $ip): array
    {
        $path = self::cacheFile($username, $ip);
        $state = [
            'attempts' => [],
            'blocked_until' => 0,
        ];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded))
                $state = $decoded + $state;
        }

        $windowStart = time() - self::windowSeconds();
        $state['attempts'] = array_values(array_filter(
            array_map('intval', (array) ($state['attempts'] ?? [])),
            static fn (int $timestamp): bool => $timestamp >= $windowStart
        ));
        $state['blocked_until'] = (int) ($state['blocked_until'] ?? 0);

        return $state;
    }

    private static function persist(string $username, string $ip, array $state): void
    {
        $path = self::cacheFile($username, $ip);
        $dir = dirname($path);
        if (!is_dir($dir))
            @mkdir($dir, 0777, true);

        @file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function status(array $state, int $now): array
    {
        $blockedUntil = (int) ($state['blocked_until'] ?? 0);
        $retryAfter = max(0, $blockedUntil - $now);
        $remaining = max(0, self::maxAttempts() - count((array) ($state['attempts'] ?? [])));

        if ($retryAfter > 0)
            return ['allowed' => false, 'retry_after' => $retryAfter, 'remaining' => 0];

        return ['allowed' => true, 'retry_after' => 0, 'remaining' => $remaining];
    }

    private static function enabled(): bool
    {
        return (bool) Config::get('app.security.login_rate_limit.enabled', true);
    }

    private static function maxAttempts(): int
    {
        return max(1, (int) Config::get('app.security.login_rate_limit.max_attempts', 5));
    }

    private static function windowSeconds(): int
    {
        return max(60, (int) Config::get('app.security.login_rate_limit.window_seconds', 300));
    }

    private static function lockoutSeconds(): int
    {
        return max(60, (int) Config::get('app.security.login_rate_limit.lockout_seconds', 900));
    }

    private static function cacheFile(string $username, string $ip): string
    {
        $base = LogPath::rootDir() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'security';
        $identity = strtolower(trim($username)) . '|' . trim($ip);
        return $base . DIRECTORY_SEPARATOR . 'login-' . sha1($identity) . '.json';
    }
}