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

use Acme\Panel\Support\PageLayout;
use Acme\Panel\Support\PageHeaderData;
use Acme\Panel\Support\PageMetadata;

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
        $viewData = $data + ['__viewName' => $view];
        $pageMeta = PageMetadata::resolve($view, $viewData);
        $viewData += [
            '__pageMeta' => $pageMeta,
            'title' => $pageMeta['title'],
        ];
        $viewData['__pageHeader'] = PageHeaderData::resolve($view, $viewData);

        $content = View::make($view, $viewData);
        $content = PageLayout::wrap($view, $content, $viewData);

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

        foreach ($this->withSecurityHeaders() as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->content;
    }

    private function withSecurityHeaders(): array
    {
        $headers = $this->headers;
        if (!(bool) Config::get('app.security.headers.enabled', true))
            return $headers;

        $defaults = [
            'X-Content-Type-Options' => Config::get('app.security.headers.x_content_type_options', 'nosniff'),
            'X-Frame-Options' => Config::get('app.security.headers.x_frame_options', 'SAMEORIGIN'),
            'Referrer-Policy' => Config::get('app.security.headers.referrer_policy', 'strict-origin-when-cross-origin'),
            'Permissions-Policy' => Config::get('app.security.headers.permissions_policy', ''),
        ];

        if ($this->isHtmlResponse()) {
            $defaults['Content-Security-Policy'] = Config::get('app.security.headers.content_security_policy', '');
        }

        foreach ($defaults as $name => $value) {
            if ($value === '' || isset($headers[$name]))
                continue;

            $headers[$name] = $value;
        }

        return $headers;
    }

    private function isHtmlResponse(): bool
    {
        $contentType = (string) ($this->headers['Content-Type'] ?? 'text/html');

        return str_contains(strtolower($contentType), 'text/html');
    }
}

