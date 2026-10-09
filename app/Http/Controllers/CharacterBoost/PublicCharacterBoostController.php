<?php
/**
 * File: app/Http/Controllers/CharacterBoost/PublicCharacterBoostController.php
 * Purpose: Public character boost redeem endpoints (options + redeem).
 */

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\CharacterBoost;

use Acme\Panel\Core\{Controller, Lang, Request, Response};
use Acme\Panel\Domain\Character\CharacterRepository;
use Acme\Panel\Domain\CharacterBoost\BoostTemplateRepository;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostGuardException;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostNotFoundException;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostService;
use Acme\Panel\Domain\CharacterBoost\CharacterBoostSoapException;
use Acme\Panel\Support\Csrf;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\ServerContext;
use PDO;

class PublicCharacterBoostController extends Controller
{
    /** 兑换码状态列的一次性迁移探测标记。 */
    private static bool $redeemStatusEnsured = false;
    private function resolveServerIdByRealmId(int $realmId): ?int
    {
        if ($realmId <= 0) {
            return null;
        }

        foreach (ServerContext::list() as $serverId => $cfg) {
            $rid = (int) ($cfg['realm_id'] ?? $serverId);
            if ($rid === $realmId) {
                return (int) $serverId;
            }
        }

        // Fallback: allow passing server index as realmId.
        if (isset(ServerContext::list()[$realmId])) {
            return $realmId;
        }

        return null;
    }

    public function index(Request $request): Response
    {
        return $this->pageView('character_boost.redeem', [], [
            'module' => 'character_boost_redeem',
        ]);
    }

    public function options(Request $request): Response
    {
        $realms = [];
        $templates = [];

        foreach (ServerContext::list() as $serverId => $cfg) {
            $realmId = (int) ($cfg['realm_id'] ?? $serverId);
            $label = (string) ($cfg['name'] ?? Lang::get('app.server.default_option', ['id' => $realmId]));

            $realms[] = [
                'realm_id' => $realmId,
                'label' => $label,
            ];

            try {
                $repo = new BoostTemplateRepository((int) $serverId);
                $rows = $repo->listForRealm($realmId);
            } catch (\Throwable $e) {
                $rows = [];
            }

            foreach ($rows as $row) {
                $templates[] = [
                    'realm_id' => $realmId,
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                    'target_level' => (int) ($row['target_level'] ?? 0),
                ];
            }
        }

        return $this->json([
            'success' => true,
            'csrf_token' => Csrf::token(),
            'realms' => $realms,
            'templates' => $templates,
        ]);
    }

