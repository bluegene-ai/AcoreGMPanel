<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Boss;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Boss\BossRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\SoapCommandRunner;

class BossController extends Controller
{
    private ?BossRepository $repo = null;

    private function repo(): BossRepository
    {
        if ($this->repo === null) {
            $this->repo = new BossRepository();
        }

        return $this->repo;
    }

    private function maybeSwitchServer(Request $request): void
    {
        $this->switchServerAndRebind($request, $this->repo());
    }

    private function requireDashboardCapability(): void
    {
        $this->requireCapability('boss.dashboard');
    }

    private function requireActionCapability(): void
    {
        $this->requireCapability('boss.actions');
    }

    public function index(Request $request): Response
    {
        $this->requireDashboardCapability();
        $this->maybeSwitchServer($request);

        $eventLimit = $this->boundedInt(
            $request,
            'event_limit',
            (int) Config::get('boss.event_limit', 18),
            5,
            100
        );
        $contributorLimit = $this->boundedInt(
            $request,
            'contributor_limit',
            (int) Config::get('boss.contributor_limit', 18),
            5,
            100
        );

        $server = ServerContext::server();

        return $this->pageView('boss.index', $this->serverViewData([
            'boss_dashboard' => $this->repo()->dashboard($eventLimit, $contributorLimit),
            'boss_options' => [
                'presets' => $this->presetOptions(),
                'difficulties' => $this->difficultyOptions(),
            ],
            'event_limit' => $eventLimit,
            'contributor_limit' => $contributorLimit,
        ]), [
            'module' => 'boss',
            'capabilities' => [
                'dashboard' => 'boss.dashboard',
                'events' => 'boss.events',
                'contributors' => 'boss.contributors',
                'actions' => 'boss.actions',
            ],
            'header' => [
                'intro' => __('app.boss.intro'),
                'note' => __('app.boss.scope_note', [
                    'server' => (string) ($server['name'] ?? ''),
                ]),
            ],
            'meta' => [
                'title' => __('app.boss.page_title'),
            ],
        ]);
    }

    public function apiAction(Request $request): Response
    {
        $this->requireActionCapability();
        $this->maybeSwitchServer($request);

        $action = $this->normalizedEnum(
            $request,
            'action',
            ['spawn', 'preset', 'difficulty', 'rebase'],
            ''
        );
        $value = $this->normalizedString($request, 'value');

        if ($action === '') {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.invalid_action'),
            ], 422);
        }

        if ($action === 'preset' && !$this->isAllowedPreset($value)) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.invalid_preset'),
            ], 422);
        }

        if ($action === 'difficulty' && !$this->isAllowedDifficulty($value)) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.invalid_difficulty'),
            ], 422);
        }

        if (in_array($action, ['preset', 'difficulty'], true) && $value === '') {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.value_required'),
            ], 422);
        }

        $command = $this->buildCommand($action, $value);
        $result = SoapCommandRunner::execute($command, [
            'server_id' => ServerContext::currentId(),
        ]);

        Audit::log('boss', 'command', $action, [
            'server_id' => ServerContext::currentId(),
            'command' => $command,
            'value' => $value,
            'success' => $result['success'],
            'output' => $result['output'] ?? '',
        ]);

        return $this->json([
            'success' => $result['success'],
            'message' => $result['message'] !== ''
                ? $result['message']
                : Lang::get('app.boss.feedback.action_success'),
            'payload' => [
                'action' => $action,
                'value' => $value,
                'execution' => $result['execution'],
                'output' => $result['output'] ?? '',
            ],
        ], $result['success'] ? 200 : 422);
    }

    private function buildCommand(string $action, string $value): string
    {
        if ($action === 'spawn')
            return '.boss spawn';

        if ($action === 'rebase')
            return '.boss rebase';

        return '.boss ' . $action . ' ' . $value;
    }

    private function presetOptions(): array
    {
        $items = [];

        foreach ((array) Config::get('boss.preset_values', []) as $value) {
            $value = trim((string) $value);
            if ($value === '')
                continue;

            $items[] = [
                'value' => $value,
                'label' => __('app.boss.presets.labels.' . $value, [], $value),
                'summary' => __('app.boss.presets.summary.' . $value, [], ''),
            ];
        }

        return $items;
    }

    private function difficultyOptions(): array
    {
        $items = [];

        foreach ((array) Config::get('boss.difficulty_values', []) as $value) {
            $value = trim((string) $value);
            if ($value === '')
                continue;

            $items[] = [
                'value' => $value,
                'label' => __('app.boss.difficulties.labels.' . $value, [], $value),
                'summary' => __('app.boss.difficulties.summary.' . $value, [], ''),
            ];
        }

        return $items;
    }

    private function isAllowedPreset(string $value): bool
    {
        return in_array($value, (array) Config::get('boss.preset_values', []), true);
    }

    private function isAllowedDifficulty(string $value): bool
    {
        return in_array(
            $value,
            (array) Config::get('boss.difficulty_values', []),
            true
        );
    }
}