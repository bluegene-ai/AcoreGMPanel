<?php
/**
 * File: app/Http/Controllers/CharacterBoost/CharacterBoostTemplateAdminController.php
 * Purpose: Admin CRUD for character boost templates.
 */

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\CharacterBoost;

use Acme\Panel\Core\{Controller, Lang, Request, Response};
use Acme\Panel\Domain\CharacterBoost\BoostTemplateRepository;
use Acme\Panel\Support\{Auth, Audit, ServerContext};

class CharacterBoostTemplateAdminController extends Controller
{
    private function requireTemplateCapability(): void
    {
        $this->requireCapability('boost.templates');
    }

    public function index(Request $request): Response
    {
        $this->requireTemplateCapability();

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $repo = new BoostTemplateRepository(ServerContext::currentId());
        $templates = $repo->listForRealmWithRewards($realmId);

        return $this->pageView('character_boost.templates', [
            'realm_id' => $realmId,
            'templates' => $templates,
        ], [
            'module' => 'character_boost_templates',
            'capabilities' => [
                'templates' => 'boost.templates',
                'codes' => 'boost.codes',
            ],
        ]);
    }

    public function edit(Request $request): Response
    {
        $this->requireTemplateCapability();

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $id = (int) $request->input('id', 0);
        $repo = new BoostTemplateRepository(ServerContext::currentId());

        $template = null;
        if ($id > 0) {
            $template = $repo->findForRealm($realmId, $id);
            if (!$template) {
                return $this->pageView('character_boost.template_edit', [
                    'realm_id' => $realmId,
                    'template' => null,
                    'error' => Lang::get('app.common.errors.not_found'),
                ], [
                    'module' => 'character_boost_template_edit',
                    'capabilities' => [
                        'templates' => 'boost.templates',
                        'codes' => 'boost.codes',
                    ],
                ]);
            }
        }

        return $this->pageView('character_boost.template_edit', [
            'realm_id' => $realmId,
            'template' => $template,
            'error' => null,
        ], [
            'module' => 'character_boost_template_edit',
            'capabilities' => [
                'templates' => 'boost.templates',
                'codes' => 'boost.codes',
            ],
        ]);
    }

    public function apiSave(Request $request): Response
    {
        $this->requireTemplateCapability();

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $id = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        $targetLevel = (int) $request->input('target_level', 0);
        $moneyGold = (int) $request->input('money_gold', 0);
        $requireMatch = $request->bool('require_account_level_match', false);

        $itemsText = (string) $request->input('items', '');
        $tiersText = (string) $request->input('class_rewards', '');

        if ($name === '' || $targetLevel < 1 || $targetLevel > 255 || $moneyGold < 0) {
            return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.templates.errors.invalid_payload')], 422);
        }

        $repo = new BoostTemplateRepository(ServerContext::currentId());

        $items = $this->parseItems($itemsText);
        $tiers = $this->parseTiers($tiersText);

        try {
            $pdo = $repo->authPdo();
            $pdo->beginTransaction();

            if ($id > 0) {
                $ok = $repo->updateTemplate($realmId, $id, $name, $targetLevel, $moneyGold, $requireMatch);
                if (!$ok) {
                    $pdo->rollBack();
                    return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.templates.errors.save_failed')], 422);
                }
                $templateId = $id;
            } else {
                $templateId = $repo->createTemplate($realmId, $name, $targetLevel, $moneyGold, $requireMatch);
                if ($templateId <= 0) {
                    $pdo->rollBack();
                    return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.templates.errors.save_failed')], 422);
                }
            }

            $repo->replaceTemplateItems($templateId, $items);
            $repo->replaceTemplateClassRewards($templateId, $tiers);

            $pdo->commit();

            Audit::log('character_boost', 'save_template', 'template_id=' . (string) $templateId, [
                'realm_id' => $realmId,
                'name' => $name,
                'target_level' => $targetLevel,
                'money_gold' => $moneyGold,
                'require_account_level_match' => $requireMatch ? 1 : 0,
                'items_count' => count($items),
                'tiers' => $tiers,
            ]);

            return $this->json([
                'success' => true,
                'message' => Lang::get('app.character_boost.templates.saved'),
                'payload' => [
                    'id' => $templateId,
                ],
            ]);
        } catch (\Throwable $e) {
            if ($repo->authPdo()->inTransaction()) {
                $repo->authPdo()->rollBack();
            }
            return $this->json(['success' => false, 'message' => Lang::get('app.common.api.errors.request_failed_retry')], 500);
        }
    }

    public function apiDelete(Request $request): Response
    {
        $this->requireTemplateCapability();

        $serverCfg = ServerContext::server();
        $realmId = (int) ($serverCfg['realm_id'] ?? 1);

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.validation.missing_id')], 422);
        }

        $repo = new BoostTemplateRepository(ServerContext::currentId());
        try {
            $ok = $repo->deleteTemplate($realmId, $id);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'message' => Lang::get('app.common.api.errors.request_failed_retry')], 500);
        }

        if (!$ok) {
            return $this->json(['success' => false, 'message' => Lang::get('app.character_boost.templates.errors.delete_failed')], 422);
        }

        Audit::log('character_boost', 'delete_template', 'template_id=' . (string) $id, [
            'realm_id' => $realmId,
        ]);

        return $this->json(['success' => true, 'message' => Lang::get('app.character_boost.templates.deleted')]);
    }

    private function parseItems(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // Each line: entry[:qty]
        $items = [];
        $lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = preg_split('/\s+|[:,x\*]/', $line);
            $parts = array_values(array_filter(array_map('trim', $parts), static fn($v) => $v !== ''));

            $entry = isset($parts[0]) ? (int) $parts[0] : 0;
            $qty = isset($parts[1]) ? (int) $parts[1] : 1;

            if ($entry <= 0 || $qty <= 0) {
                continue;
            }

            $items[] = ['item_entry' => $entry, 'quantity' => $qty];
        }

        return $items;
    }

    private function parseTiers(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $tiers = [];
        $parts = preg_split('/\r\n|\n|\r|,|;|\s+/', $raw) ?: [];
        foreach ($parts as $p) {
            $p = strtolower(trim($p));
            if ($p === '') {
                continue;
            }
            $tiers[] = $p;
        }

        return $tiers;
    }
}
