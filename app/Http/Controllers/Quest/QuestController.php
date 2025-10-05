<?php
/**
 * File: app/Http/Controllers/Quest/QuestController.php
 * Purpose: Defines class QuestController for the app/Http/Controllers/Quest module.
 * Classes:
 *   - QuestController
 * Functions:
 *   - __construct()
 *   - index()
 *   - editPage()
 *   - buildCancelQuery()
 *   - apiCreate()
 *   - apiDelete()
 *   - apiSave()
 *   - apiExecSql()
 *   - apiFetch()
 *   - apiLogs()
 *   - apiEditorLoad()
 *   - apiEditorSave()
 *   - apiEditorPreview()
 */

namespace Acme\Panel\Http\Controllers\Quest;

use Acme\Panel\Core\{Controller,Request,Response,Lang};
use Acme\Panel\Domain\Quest\QuestRepository;
use Acme\Panel\Domain\Quest\QuestAggregateService;
use Acme\Panel\Support\{ServerContext,ServerList};
use Acme\Panel\Support\Auth;

class QuestController extends Controller
{
    private QuestRepository $repo;
    public function __construct(){ $this->repo=new QuestRepository(); }

    public function index(Request $request): Response
    {
    if(!Auth::check()) return $this->redirect('/account/login');
        $reqServer=$request->input('server',null);
        if($reqServer!==null){
            $sid=(int)$reqServer;
            if(ServerContext::currentId()!==$sid && ServerList::valid($sid)){
                ServerContext::set($sid);
                $this->repo=new QuestRepository();
            }
        }
        $editId=(int)$request->input('edit_id',0);
        $viewMode = $request->input('view');
        if($viewMode === 'editor' && $editId <= 0){
            $last = isset($_SESSION['quest_editor_last_id']) ? (int)$_SESSION['quest_editor_last_id'] : 0;
            if($last > 0){
                $editId = $last;
            } else {
                $first = $this->repo->firstQuestId();
                if($first){
                    $editId = $first;
                }
            }
        }
        if($editId>0) return $this->editPage($request,$editId);
        $opts=[
            'filter_id'=>$request->int('filter_id',0),
            'filter_title'=>$request->input('filter_title',''),
            'filter_level_op'=>$request->input('filter_level_op','any'),
            'filter_level_val'=>$request->input('filter_level_val',''),
            'filter_min_level_op'=>$request->input('filter_min_level_op','any'),
            'filter_min_level_val'=>$request->input('filter_min_level_val',''),
            'filter_type'=>$request->input('filter_type',''),
            'limit'=>$request->int('limit',50),
            'page'=>$request->int('page',1),
            'sort_by'=>$request->input('sort_by','ID'),
            'sort_dir'=>$request->input('sort_dir','ASC')
        ];
        $pager=$this->repo->search($opts);
        $questInfoOptions = $this->repo->questInfoOptions();
    return $this->view('quest.index',[ 'title'=>Lang::get('app.quest.index.page_title'),'pager'=>$pager,'current_server'=>ServerContext::currentId(),'servers'=>ServerList::options(),'filters'=>$opts,'questInfoOptions'=>$questInfoOptions] + $opts);
    }

    private function editPage(Request $request,int $id): Response
    {
        $row=$this->repo->find($id);
        if(!$row){

            return $this->view('quest.index',[ 'title'=>Lang::get('app.quest.messages.not_found_title'),'pager'=>(object)['items'=>[],'page'=>1,'pages'=>1],'current_server'=>ServerContext::currentId(),'servers'=>ServerList::options(),'filters'=>[], 'not_found_id'=>$id ]);
        }
        $_SESSION['quest_editor_last_id'] = $id;
        return $this->view('quest.edit',[ 'title'=>Lang::get('app.quest.edit.page_title',['id'=>$id]),'quest'=>$row,'cancel_query'=>$this->buildCancelQuery($request) ]);
    }

    private function buildCancelQuery(Request $request): string
    { $params=$request->all(); unset($params['edit_id']); return http_build_query($params); }


    public function apiCreate(Request $request): Response
    { $newId=(int)$request->input('new_id',0); $copy=$request->input('copy_id'); $copyId=$copy!==null && $copy!=='' ? (int)$copy : null; $res=$this->repo->create($newId,$copyId); return $this->json($res,$res['success']?200:422); }

    public function apiDelete(Request $request): Response
    { $id=(int)$request->input('id',0); $res=$this->repo->delete($id); return $this->json($res,$res['success']?200:422); }

    public function apiSave(Request $request): Response
    { $id=(int)$request->input('id',0); $changes=$request->input('changes',[]); if(is_string($changes)){ $decoded=json_decode($changes,true); if(is_array($decoded)) $changes=$decoded; else $changes=[]; } $res=$this->repo->updatePartial($id,is_array($changes)?$changes:[]); return $this->json($res,$res['success']?200:422); }

    public function apiExecSql(Request $request): Response
    { $sql=(string)$request->input('sql',''); $res=$this->repo->execLimitedSql($sql); return $this->json($res,$res['success']?200:422); }

    public function apiFetch(Request $request): Response
    { $id=(int)$request->input('id',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.quest.api.errors.invalid_id')],422); $row=$this->repo->find($id); if(!$row) return $this->json(['success'=>false,'message'=>Lang::get('app.quest.messages.not_found')],404); $hash=$this->repo->rowHash($row); return $this->json(['success'=>true,'quest'=>$row,'hash'=>$hash]); }

    public function apiLogs(Request $request): Response
    { $type=$request->input('type','sql'); $limit=(int)$request->input('limit',50); $res=$this->repo->tailLog($type,$limit); return $this->json($res,$res['success']?200:422); }

    public function apiEditorLoad(Request $request): Response
    {
        $id = (int)$request->input('id', 0);
        $service = new QuestAggregateService();
        $res = $service->load($id);
        return $this->json($res, $res['success'] ? 200 : 422);
    }

    public function apiEditorSave(Request $request): Response
    {
        $id = (int)$request->input('id', 0);
        $payload = $request->input('payload', []);
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                $payload = [];
            }
        }
        $expected = $request->input('expected_hash');
        $service = new QuestAggregateService();
        $res = $service->save($id, is_array($payload) ? $payload : [], $expected ? (string)$expected : null);
        return $this->json($res, $res['success'] ? 200 : 422);
    }

    public function apiEditorPreview(Request $request): Response
    {
        $id = (int)$request->input('id', 0);
        $payload = $request->input('payload', []);
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                $payload = [];
            }
        }
        $service = new QuestAggregateService();
        $res = $service->preview($id, is_array($payload) ? $payload : []);
        return $this->json($res, $res['success'] ? 200 : 422);
    }
}

