<?php
/**
 * File: app/Http/Controllers/Creature/CreatureController.php
 * Purpose: Defines class CreatureController for the app/Http/Controllers/Creature module.
 * Classes:
 *   - CreatureController
 * Functions:
 *   - __construct()
 *   - index()
 *   - editPage()
 *   - buildCancelQuery()
 *   - apiCreate()
 *   - apiDelete()
 *   - apiSave()
 *   - apiExecSql()
 *   - apiFetchRow()
 *   - apiAddModel()
 *   - apiEditModel()
 *   - apiDeleteModel()
 */

namespace Acme\Panel\Http\Controllers\Creature;

use Acme\Panel\Core\{Controller,Lang,Request,Response};
use Acme\Panel\Domain\Creature\CreatureRepository;
use Acme\Panel\Support\{Auth,Audit,ServerContext,ServerList};

class CreatureController extends Controller
{
    private CreatureRepository $repo;
    public function __construct(){ $this->repo=new CreatureRepository(); }

    public function index(Request $request): Response
    {
    if(!Auth::check()) return $this->redirect('/account/login');

    $reqServer=$request->input('server',null); if($reqServer!==null){ $sid=(int)$reqServer; if(ServerContext::currentId()!==$sid && ServerList::valid($sid)){ ServerContext::set($sid); $this->repo=new CreatureRepository(); } }

        $editId = (int)$request->input('edit_id',0);
        if($editId>0){ return $this->editPage($request,$editId); }
        $opts=[
            'search_type'=>$request->input('search_type','name'),
            'search_value'=>$request->input('search_value',''),
            'filter_rank'=>$request->int('filter_rank',-1),
            'filter_type'=>$request->int('filter_type',-1),
            'filter_minlevel'=>$request->input('filter_minlevel',''),
            'filter_maxlevel'=>$request->input('filter_maxlevel',''),



            'filter_npcflag_bits'=>$request->input('filter_npcflag_bits',''),
            'limit'=>$request->int('limit',50),
            'page'=>$request->int('page',1),
            'sort_by'=>$request->input('sort_by','entry'),
            'sort_dir'=>$request->input('sort_dir','ASC')
        ];
        $pager=$this->repo->search($opts);
    return $this->view('creature.index',[ 'title'=>Lang::get('app.creature.index.page_title'),'pager'=>$pager,'current_server'=>ServerContext::currentId(),'servers'=>ServerList::options()] + $opts);
    }

    private function editPage(Request $request,int $id): Response
    {
    $row=$this->repo->find($id); if(!$row) return $this->redirect('/creature');
        $models=$this->repo->getModels($id);
    return $this->view('creature.edit',[ 'title'=>Lang::get('app.creature.edit.title',['id'=>$id]),'creature'=>$row,'models'=>$models,'cancel_query'=>$this->buildCancelQuery($request) ]);
    }

    private function buildCancelQuery(Request $request): string
    { $params=$request->all(); unset($params['edit_id']); return http_build_query($params); }


    public function apiCreate(Request $request): Response
    { $newId=(int)$request->input('new_creature_id',0); $copy=$request->input('copy_creature_id'); $copyId=$copy!==null && $copy!=='' ? (int)$copy : null; $res=$this->repo->create($newId,$copyId); return $this->json($res,$res['success']?200:422); }

    public function apiDelete(Request $request): Response
    { $id=(int)$request->input('entry',0); $res=$this->repo->delete($id); return $this->json($res,$res['success']?200:422); }

    public function apiSave(Request $request): Response
    { $id=(int)$request->input('entry',0); $changes=$request->input('changes',[]); if(is_string($changes)){ $decoded=json_decode($changes,true); if(is_array($decoded)) $changes=$decoded; else $changes=[]; } $res=$this->repo->updatePartial($id,is_array($changes)?$changes:[]); return $this->json($res,$res['success']?200:422); }

    public function apiExecSql(Request $request): Response
    { $sql=(string)$request->input('sql',''); $res=$this->repo->execLimitedSql($sql); return $this->json($res,$res['success']?200:422); }

    public function apiFetchRow(Request $request): Response
    { $id=(int)$request->input('entry',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.invalid_id')],422); $r=$this->repo->fetchRowDiag($id); if(!$r) return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); return $this->json(['success'=>true]+$r); }

    public function apiAddModel(Request $request): Response
    { $cid=(int)$request->input('creature_id',0); $res=$this->repo->addModel($cid,(int)$request->input('display_id',0),(float)$request->input('scale',1),(float)$request->input('probability',1),$request->input('verifiedbuild')!==''?(int)$request->input('verifiedbuild'):null); return $this->json($res,$res['success']?200:422); }

    public function apiEditModel(Request $request): Response
    { $cid=(int)$request->input('creature_id',0); $res=$this->repo->editModel($cid,(int)$request->input('idx',0),(int)$request->input('display_id',0),(float)$request->input('scale',1),(float)$request->input('probability',1),$request->input('verifiedbuild')!==''?(int)$request->input('verifiedbuild'):null); return $this->json($res,$res['success']?200:422); }

    public function apiDeleteModel(Request $request): Response
    { $cid=(int)$request->input('creature_id',0); $res=$this->repo->deleteModel($cid,(int)$request->input('idx',0)); return $this->json($res,$res['success']?200:422); }
}

