<?php
/**
 * File: app/Core/Request.php
 * Purpose: Defines class Request for the app/Core module.
 * Classes:
 *   - Request
 * Functions:
 *   - capture()
 *   - input()
 *   - int()
 *   - float()
 *   - bool()
 *   - all()
 *   - ip()
 *   - expectsJsonPayload()
 */

declare(strict_types=1);

namespace Acme\Panel\Core;

use Acme\Panel\Support\ClientIp;

class Request
{
    public string $method;
    public string $uri;
    public array $get;
    public array $post;
    public array $headers;
    public array $server;

    public static function capture(): self
    {
        $request = new self();
        $request->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $request->uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $request->get = $_GET;
        $request->post = $_POST;
        $request->headers = self::captureHeaders($_SERVER);
        $request->server = $_SERVER;

        if ($request->expectsJsonPayload() && empty($_POST)) {
            $raw = file_get_contents('php://input');

            if ($raw !== '') {
                $json = json_decode($raw, true);

                if (is_array($json)) {
                    $request->post = $json;
                }
            }
        }

        return $request;
    }

    public function input(string $key, $default = null)
    {
        if ($this->method === 'POST') {
            return $this->post[$key] ?? $this->get[$key] ?? $default;
        }

        return $this->get[$key] ?? $this->post[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, null);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        if (is_float($value) || is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key, null);

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key, null);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return $default;
    }

    public function all(): array
    {
        return $this->method === 'POST'
            ? ($this->post + $this->get)
            : ($this->get + $this->post);
    }

    public function ip(): string
    {
        return ClientIp::resolve($this->server);
    }

    public function expectsJsonResponse(): bool
    {
        return self::expectsJsonResponseForServer($this->server, $this->uri);
    }

    public static function expectsJsonResponseForServer(array $server, ?string $uri = null): bool
    {
        $accept = strtolower((string) ($server['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json'))
            return true;

        $requestedWith = strtolower((string) ($server['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($requestedWith === 'xmlhttprequest')
            return true;

        $uri ??= strtok((string) ($server['REQUEST_URI'] ?? '/'), '?') ?: '/';

        return str_contains($uri, '/api/');
    }

    private static function captureHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
                continue;
            }

            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }

    private function expectsJsonPayload(): bool
    {
        if (!in_array($this->method, ['POST', 'PUT', 'PATCH'], true)) {
            return false;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        return stripos($contentType, 'application/json') !== false;
    }
}

