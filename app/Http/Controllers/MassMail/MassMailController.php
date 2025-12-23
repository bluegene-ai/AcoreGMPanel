<?php
/**
 * File: app/Http/Controllers/MassMail/MassMailController.php
 * Purpose: Defines class MassMailController for the app/Http/Controllers/MassMail module.
 * Classes:
 *   - MassMailController
 * Functions:
 *   - __construct()
 *   - index()
 *   - apiAnnounce()
 *   - apiSend()
 *   - apiLogs()
 *   - apiBoost()
 */

namespace Acme\Panel\Http\Controllers\MassMail;

use Acme\Panel\Core\{Config,Controller,Lang,Request,Response};
use Acme\Panel\Domain\MassMail\MassMailService;
use Acme\Panel\Support\{Auth,ServerContext,ServerList};

class MassMailController extends Controller
{
    private MassMailService $svc;
    public function __construct()
    {
        $soap = Config::get('soap', []);
        if(!is_array($soap) || !$soap){
            $soap = [
                'host' => '127.0.0.1',
                'port' => 7878,
                'username' => '',
                'password' => '',
                'uri' => 'urn:AC',
            ];
        }
        $this->svc = new MassMailService($soap);
    }

    public function index(Request $request): Response
    {
    if(!Auth::check()) return $this->redirect('/account/login');

    $reqServer=$request->input('server',null); if($reqServer!==null){ $sid=(int)$reqServer; if(ServerContext::currentId()!==$sid && ServerList::valid($sid)){ ServerContext::set($sid);
 } }
        $logs = $this->svc->recentLogs(30);
    return $this->view('mass_mail.index',[ 'title'=>Lang::get('app.mass_mail.index.page_title'),'logs'=>$logs,'current_server'=>ServerContext::currentId(),'servers'=>ServerList::options() ]);
    }

    public function apiAnnounce(Request $request): Response
    { if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401); $msg=(string)$request->input('message',''); $res=$this->svc->sendAnnounce($msg); return $this->json($res,$res['success']?200:422); }

    public function apiSend(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401);
        $action=$request->input('action','');
        $subject=(string)$request->input('subject','');
        $body=(string)$request->input('body','');
        $targetType=$request->input('target_type','online');
        $custom=$request->input('custom_char_list','');
        $targets=$this->svc->resolveTargets($targetType,$custom);
        $itemsRaw = ($action==='send_item' || $action==='send_item_gold') ? (string)$request->input('items','') : '';
        // Backward compatibility (older clients)
        if(trim($itemsRaw)==='' && ($action==='send_item' || $action==='send_item_gold')){
            $legacyItemId = (int)$request->input('itemId',0);
            $legacyQty = (int)$request->input('quantity',0);
            if($legacyItemId>0 && $legacyQty>0){
                $itemsRaw = $legacyItemId.':'.$legacyQty;
            }
        }
        $amount = ($action==='send_gold' || $action==='send_item_gold') ? (int)$request->input('amount',0) : null;

        $res=$this->svc->sendBulk($action,$subject,$body,$targets,$itemsRaw,$amount);
        return $this->json($res,$res['success']?200:422);
    }

    public function apiLogs(Request $request): Response
    { if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401); $limit=(int)$request->input('limit',30); $rows=$this->svc->recentLogs($limit); return $this->json(['success'=>true,'logs'=>$rows]); }

    public function apiBoost(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.unauthorized')],401);
        $character=(string)$request->input('character','');
        $level=(int)$request->input('level',0);
        $res=$this->svc->boostCharacter($character,$level);
        return $this->json($res,$res['success']?200:422);
    }
}

?>
