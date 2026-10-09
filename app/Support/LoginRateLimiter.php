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

        // 读也走加锁路径：否则会读到别的请求正在改写的中间状态。
        return self::mutate($username, $ip, static fn (array &$state): array => self::status($state, time()));
    }

    public static function hit(string $username, string $ip): array
    {
        if (!self::enabled())
            return ['allowed' => true, 'retry_after' => 0, 'remaining' => self::maxAttempts()];

        return self::mutate($username, $ip, static function (array &$state): array {
            $now = time();
            $state['attempts'][] = $now;

            if (count($state['attempts']) >= self::maxAttempts())
                $state['blocked_until'] = max((int) ($state['blocked_until'] ?? 0), $now + self::lockoutSeconds());

            return self::status($state, $now);
        });
    }

    public static function clear(string $username, string $ip): void
    {
        if (!self::enabled())
            return;

        $path = self::cacheFile($username, $ip);
        if (is_file($path))
            @unlink($path);
    }

    /**
     * 在独占锁内完成「读 → 改 → 写」。
     *
     * 原先是 load() 与 persist() 各自独立，并发请求会各自读到旧计数再写回，
     * 5 次阈值可以被同时打进来的请求绕过。回调按引用接收状态。
     */
    private static function mutate(string $username, string $ip, callable $mutator): array
    {
        $path = self::cacheFile($username, $ip);
        $dir = dirname($path);
        if (!is_dir($dir))
            @mkdir($dir, 0777, true);

        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            // 连文件都开不了（权限等）：退化成无锁路径，至少不整体失效。
            $state = self::decode(is_file($path) ? (string) @file_get_contents($path) : '');
            $result = $mutator($state);
            @file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $result;
        }

        try {
            @flock($fp, LOCK_EX);
            $raw = (string) stream_get_contents($fp);
            $state = self::decode($raw);
            $result = $mutator($state);

            @ftruncate($fp, 0);
            @rewind($fp);
            @fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            @fflush($fp);

            return $result;
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    /** 解析已持久化的状态，并清掉滑出时间窗的尝试记录。 */
    private static function decode(string $raw): array
    {
        $state = [
            'attempts' => [],
            'blocked_until' => 0,
        ];

        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded))
                $state = $decoded + $state;
        }

        $windowStart = time() - self::windowSeconds();
        $state['attempts'] = array_values(array_filter(
            array_map('intval', (array) $state['attempts']),
            static fn (int $timestamp): bool => $timestamp >= $windowStart
        ));
        $state['blocked_until'] = (int) $state['blocked_until'];

        return $state;
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
