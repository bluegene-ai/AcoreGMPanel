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
use Acme\Panel\Support\{Auth, Lang};
use PDO;
use Throwable;

class AuditController extends Controller
{
    public function apiList(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401);
        $limit = (int)$request->input('limit',100); if($limit<1) $limit=1; if($limit>500) $limit=500;
        $module = trim($request->input('module',''));
        $action = trim($request->input('action',''));
        $admin  = trim($request->input('admin',''));
        try {
            $pdo = Database::auth();
            $w=[]; $p=[];
            if($module!==''){ $w[]='module=:m'; $p[':m']=$module; }
            if($action!==''){ $w[]='action=:a'; $p[':a']=$action; }
            if($admin!==''){ $w[]='admin=:u'; $p[':u']=$admin; }
            $where = $w?('WHERE '.implode(' AND ',$w)) : '';
            $sql = "SELECT id,ts,admin,module,action,target,detail,ip FROM panel_audit $where ORDER BY id DESC LIMIT :lim";
            $st = $pdo->prepare($sql); foreach($p as $k=>$v){ $st->bindValue($k,$v,PDO::PARAM_STR);} $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->execute();
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            foreach($rows as &$r){ if($r['detail']){ $d=json_decode($r['detail'],true); if(is_array($d)) $r['detail']=$d; } }
            return $this->json(['success'=>true,'data'=>$rows,'limit'=>$limit]);
    } catch(Throwable $e){ return $this->json(['success'=>false,'message'=>Lang::get('app.audit.api.errors.read_failed')],500); }
    }
}

