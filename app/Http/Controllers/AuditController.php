<?php
/**
 * File: app/Http/Controllers/AuditController.php
 * Purpose: Defines class AuditController for the app/Http/Controllers module.
 * Classes:
 *   - AuditController
 * Functions:
 *   - apiList()
 */

namespace Acme\Panel\Http\Controllers;

use Acme\Panel\Core\{Controller,Request,Response,Database};
use Acme\Panel\Support\Lang;
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
        try {
            $pdo = Database::auth();
            $w=[]; $p=[];
            if($state['filters']['module']!==''){ $w[]='module=:m'; $p[':m']=$state['filters']['module']; }
            if($state['filters']['action']!==''){ $w[]='action=:a'; $p[':a']=$state['filters']['action']; }
            if($state['filters']['admin']!==''){ $w[]='admin=:u'; $p[':u']=$state['filters']['admin']; }
            $where = $w?('WHERE '.implode(' AND ',$w)) : '';
            $sql = "SELECT id,ts,admin,module,action,target,detail,ip FROM panel_audit $where ORDER BY id DESC LIMIT :lim";
            $st = $pdo->prepare($sql); foreach($p as $k=>$v){ $st->bindValue($k,$v,PDO::PARAM_STR);} $st->bindValue(':lim',$state['limit'],PDO::PARAM_INT); $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach($rows as &$r){ if($r['detail']){ $d=json_decode($r['detail'],true); if(is_array($d)) $r['detail']=$d; } }
            return $this->json(['success'=>true,'data'=>$rows,'limit'=>$state['limit']]);
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
            'limit' => $this->boundedInt($request, 'limit', 100, 1, 500),
        ];
    }
}

