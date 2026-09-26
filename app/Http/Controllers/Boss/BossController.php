<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Boss;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Boss\BossRepository;
use Acme\Panel\Domain\Boss\BossConfigTransferService;
use Acme\Panel\Domain\Boss\BossTierOptions;
use Acme\Panel\Domain\Boss\RewardPoolSimulator;
use Acme\Panel\Domain\Support\ScheduleWindows;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\SoapCommandRunner;
use RuntimeException;
use Throwable;

class BossController extends Controller
{
    /**
     * normalizeExtPayload 抛出的「可以直接给用户看」的错误前缀。
     * 其余异常一律用统一文案返回，避免把 SQL / 内部细节暴露到面板上。
     */
    private const USER_FACING_ERROR_PREFIX = 'user-facing: ';

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
        $dashboard = $this->repo()->dashboard($eventLimit, $contributorLimit);
        $config = is_array($dashboard['config'] ?? null) ? $dashboard['config'] : [];
        $dataSource = $this->repo()->dataSource();

        return $this->pageView('boss.index', $this->serverViewData([
            'boss_dashboard' => $dashboard,
            'boss_options' => [
                'presets' => $this->presetOptions(),
                'difficulties' => $this->difficultyOptions(),
                'random_modes' => $this->randomModeOptions(),
                'tiers' => $this->tierPayload($config),
                'supported' => $this->repo()->serverSupported(),
            ],
            'boss_ext' => $this->extViewData($dashboard),
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
                'intro_hint' => __('app.boss.intro_hint'),
                'note' => __('app.boss.scope_note', [
                    'server' => (string) ($server['name'] ?? ''),
                    'database' => (string) $dataSource['database'] . ' (state_key=' . (string) $dataSource['runtime_key'] . ')',
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

        if (!$this->repo()->serverSupported()) {
            return $this->json([
                'success' => false,
                'message' => $this->serverNotSupportedMessage(),
            ], 422);
        }

        $action = $this->normalizedEnum(
            $request,
            'action',
            ['spawn', 'preset', 'difficulty', 'rebase', 'config_reload', 'kill', 'clear'],
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
        $result = $this->runBossCommand($command);

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

        if (!$this->repo()->serverSupported()) {
            return $this->json([
                'success' => false,
                'message' => $this->serverNotSupportedMessage(),
            ], 422);
        }

        $defaults = (array) Config::get('boss.defaults', []);
        $bossName = $this->trimmedName(
            $this->normalizedString(
                $request,
                'boss_name',
                (string) ($defaults['boss_name'] ?? '活动Boss')
            )
        );

        
        $submittedEntry = trim((string) $request->input('boss_entry', ''));
        $resolvedEntry = BossTierOptions::resolveEntry($submittedEntry);
        $entryFallback = $submittedEntry !== '' && (int) $submittedEntry !== $resolvedEntry;

        $config = [
            'boss_entry' => $resolvedEntry,
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
            'spawn_points_text' => $this->normalizedSpawnPointsText(
                $this->normalizedString(
                    $request,
                    'spawn_points_text',
                    (string) ($defaults['spawn_points_text'] ?? '')
                )
            ),
        ];

        if ($config['minion_count_max'] < $config['minion_count_min']) {
            $config['minion_count_max'] = $config['minion_count_min'];
        }

        // 未提交 = 不改（见 onlySubmittedFields 的说明：面板表单总是提交全部字段）
        $config = $this->onlySubmittedFields($request, $config, [
            'boss_scale_scaled' => 'boss_scale',
            'boss_health_multiplier_scaled' => 'boss_health_multiplier',
            'ally_health_multiplier_scaled' => 'ally_health_multiplier',
        ]);

        // 一个字段都没提交：拒绝，而不是把整行按现值重写一遍（避免空请求触发一次无意义的热加载）
        if ($config === []) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.nothing_to_save'),
            ], 422);
        }

        try {
            $savedConfig = $this->repo()->saveConfig($config);
        } catch (Throwable $exception) {
            $message = $exception->getMessage() === 'boss_config_storage_missing'
                ? Lang::get('app.boss.errors.config_storage_missing')
                : Lang::get('app.boss.errors.config_save_failed');

            return $this->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        try {
            $reloadResult = $this->runBossCommand('.boss config reload');
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
                'entry_fallback' => $entryFallback,
                'reload_success' => $reloadResult['success'],
                'config' => $savedConfig,
                'reload' => $reloadResult,
            ],
        ], $reloadResult['success'] ? 200 : 422);
    }

    // 扩展配置（ac_eluna.boss_activity_config_ext）的表单数据：

    private function extViewData(array $dashboard): array
    {
        $config = is_array($dashboard['ext'] ?? null) ? $dashboard['ext'] : [];
        $mainConfig = is_array($dashboard['config'] ?? null) ? $dashboard['config'] : [];
        $scheduleEnabled = ((int) ($config['activity_schedule_enabled'] ?? 0)) === 1;

        
        $defaults = (array) Config::get('boss.ext_defaults', []);
        $changed = [];
        foreach ($defaults as $column => $defaultValue) {
            if (!array_key_exists($column, $config)) {
                continue;
            }
            $current = $config[$column];
            $normalizedDefault = is_bool($defaultValue) ? ($defaultValue ? 1 : 0) : $defaultValue;
            if (is_scalar($current) && is_scalar($normalizedDefault)) {
                if ((string) $current !== (string) $normalizedDefault) {
                    $changed[$column] = true;
                }
                continue;
            }
            if ($current != $normalizedDefault) {
                $changed[$column] = true;
            }
        }

        return [
            'config' => $config,
            'tabs' => (array) Config::get('boss.ext_tabs', []),
            'fields' => (array) Config::get('boss.ext_fields', []),
            'available' => $this->repo()->extConfigAvailable(),
            
            'presets' => $this->presetOptions(),
            
            'item_names' => $this->extItemNames(is_array($config) ? $config : []),
            
            'defaults' => $defaults,
            'changed' => $changed,
            
            
            'reward_params' => [
                'values' => [
                    'random_reward_mode' => (string) ($mainConfig['random_reward_mode'] ?? 'weighted'),
                    'participation_range' => (int) ($mainConfig['participation_range'] ?? 80),
                    'damage_weight' => (int) ($mainConfig['damage_weight'] ?? 100),
                    'healing_weight' => (int) ($mainConfig['healing_weight'] ?? 80),
                    'threat_weight' => (int) ($mainConfig['threat_weight'] ?? 35),
                    'presence_weight' => (int) ($mainConfig['presence_weight'] ?? 10),
                    'kill_weight' => (int) ($mainConfig['kill_weight'] ?? 3),
                ],
                'modes' => $this->randomModeOptions(),
            ],
            
            'schedule' => [
                'enabled' => $scheduleEnabled,
                'clear_on_close' => ((int) ($config['activity_schedule_clear_on_close'] ?? 0)) === 1,
                'windows' => ScheduleWindows::describe((string) ($config['activity_schedule_windows'] ?? '')),
            ],
        ];
    }

    // 扩展配置校验失败时的返回文案：

    private function extPayloadErrorMessage(Throwable $exception): string
    {
        $message = (string) $exception->getMessage();

        if (str_starts_with($message, self::USER_FACING_ERROR_PREFIX)) {
            return substr($message, strlen(self::USER_FACING_ERROR_PREFIX));
        }

        return Lang::get('app.boss.errors.ext_save_failed');
    }

    /**
     * 保存扩展配置（脚本私有列），随后热加载。
     *
     * 与主表保存的差别：这里用 INSERT ... ON DUPLICATE KEY UPDATE（脚本新增的列不会被 reset），
     * 空值语义也按 Lua 侧的约定处理：未提交的字段不改（normalizeExtPayload），
     * keep_default_when_empty 的字段提交空值也不改。
     */
    public function apiExtConfigSave(Request $request): Response
    {
        $this->requireActionCapability();
        $this->maybeSwitchServer($request);

        if (!$this->repo()->serverSupported()) {
            return $this->json([
                'success' => false,
                'message' => $this->serverNotSupportedMessage(),
            ], 422);
        }

        try {
            $config = $this->normalizeExtPayload($request);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => $this->extPayloadErrorMessage($exception),
            ], 422);
        }

