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
use Acme\Panel\Domain\Account\AccountMutationHydrator;
use Acme\Panel\Domain\Account\AccountRepository;
use Acme\Panel\Support\{ClientIp,LogPath,ServerContext,ServerList};

class AccountController extends Controller
{
    private ?AccountRepository $repo = null;
    private ?AccountMutationHydrator $mutations = null;

    private function repo(): AccountRepository
    {
        if ($this->repo === null) {
            $this->repo = new AccountRepository();
        }

        return $this->repo;
    }

    private function mutations(): AccountMutationHydrator
    {
        if ($this->mutations === null) {
            $this->mutations = new AccountMutationHydrator();
        }

        return $this->mutations;
    }





    private function maybeSwitchServer(Request $request): void
    {
        $this->switchServerAndRebind($request, $this->repo);
    }

    private function requireAccountListCapability(): void
    {
        $this->requireCapability('accounts.list');
    }

    private function requireAccountIpCapability(): void
    {
        $this->requireCapability('accounts.ip');
    }

    private function requireAccountCharactersCapability(): void
    {
        $this->requireCapability('accounts.characters');
    }

    private function requireAccountCreateCapability(): void
    {
        $this->requireCapability('accounts.create');
    }

    private function requireAccountUpdateCapability(): void
    {
        $this->requireCapability('accounts.update');
    }

    private function requireAccountPasswordCapability(): void
    {
        $this->requireCapability('accounts.password');
    }

    private function requireAccountGmCapability(): void
    {
        $this->requireCapability('accounts.gm');
    }

    private function requireAccountBanCapability(): void
    {
        $this->requireCapability('accounts.ban');
    }

    private function requireAccountKickCapability(): void
    {
        $this->requireCapability('accounts.kick');
    }

    private function requireAccountDeleteCapability(): void
    {
        $this->requireCapability('accounts.delete');
    }

    public function index(Request $request): Response
    {
        if(!Auth::check()) return $this->loginPage();
        $this->requireAccountListCapability();
        $this->maybeSwitchServer($request);
        $state = $this->prepareAccountListState($request);
        $pager = $this->repo()->search(
            $state['search_type'],
            $state['search_value'],
            $state['page'],
            $state['per_page'],
            $state['filters'],
            $state['load_all'],
            $state['sort']
        );
        return $this->pageView('account.index', $this->listViewData($pager, $state['filters'], [
            'search_type' => $state['search_type'],
            'search_value' => $state['search_value'],
            'filter_online' => $state['filters']['online'],
            'filter_ban' => $state['filters']['ban'],
            'exclude_username' => $state['filters']['exclude_username'],
            'load_all' => $state['load_all'],
            'sort' => $state['sort'],
        ], false), [
            'capabilities' => [
                'list' => 'accounts.list',
                'characters' => 'accounts.characters',
                'create' => 'accounts.create',
                'update' => 'accounts.update',
                'password' => 'accounts.password',
                'gm' => 'accounts.gm',
                'ban' => 'accounts.ban',
                'ip' => 'accounts.ip',
                'kick' => 'accounts.kick',
                'delete' => 'accounts.delete',
            ],
        ]);
    }

    public function login(Request $request): Response
    {
        if($request->method==='POST'){
            $u=$request->input('username'); $p=$request->input('password');
            $attempt = Auth::attempt((string)$u,(string)$p,$request->ip());
            if(($attempt['success'] ?? false) === true) {
                Audit::log('auth','login',(string) $u,['ip' => $request->ip()]);
                return $this->redirect('/account');
            }

            $error = Lang::get('app.auth.error_invalid');
            if (($attempt['reason'] ?? '') === 'throttled') {
                $error = Lang::get('app.auth.error_throttled', ['seconds' => (int) ($attempt['retry_after'] ?? 0)]);
            }

            return $this->loginPage($error);
        }
        return $this->loginPage();
    }

    public function logout(Request $r): Response
    {
        Auth::logout();
        return $this->redirect('/account/login');
    }

    public function apiList(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->requireAccountListCapability();
        $this->maybeSwitchServer($request);
        $state = $this->prepareAccountListState($request);
        $pager = $this->repo()->search(
            $state['search_type'],
            $state['search_value'],
            $state['page'],
            $state['per_page'],
            $state['filters'],
            $state['load_all'],
            $state['sort']
        );
        return $this->json(['success'=>true,'page'=>$pager->page,'pages'=>$pager->pages,'total'=>$pager->total,'items'=>$pager->items]);
    }

