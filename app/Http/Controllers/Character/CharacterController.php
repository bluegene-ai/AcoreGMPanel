<?php
/**
 * File: app/Http/Controllers/Character/CharacterController.php
 * Purpose: Character listing, detail, and moderation endpoints.
 */

namespace Acme\Panel\Http\Controllers\Character;

use Acme\Panel\Core\{Controller,Lang,Request,Response};
use Acme\Panel\Domain\Character\CharacterRepository;
use Acme\Panel\Support\{Auth,Audit,LogPath,ServerContext,ServerList};
use Acme\Panel\Support\NfuwowNameResolver;
use Acme\Panel\Support\SoapService;

class CharacterController extends Controller
{
    private ?CharacterRepository $repo = null;

    private function repo(): CharacterRepository
    {
        if($this->repo === null){
            $this->repo = new CharacterRepository();
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
        if(!Auth::check()) return $this->redirect('/account/login');
        $this->maybeSwitchServer($request);

        $sort = (string)$request->input('sort','');
        $allowedSort = ['', 'guid_asc','guid_desc','logout_asc','logout_desc','level_asc','level_desc','online_asc','online_desc'];
        if(!in_array($sort,$allowedSort,true)){
            $sort = '';
        }
        $loadAll = ((int)$request->input('load_all',0) === 1);

        $filters = [
            'name' => (string)$request->input('name',''),
            'guid' => (int)$request->input('guid',0),
            'account' => (string)$request->input('account',''),
            'level_min' => (int)$request->input('level_min',0),
            'level_max' => (int)$request->input('level_max',0),
            'online' => in_array($request->input('online','any'),['online','offline'],true) ? $request->input('online','any') : 'any',
        ];

        $page = (int)$request->input('page',1); $per=20;
        $pager = $this->repo()->search($filters,$page,$per,$loadAll,$sort);

        return $this->view('character.index',[
            'title' => Lang::get('app.character.index.title'),
            'pager' => $pager,
            'filters' => $filters,
            'sort' => $sort,
            'load_all' => $loadAll,
            'current_server' => ServerContext::currentId(),
            'servers' => ServerList::options(),
        ]);
    }

    public function show(Request $request): Response
    {
        if(!Auth::check()) return $this->redirect('/account/login');
        $this->maybeSwitchServer($request);

        $guid = (int)$request->input('guid',0);
        if($guid <= 0){
            return $this->redirect('/character');
        }

        $summary = $this->repo()->findSummary($guid);
        if(!$summary){
            return $this->view('character.show',[ 'title'=>Lang::get('app.character.show.title_not_found',['guid'=>$guid]), 'summary'=>null, 'inventory'=>[], 'error'=>Lang::get('app.common.errors.not_found') ]);
        }

        $inventory = $this->repo()->inventory($guid);
        $skills = $this->repo()->skills($guid);
        $spells = $this->repo()->spells($guid);
        $reps = $this->repo()->reputations($guid);
        $quests = $this->repo()->quests($guid);
        $auras = $this->repo()->auras($guid);
        $cooldowns = $this->repo()->cooldowns($guid);
        $achievements = $this->repo()->achievements($guid);
        $mailCount = $this->repo()->mailCount($guid);

        return $this->view('character.show',[
            'title' => Lang::get('app.character.show.title',['name'=>$summary['name'],'guid'=>$guid]),
            'summary' => $summary,
            'inventory' => $inventory,
            'skills' => $skills,
            'spells' => $spells,
            'reputations' => $reps,
            'quests' => $quests,
            'auras' => $auras,
            'cooldowns' => $cooldowns,
            'achievements' => $achievements,
            'mail_count' => $mailCount,
            'error' => null,
        ]);
    }

    public function apiList(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);

        $sort = (string)$request->input('sort','');
        $allowedSort = ['', 'guid_asc','guid_desc','logout_asc','logout_desc','level_asc','level_desc','online_asc','online_desc'];
        if(!in_array($sort,$allowedSort,true)){
            $sort = '';
        }
        $loadAll = ((int)$request->input('load_all',0) === 1);

        $filters = [
            'name' => (string)$request->input('name',''),
            'guid' => (int)$request->input('guid',0),
            'account' => (string)$request->input('account',''),
            'level_min' => (int)$request->input('level_min',0),
            'level_max' => (int)$request->input('level_max',0),
            'online' => in_array($request->input('online','any'),['online','offline'],true) ? $request->input('online','any') : 'any',
        ];

        $pager = $this->repo()->search($filters,(int)$request->input('page',1),20,$loadAll,$sort);
        return $this->json(['success'=>true,'page'=>$pager->page,'pages'=>$pager->pages,'total'=>$pager->total,'items'=>$pager->items]);
    }

