<?php
/**
 * File: app/Http/Controllers/AuditController.php
 * Purpose: Defines class AuditController for the app/Http/Controllers module.
 */

namespace Acme\Panel\Http\Controllers;

use Acme\Panel\Core\{Controller,Request,Response,Database};
use Acme\Panel\Support\{Audit,Lang};
use PDO;
use Throwable;

class AuditController extends Controller
{
    private function requireReadCapability(): void
    {
        $this->requireCapability('audit.read');
    }

    public function apiList(Request $request): Response
    {
        $this->requireReadCapability();
        $state = $this->prepareAuditListState($request);
        $pdo = Audit::storageFor($state['server_id'] ?? null);
        if(!$pdo){
            try { $pdo = Database::auth(); } catch(Throwable $e){ return $this->json(['success'=>false,'message'=>Lang::get('audit.api.errors.read_failed')],500); }
        }
        try {
            $w=[]; $p=[];
            if($state['filters']['module']!==''){ $w[]='module=:m'; $p[':m']=$state['filters']['module']; }
            if($state['filters']['action']!==''){ $w[]='action=:a'; $p[':a']=$state['filters']['action']; }
            if($state['filters']['admin']!==''){ $w[]='admin=:u'; $p[':u']=$state['filters']['admin']; }
            if($state['server_id']!==null){ $w[]='server_id=:sid'; $p[':sid']=$state['server_id']; }
            $where = $w?('WHERE '.implode(' AND ',$w)) : '';
            $sql = "SELECT id,ts,admin,module,action,target,detail,ip,server_id FROM panel_audit $where ORDER BY id DESC LIMIT :lim";
            $st = $pdo->prepare($sql); foreach($p as $k=>$v){ $st->bindValue($k,$v,PDO::PARAM_STR);} $st->bindValue(':lim',$state['limit'],PDO::PARAM_INT); $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach($rows as &$r){ if($r['detail']){ $d=json_decode($r['detail'],true); if(is_array($d)) $r['detail']=$d; } $r['server_id']=(int)($r['server_id']??0); }
            unset($r);
            return $this->json(['success'=>true,'data'=>$rows,'limit'=>$state['limit'],'server_id'=>$state['server_id']]);
        } catch(Throwable $e){ return $this->json(['success'=>false,'message'=>Lang::get('audit.api.errors.read_failed')],500); }
    }

    private function prepareAuditListState(Request $request): array
    {
        return [
            'filters' => [
                'module' => $this->normalizedString($request, 'module'),
                'action' => $this->normalizedString($request, 'action'),
                'admin' => $this->normalizedString($request, 'admin'),
            ],
            // server=all（或省略）读全部区服；server=<id> 只看该区。
            'server_id' => $this->resolveServerFilter($request),
            'limit' => $this->boundedInt($request, 'limit', 100, 1, 500),
        ];
    }

    private function resolveServerFilter(Request $request): ?int
    {
        $raw = trim((string) $request->input('server', ''));
        if($raw === '' || strcasecmp($raw, 'all') === 0) return null;
        $id = (int) $raw;
        return $id >= 0 ? $id : null;
    }
}
