<?php
/**
 * File: routes/web.php
 * Purpose: Provides functionality for the routes module.
 */

declare(strict_types=1);

use Acme\Panel\Core\Router;
use Acme\Panel\Http\Controllers\AccountController;
use Acme\Panel\Http\Controllers\Aegis\AegisController;
use Acme\Panel\Http\Controllers\AuditController;
use Acme\Panel\Http\Controllers\Auctionator\AuctionatorController;
use Acme\Panel\Http\Controllers\Boss\BossController;
use Acme\Panel\Http\Controllers\Character\CharacterController;
use Acme\Panel\Http\Controllers\Creature\CreatureController;
use Acme\Panel\Http\Controllers\HomeController;
use Acme\Panel\Http\Controllers\Item\ItemController;
use Acme\Panel\Http\Controllers\ItemInventory\ItemInventoryController;
use Acme\Panel\Http\Controllers\LogsController;
use Acme\Panel\Http\Controllers\Mail\MailController;
use Acme\Panel\Http\Controllers\MassMail\MassMailController;
use Acme\Panel\Http\Controllers\Quest\QuestController;
use Acme\Panel\Http\Controllers\Raf\RafController;
use Acme\Panel\Http\Controllers\RealmController;
use Acme\Panel\Http\Controllers\Setup\SetupController;
use Acme\Panel\Http\Controllers\SmartAi\SmartAiWizardController;
use Acme\Panel\Http\Controllers\Soap\SoapWizardController;
use Acme\Panel\Http\Controllers\Supervisor\SupervisorController;
use Acme\Panel\Http\Controllers\Trivia\TriviaController;
use Acme\Panel\Http\Controllers\CharacterBoost\CharacterBoostAdminController;
use Acme\Panel\Http\Controllers\CharacterBoost\PublicCharacterBoostController;
use Acme\Panel\Http\Controllers\CharacterBoost\CharacterBoostRedeemCodeAdminController;
use Acme\Panel\Http\Controllers\CharacterBoost\CharacterBoostTemplateAdminController;
use Acme\Panel\Http\Middleware\AuthMiddleware;
use Acme\Panel\Http\Middleware\CsrfMiddleware;

