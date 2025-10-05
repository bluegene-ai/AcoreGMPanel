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

