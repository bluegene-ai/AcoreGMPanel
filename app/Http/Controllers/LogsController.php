<?php
/**
 * File: app/Http/Controllers/LogsController.php
 * Purpose: Defines class LogsController for the app/Http/Controllers module.
 * Classes:
 *   - LogsController
 * Functions:
 *   - __construct()
 *   - index()
 *   - apiList()
 */

namespace Acme\Panel\Http\Controllers;

use Acme\Panel\Core\{Controller,Lang,Request,Response};
use Acme\Panel\Domain\Logs\LogManager;
use InvalidArgumentException;

class LogsController extends Controller
{
    private LogManager $manager;

    private function requireCatalogCapability(): void
    {
        $this->requireCapability('logs.catalog');
    }

    private function requireReadCapability(): void
    {
        $this->requireCapability('logs.read');
    }

    public function __construct()
    {
        $this->manager = new LogManager();
    }

    public function index(Request $request): Response
    {
        $this->requireCatalogCapability();
        $modules = $this->manager->modules();
        $defaults = $this->manager->defaults();
        return $this->pageView('logs.index', [
            'modules' => $modules,
            'defaults' => $defaults,
        ], [
            'capabilities' => [
                'catalog' => 'logs.catalog',
                'read' => 'logs.read',
            ],
        ]);
    }

    public function apiList(Request $request): Response
    {
        $this->requireReadCapability();
        $state = $this->prepareLogsListState($request);
        if(!$this->manager->getType($state['module'], $state['type'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.logs.index.errors.invalid_module')],422);
        }
        try {
            $result = $this->manager->tail($state['module'], $state['type'], $state['limit']);
        } catch(InvalidArgumentException $e) {
            return $this->json(['success'=>false,'message'=>Lang::get('app.logs.index.errors.invalid_module')],422);
        } catch(\Throwable $e) {
            return $this->json(['success'=>false,'message'=>Lang::get('app.logs.index.errors.read_failed',['message'=>$e->getMessage()])],500);
        }
        return $this->json($result + ['success'=>true]);
    }

    private function prepareLogsListState(Request $request): array
    {
        $defaults = $this->manager->defaults();

        return [
            'module' => $this->normalizedString($request, 'module', (string) ($defaults['module'] ?? '')),
            'type' => $this->normalizedString($request, 'type', (string) ($defaults['type'] ?? '')),
            'limit' => $this->manager->sanitizeLimit(
                $this->boundedInt($request, 'limit', (int) ($defaults['limit'] ?? 200), 1, 5000)
            ),
        ];
    }
}

