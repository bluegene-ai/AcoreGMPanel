<?php
/**
 * File: app/Http/Controllers/AccountController.php
 * Purpose: Defines class AccountController for the app/Http/Controllers module.
 * Classes:
 *   - AccountController
 * Functions:
 *   - __construct()
 *   - maybeSwitchServer()
 *   - index()
 *   - login()
 *   - logout()
 *   - apiList()
 *   - apiCreate()
 *   - apiAccountsByIp()
 *   - apiIpLocation()
 *   - apiCharacters()
 *   - apiCharactersStatus()
 *   - apiSetGm()
 *   - apiBan()
 *   - apiUnban()
 *   - apiChangePassword()
 *   - apiKick()
 *   - logAccountAction()
 *   - logAccountCreate()
 */

namespace Acme\Panel\Http\Controllers;

use Acme\Panel\Core\{Controller,Request,Response,Lang};
use Acme\Panel\Support\{Auth,Audit,Csrf,IpLocationService};
use Acme\Panel\Support\SoapService;
use Acme\Panel\Domain\Account\AccountRepository;
use Acme\Panel\Support\{ServerContext,ServerList};

class AccountController extends Controller
{
    private ?AccountRepository $repo = null;

    private function repo(): AccountRepository
    {
        if ($this->repo === null) {
            $this->repo = new AccountRepository();
        }

        return $this->repo;
    }





    private function maybeSwitchServer(Request $request): void
    {
        $reqServer = $request->input('server', null);
        if($reqServer !== null){
            $sid = (int)$reqServer;
            if(ServerContext::currentId() !== $sid && ServerList::valid($sid)){
                ServerContext::set($sid);
                if ($this->repo !== null) {
                    $this->repo->rebind($sid);
                }
            }
        }
    }

    public function index(Request $request): Response
    {
    if(!Auth::check()) return $this->view('auth.login',[ 'title'=>Lang::get('app.auth.login_title'), 'error'=>null ]);
        $this->maybeSwitchServer($request);
        $type = $request->input('search_type','username');
        $value = $request->input('search_value','');
        $page = (int)$request->input('page',1); $per=20;
        $pager = $this->repo()->search($type,$value,$page,$per);
    return $this->view('account.index',[ 'title'=>Lang::get('app.account.page_title'),'pager'=>$pager,'search_type'=>$type,'search_value'=>$value ]);
    }

    public function login(Request $request): Response
    {
        if($request->method==='POST'){
            $u=$request->input('username'); $p=$request->input('password');
            if(Auth::attempt((string)$u,(string)$p)) {
                Audit::log('auth','login',$u);
                $base = rtrim(\Acme\Panel\Core\Config::get('app.base_path',''),'/');
                $to = ($base?:'') . '/account';
                return new Response('<script>location.href="'.htmlspecialchars($to,ENT_QUOTES).'";</script>');
            }
            return $this->view('auth.login',[ 'title'=>Lang::get('app.auth.login_title'),'error'=>Lang::get('app.auth.error_invalid') ]);
        }
        return $this->view('auth.login',[ 'title'=>Lang::get('app.auth.login_title'),'error'=>null ]);
    }

    public function logout(Request $r): Response
    {
        Auth::logout();
        $base = rtrim(\Acme\Panel\Core\Config::get('app.base_path',''),'/');
        $to = ($base?:'') . '/account/login';
        return new Response('<script>location.href="'.htmlspecialchars($to,ENT_QUOTES).'";</script>');
    }

    public function apiList(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $pager=$this->repo()->search($request->input('search_type','username'),$request->input('search_value',''),(int)$request->input('page',1),20);
        return $this->json(['success'=>true,'page'=>$pager->page,'pages'=>$pager->pages,'total'=>$pager->total,'items'=>$pager->items]);
    }

    public function apiCreate(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);

        $repo = $this->repo();

        $username = trim((string)$request->input('username',''));
        $password = (string)$request->input('password','');
        $confirm = (string)$request->input('password_confirm','');
        $email = trim((string)$request->input('email',''));
        $gmlevel = (int)$request->input('gmlevel',0);
        $context = [
            'username'=>$username,
            'email'=>$email,
            'gmlevel'=>$gmlevel,
            'server'=>ServerContext::currentId(),
            'ip'=>$request->ip(),
        ];

