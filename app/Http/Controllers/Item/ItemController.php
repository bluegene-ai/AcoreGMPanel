<?php
/**
 * File: app/Http/Controllers/Item/ItemController.php
 * Purpose: Defines class ItemController for the app/Http/Controllers/Item module.
 */

namespace Acme\Panel\Http\Controllers\Item;

use Acme\Panel\Core\{Controller,Request,Response,ItemMeta,Lang,Url};
use Acme\Panel\Domain\Item\ItemRepository;
use Acme\Panel\Support\{ItemTooltip,LogPath,ServerContext,ServerList};
use Acme\Panel\Support\Auth;

class ItemController extends Controller
{
    private ItemRepository $repo;

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

    public function __construct(){ $this->repo=new ItemRepository(); }

    public function index(Request $request): Response
    {
    $this->requireViewCapability();


    $this->switchServerAndRefresh($request, function (): void { $this->repo=new ItemRepository(); });
    $editId=(int)$request->input('edit_id',0); if($editId>0) return $this->editPage($request,$editId);
        $opts = $this->prepareItemIndexFilters($request);
    $pager=$this->repo->search($opts);
    return $this->pageView('item.index', $this->listViewData($pager, $opts), [
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
        if(!$row) return $this->redirect('/item');

        $cancelQuery = $this->buildCancelQuery($request);
        $backUrl = '/item' . ($cancelQuery !== '' ? '?' . $cancelQuery : '');

        return $this->pageView('item.edit',[ 'item'=>$row,'cancel_query'=>$cancelQuery ], [
            'capabilities' => [
                'view' => 'content.view',
                'update' => 'content.update',
                'delete' => 'content.delete',
                'sql' => 'content.sql',
            ],
            'header' => [
                'actions' => [
                    [
                        'label' => Lang::get('app.item.edit.back_to_list'),
                        'url' => Url::to($backUrl),
                        'class' => 'btn',
                    ],
                ],
            ],
        ]);
    }

    private function buildCancelQuery(Request $request): string
    { $params=$request->all(); unset($params['edit_id']); return http_build_query($params); }

    private function prepareItemIndexFilters(Request $request): array
    {
        return [
            'search_type' => $this->normalizedEnum($request, 'search_type', ['name', 'id'], 'name'),
            'search_value' => $this->normalizedString($request, 'search_value'),
            'filter_quality' => $request->int('filter_quality', -1),
            'filter_class' => $request->int('filter_class', -1),
            'filter_subclass' => $request->int('filter_subclass', -1),
            'filter_itemlevel_op' => $this->normalizedString($request, 'filter_itemlevel_op', 'any') ?: 'any',
            'filter_itemlevel_val' => $this->normalizedString($request, 'filter_itemlevel_val'),
            'limit' => $this->boundedInt($request, 'limit', 50, 1, 500),
            'page' => $this->normalizedPage($request),
            'sort_by' => $this->normalizedString($request, 'sort_by', 'entry') ?: 'entry',
            'sort_dir' => $this->normalizedDirection($request, 'sort_dir', 'ASC'),
        ];
    }


    public function apiCreate(Request $request): Response
    { $this->requireCreateCapability(); $newId=(int)$request->input('new_item_id',0); $copy=$request->input('copy_item_id'); $copyId=$copy!==null && $copy!=='' ? (int)$copy : null; $res=$this->repo->create($newId,$copyId); return $this->json($res,$res['success']?200:422); }

    public function apiDelete(Request $request): Response
    { $this->requireDeleteCapability(); $id=(int)$request->input('entry',0); $res=$this->repo->delete($id); return $this->json($res,$res['success']?200:422); }

    public function apiSave(Request $request): Response
    { $this->requireUpdateCapability(); $id=(int)$request->input('entry',0); $changes=$request->input('changes',[]); if(is_string($changes)){ $decoded=json_decode($changes,true); if(is_array($decoded)) $changes=$decoded; else $changes=[]; } $res=$this->repo->updatePartial($id,is_array($changes)?$changes:[]); return $this->json($res,$res['success']?200:422); }

    public function apiExecSql(Request $request): Response
    { $this->requireSqlCapability(); $sql=(string)$request->input('sql',''); $res=$this->repo->execLimitedSql($sql); return $this->json($res,$res['success']?200:422); }

    public function apiLogs(Request $request): Response
    {
        $this->requireLogsCapability();
        $state = $this->prepareItemLogState($request);
        $map=[
            'sql'=>'item_sql.log',
            'deleted'=>'item_deleted.log',
            'actions'=>'item_actions.log'
        ];
        if(!isset($map[$state['type']])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.item.api.errors.log_type_unknown')],422);
        }
        $file=$map[$state['type']];
        $path = LogPath::logFile($file, false);
        $lines=[]; if(is_file($path)){ $content=file($path, FILE_IGNORE_NEW_LINES); $lines=array_slice($content,-$state['limit']); }
        return $this->json(['success'=>true,'type'=>$state['type'],'logs'=>$lines]);
    }

    /**
     * 物品属性卡（悬停物品名时弹出的那个）。除物品编辑页外，面板所有页面的物品名都走这里。
     *
     * 刻意只要求登录、不要求 content.view：物品名出现在邮件、发放记录、Boss 奖池等页面，
     * 那些页面的用户未必有物品管理权限；要求 content.view 会让这些页面的悬停提示静默变成 403。
     */
    public function apiTooltip(Request $request): Response
    {
        $this->requireLogin();

        $entry = max(0, (int) $request->input('entry', 0));
        if ($entry <= 0) {
            return $this->json(['success' => false, 'message' => Lang::get('app.item.api.errors.invalid_id')], 422);
        }

        $linkHint = (string) $request->input('link', '') !== '';

        try {
            $html = ItemTooltip::html($entry, ServerContext::currentId(), $linkHint);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.item.api.errors.tooltip_failed')], 500);
        }

        if ($html === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.item.tooltip.labels.not_found', ['id' => $entry]),
            ], 404);
        }

        return $this->json(['success' => true, 'entry' => $entry, 'html' => $html]);
    }

    public function apiSubclasses(Request $request): Response
    {
        $this->requireViewCapability();
        $state = $this->prepareItemSubclassState($request);
        if($state['class'] < 0){
            return $this->json(['success'=>true,'class'=>$state['class'],'subclasses'=>[],'count'=>0]);
        }
        $subs = ItemMeta::subclassesOf($state['class']);
        return $this->json([
            'success'=>true,
            'class'=>$state['class'],
            'subclasses'=>$subs,
            'count'=>count($subs)
        ]);
    }

    private function prepareItemLogState(Request $request): array
    {
        return [
            'type' => $this->normalizedEnum($request, 'type', ['sql', 'deleted', 'actions'], 'sql'),
            'limit' => $this->boundedInt($request, 'limit', 200, 1, 500),
        ];
    }

    private function prepareItemSubclassState(Request $request): array
    {
        return [
            'class' => (int) $request->input('class', -1),
        ];
    }
}