    public function apiDelete(Request $request): Response
    {
        $this->requireAccountDeleteCapability();
        $this->maybeSwitchServer($request);

        $hydrated = $this->mutations()->delete([
            'id' => $request->input('id', 0),
            'ip' => $request->ip(),
        ]);
        $context = $hydrated['context'];
        if (!$hydrated['valid']) {
            $this->logAccountAction('delete', 'validate_fail', $context + ['reason' => $hydrated['reason']]);
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }
        $id = (int) $hydrated['payload']['id'];

        try {
            $result = $this->repo()->deleteAccountCascade($id);
        } catch(\Throwable $e){
            $this->logAccountAction('delete','error',$context+['error'=>$e->getMessage()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.database',['message'=>$e->getMessage()])],500);
        }

        $this->logAccountAction('delete',$result['success']?'success':'failed',$context+$result);
        if($result['success']){
            Audit::log('account','delete',"id=$id chars_deleted=".((int)($result['characters_deleted']??0)));
        }

        return $this->json(['success'=>(bool)$result['success'],'message'=>$result['message'] ?? null] + $result);
    }

    public function apiBulk(Request $request): Response
    {
        $action = strtolower(trim((string)$request->input('action','')));
        switch ($action) {
            case 'delete':
                $this->requireAccountDeleteCapability();
                break;
            case 'ban':
            case 'unban':
                $this->requireAccountBanCapability();
                break;
            default:
                $this->requireLogin();
                break;
        }
        $this->maybeSwitchServer($request);

        $hydrated = $this->mutations()->bulk([
            'action' => $action,
            'ids' => $request->input('ids', []),
            'hours' => $request->input('hours', 0),
            'reason' => $request->input('reason', ''),
            'ip' => $request->ip(),
        ]);
        if (!$hydrated['valid']) {
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }

        $action = (string) $hydrated['payload']['action'];
        $ids = $hydrated['payload']['ids'];
        $hours = (int) $hydrated['payload']['hours'];
        $reason = (string) $hydrated['payload']['reason'];

        $okCount = 0;
        $fail = [];

        foreach($ids as $id){
            $context = ['id'=>$id,'action'=>$action,'ip'=>$request->ip()];
            try {
                if($action==='ban'){
                    $ok = $this->repo()->ban($id,$reason,$hours);
                    $this->logAccountAction('bulk_ban',$ok?'success':'db_fail',$context+['hours'=>$hours,'reason'=>$reason]);
                    if($ok){ Audit::log('account','ban',"id=$id hours=$hours reason=$reason"); }
                } elseif($action==='unban'){
                    $cnt = $this->repo()->unban($id);
                    $ok = true;
                    $this->logAccountAction('bulk_unban',$cnt>0?'success':'noop',$context+['updated'=>$cnt]);
                    if($cnt>0){ Audit::log('account','unban',"id=$id updated=$cnt"); }
                } else { // delete
                    $res = $this->repo()->deleteAccountCascade($id);
                    $ok = (bool)($res['success'] ?? false);
                    $this->logAccountAction('bulk_delete',$ok?'success':'failed',$context+$res);
                    if($ok){ Audit::log('account','delete',"id=$id chars_deleted=".((int)($res['characters_deleted']??0))); }
                }
            } catch(\Throwable $e){
                $ok = false;
                $this->logAccountAction('bulk_'.$action,'error',$context+['error'=>$e->getMessage()]);
            }

            if($ok){
                $okCount++;
            } else {
                $fail[] = $id;
            }
        }

        $success = $okCount > 0 && count($fail) === 0;
        return $this->json([
            'success' => $success,
            'action' => $action,
            'requested' => count($ids),
            'ok' => $okCount,
            'failed' => count($fail),
            'failed_ids' => array_slice($fail, 0, 50),
        ]);
    }

    public function apiUpdateEmail(Request $request): Response
    {
        $this->requireAccountUpdateCapability();
        $this->maybeSwitchServer($request);

        $hydrated = $this->mutations()->updateEmail([
            'id' => $request->input('id', 0),
            'email' => $request->input('email', ''),
            'ip' => $request->ip(),
        ]);
        if (!$hydrated['valid']) {
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }
        $id = (int) $hydrated['payload']['id'];
        $email = (string) $hydrated['payload']['email'];

        try {
            $res = $this->repo()->updateEmail($id, $email);
        } catch(\Throwable $e){
            $this->logAccountAction('email','error',['id'=>$id,'email'=>$email,'error'=>$e->getMessage(),'ip'=>$request->ip()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.database',['message'=>$e->getMessage()])],500);
        }

        $this->logAccountAction('email',($res['success']??false)?'success':'failed',['id'=>$id,'email'=>$email,'ip'=>$request->ip()]+$res);
        if(!empty($res['success'])){
            Audit::log('account','update_email',"id=$id email=$email");
        }
        return $this->json($res);
    }

    public function apiUpdateUsername(Request $request): Response
    {
        $this->requireAccountUpdateCapability();
        $this->maybeSwitchServer($request);

        $hydrated = $this->mutations()->updateUsername([
            'id' => $request->input('id', 0),
            'username' => $request->input('username', ''),
            'password' => $request->input('password', ''),
            'ip' => $request->ip(),
        ]);
        if (!$hydrated['valid']) {
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }
        $id = (int) $hydrated['payload']['id'];
        $newUsername = (string) $hydrated['payload']['username'];
        $password = (string) $hydrated['payload']['password'];

        try {
            $res = $this->repo()->updateUsername($id, $newUsername, $password);
        } catch(\Throwable $e){
            $this->logAccountAction('rename','error',['id'=>$id,'username'=>$newUsername,'error'=>$e->getMessage(),'ip'=>$request->ip()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.database',['message'=>$e->getMessage()])],500);
        }

        $this->logAccountAction('rename',($res['success']??false)?'success':'failed',['id'=>$id,'username'=>$newUsername,'ip'=>$request->ip()]+$res);
        if(!empty($res['success'])){
            Audit::log('account','rename',"id=$id username=$newUsername");
        }
        return $this->json($res);
    }

    public function apiCreate(Request $request): Response
    {
        $this->requireAccountCreateCapability();
        $this->maybeSwitchServer($request);

        $repo = $this->repo();

        $hydrated = $this->mutations()->create([
            'username' => $request->input('username', ''),
            'password' => $request->input('password', ''),
            'password_confirm' => $request->input('password_confirm', ''),
            'email' => $request->input('email', ''),
            'gmlevel' => $request->input('gmlevel', 0),
            'server' => ServerContext::currentId(),
            'ip' => $request->ip(),
        ]);
        $context = $hydrated['context'];
        if (!$hydrated['valid']) {
            $this->logAccountCreate('validate_fail', $context + ['reason' => $hydrated['reason']]);
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }

        $username = (string) $hydrated['payload']['username'];
        $password = (string) $hydrated['payload']['password'];
        $email = (string) $hydrated['payload']['email'];
        $gmlevel = (int) $hydrated['payload']['gmlevel'];

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
        $this->requireAccountIpCapability();
        $this->maybeSwitchServer($request);
        $state = $this->prepareAccountIpLookupState($request);
        if($state['ip']==='') return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_ip')],422);
        try {
            $items = $this->repo()->accountsByLastIp($state['ip'], $state['exclude_id'], $state['limit']);
        } catch(\Throwable $e){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.query_failed',['message'=>$e->getMessage()])],500);
        }
        return $this->json(['success'=>true,'ip'=>$state['ip'],'items'=>$items]);
    }

    public function apiIpLocation(Request $request): Response
    {
        $this->requireAccountIpCapability();
        $this->maybeSwitchServer($request);
        $state = $this->prepareAccountIpLookupState($request);
        if($state['ip']==='') return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_ip')],422);
        $service = new IpLocationService();
        $result = $service->lookup($state['ip']);
        if(!$result['success']){
            return $this->json([
                'success' => false,
                'message' => $result['message'] ?? Lang::get('app.account.ip_lookup.failed'),
                'ip' => $state['ip'],
            ]);
        }
        return $this->json([
            'success' => true,
            'ip' => $state['ip'],
            'location' => $result['text'] ?? Lang::get('app.account.ip_lookup.unknown'),
            'cached' => $result['cached'] ?? false,
            'provider' => $result['provider'] ?? 'ip-api',
            'stale' => $result['stale'] ?? false,
            'message' => $result['message'] ?? null,
        ]);
    }


    public function apiCharacters(Request $request): Response
    {
        $this->requireAccountCharactersCapability();
        $this->maybeSwitchServer($request);
        $state = $this->prepareAccountCharacterLookupState($request);
        if($state['id']<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        try {
            $payload = $this->repo()->accountCharactersPayload($state['id']);
        } catch(\Throwable $e){
            return $this->json(['success'=>false,'message'=>Lang::get('app.account.api.errors.query_characters_failed',['message'=>$e->getMessage()])],500);
        }
        return $this->json(['success'=>true,'items'=>$payload['items'],'ban'=>$payload['ban']]);
    }



    public function apiCharactersStatus(Request $request): Response
    {
        $this->requireAccountCharactersCapability();
        $this->maybeSwitchServer($request);
        $state = $this->prepareAccountCharacterLookupState($request);
        if($state['id']<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        try {
            $chars=$this->repo()->listCharacters($state['id']);
            $map=[]; foreach($chars as $c){ $map[(int)$c['guid']]=['online'=> (bool)$c['online']]; }
            return $this->json(['success'=>true,'statuses'=>$map,'count'=>count($map)]);
    } catch(\Throwable $e){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.query_failed',['message'=>$e->getMessage()])],500); }
    }

    public function apiSetGm(Request $request): Response
    {
        $this->requireAccountGmCapability();
        $this->maybeSwitchServer($request);
        $hydrated = $this->mutations()->setGm([
            'id' => $request->input('id', 0),
            'gm' => $request->input('gm', 0),
            'realm' => $request->input('realm', -1),
            'ip' => $request->ip(),
        ]);
        $context = $hydrated['context'];
        if (!$hydrated['valid']) {
            $this->logAccountAction('set_gm', 'validate_fail', $context + ['reason' => $hydrated['reason']]);
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }

        $id = (int) $hydrated['payload']['id'];
        $gm = (int) $hydrated['payload']['gm'];
        $realm = (int) $hydrated['payload']['realm'];
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
        $this->requireAccountBanCapability();
        $this->maybeSwitchServer($request);
        $hydrated = $this->mutations()->ban([
            'id' => $request->input('id', 0),
            'hours' => $request->input('hours', 0),
            'reason' => $request->input('reason', ''),
            'ip' => $request->ip(),
        ]);
        $context = $hydrated['context'];
        if (!$hydrated['valid']) {
            $this->logAccountAction('ban', 'validate_fail', $context + ['reason' => $hydrated['reason']]);
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }

        $id = (int) $hydrated['payload']['id'];
        $hours = (int) $hydrated['payload']['hours'];
        $reason = (string) $hydrated['payload']['reason'];
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
        $this->requireAccountBanCapability();
        $this->maybeSwitchServer($request);
        $hydrated = $this->mutations()->unban([
            'id' => $request->input('id', 0),
            'ip' => $request->ip(),
        ]);
        $context = $hydrated['context'];
        if (!$hydrated['valid']) {
            $this->logAccountAction('unban', 'validate_fail', $context + ['reason' => $hydrated['reason']]);
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }

        $id = (int) $hydrated['payload']['id'];
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
        $this->requireAccountPasswordCapability();
        $this->maybeSwitchServer($request);
        $hydrated = $this->mutations()->changePassword([
            'id' => $request->input('id', 0),
            'username' => $request->input('username', ''),
            'password' => $request->input('password', ''),
            'ip' => $request->ip(),
        ]);
        $context = $hydrated['context'];
        if (!$hydrated['valid']) {
            $this->logAccountAction('change_password', 'validate_fail', $context + ['reason' => $hydrated['reason']]);
            return $this->json(['success' => false, 'message' => $hydrated['message']], $hydrated['status']);
        }

        $id = (int) $hydrated['payload']['id'];
        $user = (string) $hydrated['payload']['username'];
        $pass = (string) $hydrated['payload']['password'];
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
        $this->requireAccountKickCapability();
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
            if(!array_key_exists('ip',$context)){
                $context['ip'] = ClientIp::resolve($_SERVER);
            }
            $base = [
                'admin' => $_SESSION['panel_user'] ?? null,
                'server' => ServerContext::currentId(),
            ];
            $payload = array_merge($base,$context);
            $line = sprintf('[%s] %s.%s %s',date('Y-m-d H:i:s'),$action,$stage,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            LogPath::appendLine('account_actions.log', $line, true, 0777);
        } catch(\Throwable $e){  }
    }

    private function logAccountCreate(string $stage, array $context = []): void
    {
        $this->logAccountAction('create',$stage,$context);
    }

    private function loginPage(?string $error = null): Response
    {
        return $this->pageView('auth.login', [
            'error' => $error,
        ]);
    }

    private function prepareAccountListState(Request $request): array
    {
        $filters = [
            'online' => $this->normalizedEnum($request, 'online', ['any', 'online', 'offline'], 'any'),
            'ban' => $this->normalizedEnum($request, 'ban', ['any', 'banned', 'unbanned'], 'any'),
            'exclude_username' => $this->normalizedString($request, 'exclude_username'),
        ];

        return [
            'search_type' => $this->normalizedEnum($request, 'search_type', ['username', 'id'], 'username'),
            'search_value' => (string) $request->input('search_value', ''),
            'filters' => $filters,
            'sort' => $this->normalizedEnum(
                $request,
                'sort',
                ['', 'id_asc','id_desc','online_asc','online_desc','last_login_asc','last_login_desc'],
                ''
            ),
            'load_all' => $this->normalizedBoolFlag($request, 'load_all'),
            'page' => $this->normalizedPage($request),
            'per_page' => 20,
        ];
    }

    private function prepareAccountIpLookupState(Request $request): array
    {
        return [
            'ip' => $this->normalizedString($request, 'ip'),
            'exclude_id' => max(0, (int) $request->input('exclude', 0)),
            'limit' => $this->boundedInt($request, 'limit', 50, 1, 200),
        ];
    }

    private function prepareAccountCharacterLookupState(Request $request): array
    {
        return [
            'id' => max(0, (int) $request->input('id', 0)),
        ];
    }
}

