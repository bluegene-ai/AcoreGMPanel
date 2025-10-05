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
use Acme\Panel\Support\Auth;
use InvalidArgumentException;

class LogsController extends Controller
{
    private LogManager $manager;

    public function __construct()
    {
        $this->manager = new LogManager();
    }

    public function index(Request $request): Response
    {
    if(!Auth::check()) return $this->redirect('/account/login');
        $modules = $this->manager->modules();
        $defaults = $this->manager->defaults();
        return $this->view('logs.index', [
            'title' => Lang::get('app.logs.index.page_title'),
            'modules' => $modules,
            'defaults' => $defaults,
        ]);
    }

    public function apiList(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401);
        $defaults = $this->manager->defaults();
        $module = (string)$request->input('module', $defaults['module'] ?? '');
        $type = (string)$request->input('type', $defaults['type'] ?? '');
        $limit = (int)$request->input('limit', $defaults['limit'] ?? 200);
        $limit = $this->manager->sanitizeLimit($limit);
        if(!$this->manager->getType($module, $type)){
            return $this->json(['success'=>false,'message'=>Lang::get('app.logs.index.errors.invalid_module')],422);
        }
        try {
            $result = $this->manager->tail($module, $type, $limit);
        } catch(InvalidArgumentException $e) {
            return $this->json(['success'=>false,'message'=>Lang::get('app.logs.index.errors.invalid_module')],422);
        } catch(\Throwable $e) {
            return $this->json(['success'=>false,'message'=>Lang::get('app.logs.index.errors.read_failed',['message'=>$e->getMessage()])],500);
        }
        return $this->json($result + ['success'=>true]);
    }
}

