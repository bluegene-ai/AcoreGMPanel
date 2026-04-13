<?php
/**
 * File: app/Http/Controllers/Mail/MailController.php
 * Purpose: Defines class MailController for the app/Http/Controllers/Mail module.
 * Classes:
 *   - MailController
 * Functions:
 *   - __construct()
 *   - index()
 *   - apiList()
 *   - apiView()
 *   - apiMarkRead()
 *   - apiMarkReadBulk()
 *   - apiDelete()
 *   - apiDeleteBulk()
 *   - apiStats()
 *   - apiLogs()
 */

namespace Acme\Panel\Http\Controllers\Mail;

use Acme\Panel\Core\{Controller,Request,Response,Lang};
use Acme\Panel\Domain\Mail\MailMutationHydrator;
use Acme\Panel\Domain\Mail\MailRepository;
use Acme\Panel\Support\{Auth,Audit,ServerContext,ServerList};

class MailController extends Controller
{
    private ?MailRepository $repo = null;
    private ?MailMutationHydrator $mutations = null;
    private ?\Throwable $repoError = null;

    private function mutations(): MailMutationHydrator
    {
        if ($this->mutations === null) {
            $this->mutations = new MailMutationHydrator();
        }

        return $this->mutations;
    }

    private function requireListCapability(): void
    {
        $this->requireCapability('mail.list');
    }

    private function requireViewCapability(): void
    {
        $this->requireCapability('mail.view');
    }

    private function requireMarkReadCapability(): void
    {
        $this->requireCapability('mail.mark_read');
    }

    private function requireDeleteCapability(): void
    {
        $this->requireCapability('mail.delete');
    }

    private function requireStatsCapability(): void
    {
        $this->requireCapability('mail.stats');
    }

    private function requireLogsCapability(): void
    {
        $this->requireCapability('mail.logs');
    }

    public function __construct(){
        try { $this->repo = new MailRepository(); }
        catch(\Throwable $e){ $this->repoError=$e; error_log('[MAIL_REPO_CTOR_FATAL] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine()); }
    }

    public function index(Request $request): Response
    {
        try {
            if($request->input('debug')==='ping'){
                return $this->response(200,'PING OK server='.ServerContext::currentId().' time='.date('H:i:s'));
            }


            foreach(['filter_sender','filter_receiver','filter_subject'] as $fk){
                $val = $request->input($fk,'');
                if(is_string($val) && (str_contains($val,'<?') || str_contains($val,'?>'))){

                    $_GET[$fk] = '';
                }
            }
            if($this->repoError){
                $heading = Lang::get('app.mail.errors.init_failed');
                return $this->response(500,'<h2>'.$heading.'</h2><pre style="white-space:pre-wrap;font-size:12px">'.htmlspecialchars($this->repoError->getMessage().'\n'.$this->repoError->getFile().':'.$this->repoError->getLine()).'</pre>');
            }
            $this->requireListCapability();

            $this->switchServerAndRefresh($request, function (): void { $this->repo=new MailRepository(); });

            $state = $this->prepareMailListState($request);
            $offset=($state['page']-1)*$state['limit'];
            $res=$this->repo->search($state['filters'],$state['limit'],$offset,$state['sort'],$state['dir']);
            $pages=$res['total']? (int)ceil($res['total']/$state['limit']) : 1;
            $pager=(object)[
                'items' => $res['rows'],
                'page' => $state['page'],
                'pages' => $pages,
                'total' => $res['total'],
                'limit' => $state['limit'],
            ];
            return $this->pageView('mail.index', $this->listViewData($pager, $state['filters'], [
                'rows' => $res['rows'],
                'total' => $res['total'],
                'page' => $state['page'],
                'pages' => $pages,
                'limit' => $state['limit'],
                'sort' => $state['sort'],
                'dir' => $state['dir'],
            ]), [
                'capabilities' => [
                    'list' => 'mail.list',
                    'view' => 'mail.view',
                    'mark_read' => 'mail.mark_read',
                    'delete' => 'mail.delete',
                    'stats' => 'mail.stats',
                    'logs' => 'mail.logs',
                ],
            ]);
        } catch(\Throwable $e) {
            error_log('[MAIL_INDEX_FATAL] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
            $heading = Lang::get('app.mail.errors.exception');
            return $this->response(500,'<h2>'.$heading.'</h2><pre style="white-space:pre-wrap;font-size:12px">'.htmlspecialchars($e->getMessage().'\n'.$e->getFile().':'.$e->getLine())."</pre>");
        }
    }


    public function apiList(Request $request): Response
    {
    $this->requireListCapability();
        $state = $this->prepareMailListState($request);
        $offset=($state['page']-1)*$state['limit'];
        $res=$this->repo->search($state['filters'],$state['limit'],$offset,$state['sort'],$state['dir']);
        $pages=$res['total']? (int)ceil($res['total']/$state['limit']) : 1;
        return $this->json(['success'=>true]+$res+['page'=>$state['page'],'pages'=>$pages,'limit'=>$state['limit'],'server_id'=>ServerContext::currentId()]);
    }

    private function prepareMailListState(Request $request): array
    {
        return [
            'filters' => [
                'sender' => $this->normalizedString($request, 'filter_sender'),
                'receiver' => $this->normalizedString($request, 'filter_receiver'),
                'subject' => $this->normalizedString($request, 'filter_subject'),
                'unread' => $this->normalizedEnum($request, 'filter_unread', ['', '1'], ''),
                'has_items' => $this->normalizedEnum($request, 'filter_has_items', ['', '1'], ''),
                'expiring' => $this->normalizedString($request, 'filter_expiring'),
            ],
            'page' => $this->normalizedPage($request),
            'limit' => $this->boundedInt($request, 'limit', 50, 10, 200),
            'sort' => $this->normalizedString($request, 'sort', 'id') ?: 'id',
            'dir' => $this->normalizedDirection($request, 'dir', 'DESC'),
        ];
    }

    public function apiView(Request $request): Response
    {
        $this->requireViewCapability();
        $state = $this->prepareMailViewState($request);
        $hydrated = $state['hydrated'];
        if (!$hydrated['valid'])
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);

        $id = (int) $hydrated['payload']['mail_id'];
        $row = $this->repo->getWithItems($id);
        if (!$row)
            return $this->json(['success' => false, 'message' => Lang::get('app.common.errors.not_found')], 404);

        Audit::log('mail', 'view', (string) $id, ['receiver' => $row['receiver'] ?? null, 'srv' => ServerContext::currentId()]);
        return $this->json(['success' => true, 'mail' => $row, 'server_id' => ServerContext::currentId()]);
    }

    public function apiMarkRead(Request $request): Response
    {
        $this->requireMarkReadCapability();
        $hydrated = $this->mutations()->mailId([
            'mail_id' => $request->input('mail_id', 0),
            'ip' => $request->ip(),
        ]);
        if (!$hydrated['valid'])
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);

        $id = (int) $hydrated['payload']['mail_id'];
        $ok = $this->repo->markRead($id);
        Audit::log('mail', 'mark_read', (string) $id, ['ok' => $ok, 'srv' => ServerContext::currentId()]);
        return $this->json([
            'success' => $ok,
            'message' => $ok ? Lang::get('app.mail.api.success.marked_read') : Lang::get('app.mail.api.success.no_changes'),
            'server_id' => ServerContext::currentId(),
        ]);
    }

    public function apiMarkReadBulk(Request $request): Response
    {
        $this->requireMarkReadCapability();
        $hydrated = $this->mutations()->mailIds([
            'ids' => $request->input('ids', ''),
            'ip' => $request->ip(),
        ]);
        if (!$hydrated['valid'])
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);

        $ids = $hydrated['payload']['ids'];
        $res = $this->repo->markReadBulk($ids);
        Audit::log('mail', 'mark_read_bulk', implode(',', $ids), ['aff' => $res['affected'], 'srv' => ServerContext::currentId()]);
        return $this->json(['success' => true, 'message' => Lang::get('app.mail.api.success.bulk_marked', ['count' => $res['affected']]), 'server_id' => ServerContext::currentId()] + $res);
    }

