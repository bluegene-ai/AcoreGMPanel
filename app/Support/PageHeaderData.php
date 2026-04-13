<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Url;

final class PageHeaderData
{
    public static function resolve(string $view, array $data = []): array
    {
        $normalizedView = str_replace('/', '.', $view);

        $header = self::registeredHeader($normalizedView, $data);
        $header = self::mergeOverrides($header, $data);

        $header['title'] = self::normalizeString($header['title'] ?? null)
            ?? self::normalizeString($data['title'] ?? null)
            ?? self::normalizeString(($data['__pageMeta']['title'] ?? null));
        $header['intro'] = self::normalizeString($header['intro'] ?? null);
        $header['note'] = self::normalizeString($header['note'] ?? null);
        $header['actions'] = self::normalizeActions($header['actions'] ?? []);

        return $header;
    }

    private static function registeredHeader(string $view, array $data): array
    {
        return match ($view) {
            'home.index' => [
                'title' => Lang::get('app.home.readme_heading'),
            ],
            'auth.login' => [
                'title' => Lang::get('app.auth.page_title'),
            ],
            'aegis.index' => [
                'intro' => Lang::get('app.aegis.intro'),
            ],
            'soap.index' => [
                'intro' => Lang::get('app.soap.intro'),
            ],
            'logs.index' => [
                'intro' => Lang::get('app.logs.intro'),
            ],
            'smartai.index' => [
                'intro' => Lang::get('app.smartai.intro'),
            ],
            'character.show' => [
                'actions' => [
                    [
                        'label' => '<- ' . Lang::get('app.character.show.back'),
                        'url' => Url::to('/character'),
                        'class' => 'btn',
                    ],
                ],
            ],
            'creature.edit' => [
                'intro' => self::normalizeString($data['creature']['name'] ?? null),
            ],
            'quest.edit' => [
                'intro' => self::normalizeString($data['quest']['LogTitle'] ?? null),
            ],
            'character_boost.templates' => [
                'note' => Lang::get('app.character_boost.templates.hint.realm', [
                    'id' => (int) ($data['realm_id'] ?? 1),
                ]),
                'actions' => [
                    [
                        'label' => Lang::get('app.character_boost.templates.actions.create'),
                        'url' => Url::to('/character-boost/templates/edit'),
                        'class' => 'btn btn-primary',
                        'capability' => 'boost.templates',
                    ],
                    [
                        'label' => Lang::get('app.character_boost.templates.actions.codes'),
                        'url' => Url::to('/character-boost/redeem-codes'),
                        'class' => 'btn',
                        'capability' => 'boost.codes',
                    ],
                    [
                        'label' => Lang::get('app.character_boost.templates.actions.public_redeem'),
                        'url' => Url::to('/public/character-boost'),
                        'class' => 'btn',
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                    ],
                ],
            ],
            'character_boost.codes' => [
                'actions' => [
                    [
                        'label' => Lang::get('app.character_boost.templates.title'),
                        'url' => Url::to('/character-boost/templates'),
                        'class' => 'btn',
                        'capability' => 'boost.templates',
                    ],
                ],
            ],
            'character_boost.template_edit' => [
                'title' => !empty($data['template']['id'])
                    ? Lang::get('app.character_boost.templates.edit_heading', [
                        'id' => (int) ($data['template']['id'] ?? 0),
                    ])
                    : Lang::get('app.character_boost.templates.create_heading'),
                'note' => Lang::get('app.character_boost.templates.hint.realm', [
                    'id' => (int) ($data['realm_id'] ?? 1),
                ]),
                'actions' => [
                    [
                        'label' => Lang::get('app.character_boost.templates.actions.back'),
                        'url' => Url::to('/character-boost/templates'),
                        'class' => 'btn',
                        'capability' => 'boost.templates',
                    ],
                    [
                        'label' => Lang::get('app.character_boost.templates.actions.codes'),
                        'url' => Url::to('/character-boost/redeem-codes'),
                        'class' => 'btn',
                        'capability' => 'boost.codes',
                    ],
                ],
            ],
            default => [],
        };
    }

    private static function mergeOverrides(array $header, array $data): array
    {
        $overrides = [];
        if (isset($data['page_header']) && is_array($data['page_header']))
            $overrides = $data['page_header'];

        foreach (['title', 'intro', 'note', 'actions'] as $key) {
            if (array_key_exists($key, $data))
                $overrides[$key] = $data[$key];
        }

        return array_replace($header, $overrides);
    }

    private static function normalizeActions(mixed $value): array
    {
        if (!is_array($value))
            return [];

        $actions = [];
        foreach ($value as $action) {
            if (!is_array($action))
                continue;

            $label = self::normalizeString($action['label'] ?? null);
            $url = self::normalizeString($action['url'] ?? null);
            if ($label === null || $url === null)
                continue;

            $normalized = [
                'label' => $label,
                'url' => $url,
                'class' => self::normalizeString($action['class'] ?? null) ?? 'btn',
            ];

            $target = self::normalizeString($action['target'] ?? null);
            if ($target !== null)
                $normalized['target'] = $target;

            $rel = self::normalizeString($action['rel'] ?? null);
            if ($rel !== null)
                $normalized['rel'] = $rel;

            $capability = self::normalizeString($action['capability'] ?? null);
            if ($capability !== null && !Auth::can($capability))
                continue;

            $capabilities = $action['capabilities'] ?? null;
            if (is_array($capabilities) && $capabilities !== []) {
                $allowed = false;
                foreach ($capabilities as $candidate) {
                    if (is_string($candidate) && $candidate !== '' && Auth::can($candidate)) {
                        $allowed = true;
                        break;
                    }
                }

                if (!$allowed)
                    continue;
            }

            $actions[] = $normalized;
        }

        return $actions;
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value))
            return null;

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}