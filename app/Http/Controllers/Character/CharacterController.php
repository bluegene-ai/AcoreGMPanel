<?php
/**
 * File: app/Http/Controllers/Character/CharacterController.php
 * Purpose: Character listing, detail, and moderation endpoints.
 */

namespace Acme\Panel\Http\Controllers\Character;

use Acme\Panel\Core\{Controller,Lang,Request,Response};
use Acme\Panel\Domain\Character\CharacterRepository;
use Acme\Panel\Domain\CharacterBoost\BoostTemplateRepository;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostGuardException;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostNotFoundException;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostService;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostSoapException;
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
        $this->switchServerAndRebind($request, $this->repo);
    }

    private function requireCharacterListCapability(): void
    {
        $this->requireCapability('characters.list');
    }

    private function requireCharacterDetailsCapability(): void
    {
        $this->requireCapability('characters.details');
    }

    private function requireCharacterBanCapability(): void
    {
        $this->requireCapability('characters.ban');
    }

    private function requireCharacterLevelCapability(): void
    {
        $this->requireCapability('characters.level');
    }

    private function requireCharacterGoldCapability(): void
    {
        $this->requireCapability('characters.gold');
    }

    private function requireCharacterKickCapability(): void
    {
        $this->requireCapability('characters.kick');
    }

    private function requireCharacterTeleportCapability(): void
    {
        $this->requireCapability('characters.teleport');
    }

    private function requireCharacterResetCapability(): void
    {
        $this->requireCapability('characters.reset');
    }

    private function requireCharacterDeleteCapability(): void
    {
        $this->requireCapability('characters.delete');
    }

    public function index(Request $request): Response
    {
        if(!Auth::check()) return $this->redirect('/account/login');
        $this->requireCharacterListCapability();
        $this->maybeSwitchServer($request);

        $state = $this->prepareCharacterListState($request);
        $pager = $this->repo()->search(
            $state['filters'],
            $state['page'],
            $state['per_page'],
            $state['load_all'],
            $state['sort']
        );

        return $this->pageView('character.index', $this->listViewData($pager, $state['filters'], [
            'sort' => $state['sort'],
            'load_all' => $state['load_all'],
        ]), [
            'capabilities' => [
                'list' => 'characters.list',
                'details' => 'characters.details',
                'ban' => 'characters.ban',
                'delete' => 'characters.delete',
            ],
        ]);
    }

    private function prepareCharacterListState(Request $request): array
    {
        return [
            'filters' => [
                'name' => $this->normalizedString($request, 'name'),
                'guid' => $request->int('guid', 0),
                'account' => $this->normalizedString($request, 'account'),
                'level_min' => $request->int('level_min', 0),
                'level_max' => $request->int('level_max', 0),
                'online' => $this->normalizedEnum($request, 'online', ['any', 'online', 'offline'], 'any'),
            ],
            'sort' => $this->normalizedEnum(
                $request,
                'sort',
                ['', 'guid_asc','guid_desc','logout_asc','logout_desc','level_asc','level_desc','online_asc','online_desc'],
                ''
            ),
            'load_all' => $this->normalizedBoolFlag($request, 'load_all'),
            'page' => $this->normalizedPage($request),
            'per_page' => 20,
        ];
    }

    public function show(Request $request): Response
    {
        if(!Auth::check()) return $this->redirect('/account/login');
        $this->requireCharacterDetailsCapability();
        $this->maybeSwitchServer($request);

        $guid = (int)$request->input('guid',0);
        if($guid <= 0){
            return $this->redirect('/character');
        }

        $summary = $this->repo()->findSummary($guid);
        if(!$summary){
            return $this->pageView('character.show',[ 'summary'=>null, 'inventory'=>[], 'error'=>Lang::get('app.common.errors.not_found'), 'guid'=>$guid ], [
                'capabilities' => [
                    'details' => 'characters.details',
                    'ban' => 'characters.ban',
                    'delete' => 'characters.delete',
                    'level' => 'characters.level',
                    'gold' => 'characters.gold',
                    'teleport' => 'characters.teleport',
                    'reset' => 'characters.reset',
                    'boost' => 'boost.apply',
                    'boost_templates' => 'boost.templates',
                    'boost_codes' => 'boost.codes',
                ],
            ]);
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

        $serverCfg = ServerContext::server();
        $realmId = (int)($serverCfg['realm_id'] ?? 1);
        $boostTemplates = Auth::can('boost.apply')
            ? (new BoostTemplateRepository())->listForRealm($realmId)
            : [];

        return $this->pageView('character.show',[
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
            'boost_templates' => $boostTemplates,
            'error' => null,
        ], [
            'capabilities' => [
                'details' => 'characters.details',
                'ban' => 'characters.ban',
                'delete' => 'characters.delete',
                'level' => 'characters.level',
                'gold' => 'characters.gold',
                'teleport' => 'characters.teleport',
                'reset' => 'characters.reset',
                'boost' => 'boost.apply',
                'boost_templates' => 'boost.templates',
                'boost_codes' => 'boost.codes',
            ],
        ]);
    }

    public function apiBoost(Request $request): Response
    {
        $this->requireCapability('boost.apply');

        $this->maybeSwitchServer($request);

        $guid = (int)$request->input('guid',0);
        $templateIdRaw = $request->input('template_id', null);
        $templateId = $templateIdRaw === null || $templateIdRaw === '' ? null : (int)$templateIdRaw;
        $targetLevelRaw = $request->input('target_level', null);
        $targetLevel = $targetLevelRaw === null || $targetLevelRaw === '' ? null : (int)$targetLevelRaw;

        if($guid <= 0){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        }

        $serverCfg = ServerContext::server();
        $realmId = (int)($serverCfg['realm_id'] ?? 1);
        try {
            $svc = new CharacterBoostService(ServerContext::currentId());
            $payload = $svc->boostByGuid(
                $realmId,
                $guid,
                $templateId,
                $targetLevel,
                [
                    'name' => (string)($_SESSION['panel_user'] ?? 'system'),
                    'ip' => (string)$request->ip(),
                ]
            );
        } catch (CharacterBoostNotFoundException $e) {
            Audit::log('character','boost_failed', 'guid='.(string)$guid, [
                'realm_id' => $realmId,
                'server_id' => ServerContext::currentId(),
                'guid' => $guid,
                'template_id' => $templateId,
                'target_level' => $targetLevel,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['success'=>false,'message'=>$e->getMessage()],404);
        } catch (CharacterBoostGuardException $e) {
            Audit::log('character','boost_failed', 'guid='.(string)$guid, [
                'realm_id' => $realmId,
                'server_id' => ServerContext::currentId(),
                'guid' => $guid,
                'template_id' => $templateId,
                'target_level' => $targetLevel,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['success'=>false,'message'=>$e->getMessage()],422);
        } catch (CharacterBoostSoapException $e) {
            Audit::log('character','boost_failed', 'guid='.(string)$guid, [
                'realm_id' => $realmId,
                'server_id' => ServerContext::currentId(),
                'guid' => $guid,
                'template_id' => $templateId,
                'target_level' => $targetLevel,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['success'=>false,'message'=>$e->getMessage()],500);
        } catch(\Throwable $e) {
            $this->logCharacterAction('boost', 'error', [
                'guid' => $guid,
                'template_id' => $templateId,
                'target_level' => $targetLevel,
                'server_id' => ServerContext::currentId(),
                'realm_id' => $realmId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            Audit::log('character','boost_failed', 'guid='.(string)$guid, [
                'realm_id' => $realmId,
                'server_id' => ServerContext::currentId(),
                'guid' => $guid,
                'template_id' => $templateId,
                'target_level' => $targetLevel,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.request_failed_retry')],500);
        }

        $ch = $payload['character'] ?? [];
        $commands = $payload['commands'] ?? [];

        Audit::log('character','boost', 'guid='.(string)$guid, [
            'realm_id' => $realmId,
            'character' => $ch,
            'commands' => array_map(static function($c){
                return [
                    'command' => $c['command'] ?? null,
                    'success' => $c['response']['success'] ?? null,
                ];
            }, is_array($commands) ? $commands : []),
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.character.actions.boost_success'),
            'payload' => $payload,
        ]);
    }

    public function apiList(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->requireCharacterListCapability();
        $this->maybeSwitchServer($request);

        $state = $this->prepareCharacterListState($request);
        $pager = $this->repo()->search(
            $state['filters'],
            $state['page'],
            $state['per_page'],
            $state['load_all'],
            $state['sort']
        );
        return $this->json(['success'=>true,'page'=>$pager->page,'pages'=>$pager->pages,'total'=>$pager->total,'items'=>$pager->items]);
    }

    public function apiShow(Request $request): Response
    {
        if(!Auth::check()) return $this->json(['success'=>false,'message'=>Lang::get('app.auth.errors.not_logged_in')],403);
        $this->requireCharacterDetailsCapability();
        $this->maybeSwitchServer($request);
        $state = $this->prepareCharacterDetailState($request);
        if($state['guid']<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($state['guid']);
        if(!$summary) return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404);
        $inventory = $this->repo()->inventory($state['guid']);
        $skills = $this->repo()->skills($state['guid']);
        $spells = $this->repo()->spells($state['guid']);
        $reps = $this->repo()->reputations($state['guid']);
        $quests = $this->repo()->quests($state['guid']);
        $auras = $this->repo()->auras($state['guid']);
        $cooldowns = $this->repo()->cooldowns($state['guid']);
        $ach = $this->repo()->achievements($state['guid']);
        $mailCount = $this->repo()->mailCount($state['guid']);
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

        $this->requireCharacterListCapability();

        $state = $this->prepareCharacterNameLookupState($request);
        if($state['type'] === ''){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_params')],422);
        }

        if(!$state['ids']){
            return $this->json(['success'=>true,'type'=>$state['type'],'names'=>[]]);
        }

        try {
            $names = NfuwowNameResolver::resolveMany($state['type'], $state['ids']);
        } catch(\Throwable $e) {
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.query_failed',['message'=>$e->getMessage()])],500);
        }

        return $this->json(['success'=>true,'type'=>$state['type'],'names'=>$names]);
    }

    private function prepareCharacterDetailState(Request $request): array
    {
        return [
            'guid' => max(0, (int) $request->input('guid', 0)),
        ];
    }

    private function prepareCharacterNameLookupState(Request $request): array
    {
        $type = $this->normalizedEnum(
            $request,
            'type',
            ['spell', 'skill', 'achievement', 'achievementcriteria', 'quest', 'faction'],
            ''
        );
        $raw = $this->normalizedString($request, 'ids');
        $parts = preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $parts), static fn($value) => $value > 0)));

        if (count($ids) > 80) {
            $ids = array_slice($ids, 0, 80);
        }

        return [
            'type' => $type,
            'ids' => $ids,
        ];
    }

    public function apiBan(Request $request): Response
    {
        $this->requireCharacterBanCapability();
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
        $this->requireCharacterBanCapability();
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
        $this->requireCharacterLevelCapability();
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
        $this->requireCharacterGoldCapability();
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
        $this->requireCharacterKickCapability();
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
        $this->requireCharacterTeleportCapability();
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
        $this->requireCharacterTeleportCapability();
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
        $this->requireCharacterResetCapability();
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
        $this->requireCharacterResetCapability();
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
        $this->requireCharacterResetCapability();
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
        $this->requireCharacterResetCapability();
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
        $this->requireCharacterDeleteCapability();
        $this->maybeSwitchServer($request);
        $guid=(int)$request->input('guid',0);
        if($guid<=0) return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_id')],422);
        $summary = $this->repo()->findSummary($guid);
        if(!$summary){ return $this->json(['success'=>false,'message'=>Lang::get('app.common.errors.not_found')],404); }
        if(!empty($summary['online'])){
            return $this->json(['success'=>false,'message'=>Lang::get('app.character.actions.blocked_online')],422);
        }
        $res = $this->repo()->deleteCharacterDetailed($guid);
        $ok = (bool)($res['success'] ?? false);
        $this->logCharacterAction('delete',$ok?'success':'db_fail',['guid'=>$guid,'ip'=>$request->ip(),'error'=>$res['message'] ?? null]);
        if($ok){ Audit::log('character','delete',"guid=$guid"); }
        $msg = $ok
            ? Lang::get('app.character.actions.success')
            : (($res['message'] ?? '') !== '' ? ('删除失败：'.$res['message']) : Lang::get('app.character.actions.failed'));
        return $this->json(['success'=>$ok,'message'=>$msg]);
    }

    public function apiBulk(Request $request): Response
    {
        $action = strtolower(trim((string)$request->input('action','')));
        switch ($action) {
            case 'delete':
                $this->requireCharacterDeleteCapability();
                break;
            case 'ban':
            case 'unban':
                $this->requireCharacterBanCapability();
                break;
            default:
                $this->requireLogin();
                break;
        }
        $this->maybeSwitchServer($request);

        $allowed = ['delete','ban','unban'];
        if(!in_array($action,$allowed,true)){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_params')],422);
        }

        $raw = $request->input('guids',[]);
        $guids = [];
        if(is_array($raw)){
            foreach($raw as $v){ $iv=(int)$v; if($iv>0) $guids[]=$iv; }
        } else {
            $parts = preg_split('/\s*,\s*/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach($parts as $p){ $iv=(int)$p; if($iv>0) $guids[]=$iv; }
        }
        $guids = array_values(array_unique($guids));
        if(!$guids){
            return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_params')],422);
        }
        if(count($guids) > 200){
            $guids = array_slice($guids, 0, 200);
        }

        $hours = (int)$request->input('hours',0);
        $reason = trim((string)$request->input('reason',''));
        if($action === 'ban' && $reason === ''){
            $reason = Lang::get('app.character.actions.default_reason');
        }

        $okCount = 0;
        $fail = [];

        foreach($guids as $guid){
            try {
                if($action === 'ban'){
                    $ok = $this->repo()->ban($guid,$reason,$hours);
                    $this->logCharacterAction('bulk_ban',$ok?'success':'db_fail',['guid'=>$guid,'hours'=>$hours,'reason'=>$reason,'ip'=>$request->ip()]);
                    if($ok){ Audit::log('character','ban',"guid=$guid hours=$hours reason=$reason"); }
                } elseif($action === 'unban'){
                    $cnt = $this->repo()->unban($guid);
                    $ok = true;
                    $this->logCharacterAction('bulk_unban',$cnt>0?'success':'noop',['guid'=>$guid,'updated'=>$cnt,'ip'=>$request->ip()]);
                    if($cnt>0){ Audit::log('character','unban',"guid=$guid updated=$cnt"); }
                } else {
                    $summary = $this->repo()->findSummary($guid);
                    if(!$summary){
                        $ok = false;
                    } elseif(!empty($summary['online'])){
                        $ok = false;
                    } else {
                        $del = $this->repo()->deleteCharacterDetailed($guid);
                        $ok = (bool)($del['success'] ?? false);
                    }
                    $this->logCharacterAction('bulk_delete',$ok?'success':'failed',['guid'=>$guid,'ip'=>$request->ip()]);
                    if($ok){ Audit::log('character','delete',"guid=$guid"); }
                }
            } catch(\Throwable $e){
                $ok = false;
                $this->logCharacterAction('bulk_'.$action,'error',['guid'=>$guid,'error'=>$e->getMessage(),'ip'=>$request->ip()]);
            }

            if($ok){ $okCount++; } else { $fail[] = $guid; }
        }

        $success = $okCount > 0 && count($fail) === 0;
        return $this->json([
            'success' => $success,
            'action' => $action,
            'requested' => count($guids),
            'ok' => $okCount,
            'failed' => count($fail),
            'failed_guids' => array_slice($fail, 0, 50),
        ]);
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
