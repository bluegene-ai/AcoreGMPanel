<?php
/**
 * File: app/Http/Controllers/BagQuery/BagQueryController.php
 * Purpose: Defines class BagQueryController for the app/Http/Controllers/BagQuery module.
 * Classes:
 *   - BagQueryController
 * Functions:
 *   - __construct()
 *   - index()
 *   - legacyRedirect()
 *   - apiCharacters()
 *   - apiItems()
 *   - apiReduce()
 */

namespace Acme\Panel\Http\Controllers\BagQuery;

use Acme\Panel\Core\{Controller,Lang,Request,Response};
use Acme\Panel\Domain\BagQuery\BagQueryRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\{ServerContext,ServerList};

class BagQueryController extends Controller
{
    private BagQueryRepository $repo;
    public function __construct(){ $this->repo = new BagQueryRepository(); }

    public function index(Request $request): Response
    {
        $this->requireLogin();
        $reqServer=$request->input('server',null);
        if($reqServer!==null){
            $sid=(int)$reqServer;
            if(ServerContext::currentId()!==$sid && ServerList::valid($sid)){
                ServerContext::set($sid);
                $this->repo=new BagQueryRepository();
            }
        }

        $prefillType=null; $prefillValue=null; $autoSearch=false;
        $valueParam=trim((string)$request->input('value',''));
        if($valueParam!==''){
            $prefillValue=$valueParam;
            $typeParam=(string)$request->input('type','character_name');
            $prefillType=in_array($typeParam,['character_name','username'],true)? $typeParam:'character_name';
            $autoSearch=true;
        } else {
            $legacyName=trim((string)$request->input('name',''));
            if($legacyName!==''){
                $prefillValue=$legacyName;
                $prefillType='character_name';
                $autoSearch=true;
            }
        }

        return $this->view('bag_query.index',[
            'title'=>Lang::get('app.bag_query.page_title'),
            'current_server'=>ServerContext::currentId(),
            'prefill'=>[
                'type'=>$prefillType,
                'value'=>$prefillValue,
                'auto'=>$autoSearch,
            ],
        ]);
    }

    public function legacyRedirect(): Response
    {
        return Response::redirect('/bag');
    }

    public function apiCharacters(Request $request): Response
    {
        $this->requireLogin();
        $type=$request->input('type','character_name');
        if(!in_array($type,['character_name','username'],true)) $type='character_name';
        $value=(string)$request->input('value','');
        $limit=max(1,min(200,(int)$request->input('limit',100)));
        $list=$this->repo->searchCharacters($type,$value,$limit);
        Audit::log('bag_query','search','characters',[ 'type'=>$type,'value'=>mb_substr($value,0,40),'returned'=>count($list) ]);
        return $this->json(['success'=>true,'data'=>$list]);
    }

    public function apiItems(Request $request): Response
    {
        $this->requireLogin();
        $guid=(int)$request->input('guid',0);
        if($guid<=0){
            return $this->json([
                'success'=>false,
                'message'=>Lang::get('app.bag_query.api.errors.invalid_guid'),
            ]);
        }
        $items=$this->repo->characterItems($guid);
        Audit::log('bag_query','view_items',(string)$guid,['count'=>count($items)]);
        return $this->json(['success'=>true,'data'=>$items]);
    }

    public function apiReduce(Request $request): Response
    {
        $this->requireLogin();
        $guid=(int)$request->input('character_guid',0);
        $inst=(int)$request->input('item_instance_guid',0);
        $qty=(int)$request->input('quantity',0);
        $entry=(int)$request->input('item_entry',0);
        if($guid<=0 || $inst<=0 || $qty<=0){
            return $this->json([
                'success'=>false,
                'message'=>Lang::get('app.bag_query.api.errors.invalid_parameters'),
            ]);
        }
    $res=$this->repo->reduceInstance($guid,$inst,$qty,$entry);
        Audit::log('bag_query','reduce',(string)$inst,[
            'character_guid'=>$guid,
            'item_entry'=>$entry>0?$entry:null,
            'quantity'=>$qty,
            'success'=>$res['success']??false,
            'new_count'=>$res['new_count']??null,
            'message'=>$res['message']??null,
        ]);
        return $this->json($res);
    }
}

