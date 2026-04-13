<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;

final class ClientIp
{
    public static function resolve(array $server): string
    {
        $remoteAddr = self::normalize((string) ($server['REMOTE_ADDR'] ?? ''));
        $trustedProxies = Config::get('app.security.trusted_proxies', []);

        if ($remoteAddr === '')
            return '0.0.0.0';

        if (!self::isTrustedProxy($remoteAddr, $trustedProxies))
            return $remoteAddr;

        foreach ([
            self::forwardedFor($server),
            self::xForwardedFor($server),
        ] as $candidate) {
            if ($candidate !== '')
                return $candidate;
        }

        return $remoteAddr;
    }

    private static function forwardedFor(array $server): string
    {
        $header = trim((string) ($server['HTTP_FORWARDED'] ?? ''));
        if ($header === '')
            return '';

        foreach (explode(',', $header) as $entry) {
            foreach (explode(';', $entry) as $part) {
                $part = trim($part);
                if (!str_starts_with(strtolower($part), 'for='))
                    continue;

                $candidate = self::normalize(substr($part, 4));
                if ($candidate !== '')
                    return $candidate;
            }
        }

        return '';
    }

    private static function xForwardedFor(array $server): string
    {
        $header = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($header === '')
            return '';

        foreach (explode(',', $header) as $part) {
            $candidate = self::normalize($part);
            if ($candidate !== '')
                return $candidate;
        }

        return '';
    }

    private static function normalize(string $value): string
    {
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($value === '' || strtolower($value) === 'unknown')
            return '';

        if (preg_match('/^for=(.+)$/i', $value, $matches))
            $value = trim($matches[1], " \t\n\r\0\x0B\"'");

        if (preg_match('/^\[(.+)\](?::\d+)?$/', $value, $matches))
            $value = $matches[1];
        elseif (preg_match('/^([0-9.]+):\d+$/', $value, $matches))
            $value = $matches[1];

        return filter_var($value, FILTER_VALIDATE_IP) ? $value : '';
    }

    private static function isTrustedProxy(string $ip, array $trustedProxies): bool
    {
        if ($trustedProxies === [])
            return false;

        foreach ($trustedProxies as $proxy) {
            if (!is_string($proxy) || $proxy === '')
                continue;

            if ($proxy === $ip || self::matchesCidr($ip, $proxy))
                return true;
        }

        return false;
    }

    private static function matchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/'))
            return false;

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary))
            return false;

        $maskBits = (int) $bits;
        $maxBits = strlen($ipBinary) * 8;
        if ($maskBits < 0 || $maskBits > $maxBits)
            return false;

        $bytes = intdiv($maskBits, 8);
        $remainingBits = $maskBits % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes))
            return false;

        if ($remainingBits === 0)
            return true;

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
    }
}