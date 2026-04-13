<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\View;

final class PageLayout
{
    public static function wrap(string $view, string $content, array $data = []): string
    {
        if (stripos($content, '<!DOCTYPE') !== false)
            return $content;

        $layout = self::resolveLayout($view, $data);
        if ($layout === 'setup')
            return SetupLayout::wrap($content, $data);

        if ($layout !== 'standard')
            return $content;

        $layoutData = self::layoutData($data);

        $top = View::make('layouts.base_top', $layoutData);
        $bottom = View::make('layouts.base_bottom', $layoutData);

        return $top . $content . $bottom;
    }

    private static function layoutData(array $data): array
    {
        $pageModule = ModuleAssets::pageModule($data['__viewName'] ?? null, $data['module'] ?? null);
        $currentServer = isset($data['current_server'])
            ? (int) $data['current_server']
            : ServerContext::currentId();
        $online = ServerStats::onlineCount($currentServer);
        $totalCharacters = ServerStats::totalCharacters($currentServer);

        return $data + [
            '__pageModule' => $pageModule,
            '__layoutHead' => PageMetadata::present(
                is_array($data['__pageMeta'] ?? null) ? $data['__pageMeta'] : [],
                $data['title'] ?? null
            ),
            '__layoutCurrentPath' => self::currentPath(),
            '__layoutBodyAttributes' => self::bodyAttributes($pageModule),
            '__layoutStyleAssetUrls' => ModuleAssets::stylesheetUrlsForPage($pageModule),
            '__layoutScriptModule' => ModuleAssets::scriptModuleForPage($pageModule),
            '__layoutNavigationItems' => ModuleAssets::navigationItems(),
            '__layoutServerSwitch' => [
                'current_server' => $currentServer,
                'servers' => isset($data['servers']) && is_array($data['servers'])
                    ? $data['servers']
                    : ServerList::options(),
            ],
            '__layoutLocales' => [
                'available' => Lang::available(),
                'active' => Lang::locale(),
            ],
            '__layoutServerStats' => [
                'online' => $online,
                'total' => $totalCharacters,
                'online_label' => $online === null ? '?' : (string) $online,
                'total_label' => $totalCharacters === null ? '?' : (string) $totalCharacters,
            ],
        ];
    }

    private static function resolveLayout(string $view, array $data): string
    {
        $explicit = $data['__layout'] ?? null;
        if ($explicit === false || $explicit === 'none')
            return 'none';

        if (is_string($explicit) && $explicit !== '')
            return $explicit;

        $normalizedView = str_replace('/', '.', $view);
        if (str_starts_with($normalizedView, 'setup.'))
            return 'setup';

        return 'standard';
    }

    private static function currentPath(): string
    {
        $currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $basePath = rtrim((string) (Config::get('app.base_path') ?? ''), '/');
        if ($basePath !== '' && str_starts_with($currentPath, $basePath)) {
            $trimmedPath = substr($currentPath, strlen($basePath));
            $currentPath = ($trimmedPath === false || $trimmedPath === '') ? '/' : $trimmedPath;
        }

        return $currentPath === '' ? '/' : $currentPath;
    }

    private static function bodyAttributes(?string $pageModule): array
    {
        $attributes = [
            'data-app-base' => (string) (Config::get('app.base_path') ?? ''),
        ];

        $scriptModule = ModuleAssets::scriptModuleForPage($pageModule);
        if ($scriptModule !== null)
            $attributes['data-module'] = $scriptModule;

        return $attributes;
    }
}