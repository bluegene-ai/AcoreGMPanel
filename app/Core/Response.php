<?php
/**
 * File: app/Core/Response.php
 * Purpose: Defines class Response for the app/Core module.
 * Classes:
 *   - Response
 * Functions:
 *   - __construct()
 *   - view()
 *   - json()
 *   - redirect()
 *   - send()
 */

declare(strict_types=1);

namespace Acme\Panel\Core;

class Response
{
    public function __construct(
        private string $content = '',
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public static function view(string $view, array $data = []): self
    {
        $content = View::make($view, $data);

        if (stripos($content, '<!DOCTYPE') === false) {
            $layoutData = $data + ['title' => $data['title'] ?? 'Panel'];
            $top = View::make('layouts.base_top', $layoutData);
            $bottom = View::make('layouts.base_bottom', $layoutData);
            $content = $top . $content . $bottom;
        }

        return new self($content, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        if (str_starts_with($location, '/') && !preg_match('#^https?://#i', $location)) {
            $base = rtrim(Config::get('app.base_path') ?? '', '/');

            if ($base !== '') {
                $location = $base . $location;
            }
        }

        return new self('', $status, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->content;
    }
}

