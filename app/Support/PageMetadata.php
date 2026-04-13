<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Url;

final class PageMetadata
{
    public static function resolve(string $view, array $data = []): array
    {
        $normalizedView = str_replace('/', '.', $view);

        $meta = self::registeredMetadata($normalizedView, $data);
        $meta = self::mergeOverrides($meta, $data);

        $meta['title'] = self::normalizeString($meta['title'] ?? null)
            ?? self::fallbackTitle();
        $meta['description'] = self::normalizeString($meta['description'] ?? null);
        $meta['keywords'] = self::normalizeKeywords($meta['keywords'] ?? null);
        $meta['canonical'] = self::normalizeString($meta['canonical'] ?? null)
            ?? self::currentCanonical();
        $meta['breadcrumbs'] = self::normalizeBreadcrumbs($meta['breadcrumbs'] ?? []);

        return $meta;
    }

    public static function present(array $meta = [], mixed $fallbackTitle = null): array
    {
        $keywords = $meta['keywords'] ?? null;
        if (is_array($keywords)) {
            $keywords = implode(', ', array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $keywords
            )));
        }

        return [
            'title' => self::normalizeString($meta['title'] ?? null)
                ?? self::normalizeString($fallbackTitle)
                ?? self::fallbackTitle(),
            'description' => self::normalizeString($meta['description'] ?? null) ?? '',
            'keywords' => self::normalizeString($keywords) ?? '',
            'canonical' => self::normalizeString($meta['canonical'] ?? null) ?? '',
            'breadcrumbs' => self::normalizeBreadcrumbs($meta['breadcrumbs'] ?? []),
        ];
    }

    private static function registeredMetadata(string $view, array $data): array
    {
        return match ($view) {
            'home.index' => [
                'title' => (string) Config::get('app.name', 'Acore GM Panel'),
                'breadcrumbs' => [self::crumb(Lang::get('app.nav.home'), '/')],
            ],
            'auth.login' => [
                'title' => Lang::get('app.auth.login_title'),
                'breadcrumbs' => [
                    self::crumb(Lang::get('app.nav.home'), '/'),
                    self::crumb(Lang::get('app.auth.page_title')),
                ],
            ],
            'account.index' => [
                'title' => Lang::get('app.account.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.account.page_title', '/account'),
            ],
            'character.index' => [
                'title' => Lang::get('app.character.index.title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.character.index.title', '/character'),
            ],
            'character.show' => self::characterShowMetadata($data),
            'item.index' => [
                'title' => Lang::get('app.item.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.item.page_title', '/item'),
            ],
            'item.edit' => self::itemEditMetadata($data),
            'creature.index' => [
                'title' => Lang::get('app.creature.index.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.creature.index.page_title', '/creature'),
            ],
            'creature.edit' => self::creatureEditMetadata($data),
            'quest.index' => self::questIndexMetadata($data),
            'quest.edit' => self::questEditMetadata($data),
            'mail.index' => [
                'title' => Lang::get('app.mail.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.mail.page_title', '/mail'),
            ],
            'mass_mail.index' => [
                'title' => Lang::get('app.mass_mail.index.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.mass_mail.index.page_title', '/mass-mail'),
            ],
            'soap.index' => [
                'title' => Lang::get('app.soap.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.soap.page_title', '/soap'),
            ],
            'smartai.index' => [
                'title' => Lang::get('app.smartai.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.smartai.page_title', '/smartai'),
            ],
            'logs.index' => [
                'title' => Lang::get('app.logs.index.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.logs.page_title', '/logs'),
            ],
            'aegis.index' => [
                'title' => Lang::get('app.aegis.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.aegis.page_title', '/aegis'),
            ],
            'bag_query.index' => [
                'title' => Lang::get('app.bag_query.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.bag_query.page_title', '/bag'),
            ],
            'item_owner.index' => [
                'title' => Lang::get('app.item_owner.page_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.item_owner.page_title', '/item-owner'),
            ],
            'character_boost.templates' => [
                'title' => Lang::get('app.character_boost.templates.title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.character_boost.templates.title', '/character-boost/templates'),
            ],
            'character_boost.template_edit' => self::characterBoostTemplateEditMetadata($data),
            'character_boost.codes' => [
                'title' => Lang::get('app.character_boost.codes.title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.character_boost.codes.title', '/character-boost/codes'),
            ],
            'character_boost.redeem' => [
                'title' => Lang::get('app.character_boost.redeem.title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.character_boost.redeem.title', '/character-boost/redeem'),
            ],
            'setup.env',
            'setup.mode',
            'setup.test',
            'setup.admin',
            'setup.finish' => [
                'title' => Lang::get('app.setup.layout.page_title'),
                'breadcrumbs' => [self::crumb(Lang::get('app.setup.layout.page_title'))],
            ],
            default => [],
        };
    }

    private static function mergeOverrides(array $meta, array $data): array
    {
        $overrides = [];
        if (isset($data['meta']) && is_array($data['meta']))
            $overrides = $data['meta'];

        foreach (['title', 'description', 'keywords', 'canonical', 'breadcrumbs'] as $key) {
            if (array_key_exists($key, $data))
                $overrides[$key] = $data[$key];
        }

        if (array_key_exists('meta_description', $data))
            $overrides['description'] = $data['meta_description'];
        if (array_key_exists('meta_keywords', $data))
            $overrides['keywords'] = $data['meta_keywords'];

        return array_replace($meta, $overrides);
    }

    private static function moduleBreadcrumbs(string $labelKey, string $path): array
    {
        return [
            self::crumb(Lang::get('app.nav.home'), '/'),
            self::crumb(Lang::get($labelKey), $path),
        ];
    }

    private static function characterShowMetadata(array $data): array
    {
        $summary = $data['summary'] ?? null;
        if (is_array($summary) && !empty($summary['name'])) {
            $guid = (int) ($summary['guid'] ?? ($data['guid'] ?? 0));

            return [
                'title' => Lang::get('app.character.show.title', [
                    'name' => (string) $summary['name'],
                    'guid' => $guid,
                ]),
                'breadcrumbs' => [
                    self::crumb(Lang::get('app.nav.home'), '/'),
                    self::crumb(Lang::get('app.character.index.title'), '/character'),
                    self::crumb((string) $summary['name']),
                ],
            ];
        }

        $guid = (int) ($data['guid'] ?? 0);
        return [
            'title' => Lang::get('app.character.show.title_not_found', ['guid' => $guid]),
            'breadcrumbs' => [
                self::crumb(Lang::get('app.nav.home'), '/'),
                self::crumb(Lang::get('app.character.index.title'), '/character'),
                self::crumb(Lang::get('app.character.show.title_default')),
            ],
        ];
    }

    private static function itemEditMetadata(array $data): array
    {
        $item = $data['item'] ?? [];
        $entry = (int) ($item['entry'] ?? 0);

        return [
            'title' => Lang::get('app.item.edit.page_title', ['id' => $entry]),
            'breadcrumbs' => [
                self::crumb(Lang::get('app.nav.home'), '/'),
                self::crumb(Lang::get('app.item.page_title'), '/item'),
                self::crumb(Lang::get('app.item.edit.title', ['id' => $entry])),
            ],
        ];
    }

    private static function creatureEditMetadata(array $data): array
    {
        $creature = $data['creature'] ?? [];
        $entry = (int) ($creature['entry'] ?? 0);

        return [
            'title' => Lang::get('app.creature.edit.title', ['id' => $entry]),
            'breadcrumbs' => [
                self::crumb(Lang::get('app.nav.home'), '/'),
                self::crumb(Lang::get('app.creature.index.page_title'), '/creature'),
                self::crumb(Lang::get('app.creature.edit.title', ['id' => $entry])),
            ],
        ];
    }

    private static function questIndexMetadata(array $data): array
    {
        if (!empty($data['not_found_id'])) {
            return [
                'title' => Lang::get('app.quest.messages.not_found_title'),
                'breadcrumbs' => self::moduleBreadcrumbs('app.quest.index.page_title', '/quest'),
            ];
        }

        return [
            'title' => Lang::get('app.quest.index.page_title'),
            'breadcrumbs' => self::moduleBreadcrumbs('app.quest.index.page_title', '/quest'),
        ];
    }

    private static function questEditMetadata(array $data): array
    {
        $quest = $data['quest'] ?? [];
        $id = (int) ($quest['ID'] ?? 0);

        return [
            'title' => Lang::get('app.quest.edit.page_title', ['id' => $id]),
            'breadcrumbs' => [
                self::crumb(Lang::get('app.nav.home'), '/'),
                self::crumb(Lang::get('app.quest.index.page_title'), '/quest'),
                self::crumb(Lang::get('app.quest.edit.page_title', ['id' => $id])),
            ],
        ];
    }

    private static function characterBoostTemplateEditMetadata(array $data): array
    {
        if (($data['error'] ?? null) !== null && empty($data['template'])) {
            return [
                'title' => Lang::get('app.character_boost.templates.edit_title_not_found'),
                'breadcrumbs' => [
                    self::crumb(Lang::get('app.nav.home'), '/'),
                    self::crumb(Lang::get('app.character_boost.templates.title'), '/character-boost/templates'),
                    self::crumb(Lang::get('app.character_boost.templates.edit_title_not_found')),
                ],
            ];
        }

        $template = $data['template'] ?? [];
        $id = (int) ($template['id'] ?? 0);
        $title = $id > 0
            ? Lang::get('app.character_boost.templates.edit_title', ['id' => $id])
            : Lang::get('app.character_boost.templates.create_title');

        return [
            'title' => $title,
            'breadcrumbs' => [
                self::crumb(Lang::get('app.nav.home'), '/'),
                self::crumb(Lang::get('app.character_boost.templates.title'), '/character-boost/templates'),
                self::crumb($title),
            ],
        ];
    }

    private static function fallbackTitle(): string
    {
        return (string) Config::get('app.name', Lang::get('app.app.title_suffix'));
    }

    private static function currentCanonical(): ?string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '')
            return null;

        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            ? 'https'
            : 'http';
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($path) || $path === '')
            $path = Url::to('/');

        return $scheme . '://' . $host . $path;
    }

    private static function crumb(string $label, ?string $path = null): array
    {
        $crumb = ['label' => $label];
        if ($path !== null)
            $crumb['url'] = Url::to($path);

        return $crumb;
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value))
            return null;

        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private static function normalizeKeywords(mixed $value): ?string
    {
        if (is_array($value)) {
            $parts = array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                $value
            ));

            return $parts ? implode(', ', $parts) : null;
        }

        return self::normalizeString($value);
    }

    private static function normalizeBreadcrumbs(mixed $value): array
    {
        if (!is_array($value))
            return [];

        $breadcrumbs = [];
        foreach ($value as $item) {
            if (!is_array($item))
                continue;

            $label = self::normalizeString($item['label'] ?? null);
            if ($label === null)
                continue;

            $crumb = ['label' => $label];
            $url = self::normalizeString($item['url'] ?? null);
            if ($url !== null)
                $crumb['url'] = $url;

            $breadcrumbs[] = $crumb;
        }

        return $breadcrumbs;
    }
}