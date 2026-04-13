<?php

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Aegis;

use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Aegis\AegisActionHydrator;
use Acme\Panel\Domain\Aegis\AegisRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\Auth;
use Acme\Panel\Support\GameMaps;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\SoapExecutor;

class AegisController extends Controller
{
    private ?AegisRepository $repo = null;
    private ?AegisActionHydrator $mutations = null;

    private function requireDashboardCapability(): void
    {
        $this->requireCapability('aegis.dashboard');
    }

    private function requireOverviewCapability(): void
    {
        $this->requireCapability('aegis.overview');
    }

    private function requireOffensesCapability(): void
    {
        $this->requireCapability('aegis.offenses');
    }

    private function requireEventsCapability(): void
    {
        $this->requireCapability('aegis.events');
    }

    private function requirePlayerCapability(): void
    {
        $this->requireCapability('aegis.player');
    }

    private function requireActionsCapability(): void
    {
        $this->requireCapability('aegis.actions');
    }

    private function requireLogsCapability(): void
    {
        $this->requireCapability('aegis.logs');
    }

    private function repo(): AegisRepository
    {
        if ($this->repo === null) {
            $this->repo = new AegisRepository();
        }

        return $this->repo;
    }

    private function mutations(): AegisActionHydrator
    {
        if ($this->mutations === null) {
            $this->mutations = new AegisActionHydrator();
        }

        return $this->mutations;
    }

    private function maybeSwitchServer(Request $request): void
    {
        $this->switchServerAndRebind($request, $this->repo);
    }

    public function index(Request $request): Response
    {
        $this->requireDashboardCapability();

        $this->maybeSwitchServer($request);

        return $this->pageView('aegis.index', $this->serverViewData([
            'aegis_data' => [
                'options' => [
                    'cheat_types' => $this->cheatTypes(),
                    'evidence_levels' => $this->evidenceLevels(),
                    'punish_stages' => $this->punishStages(),
                    'status_filters' => $this->statusFilters(),
                    'manual_actions' => $this->manualActions(),
                ],
                'defaults' => [
                    'overview_days' => 7,
                    'events_days' => 7,
                    'per_page' => 20,
                    'log_limit' => 80,
                ],
            ],
        ]), [
            'module' => 'aegis',
            'capabilities' => [
                'dashboard' => 'aegis.dashboard',
                'overview' => 'aegis.overview',
                'offenses' => 'aegis.offenses',
                'events' => 'aegis.events',
                'player' => 'aegis.player',
                'actions' => 'aegis.actions',
                'logs' => 'aegis.logs',
            ],
        ]);
    }

    public function apiOverview(Request $request): Response
    {
        $this->requireOverviewCapability();

        $this->maybeSwitchServer($request);

        $state = $this->prepareOverviewState($request);
        $overview = $this->repo()->overview($state['days']);
        return $this->json([
            'success' => true,
            'payload' => $overview,
        ]);
    }

    public function apiOffenses(Request $request): Response
    {
        $this->requireOffensesCapability();

        $this->maybeSwitchServer($request);

        $state = $this->prepareOffenseListState($request);
        $pager = $this->repo()->searchOffenses($state['filters'], $state['page'], $state['per_page']);

        return $this->json([
            'success' => true,
            'payload' => [
                'page' => $pager->page,
                'pages' => $pager->pages,
                'total' => $pager->total,
                'items' => $pager->items,
            ],
        ]);
    }

    public function apiEvents(Request $request): Response
    {
        $this->requireEventsCapability();

        $this->maybeSwitchServer($request);

        $state = $this->prepareEventListState($request);
        $pager = $this->repo()->searchEvents($state['filters'], $state['page'], $state['per_page']);

        return $this->json([
            'success' => true,
            'payload' => [
                'page' => $pager->page,
                'pages' => $pager->pages,
                'total' => $pager->total,
                'items' => $pager->items,
            ],
        ]);
    }

