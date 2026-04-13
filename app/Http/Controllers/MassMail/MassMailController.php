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
use Acme\Panel\Domain\CharacterBoost\BoostTemplateRepository;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostService;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostGuardException;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostNotFoundException;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostSoapException;
use Acme\Panel\Domain\Character\CharacterRepository;
use Acme\Panel\Support\{Auth,ServerContext,ServerList};
use Acme\Panel\Support\Audit;

class MassMailController extends Controller
{
    private MassMailService $svc;

    private function refreshService(): void
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

        $this->svc = new MassMailService($soap, ServerContext::currentId());
    }

    private function requireComposeCapability(): void
    {
        $this->requireCapability('mass_mail.compose');
    }

    private function requireAnnounceCapability(): void
    {
        $this->requireCapability('mass_mail.announce');
    }

    private function requireSendCapability(): void
    {
        $this->requireCapability('mass_mail.send');
    }

    private function requireLogsCapability(): void
    {
        $this->requireCapability('mass_mail.logs');
    }

    private function requireBoostCapability(): void
    {
        $this->requireCapability('mass_mail.boost');
    }

    public function __construct()
    {
        $this->refreshService();
    }

    public function index(Request $request): Response
    {
    $this->requireComposeCapability();

    $this->switchServerAndRefresh($request, function (): void { $this->refreshService(); });
                $logs = $this->svc->recentLogs(30);
                $serverCfg = ServerContext::server();
                $realmId = (int)($serverCfg['realm_id'] ?? 1);
                $boostTemplates = (new BoostTemplateRepository(ServerContext::currentId()))->listForRealm($realmId);

        return $this->pageView('mass_mail.index', $this->serverViewData([
            'logs'=>$logs,
            'realm_id' => $realmId,
            'boost_templates' => $boostTemplates,
        ]), [
            'capabilities' => [
                'compose' => 'mass_mail.compose',
                'announce' => 'mass_mail.announce',
                'send' => 'mass_mail.send',
                'logs' => 'mass_mail.logs',
                'boost' => 'mass_mail.boost',
            ],
        ]);
    }

    public function apiAnnounce(Request $request): Response
    { $this->requireAnnounceCapability(); $this->switchServerAndRefresh($request, function (): void { $this->refreshService(); }); $msg=(string)$request->input('message',''); $res=$this->svc->sendAnnounce($msg); return $this->json($res,$res['success']?200:422); }

    public function apiSend(Request $request): Response
    {
    $this->requireSendCapability();
        $this->switchServerAndRefresh($request, function (): void { $this->refreshService(); });
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
    { $this->requireLogsCapability(); $this->switchServerAndRefresh($request, function (): void { $this->refreshService(); }); $limit=(int)$request->input('limit',30); $rows=$this->svc->recentLogs($limit); return $this->json(['success'=>true,'logs'=>$rows]); }

    public function apiBoost(Request $request): Response
    {
    $this->requireBoostCapability();
                $this->switchServerAndRefresh($request, function (): void { $this->refreshService(); });
                $characterName=trim((string)$request->input('character_name', (string)$request->input('character','')));
                $templateIdRaw = $request->input('template_id', null);
                $templateId = $templateIdRaw === null || $templateIdRaw === '' ? null : (int)$templateIdRaw;
                $targetLevelRaw = $request->input('target_level', null);
                $targetLevel = $targetLevelRaw === null || $targetLevelRaw === '' ? null : (int)$targetLevelRaw;

                if($characterName===''){
                    return $this->json(['success'=>false,'message'=>Lang::get('app.common.validation.missing_params')],422);
                }

                $serverCfg = ServerContext::server();
                $realmId = (int)($serverCfg['realm_id'] ?? 1);

                $serverCfg = ServerContext::server();
                $realmId = (int)($serverCfg['realm_id'] ?? 1);

                try{
                    $charRepo = new CharacterRepository(ServerContext::currentId());
                    $summary = $charRepo->findSummaryByName($characterName);
                    if(!$summary){
                        throw new CharacterBoostNotFoundException('未找到指定角色。');
                    }

                    $svc = new CharacterBoostService(ServerContext::currentId());
                    $payload = $svc->boostByGuid(
                        $realmId,
                        (int)($summary['guid'] ?? 0),
                        $templateId,
                        $targetLevel,
                        [
                            'name' => (string)($_SESSION['panel_user'] ?? 'system'),
                            'ip' => (string)$request->ip(),
                            'source' => 'mass_mail',
                        ]
                    );
                }catch(CharacterBoostNotFoundException $e){
                    Audit::log('character','boost_failed','mass_mail',[
                        'realm_id' => $realmId,
                        'server_id' => ServerContext::currentId(),
                        'character_name' => $characterName,
                        'template_id' => $templateId,
                        'target_level' => $targetLevel,
                        'error' => $e->getMessage(),
                    ]);
                    return $this->json(['success'=>false,'message'=>$e->getMessage()],404);
                }catch(CharacterBoostGuardException $e){
                    Audit::log('character','boost_failed','mass_mail',[
                        'realm_id' => $realmId,
                        'server_id' => ServerContext::currentId(),
                        'character_name' => $characterName,
                        'template_id' => $templateId,
                        'target_level' => $targetLevel,
                        'error' => $e->getMessage(),
                    ]);
                    return $this->json(['success'=>false,'message'=>$e->getMessage()],422);
                }catch(CharacterBoostSoapException $e){
                    Audit::log('character','boost_failed','mass_mail',[
                        'realm_id' => $realmId,
                        'server_id' => ServerContext::currentId(),
                        'character_name' => $characterName,
                        'template_id' => $templateId,
                        'target_level' => $targetLevel,
                        'error' => $e->getMessage(),
                    ]);
                    return $this->json(['success'=>false,'message'=>$e->getMessage()],500);
                }catch(\Throwable $e){
                    Audit::log('character','boost_failed','mass_mail',[
                        'realm_id' => $realmId,
                        'server_id' => ServerContext::currentId(),
                        'character_name' => $characterName,
                        'template_id' => $templateId,
                        'target_level' => $targetLevel,
                        'error' => $e->getMessage(),
                    ]);
                    return $this->json(['success'=>false,'message'=>Lang::get('app.common.api.errors.request_failed_retry')],500);
                }

                $ch = $payload['character'] ?? [];
                $commands = $payload['commands'] ?? [];
                Audit::log('character','boost', 'mass_mail', [
                    'realm_id' => $realmId,
                    'character' => $ch,
                    'template_id' => $templateId,
                    'target_level' => $targetLevel,
                    'commands' => array_map(static function($c){
                        return [
                            'command' => $c['command'] ?? null,
                            'success' => $c['response']['success'] ?? null,
                        ];
                    }, is_array($commands) ? $commands : []),
                ]);

                return $this->json(['success'=>true,'message'=>Lang::get('app.character.actions.boost_success'),'payload'=>$payload],200);
    }
}

?>
