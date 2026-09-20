<?php
/**
 * File: app/Http/Controllers/Supervisor/SupervisorController.php
 * Purpose: "Supervisor" page: state of the worldserver/authserver watchdog plus start/stop/restart.
 * Classes:
 *   - SupervisorController
 * Functions:
 *   - __construct()
 *   - index()
 *   - apiStatus()
 *   - apiLog()
 *   - apiCommand()
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
        $this->manager = new SupervisorManager();
    }

    public function index(Request $request): Response
    {
        $this->requireCapability('supervisor.view');

        $state = $this->manager->status();
        $log = $this->manager->logTail();

        return $this->pageView('supervisor.index', [
            'state' => $state,
            'log_lines' => $log,
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

        $state = $this->manager->status();
        $payload = [
            'success' => true,
            'state' => $state,
        ];

        if ($this->normalizedBoolFlag($request, 'with_log')) {
            $lines = $this->boundedInt($request, 'lines', 200, 20, 2000);
            $payload['log'] = $this->manager->logTail($lines);
        }

        return $this->json($payload);
    }

    public function apiLog(Request $request): Response
    {
        $this->requireCapability('supervisor.view');

        $lines = $this->boundedInt($request, 'lines', 200, 20, 2000);

        return $this->json([
            'success' => true,
            'lines' => $this->manager->logTail($lines),
        ]);
    }

    public function apiCommand(Request $request): Response
    {
        $this->requireCapability('supervisor.control');

        $action = $this->normalizedString($request, 'action');
        $target = $this->normalizedString($request, 'target', 'all');
        if ($target === '') {
            $target = 'all';
        }

        $result = $this->manager->dispatch($action, $target);

        Audit::log('supervisor', $action !== '' ? $action : 'unknown', $target, [
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
            'message' => (string) ($result['message'] ?? Lang::get('app.common.api.success.generic')),
            'state' => $this->manager->status(),
        ]);
    }
}
