<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Raf;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Core\View;
use Acme\Panel\Domain\Raf\RafRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\Paginator;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\SoapCommandRunner;
use Throwable;

class RafController extends Controller
{
    private ?RafRepository $repo = null;

    private function repo(): RafRepository
    {
        if ($this->repo === null) {
            $this->repo = new RafRepository();
        }

        return $this->repo;
    }

    private function maybeSwitchServer(Request $request): void
    {
        $this->switchServerAndRebind($request, $this->repo());
    }

    private function requireListCapability(): void
    {
        $this->requireCapability('raf.list');
    }

    private function requireBindCapability(): void
    {
        $this->requireCapability('raf.bind');
    }

    private function requireUnbindCapability(): void
    {
        $this->requireCapability('raf.unbind');
    }

    private function requireCommentCapability(): void
    {
        $this->requireCapability('raf.comment');
    }

    public function index(Request $request): Response
    {
        $this->requireListCapability();
        $this->maybeSwitchServer($request);

        $state = $this->prepareListState($request);
        $view = $this->buildListViewData($state);

        $logState = $this->prepareRewardLogState($request);
        $rewardLog = $this->buildRewardLogViewData($logState);

        $view['raf_permanent_block_threshold'] = (int) Config::get('raf.permanent_block_threshold', 5);
        $view['raf_stats_has_query'] = $this->listHasQuery($state['filters']);
        $defaults = $this->listDefaults();
        $view['raf_defaults'] = $defaults;
        $view = array_merge($view, $this->rewardLogSectionView($rewardLog));

        return $this->pageView('raf.index', $view, [
            'module' => 'raf',
            'capabilities' => $this->listCapabilities(),
            'header' => [
                'intro' => __('app.raf.intro'),
                'note' => __('app.raf.scope_note', [
                    'server' => (string) ($defaults['server_name'] ?? ''),
                    'realm' => (string) ($defaults['realm_id'] ?? 0),
                ]),
            ],
            'meta' => [
                'title' => __('app.raf.page_title'),
            ],
        ]);
    }

    /**
     * 顶部统计卡是否处于"筛选后"状态，决定卡片下方的说明文案。
     */
    private function listHasQuery(array $filters): bool
    {
        return trim((string) ($filters['search'] ?? '')) !== ''
            || (int) ($filters['recruiter_guid'] ?? 0) > 0
            || (string) ($filters['status'] ?? 'all') !== 'all';
    }

    /**
     * 绑定列表区块：统计与分页分开兜底，统计失败不牵连已经取到的列表。
     */
    private function buildListViewData(array $state): array
    {
        $pager = new Paginator([], 0, $state['page'], $state['limit']);
        $stats = $this->defaultStats();
        $error = null;
        $schemaStatus = $this->repo()->schemaStatus();
        $filters = $state['filters'];

        if (!$schemaStatus['ready']) {
            $error = Lang::get('app.raf.errors.schema_missing', [
                'tables' => implode(', ', $schemaStatus['missing_tables']),
            ]);
        } else {
            try {
                $pager = $this->repo()->listLinks(
                    $filters,
                    $state['page'],
                    $state['limit']
                );
            } catch (Throwable $exception) {
                $error = Lang::get('app.raf.errors.load_failed');
            }

            try {
                $stats = $this->repo()->stats($filters);
            } catch (Throwable $exception) {
                $stats = $this->defaultStats();
            }
        }

        return $this->listViewData($pager, $filters, [
            'raf_stats' => $stats,
            'raf_error' => $error,
            'raf_schema_missing' => !$schemaStatus['ready'],
        ]);
    }

    /**
     * 奖励发放记录区块：表缺失只影响该区块，绑定列表保持可用。
     */
    private function buildRewardLogViewData(array $state): array
    {
        $schemaStatus = $this->repo()->schemaStatus();
        $pager = new Paginator([], 0, $state['page'], $state['limit']);
        $stats = $this->defaultRewardLogStats();
        $error = null;
        $ready = (bool) $schemaStatus['reward_log_ready'];

        if (!$ready) {
            $error = Lang::get('app.raf.errors.reward_log_missing');
        } else {
            try {
                $pager = $this->repo()->listRewardLogs(
                    $state['filters'],
                    $state['page'],
                    $state['limit']
                );
                $stats = $this->repo()->rewardLogStats($state['filters']);
            } catch (Throwable $exception) {
                $error = Lang::get('app.raf.errors.reward_log_load_failed');
            }
        }

        return [
            'pager' => $pager,
            'stats' => $stats,
            'error' => $error,
            'ready' => $ready,
            'filters' => $state['filters'],
            'page' => $state['page'],
            'limit' => $state['limit'],
        ];
    }