    if($username===''){ $this->logAccountCreate('validate_fail',$context+['reason'=>'empty_username']); return $this->json(['success'=>false,'message'=>Lang::get('app.account.create.errors.username_required')],422); }
    if(strlen($username)>32){ $this->logAccountCreate('validate_fail',$context+['reason'=>'username_too_long']); return $this->json(['success'=>false,'message'=>Lang::get('app.account.create.errors.username_length')],422); }
        if($password===''){ $this->logAccountCreate('validate_fail',$context+['reason'=>'empty_password']); return $this->json(['success'=>false,'message'=>Lang::get('app.account.password.error_empty')],422); }
        if(strlen($password)<8){ $this->logAccountCreate('validate_fail',$context+['reason'=>'password_too_short']); return $this->json(['success'=>false,'message'=>Lang::get('app.account.api.validation.password_min')],422); }
        if($password!==$confirm){ $this->logAccountCreate('validate_fail',$context+['reason'=>'password_mismatch']); return $this->json(['success'=>false,'message'=>Lang::get('app.account.password.error_mismatch')],422); }
        if($email!==''){
            if(strlen($email)>128){ $this->logAccountCreate('validate_fail',$context+['reason'=>'email_too_long']); return $this->json(['success'=>false,'message'=>Lang::get('app.account.create.errors.email_length')],422); }
            if(!filter_var($email, FILTER_VALIDATE_EMAIL)){ $this->logAccountCreate('validate_fail',$context+['reason'=>'email_invalid']); return $this->json(['success'=>false,'message'=>Lang::get('app.account.create.errors.email_invalid')],422); }
        }
        if($gmlevel<0||$gmlevel>3) $gmlevel = 0;