        // 一个字段都没提交：拒绝，而不是把整行按现值重写一遍（避免空请求触发一次无意义的热加载）
        if ($config === []) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.nothing_to_save'),
            ], 422);
        }

        try {
            $savedConfig = $this->repo()->saveExtConfig($config);
        } catch (Throwable $exception) {
            $message = $exception->getMessage() === 'boss_ext_storage_missing'
                ? Lang::get('app.boss.errors.ext_storage_missing')
                : Lang::get('app.boss.errors.ext_save_failed');

            return $this->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        try {
            $reloadResult = $this->runBossCommand('.boss config reload');
        } catch (Throwable $exception) {
            $reloadResult = [
                'success' => false,
                'message' => $exception->getMessage(),
                'output' => '',
                'execution' => [],
            ];
        }

        Audit::log('boss', 'save_ext_config', 'boss_activity_config_ext', [
            'server_id' => ServerContext::currentId(),
            'fields' => count($savedConfig),
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
                ? Lang::get('app.boss.feedback.ext_saved')
                : Lang::get('app.boss.feedback.ext_saved_reload_failed', [
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

    /**
     * 跨区复制扩展配置（可选连主表配置一起复制）。
     *
     * 多区部署时最烦的是"每个区手改一遍"：这里把**本区**（源）的配置写到目标区，
     * 只写请求里点名的分组（面板的 upsert 语义：未提交的列保持目标区现值），
     * 写完切到目标区发一次 .boss config reload，再切回本区。
     */
    public function apiExtConfigCopy(Request $request): Response
    {
        $this->requireActionCapability();
        $this->maybeSwitchServer($request);

        $servers = ServerContext::list();
        $currentId = ServerContext::currentId();
        $targetId = $this->boundedInt($request, 'target_server', -1, -1, 1000000);

        if ($targetId < 0 || !isset($servers[$targetId]) || $targetId === $currentId) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.copy_target_invalid'),
            ], 422);
        }

        
        $rawGroups = $request->input('groups', []);
        $groups = [];
        if (is_array($rawGroups)) {
            foreach ($rawGroups as $group) {
                $group = trim((string) $group);
                if ($group !== '') {
                    $groups[] = $group;
                }
            }
        } elseif (is_string($rawGroups) && trim($rawGroups) !== '') {
            foreach (preg_split('/[\s,;]+/', $rawGroups) ?: [] as $group) {
                $group = trim((string) $group);
                if ($group !== '') {
                    $groups[] = $group;
                }
            }
        }

        $includeMain = $this->normalizedBoolFlag($request, 'include_main_config');

        
        $targetRepo = null;
        ServerContext::set($targetId);
        try {
            $targetRepo = new BossRepository();
        } finally {
            ServerContext::set($currentId);
        }

        $service = new BossConfigTransferService($this->repo(), $targetRepo);
        try {
            $result = $service->copyExt($groups, $includeMain);
        } catch (Throwable $exception) {
            $result = [
                'ok' => false,
                'ext_columns' => 0,
                'main_columns' => 0,
                'skipped' => [],
                'warnings' => [$exception->getMessage()],
            ];
        }

        
        $reloadResult = ['success' => false, 'message' => '', 'output' => '', 'execution' => []];
        if (!empty($result['ok'])) {
            ServerContext::set($targetId);
            try {
                $reloadResult = $this->runBossCommand('.boss config reload');
            } catch (Throwable $exception) {
                $reloadResult['message'] = $exception->getMessage();
            } finally {
                ServerContext::set($currentId);
            }
        }

        Audit::log('boss', 'copy_ext_config', 'boss_activity_config_ext', [
            'server_id' => $currentId,
            'target_server_id' => $targetId,
            'groups' => $groups,
            'include_main_config' => $includeMain,
            'ext_columns' => (int) ($result['ext_columns'] ?? 0),
            'main_columns' => (int) ($result['main_columns'] ?? 0),
            'ok' => !empty($result['ok']),
        ]);

        $targetName = trim((string) ($servers[$targetId]['name'] ?? ''));
        $success = !empty($result['ok']) && !empty($reloadResult['success']);

        return $this->json([
            'success' => $success,
            'message' => $success
                ? Lang::get('app.boss.feedback.ext_copied', [
                    'server' => $targetName !== '' ? $targetName : (string) $targetId,
                    'columns' => (string) ((int) ($result['ext_columns'] ?? 0) + (int) ($result['main_columns'] ?? 0)),
                ])
                : Lang::get('app.boss.errors.copy_failed'),
            'payload' => [
                'result' => $result,
                'reload' => $reloadResult,
                'target_server_id' => $targetId,
                'target_server_name' => $targetName,
            ],
        ], $success ? 200 : 422);
    }

    // 奖池模拟：用当前配置 + 最近一次击杀的真实参战名单，按与 boss.lua 相同的算法跑 N 轮，

    public function apiRewardSimulate(Request $request): Response
    {
        $this->requireDashboardCapability();
        $this->maybeSwitchServer($request);

        $rounds = $this->boundedInt(
            $request,
            'rounds',
            (int) Config::get('boss.simulate_rounds', 400),
            1,
            5000
        );
        $seed = $this->boundedInt($request, 'seed', random_int(1, 1000000), 1, 2000000000);

        $dashboard = $this->repo()->dashboard(0, 0);
        $extConfig = is_array($dashboard['ext'] ?? null) ? $dashboard['ext'] : [];
        $mainConfig = is_array($dashboard['config'] ?? null) ? $dashboard['config'] : [];

        // 参战名单：请求里可以带（GM 手填/测试），否则用最近一次击杀的真实快照
        $roster = $this->repo()->latestKillRoster(40);
        $rosterSource = 'latest_kill';
        $participantsRequest = $request->input('participants');
        if (is_string($participantsRequest) && trim($participantsRequest) !== '') {
            $decoded = json_decode($participantsRequest, true);
            if (is_array($decoded) && $decoded !== []) {
                $roster = $decoded;
                $rosterSource = 'request';
            }
        }

        $participants = [];
        foreach ($roster as $member) {
            if (!is_array($member)) {
                continue;
            }
            $participants[] = [
                'guid' => (int) ($member['guid'] ?? 0),
                'name' => (string) ($member['name'] ?? ''),
                'class_id' => (int) ($member['class_id'] ?? $member['classId'] ?? 0),
                'level' => (int) ($member['level'] ?? 80),
                'damage' => (int) ($member['damage'] ?? $member['damage_done'] ?? 0),
                'healing' => (int) ($member['healing'] ?? $member['healing_done'] ?? 0),
                'threat' => (int) ($member['threat'] ?? $member['threat_samples'] ?? 0),
                'presence' => (int) ($member['presence'] ?? $member['presence_samples'] ?? 0),
                'is_killer' => !empty($member['is_killer']) || !empty($member['was_killer']),
            ];
        }

        $simulator = new RewardPoolSimulator();
        try {
            $report = $simulator->simulate(
                $extConfig,
                $participants,
                $this->parseClassItemMap((string) ($extConfig['class_reward_items_text'] ?? '')),
                [
                    'damage' => (int) ($mainConfig['damage_weight'] ?? 100),
                    'healing' => (int) ($mainConfig['healing_weight'] ?? 80),
                    'threat' => (int) ($mainConfig['threat_weight'] ?? 35),
                    'presence' => (int) ($mainConfig['presence_weight'] ?? 10),
                    'kill' => (int) ($mainConfig['kill_weight'] ?? 3),
                    'mode' => (string) ($mainConfig['random_reward_mode'] ?? 'weighted'),
                ],
                $rounds,
                $seed
            );
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.simulate_failed', ['message' => $exception->getMessage()]),
            ], 422);
        }

        $report['participants_source'] = $rosterSource;
        $report['seed'] = $seed;

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.boss.feedback.simulated', ['rounds' => (string) $rounds]),
            'payload' => ['report' => $report],
        ]);
    }

    // 「职业过滤映射」自动补全：按当前 6 个奖池里的物品，把核心已经限制好职业的

    public function apiClassMapAutofill(Request $request): Response
    {
        $this->requireActionCapability();
        $this->maybeSwitchServer($request);

        $dashboard = $this->repo()->dashboard(0, 0);
        $extConfig = is_array($dashboard['ext'] ?? null) ? $dashboard['ext'] : [];
        $existingMap = $this->parseClassItemMap((string) ($extConfig['class_reward_items_text'] ?? ''));

        
        $itemIds = [];
        for ($index = 1; $index <= 6; $index++) {
            foreach (preg_split('/[\s,;]+/', (string) ($extConfig['reward_pool_' . $index . '_items_text'] ?? '')) ?: [] as $token) {
                $itemId = (int) trim((string) $token);
                if ($itemId > 0) {
                    $itemIds[$itemId] = true;
                }
            }
        }
        $itemIds = array_keys($itemIds);
        if ($itemIds === []) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.boss.errors.class_map_no_items'),
            ], 422);
        }

        $restrictions = $this->repo()->itemClassRestrictions($itemIds);

        
        $mappedClasses = [];
        foreach ($existingMap as $classId => $items) {
            foreach ($items as $itemId) {
                $mappedClasses[$itemId][] = (int) $classId;
            }
        }

        $classBitToId = [1 => 1, 2 => 2, 4 => 3, 8 => 4, 16 => 5, 32 => 6, 64 => 7, 128 => 8, 256 => 9, 1024 => 11];
        $classOrder = [1, 2, 3, 4, 5, 6, 7, 8, 9, 11];
        $allClassCount = count($classOrder);
        $merged = $existingMap;
        $fromCore = [];
        $needsManual = [];
        $unknownItems = [];

        foreach ($itemIds as $itemId) {
            if (!empty($mappedClasses[$itemId])) {
                continue; // 手工填过 → 保持不动
            }

            $row = $restrictions[$itemId] ?? null;
            if ($row === null) {
                $unknownItems[] = $itemId;
                continue;
            }

            $mask = (int) $row['allowable_class'];
            $classes = [];
            if ($mask !== -1 && $mask !== 0) {
                foreach ($classBitToId as $bit => $classId) {
                    if (($mask & $bit) !== 0) {
                        $classes[] = $classId;
                    }
                }
            }

            
            
            if ($classes === [] || count($classes) >= $allClassCount) {
                $needsManual[] = ['id' => $itemId, 'name' => (string) $row['name']];
                continue;
            }

            foreach ($classes as $classId) {
                $merged[$classId] = $merged[$classId] ?? [];
                if (!in_array($itemId, $merged[$classId], true)) {
                    $merged[$classId][] = $itemId;
                }
            }
            $fromCore[] = ['id' => $itemId, 'name' => (string) $row['name'], 'classes' => $classes];
        }

        // 生成文本：固定职业顺序 + 每个职业内按物品ID升序（diff 友好）
        $lines = [];
        foreach ($classOrder as $classId) {
            $items = $merged[$classId] ?? [];
            if ($items === []) {
                continue;
            }
            $items = array_values(array_unique(array_map('intval', $items)));
            sort($items);
            $lines[] = $classId . '=' . implode(',', $items);
        }

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.boss.feedback.class_map_filled', [
                'core' => (string) count($fromCore),
                'manual' => (string) count($needsManual),
            ]),
            'payload' => [
                'text' => implode("\n", $lines),
                'report' => [
                    'from_core' => $fromCore,
                    'kept_manual' => array_keys(array_filter($mappedClasses, static fn (array $classes): bool => $classes !== [])),
                    'needs_manual' => $needsManual,
                    'unknown_items' => $unknownItems,
                ],
            ],
        ]);
    }

    /**
     * ext 里的「职业奖励池」映射（class_reward_items_text）：
     * 每行 "职业ID=物品ID,物品ID" → [classId => [itemId, ...]]，与 boss.lua 的解析保持一致。
     */
    private function parseClassItemMap(string $text): array
    {
        $map = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }

            [$classId, $itemList] = explode('=', $line, 2);
            $classId = (int) trim($classId);
            if ($classId <= 0) {
                continue;
            }

            $items = $map[$classId] ?? [];
            foreach (preg_split('/[\s,;]+/', $itemList) ?: [] as $itemId) {
                $itemId = (int) trim((string) $itemId);
                if ($itemId > 0 && !in_array($itemId, $items, true)) {
                    $items[] = $itemId;
                }
            }
            $map[$classId] = $items;
        }

        return $map;
    }

    /**
     * 按 schema 归一化扩展配置表单：逐字段按 kind 处理，只保留能安全落库的值。
     *
     * 「未提交 = 不改」是这里的硬规则：面板表单（含开关的 hidden 0）永远提交全部字段，
     * 所以跳过未提交的字段不会影响正常保存；而任何只提交部分字段的调用方（脚本 / curl /
     * 半截表单）都不会再把其余字段写成默认值 —— 2026-09-23 线上扩展配置整行的文本被清空、
     * 整数被写成最小值，就是"缺字段按默认值提交"造成的。
     */
    private function normalizeExtPayload(Request $request): array
    {
        $payload = [];

        foreach ($this->repo()->extFieldSchema() as $name => $spec) {
            if ($request->input($name) === null) {
                continue;
            }

            $kind = (string) ($spec['kind'] ?? 'text');

            
            if ($kind === 'schedule_windows') {
                $payload[$name] = $this->normalizedScheduleWindows(
                    (string) $request->input($name, ''),
                    (int) ($spec['maxlength'] ?? 255)
                );
                continue;
            }

            if ($kind === 'bool') {
                $payload[$name] = $this->normalizedBoolFlag($request, $name) ? 1 : 0;
                continue;
            }

            
            if ($kind === 'preset_multi') {
                $payload[$name] = $this->normalizedPresetPool(
                    $request->input($name),
                    (int) ($spec['maxlength'] ?? 255)
                );
                continue;
            }

            
            if ($kind === 'enum') {
                $enumOptions = [];
                foreach ((array) ($spec['options'] ?? []) as $option) {
                    $option = trim((string) $option);
                    if ($option !== '') {
                        $enumOptions[] = $option;
                    }
                }

                $payload[$name] = $this->normalizedEnumValue(
                    (string) $request->input($name, ''),
                    $enumOptions,
                    $enumOptions[0] ?? ''
                );
                continue;
            }

            if ($kind === 'int') {
                $min = (int) ($spec['min'] ?? 0);
                $max = (int) ($spec['max'] ?? 2000000000);
                $payload[$name] = $this->boundedInt($request, $name, $min, $min, $max);
                continue;
            }

            $raw = $request->input($name, '');
            $text = is_string($raw) ? $raw : (string) ($raw ?? '');

            switch ($kind) {
                case 'lines':
                    $value = $this->normalizedLineList($text);
                    break;
                case 'keyedlines':
                    $value = $this->normalizedKeyedLines($text);
                    break;
                case 'keyedintlist':
                    $value = $this->normalizedKeyedIntegerLists($text);
                    break;
                case 'intlist':
                    $value = $this->normalizedIntegerListString($text);
                    break;
                case 'itemlist':
                    
                    $value = $this->normalizedIntegerListString($text, (int) ($spec['max_items'] ?? 300));
                    break;
                default:
                    $value = $this->limitedSingleLine($text, (int) ($spec['maxlength'] ?? 255));
            }

            
            
            if (!empty($spec['keep_default_when_empty']) && $value === '') {
                continue;
            }

            $payload[$name] = $value;
        }

        return $payload;
    }

    /**
     * 只保留「请求里确实提交过」的字段 —— 未提交的字段交给仓储保留数据库现值。
     *
     * 为什么是硬规则：面板表单总会提交全部字段（扩展配置的开关还带一个 hidden 0，
     * 主表配置的开关由 boss.js 显式补 0/1），所以正常保存完全不受影响；而"只提交一个字段"
     * 的调用方以前会把其余字段写成各自的默认值（bool→0、int→最小值、text→空串）。
     * 2026-09-23 线上 boss_activity_config_ext 整行文本被清空、整数被写成最小值，
     * 就是这么来的（已从 binlog 逐列还原）。
     *
     * @param array<string,mixed> $config     归一化后的候选值（键 = 数据库列名）
     * @param array<string,string> $inputNames 列名 → 请求字段名（只有缩放列不同名）
     * @return array<string,mixed>
     */
    private function onlySubmittedFields(Request $request, array $config, array $inputNames = []): array
    {
        $submitted = [];

        foreach ($config as $key => $value) {
            $inputName = $inputNames[$key] ?? (string) $key;

            if ($request->input($inputName) === null) {
                continue;
            }

            $submitted[$key] = $value;
        }

        return $submitted;
    }

    /**
     * 多行文案：一行一条，去掉空行（喊话/嘲讽允许整体留空 = 不喊）。
     */
    private function normalizedLineList(string $value, int $limit = 200): string
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $row) {
            $row = trim((string) $row);
            if ($row === '')
                continue;

            $lines[] = $row;
            if (count($lines) >= $limit)
                break;
        }

        return implode("\n", $lines);
    }

    // "键=值" 多行（技能名/连招名 → 喊话）：丢掉没有 =、键或值为空、键重复的行。

    private function normalizedKeyedLines(string $value, int $limit = 200): string
    {
        $lines = [];
        $seen = [];

        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $row) {
            $row = trim((string) $row);
            if ($row === '' || !str_contains($row, '='))
                continue;

            [$key, $rest] = explode('=', $row, 2);
            $key = trim($key);
            $rest = trim($rest);

            if ($key === '' || $rest === '' || isset($seen[$key]))
                continue;

            $seen[$key] = true;
            $lines[] = $key . '=' . $rest;

            if (count($lines) >= $limit)
                break;
        }

        return implode("\n", $lines);
    }

    // "职业ID=物品ID,物品ID" 多行：键取正整数，值走整数列表归一。

    private function normalizedKeyedIntegerLists(string $value, int $limit = 100): string
    {
        $lines = [];
        $seen = [];

        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $row) {
            $row = trim((string) $row);
            if ($row === '' || !str_contains($row, '='))
                continue;

            [$key, $rest] = explode('=', $row, 2);
            $key = (int) trim($key);
            if ($key <= 0 || isset($seen[$key]))
                continue;

            $list = $this->normalizedIntegerListString($rest);
            if ($list === '')
                continue;

            $seen[$key] = true;
            $lines[] = $key . '=' . $list;

            if (count($lines) >= $limit)
                break;
        }

        return implode("\n", $lines);
    }

    /**
     * 单行文本：换行折成空格（列是 VARCHAR），按列宽截断。
     * 不做 trim：Lua 默认的生成喊话带前导空格，面板保存应保持原样。
     */
    private function limitedSingleLine(string $value, int $maxLength): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        if ($maxLength <= 0)
            return $value;

        if (function_exists('mb_substr'))
            return mb_substr($value, 0, $maxLength);

        return substr($value, 0, $maxLength);
    }

    // 「定时启停」时间段：面板侧先校验并归一化（写库的是规范写法，Lua 只负责执行）。

    private function normalizedScheduleWindows(string $value, int $maxLength = 255): string
    {
        try {
            $normalized = ScheduleWindows::normalize($value);
        } catch (RuntimeException $exception) {
            throw new RuntimeException(self::USER_FACING_ERROR_PREFIX . Lang::get(
                'app.boss.errors.schedule_invalid',
                ['token' => $exception->getMessage()]
            ));
        }

        
        if ($maxLength > 0 && mb_strlen($normalized) > $maxLength) {
            throw new RuntimeException(self::USER_FACING_ERROR_PREFIX . Lang::get(
                'app.boss.errors.schedule_too_long',
                ['max' => (string) $maxLength, 'length' => (string) mb_strlen($normalized)]
            ));
        }

        return $normalized;
    }

    /**
     * Boss SOAP 调用统一入口：强制 strict_marker，避免「命令没到游戏」被当成成功。
     */
    private function runBossCommand(string $command): array
    {
        return SoapCommandRunner::execute($command, [
            'server_id' => ServerContext::currentId(),
            'strict_marker' => true,
        ]);
    }

    private function serverNotSupportedMessage(): string
    {
        $server = ServerContext::server();
        $name = trim((string) ($server['name'] ?? ''));

        return Lang::get('app.boss.warnings.server_not_supported', [
            'server' => $name !== '' ? $name : (string) ServerContext::currentId(),
        ]);
    }

    // 难度档位 payload：每档的倍率与当前倍率下的预估血量，供视图与前端即时换算。

    private function tierPayload(array $config): array
    {
        $currentScaled = $this->resolveCurrentHealthMultiplierScaled($config);
        $currentEntry = BossTierOptions::resolveEntry($config['boss_entry'] ?? null);
        $labels = BossTierOptions::labels();

        $items = [];

        foreach (BossTierOptions::entries() as $entry => $tier) {
            $items[] = [
                'entry' => $entry,
                'key' => (string) ($tier['key'] ?? ''),
                'label' => (string) ($labels[$entry] ?? (string) $entry),
                'health_modifier' => (float) ($tier['health_modifier'] ?? 0),
                'damage_modifier' => (float) ($tier['damage_modifier'] ?? 0),
                'estimated_hp' => BossTierOptions::estimateHp($entry, $currentScaled),
            ];
        }

        return [
            'items' => $items,
            'base_hp' => (int) Config::get('boss.tier_base_hp', 13945),
            'decimal_scale' => max(1, (int) Config::get('boss.decimal_scale', 100)),
            'current_tier_entry' => $currentEntry,
            'current_health_multiplier_scaled' => $currentScaled,
            'current_estimated_hp' => BossTierOptions::estimateHp($currentEntry, $currentScaled),
            'default_tier_entry' => BossTierOptions::defaultEntry(),
        ];
    }

    // 当前生效的血量倍率（缩放值）：以数据库现值为准，缺失时回落到默认值。

    private function resolveCurrentHealthMultiplierScaled(array $config): int
    {
        $display = $config['boss_health_multiplier'] ?? null;

        if (is_numeric($display) && $display !== '')
            return BossTierOptions::toScaled($display);

        $scaled = $config['boss_health_multiplier_scaled'] ?? null;
        if (is_numeric($scaled) && $scaled !== '')
            return max(0, (int) $scaled);

        return (int) Config::get('boss.defaults.boss_health_multiplier_scaled', 2000);
    }

    private function buildCommand(string $action, string $value): string
    {
        if ($action === 'spawn')
            return '.boss spawn';

        if ($action === 'rebase')
            return '.boss rebase';

        if ($action === 'config_reload')
            return '.boss config reload';

        if ($action === 'kill')
            return '.boss kill';

        if ($action === 'clear')
            return '.boss clear';

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

    private function normalizedIntegerListString(string $value, int $limit = 0): string
    {
        preg_match_all('/\d+/', $value, $matches);
        $seen = [];

        foreach (($matches[0] ?? []) as $match) {
            $numericValue = (int) $match;
            if ($numericValue <= 0 || array_key_exists($numericValue, $seen)) {
                continue;
            }

            $seen[$numericValue] = (string) $numericValue;

            if ($limit > 0 && count($seen) >= $limit) {
                break;
            }
        }

        return implode(',', array_values($seen));
    }

    /**
     * 枚举字段归一：只接受 $allowed 里列出的取值，其它一律回落到第一个合法值。
     *
     * @param string[] $allowed
     */
    private function normalizedEnumValue(string $value, array $allowed, string $fallback): string
    {
        $value = trim($value);
        if ($allowed === []) {
            return $value;
        }

        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * 奖池奖品（kind=itemlist）的 ID → 物品名 映射：把本区所有 itemlist 字段里的 ID 收集起来
     * 一次性解析，供视图在输入框下面显示"这件奖品是什么"。
     *
     * @param array<string,mixed> $extConfig
     * @return array<int,string> itemId => 物品名（查不到 = "#ID"）
     */
    private function extItemNames(array $extConfig): array
    {
        $ids = [];

        foreach ($this->repo()->extFieldSchema() as $name => $spec) {
            if ((string) ($spec['kind'] ?? 'text') !== 'itemlist') {
                continue;
            }

            foreach (preg_split('/[\s,;]+/', (string) ($extConfig[$name] ?? '')) ?: [] as $token) {
                $itemId = (int) trim((string) $token);
                if ($itemId > 0) {
                    $ids[$itemId] = true;
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        return $this->repo()->itemNames(array_keys($ids));
    }

    /**
     * 「技能池随机」的随机池（kind=preset_multi）：接受预设 key 的数组（表单复选框 name[]）
     * 或逗号/空格分隔的字符串，只保留 boss.preset_values 里存在的 key，
     * 输出按 preset_values 的顺序归一（去重）。
     *
     * 空串是**合法值**（Lua 侧语义 = 使用全部预设），所以这里不能套用其它字段
     * "空 = 不改"的规则；池子列宽有限，超长直接拒绝保存而不是悄悄截断。
     */
    private function normalizedPresetPool($value, int $maxLength = 255): string
    {
        $raw = is_array($value) ? $value : (preg_split('/[\s,;]+/', (string) $value) ?: []);
        $submitted = [];

        foreach ($raw as $token) {
            $token = strtolower(trim((string) $token));
            if ($token !== '') {
                $submitted[$token] = true;
            }
        }

        $chosen = [];
        foreach ((array) Config::get('boss.preset_values', []) as $preset) {
            $preset = trim((string) $preset);
            if ($preset === '') {
                continue;
            }

            if (isset($submitted[strtolower($preset)])) {
                $chosen[] = $preset;
            }
        }

        $text = implode(',', $chosen);

        if ($maxLength > 0 && strlen($text) > $maxLength) {
            throw new RuntimeException(self::USER_FACING_ERROR_PREFIX . Lang::get(
                'app.boss.errors.preset_pool_too_long',
                ['max' => (string) $maxLength, 'length' => (string) strlen($text)]
            ));
        }

        return $text;
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

    private function normalizedSpawnPointsText(string $value): string
    {
        $rows = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];
        $normalized = [];

        foreach ($rows as $row) {
            $parts = preg_split('/\s*,\s*/', trim((string) $row)) ?: [];
            if (count($parts) !== 4) {
                continue;
            }

            if (!is_numeric($parts[0]) || !is_numeric($parts[1]) || !is_numeric($parts[2]) || !is_numeric($parts[3])) {
                continue;
            }

            $mapId = max(0, min(2000000, (int) round((float) $parts[0])));
            $x = (float) $parts[1];
            $y = (float) $parts[2];
            $z = (float) $parts[3];

            $normalized[] = $mapId . ','
                . number_format($x, 4, '.', '') . ','
                . number_format($y, 4, '.', '') . ','
                . number_format($z, 4, '.', '');

            if (count($normalized) >= 100) {
                break;
            }
        }

        return implode("\n", $normalized);
    }
}