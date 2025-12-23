<?php
/**
 * File: app/Http/Controllers/Item/ItemController.php
 * Purpose: Defines class ItemController for the app/Http/Controllers/Item module.
 * Classes:
 *   - ItemController
 * Functions:
 *   - __construct()
 *   - index()
 *   - editPage()
 *   - buildCancelQuery()
 *   - apiCreate()
 *   - apiDelete()
 *   - apiSave()
 *   - apiExecSql()
 *   - apiLogs()
 *   - apiCheck()
 *   - apiFetch()
 *   - apiSubclasses()
 */

namespace Acme\Panel\Http\Controllers\Item;

use Acme\Panel\Core\{Controller,Request,Response,ItemMeta,Lang};
use Acme\Panel\Domain\Item\ItemRepository;
use Acme\Panel\Support\{LogPath,ServerContext,ServerList};
use Acme\Panel\Support\Auth;

class ItemController extends Controller
{
    private ItemRepository $repo;
    public function __construct(){ $this->repo=new ItemRepository(); }

    public function index(Request $request): Response
    {
    if(!Auth::check()) return $this->redirect('/account/login');


    $reqServer = $request->input('server', null);
    if($reqServer !== null){ $sid=(int)$reqServer; if(ServerContext::currentId()!==$sid && ServerList::valid($sid)){ ServerContext::set($sid); $this->repo=new ItemRepository(); }
    }
    $editId=(int)$request->input('edit_id',0); if($editId>0) return $this->editPage($request,$editId);
        $opts=[
            'search_type'=>$request->input('search_type','name'),
            'search_value'=>$request->input('search_value',''),
            'filter_quality'=>$request->int('filter_quality',-1),
            'filter_class'=>$request->int('filter_class',-1),
            'filter_subclass'=>$request->int('filter_subclass',-1),
            'filter_itemlevel_op'=>$request->input('filter_itemlevel_op','any'),
            'filter_itemlevel_val'=>$request->input('filter_itemlevel_val',''),
            'limit'=>$request->int('limit',50),
            'page'=>$request->int('page',1),
            'sort_by'=>$request->input('sort_by','entry'),
            'sort_dir'=>$request->input('sort_dir','ASC')
        ];
    $pager=$this->repo->search($opts);
    return $this->view('item.index',[ 'title'=>Lang::get('app.item.page_title'),'pager'=>$pager,'current_server'=>ServerContext::currentId(),'servers'=>ServerList::options()] + $opts);
    }

    private function editPage(Request $request,int $id): Response
    { $row=$this->repo->find($id); if(!$row) return $this->redirect('/item'); return $this->view('item.edit',[ 'title'=>Lang::get('app.item.edit.page_title',['id'=>$id]),'item'=>$row,'cancel_query'=>$this->buildCancelQuery($request) ]); }

    private function buildCancelQuery(Request $request): string
    { $params=$request->all(); unset($params['edit_id']); return http_build_query($params); }


    public function apiCreate(Request $request): Response
    { $newId=(int)$request->input('new_item_id',0); $copy=$request->input('copy_item_id'); $copyId=$copy!==null && $copy!=='' ? (int)$copy : null; $res=$this->repo->create($newId,$copyId); return $this->json($res,$res['success']?200:422); }

    public function apiDelete(Request $request): Response
    { $id=(int)$request->input('entry',0); $res=$this->repo->delete($id); return $this->json($res,$res['success']?200:422); }

    public function apiSave(Request $request): Response
    { $id=(int)$request->input('entry',0); $changes=$request->input('changes',[]); if(is_string($changes)){ $decoded=json_decode($changes,true); if(is_array($decoded)) $changes=$decoded; else $changes=[]; } $res=$this->repo->updatePartial($id,is_array($changes)?$changes:[]); return $this->json($res,$res['success']?200:422); }

    public function apiExecSql(Request $request): Response
    { $sql=(string)$request->input('sql',''); $res=$this->repo->execLimitedSql($sql); return $this->json($res,$res['success']?200:422); }

    public function apiLogs(Request $request): Response
    {
        $type=$request->input('type','sql'); $limit=max(1,min(500,(int)$request->input('limit',200)));
        $map=[
            'sql'=>'item_sql.log',
            'deleted'=>'item_deleted.log',
            'actions'=>'item_actions.log'
        ];
        if(!isset($map[$type])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.item.api.errors.log_type_unknown')],422);
        }
        $file=$map[$type];
        $path = LogPath::logFile($file, false);
        $lines=[]; if(is_file($path)){ $content=file($path, FILE_IGNORE_NEW_LINES); $lines=array_slice($content,-$limit); }
        return $this->json(['success'=>true,'type'=>$type,'logs'=>$lines]);
    }

    public function apiCheck(Request $request): Response
    { $id=(int)$request->input('entry',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.item.api.errors.invalid_id')],422); $exists=$this->repo->find($id)!==null; return $this->json(['success'=>true,'exists'=>$exists,'entry'=>$id]); }

    public function apiFetch(Request $request): Response
    { $id=(int)$request->input('entry',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.item.api.errors.invalid_id')],422); $row=$this->repo->find($id); if(!$row) return $this->json(['success'=>false,'message'=>Lang::get('app.item.api.errors.not_found')],404); return $this->json(['success'=>true,'item'=>$row]); }


    public function apiSubclasses(Request $request): Response
    {
        $classId = $request->int('class', -1);
        if($classId < 0){
            return $this->json(['success'=>true,'class'=>$classId,'subclasses'=>[],'count'=>0]);
        }
        $subs = ItemMeta::subclassesOf($classId);
        return $this->json([
            'success'=>true,
            'class'=>$classId,
            'subclasses'=>$subs,
            'count'=>count($subs)
        ]);
    }
}

