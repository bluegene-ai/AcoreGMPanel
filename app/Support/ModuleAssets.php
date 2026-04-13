<?php
declare(strict_types=1);

namespace Acme\Panel\Support;

final class ModuleAssets
{
    private const CORE_STYLE_ASSETS = [
        'css/app-core.css',
    ];

    private const PANEL_SCRIPT_ASSET = 'js/panel.js';

    private const VIEW_MODULES = [
        'account.' => 'account',
        'aegis.' => 'aegis',
        'bag_query.' => 'bag_query',
        'character.' => 'character',
        'creature.' => 'creature',
        'item.' => 'item',
        'item_owner.' => 'item_owner',
        'logs.' => 'logs',
        'mail.' => 'mail',
        'mass_mail.' => 'mass_mail',
        'quest.' => 'quest',
        'smartai.' => 'smart_ai_wizard',
        'soap.' => 'soap_wizard',
    ];

    private const PAGE_MODULE_ALIASES = [
        'character_boost_codes' => 'character_boost',
        'character_boost_redeem' => 'character_boost',
        'character_boost_template_edit' => 'character_boost',
        'character_boost_templates' => 'character_boost',
        'smart_ai_wizard' => 'smartai',
        'soap_wizard' => 'soap',
    ];

    private const NAV_ITEMS = [
        [
            'path' => '/',
            'label' => 'app.nav.home',
            'activePrefixes' => ['/'],
        ],
        [
            'path' => '/account',
            'label' => 'app.nav.account',
            'activePrefixes' => ['/account'],
            'capability' => 'accounts.list',
        ],
        [
            'path' => '/character',
            'label' => 'app.nav.character',
            'activePrefixes' => ['/character'],
            'capability' => 'characters.list',
        ],
        [
            'path' => '/character-boost/templates',
            'label' => 'app.nav.character_boost',
            'activePrefixes' => ['/character-boost'],
            'capability' => 'boost.templates',
        ],
        [
            'path' => '/item',
            'label' => 'app.nav.item',
            'activePrefixes' => ['/item'],
            'capability' => 'content.view',
        ],
        [
            'path' => '/creature',
            'label' => 'app.nav.creature',
            'activePrefixes' => ['/creature'],
            'capability' => 'content.view',
        ],
        [
            'path' => '/quest',
            'label' => 'app.nav.quest',
            'activePrefixes' => ['/quest'],
            'capability' => 'content.view',
        ],
        [
            'path' => '/mail',
            'label' => 'app.nav.mail',
            'activePrefixes' => ['/mail'],
            'capability' => 'mail.list',
        ],
        [
            'path' => '/mass-mail',
            'label' => 'app.nav.mass_mail',
            'activePrefixes' => ['/mass-mail'],
            'capability' => 'mass_mail.compose',
        ],
        [
            'path' => '/bag',
            'label' => 'app.nav.bag',
            'activePrefixes' => ['/bag', '/bag-query'],
        ],
        [
            'path' => '/item-ownership',
            'label' => 'app.nav.item_owner',
            'activePrefixes' => ['/item-ownership'],
        ],
        [
            'path' => '/soap',
            'label' => 'app.nav.soap',
            'activePrefixes' => ['/soap'],
            'capability' => 'soap.catalog',
        ],
        [
            'path' => '/smart-ai',
            'label' => 'app.nav.smart_ai',
            'activePrefixes' => ['/smart-ai'],
            'capability' => 'content.preview',
        ],
        [
            'path' => '/aegis',
            'label' => 'app.nav.aegis',
            'activePrefixes' => ['/aegis'],
            'capability' => 'aegis.dashboard',
        ],
        [
            'path' => '/logs',
            'label' => 'app.nav.logs',
            'activePrefixes' => ['/logs'],
            'capability' => 'logs.catalog',
        ],
    ];

    private const CSS_BUNDLES = [
        'account' => ['account'],
        'aegis' => ['aegis'],
        'bag_query' => ['bag_query'],
        'character' => ['character', 'bag_query'],
        'character_boost' => ['character_boost'],
        'creature' => ['editor_common', 'creature'],
        'item' => ['editor_common', 'item'],
        'item_owner' => ['item_owner'],
        'logs' => ['logs'],
        'mail' => ['mail'],
        'mass_mail' => ['mass_mail'],
        'quest' => ['quest'],
        'smartai' => ['smartai'],
        'soap' => ['soap'],
    ];

