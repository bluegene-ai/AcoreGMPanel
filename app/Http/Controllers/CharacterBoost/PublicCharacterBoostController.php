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

class PublicCharacterBoostController extends Controller
{
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
            Audit::log('character_boost', 'public_redeem_failed', 'realm_id=' . (string) $realmId, [
                'realm_id' => $realmId,
                'character_name' => $characterName,
                'code_tail' => $safeCode,
                'ip' => $ip,
                'error' => 'invalid_code_format',
            ]);
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.character_boost.redeem.errors.invalid_code_format'),
            ], 422);
        }

        $serverId = $this->resolveServerIdByRealmId($realmId);
        if ($serverId === null) {
            Audit::log('character_boost', 'public_redeem_failed', 'realm_id=' . (string) $realmId, [
                'realm_id' => $realmId,
                'character_name' => $characterName,
                'code_tail' => $safeCode,
                'ip' => $ip,
                'error' => 'invalid_realm',
            ]);
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.character_boost.redeem.errors.invalid_realm'),
            ], 422);
        }

        $tplRepo = new BoostTemplateRepository($serverId);
        $pdo = $tplRepo->authPdo();

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

            if (!empty($locked['used_at'])) {
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

            $ok = $tplRepo->markRedeemCodeUsed((int) $locked['id'], $realmId, (string) ($summary['name'] ?? $characterName), $ip);
            if (!$ok) {
                throw new CharacterBoostGuardException(Lang::get('app.character_boost.redeem.errors.code_used'));
            }

            $pdo->commit();

            $payloadCharacter = $payload['character'] ?? [];
            $commands = $payload['commands'] ?? [];
            $safeCode = $code !== '' ? ('****' . substr($code, -4)) : '';
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
        } catch (CharacterBoostNotFoundException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Audit::log('character_boost', 'public_redeem_failed', 'realm_id=' . (string) $realmId, [
                'realm_id' => $realmId,
                'character_name' => $characterName,
                'template_id' => isset($templateId) ? (int) $templateId : null,
                'code_tail' => $safeCode,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (CharacterBoostGuardException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Audit::log('character_boost', 'public_redeem_failed', 'realm_id=' . (string) $realmId, [
                'realm_id' => $realmId,
                'character_name' => $characterName,
                'template_id' => isset($templateId) ? (int) $templateId : null,
                'code_tail' => $safeCode,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (CharacterBoostSoapException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Audit::log('character_boost', 'public_redeem_failed', 'realm_id=' . (string) $realmId, [
                'realm_id' => $realmId,
                'character_name' => $characterName,
                'template_id' => isset($templateId) ? (int) $templateId : null,
                'code_tail' => $safeCode,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Audit::log('character_boost', 'public_redeem_failed', 'realm_id=' . (string) $realmId, [
                'realm_id' => $realmId,
                'character_name' => $characterName,
                'code_tail' => $safeCode,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.common.api.errors.request_failed_retry'),
            ], 500);
        }
    }
}
