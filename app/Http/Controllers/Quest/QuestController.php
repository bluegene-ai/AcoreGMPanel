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

use Acme\Panel\Core\{Controller,Request,Response,Lang,Url};
use Acme\Panel\Domain\Quest\QuestRepository;
use Acme\Panel\Domain\Quest\QuestAggregateService;
use Acme\Panel\Support\{ServerContext,ServerList};
use Acme\Panel\Support\Auth;

class QuestController extends Controller
{
    private QuestRepository $repo;

    private function requireViewCapability(): void
    {
        $this->requireCapability('content.view');
    }

    private function requireCreateCapability(): void
    {
        $this->requireCapability('content.create');
    }

    private function requireUpdateCapability(): void
    {
        $this->requireCapability('content.update');
    }

    private function requireDeleteCapability(): void
    {
        $this->requireCapability('content.delete');
    }

    private function requireSqlCapability(): void
    {
        $this->requireCapability('content.sql');
    }

    private function requireLogsCapability(): void
    {
        $this->requireCapability('content.logs');
    }

    private function requirePreviewCapability(): void
    {
        $this->requireCapability('content.preview');
    }

    public function __construct(){ $this->repo=new QuestRepository(); }

    public function index(Request $request): Response
    {
        $this->requireViewCapability();
        $this->switchServerAndRefresh($request, function (): void { $this->repo=new QuestRepository(); });
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
        $opts = $this->prepareQuestIndexFilters($request);
        $pager=$this->repo->search($opts);
        $questInfoOptions = $this->repo->questInfoOptions();
        return $this->pageView('quest.index', $this->listViewData($pager, $opts, [
            'questInfoOptions' => $questInfoOptions,
        ]), [
            'capabilities' => [
                'view' => 'content.view',
                'create' => 'content.create',
                'delete' => 'content.delete',
                'logs' => 'content.logs',
            ],
        ]);
    }

    private function editPage(Request $request,int $id): Response
    {
        $row=$this->repo->find($id);
        if(!$row){

            return $this->pageView('quest.index', $this->listViewData((object)['items'=>[],'page'=>1,'pages'=>1], [], [
                'not_found_id' => $id,
            ]), [
                'capabilities' => [
                    'view' => 'content.view',
                    'create' => 'content.create',
                    'delete' => 'content.delete',
                    'logs' => 'content.logs',
                ],
            ]);
        }
        $_SESSION['quest_editor_last_id'] = $id;
        $cancelQuery = $this->buildCancelQuery($request);
        $backUrl = '/quest' . ($cancelQuery !== '' ? '?' . $cancelQuery : '');
        return $this->pageView('quest.edit',[ 'quest'=>$row,'cancel_query'=>$cancelQuery ], [
            'capabilities' => [
                'view' => 'content.view',
                'update' => 'content.update',
                'sql' => 'content.sql',
                'logs' => 'content.logs',
            ],
            'header' => [
                'actions' => [
                    [
                        'label' => Lang::get('app.quest.edit.toolbar.back'),
                        'url' => Url::to($backUrl),
                        'class' => 'btn',
                    ],
                ],
            ],
        ]);
    }

    private function buildCancelQuery(Request $request): string
    { $params=$request->all(); unset($params['edit_id']); return http_build_query($params); }

    private function prepareQuestIndexFilters(Request $request): array
    {
        return [
            'filter_id' => $request->int('filter_id', 0),
            'filter_title' => $this->normalizedString($request, 'filter_title'),
            'filter_level_op' => $this->normalizedString($request, 'filter_level_op', 'any') ?: 'any',
            'filter_level_val' => $this->normalizedString($request, 'filter_level_val'),
            'filter_min_level_op' => $this->normalizedString($request, 'filter_min_level_op', 'any') ?: 'any',
            'filter_min_level_val' => $this->normalizedString($request, 'filter_min_level_val'),
            'filter_type' => $this->normalizedString($request, 'filter_type'),
            'limit' => $this->boundedInt($request, 'limit', 50, 1, 500),
            'page' => $this->normalizedPage($request),
            'sort_by' => $this->normalizedString($request, 'sort_by', 'ID') ?: 'ID',
            'sort_dir' => $this->normalizedDirection($request, 'sort_dir', 'ASC'),
        ];
    }


    public function apiCreate(Request $request): Response
    { $this->requireCreateCapability(); $newId=(int)$request->input('new_id',0); $copy=$request->input('copy_id'); $copyId=$copy!==null && $copy!=='' ? (int)$copy : null; $res=$this->repo->create($newId,$copyId); return $this->json($res,$res['success']?200:422); }

    public function apiDelete(Request $request): Response
    { $this->requireDeleteCapability(); $id=(int)$request->input('id',0); $res=$this->repo->delete($id); return $this->json($res,$res['success']?200:422); }

    public function apiSave(Request $request): Response
    { $this->requireUpdateCapability(); $id=(int)$request->input('id',0); $changes=$request->input('changes',[]); if(is_string($changes)){ $decoded=json_decode($changes,true); if(is_array($decoded)) $changes=$decoded; else $changes=[]; } $res=$this->repo->updatePartial($id,is_array($changes)?$changes:[]); return $this->json($res,$res['success']?200:422); }

    public function apiExecSql(Request $request): Response
    { $this->requireSqlCapability(); $sql=(string)$request->input('sql',''); $res=$this->repo->execLimitedSql($sql); return $this->json($res,$res['success']?200:422); }

    public function apiFetch(Request $request): Response
    { $this->requireViewCapability(); $state = $this->prepareQuestFetchState($request); if($state['id']<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.quest.api.errors.invalid_id')],422); $row=$this->repo->find($state['id']); if(!$row) return $this->json(['success'=>false,'message'=>Lang::get('app.quest.messages.not_found')],404); $hash=$this->repo->rowHash($row); return $this->json(['success'=>true,'quest'=>$row,'hash'=>$hash]); }

    public function apiLogs(Request $request): Response
    { $this->requireLogsCapability(); $state = $this->prepareQuestLogState($request); $res=$this->repo->tailLog($state['type'],$state['limit']); return $this->json($res,$res['success']?200:422); }

    public function apiEditorLoad(Request $request): Response
    {
        $this->requireViewCapability();
        $state = $this->prepareQuestFetchState($request);
        $service = new QuestAggregateService();
        $res = $service->load($state['id']);
        return $this->json($res, $res['success'] ? 200 : 422);
    }

    public function apiEditorSave(Request $request): Response
    {
        $this->requireUpdateCapability();
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
        $this->requirePreviewCapability();
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

    private function prepareQuestFetchState(Request $request): array
    {
        return [
            'id' => max(0, (int) $request->input('id', 0)),
        ];
    }

    private function prepareQuestLogState(Request $request): array
    {
        return [
            'type' => $this->normalizedString($request, 'type', 'sql') ?: 'sql',
            'limit' => max(1, (int) $request->input('limit', 50)),
        ];
    }
}

