<?php
/**
 * File: app/Support/Auth.php
 * Purpose: Defines class Auth for the app/Support module.
 * Classes:
 *   - Auth
 * Functions:
 *   - check()
 *   - attempt()
 *   - logout()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;

class Auth
{
    public static function check(): bool {
        return !empty($_SESSION['panel_logged_in']);
    }

    public static function capabilities(): array
    {
        if (!self::check())
            return [];

        $caps = $_SESSION['panel_capabilities'] ?? Config::get('auth.admin.capabilities', ['*']);
        if (!is_array($caps) || $caps === [])
            return ['*'];

        $normalized = [];
        foreach ($caps as $capability) {
            if (!is_string($capability))
                continue;

            $capability = trim($capability);
            if ($capability === '')
                continue;

            $normalized[] = $capability;
        }

        return $normalized ?: ['*'];
    }

    public static function can(string $capability): bool
    {
        if (!self::check())
            return false;

        foreach (self::capabilities() as $granted) {
            if ($granted === '*' || $granted === $capability)
                return true;

            if (str_ends_with($granted, '.*') && str_starts_with($capability, substr($granted, 0, -1)))
                return true;
        }

        return false;
    }

    public static function user(): ?string
    {
        $user = $_SESSION['panel_user'] ?? null;

        return is_string($user) && $user !== '' ? $user : null;
    }

    public static function attempt(string $u, string $p, ?string $ip = null): array
    {
        $cfg = Config::get('auth.admin');
        $ip ??= ClientIp::resolve($_SERVER);
        $username = trim($u);

        $rateLimit = LoginRateLimiter::check($username, $ip);
        if (!$rateLimit['allowed']) {
            return ['success' => false, 'reason' => 'throttled', 'retry_after' => $rateLimit['retry_after']];
        }

        if(!$cfg) return ['success' => false, 'reason' => 'config'];
        if($username === ($cfg['username']??'') && password_verify($p, $cfg['password_hash']??'')){
            LoginRateLimiter::clear($username, $ip);
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_regenerate_id(true);
            }
            $_SESSION['panel_logged_in']=true;
            $_SESSION['panel_user']=$username;
            $_SESSION['panel_capabilities'] = self::capabilitiesFromConfig($cfg);
            return ['success' => true];
        }

        $rateLimit = LoginRateLimiter::hit($username, $ip);

        return [
            'success' => false,
            'reason' => $rateLimit['retry_after'] > 0 ? 'throttled' : 'invalid',
            'retry_after' => $rateLimit['retry_after'],
        ];
    }

    public static function logout(): void
    {
        $_SESSION=[];
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    private static function capabilitiesFromConfig(array $cfg): array
    {
        $caps = $cfg['capabilities'] ?? ['*'];
        if (!is_array($caps) || $caps === [])
            return ['*'];

        $normalized = [];
        foreach ($caps as $capability) {
            if (!is_string($capability))
                continue;

            $capability = trim($capability);
            if ($capability === '')
                continue;

            $normalized[] = $capability;
        }

        return $normalized ?: ['*'];
    }
}