    private const JS_LOCALE_BUNDLES = [
        'account' => ['account'],
        'aegis' => ['aegis'],
        'bag_query' => ['bag_query'],
        'character' => ['character', 'bag_query'],
        'character_boost' => ['character_boost'],
        'creature' => ['creature'],
        'item' => ['item', 'bitmask'],
        'item_owner' => ['item_owner'],
        'logs' => ['logs'],
        'mail' => ['mail'],
        'mass_mail' => ['mass_mail'],
        'quest' => ['quest'],
        'smartai' => ['smartai'],
        'soap' => ['soap'],
    ];

    private const SPLIT_LANG_SECTIONS = [
        'account',
        'aegis',
        'auth',
        'alerts',
        'bag_query',
        'character',
        'character_boost',
        'creature',
        'home',
        'item',
        'item_owner',
        'logs',
        'mail',
        'mass_mail',
        'quest',
        'realm',
        'smartai',
        'soap',
        'setup',
    ];

    private const SPLIT_JS_MODULES = [
        'aegis',
        'bag_query',
        'character_boost',
        'creature',
        'item',
        'item_owner',
        'bitmask',
        'logs',
        'mail',
        'mass_mail',
        'quest',
        'smartai',
        'soap',
    ];

    private const JS_LOCALE_TRANSLATION_KEYS = [
        'account' => 'app.account',
        'character' => 'app.character',
    ];

    public static function normalizePageModule(?string $module): ?string
    {
        $module = self::sanitizePageModule($module);
        if ($module === null)
            return null;

        return self::PAGE_MODULE_ALIASES[$module] ?? $module;
    }

    public static function pageModule(?string $viewName, ?string $explicitModule = null): ?string
    {
        $explicitModule = self::sanitizePageModule($explicitModule);
        if ($explicitModule !== null)
            return $explicitModule;

        if (!is_string($viewName) || trim($viewName) === '')
            return null;

        foreach (self::VIEW_MODULES as $prefix => $module) {
            if (str_starts_with($viewName, $prefix))
                return $module;
        }

        return null;
    }

    public static function scriptModuleForPage(?string $module): ?string
    {
        return self::sanitizePageModule($module);
    }

    public static function scriptAssetPathForPage(?string $module): ?string
    {
        $scriptModule = self::scriptModuleForPage($module);
        if ($scriptModule === null)
            return null;

        return 'js/modules/' . $scriptModule . '.js';
    }

    public static function pageScriptUrlForPage(?string $module): ?string
    {
        $path = self::scriptAssetPathForPage($module);
        if ($path === null)
            return null;

        return self::assetPath($path);
    }

    public static function cssBundlesForPage(?string $module): array
    {
        $module = self::normalizePageModule($module);
        if ($module === null)
            return [];

        return self::CSS_BUNDLES[$module] ?? [];
    }

    public static function stylesheetUrlsForPage(?string $module): array
    {
        $urls = [];

        foreach (self::CORE_STYLE_ASSETS as $asset) {
            $urls[] = self::assetPath($asset);
        }

        foreach (self::cssBundlesForPage($module) as $bundle) {
            $urls[] = self::assetPath('css/modules/' . $bundle . '.css');
        }

        return array_values(array_unique($urls));
    }

    public static function jsLocaleBundlesForPage(?string $module): array
    {
        $module = self::normalizePageModule($module);
        if ($module === null)
            return [];

        return self::JS_LOCALE_BUNDLES[$module] ?? [$module];
    }

    public static function jsLocaleTranslationKey(string $bundle): string
    {
        return self::JS_LOCALE_TRANSLATION_KEYS[$bundle] ?? ('app.js.modules.' . $bundle);
    }

    public static function panelScriptUrl(): string
    {
        return self::assetPath(self::PANEL_SCRIPT_ASSET);
    }