    public function apiDelete(Request $request): Response
    {
        $this->requireDeleteCapability();
        $hydrated = $this->mutations()->mailId([
            'mail_id' => $request->input('mail_id', 0),
            'ip' => $request->ip(),
        ]);
        if (!$hydrated['valid'])
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);

        $id = (int) $hydrated['payload']['mail_id'];
        $ok = $this->repo->delete($id);
        Audit::log('mail', 'delete', (string) $id, ['ok' => $ok, 'srv' => ServerContext::currentId()]);
        return $this->json([
            'success' => $ok,
            'message' => $ok ? Lang::get('app.mail.api.success.deleted_single') : Lang::get('app.mail.api.errors.delete_restricted'),
            'server_id' => ServerContext::currentId(),
        ]);
    }

    public function apiDeleteBulk(Request $request): Response
    {
        $this->requireDeleteCapability();
        $hydrated = $this->mutations()->mailIds([
            'ids' => $request->input('ids', ''),
            'ip' => $request->ip(),
        ]);
        if (!$hydrated['valid'])
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);

        $ids = $hydrated['payload']['ids'];
        $res = $this->repo->deleteBulk($ids);
        Audit::log('mail', 'delete_bulk', implode(',', $ids), ['del' => count($res['deleted']), 'blocked' => count($res['blocked']), 'srv' => ServerContext::currentId()]);
        $msg = Lang::get('app.mail.api.success.bulk_deleted', ['count' => count($res['deleted'])]);
        if ($res['blocked'])
            $msg .= Lang::get('app.mail.api.success.bulk_deleted_blocked_suffix', ['count' => count($res['blocked'])]);
        return $this->json(['success' => true, 'message' => $msg, 'server_id' => ServerContext::currentId()] + $res);
    }

    public function apiStats(Request $request): Response
    { $this->requireStatsCapability(); $stat=$this->repo->stats(); return $this->json(['success'=>true,'server_id'=>ServerContext::currentId()]+$stat); }

    public function apiLogs(Request $request): Response
    {
        $this->requireLogsCapability();
        if(!$this->repo){
            $msg = $this->repoError ? $this->repoError->getMessage() : Lang::get('app.mail.api.errors.repository_not_ready');
            return $this->json(['success'=>false,'message'=>$msg],500);
        }
        $state = $this->prepareMailLogState($request);
        $res = $this->repo->tailLog($state['type'],$state['limit']);
        return $this->json($res, $res['success'] ? 200 : 422);
    }

    private function prepareMailViewState(Request $request): array
    {
        return [
            'hydrated' => $this->mutations()->mailId([
                'mail_id' => $request->input('mail_id', 0),
                'ip' => $request->ip(),
            ]),
        ];
    }

    private function prepareMailLogState(Request $request): array
    {
        return [
            'type' => $this->normalizedString($request, 'type', 'sql') ?: 'sql',
            'limit' => max(1, (int) $request->input('limit', 50)),
        ];
    }
}

