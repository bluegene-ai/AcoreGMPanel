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

class Request
{
    public string $method;
    public string $uri;
    public array $get;
    public array $post;
    public array $server;

    public static function capture(): self
    {
        $request = new self();
        $request->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $request->uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $request->get = $_GET;
        $request->post = $_POST;
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
        $candidates = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $header) {
            if (empty($this->server[$header])) {
                continue;
            }

            $value = $this->server[$header];

            if ($header === 'HTTP_X_FORWARDED_FOR') {
                $parts = array_map('trim', explode(',', (string) $value));

                foreach ($parts as $part) {
                    if ($part !== '') {
                        return $part;
                    }
                }

                continue;
            }

            return is_string($value) ? $value : (string) $value;
        }

        return '0.0.0.0';
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

