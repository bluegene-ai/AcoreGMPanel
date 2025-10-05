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
use Acme\Panel\Domain\Mail\MailRepository;
use Acme\Panel\Support\{Auth,Audit,ServerContext,ServerList};

class MailController extends Controller
{
    private ?MailRepository $repo = null;
    private ?\Throwable $repoError = null;
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
            if(!Auth::check()) return $this->redirect('/account/login');

            $reqServer=$request->input('server',null); if($reqServer!==null){ $sid=(int)$reqServer; if(ServerContext::currentId()!==$sid && ServerList::valid($sid)){ ServerContext::set($sid); $this->repo=new MailRepository(); } }

            $filters=[
                'sender'=>$request->input('filter_sender',''),
                'receiver'=>$request->input('filter_receiver',''),
                'subject'=>$request->input('filter_subject',''),
                'unread'=>$request->input('filter_unread',''),
                'has_items'=>$request->input('filter_has_items',''),
                'expiring'=>$request->input('filter_expiring','')
            ];
            $page=max(1,(int)$request->input('page',1));
            $limit=max(10,min(200,(int)$request->input('limit',50)));
            $offset=($page-1)*$limit;
            $sort=$request->input('sort','id');
            $dir=$request->input('dir','DESC');
            $res=$this->repo->search($filters,$limit,$offset,$sort,$dir);
            $pages=$res['total']? (int)ceil($res['total']/$limit) : 1;
            return $this->view('mail.index',[ 'title'=>Lang::get('app.mail.page_title'),'rows'=>$res['rows'],'total'=>$res['total'],'page'=>$page,'pages'=>$pages,'limit'=>$limit,'filters'=>$filters,'sort'=>$sort,'dir'=>strtoupper($dir)==='ASC'?'ASC':'DESC','current_server'=>ServerContext::currentId(),'servers'=>ServerList::options() ]);
        } catch(\Throwable $e) {
            error_log('[MAIL_INDEX_FATAL] '.$e->getMessage().' @'.$e->getFile().':'.$e->getLine());
            $heading = Lang::get('app.mail.errors.exception');
            return $this->response(500,'<h2>'.$heading.'</h2><pre style="white-space:pre-wrap;font-size:12px">'.htmlspecialchars($e->getMessage().'\n'.$e->getFile().':'.$e->getLine())."</pre>");
        }
    }


    public function apiList(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401);
        $filters=[
            'sender'=>$request->input('filter_sender',''),
            'receiver'=>$request->input('filter_receiver',''),
            'subject'=>$request->input('filter_subject',''),
            'unread'=>$request->input('filter_unread',''),
            'has_items'=>$request->input('filter_has_items',''),
            'expiring'=>$request->input('filter_expiring','')
        ];
        $page=max(1,(int)$request->input('page',1));
        $limit=max(10,min(200,(int)$request->input('limit',50)));
        $offset=($page-1)*$limit;
        $sort=$request->input('sort','id');
        $dir=$request->input('dir','DESC');
        $res=$this->repo->search($filters,$limit,$offset,$sort,$dir);
        $pages=$res['total']? (int)ceil($res['total']/$limit) : 1;
        return $this->json(['success'=>true]+$res+['page'=>$page,'pages'=>$pages,'limit'=>$limit,'server_id'=>ServerContext::currentId()]);
    }

    public function apiView(Request $request): Response
    { if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401); $id=(int)$request->input('mail_id',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.invalid_id')],422); $row=$this->repo->getWithItems($id); if(!$row) return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); Audit::log('mail','view',(string)$id,['receiver'=>$row['receiver']??null,'srv'=>ServerContext::currentId()]); return $this->json(['success'=>true,'mail'=>$row,'server_id'=>ServerContext::currentId()]); }

    public function apiMarkRead(Request $request): Response
    { if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401); $id=(int)$request->input('mail_id',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.invalid_id')],422); $ok=$this->repo->markRead($id); Audit::log('mail','mark_read',(string)$id,['ok'=>$ok,'srv'=>ServerContext::currentId()]); return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.mail.api.success.marked_read'):Lang::get('app.mail.api.success.no_changes'),'server_id'=>ServerContext::currentId()]); }

    public function apiMarkReadBulk(Request $request): Response
    { if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401); $raw=(string)$request->input('ids',''); if($raw==='') return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.id_required')],422); $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',$raw)),fn($v)=>$v>0))); if(!$ids) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.no_valid_id')],422); $res=$this->repo->markReadBulk($ids); Audit::log('mail','mark_read_bulk',implode(',',$ids),['aff'=>$res['affected'],'srv'=>ServerContext::currentId()]); return $this->json(['success'=>true,'message'=>Lang::get('app.mail.api.success.bulk_marked',['count'=>$res['affected']]),'server_id'=>ServerContext::currentId()]+$res); }

    public function apiDelete(Request $request): Response
    { if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401); $id=(int)$request->input('mail_id',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.invalid_id')],422); $ok=$this->repo->delete($id); Audit::log('mail','delete',(string)$id,['ok'=>$ok,'srv'=>ServerContext::currentId()]); return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.mail.api.success.deleted_single'):Lang::get('app.mail.api.errors.delete_restricted'),'server_id'=>ServerContext::currentId()]); }

    public function apiDeleteBulk(Request $request): Response
    { if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401); $raw=(string)$request->input('ids',''); if($raw==='') return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.id_required')],422); $ids=array_values(array_unique(array_filter(array_map('intval',explode(',',$raw)),fn($v)=>$v>0))); if(!$ids) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.no_valid_id')],422); $res=$this->repo->deleteBulk($ids); Audit::log('mail','delete_bulk',implode(',',$ids),['del'=>count($res['deleted']),'blocked'=>count($res['blocked']),'srv'=>ServerContext::currentId()]); $msg=Lang::get('app.mail.api.success.bulk_deleted',['count'=>count($res['deleted'])]); if($res['blocked']) $msg.=Lang::get('app.mail.api.success.bulk_deleted_blocked_suffix',['count'=>count($res['blocked'])]); return $this->json(['success'=>true,'message'=>$msg,'server_id'=>ServerContext::currentId()]+$res); }

    public function apiStats(Request $request): Response
    { if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401); $stat=$this->repo->stats(); return $this->json(['success'=>true,'server_id'=>ServerContext::currentId()]+$stat); }

    public function apiLogs(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401);
        if(!$this->repo){
            $msg = $this->repoError ? $this->repoError->getMessage() : Lang::get('app.mail.api.errors.repository_not_ready');
            return $this->json(['success'=>false,'message'=>$msg],500);
        }
        $type = (string)$request->input('type','sql');
        $limit = (int)$request->input('limit',50);
        $res = $this->repo->tailLog($type,$limit);
        return $this->json($res, $res['success'] ? 200 : 422);
    }
}

