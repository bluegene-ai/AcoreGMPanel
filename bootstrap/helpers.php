<?php
/**
 * File: bootstrap/helpers.php
 * Purpose: Provides functionality for the bootstrap module.
 * Functions:
 *   - url()
 *   - asset()
 *   - url_with_server()
 *   - flash_add()
 *   - flash_pull_all()
 *   - __()
 */

declare(strict_types=1);

use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Url;
use Acme\Panel\Support\ServerContext;

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return Url::to($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}

if (!function_exists('url_with_server')) {
    function url_with_server(string $path, ?int $serverId = null): string
    {
        $serverId = $serverId ?? ServerContext::currentId();

        if (strpos($path, 'server=') !== false) {
            return url($path);
        }

        $parts = parse_url($path) ?: [];
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['server'] = $serverId;

        $rebuilt = ($parts['path'] ?? '') . '?' . http_build_query($query);

        if (!empty($parts['fragment'])) {
            $rebuilt .= '#' . $parts['fragment'];
        }

        return url($rebuilt);
    }
}

if (!function_exists('flash_add')) {
    function flash_add(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['flashes'][$type][] = $message;
    }
}

if (!function_exists('flash_pull_all')) {
    function flash_pull_all(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $all = $_SESSION['flashes'] ?? [];
        unset($_SESSION['flashes']);

        return $all;
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = [], ?string $default = null): string
    {
        return Lang::get($key, $replace, $default);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime($value, string $format = 'Y-m-d H:i:s'): string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return '-';
        }

        if (is_numeric($value)) {
            $ts = (int)$value;
            if ($ts >= 1000000000) {
                return date($format, $ts);
            }
        }

        return (string)$value;
    }
}

if (!function_exists('format_money_gsc')) {
    function format_money_gsc($copper): string
    {
        $amount = is_numeric($copper) ? (int)$copper : 0;
        if ($amount < 0) {
            $amount = 0;
        }

        $gold = intdiv($amount, 10000);
        $silver = intdiv($amount % 10000, 100);
        $copperLeft = $amount % 100;
        return $gold . '金' . $silver . '银' . $copperLeft . '铜';
    }
}

