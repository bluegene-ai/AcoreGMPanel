<?php

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;

final class SetupPageData
{
    private const TOTAL_STEPS = 5;

    public static function layoutData(int $currentStep): array
    {
        $steps = [];
        for ($i = 1; $i <= self::TOTAL_STEPS; $i++) {
            $steps[$i] = Lang::get('app.setup.layout.step_titles.' . $i, [], 'Step ' . $i);
        }

        return [
            'page_title' => Lang::get('app.setup.layout.page_title'),
            'intro' => Lang::get('app.setup.layout.intro'),
            'stepper_label' => Lang::get('app.setup.layout.stepper_label'),
            'steps' => $steps,
            'current_step' => max(1, min(self::TOTAL_STEPS, $currentStep)),
        ];
    }

    public static function stepData(int $step): array
    {
        return match ($step) {
            1 => self::envData(),
            2 => self::modeData(),
            3 => self::testData(),
            4 => self::adminData(),
            5 => self::finishData(),
            default => [],
        };
    }

    private static function envData(): array
    {
        return [
            'heading' => Lang::get('app.setup.env.title'),
            'section' => [
                'title' => Lang::get('app.setup.env.title'),
                'hint' => Lang::get('app.setup.env.hint'),
                'pill' => Lang::get('app.setup.env.pill'),
            ],
            'language' => [
                'title' => Lang::get('app.setup.env.language_title'),
                'intro' => Lang::get('app.setup.env.language_intro'),
                'submit' => Lang::get('app.setup.env.language_submit'),
                'submit_fail' => Lang::get('app.setup.env.language_submit_fail'),
            ],
            'messages' => [
                'passed' => Lang::get('app.setup.env.check_passed'),
                'failed' => Lang::get('app.setup.env.check_failed'),
            ],
            'actions' => [
                'retry' => Lang::get('app.setup.env.retry'),
            ],
        ];
    }

