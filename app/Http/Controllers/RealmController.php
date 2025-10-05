<?php
/**
 * File: app/Http/Controllers/RealmController.php
 * Purpose: Defines class RealmController for the app/Http/Controllers module.
 * Classes:
 *   - RealmController
 * Functions:
 *   - select()
 */

namespace Acme\Panel\Http\Controllers;

use Acme\Panel\Core\{Config,Controller,Lang};
use Acme\Panel\Support\{Audit,ServerContext};

class RealmController extends Controller
{




    public function list(): \Acme\Panel\Core\Response
    {
        try {
            $servers=ServerContext::list(); $cur=ServerContext::currentId(); $out=[];
            foreach($servers as $id=>$cfg){ $out[]=['id'=>$id,'name'=>$cfg['name']??Lang::get('app.server.default_option',['id'=>$id])]; }
            return $this->json(['success'=>true,'current'=>$cur,'realms'=>$out,'count'=>count($out)]);
        } catch(\Throwable $e){
            return $this->json(['success'=>false,'error'=>'realm_list_failed','message'=>Config::get('app.debug',false)?$e->getMessage():'internal','trace'=>Config::get('app.debug',false)?$e->getTraceAsString():null],500);
        }
    }

    public function select(): \Acme\Panel\Core\Response
    {
    if(!isset($_SESSION['user_id'])){ return $this->json(['success'=>false,'message'=>Lang::get('app.realm.errors.not_logged_in')],401); }
        $id=(int)$this->getPost('server_id',-1);
    if(!ServerContext::set($id)) { return $this->json(['success'=>false,'message'=>Lang::get('app.realm.errors.not_found')],404); }
        Audit::log('realm','switch',(string)$id,['user'=>$_SESSION['username']??'unknown']);
        return $this->json(['success'=>true,'current'=>$id]);
    }
}