        try {
            $id = $repo->createAccount($username,$password,$email);
        } catch(\InvalidArgumentException $e){
            $this->logAccountCreate('invalid_argument',$context+['error'=>$e->getMessage()]);
            return $this->json(['success'=>false,'message'=>$e->getMessage()],422);
        } catch(\RuntimeException $e){
            $this->logAccountCreate('runtime_exception',$context+['error'=>$e->getMessage()]);
            return $this->json(['success'=>false,'message'=>$e->getMessage()],422);
        } catch(\Throwable $e){
            $this->logAccountCreate('unexpected_error',$context+['error'=>$e->getMessage()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.account.api.errors.create_failed',['message'=>$e->getMessage()])],500);
        }

        if($gmlevel>0){
            $gmContext = $context + ['id'=>$id,'gm'=>$gmlevel,'realm'=>-1,'source'=>'create'];
            $gmOk = $repo->setGmLevel($id,$gmlevel,-1);
            $this->logAccountAction('set_gm',$gmOk?'success':'db_fail',$gmContext);
            $context['gmlevel_set'] = $gmOk ? 'success' : 'failed';
        } else {
            $context['gmlevel_set'] = 'skipped';
        }
        $this->logAccountCreate('success',$context+['id'=>$id]);
        Audit::log('account','create',"id=$id user=$username gm=$gmlevel");
        return $this->json(['success'=>true,'id'=>$id]);
    }

    public function apiAccountsByIp(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $ip = trim((string)$request->input('ip',''));
    if($ip==='') return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_ip')],422);
        $excludeId = (int)$request->input('exclude',0);
        $limit = (int)$request->input('limit',50);
        if($limit<=0) $limit = 50; if($limit>200) $limit=200;
        try {
            $items = $this->repo()->accountsByLastIp($ip, $excludeId, $limit);
        } catch(\Throwable $e){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.query_failed',['message'=>$e->getMessage()])],500);
        }
        return $this->json(['success'=>true,'ip'=>$ip,'items'=>$items]);
    }

    public function apiIpLocation(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $ip = trim((string)$request->input('ip',''));
    if($ip==='') return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_ip')],422);
        $service = new IpLocationService();
        $result = $service->lookup($ip);
        if(!$result['success']){
            return $this->json([
                'success' => false,
                'message' => $result['message'] ?? Lang::get('app.account.ip_lookup.failed'),
                'ip' => $ip,
            ]);
        }
        return $this->json([
            'success' => true,
            'ip' => $ip,
            'location' => $result['text'] ?? Lang::get('app.account.ip_lookup.unknown'),
            'cached' => $result['cached'] ?? false,
            'provider' => $result['provider'] ?? 'ip-api',
            'stale' => $result['stale'] ?? false,
            'message' => $result['message'] ?? null,
        ]);
    }


    public function apiCharacters(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
    $id=(int)$request->input('id',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $serverId = ServerContext::currentId();
        try {
            $chars=$this->repo()->listCharacters($id);
        } catch(\Throwable $e){
            return $this->json(['success'=>false,'message'=>Lang::get('app.account.api.errors.query_characters_failed',['message'=>$e->getMessage()])],500);
        }
        $ban=$this->repo()->banStatus($id);
        return $this->json(['success'=>true,'items'=>$chars,'ban'=>$ban]);
    }



    public function apiCharactersStatus(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
    $id=(int)$request->input('id',0); if($id<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        try {
            $chars=$this->repo()->listCharacters($id);
            $map=[]; foreach($chars as $c){ $map[(int)$c['guid']]=['online'=> (bool)$c['online']]; }
            return $this->json(['success'=>true,'statuses'=>$map,'count'=>count($map)]);
    } catch(\Throwable $e){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.query_failed',['message'=>$e->getMessage()])],500); }
    }

    public function apiSetGm(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $id=(int)$request->input('id',0); $gm=(int)$request->input('gm',0); $realm=(int)$request->input('realm',-1);
        $context = ['id'=>$id,'gm'=>$gm,'realm'=>$realm,'ip'=>$request->ip()];
        if($id<=0){
            $this->logAccountAction('set_gm','validate_fail',$context+['reason'=>'missing_id']);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        }
        if($gm<0||$gm>6){
            $this->logAccountAction('set_gm','validate_fail',$context+['reason'=>'gm_out_of_range']);
            return $this->json(['success'=>false,'message'=>Lang::get('app.account.api.validation.gm_range')],422);
        }
    $ok=$this->repo()->setGmLevel($id,$gm,$realm);
        if($ok){
            $this->logAccountAction('set_gm','success',$context);
            Audit::log('account','set_gm',"id=$id gm=$gm realm=$realm");
        } else {
            $this->logAccountAction('set_gm','db_fail',$context);
        }
        return $this->json(['success'=>$ok]);
    }

    public function apiBan(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $id=(int)$request->input('id',0); $hours=(int)$request->input('hours',0); $reason=(string)$request->input('reason','');
        $context = ['id'=>$id,'hours'=>$hours,'reason'=>$reason,'ip'=>$request->ip()];
        if($id<=0){
            $this->logAccountAction('ban','validate_fail',$context+['reason_code'=>'missing_id']);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        }
    if($reason==='') $reason=Lang::get('app.account.api.defaults.no_reason');
        $context['reason']=$reason;
    $ok=$this->repo()->ban($id,$reason,$hours);
        if($ok){
            $this->logAccountAction('ban','success',$context);
            Audit::log('account','ban',"id=$id hours=$hours reason=$reason");
        } else {
            $this->logAccountAction('ban','db_fail',$context);
        }
        return $this->json(['success'=>$ok]);
    }

    public function apiUnban(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $id=(int)$request->input('id',0);
        $context = ['id'=>$id,'ip'=>$request->ip()];
        if($id<=0){
            $this->logAccountAction('unban','validate_fail',$context+['reason'=>'missing_id']);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        }
        try {
            $cnt=$this->repo()->unban($id);
        } catch(\Throwable $e){
            $this->logAccountAction('unban','error',$context+['error'=>$e->getMessage()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.database',['message'=>$e->getMessage()])],500);
        }
        if($cnt>0){
            $this->logAccountAction('unban','success',$context+['updated'=>$cnt]);
            Audit::log('account','unban',"id=$id updated=$cnt");
        } else {
            $this->logAccountAction('unban','noop',$context+['updated'=>$cnt]);
        }
        return $this->json(['success'=>true,'updated'=>$cnt]);
    }

    public function apiChangePassword(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $id=(int)$request->input('id',0); $user=(string)$request->input('username',''); $pass=(string)$request->input('password','');
        $context = ['id'=>$id,'username'=>$user,'ip'=>$request->ip()];
        if($id<=0 || $user==='' || $pass===''){
            $this->logAccountAction('change_password','validate_fail',$context+['reason'=>'missing_params']);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_params')],422);
        }
        if(strlen($pass)<8){
            $this->logAccountAction('change_password','validate_fail',$context+['reason'=>'password_too_short']);
            return $this->json(['success'=>false,'message'=>Lang::get('app.account.api.validation.password_min')],422);
        }
        try {
            $ok=$this->repo()->changePassword($id,$user,$pass);
        } catch(\Throwable $e){
            $this->logAccountAction('change_password','error',$context+['error'=>$e->getMessage()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.database',['message'=>$e->getMessage()])],500);
        }
        if(!$ok){
            $this->logAccountAction('change_password','unsupported_schema',$context);
            return $this->json(['success'=>false,'message'=>Lang::get('app.account.api.errors.password_schema_unsupported')],422);
        }
        $this->logAccountAction('change_password','success',$context);
        Audit::log('account','change_password',"id=$id user=$user");
        return $this->json(['success'=>true]);
    }

    public function apiKick(Request $request): Response
    {
    if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
    $player=(string)$request->input('player',''); if($player==='') return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_player')],422);
        $soap = new SoapService();
        $res = $soap->execute('.kick '.$player);
        if($res['success']){ Audit::log('account','kick',"player=$player"); }
        return $this->json($res, $res['success']?200:500);
    }

    private function logAccountAction(string $action, string $stage, array $context = []): void
    {
        try {
            $logDir = dirname(__DIR__,3).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
            if(!is_dir($logDir)) @mkdir($logDir,0777,true);
            if(!array_key_exists('ip',$context)){
                $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            }
            $base = [
                'admin' => $_SESSION['panel_user'] ?? null,
                'server' => ServerContext::currentId(),
            ];
            $payload = array_merge($base,$context);
            $line = sprintf('[%s] %s.%s %s'.PHP_EOL,date('Y-m-d H:i:s'),$action,$stage,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            @file_put_contents($logDir.DIRECTORY_SEPARATOR.'account_actions.log',$line,FILE_APPEND);
        } catch(\Throwable $e){  }
    }

    private function logAccountCreate(string $stage, array $context = []): void
    {
        $this->logAccountAction('create',$stage,$context);
    }
}

