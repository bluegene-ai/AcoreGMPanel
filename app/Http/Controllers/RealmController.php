<?php
/**
 * File: app/Http/Controllers/RealmController.php
 * Purpose: Defines class RealmController for the app/Http/Controllers module.
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
            return $this->json(['success'=>false,'error'=>'realm_list_failed'],500);
        }
    }
}

