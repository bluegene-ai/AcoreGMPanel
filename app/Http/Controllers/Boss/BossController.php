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
use Throwable;

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
                'random_modes' => $this->randomModeOptions(),
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
            ['spawn', 'preset', 'difficulty', 'rebase', 'config_reload'],
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

    public function apiConfigSave(Request $request): Response
    {
        $this->requireActionCapability();
        $this->maybeSwitchServer($request);

        $defaults = (array) Config::get('boss.defaults', []);
        $bossName = $this->trimmedName(
            $this->normalizedString(
                $request,
                'boss_name',
                (string) ($defaults['boss_name'] ?? '活动Boss')
            )
        );

        $config = [
            'boss_entry' => $this->boundedInt(
                $request,
                'boss_entry',
                (int) ($defaults['boss_entry'] ?? 647),
                1,
                2000000
            ),
            'boss_name' => $bossName,
            'boss_level' => $this->boundedInt(
                $request,
                'boss_level',
                (int) ($defaults['boss_level'] ?? 83),
                1,
                255
            ),
            'boss_scale_scaled' => $this->boundedScaledDecimal(
                $request,
                'boss_scale',
                (int) ($defaults['boss_scale_scaled'] ?? 500),
                10,
                5000
            ),
            'boss_health_multiplier_scaled' => $this->boundedScaledDecimal(
                $request,
                'boss_health_multiplier',
                (int) ($defaults['boss_health_multiplier_scaled'] ?? 2000),
                10,
                200000
            ),
            'boss_auras_text' => $this->normalizedIntegerListString(
                $this->normalizedString(
                    $request,
                    'boss_auras_text',
                    (string) ($defaults['boss_auras_text'] ?? '')
                )
            ),
            'ally_level' => $this->boundedInt(
                $request,
                'ally_level',
                (int) ($defaults['ally_level'] ?? 20),
                1,
                255
            ),
            'ally_health_multiplier_scaled' => $this->boundedScaledDecimal(
                $request,
                'ally_health_multiplier',
                (int) ($defaults['ally_health_multiplier_scaled'] ?? 150),
                10,
                200000
            ),
            'respawn_time_minutes' => $this->boundedInt(
                $request,
                'respawn_time_minutes',
                (int) ($defaults['respawn_time_minutes'] ?? 10),
                1,
                1440
            ),
            'minion_count_min' => $this->boundedInt(
                $request,
                'minion_count_min',
                (int) ($defaults['minion_count_min'] ?? 1),
                0,
                20
            ),
            'minion_count_max' => $this->boundedInt(
                $request,
                'minion_count_max',
                (int) ($defaults['minion_count_max'] ?? 2),
                0,
                20
            ),
            'skill_preset' => $this->normalizedEnum(
                $request,
                'skill_preset',
                (array) Config::get('boss.preset_values', []),
                (string) ($defaults['skill_preset'] ?? 'storm_siege')
            ),
            'skill_difficulty' => $this->normalizedEnum(
                $request,
                'skill_difficulty',
                (array) Config::get('boss.difficulty_values', []),
                (string) ($defaults['skill_difficulty'] ?? 'standard')
            ),
            'guaranteed_reward_enabled' => $this->normalizedBoolFlag($request, 'guaranteed_reward_enabled') ? 1 : 0,
            'guaranteed_reward_notify' => $this->normalizedBoolFlag($request, 'guaranteed_reward_notify') ? 1 : 0,
            'max_random_reward_players' => $this->boundedInt(
                $request,
                'max_random_reward_players',
                (int) ($defaults['max_random_reward_players'] ?? 3),
                0,
                100
            ),
            'class_reward_chance' => $this->boundedInt(
                $request,
                'class_reward_chance',
                (int) ($defaults['class_reward_chance'] ?? 60),
                0,
                100
            ),
            'formula_reward_chance' => $this->boundedInt(
                $request,
                'formula_reward_chance',
                (int) ($defaults['formula_reward_chance'] ?? 10),
                0,
                100
            ),
            'mount_reward_chance' => $this->boundedInt(
                $request,
                'mount_reward_chance',
                (int) ($defaults['mount_reward_chance'] ?? 15),
                0,
                100
            ),
            'random_reward_mode' => $this->normalizedEnum(
                $request,
                'random_reward_mode',
                ['weighted', 'random'],
                (string) ($defaults['random_reward_mode'] ?? 'weighted')
            ),
            'participation_range' => $this->boundedInt(
                $request,
                'participation_range',
                (int) ($defaults['participation_range'] ?? 80),
                20,
                500
            ),
            'damage_weight' => $this->boundedInt(
                $request,
                'damage_weight',
                (int) ($defaults['damage_weight'] ?? 100),
                0,
                10000
            ),
            'healing_weight' => $this->boundedInt(
                $request,
                'healing_weight',
                (int) ($defaults['healing_weight'] ?? 80),
                0,
                10000
            ),
            'threat_weight' => $this->boundedInt(
                $request,
                'threat_weight',
                (int) ($defaults['threat_weight'] ?? 35),
                0,
                10000
            ),
            'presence_weight' => $this->boundedInt(
                $request,
                'presence_weight',
                (int) ($defaults['presence_weight'] ?? 10),
                0,
                10000
            ),
            'kill_weight' => $this->boundedInt(
                $request,
                'kill_weight',
                (int) ($defaults['kill_weight'] ?? 3),
                0,
                10000
            ),
            'guaranteed_item_id' => $this->boundedInt(
                $request,
                'guaranteed_item_id',
                (int) ($defaults['guaranteed_item_id'] ?? 40753),
                0,
                2000000
            ),
            'guaranteed_item_count' => $this->boundedInt(
                $request,
                'guaranteed_item_count',
                (int) ($defaults['guaranteed_item_count'] ?? 2),
                0,
                10000
            ),
            'gold_min_copper' => $this->boundedInt(
                $request,
                'gold_min_copper',
                (int) ($defaults['gold_min_copper'] ?? 30000),
                0,
                2000000000
            ),
            'gold_max_copper' => $this->boundedInt(
                $request,
                'gold_max_copper',
                (int) ($defaults['gold_max_copper'] ?? 50000),
                0,
                2000000000
            ),
            'reward_items_text' => $this->normalizedIntegerListString(
                $this->normalizedString(
                    $request,
                    'reward_items_text',
                    (string) ($defaults['reward_items_text'] ?? '')
                )
            ),
            'reward_formulas_text' => $this->normalizedIntegerListString(
                $this->normalizedString(
                    $request,
                    'reward_formulas_text',
                    (string) ($defaults['reward_formulas_text'] ?? '')
                )
            ),
            'reward_mounts_text' => $this->normalizedIntegerListString(
                $this->normalizedString(
                    $request,
                    'reward_mounts_text',
                    (string) ($defaults['reward_mounts_text'] ?? '')
                )
            ),
        ];

        if ($config['minion_count_max'] < $config['minion_count_min']) {
            $config['minion_count_max'] = $config['minion_count_min'];
        }

        if ($config['gold_max_copper'] < $config['gold_min_copper']) {
            $config['gold_max_copper'] = $config['gold_min_copper'];
        }

        try {
            $savedConfig = $this->repo()->saveConfig($config);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.config_save_failed'),
            ], 422);
        }

        try {
            $reloadResult = SoapCommandRunner::execute('.boss config reload', [
                'server_id' => ServerContext::currentId(),
            ]);
        } catch (Throwable $exception) {
            $reloadResult = [
                'success' => false,
                'message' => $exception->getMessage(),
                'output' => '',
                'execution' => [],
            ];
        }

        Audit::log('boss', 'save_config', 'boss_activity_config', [
            'server_id' => ServerContext::currentId(),
            'boss_entry' => $config['boss_entry'],
            'boss_name' => $config['boss_name'],
            'success' => $reloadResult['success'],
            'reload_message' => $reloadResult['message'] ?? '',
            'reload_output' => $reloadResult['output'] ?? '',
        ]);

        $reloadMessage = trim((string) ($reloadResult['message'] ?? ''));
        if ($reloadMessage === '') {
            $reloadMessage = trim((string) ($reloadResult['output'] ?? ''));
        }

        return $this->json([
            'success' => $reloadResult['success'],
            'message' => $reloadResult['success']
                ? Lang::get('app.boss.feedback.config_saved')
                : Lang::get('app.boss.feedback.config_saved_reload_failed', [
                    'message' => $reloadMessage !== ''
                        ? $reloadMessage
                        : Lang::get('app.boss.errors.reload_failed'),
                ]),
            'payload' => [
                'saved' => true,
                'reload_success' => $reloadResult['success'],
                'config' => $savedConfig,
                'reload' => $reloadResult,
            ],
        ], $reloadResult['success'] ? 200 : 422);
    }

    private function buildCommand(string $action, string $value): string
    {
        if ($action === 'spawn')
            return '.boss spawn';

        if ($action === 'rebase')
            return '.boss rebase';

        if ($action === 'config_reload')
            return '.boss config reload';

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

    private function randomModeOptions(): array
    {
        return [
            [
                'value' => 'weighted',
                'label' => __('app.boss.config.random_modes.weighted'),
            ],
            [
                'value' => 'random',
                'label' => __('app.boss.config.random_modes.random'),
            ],
        ];
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

    private function boundedScaledDecimal(
        Request $request,
        string $key,
        int $defaultScaled,
        int $minScaled,
        int $maxScaled
    ): int {
        $rawValue = trim((string) $request->input($key, ''));
        $numericValue = is_numeric($rawValue)
            ? (float) $rawValue
            : ($defaultScaled / max(1, (int) Config::get('boss.decimal_scale', 100)));

        $scaled = (int) round(
            $numericValue * max(1, (int) Config::get('boss.decimal_scale', 100))
        );

        return max($minScaled, min($maxScaled, $scaled));
    }

    private function normalizedIntegerListString(string $value): string
    {
        preg_match_all('/\d+/', $value, $matches);
        $seen = [];

        foreach (($matches[0] ?? []) as $match) {
            $numericValue = (int) $match;
            if ($numericValue <= 0 || array_key_exists($numericValue, $seen)) {
                continue;
            }

            $seen[$numericValue] = (string) $numericValue;
        }

        return implode(',', array_values($seen));
    }

    private function trimmedName(string $value): string
    {
        $resolved = trim($value);
        if ($resolved === '') {
            $resolved = (string) Config::get('boss.defaults.boss_name', '活动Boss');
        }

        if (function_exists('mb_substr')) {
            return mb_substr($resolved, 0, 120);
        }

        return substr($resolved, 0, 120);
    }
}