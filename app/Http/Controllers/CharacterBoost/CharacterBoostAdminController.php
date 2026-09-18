<?php
/**
 * File: app/Http/Controllers/CharacterBoost/CharacterBoostAdminController.php
 * Purpose: 直升管理统一入口：概览、执行直升、直升历史。
 *
 * 直升原本分散在三处：角色详情页、群发管理页、兑换码页。
 * 现在统一到 /character-boost 概览页执行，模板与兑换码仍由各自的子页面管理。
 */

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\CharacterBoost;

use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Character\CharacterRepository;
use Acme\Panel\Domain\CharacterBoost\BoostLogRepository;
use Acme\Panel\Domain\CharacterBoost\BoostTemplateRepository;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostGuardException;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostNotFoundException;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostService;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostSoapException;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\Auth;
use Acme\Panel\Support\ServerContext;

class CharacterBoostAdminController extends Controller
{
    private function requireApplyCapability(): void
    {
        $this->requireCapability('boost.apply');
    }

    private function requireTemplatesCapability(): void
    {
        $this->requireCapability('boost.templates');
    }

    private function requireCodesCapability(): void
    {
        $this->requireCapability('boost.codes');
    }

    /**
     * 概览页：能执行直升就要求 boost.apply；只有模板/兑换码权限时仍可查看概览。
     */
    public function index(Request $request): Response
    {
        $this->requireLogin();

        $canApply = Auth::can('boost.apply');
        $canTemplates = Auth::can('boost.templates');
        $canCodes = Auth::can('boost.codes');

        if (!$canApply && !$canTemplates && !$canCodes) {
            $this->requireCapability('boost.apply');
        }

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);
        $serverId = ServerContext::currentId();

        $repo = new BoostTemplateRepository($serverId);
        $templates = $canApply || $canTemplates
            ? $repo->listForRealmWithRewards($realmId)
            : [];

        $codeStats = ['total' => 0, 'unused' => 0, 'used' => 0];
        if ($canCodes) {
            try {
                $stats = $repo->redeemCodeStatsForRealm($realmId);
                $codeStats = [
                    'total' => (int) ($stats['total'] ?? 0),
                    'unused' => (int) ($stats['unused'] ?? 0),
                    'used' => (int) ($stats['used'] ?? 0),
                ];
            } catch (\Throwable $exception) {
                // 兑换码表尚未创建时概览页仍要可用
                $codeStats = ['total' => 0, 'unused' => 0, 'used' => 0];
            }
        }

        $history = $canApply ? $this->recentBoostHistory(20) : [];

