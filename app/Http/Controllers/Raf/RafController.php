<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Raf;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
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
        $pager = new Paginator([], 0, $state['page'], $state['limit']);
        $stats = $this->defaultStats();
        $error = null;
        $schemaStatus = $this->repo()->schemaStatus();

        if (!$schemaStatus['ready']) {
            $error = Lang::get('app.raf.errors.schema_missing', [
                'tables' => implode(', ', $schemaStatus['missing_tables']),
            ]);
        } else {
            try {
                $pager = $this->repo()->listLinks(
                    $state['filters'],
                    $state['page'],
                    $state['limit']
                );
            } catch (Throwable $exception) {
                $error = Lang::get('app.raf.errors.load_failed');
            }

            // 统计单独兜底：统计失败不应把已经取到的列表也一并清空
            try {
                $stats = $this->repo()->stats($state['filters']);
            } catch (Throwable $exception) {
                $stats = $this->defaultStats();
            }
        }

        // 奖励发放记录：独立加载，失败时只影响该区块
        $logState = $this->prepareRewardLogState($request);
        $logPager = new Paginator([], 0, $logState['page'], $logState['limit']);
        $logStats = $this->defaultRewardLogStats();
        $logError = null;

        if (!$schemaStatus['reward_log_ready']) {
            $logError = Lang::get('app.raf.errors.reward_log_missing');
        } else {
            try {
                $logPager = $this->repo()->listRewardLogs(
                    $logState['filters'],
                    $logState['page'],
                    $logState['limit']
                );
                $logStats = $this->repo()->rewardLogStats($logState['filters']);
            } catch (Throwable $exception) {
                $logError = Lang::get('app.raf.errors.reward_log_load_failed');
            }
        }

        $server = ServerContext::server();
        $realmId = $this->repo()->currentRealmId();

        return $this->pageView('raf.index', $this->listViewData($pager, $state['filters'], [
            'raf_stats' => $stats,
            'raf_error' => $error,
            'raf_schema_missing' => !$schemaStatus['ready'],
            'raf_reward_log' => [
                'pager' => $logPager,
                'stats' => $logStats,
                'error' => $logError,
                'ready' => (bool) $schemaStatus['reward_log_ready'],
                'filters' => $logState['filters'],
                'page' => $logState['page'],
                'limit' => $logState['limit'],
            ],
            'raf_defaults' => [
                'server_name' => (string) ($server['name'] ?? ''),
                'realm_id' => $realmId,
                'page_size_options' => Config::get(
                    'raf.page_size_options',
                    [20, 30, 50, 100]
                ),
            ],
        ]), [
            'module' => 'raf',
            'capabilities' => [
                'list' => 'raf.list',
                'bind' => 'raf.bind',
                'unbind' => 'raf.unbind',
                'comment' => 'raf.comment',
            ],
            'header' => [
                'intro' => __('app.raf.intro'),
                'note' => __('app.raf.scope_note', [
                    'server' => (string) ($server['name'] ?? ''),
                    'realm' => (string) $realmId,
                ]),
            ],
            'meta' => [
                'title' => __('app.raf.page_title'),
            ],
        ]);
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