    private static function modeData(): array
    {
        return [
            'heading' => Lang::get('app.setup.mode.step_title', ['current' => 2, 'total' => self::TOTAL_STEPS]),
            'sections' => [
                'mode' => [
                    'title' => Lang::get('app.setup.mode.section.mode.title'),
                    'hint' => Lang::get('app.setup.mode.section.mode.hint'),
                    'pill' => Lang::get('app.setup.mode.section.mode.pill'),
                    'aria_group' => Lang::get('app.setup.mode.section.mode.aria_group'),
                ],
                'server_groups' => [
                    'title' => Lang::get('app.setup.mode.section.server_groups.title'),
                    'hint' => Lang::get('app.setup.mode.section.server_groups.hint'),
                    'pill' => Lang::get('app.setup.mode.section.server_groups.pill'),
                    'summary' => Lang::get('app.setup.mode.section.server_groups.summary'),
                ],
                'auth' => [
                    'title' => Lang::get('app.setup.mode.section.auth.title'),
                    'hint' => Lang::get('app.setup.mode.section.auth.hint'),
                    'pill' => Lang::get('app.setup.mode.section.auth.pill'),
                ],
                'realm_groups' => [
                    'title' => Lang::get('app.setup.mode.section.realm_groups.title'),
                    'hint' => Lang::get('app.setup.mode.section.realm_groups.hint'),
                    'pill' => Lang::get('app.setup.mode.section.realm_groups.pill'),
                ],
            ],
            'cards' => [
                'single' => [
                    'title' => Lang::get('app.setup.mode.cards.single.title'),
                    'badge' => Lang::get('app.setup.mode.cards.single.badge'),
                    'desc' => Lang::get('app.setup.mode.cards.single.desc'),
                ],
                'multi' => [
                    'title' => Lang::get('app.setup.mode.cards.multi.title'),
                    'badge' => Lang::get('app.setup.mode.cards.multi.badge'),
                    'desc' => Lang::get('app.setup.mode.cards.multi.desc'),
                ],
                'multi_full' => [
                    'title' => Lang::get('app.setup.mode.cards.multi_full.title'),
                    'badge' => Lang::get('app.setup.mode.cards.multi_full.badge'),
                    'desc' => Lang::get('app.setup.mode.cards.multi_full.desc'),
                ],
            ],
            'server' => [
                'title_prefix' => Lang::get('app.setup.mode.server.title_prefix'),
                'remove' => Lang::get('app.setup.mode.server.remove'),
                'name_label' => Lang::get('app.setup.mode.server.name_label'),
                'name_placeholder' => Lang::get('app.setup.mode.server.name_placeholder'),
                'auth_title' => Lang::get('app.setup.mode.server.auth_title'),
                'characters_title' => Lang::get('app.setup.mode.server.characters_title'),
                'world_title' => Lang::get('app.setup.mode.server.world_title'),
                'soap_title' => Lang::get('app.setup.mode.server.soap_title'),
            ],
            'realm' => [
                'title_prefix' => Lang::get('app.setup.mode.realm.title_prefix'),
                'meta' => [
                    'id' => Lang::get('app.setup.mode.realm.meta.id'),
                    'port' => Lang::get('app.setup.mode.realm.meta.port'),
                ],
                'characters' => [
                    'title' => Lang::get('app.setup.mode.realm.characters.title'),
                ],
                'world' => [
                    'title' => Lang::get('app.setup.mode.realm.world.title'),
                ],
                'soap' => [
                    'title' => Lang::get('app.setup.mode.realm.soap.title'),
                ],
                'empty' => Lang::get('app.setup.mode.realm.empty'),
            ],
            'actions' => [
                'add_server' => Lang::get('app.setup.mode.actions.add_server'),
                'verify' => Lang::get('app.setup.mode.actions.verify'),
                'verifying' => Lang::get('app.setup.mode.actions.verifying'),
                'request_fail' => Lang::get('app.setup.mode.actions.request_fail'),
                'save_fail' => Lang::get('app.setup.mode.actions.save_fail'),
                'unknown_error' => Lang::get('app.setup.mode.actions.unknown_error'),
            ],
            'messages' => [
                'verify_success' => Lang::get('app.setup.mode.messages.verify_success', ['count' => ':count']),
                'verify_empty' => Lang::get('app.setup.mode.messages.verify_empty'),
                'verify_fail' => Lang::get('app.setup.mode.messages.verify_fail'),
            ],
            'footer' => [
                'hint' => Lang::get('app.setup.mode.footer.hint'),
                'submit' => Lang::get('app.setup.mode.footer.submit'),
                'back' => Lang::get('app.setup.mode.footer.back'),
            ],
        ];
    }

    private static function testData(): array
    {
        return [
            'heading' => Lang::get('app.setup.test.title', ['current' => 3, 'total' => self::TOTAL_STEPS]),
            'messages' => [
                'success' => Lang::get('app.setup.test.success'),
                'failure' => Lang::get('app.setup.test.failure'),
            ],
            'actions' => [
                'next' => Lang::get('app.setup.test.next_admin'),
                'back' => Lang::get('app.setup.test.back'),
            ],
            'status' => [
                'ok' => Lang::get('app.setup.status.ok'),
                'fail' => Lang::get('app.setup.status.fail'),
            ],
        ];
    }

    private static function adminData(): array
    {
        return [
            'heading' => Lang::get('app.setup.admin.step_title', ['current' => 4, 'total' => self::TOTAL_STEPS]),
            'fields' => [
                'username' => Lang::get('app.setup.admin.fields.username'),
                'password' => Lang::get('app.setup.admin.fields.password'),
                'password_confirm' => Lang::get('app.setup.admin.fields.password_confirm'),
            ],
            'actions' => [
                'submit' => Lang::get('app.setup.admin.submit'),
                'back' => Lang::get('app.setup.admin.back'),
            ],
            'submit_fail' => Lang::get('app.setup.admin.save_failed'),
        ];
    }

    private static function finishData(): array
    {
        return [
            'heading' => Lang::get('app.setup.finish.step_title', ['current' => 5, 'total' => self::TOTAL_STEPS]),
            'messages' => [
                'success' => Lang::get('app.setup.finish.success'),
                'failure' => Lang::get('app.setup.finish.failure', ['errors' => ':errors']),
            ],
            'actions' => [
                'enter_panel' => Lang::get('app.setup.finish.enter_panel'),
                'back' => Lang::get('app.setup.finish.back'),
            ],
        ];
    }
}