    public function apiShow(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid = (int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary) return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404);
        $inventory = $this->repo()->inventory($guid);
        $skills = $this->repo()->skills($guid);
        $spells = $this->repo()->spells($guid);
        $reps = $this->repo()->reputations($guid);
        $quests = $this->repo()->quests($guid);
        $auras = $this->repo()->auras($guid);
        $cooldowns = $this->repo()->cooldowns($guid);
        $ach = $this->repo()->achievements($guid);
        $mailCount = $this->repo()->mailCount($guid);
        return $this->json([
            'success'=>true,
            'summary'=>$summary,
            'inventory'=>$inventory,
            'skills'=>$skills,
            'spells'=>$spells,
            'reputations'=>$reps,
            'quests'=>$quests,
            'auras'=>$auras,
            'cooldowns'=>$cooldowns,
            'achievements'=>$ach,
            'mail_count'=>$mailCount,
        ]);
    }

    public function apiNames(Request $request): Response
    {
        if(!Auth::check()) {
            return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        }

        $type = strtolower(trim((string)$request->input('type','')));
        $allowed = ['spell','skill','achievement','achievementcriteria','quest','faction'];
        if(!in_array($type,$allowed,true)){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_params')],422);
        }

        $raw = (string)$request->input('ids','');
        $parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = array_values(array_unique(array_filter(array_map('intval',$parts), static fn($v)=>$v>0)));
        if(!$ids){
            return $this->json(['success'=>true,'type'=>$type,'names'=>[]]);
        }

        if(count($ids) > 80){
            $ids = array_slice($ids, 0, 80);
        }

        try {
            $names = NfuwowNameResolver::resolveMany($type, $ids);
        } catch(\Throwable $e) {
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.query_failed',['message'=>$e->getMessage()])],500);
        }

        return $this->json(['success'=>true,'type'=>$type,'names'=>$names]);
    }

    public function apiBan(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid = (int)$request->input('guid',0);
        $hours = (int)$request->input('hours',0);
        $reason = trim((string)$request->input('reason',''));
        if($guid<=0){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        }
        if($reason==='') $reason = Lang::get('app.character.actions.default_reason');

        $ok = $this->repo()->ban($guid,$reason,$hours);
        $this->logCharacterAction('ban',$ok?'success':'db_fail',['guid'=>$guid,'hours'=>$hours,'reason'=>$reason,'ip'=>$request->ip()]);
        if($ok){
            Audit::log('character','ban',"guid=$guid hours=$hours reason=$reason");
        }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiUnban(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid = (int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        try {
            $cnt = $this->repo()->unban($guid);
        } catch(\Throwable $e){
            $this->logCharacterAction('unban','error',['guid'=>$guid,'error'=>$e->getMessage(),'ip'=>$request->ip()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.database',['message'=>$e->getMessage()])],500);
        }
        $this->logCharacterAction('unban',$cnt>0?'success':'noop',['guid'=>$guid,'updated'=>$cnt,'ip'=>$request->ip()]);
        if($cnt>0){ Audit::log('character','unban',"guid=$guid updated=$cnt"); }
        return $this->json(['success'=>true,'updated'=>$cnt,'message'=>Lang::get('app.character.actions.success')]);
    }

    public function apiSetLevel(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid = (int)$request->input('guid',0);
        $level = (int)$request->input('level',0);
        if($guid<=0 || $level<=0){
            $this->logCharacterAction('set_level','validate_fail',['guid'=>$guid,'level'=>$level,'ip'=>$request->ip()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_params')],422);
        }
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404);
        }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        if($level<1) $level=1; if($level>255) $level=255;
        $ok = $this->repo()->setLevel($guid,$level);
        $this->logCharacterAction('set_level',$ok?'success':'db_fail',['guid'=>$guid,'level'=>$level,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','set_level',"guid=$guid level=$level"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiSetGold(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid = (int)$request->input('guid',0);
        $copper = (int)$request->input('copper',-1);
        if($guid<=0 || $copper<0){
            $this->logCharacterAction('set_gold','validate_fail',['guid'=>$guid,'copper'=>$copper,'ip'=>$request->ip()]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_params')],422);
        }
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404);
        }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $ok = $this->repo()->setGold($guid,$copper);
        $this->logCharacterAction('set_gold',$ok?'success':'db_fail',['guid'=>$guid,'copper'=>$copper,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','set_gold',"guid=$guid copper=$copper"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiKick(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid = (int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary) return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404);
        $name = $summary['name'];
        $soap = new SoapService();
        $res = $soap->execute('.kick '.$name);
        $this->logCharacterAction('kick',$res['success']?'success':'fail',['guid'=>$guid,'name'=>$name,'ip'=>$request->ip(),'message'=>$res['message'] ?? null]);
        if($res['success']){ Audit::log('character','kick',"guid=$guid name=$name"); }
        return $this->json($res + ['message'=>$res['message'] ?? ($res['success']?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed'))], $res['success']?200:500);
    }

    public function apiTeleport(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid=(int)$request->input('guid',0);
        $map=(int)$request->input('map',0);
        $zone=(int)$request->input('zone',0);
        $x=(float)$request->input('x',0);
        $y=(float)$request->input('y',0);
        $z=(float)$request->input('z',0);
        if($guid<=0){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422); }
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $ok = $this->repo()->teleport($guid,$map,$zone,$x,$y,$z);
        $this->logCharacterAction('teleport',$ok?'success':'db_fail',['guid'=>$guid,'map'=>$map,'zone'=>$zone,'x'=>$x,'y'=>$y,'z'=>$z,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','teleport',"guid=$guid map=$map zone=$zone x=$x y=$y z=$z"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiUnstuck(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid=(int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $ok = $this->repo()->unstuck($guid);
        $this->logCharacterAction('unstuck',$ok?'success':'db_fail',['guid'=>$guid,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','unstuck',"guid=$guid"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiResetTalents(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid=(int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $ok = $this->repo()->resetTalents($guid);
        $this->logCharacterAction('reset_talents',$ok?'success':'db_fail',['guid'=>$guid,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','reset_talents',"guid=$guid"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiResetSpells(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid=(int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $ok = $this->repo()->resetSpells($guid);
        $this->logCharacterAction('reset_spells',$ok?'success':'db_fail',['guid'=>$guid,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','reset_spells',"guid=$guid"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiResetCooldowns(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid=(int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $ok = $this->repo()->resetCooldowns($guid);
        $this->logCharacterAction('reset_cooldowns',$ok?'success':'db_fail',['guid'=>$guid,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','reset_cooldowns',"guid=$guid"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiRenameFlag(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid=(int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $ok = $this->repo()->setRenameFlag($guid);
        $this->logCharacterAction('rename_flag',$ok?'success':'db_fail',['guid'=>$guid,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','rename_flag',"guid=$guid"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    public function apiDelete(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->maybeSwitchServer($request);
        $guid=(int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $ok = $this->repo()->deleteCharacter($guid);
        $this->logCharacterAction('delete',$ok?'success':'db_fail',['guid'=>$guid,'ip'=>$request->ip()]);
        if($ok){ Audit::log('character','delete',"guid=$guid"); }
        return $this->json(['success'=>$ok,'message'=>$ok?Lang::get('app.character.actions.success'):Lang::get('app.character.actions.failed')]);
    }

    private function logCharacterAction(string $action,string $stage,array $context=[]): void
    {
        try {
            $payload = date('Y-m-d H:i:s').' ['.$action.'|'.$stage.'] '.json_encode($context, JSON_UNESCAPED_SLASHES);
            LogPath::appendLine('character_actions.log', $payload, true, 0775);
        } catch(\Throwable $e){
            // swallow
        }
    }
}
