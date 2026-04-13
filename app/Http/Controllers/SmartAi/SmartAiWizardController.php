<?php
/**
 * File: app/Http/Controllers/SmartAi/SmartAiWizardController.php
 * Purpose: Defines class SmartAiWizardController for the app/Http/Controllers/SmartAi module.
 * Classes:
 *   - SmartAiWizardController
 * Functions:
 *   - __construct()
 *   - index()
 *   - apiPreview()
 */

namespace Acme\Panel\Http\Controllers\SmartAi;

use Acme\Panel\Core\{Controller,Lang,Request,Response,Url};
use Acme\Panel\Domain\SmartAi\SmartAiWizardService;
use Acme\Panel\Support\{Auth,ServerContext,ServerList};

class SmartAiWizardController extends Controller
{
    private SmartAiWizardService $service;

    private function requirePreviewCapability(): void
    {
        $this->requireCapability('content.preview');
    }

    public function __construct()
    {
        $this->service = new SmartAiWizardService();
    }

    public function index(Request $request): Response
    {
        $this->requirePreviewCapability();

        $this->switchServerContext($request);

        $catalog = $this->service->catalog();

        return $this->pageView('smartai.index', $this->serverViewData([
            'catalog' => $catalog,
        ]));
    }

    public function apiPreview(Request $request): Response
    {
        $this->requirePreviewCapability();

        $payload = $request->input('payload', []);
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $payload = $decoded;
            } else {
                $payload = [];
            }
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        $result = $this->service->build($payload);
        $status = $result['success'] ?? false ? 200 : 422;
        return $this->json($result, $status);
    }
}

