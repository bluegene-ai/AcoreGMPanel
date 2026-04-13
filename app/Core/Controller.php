<?php
/**
 * File: app/Core/Controller.php
 * Purpose: Defines class Controller for the app/Core module.
 * Classes:
 *   - Controller
 * Functions:
 *   - view()
 *   - json()
 *   - getPost()
 *   - getQuery()
 *   - requireLogin()
 *   - redirect()
 *   - response()
 */

namespace Acme\Panel\Core;

use Acme\Panel\Support\Auth;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\ServerList;

abstract class Controller
{
    protected function view(string $view, array $data=[]): Response
    { return Response::view($view,$data); }

    protected function pageView(string $view, array $data = [], array $page = []): Response
    {
        if (isset($page['capabilities']) && is_array($page['capabilities'])) {
            $data['__pageCapabilities'] = $this->pageCapabilities($page['capabilities']);
        }

        if (isset($page['header']) && is_array($page['header'])) {
            $existingHeader = is_array($data['page_header'] ?? null)
                ? $data['page_header']
                : [];
            $data['page_header'] = array_replace($page['header'], $existingHeader);
        }

        if (isset($page['meta']) && is_array($page['meta'])) {
            $existingMeta = is_array($data['meta'] ?? null)
                ? $data['meta']
                : [];
            $data['meta'] = array_replace($page['meta'], $existingMeta);
        }

        if (isset($page['module']) && is_string($page['module']) && $page['module'] !== '') {
            $data['module'] = $page['module'];
        }

        if (array_key_exists('layout', $page)) {
            $data['__layout'] = $page['layout'];
        }

        return Response::view($view, $data);
    }

    protected function pageCapabilities(array $capabilities): array
    {
        $resolved = [];
        foreach ($capabilities as $key => $capability) {
            if (!is_string($key) || $key === '')
                continue;

            if (is_bool($capability)) {
                $resolved[$key] = $capability;
                continue;
            }

            if (!is_string($capability) || $capability === '')
                continue;

            $resolved[$key] = Auth::can($capability);
        }

        return $resolved;
    }

    protected function serverViewData(array $data = []): array
    {
        return array_replace([
            'current_server' => ServerContext::currentId(),
            'servers' => ServerList::options(),
        ], $data);
    }

    protected function listViewData(mixed $pager, array $filters = [], array $extra = [], bool $withServerContext = true): array
    {
        $data = [
            'pager' => $pager,
            'filters' => $filters,
        ];

        if ($withServerContext) {
            $data = $this->serverViewData($data);
        }

        foreach ($filters as $key => $value) {
            if (!is_string($key) || $key === '' || array_key_exists($key, $data)) {
                continue;
            }

            $data[$key] = $value;
        }

        return array_replace($data, $extra);
    }

    protected function normalizedString(Request $request, string $key, string $default = ''): string
    {
        return trim((string) $request->input($key, $default));
    }

    protected function normalizedEnum(Request $request, string $key, array $allowed, string $default = ''): string
    {
        $value = (string) $request->input($key, $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    protected function normalizedBoolFlag(Request $request, string $key): bool
    {
        return (int) $request->input($key, 0) === 1;
    }

    protected function normalizedPage(Request $request, string $key = 'page', int $default = 1): int
    {
        return max(1, (int) $request->input($key, $default));
    }

    protected function boundedInt(Request $request, string $key, int $default, int $min, int $max): int
    {
        return max($min, min($max, (int) $request->input($key, $default)));
    }

    protected function normalizedDirection(Request $request, string $key, string $default = 'ASC'): string
    {
        $fallback = strtoupper($default) === 'DESC' ? 'DESC' : 'ASC';

        return strtoupper($this->normalizedEnum($request, $key, ['ASC', 'DESC', 'asc', 'desc'], $fallback));
    }

    protected function switchServerContext(Request $request, ?callable $onSwitch = null, string $key = 'server'): int
    {
        $requested = $request->input($key, null);
        if ($requested === null || $requested === '') {
            return ServerContext::currentId();
        }

        $serverId = (int) $requested;
        if ($serverId <= 0 || !ServerList::valid($serverId)) {
            return ServerContext::currentId();
        }

        if (ServerContext::currentId() === $serverId) {
            return $serverId;
        }

        ServerContext::set($serverId);
        if ($onSwitch !== null) {
            $onSwitch($serverId);
        }

        return $serverId;
    }

    protected function switchServerAndRebind(Request $request, ?object $repository, string $key = 'server'): int
    {
        return $this->switchServerContext($request, function (int $serverId) use ($repository): void {
            if ($repository !== null && method_exists($repository, 'rebind')) {
                $repository->rebind($serverId);
            }
        }, $key);
    }

    protected function switchServerAndRefresh(Request $request, callable $refresh, string $key = 'server'): int
    {
        return $this->switchServerContext($request, $refresh, $key);
    }

    protected function json(array $payload, int $status=200): Response
    { return Response::json($payload,$status); }

    protected function getPost(string $key,$default=null){ return $_POST[$key]??$default; }
    protected function getQuery(string $key,$default=null){ return $_GET[$key]??$default; }
    protected function requireLogin(): void
    {
        if(!Auth::check()){
            if(Request::expectsJsonResponseForServer($_SERVER)){
                Response::json([
                    'success' => false,
                    'message' => Lang::get('app.auth.errors.not_logged_in'),
                ],401)->send();
                exit;
            }
            Response::redirect('/account/login')->send();
            exit;
        }
    }

    protected function requireCapability(string $capability): void
    {
        $this->requireLogin();

        if (Auth::can($capability))
            return;

        if (Request::expectsJsonResponseForServer($_SERVER)) {
            Response::json([
                'success' => false,
                'message' => Lang::get('app.common.api.errors.forbidden'),
            ], 403)->send();
            exit;
        }

        Response::redirect('/')->send();
        exit;
    }

    protected function redirect(string $location,int $status=302): Response
    { return Response::redirect($location,$status); }

    protected function response(int $status=200,string $content='',array $headers=[]): Response
    { return new Response($content,$status,$headers); }
}