    /**
     * 把奖励记录状态拼成区块部分视图所需的数据。
     */
    private function rewardLogSectionView(array $rewardLog): array
    {
        return [
            'raf_reward_log' => $rewardLog,
            'raf_log_default_only' => !empty($rewardLog['filters']['default_only']) ? 1 : 0,
            'raf_defaults' => $this->listDefaults(),
        ];
    }

    private function listDefaults(): array
    {
        $server = ServerContext::server();

        return [
            'server_name' => (string) ($server['name'] ?? ''),
            'realm_id' => $this->repo()->currentRealmId(),
            'page_size_options' => Config::get('raf.page_size_options', [20, 30, 50, 100]),
        ];
    }

    /**
     * 绑定列表区块的 AJAX 刷新：返回可直接替换的 HTML 片段 + 最新统计。
     */
    public function apiBindings(Request $request): Response
    {
        $this->requireListCapability();
        $this->maybeSwitchServer($request);

        $state = $this->prepareListState($request);
        $view = $this->buildListViewData($state);
        $view['raf_defaults'] = $this->listDefaults();

        return $this->json([
            'success' => true,
            'section' => 'bindings',
            'html' => $this->partial('raf._binding_section', $view),
            'stats' => [
                'bindings' => is_array($view['raf_stats'] ?? null) ? $view['raf_stats'] : $this->defaultStats(),
            ],
        ]);
    }

    /**
     * 奖励发放记录区块的 AJAX 刷新。
     */
    public function apiRewardLogs(Request $request): Response
    {
        $this->requireListCapability();
        $this->maybeSwitchServer($request);

        $state = $this->prepareRewardLogState($request);
        $rewardLog = $this->buildRewardLogViewData($state);
        $view = $this->rewardLogSectionView($rewardLog);

        return $this->json([
            'success' => true,
            'section' => 'reward-log',
            'html' => $this->partial('raf._reward_log_section', $view),
            'stats' => [
                'reward_log' => $this->rewardLogStatPayload(
                    is_array($rewardLog['stats'] ?? null)
                        ? $rewardLog['stats']
                        : $this->defaultRewardLogStats()
                ),
            ],
        ]);
    }

