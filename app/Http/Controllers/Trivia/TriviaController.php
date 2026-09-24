<?php

/**
 * File: app/Http/Controllers/Trivia/TriviaController.php
 * Purpose: "Trivia reward" (聊天答题) page: live state, run controls, settings, question bank,
 *          reward presets and the winner leaderboard.
 *
 * 运行状态与控制走 SOAP（.trivia api / start / stop / …），配置与题库走 ac_eluna 数据表，
 * 两者都改完后由 .trivia reload 让脚本重新读库。
 */

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Trivia;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Core\View;
use Acme\Panel\Domain\Trivia\QuestionTemplate;
use Acme\Panel\Domain\Trivia\ScheduleWindows;
use Acme\Panel\Domain\Trivia\TriviaRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\Paginator;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\SoapCommandRunner;
use Throwable;

class TriviaController extends Controller
{
    private ?TriviaRepository $repo = null;

    private function repo(): TriviaRepository
    {
        if ($this->repo === null) {
            $this->repo = new TriviaRepository();
        }

        return $this->repo;
    }

    private function maybeSwitchServer(Request $request): void
    {
        $this->switchServerAndRebind($request, $this->repo());
    }

    public function index(Request $request): Response
    {
        $this->requireCapability('trivia.view');
        $this->maybeSwitchServer($request);

        $limit = $this->boundedInt($request, 'limit', (int) Config::get('trivia.page_size', 30), 10, 200);
        $state = $this->questionListState($request, $limit);
        $schema = $this->repo()->schemaStatus();

        $questions = new Paginator([], 0, 1, $limit);
        $questionStats = ['total' => 0, 'enabled' => 0, 'disabled' => 0, 'with_reward' => 0];
        $winnerPager = new Paginator([], 0, 1, (int) Config::get('trivia.winner_limit', 30));
        $winnerStats = ['players' => 0, 'wins' => 0, 'money' => 0, 'latest' => 0];
        $error = null;

        if ($schema['ready']) {
            try {
                $questions = $this->repo()->listQuestions($state['filters'], $state['page'], $limit);
                $questionStats = $this->repo()->questionStats();
                $winnerPager = $this->repo()->listWinners([], 1, (int) Config::get('trivia.winner_limit', 30));
                $winnerStats = $this->repo()->winnerStats();
            } catch (Throwable $exception) {
                $error = Lang::get('app.trivia.errors.load_failed');
            }
        } else {
            $error = Lang::get('app.trivia.errors.schema_missing', ['tables' => implode(', ', $schema['missing'])]);
        }

        $settings = $this->repo()->settings();
        $presets = $this->repo()->listPresets();

        return $this->pageView('trivia.index', $this->serverViewData([
            'trivia_schema' => $schema,
            'trivia_settings' => $this->settingsViewData($settings),
            'trivia_questions' => $questions,
            'trivia_question_stats' => $questionStats,
            'trivia_presets' => $presets,
            'trivia_preset_names' => array_values(array_map(static fn(array $p): string => (string) $p['name'], $presets)),
            'trivia_preset_items' => $this->presetItemNames($presets, $questions->items),
            'trivia_winners' => $winnerPager,
            'trivia_winner_stats' => $winnerStats,
            'trivia_status' => $this->liveStatus(),
            'trivia_options' => $this->optionPayload(),
            'trivia_filters' => $state['filters'],
            'trivia_page' => $state['page'],
            'trivia_limit' => $limit,
            'trivia_error' => $error,
            'trivia_db_name' => $this->repo()->customDbName(),
        ]), [
            'module' => 'trivia',
            'capabilities' => $this->pageCapabilitiesForView(),
            'header' => [
                'intro' => __('app.trivia.intro'),
                'note' => __('app.trivia.scope_note', [
                    'server' => (string) (ServerContext::server()['name'] ?? ''),
                    'database' => $this->repo()->customDbName(),
                ]),
            ],
            'meta' => [
                'title' => __('app.trivia.page_title'),
            ],
        ]);
    }

    /**
     * 页面级能力位（视图与前端都按这个决定按钮是否可点）。
     */
    private function pageCapabilitiesForView(): array
    {
        return [
            'view' => 'trivia.view',
            'control' => 'trivia.control',
            'manage' => 'trivia.manage',
        ];
    }

    // ------------------------------------------------------------------ 只读接口

    public function apiStatus(Request $request): Response
    {
        $this->requireCapability('trivia.view');
        $this->maybeSwitchServer($request);

        $status = $this->liveStatus();

        return $this->json([
            'success' => true,
            'available' => $status['available'],
            'error' => $status['error'],
            'data' => $status['data'],
        ]);
    }