    public function apiPlayer(Request $request): Response
    {
        $this->requirePlayerCapability();

        $this->maybeSwitchServer($request);

        $state = $this->preparePlayerLookupState($request);
        if ($state['guid'] <= 0 && $state['name'] === '') {
            return $this->json(['success' => false, 'message' => Lang::get('app.aegis.errors.player_required')], 422);
        }

        $payload = $this->repo()->findPlayerSnapshot(
            $state['guid'] > 0 ? $state['guid'] : null,
            $state['name'] !== '' ? $state['name'] : null
        );
        if ($payload === null) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.errors.not_found')], 404);
        }

        return $this->json([
            'success' => true,
            'payload' => $payload,
        ]);
    }

    public function apiAction(Request $request): Response
    {
        $this->requireActionsCapability();

        $this->maybeSwitchServer($request);

        $hydrated = $this->mutations()->action([
            'action' => $request->input('action', ''),
            'target' => $request->input('target', ''),
            'ip' => $request->ip(),
        ]);
        if (!$hydrated['valid'])
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);

        $action = (string) $hydrated['payload']['action'];
        $target = (string) $hydrated['payload']['target'];

        $resolvedTarget = null;
        if (!empty($hydrated['payload']['requires_target'])) {
            $resolvedTarget = $this->repo()->resolveCommandTarget($target);
            if ($resolvedTarget === null || ($resolvedTarget['name'] ?? '') === '') {
                return $this->json(['success' => false, 'message' => Lang::get('app.common.errors.not_found')], 404);
            }
        }

        $command = '.aegis ' . $action;
        if ($resolvedTarget !== null) {
            $command .= ' ' . $resolvedTarget['name'];
        }

        $executor = new SoapExecutor();
        $result = $executor->execute($command, [
            'server_id' => ServerContext::currentId(),
            'audit' => true,
        ]);

        if (($result['success'] ?? false) === true)
            $this->repo()->invalidateReadCaches();

        Audit::log('aegis', 'command', $action, [
            'server_id' => ServerContext::currentId(),
            'command' => $command,
            'target' => $resolvedTarget['name'] ?? null,
            'target_guid' => $resolvedTarget['guid'] ?? null,
            'success' => $result['success'] ?? false,
            'code' => $result['code'] ?? 'ok',
            'message' => $result['message'] ?? null,
        ]);

        return $this->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => ($result['success'] ?? false)
                ? Lang::get('app.aegis.actions.success')
                : ($result['message'] ?? Lang::get('app.aegis.actions.failure')),
            'payload' => [
                'command' => $command,
                'target' => $resolvedTarget,
                'execution' => $result,
            ],
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function apiLog(Request $request): Response
    {
        $this->requireLogsCapability();

        $this->maybeSwitchServer($request);
        $state = $this->prepareLogState($request);
        $payload = $this->repo()->recentLog($state['limit']);
        return $this->json([
            'success' => true,
            'payload' => $payload,
        ]);
    }

    private function prepareOverviewState(Request $request): array
    {
        return [
            'days' => $this->boundedInt($request, 'days', 7, 1, 365),
        ];
    }

    private function prepareOffenseListState(Request $request): array
    {
        return [
            'filters' => [
                'query' => $this->normalizedString($request, 'query'),
                'stage' => $this->normalizedString($request, 'stage'),
                'cheat_type' => $request->int('cheat_type', 0),
                'status' => $this->normalizedString($request, 'status', 'all') ?: 'all',
                'sort' => $this->normalizedString($request, 'sort', 'last_offense_desc') ?: 'last_offense_desc',
            ],
            'page' => $this->normalizedPage($request),
            'per_page' => $this->boundedInt($request, 'per_page', 20, 1, 200),
        ];
    }

    private function prepareEventListState(Request $request): array
    {
        return [
            'filters' => [
                'query' => $this->normalizedString($request, 'query'),
                'cheat_type' => $request->int('cheat_type', 0),
                'evidence_level' => $this->normalizedString($request, 'evidence_level'),
                'days' => $this->boundedInt($request, 'days', 7, 1, 365),
            ],
            'page' => $this->normalizedPage($request),
            'per_page' => $this->boundedInt($request, 'per_page', 20, 1, 200),
        ];
    }

    private function preparePlayerLookupState(Request $request): array
    {
        return [
            'guid' => $request->int('guid', 0),
            'name' => $this->normalizedString($request, 'name'),
        ];
    }

    private function prepareLogState(Request $request): array
    {
        return [
            'limit' => $this->boundedInt($request, 'limit', 80, 1, 500),
        ];
    }

    private function cheatTypes(): array
    {
        return [
            ['value' => 0, 'label' => Lang::get('app.aegis.enums.cheat_type.0')],
            ['value' => 1, 'label' => Lang::get('app.aegis.enums.cheat_type.1')],
            ['value' => 2, 'label' => Lang::get('app.aegis.enums.cheat_type.2')],
            ['value' => 3, 'label' => Lang::get('app.aegis.enums.cheat_type.3')],
            ['value' => 4, 'label' => Lang::get('app.aegis.enums.cheat_type.4')],
            ['value' => 5, 'label' => Lang::get('app.aegis.enums.cheat_type.5')],
            ['value' => 6, 'label' => Lang::get('app.aegis.enums.cheat_type.6')],
            ['value' => 7, 'label' => Lang::get('app.aegis.enums.cheat_type.7')],
            ['value' => 8, 'label' => Lang::get('app.aegis.enums.cheat_type.8')],
            ['value' => 9, 'label' => Lang::get('app.aegis.enums.cheat_type.9')],
        ];
    }

    private function evidenceLevels(): array
    {
        return [
            ['value' => 0, 'label' => Lang::get('app.aegis.enums.evidence_level.0')],
            ['value' => 1, 'label' => Lang::get('app.aegis.enums.evidence_level.1')],
            ['value' => 2, 'label' => Lang::get('app.aegis.enums.evidence_level.2')],
            ['value' => 3, 'label' => Lang::get('app.aegis.enums.evidence_level.3')],
        ];
    }

    private function punishStages(): array
    {
        return [
            ['value' => 0, 'label' => Lang::get('app.aegis.enums.punish_stage.0')],
            ['value' => 1, 'label' => Lang::get('app.aegis.enums.punish_stage.1')],
            ['value' => 2, 'label' => Lang::get('app.aegis.enums.punish_stage.2')],
            ['value' => 3, 'label' => Lang::get('app.aegis.enums.punish_stage.3')],
            ['value' => 4, 'label' => Lang::get('app.aegis.enums.punish_stage.4')],
            ['value' => 5, 'label' => Lang::get('app.aegis.enums.punish_stage.5')],
        ];
    }

    private function statusFilters(): array
    {
        return [
            ['value' => 'all', 'label' => Lang::get('app.aegis.filters.status.all')],
            ['value' => 'tracked', 'label' => Lang::get('app.aegis.filters.status.tracked')],
            ['value' => 'debuffed', 'label' => Lang::get('app.aegis.filters.status.debuffed')],
            ['value' => 'jailed', 'label' => Lang::get('app.aegis.filters.status.jailed')],
            ['value' => 'banned', 'label' => Lang::get('app.aegis.filters.status.banned')],
            ['value' => 'permanent', 'label' => Lang::get('app.aegis.filters.status.permanent')],
        ];
    }

    private function manualActions(): array
    {
        return [
            ['value' => 'clear', 'label' => Lang::get('app.aegis.manual.actions.clear'), 'needs_target' => true],
            ['value' => 'delete', 'label' => Lang::get('app.aegis.manual.actions.delete'), 'needs_target' => true],
            ['value' => 'reload', 'label' => Lang::get('app.aegis.manual.actions.reload'), 'needs_target' => false],
            ['value' => 'purge', 'label' => Lang::get('app.aegis.manual.actions.purge'), 'needs_target' => false],
        ];
    }
}