    public function redeem(Request $request): Response
    {
        $realmId = (int) $request->input('realm_id', 0);
        $characterName = trim((string) $request->input('character_name', ''));
        $code = strtoupper(trim((string) $request->input('code', '')));
        $ip = (string) $request->ip();

        $safeCode = $code !== '' ? ('****' . substr($code, -4)) : '';

        if ($realmId <= 0 || $characterName === '' || $code === '') {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.common.validation.missing_params'),
            ], 422);
        }

        if (!preg_match('/^[A-Z0-9]{16}$/', $code)) {
            $this->auditRedeemFailure($realmId, $characterName, 0, $safeCode, $ip, 'invalid_code_format');
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.character_boost.redeem.errors.invalid_code_format'),
            ], 422);
        }

        $serverId = $this->resolveServerIdByRealmId($realmId);
        if ($serverId === null) {
            $this->auditRedeemFailure($realmId, $characterName, 0, $safeCode, $ip, 'invalid_realm');
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.character_boost.redeem.errors.invalid_realm'),
            ], 422);
        }

        $tplRepo = new BoostTemplateRepository($serverId);
        $pdo = $tplRepo->authPdo();
        $this->ensureRedeemStatusColumn($pdo);

        $templateId = 0;
        $summary = [];
        $lockedId = 0;

        // 阶段一：校验并占位（reserved）。占位在发奖之前提交，因此发奖成功、失败或进程中断，
        // 该码都不会回到可兑换状态 —— 并发与重试下至多发一次奖。
        try {
            $pdo->beginTransaction();

            $locked = $tplRepo->lockRedeemCode($code);
            if (!$locked) {
                $pdo->rollBack();
                return $this->json([
                    'success' => false,
                    'message' => Lang::get('app.character_boost.redeem.errors.code_not_found'),
                ], 404);
            }

            $lockedId = (int) $locked['id'];

            $statusSt = $pdo->prepare('SELECT status FROM character_boost_redeem_codes WHERE id=:id FOR UPDATE');
            $statusSt->execute([':id' => $lockedId]);
            $status = strtolower((string) ($statusSt->fetchColumn() ?: 'unused'));

            if (!empty($locked['used_at']) || in_array($status, ['reserved', 'used', 'failed'], true)) {
                $pdo->rollBack();
                return $this->json([
                    'success' => false,
                    'message' => Lang::get('app.character_boost.redeem.errors.code_used'),
                ], 409);
            }

            $templateId = (int) ($locked['template_id'] ?? 0);
            if ($templateId <= 0) {
                throw new CharacterBoostGuardException(Lang::get('app.character_boost.redeem.errors.invalid_template'));
            }

            $template = $tplRepo->findForRealm($realmId, $templateId);
            if (!$template) {
                throw new CharacterBoostGuardException(Lang::get('app.character_boost.redeem.errors.invalid_template'));
            }

            $charRepo = new CharacterRepository($serverId);
            $summary = $charRepo->findSummaryByName($characterName);
            if (!$summary) {
                throw new CharacterBoostNotFoundException(Lang::get('app.character_boost.redeem.errors.character_not_found'));
            }

            $claim = $pdo->prepare(
                'UPDATE character_boost_redeem_codes
                    SET status=:reserved, used_at=NOW(), used_realm_id=:rid, used_character_name=:name, used_ip=:ip, updated_at=NOW()
                  WHERE id=:id AND used_at IS NULL AND status=:unused'
            );
            $claim->execute([
                ':reserved' => 'reserved',
                ':unused' => 'unused',
                ':rid' => $realmId,
                ':name' => (string) ($summary['name'] ?? $characterName),
                ':ip' => $ip,
                ':id' => $lockedId,
            ]);
            if ($claim->rowCount() !== 1) {
                $pdo->rollBack();
                return $this->json([
                    'success' => false,
                    'message' => Lang::get('app.character_boost.redeem.errors.code_used'),
                ], 409);
            }

            $pdo->commit();
        } catch (CharacterBoostNotFoundException $e) {
            $this->rollbackQuietly($pdo);
            $this->auditRedeemFailure($realmId, $characterName, $templateId, $safeCode, $ip, $e->getMessage());
            return $this->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (CharacterBoostGuardException $e) {
            $this->rollbackQuietly($pdo);
            $this->auditRedeemFailure($realmId, $characterName, $templateId, $safeCode, $ip, $e->getMessage());
            return $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            $this->rollbackQuietly($pdo);
            $this->auditRedeemFailure($realmId, $characterName, $templateId, $safeCode, $ip, $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.common.api.errors.request_failed_retry'),
            ], 500);
        }

        // 阶段二：发奖。守卫类异常发生在任何 SOAP 命令之前，可以把占位退回 unused；
        // 其余异常一律置 failed（保持已消费，避免重试重复发奖）。
        try {
            $svc = new CharacterBoostService($serverId);
            $payload = $svc->boostBySummary(
                $realmId,
                $summary,
                $templateId,
                null,
                [
                    'name' => 'public_redeem',
                    'ip' => $ip,
                    'code' => $code,
                ]
            );
        } catch (CharacterBoostNotFoundException | CharacterBoostGuardException $e) {
            $this->releaseRedeemReservation($pdo, $lockedId);
            $this->auditRedeemFailure($realmId, $characterName, $templateId, $safeCode, $ip, $e->getMessage());
            $status = $e instanceof CharacterBoostNotFoundException ? 404 : 422;
            return $this->json(['success' => false, 'message' => $e->getMessage()], $status);
        } catch (\Throwable $e) {
            $this->finishRedeemReservation($pdo, $lockedId, 'failed');
            $this->auditRedeemFailure($realmId, $characterName, $templateId, $safeCode, $ip, $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => $e instanceof CharacterBoostSoapException
                    ? $e->getMessage()
                    : Lang::get('app.common.api.errors.request_failed_retry'),
            ], 500);
        }

        $this->finishRedeemReservation($pdo, $lockedId, 'used');

        $payloadCharacter = $payload['character'] ?? [];
        $commands = $payload['commands'] ?? [];
        Audit::log('character_boost', 'public_redeem', 'realm_id=' . (string) $realmId, [
            'realm_id' => $realmId,
            'template_id' => $templateId,
            'character' => $payloadCharacter,
            'code_tail' => $safeCode,
            'ip' => $ip,
            'commands' => array_map(static function ($c) {
                return [
                    'command' => $c['command'] ?? null,
                    'success' => $c['response']['success'] ?? null,
                ];
            }, is_array($commands) ? $commands : []),
        ]);

        return $this->json([
            'success' => true,
            'message' => Lang::get('app.character_boost.redeem.success'),
            'payload' => $payload,
        ]);
    }

    /**
     * 兑换码状态列：老库只有 used_at，补一列区分 unused / reserved / used / failed。
     */
    private function ensureRedeemStatusColumn(PDO $pdo): void
    {
        if (self::$redeemStatusEnsured) {
            return;
        }

        $st = $pdo->query("SHOW COLUMNS FROM character_boost_redeem_codes LIKE 'status'");
        if (!$st->fetch()) {
            $pdo->exec("ALTER TABLE character_boost_redeem_codes ADD status VARCHAR(16) NOT NULL DEFAULT 'unused' AFTER code, ADD KEY idx_redeem_status (status)");
        }

        self::$redeemStatusEnsured = true;
    }

    private function finishRedeemReservation(PDO $pdo, int $id, string $status): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            $st = $pdo->prepare('UPDATE character_boost_redeem_codes SET status=:st, updated_at=NOW() WHERE id=:id AND status=:reserved');
            $st->execute([':st' => $status, ':reserved' => 'reserved', ':id' => $id]);
        } catch (\Throwable $e) {
        }
    }

    private function releaseRedeemReservation(PDO $pdo, int $id): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            $st = $pdo->prepare('UPDATE character_boost_redeem_codes SET status=:unused, used_at=NULL, used_realm_id=NULL, used_character_name=NULL, used_ip=NULL, updated_at=NOW() WHERE id=:id AND status=:reserved');
            $st->execute([':unused' => 'unused', ':reserved' => 'reserved', ':id' => $id]);
        } catch (\Throwable $e) {
        }
    }

    private function rollbackQuietly(PDO $pdo): void
    {
        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (\Throwable $e) {
        }
    }

    private function auditRedeemFailure(int $realmId, string $characterName, int $templateId, string $safeCode, string $ip, string $error): void
    {
        Audit::log('character_boost', 'public_redeem_failed', 'realm_id=' . (string) $realmId, [
            'realm_id' => $realmId,
            'character_name' => $characterName,
            'template_id' => $templateId > 0 ? $templateId : null,
            'code_tail' => $safeCode,
            'ip' => $ip,
            'error' => $error,
        ]);
    }
}
