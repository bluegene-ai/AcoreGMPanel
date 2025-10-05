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

    public function __construct()
    {
        $this->service = new SmartAiWizardService();
    }

    public function index(Request $request): Response
    {
        if (!Auth::check()) {
            $login = Url::to('/account/login');
            return $this->redirect($login);
        }

        $requestedServer = $request->input('server', null);
        if ($requestedServer !== null) {
            $sid = (int)$requestedServer;
            if ($sid !== ServerContext::currentId() && ServerList::valid($sid)) {
                ServerContext::set($sid);
            }
        }

        $catalog = $this->service->catalog();

        return $this->view('smartai.index', [
            'title' => Lang::get('app.smartai.page_title'),
            'catalog' => $catalog,
            'current_server' => ServerContext::currentId(),
            'servers' => ServerList::options(),
        ]);
    }

    public function apiPreview(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.common.api.errors.unauthorized'),
            ], 401);
        }

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