    public function apiQuestions(Request $request): Response
    {
        $this->requireCapability('trivia.view');
        $this->maybeSwitchServer($request);

        $limit = $this->boundedInt($request, 'limit', (int) Config::get('trivia.page_size', 30), 10, 200);
        $state = $this->questionListState($request, $limit);

        $pager = new Paginator([], 0, $state['page'], $limit);
        $stats = ['total' => 0, 'enabled' => 0, 'disabled' => 0, 'with_reward' => 0];

        try {
            $pager = $this->repo()->listQuestions($state['filters'], $state['page'], $limit);
            $stats = $this->repo()->questionStats();
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.load_failed'),
            ], 422);
        }

        return $this->json([
            'success' => true,
            'section' => 'questions',
            'html' => $this->partial('trivia._question_table', [
                'trivia_questions' => $pager,
                'trivia_preset_names' => array_values(array_map(
                    static fn(array $p): string => (string) $p['name'],
                    $this->repo()->listPresets()
                )),
                'trivia_item_names' => $this->presetItemNames([], $pager->items),
                'triviaCapabilities' => $this->pageCapabilities($this->pageCapabilitiesForView()),
            ]),
            'stats' => $stats,
            'total' => $pager->total,
        ]);
    }

    public function apiPresets(Request $request): Response
    {
        $this->requireCapability('trivia.view');
        $this->maybeSwitchServer($request);

        $presets = $this->repo()->listPresets();

        return $this->json([
            'success' => true,
            'section' => 'presets',
            'html' => $this->partial('trivia._preset_table', [
                'trivia_presets' => $presets,
                'trivia_item_names' => $this->presetItemNames($presets, []),
                'triviaCapabilities' => $this->pageCapabilities($this->pageCapabilitiesForView()),
            ]),
            'presets' => $presets,
        ]);
    }

    public function apiWinners(Request $request): Response
    {
        $this->requireCapability('trivia.view');
        $this->maybeSwitchServer($request);

        $limit = $this->boundedInt($request, 'limit', (int) Config::get('trivia.winner_limit', 30), 5, 200);
        $search = $this->normalizedString($request, 'search');
        $page = $this->normalizedPage($request, 'winner_page');

        $pager = new Paginator([], 0, $page, $limit);
        $stats = ['players' => 0, 'wins' => 0, 'money' => 0, 'latest' => 0];
        try {
            $pager = $this->repo()->listWinners(['search' => $search], $page, $limit);
            $stats = $this->repo()->winnerStats();
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.load_failed'),
            ], 422);
        }

        return $this->json([
            'success' => true,
            'section' => 'winners',
            'html' => $this->partial('trivia._winner_table', [
                'trivia_winners' => $pager,
                'triviaCapabilities' => $this->pageCapabilities($this->pageCapabilitiesForView()),
            ]),
            'stats' => $stats,
        ]);
    }

    // ------------------------------------------------------------------ 运行控制

    public function apiAction(Request $request): Response
    {
        $this->requireCapability('trivia.control');
        $this->maybeSwitchServer($request);

        $action = $this->normalizedEnum(
            $request,
            'action',
            ['start', 'stop', 'pause', 'resume', 'enable', 'disable', 'reload'],
            ''
        );

        if ($action === '') {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.invalid_action'),
            ], 422);
        }

        $command = '.trivia ' . $action;
        $questionId = $request->int('index', 0);
        if ($action === 'start' && $questionId > 0) {
            $command .= ' ' . $questionId;
        }

        $result = $this->runTriviaCommand($command);

        Audit::log('trivia', 'action', $action, [
            'server_id' => ServerContext::currentId(),
            'command' => $command,
            'success' => $result['success'],
            'output' => $result['output'] ?? '',
        ]);

        if (!$result['success']) {
            return $this->json([
                'success' => false,
                'message' => $result['message'] !== ''
                    ? $result['message']
                    : Lang::get('app.trivia.errors.command_failed'),
                'payload' => ['output' => $result['output'] ?? ''],
            ], 422);
        }

        return $this->json([
            'success' => true,
            'message' => $result['message'] !== ''
                ? $result['message']
                : Lang::get('app.trivia.feedback.action_done', ['action' => $action]),
            'payload' => [
                'action' => $action,
                'output' => $result['output'] ?? '',
                'status' => $this->liveStatus(),
            ],
        ]);
    }

    // ------------------------------------------------------------------ 设置

    public function apiSettings(Request $request): Response
    {
        $this->requireCapability('trivia.manage');
        $this->maybeSwitchServer($request);

        $schema = $this->repo()->schemaStatus();
        if (!$schema['ready']) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.schema_missing', ['tables' => implode(', ', $schema['missing'])]),
            ], 422);
        }

        try {
            $values = $this->collectSettings($request);
        } catch (\RuntimeException $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.invalid_settings', ['reason' => $exception->getMessage()]),
            ], 422);
        }

        try {
            $this->repo()->saveSettings($values);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.settings_save_failed'),
            ], 422);
        }

        $reload = $this->runTriviaCommand('.trivia reload');

        Audit::log('trivia', 'save_settings', 'trivia_reward_settings', [
            'server_id' => ServerContext::currentId(),
            'keys' => array_keys($values),
            'reload_success' => $reload['success'],
            'reload_output' => $reload['output'] ?? '',
        ]);

        $message = $reload['success']
            ? Lang::get('app.trivia.feedback.settings_saved')
            : Lang::get('app.trivia.feedback.settings_saved_reload_failed', [
                'message' => $this->firstNonEmpty($reload['message'] ?? '', $reload['output'] ?? '', Lang::get('app.trivia.errors.command_failed')),
            ]);

        return $this->json([
            'success' => $reload['success'],
            'message' => $message,
            'payload' => [
                'settings' => $this->repo()->settings(),
                'reload' => $reload,
                'status' => $this->liveStatus(),
            ],
        ], $reload['success'] ? 200 : 422);
    }

    /**
     * 表单 → 数据库列。
     *
     * 约定：**请求里没带的字段保持数据库现值**（只有显式传了才会改）。
     * 这样部分提交（外部 API、以后 UI 少传一个字段）不会静默把设置清成 0/空，
     * 也让"取消勾选"必须由前端显式发 0 —— 视图里每个复选框都配了 hidden 0。
     *
     * @return array<string,mixed>
     */
    private function collectSettings(Request $request): array
    {
        $limits = (array) Config::get('trivia.limits', []);
        $current = $this->repo()->settings();
        $values = [];

        foreach (['interval_seconds', 'answer_seconds', 'remind_every_seconds', 'first_delay_seconds',
                  'min_players_online', 'min_level', 'attempts_per_player', 'min_gm_rank_for_command',
                  'idle_retry_seconds', 'resume_delay_seconds', 'gm_rank_exempt',
                  'sender_guid', 'mail_stationery', 'item_link_locale'] as $column) {
            $range = $limits[$column] ?? [0, 2147483647];
            $default = array_key_exists($column, $current) ? (int) $current[$column] : (int) $range[0];
            if ($request->input($column) === null) {
                $values[$column] = max((int) $range[0], min((int) $range[1], $default));
                continue;
            }
            $values[$column] = max((int) $range[0], min((int) $range[1], $request->int($column, $default)));
        }

        foreach (['enabled', 'paused', 'answer_say', 'answer_yell', 'answer_emote', 'answer_whisper',
                  'allow_number_answer', 'allow_latin_letters', 'allow_text_answer', 'use_builtin_questions',
                  'announce_on_login', 'reply_wrong_answer', 'reply_already_answered',
                  'schedule_enabled', 'debug_log', 'allow_loose_letter', 'ignore_gms'] as $column) {
            if ($request->input($column) === null) {
                if (array_key_exists($column, $current)) {
                    $values[$column] = ((int) $current[$column]) === 1 ? 1 : 0;
                }
                continue;
            }
            $values[$column] = $this->normalizedBoolFlag($request, $column) ? 1 : 0;
        }

        // 定时启停的时间段：面板侧先校验并归一化（写库的是规范写法，Lua 只负责执行）
        if ($request->input('schedule_windows') === null && array_key_exists('schedule_windows', $current)) {
            $values['schedule_windows'] = (string) $current['schedule_windows'];
        } else {
            try {
                $normalized = ScheduleWindows::normalize((string) $request->input('schedule_windows', ''));
            } catch (\RuntimeException $exception) {
                throw new \RuntimeException(Lang::get('app.trivia.errors.schedule_invalid', [
                    'token' => $exception->getMessage(),
                ]));
            }
            $values['schedule_windows'] = mb_substr($normalized, 0, 255);
        }

        // 作答频道：只接受数字 ID（负数 = 自定义频道）
        if ($request->input('answer_channel_ids') === null && array_key_exists('answer_channel_ids', $current)) {
            $values['answer_channel_ids'] = (string) $current['answer_channel_ids'];
        } else {
            $values['answer_channel_ids'] = implode(',', $this->parseIntList((string) $request->input('answer_channel_ids', '')));
        }

        if ($request->input('answer_prefix') === null && array_key_exists('answer_prefix', $current)) {
            $values['answer_prefix'] = (string) $current['answer_prefix'];
        } else {
            $values['answer_prefix'] = mb_substr(trim((string) $request->input('answer_prefix', '')), 0, 8);
        }

        // 选项标号：解析成 token，最多 8 个
        if ($request->input('option_labels') === null && array_key_exists('option_labels', $current)) {
            $values['option_labels'] = (string) $current['option_labels'];
        } else {
            $labels = $this->parseLabelList((string) $request->input('option_labels', ''));
            if (count($labels) < 2 || count($labels) > 8) {
                throw new \RuntimeException(Lang::get('app.trivia.errors.labels_invalid'));
            }
            $values['option_labels'] = implode(',', $labels);
        }

        if ($request->input('option_format') === null && array_key_exists('option_format', $current)) {
            $values['option_format'] = (string) $current['option_format'];
        } else {
            $format = (string) $request->input('option_format', '%s) %s');
            if (substr_count($format, '%s') !== 2) {
                throw new \RuntimeException(Lang::get('app.trivia.errors.format_invalid'));
            }
            $values['option_format'] = mb_substr($format, 0, 32);
        }

        $values['reward_mode'] = $this->normalizedEnum(
            $request,
            'reward_mode',
            (array) Config::get('trivia.reward_modes', ['question']),
            (string) ($current['reward_mode'] ?? 'question')
        );

        $presetNames = array_map(static fn(array $p): string => (string) $p['name'], $this->repo()->listPresets());

        if ($request->input('default_reward_preset') === null && array_key_exists('default_reward_preset', $current)) {
            $values['default_reward_preset'] = (string) $current['default_reward_preset'];
        } else {
            $default = trim((string) $request->input('default_reward_preset', ''));
            $values['default_reward_preset'] = in_array($default, $presetNames, true) ? $default : '';
        }

        if ($request->input('pool_presets') === null && array_key_exists('pool_presets', $current)) {
            $values['pool_presets'] = (string) $current['pool_presets'];
        } else {
            $pool = array_values(array_filter(
                $this->parseNameList((string) $request->input('pool_presets', '')),
                static fn(string $name): bool => in_array($name, $presetNames, true)
            ));
            $values['pool_presets'] = implode(',', $pool);
        }

        $values['mail_subject'] = $request->input('mail_subject') === null && array_key_exists('mail_subject', $current)
            ? (string) $current['mail_subject']
            : mb_substr(trim((string) $request->input('mail_subject', '')), 0, 128);

        $values['mail_body'] = $request->input('mail_body') === null && array_key_exists('mail_body', $current)
            ? (string) $current['mail_body']
            : mb_substr((string) $request->input('mail_body', ''), 0, 2000);

        // 作答提示 / 播报前缀（原来是 TriviaReward_conf.lua 里的项，现在写库）
        $values['answer_hint'] = $request->input('answer_hint') === null && array_key_exists('answer_hint', $current)
            ? (string) $current['answer_hint']
            : mb_substr(trim((string) $request->input('answer_hint', '')), 0, 255);

        $values['broadcast_prefix'] = $request->input('broadcast_prefix') === null && array_key_exists('broadcast_prefix', $current)
            ? (string) $current['broadcast_prefix']
            : mb_substr((string) $request->input('broadcast_prefix', ''), 0, 32);

        $values['win_prefix'] = $request->input('win_prefix') === null && array_key_exists('win_prefix', $current)
            ? (string) $current['win_prefix']
            : mb_substr((string) $request->input('win_prefix', ''), 0, 32);

        return $values;
    }

    /**
     * @return array<int,int>
     */
    private function parseIntList(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[,\s]+/', $raw) ?: [] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '' || !preg_match('/^-?\d+$/', $chunk)) {
                continue;
            }
            $value = (int) $chunk;
            if (!in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }

    /**
     * "甲,乙,丙,丁" / "甲乙丙丁" / "A B C D" → ['甲','乙','丙','丁']
     *
     * 没有逗号时按 UTF-8 字符切，并丢掉空白字符，避免 "A B C D" 被当成 7 个标号。
     *
     * @return array<int,string>
     */
    private function parseLabelList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = array_values(array_filter(
            array_map('trim', preg_split('/[,;]+/', $raw) ?: []),
            static fn(string $v): bool => $v !== ''
        ));
        if (count($parts) >= 2) {
            return $parts;
        }

        $chars = preg_split('//u', $parts[0] ?? '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $chars = array_values(array_filter($chars, static fn(string $v): bool => trim($v) !== ''));

        return $chars;
    }

    /**
     * @return array<int,string>
     */
    private function parseNameList(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[,\s]+/', $raw) ?: [] as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk !== '' && !in_array($chunk, $out, true)) {
                $out[] = $chunk;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ 题库

    public function apiQuestionSave(Request $request): Response
    {
        $this->requireCapability('trivia.manage');
        $this->maybeSwitchServer($request);

        if (!$this->repo()->tableExists('questions')) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.schema_missing', ['tables' => $this->repo()->tableName('questions')]),
            ], 422);
        }

        $id = $request->int('id', 0);
        $question = mb_substr(trim((string) $request->input('question', '')), 0, 255);
        if ($question === '') {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.question_required'),
            ], 422);
        }

        $options = [];
        foreach ([1, 2, 3, 4] as $index) {
            $options[$index] = mb_substr(trim((string) $request->input('option' . $index, '')), 0, 120);
        }

        $filled = array_values(array_filter($options, static fn(string $v): bool => $v !== ''));
        if (count($filled) < 2) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.options_required'),
            ], 422);
        }

        // 选项必须从左往右连续填写，否则 answer_index 会指到空选项
        $expected = 1;
        foreach ($options as $index => $text) {
            if ($text === '') {
                break;
            }
            $expected = $index + 1;
        }
        for ($index = $expected; $index <= 4; $index++) {
            if ($options[$index] !== '') {
                return $this->json([
                    'success' => false,
                    'message' => Lang::get('app.trivia.errors.options_not_contiguous'),
                ], 422);
            }
        }

        $optionCount = count($filled);
        $answerIndex = (int) $request->input('answer_index', 1);
        if ($answerIndex < 1 || $answerIndex > $optionCount) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.answer_out_of_range', ['count' => (string) $optionCount]),
            ], 422);
        }

        $labels = $this->parseLabelList((string) $request->input('labels', ''));
        if ($labels !== [] && count($labels) < $optionCount) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.labels_too_few', ['count' => (string) $optionCount]),
            ], 422);
        }

        $presetNames = array_map(static fn(array $p): string => (string) $p['name'], $this->repo()->listPresets());
        $preset = trim((string) $request->input('reward_preset', ''));
        if ($preset !== '' && !in_array($preset, $presetNames, true)) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.preset_unknown'),
            ], 422);
        }

        $moneyRange = (array) Config::get('trivia.limits.reward_money', [0, 2147483647]);
        $items = $this->repo()->normalizeItemsText((string) $request->input('reward_items', ''));
        $money = max((int) $moneyRange[0], min((int) $moneyRange[1], (int) $request->input('reward_money', 0)));

        $data = [
            'id' => $id,
            'question' => $question,
            'option1' => $options[1],
            'option2' => $options[2],
            'option3' => $options[3],
            'option4' => $options[4],
            'answer_index' => $answerIndex,
            'labels' => $labels === [] ? '' : implode(',', $labels),
            'reward_preset' => $preset,
            'reward_items' => $items,
            'reward_money' => $money,
            'enabled' => $this->normalizedBoolFlag($request, 'enabled') ? 1 : 0,
            'sort_order' => $this->boundedInt($request, 'sort_order', 0, -100000, 100000),
        ];

        try {
            $savedId = $this->repo()->saveQuestion($data);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.question_save_failed'),
            ], 422);
        }

        $reload = $this->runTriviaCommand('.trivia reload');

        Audit::log('trivia', $id > 0 ? 'question_update' : 'question_create', (string) $savedId, [
            'server_id' => ServerContext::currentId(),
            'question' => $question,
            'reload_success' => $reload['success'],
        ]);

        return $this->json([
            'success' => true,
            'message' => $reload['success']
                ? Lang::get('app.trivia.feedback.question_saved')
                : Lang::get('app.trivia.feedback.saved_reload_failed', [
                    'message' => $this->firstNonEmpty($reload['message'] ?? '', $reload['output'] ?? '', Lang::get('app.trivia.errors.command_failed')),
                ]),
            'payload' => [
                'id' => $savedId,
                'reload_success' => $reload['success'],
                'stats' => $this->repo()->questionStats(),
            ],
        ], 200);
    }

    public function apiQuestionDelete(Request $request): Response
    {
        $this->requireCapability('trivia.manage');
        $this->maybeSwitchServer($request);

        $id = $request->int('id', 0);
        if ($id <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.question_id_required'),
            ], 422);
        }

        try {
            $deleted = $this->repo()->deleteQuestion($id);
        } catch (Throwable $exception) {
            $deleted = false;
        }

        if (!$deleted) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.question_not_found'),
            ], 404);
        }

        $reload = $this->runTriviaCommand('.trivia reload');

        Audit::log('trivia', 'question_delete', (string) $id, [
            'server_id' => ServerContext::currentId(),
            'reload_success' => $reload['success'],
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.trivia.feedback.question_deleted'),
            'payload' => ['stats' => $this->repo()->questionStats()],
        ]);
    }

    public function apiQuestionToggle(Request $request): Response
    {
        $this->requireCapability('trivia.manage');
        $this->maybeSwitchServer($request);

        $id = $request->int('id', 0);
        $enabled = $this->normalizedBoolFlag($request, 'enabled');
        if ($id <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.question_id_required'),
            ], 422);
        }

        try {
            $updated = $this->repo()->setQuestionEnabled($id, $enabled);
        } catch (Throwable $exception) {
            $updated = false;
        }

        if (!$updated) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.question_not_found'),
            ], 404);
        }

        $reload = $this->runTriviaCommand('.trivia reload');

        Audit::log('trivia', $enabled ? 'question_enable' : 'question_disable', (string) $id, [
            'server_id' => ServerContext::currentId(),
            'reload_success' => $reload['success'],
        ]);

        return $this->json([
            'success' => true,
            'message' => $enabled
                ? Lang::get('app.trivia.feedback.question_enabled')
                : Lang::get('app.trivia.feedback.question_disabled'),
            'payload' => ['stats' => $this->repo()->questionStats()],
        ]);
    }

    // ------------------------------------------------------------------ 奖励预设

    public function apiPresetSave(Request $request): Response
    {
        $this->requireCapability('trivia.manage');
        $this->maybeSwitchServer($request);

        if (!$this->repo()->tableExists('presets')) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.schema_missing', ['tables' => $this->repo()->tableName('presets')]),
            ], 422);
        }

        $name = strtolower(trim((string) $request->input('name', '')));
        $original = strtolower(trim((string) $request->input('original_name', '')));
        if ($name === '' || preg_match('/^[a-z0-9_\-]{1,32}$/', $name) !== 1) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.preset_name_invalid'),
            ], 422);
        }

        $items = $this->repo()->normalizeItemsText((string) $request->input('items', ''));
        $moneyRange = (array) Config::get('trivia.limits.reward_money', [0, 2147483647]);
        $money = max((int) $moneyRange[0], min((int) $moneyRange[1], (int) $request->input('money', 0)));

        if ($items === '' && $money <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.preset_empty'),
            ], 422);
        }

        try {
            $this->repo()->savePreset([
                'name' => $name,
                'items' => $items,
                'money' => $money,
                'enabled' => $this->normalizedBoolFlag($request, 'enabled') ? 1 : 0,
            ], $original !== '' ? $original : null);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.preset_save_failed'),
            ], 422);
        }

        $reload = $this->runTriviaCommand('.trivia reload');

        Audit::log('trivia', 'preset_save', $name, [
            'server_id' => ServerContext::currentId(),
            'items' => $items,
            'money' => $money,
            'reload_success' => $reload['success'],
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.trivia.feedback.preset_saved'),
            'payload' => [
                'name' => $name,
                'money' => $money,
                'items' => $items,
                'reload_success' => $reload['success'],
            ],
        ]);
    }

    public function apiPresetDelete(Request $request): Response
    {
        $this->requireCapability('trivia.manage');
        $this->maybeSwitchServer($request);

        $name = strtolower(trim((string) $request->input('name', '')));
        if ($name === '') {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.preset_name_invalid'),
            ], 422);
        }

        // 仍被题目引用时不删，避免题目变成"预设不存在"
        $usage = $this->repo()->presetUsage($name);
        if ($usage !== []) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.preset_in_use', [
                    'count' => (string) count($usage),
                    'first' => (string) ($usage[0]['question'] ?? ''),
                ]),
            ], 422);
        }

        try {
            $deleted = $this->repo()->deletePreset($name);
        } catch (Throwable $exception) {
            $deleted = false;
        }

        if (!$deleted) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.preset_not_found'),
            ], 404);
        }

        $reload = $this->runTriviaCommand('.trivia reload');

        Audit::log('trivia', 'preset_delete', $name, [
            'server_id' => ServerContext::currentId(),
            'reload_success' => $reload['success'],
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.trivia.feedback.preset_deleted'),
            'payload' => ['reload_success' => $reload['success']],
        ]);
    }

    // ------------------------------------------------------------------ 题库模板导入 / 导出

    /**
     * 模板导入：mode=preview 只解析并返回逐行诊断；mode=commit 真正写库。
     *
     * 模板文本从 template 参数来（前端"选择文件"会把文件内容读进同一个文本框），
     * 支持 CSV / TSV / JSON，表头可选，答案可写 1-4 / A-D / 甲-丁 / 选项原文。
     */
    public function apiQuestionImport(Request $request): Response
    {
        $this->requireCapability('trivia.manage');
        $this->maybeSwitchServer($request);

        if (!$this->repo()->tableExists('questions')) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.schema_missing', ['tables' => $this->repo()->tableName('questions')]),
            ], 422);
        }

        $mode = $this->normalizedEnum($request, 'mode', ['preview', 'commit'], 'preview');
        $text = (string) $request->input('template', '');
        if (trim($text) === '') {
            $text = $this->uploadedTemplate();
        }

        if (trim($text) === '') {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.import_empty'),
            ], 422);
        }

        if (strlen($text) > 1024 * 1024) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.import_too_large'),
            ], 422);
        }

        $parsed = QuestionTemplate::parse($text);
        $presetNames = array_map(static fn(array $p): string => (string) $p['name'], $this->repo()->listPresets());
        $moneyRange = (array) Config::get('trivia.limits.reward_money', [0, 2147483647]);

        $validRows = [];
        $previewRows = [];
        $errors = $parsed['errors'];

        foreach ($parsed['rows'] as $entry) {
            [$row, $error] = $this->buildQuestionFromTemplate($entry['data'], $presetNames, $moneyRange);
            if ($error !== null) {
                $errors[] = ['line' => $entry['line'], 'message' => $error];
                continue;
            }

            $previewRows[] = [
                'line' => $entry['line'],
                'question' => (string) $row['question'],
                'options' => array_values(array_filter([
                    (string) $row['option1'], (string) $row['option2'], (string) $row['option3'], (string) $row['option4'],
                ], static fn(string $v): bool => $v !== '')),
                'answer_index' => (int) $row['answer_index'],
                'answer' => (string) $row['_answer_text'],
                'labels' => (string) $row['labels'],
                'reward_preset' => (string) $row['reward_preset'],
                'reward_items' => (string) $row['reward_items'],
                'reward_money' => (int) $row['reward_money'],
                'enabled' => ((int) $row['enabled']) === 1,
            ];

            unset($row['_answer_text']);
            $validRows[] = $row;
        }

        if ($mode === 'preview') {
            $summary = [
                'format' => $parsed['format'],
                'parsed' => count($parsed['rows']),
                'valid' => count($validRows),
                'invalid' => count($errors),
                'errors' => $errors,
                'preview' => array_slice($previewRows, 0, 30),
                'preview_truncated' => count($previewRows) > 30,
            ];

            return $this->json([
                'success' => true,
                'mode' => 'preview',
                'can_commit' => $validRows !== [],
                'html' => $this->partial('trivia._import_preview', ['trivia_import' => $summary]),
            ] + $summary);
        }

        if ($validRows === []) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.import_nothing_valid'),
                'errors' => $errors,
            ], 422);
        }

        try {
            $inserted = $this->repo()->insertQuestions($validRows, 'import');
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.import_failed'),
            ], 422);
        }

        $reload = $this->runTriviaCommand('.trivia reload');

        Audit::log('trivia', 'question_import', $this->repo()->tableName('questions'), [
            'server_id' => ServerContext::currentId(),
            'format' => $parsed['format'],
            'inserted' => $inserted,
            'skipped' => count($errors),
            'reload_success' => $reload['success'],
        ]);

        return $this->json([
            'success' => true,
            'mode' => 'commit',
            'inserted' => $inserted,
            'skipped' => count($errors),
            'errors' => $errors,
            'message' => Lang::get('app.trivia.feedback.import_done', [
                'inserted' => (string) $inserted,
                'skipped' => (string) count($errors),
            ]),
            'payload' => [
                'stats' => $this->repo()->questionStats(),
                'sources' => $this->repo()->questionSourceStats(),
                'reload_success' => $reload['success'],
            ],
        ]);
    }

    /**
     * 导出当前题库为 CSV（UTF-8 BOM，Excel 可直接打开）。
     */
    public function apiQuestionExport(Request $request): Response
    {
        $this->requireCapability('trivia.view');
        $this->maybeSwitchServer($request);

        $rows = $this->repo()->allQuestions();
        $filename = sprintf('trivia-questions-realm%d-%s.csv', ServerContext::currentId(), date('Ymd_His'));

        Audit::log('trivia', 'question_export', $this->repo()->tableName('questions'), [
            'server_id' => ServerContext::currentId(),
            'rows' => count($rows),
        ]);

        return $this->response(200, QuestionTemplate::toCsv($rows), [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Row-Count' => (string) count($rows),
        ]);
    }

    /**
     * 下载空白模板（表头 + 两行示例）。
     */
    public function apiQuestionTemplate(Request $request): Response
    {
        $this->requireCapability('trivia.view');

        return $this->response(200, QuestionTemplate::sample(), [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="trivia-questions-template.csv"',
        ]);
    }

    /**
     * 模板一行 → 数据库行；返回 [row, error]
     *
     * @param array<string,string> $data
     * @param array<int,string> $presetNames
     * @param array<int,int> $moneyRange
     * @return array{0:array<string,mixed>,1:?string}
     */
    private function buildQuestionFromTemplate(array $data, array $presetNames, array $moneyRange): array
    {
        $question = mb_substr(trim((string) ($data['question'] ?? '')), 0, 255);
        if ($question === '') {
            return [[], Lang::get('app.trivia.errors.question_required')];
        }

        $options = [];
        foreach ([1, 2, 3, 4] as $index) {
            $options[$index] = mb_substr(trim((string) ($data['option' . $index] ?? '')), 0, 120);
        }

        // 选项必须从第一个开始连续填写
        $filled = 0;
        foreach ($options as $index => $text) {
            if ($text === '') {
                break;
            }
            $filled = $index;
        }
        for ($index = $filled + 1; $index <= 4; $index++) {
            if ($options[$index] !== '') {
                return [[], Lang::get('app.trivia.errors.options_not_contiguous')];
            }
        }
        if ($filled < 2) {
            return [[], Lang::get('app.trivia.errors.options_required')];
        }

        $labels = $this->parseLabelList((string) ($data['labels'] ?? ''));
        if ($labels !== [] && count($labels) < $filled) {
            return [[], Lang::get('app.trivia.errors.labels_too_few', ['count' => (string) $filled])];
        }

        $answerRaw = trim((string) ($data['answer'] ?? ''));
        $answerIndex = $this->resolveTemplateAnswer($answerRaw, $options, $filled, $labels);
        if ($answerIndex === null) {
            return [[], Lang::get('app.trivia.errors.answer_unresolved', ['answer' => $answerRaw])];
        }

        $preset = trim((string) ($data['reward_preset'] ?? ''));
        if ($preset !== '' && !in_array($preset, $presetNames, true)) {
            return [[], Lang::get('app.trivia.errors.preset_unknown')];
        }

        $enabledRaw = strtolower(trim((string) ($data['enabled'] ?? '1')));
        $enabled = !in_array($enabledRaw, ['0', 'false', 'no', 'n', '否', '停用', '禁用'], true);

        $money = (int) preg_replace('/[^0-9]/', '', (string) ($data['reward_money'] ?? '0'));

        return [[
            'question' => $question,
            'option1' => $options[1],
            'option2' => $options[2],
            'option3' => $options[3],
            'option4' => $options[4],
            'answer_index' => $answerIndex,
            'labels' => $labels === [] ? '' : implode(',', array_slice($labels, 0, $filled)),
            'reward_preset' => $preset,
            'reward_items' => $this->repo()->normalizeItemsText((string) ($data['reward_items'] ?? '')),
            'reward_money' => max((int) $moneyRange[0], min((int) $moneyRange[1], $money)),
            'enabled' => $enabled ? 1 : 0,
            'sort_order' => 0,
            '_answer_text' => $options[$answerIndex],
        ], null];
    }

    /**
     * 模板里的"答案"支持 1-4 / A-D / 甲-丁 / 选项原文。
     *
     * @param array<int,string> $options
     * @param array<int,string> $labels
     */
    private function resolveTemplateAnswer(string $answer, array $options, int $optionCount, array $labels): ?int
    {
        $answer = trim($answer);
        if ($answer === '') {
            return null;
        }

        if (preg_match('/^[1-9]$/', $answer) === 1) {
            $index = (int) $answer;

            return $index <= $optionCount ? $index : null;
        }

        $labelSets = [];
        if ($labels !== []) {
            $labelSets[] = $labels;
        }
        $labelSets[] = ['A', 'B', 'C', 'D'];
        $labelSets[] = ['甲', '乙', '丙', '丁'];

        $needle = mb_strtolower($answer);
        foreach ($labelSets as $set) {
            foreach ($set as $index => $label) {
                if ($index + 1 <= $optionCount && mb_strtolower((string) $label) === $needle) {
                    return $index + 1;
                }
            }
        }

        for ($index = 1; $index <= $optionCount; $index++) {
            if (mb_strtolower(trim($options[$index])) === $needle) {
                return $index;
            }
        }

        return null;
    }

    /**
     * 直接上传文件时的入口（前端默认把文件内容读进文本框，这个入口留给 multipart 提交）。
     */
    private function uploadedTemplate(): string
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return '';
        }

        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return '';
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp) || (int) ($file['size'] ?? 0) > 1024 * 1024) {
            return '';
        }

        return (string) file_get_contents($tmp);
    }

    // ------------------------------------------------------------------ 排行

    public function apiWinnersClear(Request $request): Response
    {
        $this->requireCapability('trivia.manage');
        $this->maybeSwitchServer($request);

        try {
            $removed = $this->repo()->clearWinners();
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.trivia.errors.winners_clear_failed'),
            ], 422);
        }

        Audit::log('trivia', 'winners_clear', $this->repo()->tableName('winners'), [
            'server_id' => ServerContext::currentId(),
            'removed' => $removed,
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.trivia.feedback.winners_cleared', ['count' => (string) $removed]),
            'payload' => ['removed' => $removed, 'stats' => $this->repo()->winnerStats()],
        ]);
    }

    // ------------------------------------------------------------------ 内部

    /**
     * 通过 SOAP 读脚本的实时状态（.trivia api 返回单行 JSON）。
     *
     * 失败时一律返回本地化的可读原因（error），技术细节放在 detail 里，不要把
     * "Could not connect to host" 这类底层报错直接甩到页面上。
     *
     * @return array{available:bool,error:string,detail:string,data:array<string,mixed>,raw:string}
     */
    private function liveStatus(): array
    {
        $unreachable = Lang::get('app.trivia.warnings.soap_unreachable');

        try {
            $result = $this->runTriviaCommand('.trivia api');
        } catch (Throwable $exception) {
            return [
                'available' => false,
                'error' => $unreachable,
                'detail' => $exception->getMessage(),
                'data' => [],
                'raw' => '',
            ];
        }

        $raw = trim((string) ($result['message'] ?? ''));
        if ($raw === '') {
            $raw = trim((string) ($result['output'] ?? ''));
        }

        // 有些版本会把整个输出包在标记行里，这里取第一个 { 到最后一个 } 之间的内容
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            // 连不上 / 没有任何 JSON：本地化提示已经说清楚了，不再把底层报错甩到页面上
            return [
                'available' => false,
                'error' => $unreachable,
                'detail' => '',
                'data' => [],
                'raw' => $raw,
            ];
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
        if (!is_array($decoded)) {
            return [
                'available' => false,
                'error' => Lang::get('app.trivia.warnings.status_parse_failed'),
                'detail' => substr($raw, 0, 500),
                'data' => [],
                'raw' => $raw,
            ];
        }

        return ['available' => true, 'error' => '', 'detail' => '', 'data' => $decoded, 'raw' => $raw];
    }

    private function runTriviaCommand(string $command): array
    {
        $options = [
            'server_id' => ServerContext::currentId(),
            'strict_marker' => true,
        ];

        $soap = (array) Config::get('trivia.soap', []);
        if (isset($soap['timeout_connect'])) {
            $options['timeout_connect'] = (float) $soap['timeout_connect'];
        }
        if (isset($soap['timeout_total'])) {
            $options['timeout_total'] = (float) $soap['timeout_total'];
        }

        // 双保险：worldserver 没启动时 SOAP 会发 PHP warning，而面板的 ErrorHandler 会把
        // warning 直接渲染成一段异常 HTML 插进页面。这里临时静音，调用结束立刻恢复。
        set_error_handler(static function (): bool {
            return true;
        }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED);

        try {
            return SoapCommandRunner::execute($command, $options);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * 视图用的设置数据：数据库行 + 每列的默认值，保证表单永远有值可渲染。
     */
    private function settingsViewData(array $row): array
    {
        $defaults = [
            'enabled' => 1,
            'interval_seconds' => 900,
            'answer_seconds' => 60,
            'remind_every_seconds' => 30,
            'first_delay_seconds' => 60,
            'min_players_online' => 1,
            'min_level' => 1,
            'answer_say' => 1,
            'answer_yell' => 1,
            'answer_emote' => 0,
            'answer_whisper' => 0,
            'answer_channel_ids' => '',
            'answer_prefix' => '',
            'allow_number_answer' => 1,
            'allow_latin_letters' => 1,
            'allow_text_answer' => 0,
            'attempts_per_player' => 1,
            'option_labels' => 'A,B,C,D',
            'option_format' => '%s) %s',
            'reward_mode' => 'question',
            'default_reward_preset' => '',
            'pool_presets' => '',
            'use_builtin_questions' => 1,
            'announce_on_login' => 1,
            // 默认暂停：新装/缺行时服务器重启后不会自动出题，需手动开启或等定时计划
            'paused' => 1,
            'reply_wrong_answer' => 0,
            'reply_already_answered' => 1,
            'min_gm_rank_for_command' => 2,
            'sender_guid' => 10667,
            'mail_stationery' => 41,
            'item_link_locale' => 4,
            'mail_subject' => '答题奖励',
            'mail_body' => "勇士，恭喜你在聊天答题中第一个答对！\n题目：{question}\n正确答案：{answer}\n奖励已随信附上，祝你在艾泽拉斯的旅途愉快！",
            'schedule_enabled' => 0,
            'schedule_windows' => '',
            'debug_log' => 0,
            'idle_retry_seconds' => 5,
            'resume_delay_seconds' => 5,
            'allow_loose_letter' => 1,
            'ignore_gms' => 1,
            'gm_rank_exempt' => 3,
            'answer_hint' => '',
            'broadcast_prefix' => '|cff00ff00[答题]|r ',
            'win_prefix' => '|cffffd200[答题]|r ',
        ];

        $merged = array_replace($defaults, array_intersect_key($row, $defaults));
        $merged['exists'] = $row !== [];
        $merged['channel_ids_list'] = $this->parseIntList((string) $merged['answer_channel_ids']);
        $merged['pool_presets_list'] = $this->parseNameList((string) $merged['pool_presets']);
        $merged['schedule_windows_list'] = ScheduleWindows::describe((string) $merged['schedule_windows']);

        return $merged;
    }

    private function optionPayload(): array
    {
        return [
            'channels' => (array) Config::get('trivia.channels', []),
            'label_presets' => (array) Config::get('trivia.label_presets', []),
            'format_presets' => (array) Config::get('trivia.format_presets', []),
            'reward_modes' => (array) Config::get('trivia.reward_modes', ['question']),
            'limits' => (array) Config::get('trivia.limits', []),
            'page_size_options' => (array) Config::get('trivia.page_size_options', [20, 30, 50, 100]),
        ];
    }

    /**
     * 收集题目/预设里用到的物品 ID → 名字，供视图显示。
     *
     * @param array<int,array<string,mixed>> $presets
     * @param array<int,array<string,mixed>> $questions
     * @return array<int,string>
     */
    private function presetItemNames(array $presets, array $questions): array
    {
        $ids = [];

        foreach ($presets as $preset) {
            foreach ($this->repo()->parseItemsText((string) ($preset['items'] ?? '')) as $item) {
                $ids[$item['entry']] = true;
            }
        }

        foreach ($questions as $question) {
            foreach (($question['reward_items_parsed'] ?? []) as $item) {
                $ids[(int) $item['entry']] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        try {
            return $this->repo()->itemNames(array_map('intval', array_keys($ids)));
        } catch (Throwable $exception) {
            return [];
        }
    }

    private function questionListState(Request $request, int $limit): array
    {
        return [
            'filters' => [
                'search' => $this->normalizedString($request, 'search'),
                'status' => $this->normalizedEnum($request, 'status', ['all', 'enabled', 'disabled'], 'all'),
                'limit' => $limit,
            ],
            'page' => $this->normalizedPage($request, 'page'),
            'limit' => $limit,
        ];
    }

    private function partial(string $view, array $data): string
    {
        return View::make($view, $data);
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