return static function (Router $router): void {
    $router->match(['GET'], '/setup', [SetupController::class, 'index']);
    $router->match(['POST'], '/setup/post', [SetupController::class, 'post']);
    $router->match(['GET', 'POST'], '/setup/api/realms', [SetupController::class, 'apiRealms']);

    $router->get('/', [HomeController::class, 'index']);

    // Public character boost redeem (no login)
    $router->get('/public/character-boost', [PublicCharacterBoostController::class, 'index']);
    $router->get('/public/character-boost/options', [PublicCharacterBoostController::class, 'options']);
    $router->group([CsrfMiddleware::class], static function (Router $router): void {
        $router->post('/public/character-boost/redeem', [PublicCharacterBoostController::class, 'redeem']);
    });

    $router->match(['GET', 'POST'], '/account/login', [AccountController::class, 'login']);
    $router->get('/account/logout', [AccountController::class, 'logout']);

    $router->get('/realm/list', [RealmController::class, 'list']);

    $router->group([AuthMiddleware::class], static function (Router $router): void {
        $router->get('/account', [AccountController::class, 'index']);
        $router->get('/aegis', [AegisController::class, 'index']);
        $router->get('/aegis/api/overview', [AegisController::class, 'apiOverview']);
        $router->get('/aegis/api/offenses', [AegisController::class, 'apiOffenses']);
        $router->get('/aegis/api/events', [AegisController::class, 'apiEvents']);
        $router->get('/aegis/api/player', [AegisController::class, 'apiPlayer']);
        $router->get('/aegis/api/log', [AegisController::class, 'apiLog']);
        $router->get('/boss', [BossController::class, 'index']);
        $router->get('/account/view', [AccountController::class, 'show']);
        $router->get('/account/api/list', [AccountController::class, 'apiList']);
        $router->get('/account/api/ip-accounts', [AccountController::class, 'apiAccountsByIp']);
        $router->get('/account/api/ip-location', [AccountController::class, 'apiIpLocation']);
        $router->get('/account/api/characters', [AccountController::class, 'apiCharacters']);
        $router->get('/account/api/characters-status', [AccountController::class, 'apiCharactersStatus']);
        $router->get('/raf', [RafController::class, 'index']);
        // 招募管理区块的无刷新刷新与统计卡下钻（只读）
        $router->get('/raf/api/bindings', [RafController::class, 'apiBindings']);
        $router->get('/raf/api/reward-logs', [RafController::class, 'apiRewardLogs']);
        $router->get('/raf/api/card', [RafController::class, 'apiCard']);

        $router->get('/character', [CharacterController::class, 'index']);
        $router->get('/character/view', [CharacterController::class, 'show']);
        $router->get('/character/api/list', [CharacterController::class, 'apiList']);
        $router->get('/character/api/show', [CharacterController::class, 'apiShow']);
        $router->get('/character/api/names', [CharacterController::class, 'apiNames']);

        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/account/api/create', [AccountController::class, 'apiCreate']);
            $router->post('/aegis/api/action', [AegisController::class, 'apiAction']);
            $router->post('/boss/api/action', [BossController::class, 'apiAction']);
            $router->post('/boss/api/config', [BossController::class, 'apiConfigSave']);
            $router->post('/boss/api/ext-config', [BossController::class, 'apiExtConfigSave']);
            $router->post('/account/api/set-gm', [AccountController::class, 'apiSetGm']);
            $router->post('/soap/api/execute', [SoapWizardController::class, 'apiExecute']);
            $router->post('/smart-ai/api/preview', [SmartAiWizardController::class, 'apiPreview']);
            $router->post('/account/api/ban', [AccountController::class, 'apiBan']);
            $router->post('/account/api/unban', [AccountController::class, 'apiUnban']);
            $router->post('/account/api/delete', [AccountController::class, 'apiDelete']);
            $router->post('/account/api/bulk', [AccountController::class, 'apiBulk']);
            $router->post('/account/api/update-email', [AccountController::class, 'apiUpdateEmail']);
            $router->post('/account/api/update-username', [AccountController::class, 'apiUpdateUsername']);
            $router->post('/account/api/change-password', [AccountController::class, 'apiChangePassword']);
            $router->post('/account/api/kick', [AccountController::class, 'apiKick']);

            $router->post('/character/api/ban', [CharacterController::class, 'apiBan']);
            $router->post('/character/api/unban', [CharacterController::class, 'apiUnban']);
            $router->post('/character/api/bulk', [CharacterController::class, 'apiBulk']);
            $router->post('/character/api/set-level', [CharacterController::class, 'apiSetLevel']);
            $router->post('/character/api/set-gold', [CharacterController::class, 'apiSetGold']);
            $router->post('/character/api/kick', [CharacterController::class, 'apiKick']);
            $router->post('/character/api/teleport', [CharacterController::class, 'apiTeleport']);
            $router->post('/character/api/unstuck', [CharacterController::class, 'apiUnstuck']);
            $router->post('/character/api/reset-talents', [CharacterController::class, 'apiResetTalents']);
            $router->post('/character/api/reset-spells', [CharacterController::class, 'apiResetSpells']);
            $router->post('/character/api/reset-cooldowns', [CharacterController::class, 'apiResetCooldowns']);
            $router->post('/character/api/rename-flag', [CharacterController::class, 'apiRenameFlag']);
            $router->post('/character/api/boost', [CharacterController::class, 'apiBoost']);
            $router->post('/character/api/delete', [CharacterController::class, 'apiDelete']);

            $router->post('/character-boost/api/redeem-codes/generate', [CharacterBoostRedeemCodeAdminController::class, 'apiGenerate']);
            $router->post('/character-boost/api/redeem-codes/stats', [CharacterBoostRedeemCodeAdminController::class, 'apiStats']);
            $router->post('/character-boost/api/redeem-codes/list', [CharacterBoostRedeemCodeAdminController::class, 'apiList']);
            $router->post('/character-boost/api/redeem-codes/delete-unused', [CharacterBoostRedeemCodeAdminController::class, 'apiDeleteUnused']);
            $router->post('/character-boost/api/redeem-codes/purge-unused', [CharacterBoostRedeemCodeAdminController::class, 'apiPurgeUnused']);

            $router->post('/character-boost/api/templates/save', [CharacterBoostTemplateAdminController::class, 'apiSave']);
            $router->post('/character-boost/api/templates/delete', [CharacterBoostTemplateAdminController::class, 'apiDelete']);

            // 直升管理：执行直升 / 预览 / 历史（原群发页的直升入口已迁移到这里）
            $router->post('/character-boost/api/apply', [CharacterBoostAdminController::class, 'apiApply']);
            $router->post('/character-boost/api/history', [CharacterBoostAdminController::class, 'apiHistory']);

            $router->post('/raf/api/bind', [RafController::class, 'apiBind']);
            $router->post('/raf/api/unbind', [RafController::class, 'apiUnbind']);
            $router->post('/raf/api/comment', [RafController::class, 'apiComment']);
        });

        $router->get('/character-boost', [CharacterBoostAdminController::class, 'index']);
        $router->get('/character-boost/templates', [CharacterBoostTemplateAdminController::class, 'index']);
        $router->get('/character-boost/templates/edit', [CharacterBoostTemplateAdminController::class, 'edit']);
        $router->get('/character-boost/redeem-codes', [CharacterBoostRedeemCodeAdminController::class, 'index']);

        // Unified item / inventory module (merged 背包查询 + 物品归属).
        // Character axis: ?mode=character   Item axis: ?mode=item
        $router->get('/item-inventory', [ItemInventoryController::class, 'index']);
        $router->get('/item-inventory/api/characters', [ItemInventoryController::class, 'apiCharacters']);
        $router->get('/item-inventory/api/character-items', [ItemInventoryController::class, 'apiCharacterItems']);
        $router->get('/item-inventory/api/search-items', [ItemInventoryController::class, 'apiSearchItems']);
        $router->get('/item-inventory/api/ownership', [ItemInventoryController::class, 'apiOwnership']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/item-inventory/api/reduce', [ItemInventoryController::class, 'apiReduce']);
            $router->post('/item-inventory/api/bulk', [ItemInventoryController::class, 'apiBulk']);
        });

        // Legacy URLs kept alive so existing bookmarks and external links keep working.
        $router->get('/bag-query', [ItemInventoryController::class, 'legacyRedirect']);
        $router->get('/bag', [ItemInventoryController::class, 'legacyBagRedirect']);
        $router->get('/item-ownership', [ItemInventoryController::class, 'legacyOwnershipRedirect']);

        $router->get('/creature', [CreatureController::class, 'index']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/creature/api/create', [CreatureController::class, 'apiCreate']);
            $router->post('/creature/api/delete', [CreatureController::class, 'apiDelete']);
            $router->post('/creature/api/save', [CreatureController::class, 'apiSave']);
            $router->post('/creature/api/exec-sql', [CreatureController::class, 'apiExecSql']);
            $router->post('/creature/api/logs', [CreatureController::class, 'apiLogs']);
            $router->post('/creature/api/fetch-row', [CreatureController::class, 'apiFetchRow']);
            $router->post('/creature/api/add-model', [CreatureController::class, 'apiAddModel']);
            $router->post('/creature/api/edit-model', [CreatureController::class, 'apiEditModel']);
            $router->post('/creature/api/delete-model', [CreatureController::class, 'apiDeleteModel']);
        });

        $router->get('/item', [ItemController::class, 'index']);
        $router->get('/item/api/subclasses', [ItemController::class, 'apiSubclasses']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/item/api/create', [ItemController::class, 'apiCreate']);
            $router->post('/item/api/delete', [ItemController::class, 'apiDelete']);
            $router->post('/item/api/save', [ItemController::class, 'apiSave']);
            $router->post('/item/api/exec-sql', [ItemController::class, 'apiExecSql']);
            $router->post('/item/api/logs', [ItemController::class, 'apiLogs']);
            $router->post('/item/api/check', [ItemController::class, 'apiCheck']);
            $router->post('/item/api/fetch', [ItemController::class, 'apiFetch']);
            $router->post('/logs/api/list', [LogsController::class, 'apiList']);
            $router->post('/audit/api/list', [AuditController::class, 'apiList']);

            $router->post('/mail/api/list', [MailController::class, 'apiList']);
            $router->post('/mail/api/view', [MailController::class, 'apiView']);
            $router->post('/mail/api/mark-read', [MailController::class, 'apiMarkRead']);
            $router->post('/mail/api/mark-read-bulk', [MailController::class, 'apiMarkReadBulk']);
            $router->post('/mail/api/delete', [MailController::class, 'apiDelete']);
            $router->post('/mail/api/delete-bulk', [MailController::class, 'apiDeleteBulk']);
            $router->post('/mail/api/stats', [MailController::class, 'apiStats']);
            $router->post('/mail/api/logs', [MailController::class, 'apiLogs']);
        });

        $router->get('/logs', [LogsController::class, 'index']);

        $router->get('/quest', [QuestController::class, 'index']);
        $router->get('/quest/api/editor/load', [QuestController::class, 'apiEditorLoad']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/quest/api/create', [QuestController::class, 'apiCreate']);
            $router->post('/quest/api/delete', [QuestController::class, 'apiDelete']);
            $router->post('/quest/api/save', [QuestController::class, 'apiSave']);
            $router->post('/quest/api/exec-sql', [QuestController::class, 'apiExecSql']);
            $router->post('/quest/api/fetch', [QuestController::class, 'apiFetch']);
            $router->post('/quest/api/editor/preview', [QuestController::class, 'apiEditorPreview']);
            $router->post('/quest/api/editor/save', [QuestController::class, 'apiEditorSave']);
            $router->post('/quest/api/logs', [QuestController::class, 'apiLogs']);
        });

        $router->get('/mail', [MailController::class, 'index']);

        $router->get('/mass-mail', [MassMailController::class, 'index']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/mass-mail/api/announce', [MassMailController::class, 'apiAnnounce']);
            $router->post('/mass-mail/api/send', [MassMailController::class, 'apiSend']);
            $router->post('/mass-mail/api/logs', [MassMailController::class, 'apiLogs']);
            // 只读辅助：物品名解析与收件人预览
            $router->post('/mass-mail/api/items', [MassMailController::class, 'apiItems']);
            $router->post('/mass-mail/api/targets', [MassMailController::class, 'apiPreviewTargets']);
        });

        $router->get('/soap', [SoapWizardController::class, 'index']);
        $router->get('/smart-ai', [SmartAiWizardController::class, 'index']);

        // 守护管理：查看/控制 acore_supervisor.exe（worldserver + authserver）
        $router->get('/supervisor', [SupervisorController::class, 'index']);
        $router->get('/supervisor/api/status', [SupervisorController::class, 'apiStatus']);
        $router->get('/supervisor/api/log', [SupervisorController::class, 'apiLog']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/supervisor/api/command', [SupervisorController::class, 'apiCommand']);
        });

        // 聊天答题（TriviaReward.lua）：实时状态走 SOAP，配置/题库/预设/排行走 ac_eluna 数据表
        $router->get('/trivia', [TriviaController::class, 'index']);
        $router->get('/trivia/api/status', [TriviaController::class, 'apiStatus']);
        $router->get('/trivia/api/questions', [TriviaController::class, 'apiQuestions']);
        $router->get('/trivia/api/presets', [TriviaController::class, 'apiPresets']);
        $router->get('/trivia/api/winners', [TriviaController::class, 'apiWinners']);
        // 题库模板导入/导出（导出与模板下载是 GET，导入走 POST + CSRF）
        $router->get('/trivia/api/questions/export', [TriviaController::class, 'apiQuestionExport']);
        $router->get('/trivia/api/questions/template', [TriviaController::class, 'apiQuestionTemplate']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/trivia/api/action', [TriviaController::class, 'apiAction']);
            $router->post('/trivia/api/settings', [TriviaController::class, 'apiSettings']);
            $router->post('/trivia/api/questions/import', [TriviaController::class, 'apiQuestionImport']);
            $router->post('/trivia/api/question/save', [TriviaController::class, 'apiQuestionSave']);
            $router->post('/trivia/api/question/delete', [TriviaController::class, 'apiQuestionDelete']);
            $router->post('/trivia/api/question/toggle', [TriviaController::class, 'apiQuestionToggle']);
            $router->post('/trivia/api/preset/save', [TriviaController::class, 'apiPresetSave']);
            $router->post('/trivia/api/preset/delete', [TriviaController::class, 'apiPresetDelete']);
            $router->post('/trivia/api/winners/clear', [TriviaController::class, 'apiWinnersClear']);
        });

        // 拍卖机器人（mod-auctionator）：挂单/市场概览、conf 读写、物品策略表、.auctionator GM 命令（SOAP）
        $router->get('/auctionator', [AuctionatorController::class, 'index']);
        $router->get('/auctionator/api/status', [AuctionatorController::class, 'apiStatus']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/auctionator/api/config', [AuctionatorController::class, 'apiConfigSave']);
            $router->post('/auctionator/api/item', [AuctionatorController::class, 'apiItem']);
            $router->post('/auctionator/api/action', [AuctionatorController::class, 'apiAction']);
        });
    });
};