    public static function clientGlobalsForPage(?string $module, array $pageCapabilities = []): array
    {
        return [
            [
                'name' => '__CSRF_TOKEN',
                'value' => Csrf::token(),
                'freeze' => false,
            ],
            [
                'name' => 'PANEL_LOCALE',
                'value' => PanelLocale::jsLocaleForPage($module),
                'freeze' => false,
            ],
            [
                'name' => 'APP_ENUMS',
                'value' => self::appEnums(),
                'freeze' => true,
            ],
            [
                'name' => 'PANEL_PAGE_SCRIPT_MODULE',
                'value' => self::scriptModuleForPage($module),
                'freeze' => false,
            ],
            [
                'name' => 'PANEL_PAGE_SCRIPT_SRC',
                'value' => self::pageScriptUrlForPage($module),
                'freeze' => false,
            ],
            [
                'name' => 'PANEL_CAPABILITIES',
                'value' => self::normalizePageCapabilities($pageCapabilities),
                'freeze' => true,
            ],
        ];
    }

    public static function navigationItems(): array
    {
        return self::NAV_ITEMS;
    }

    public static function pathMatches(string $currentPath, array $prefixes): bool
    {
        $currentPath = trim($currentPath);
        if ($currentPath === '')
            $currentPath = '/';

        foreach ($prefixes as $prefix) {
            if (!is_string($prefix) || $prefix === '')
                continue;

            $normalizedPrefix = $prefix === '/' ? '/' : rtrim($prefix, '/');
            if ($normalizedPrefix === '')
                $normalizedPrefix = '/';

            if ($normalizedPrefix === '/' && $currentPath === '/')
                return true;

            if ($normalizedPrefix !== '/' && ($currentPath === $normalizedPrefix || str_starts_with($currentPath, $normalizedPrefix . '/')))
                return true;
        }

        return false;
    }

    public static function languageRoute(string $key): ?array
    {
        $parts = explode('.', $key);
        if (($parts[0] ?? null) !== 'app')
            return null;

        $section = $parts[1] ?? null;
        if (is_string($section) && in_array($section, self::SPLIT_LANG_SECTIONS, true)) {
            return [
                'file' => $section,
                'path' => array_slice($parts, 2),
            ];
        }

        if (($parts[1] ?? null) !== 'js' || ($parts[2] ?? null) !== 'modules')
            return null;

        $module = $parts[3] ?? null;
        if (!is_string($module) || !in_array($module, self::SPLIT_JS_MODULES, true))
            return null;

        return [
            'file' => $module,
            'path' => array_merge(['js', 'modules', $module], array_slice($parts, 4)),
        ];
    }

    public static function assetPath(string $path): string
    {
        $normalizedPath = ltrim($path, '/');
        $url = function_exists('asset') ? asset($normalizedPath) : '/assets/' . $normalizedPath;

        $assetFile = dirname(__DIR__, 2) . '/public/assets/' . preg_replace('#^assets/#', '', $normalizedPath);
        if (!is_file($assetFile))
            return $url;

        $version = @filemtime($assetFile);
        if (!$version)
            return $url;

        return $url . (str_contains($url, '?') ? '&' : '?') . 'v=' . $version;
    }

    private static function appEnums(): array
    {
        $qualityNames = \Acme\Panel\Core\ItemQuality::allLocalized();
        $qualityCodes = [];
        foreach (array_keys($qualityNames) as $qualityNumber) {
            $qualityCodes[$qualityNumber] = \Acme\Panel\Core\ItemQuality::code((int) $qualityNumber);
        }

        return [
            'qualities' => $qualityNames,
            'qualityCodes' => $qualityCodes,
            'classes' => \Acme\Panel\Core\ItemMeta::classes(),
            'subclasses' => [],
            'flags' => [
                'regular' => \Acme\Panel\Core\ItemFlags::regular(),
                'extra' => \Acme\Panel\Core\ItemFlags::extra(),
                'custom' => \Acme\Panel\Core\ItemFlags::custom(),
            ],
        ];
    }

    private static function normalizePageCapabilities(array $pageCapabilities): array
    {
        $normalized = [];
        foreach ($pageCapabilities as $key => $value) {
            if (!is_string($key) || $key === '')
                continue;

            $normalized[$key] = (bool) $value;
        }

        return $normalized;
    }

    private static function sanitizePageModule(?string $module): ?string
    {
        if (!is_string($module))
            return null;

        $module = preg_replace('/[^A-Za-z0-9_]/', '', $module) ?? '';
        if ($module === '')
            return null;

        return $module;
    }
}