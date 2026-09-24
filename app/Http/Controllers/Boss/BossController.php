<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Boss;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Boss\BossRepository;
use Acme\Panel\Domain\Boss\BossTierOptions;
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

        // 难度档位：只接受 config/boss.php tiers 里的 entry，不在列表内回落到 default_tier_entry。
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

        if ($config['gold_max_copper'] < $config['gold_min_copper']) {
            $config['gold_max_copper'] = $config['gold_min_copper'];
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

    /**
     * 扩展配置（ac_eluna.boss_activity_config_ext）的表单数据：
     * 当前值 + 字段 schema + 二级 Tab 分组；schema 与保存校验共用一份定义。
     */
    private function extViewData(array $dashboard): array
    {
        $config = is_array($dashboard['ext'] ?? null) ? $dashboard['ext'] : [];
        $scheduleEnabled = ((int) ($config['activity_schedule_enabled'] ?? 0)) === 1;

        return [
            'config' => $config,
            'tabs' => (array) Config::get('boss.ext_tabs', []),
            'fields' => (array) Config::get('boss.ext_fields', []),
            'available' => $this->repo()->extConfigAvailable(),
            // 定时启停：面板侧展示用（解析出的时间段清单；真正到点开关在 Lua 的 tick 里）
            'schedule' => [
                'enabled' => $scheduleEnabled,
                'clear_on_close' => ((int) ($config['activity_schedule_clear_on_close'] ?? 0)) === 1,
                'windows' => ScheduleWindows::describe((string) ($config['activity_schedule_windows'] ?? '')),
            ],
        ];
    }

    /**
     * 扩展配置校验失败时的返回文案：
     * 只有带 USER_FACING_ERROR_PREFIX 的异常（时间段写错这类可自助修正的输入问题）
     * 才把原文回给面板，其余一律用统一提示。
     */
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
     * 空值语义也按 Lua 侧的约定处理（keep_default_when_empty 的字段留空 = 不改）。
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
     * 按 schema 归一化扩展配置表单：逐字段按 kind 处理，只保留能安全落库的值。
     */
    private function normalizeExtPayload(Request $request): array
    {
        $payload = [];

        foreach ($this->repo()->extFieldSchema() as $name => $spec) {
            $kind = (string) ($spec['kind'] ?? 'text');

            // 时间段字段比普通文本多一层语义：**没提交 = 不改**，提交空串 = 清空时间段。
            // （面板表单永远会带上这个字段，所以"清空"照常生效；只发部分字段的调用方
            //   不会因为少带一个字段就把线上的时间段清掉。）
            if ($kind === 'schedule_windows') {
                if ($request->input($name) === null) {
                    continue;
                }

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
                default:
                    $value = $this->limitedSingleLine($text, (int) ($spec['maxlength'] ?? 255));
            }

            // 留空 = 不改（Lua 侧对空值会回退到脚本默认值，写空只会让两边显示不一致）
            if (!empty($spec['keep_default_when_empty']) && $value === '') {
                continue;
            }

            $payload[$name] = $value;
        }

        return $payload;
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

    /**
     * "键=值" 多行（技能名/连招名 → 喊话）：丢掉没有 =、键或值为空、键重复的行。
     */
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

    /**
     * "职业ID=物品ID,物品ID" 多行：键取正整数，值走整数列表归一。
     */
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

    /**
     * 「定时启停」时间段：面板侧先校验并归一化（写库的是规范写法，Lua 只负责执行）。
     *
     * 非法片段（例如 "25:00-26:00" 或写错星期）直接拒绝保存，并把看不懂的那一段回给用户 ——
     * 交给 Lua 只会变成"静默不生效"，用户完全不知道为什么到点没开。
     */
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

        // 不截断：时间段是有语义的，砍一半只会变成"到点不生效"这种最难查的问题
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

    /**
     * 难度档位 payload：每档的倍率与当前倍率下的预估血量，供视图与前端即时换算。
     */
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

    /**
     * 当前生效的血量倍率（缩放值）：以数据库现值为准，缺失时回落到默认值。
     */
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