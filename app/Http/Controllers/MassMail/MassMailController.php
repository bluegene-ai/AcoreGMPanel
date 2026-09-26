<?php
/**
 * File: app/Http/Controllers/MassMail/MassMailController.php
 * Purpose: Defines class MassMailController for the app/Http/Controllers/MassMail module.
 */

namespace Acme\Panel\Http\Controllers\MassMail;

use Acme\Panel\Core\{Config,Controller,Lang,Request,Response};
use Acme\Panel\Domain\MassMail\MassMailService;
use Acme\Panel\Support\{ServerContext};

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

        return $this->pageView('mass_mail.index', $this->serverViewData([
            'logs'=>$logs,
            'realm_id' => $realmId,
        ]), [
            'capabilities' => [
                'compose' => 'mass_mail.compose',
                'announce' => 'mass_mail.announce',
                'send' => 'mass_mail.send',
                'logs' => 'mass_mail.logs',
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

    /** 物品名解析：只读，供发送界面的物品编辑器即时显示名称。 */
    public function apiItems(Request $request): Response
    {
        $this->requireSendCapability();
        $this->switchServerAndRefresh($request, function (): void { $this->refreshService(); });

        $raw = (string) $request->input('ids', '');
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if (count($ids) > 200) {
            $ids = array_slice($ids, 0, 200, true);
        }

        try {
            $names = $this->svc->resolveItemNames(array_values($ids));
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.errors.query_failed', ['message' => $e->getMessage()])], 500);
        }

        return $this->json(['success' => true, 'names' => $names]);
    }

    /** 收件人预览：返回数量、上限与少量样本，避免把整份名单回传浏览器。 */
    public function apiPreviewTargets(Request $request): Response
    {
        $this->requireSendCapability();
        $this->switchServerAndRefresh($request, function (): void { $this->refreshService(); });

        $type = (string) $request->input('target_type', 'online');
        if (!in_array($type, ['online', 'custom'], true)) {
            $type = 'online';
        }
        $custom = (string) $request->input('custom_char_list', '');

        try {
            $preview = $this->svc->previewTargets($type, $custom);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.errors.query_failed', ['message' => $e->getMessage()])], 500);
        }

        return $this->json(['success' => true] + $preview);
    }
}

?>
