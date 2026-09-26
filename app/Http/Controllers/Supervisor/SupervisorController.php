<?php
/**
 * File: app/Http/Controllers/Supervisor/SupervisorController.php
 * Purpose: "Supervisor" page: state of the worldserver/authserver watchdog plus start/stop/restart.
 */

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Supervisor;

use Acme\Panel\Core\{Controller, Lang, Request, Response};
use Acme\Panel\Domain\Supervisor\SupervisorManager;
use Acme\Panel\Support\Audit;

final class SupervisorController extends Controller
{
    private SupervisorManager $manager;

    public function __construct()
    {
        // default instance; APIs/page switch with the ?instance=<id> parameter
        $this->manager = new SupervisorManager();
    }

    /**
     * Manager for the requested instance, or null when the id is not configured: never silently falls
     * back, a command must not reach another realm's supervisor.
     */
    private function manager(Request $request): ?SupervisorManager
    {
        $instance = trim((string) $request->input('instance', ''));
        if ($instance === '') {
            return $this->manager;
        }

        $manager = new SupervisorManager($instance);

        return $manager->isValidInstance() ? $manager : null;
    }

    private function unknownInstance(Request $request): Response
    {
        return $this->json([
            'success' => false,
            'message' => Lang::get('app.supervisor.errors.unknown_instance', [
                'instance' => trim((string) $request->input('instance', '')),
            ]),
        ], 404);
    }

    public function index(Request $request): Response
    {
        $this->requireCapability('supervisor.view');

        // a stale bookmark must still render the page: fall back to the first instance
        $manager = $this->manager($request) ?? $this->manager;

        return $this->pageView('supervisor.index', [
            'state' => $manager->status(),
            'log_lines' => $manager->logTail(),
            'instances' => $manager->summaries(),
            'current_instance' => $manager->instanceId(),
        ], [
            'capabilities' => [
                'view' => 'supervisor.view',
                'control' => 'supervisor.control',
            ],
        ]);
    }

    public function apiStatus(Request $request): Response
    {
        $this->requireCapability('supervisor.view');

        $manager = $this->manager($request);
        if ($manager === null) {
            return $this->unknownInstance($request);
        }

        $state = $manager->status();
        $payload = [
            'success' => true,
            'state' => $state,
            'instances' => $manager->summaries(),
        ];

        if ($this->normalizedBoolFlag($request, 'with_log')) {
            $lines = $this->boundedInt($request, 'lines', 200, 20, 2000);
            $payload['log'] = $manager->logTail($lines);
        }

        return $this->json($payload);
    }

    public function apiLog(Request $request): Response
    {
        $this->requireCapability('supervisor.view');

        $manager = $this->manager($request);
        if ($manager === null) {
            return $this->unknownInstance($request);
        }

        $lines = $this->boundedInt($request, 'lines', 200, 20, 2000);

        return $this->json([
            'success' => true,
            'lines' => $manager->logTail($lines),
        ]);
    }

    public function apiCommand(Request $request): Response
    {
        $this->requireCapability('supervisor.control');

        $manager = $this->manager($request);
        if ($manager === null) {
            return $this->unknownInstance($request);
        }

        $action = $this->normalizedString($request, 'action');
        $target = $this->normalizedString($request, 'target', 'all');
        if ($target === '') {
            $target = 'all';
        }

        $instance = $manager->instanceId();
        $result = $manager->dispatch($action, $target);

        Audit::log('supervisor', $action !== '' ? $action : 'unknown', $target, [
            'instance' => $instance,
            'success' => (bool) ($result['success'] ?? false),
            'id' => (string) ($result['id'] ?? ''),
            'message' => (string) ($result['message'] ?? ''),
        ]);

        if (!$result['success']) {
            return $this->json([
                'success' => false,
                'message' => (string) ($result['message'] ?? Lang::get('app.common.api.errors.request_failed')),
            ], 422);
        }

        return $this->json([
            'success' => true,
            'id' => (string) ($result['id'] ?? ''),
            'action' => (string) ($result['action'] ?? $action),
            'target' => (string) ($result['target'] ?? $target),
            'instance' => $instance,
            'message' => (string) ($result['message'] ?? Lang::get('app.common.api.success.generic')),
            'state' => $manager->status(),
            'instances' => $manager->summaries(),
        ]);
    }
}