    /**
     * 统计卡片下钻：把卡片背后的明细渲染成弹窗内容。
     */
    public function apiCard(Request $request): Response
    {
        $this->requireListCapability();
        $this->maybeSwitchServer($request);

        $card = $this->normalizedString($request, 'card');
        $limit = $this->boundedInt($request, 'detail_limit', 12, 1, 50);
        $page = $this->normalizedPage($request, 'detail_page');

        return match ($card) {
            'bindings' => $this->bindingCardResponse($request, $limit, $page),
            'reward-log' => $this->rewardLogCardResponse($request, $limit, $page),
            default => $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.card_unknown'),
            ], 422),
        };
    }

    private function bindingCardResponse(Request $request, int $limit, int $page): Response
    {
        $group = 'bindings';
        $key = $this->normalizedString($request, 'key');
        $state = $this->prepareListState($request);
        $base = $state['filters'];

        $schemaStatus = $this->repo()->schemaStatus();
        if (!$schemaStatus['ready']) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.schema_missing', [
                    'tables' => implode(', ', $schemaStatus['missing_tables']),
                ]),
            ], 422);
        }

        $stats = $this->defaultStats();
        try {
            $stats = $this->repo()->stats($base);
        } catch (Throwable $exception) {
            $stats = $this->defaultStats();
        }

        $cards = $this->bindingCards($stats);
        $card = $cards[$key] ?? null;
        if ($card === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.card_unknown'),
            ], 422);
        }

        $extra = is_array($card['filters'] ?? null) ? $card['filters'] : [];
        $filters = array_replace($base, $extra);

        $pager = new Paginator([], 0, 1, $limit);
        try {
            $pager = $this->repo()->listLinks($filters, $page, $limit);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.load_failed'),
            ], 422);
        }

        $rows = is_array($pager->items) ? $pager->items : [];

        return $this->json([
            'success' => true,
            'group' => $group,
            'key' => $key,
            'title' => (string) ($card['label'] ?? $key),
            'value' => (int) ($card['value'] ?? 0),
            'hint' => (string) ($card['hint'] ?? ''),
            'total' => (int) $pager->total,
            'shown' => count($rows),
            'empty' => Lang::get('app.raf.empty'),
            'html' => $rows === [] ? '' : $this->partial('raf._binding_table', [
                'pager' => $pager,
                'rafCapabilities' => $this->pageCapabilities($this->listCapabilities()),
                'raf_detail_mode' => true,
            ]),
            'actions' => array_values(array_filter([
                $this->cardViewAllAction($card, $base),
            ])),
        ]);
    }

    private function rewardLogCardResponse(Request $request, int $limit, int $page): Response
    {
        $group = 'reward-log';
        $key = $this->normalizedString($request, 'key');
        $logState = $this->prepareRewardLogState($request);
        $stats = $this->defaultRewardLogStats();

        try {
            $stats = $this->repo()->rewardLogStats($logState['filters']);
        } catch (Throwable $exception) {
            $stats = $this->defaultRewardLogStats();
        }

        $cards = $this->rewardLogCards($stats);
        $card = $cards[$key] ?? null;

        if ($card === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.card_unknown'),
            ], 422);
        }

        $schemaStatus = $this->repo()->schemaStatus();
        if (!$schemaStatus['reward_log_ready']) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.reward_log_missing'),
            ], 422);
        }

        $extra = is_array($card['filters'] ?? null) ? $card['filters'] : [];
        $filters = array_replace($logState['filters'], $extra);
        $rowLimit = isset($card['limit']) ? (int) $card['limit'] : $limit;
        $rowLimit = max(1, min($limit, $rowLimit));
        // "最近发放时间"只展示最新一条，不需要翻页
        $rowPage = $rowLimit === 1 ? 1 : $page;

        $pager = new Paginator([], 0, 1, $rowLimit);
        try {
            $pager = $this->repo()->listRewardLogs($filters, $rowPage, $rowLimit);
        } catch (Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.reward_log_load_failed'),
            ], 422);
        }

        $rows = is_array($pager->items) ? $pager->items : [];

        return $this->json([
            'success' => true,
            'group' => $group,
            'key' => $key,
            'title' => (string) ($card['label'] ?? $key),
            'value' => (int) ($card['value'] ?? 0),
            'value_text' => isset($card['value_text']) ? (string) $card['value_text'] : null,
            'hint' => (string) ($card['hint'] ?? ''),
            'total' => (int) $pager->total,
            'shown' => count($rows),
            'empty' => Lang::get('app.raf.reward_log.empty'),
            'html' => $rows === [] ? '' : $this->partial('raf._reward_log_table', [
                'pager' => $pager,
                'raf_log_source_labels' => $this->rewardLogSourceLabels(),
                'raf_detail_mode' => true,
            ]),
            'actions' => array_values(array_filter([
                $this->rewardLogViewAllAction($card, $logState['filters']),
            ])),
        ]);
    }

    private function cardViewAllAction(array $card, array $filters): ?array
    {
        $link = is_array($card['link'] ?? null) ? $card['link'] : null;
        if ($link === null) {
            return null;
        }

        return [
            'label' => Lang::get('app.raf.stats.view_all'),
            'href' => url_with_server('/raf?' . http_build_query(array_filter([
                'tab' => 'bindings',
                'search' => (string) ($filters['search'] ?? ''),
                'recruiter_guid' => (int) ($filters['recruiter_guid'] ?? 0) > 0
                    ? (int) $filters['recruiter_guid']
                    : '',
                'status' => (string) ($link['status'] ?? 'all'),
            ], static function ($value): bool {
                return $value !== '' && $value !== null && $value !== 'all';
            }))),
        ];
    }

    private function rewardLogViewAllAction(array $card, array $filters): ?array
    {
        $link = is_array($card['link'] ?? null) ? $card['link'] : null;
        if ($link === null) {
            return null;
        }

        $sort = (string) ($filters['sort'] ?? 'granted_at');
        $dir = strtoupper((string) ($filters['dir'] ?? 'DESC'));

        $query = array_filter([
            'tab' => 'reward-log',
            'log_search' => (string) ($filters['search'] ?? ''),
            'log_level' => (int) ($filters['level'] ?? 0) > 0 ? (int) $filters['level'] : '',
            'log_source' => (string) ($filters['source'] ?? ''),
            'log_from' => (int) ($filters['from'] ?? 0) > 0 ? date('Y-m-d', (int) $filters['from']) : '',
            'log_to' => (int) ($filters['to'] ?? 0) > 0 ? date('Y-m-d', (int) $filters['to']) : '',
            'log_sort' => $sort !== 'granted_at' ? $sort : '',
            'log_dir' => $dir !== 'DESC' ? $dir : '',
            'log_default' => !empty($link['default_only']) ? 1 : '',
        ], static function ($value): bool {
            return $value !== '' && $value !== null;
        });

        return [
            'label' => Lang::get('app.raf.reward_log.stats.view_all'),
            'href' => url_with_server('/raf?' . http_build_query($query)),
        ];
    }

    /**
     * 统计卡的 label/hint/filters 在控制器内解析，前后端文案与口径保持一致。
     */
    private function bindingCards(array $stats): array
    {
        $threshold = (int) Config::get('raf.permanent_block_threshold', 5);

        return [
            'total' => [
                'label' => __('app.raf.stats.total'),
                'hint' => __('app.raf.stats.hints.total'),
                'value' => (int) ($stats['total'] ?? 0),
                'filters' => [],
                'link' => ['status' => 'all'],
            ],
            'active' => [
                'label' => __('app.raf.stats.active'),
                'hint' => __('app.raf.stats.hints.active'),
                'value' => (int) ($stats['active'] ?? 0),
                'filters' => ['status' => 'active'],
                'link' => ['status' => 'active'],
            ],
            'completed' => [
                'label' => __('app.raf.stats.completed'),
                'hint' => __('app.raf.stats.hints.completed'),
                'value' => (int) ($stats['completed'] ?? 0),
                'filters' => ['status' => 'completed'],
                'link' => ['status' => 'completed'],
            ],
            'inactive' => [
                'label' => __('app.raf.stats.inactive'),
                'hint' => __('app.raf.stats.hints.inactive', ['threshold' => (string) $threshold]),
                'value' => (int) ($stats['inactive'] ?? 0),
                // status=inactive 已自带"同 IP 次数 <= threshold"，不能再叠加 ip_abuse_max，
                // 否则同一个值会绑定到两个不同的命名占位符（原生预处理会直接报错）
                'filters' => ['status' => 'inactive'],
                'link' => ['status' => 'inactive'],
            ],
            'permanent_blocked' => [
                'label' => __('app.raf.stats.permanent_blocked'),
                'hint' => __('app.raf.stats.hints.permanent_blocked', ['threshold' => (string) $threshold]),
                'value' => (int) ($stats['permanent_blocked'] ?? 0),
                'filters' => ['status' => 'permanent'],
                'link' => ['status' => 'permanent'],
            ],
            'rewarded_accounts' => [
                'label' => __('app.raf.stats.rewarded_accounts'),
                'hint' => __('app.raf.stats.hints.rewarded_accounts'),
                'value' => (int) ($stats['rewarded_accounts'] ?? 0),
                // 跨表条件，由 RafRepository::filterBindingsForExecution() 落成具体账号列表
                'filters' => ['rewarded_only' => 1],
                'link' => ['status' => 'all'],
            ],
        ];
    }

    private function rewardLogCards(array $stats): array
    {
        return [
            'total' => [
                'label' => __('app.raf.reward_log.stats.total'),
                'hint' => __('app.raf.reward_log.stats.hints.total'),
                'value' => (int) ($stats['total'] ?? 0),
                'filters' => [],
                'link' => [],
            ],
            'recruiters' => [
                'label' => __('app.raf.reward_log.stats.recruiters'),
                'hint' => __('app.raf.reward_log.stats.hints.recruiters'),
                'value' => (int) ($stats['recruiters'] ?? 0),
                'filters' => [],
                'link' => [],
            ],
            'recruits' => [
                'label' => __('app.raf.reward_log.stats.recruits'),
                'hint' => __('app.raf.reward_log.stats.hints.recruits'),
                'value' => (int) ($stats['recruits'] ?? 0),
                'filters' => [],
                'link' => [],
            ],
            'default_rewards' => [
                'label' => __('app.raf.reward_log.stats.default_rewards'),
                'hint' => __('app.raf.reward_log.stats.hints.default_rewards'),
                'value' => (int) ($stats['default_rewards'] ?? 0),
                'filters' => ['default_only' => 1],
                'link' => ['default_only' => 1],
            ],
            'latest' => [
                'label' => __('app.raf.reward_log.stats.latest'),
                'hint' => __('app.raf.reward_log.stats.hints.latest'),
                'value' => (int) ($stats['latest_granted_at'] ?? 0),
                'value_text' => format_datetime((int) ($stats['latest_granted_at'] ?? 0)),
                'format' => 'time',
                'filters' => ['sort' => 'granted_at', 'dir' => 'DESC'],
                'link' => [],
                'limit' => 1,
            ],
        ];
    }

    /**
     * 前端统计卡刷新载荷：时间型卡片必须带 value_text，
     * 否则浏览器会按本地时区重新格式化时间戳，与面板时区不一致。
     */
    private function rewardLogStatPayload(array $stats): array
    {
        $latest = (int) ($stats['latest_granted_at'] ?? 0);

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'recruiters' => (int) ($stats['recruiters'] ?? 0),
            'recruits' => (int) ($stats['recruits'] ?? 0),
            'default_rewards' => (int) ($stats['default_rewards'] ?? 0),
            'latest_granted_at' => $latest,
            'latest_granted_at_text' => format_datetime($latest),
        ];
    }

    private function rewardLogSourceLabels(): array
    {
        return [
            'login' => __('app.raf.reward_log.sources.login'),
            'level_change' => __('app.raf.reward_log.sources.level_change'),
        ];
    }

    /**
     * 渲染不含布局的 HTML 片段，供 AJAX 直接替换。
     */
    private function partial(string $view, array $data): string
    {
        return View::make($view, $data);
    }

    private function listCapabilities(): array
    {
        return [
            'list' => 'raf.list',
            'bind' => 'raf.bind',
            'unbind' => 'raf.unbind',
            'comment' => 'raf.comment',
        ];
    }

    public function apiBind(Request $request): Response
    {
        $this->requireBindCapability();
        $this->maybeSwitchServer($request);

        $accountId = $request->int('account_id', 0);
        $recruiterGuid = $request->int('recruiter_guid', 0);
        $force = $request->bool('force', false);

        if ($accountId <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.account_id_required'),
            ], 422);
        }

        if ($recruiterGuid <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.recruiter_guid_required'),
            ], 422);
        }

        $account = $this->repo()->findAccountSummary($accountId);
        if ($account === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.account_not_found'),
            ], 404);
        }

        $recruiter = $this->repo()->findRecruiterCharacter($recruiterGuid);
        if ($recruiter === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.recruiter_not_found'),
            ], 404);
        }

        if ((int) ($recruiter['account_id'] ?? 0) === $accountId) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.self_bind'),
            ], 422);
        }

        $command = ($force ? '.forcebindraf ' : '.bindraf ')
            . $accountId . ' ' . $recruiterGuid;
        $result = SoapCommandRunner::execute($command, [
            'server_id' => ServerContext::currentId(),
        ]);

        Audit::log('raf', $force ? 'force_bind' : 'bind', (string) $accountId, [
            'server_id' => ServerContext::currentId(),
            'command' => $command,
            'recruiter_guid' => $recruiterGuid,
            'success' => $result['success'],
            'output' => $result['output'] ?? '',
        ]);

        return $this->json([
            'success' => $result['success'],
            'message' => $result['message'] !== ''
                ? $result['message']
                : Lang::get('app.raf.feedback.bind_success'),
            'payload' => [
                'execution' => $result['execution'],
                'output' => $result['output'] ?? '',
            ],
        ], $result['success'] ? 200 : 422);
    }

    public function apiUnbind(Request $request): Response
    {
        $this->requireUnbindCapability();
        $this->maybeSwitchServer($request);

        $accountId = $request->int('account_id', 0);
        if ($accountId <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.account_id_required'),
            ], 422);
        }

        $link = $this->repo()->findLink($accountId);
        if ($link === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.link_not_found'),
            ], 404);
        }

        // 已失效或已完成的绑定不需要再解绑，避免产生误导性的审计记录
        if ((int) ($link['time_stamp'] ?? 0) <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.link_inactive'),
            ], 422);
        }

        $command = '.unbindraf ' . $accountId;
        $result = SoapCommandRunner::execute($command, [
            'server_id' => ServerContext::currentId(),
        ]);

        Audit::log('raf', 'unbind', (string) $accountId, [
            'server_id' => ServerContext::currentId(),
            'command' => $command,
            'success' => $result['success'],
            'output' => $result['output'] ?? '',
        ]);

        return $this->json([
            'success' => $result['success'],
            'message' => $result['message'] !== ''
                ? $result['message']
                : Lang::get('app.raf.feedback.unbind_success'),
            'payload' => [
                'execution' => $result['execution'],
                'output' => $result['output'] ?? '',
            ],
        ], $result['success'] ? 200 : 422);
    }

    public function apiComment(Request $request): Response
    {
        $this->requireCommentCapability();
        $this->maybeSwitchServer($request);

        $accountId = $request->int('account_id', 0);
        $comment = trim((string) $request->input('comment', ''));

        if ($accountId <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.account_id_required'),
            ], 422);
        }

        if (mb_strlen($comment) > 255) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.invalid_comment'),
            ], 422);
        }

        if ($this->repo()->findLink($accountId) === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.link_not_found'),
            ], 404);
        }

        $updated = $this->repo()->updateComment($accountId, $comment);
        if (!$updated && $comment !== '') {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.raf.errors.comment_save_failed'),
            ], 422);
        }

        Audit::log('raf', 'comment', (string) $accountId, [
            'server_id' => ServerContext::currentId(),
            'comment' => $comment,
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.raf.feedback.comment_saved'),
            'payload' => [
                'comment' => $comment,
            ],
        ]);
    }

    private function prepareListState(Request $request): array
    {
        $limit = $this->boundedInt(
            $request,
            'limit',
            (int) Config::get('raf.page_size', 30),
            10,
            200
        );

        return [
            'filters' => [
                'search' => $this->normalizedString($request, 'search'),
                'recruiter_guid' => $request->int('recruiter_guid', 0),
                'status' => $this->normalizedEnum(
                    $request,
                    'status',
                    ['all', 'active', 'completed', 'inactive', 'permanent'],
                    'all'
                ),
                'sort' => $this->normalizedEnum(
                    $request,
                    'sort',
                    [
                        'account_id',
                        'recruiter_guid',
                        'time_stamp',
                        'ip_abuse_counter',
                        'kick_counter',
                        'reward_level',
                    ],
                    'time_stamp'
                ),
                'dir' => $this->normalizedDirection($request, 'dir', 'DESC'),
                'limit' => $limit,
            ],
            'page' => $this->normalizedPage($request),
            'limit' => $limit,
        ];
    }

    private function prepareRewardLogState(Request $request): array
    {
        $limit = $this->boundedInt(
            $request,
            'log_limit',
            (int) Config::get('raf.page_size', 30),
            10,
            200
        );

        return [
            'filters' => [
                'search' => $this->normalizedString($request, 'log_search'),
                'level' => (int) $request->int('log_level', 0),
                'default_only' => $this->normalizedBoolFlag($request, 'log_default'),
                'source' => $this->normalizedEnum(
                    $request,
                    'log_source',
                    ['', 'login', 'level_change'],
                    ''
                ),
                'from' => $this->rewardLogDateBoundary(
                    $this->normalizedString($request, 'log_from'),
                    false
                ),
                'to' => $this->rewardLogDateBoundary(
                    $this->normalizedString($request, 'log_to'),
                    true
                ),
                'sort' => $this->normalizedEnum(
                    $request,
                    'log_sort',
                    [
                        'granted_at',
                        'reward_level',
                        'recruiter_guid',
                        'recruit_account_id',
                        'id',
                    ],
                    'granted_at'
                ),
                'dir' => $this->normalizedDirection($request, 'log_dir', 'DESC'),
                'limit' => $limit,
            ],
            'page' => $this->normalizedPage($request, 'log_page'),
            'limit' => $limit,
        ];
    }

    /**
     * 把日期输入转换为当天的起止时间戳（按面板时区）
     */
    private function rewardLogDateBoundary(string $value, bool $endOfDay): int
    {
        $value = trim($value);
        if ($value === '')
            return 0;

        $timestamp = strtotime($value . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));

        return $timestamp === false ? 0 : (int) $timestamp;
    }

    private function defaultRewardLogStats(): array
    {
        return [
            'total' => 0,
            'recruiters' => 0,
            'recruits' => 0,
            'default_rewards' => 0,
            'latest_granted_at' => 0,
        ];
    }

    private function defaultStats(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'completed' => 0,
            'inactive' => 0,
            'permanent_blocked' => 0,
            'rewarded_accounts' => 0,
        ];
    }
}