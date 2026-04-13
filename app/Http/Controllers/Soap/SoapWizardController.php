<?php
/**
 * File: app/Http/Controllers/Soap/SoapWizardController.php
 * Purpose: Defines class SoapWizardController for the app/Http/Controllers/Soap module.
 * Classes:
 *   - SoapWizardController
 * Functions:
 *   - __construct()
 *   - index()
 *   - apiExecute()
 */

namespace Acme\Panel\Http\Controllers\Soap;

use Acme\Panel\Core\{Controller,Lang,Request,Response};
use Acme\Panel\Domain\Soap\SoapWizardService;
use Acme\Panel\Support\{Auth,ServerContext,ServerList,SoapExecutor};

class SoapWizardController extends Controller
{
    private SoapWizardService $service;
    private SoapExecutor $executor;

    private function requireCatalogCapability(): void
    {
        $this->requireCapability('soap.catalog');
    }

    private function requireExecuteCapability(): void
    {
        $this->requireCapability('soap.execute');
    }

    public function __construct()
    {
        $this->service = new SoapWizardService();
        $this->executor = new SoapExecutor();
    }

    public function index(Request $request): Response
    {
        $this->requireCatalogCapability();

        $this->switchServerContext($request);

        $catalog = [
            'metadata' => $this->service->metadata(),
            'categories' => $this->service->categories(),
        ];

        return $this->pageView('soap.index', $this->serverViewData([
            'catalog' => $catalog,
        ]), [
            'capabilities' => [
                'catalog' => 'soap.catalog',
                'execute' => 'soap.execute',
            ],
        ]);
    }

    public function apiExecute(Request $request): Response
    {
        $this->requireExecuteCapability();

        $commandKey = (string)$request->input('command_key', '');
        $rawArguments = $request->input('arguments', []);
        $arguments = [];
        if (is_array($rawArguments)) {
            $arguments = $rawArguments;
        } elseif (is_string($rawArguments) && $rawArguments !== '') {
            $decoded = json_decode($rawArguments, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $arguments = $decoded;
            }
        }

        $build = $this->service->buildCommand($commandKey, $arguments);
        if (!$build['success']) {
            return $this->json([
                'success' => false,
                'message' => $build['message'] ?? Lang::get('app.soap.api.errors.invalid_arguments'),
                'errors' => $build['errors'] ?? [],
            ], 422);
        }

        $serverId = $this->switchServerContext($request, null, 'server_id');

        $result = $this->executor->execute($build['command'], [
            'server_id' => $serverId,
            'audit' => true,
        ]);

        $successFallback = Lang::get('app.soap.modules.soap.feedback.execute_success');
        $failureFallback = Lang::get('app.soap.modules.soap.feedback.execute_failed');

        $payload = [
            'success' => $result['success'] ?? false,
            'command' => $build['command'],
            'output' => $result['output'] ?? '',
            'message' => $result['success']
                ? ($result['output'] ?? $successFallback)
                : ($result['message'] ?? $result['code'] ?? $failureFallback),
            'code' => $result['code'] ?? ($result['success'] ? 'ok' : null),
            'time_ms' => $result['time_ms'] ?? null,
            'retried' => $result['retried'] ?? 0,
            'fault' => $result['fault'] ?? null,
            'definition' => [
                'key' => $build['definition']['key'] ?? $commandKey,
                'name' => $build['definition']['name'] ?? $commandKey,
                'risk' => $build['definition']['risk'] ?? 'unknown',
                'requires_target' => $build['definition']['requires_target'] ?? false,
                'notes' => $build['definition']['notes'] ?? [],
            ],
            'category' => $build['category'],
            'resolved_args' => $build['resolved_args'],
            'execution' => $result,
        ];

        return $this->json($payload, ($result['success'] ?? false) ? 200 : 422);
    }
}

