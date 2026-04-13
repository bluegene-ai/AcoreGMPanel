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

use Acme\Panel\Core\{Controller,Lang,Request,Response,Url};
use Acme\Panel\Domain\Creature\CreatureRepository;
use Acme\Panel\Support\{Auth,Audit,LogPath,ServerContext,ServerList};

class CreatureController extends Controller
{
    private CreatureRepository $repo;

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

    public function __construct(){ $this->repo=new CreatureRepository(); }

    public function index(Request $request): Response
    {
    $this->requireViewCapability();

    $this->switchServerAndRefresh($request, function (): void { $this->repo=new CreatureRepository(); });

        $editId = (int)$request->input('edit_id',0);
        if($editId>0){ return $this->editPage($request,$editId); }
        $opts = $this->prepareCreatureIndexFilters($request);
        $pager=$this->repo->search($opts);
        return $this->pageView('creature.index', $this->listViewData($pager, $opts), [
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
    $row=$this->repo->find($id); if(!$row) return $this->redirect('/creature');
        $models=$this->repo->getModels($id);
        $cancelQuery = $this->buildCancelQuery($request);
        if ($request->input('server') !== null && !str_contains($cancelQuery, 'server=')) {
            $cancelQuery .= ($cancelQuery !== '' ? '&' : '') . 'server=' . (int) $request->input('server');
        }
        $backUrl = '/creature' . ($cancelQuery !== '' ? '?' . $cancelQuery : '');

    return $this->pageView('creature.edit',[ 'creature'=>$row,'models'=>$models,'cancel_query'=>$cancelQuery ], [
        'capabilities' => [
            'view' => 'content.view',
            'update' => 'content.update',
            'delete' => 'content.delete',
            'sql' => 'content.sql',
        ],
        'header' => [
            'actions' => [
                [
                    'label' => Lang::get('app.creature.edit.actions.back'),
                    'url' => Url::to($backUrl),
                    'class' => 'btn',
                ],
            ],
        ],
    ]);
    }

    private function buildCancelQuery(Request $request): string
    { $params=$request->all(); unset($params['edit_id']); return http_build_query($params); }

    private function prepareCreatureIndexFilters(Request $request): array
    {
        return [
            'search_type' => $this->normalizedEnum($request, 'search_type', ['name', 'id'], 'name'),
            'search_value' => $this->normalizedString($request, 'search_value'),
            'filter_rank' => $request->int('filter_rank', -1),
            'filter_type' => $request->int('filter_type', -1),
            'filter_minlevel' => $this->normalizedString($request, 'filter_minlevel'),
            'filter_maxlevel' => $this->normalizedString($request, 'filter_maxlevel'),
            'filter_npcflag_bits' => $this->normalizedString($request, 'filter_npcflag_bits'),
            'limit' => $this->boundedInt($request, 'limit', 50, 1, 500),
            'page' => $this->normalizedPage($request),
            'sort_by' => $this->normalizedString($request, 'sort_by', 'entry') ?: 'entry',
            'sort_dir' => $this->normalizedDirection($request, 'sort_dir', 'ASC'),
        ];
    }


    public function apiCreate(Request $request): Response
    { $this->requireCreateCapability(); $newId=(int)$request->input('new_creature_id',0); $copy=$request->input('copy_creature_id'); $copyId=$copy!==null && $copy!=='' ? (int)$copy : null; $res=$this->repo->create($newId,$copyId); return $this->json($res,$res['success']?200:422); }

    public function apiDelete(Request $request): Response
    { $this->requireDeleteCapability(); $id=(int)$request->input('entry',0); $res=$this->repo->delete($id); return $this->json($res,$res['success']?200:422); }

    public function apiSave(Request $request): Response
    { $this->requireUpdateCapability(); $id=(int)$request->input('entry',0); $changes=$request->input('changes',[]); if(is_string($changes)){ $decoded=json_decode($changes,true); if(is_array($decoded)) $changes=$decoded; else $changes=[]; } $res=$this->repo->updatePartial($id,is_array($changes)?$changes:[]); return $this->json($res,$res['success']?200:422); }

    public function apiExecSql(Request $request): Response
    { $this->requireSqlCapability(); $sql=(string)$request->input('sql',''); $res=$this->repo->execLimitedSql($sql); return $this->json($res,$res['success']?200:422); }

    public function apiLogs(Request $request): Response
    {
        $this->requireLogsCapability();
        $state = $this->prepareCreatureLogState($request);
        $map = [
            'sql' => 'creature_sql.log',
            'deleted' => 'creature_deleted.log',
            'actions' => 'creature_actions.log',
        ];
        if(!isset($map[$state['type']]))
            return $this->json(['success' => false, 'message' => Lang::get('app.common.errors.not_found')], 422);

        $path = LogPath::logFile($map[$state['type']], false);
        $lines = [];
        if(is_file($path)) {
            $content = file($path, FILE_IGNORE_NEW_LINES);
            $lines = array_slice($content, -$state['limit']);
        }

        return $this->json(['success' => true, 'type' => $state['type'], 'logs' => $lines]);
    }

    public function apiFetchRow(Request $request): Response
    { $this->requireViewCapability(); $state = $this->prepareCreatureEntryState($request); if($state['entry']<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.invalid_id')],422); $r=$this->repo->fetchRowDiag($state['entry']); if(!$r) return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); return $this->json(['success'=>true]+$r); }

    public function apiAddModel(Request $request): Response
    { $this->requireUpdateCapability(); $cid=(int)$request->input('creature_id',0); $res=$this->repo->addModel($cid,(int)$request->input('display_id',0),(float)$request->input('scale',1),(float)$request->input('probability',1),$request->input('verifiedbuild')!==''?(int)$request->input('verifiedbuild'):null); return $this->json($res,$res['success']?200:422); }

    public function apiEditModel(Request $request): Response
    { $this->requireUpdateCapability(); $cid=(int)$request->input('creature_id',0); $res=$this->repo->editModel($cid,(int)$request->input('idx',0),(int)$request->input('display_id',0),(float)$request->input('scale',1),(float)$request->input('probability',1),$request->input('verifiedbuild')!==''?(int)$request->input('verifiedbuild'):null); return $this->json($res,$res['success']?200:422); }

    public function apiDeleteModel(Request $request): Response
    { $this->requireDeleteCapability(); $cid=(int)$request->input('creature_id',0); $res=$this->repo->deleteModel($cid,(int)$request->input('idx',0)); return $this->json($res,$res['success']?200:422); }

    private function prepareCreatureLogState(Request $request): array
    {
        return [
            'type' => $this->normalizedEnum($request, 'type', ['sql', 'deleted', 'actions'], 'sql'),
            'limit' => $this->boundedInt($request, 'limit', 200, 1, 500),
        ];
    }

    private function prepareCreatureEntryState(Request $request): array
    {
        return [
            'entry' => max(0, (int) $request->input('entry', 0)),
        ];
    }
}