        return $this->pageView('character_boost.index', [
            'realm_id' => $realmId,
            'templates' => $templates,
            'code_stats' => $codeStats,
            'history' => $history,
        ], [
            'module' => 'character_boost',
            'capabilities' => [
                'apply' => 'boost.apply',
                'templates' => 'boost.templates',
                'codes' => 'boost.codes',
            ],
        ]);
    }

    /**
     * 执行直升：支持角色名或 GUID，模板优先、其次目标等级。
     */
    public function apiApply(Request $request): Response
    {
        $this->requireApplyCapability();

        $characterName = trim((string) $request->input('character_name', ''));
        $guid = (int) $request->input('guid', 0);
        $templateIdRaw = $request->input('template_id', null);
        $templateId = ($templateIdRaw === null || $templateIdRaw === '') ? null : (int) $templateIdRaw;
        $targetLevelRaw = $request->input('target_level', null);
        $targetLevel = ($targetLevelRaw === null || $targetLevelRaw === '') ? null : (int) $targetLevelRaw;
        $dryRun = $request->bool('dry_run', false);

        if ($characterName === '' && $guid <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.character_boost.admin.errors.character_required'),
            ], 422);
        }

        if ($templateId === null && $targetLevel === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.character_boost.admin.errors.target_required'),
            ], 422);
        }

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);
        $serverId = ServerContext::currentId();

        try {
            $charRepo = new CharacterRepository($serverId);
            $summary = $guid > 0
                ? $charRepo->findSummary($guid)
                : $charRepo->findSummaryByName($characterName);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.common.errors.query_failed', ['message' => $exception->getMessage()]),
            ], 500);
        }

        if (!$summary) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.character_boost.admin.errors.character_missing'),
            ], 404);
        }

        $resolvedGuid = (int) ($summary['guid'] ?? 0);
        $resolvedName = (string) ($summary['name'] ?? $characterName);

        // 预览模式：只解析角色与将要发放的内容，不落库、不发命令
        $svc = new CharacterBoostService($serverId);
        if ($dryRun) {
            try {
                $preview = $svc->previewByGuid($realmId, $resolvedGuid, $templateId, $targetLevel);
            } catch (\Throwable $exception) {
                return $this->json([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return $this->json([
                'success' => true,
                'dry_run' => true,
                'character' => [
                    'guid' => $resolvedGuid,
                    'name' => $resolvedName,
                    'level' => (int) ($summary['level'] ?? 0),
                    'class' => (int) ($summary['class'] ?? 0),
                    'account' => (int) ($summary['account'] ?? 0),
                    'online' => (int) ($summary['online'] ?? 0),
                ],
                'preview' => $preview,
            ]);
        }

        try {
            $payload = $svc->boostByGuid($realmId, $resolvedGuid, $templateId, $targetLevel, [
                'name' => (string) ($_SESSION['panel_user'] ?? 'system'),
                'ip' => (string) $request->ip(),
                'source' => 'boost_admin',
            ]);
        } catch (CharacterBoostNotFoundException $e) {
            $this->auditFailure($realmId, $resolvedGuid, $templateId, $targetLevel, $e->getMessage());
            return $this->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (CharacterBoostGuardException $e) {
            $this->auditFailure($realmId, $resolvedGuid, $templateId, $targetLevel, $e->getMessage());
            return $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (CharacterBoostSoapException $e) {
            $this->auditFailure($realmId, $resolvedGuid, $templateId, $targetLevel, $e->getMessage());
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            $this->auditFailure($realmId, $resolvedGuid, $templateId, $targetLevel, $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.common.api.errors.request_failed_retry'),
            ], 500);
        }

        $character = is_array($payload['character'] ?? null) ? $payload['character'] : [];
        $commands = is_array($payload['commands'] ?? null) ? $payload['commands'] : [];

        Audit::log('character', 'boost', 'boost_admin', [
            'realm_id' => $realmId,
            'server_id' => $serverId,
            'character' => $character,
            'template_id' => $templateId,
            'target_level' => $targetLevel,
            'commands' => array_map(static function ($command): array {
                return [
                    'command' => $command['command'] ?? null,
                    'success' => $command['response']['success'] ?? null,
                ];
            }, $commands),
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.character_boost.admin.applied', [
                'name' => $resolvedName,
                'level' => (string) ($character['level'] ?? $targetLevel ?? ''),
            ]),
            'character' => [
                'guid' => $resolvedGuid,
                'name' => $resolvedName,
            ],
            'payload' => $payload,
        ]);
    }

    /**
     * 直升历史（只读）。
     */
    public function apiHistory(Request $request): Response
    {
        $this->requireApplyCapability();

        $limit = (int) $request->input('limit', 20);
        $limit = max(1, min($limit, 100));

        return $this->json([
            'success' => true,
            'logs' => $this->recentBoostHistory($limit),
        ]);
    }

    /**
     * 最近直升记录（只读，来自 BoostLogRepository）。
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentBoostHistory(int $limit): array
    {
        try {
            return (new BoostLogRepository(ServerContext::currentId()))->recent($limit);
        } catch (\Throwable $exception) {
            // 日志不可用时概览页不应整体失败
            return [];
        }
    }

    private function auditFailure(int $realmId, int $guid, ?int $templateId, ?int $targetLevel, string $error): void
    {
        Audit::log('character', 'boost_failed', 'boost_admin', [
            'realm_id' => $realmId,
            'server_id' => ServerContext::currentId(),
            'guid' => $guid,
            'template_id' => $templateId,
            'target_level' => $targetLevel,
            'error' => $error,
        ]);
    }
}
