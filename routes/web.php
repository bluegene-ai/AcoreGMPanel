<?php
/**
 * File: routes/web.php
 * Purpose: Provides functionality for the routes module.
 */

declare(strict_types=1);

use Acme\Panel\Core\Router;
use Acme\Panel\Http\Controllers\AccountController;
use Acme\Panel\Http\Controllers\AuditController;
use Acme\Panel\Http\Controllers\BagQuery\BagQueryController;
use Acme\Panel\Http\Controllers\Character\CharacterController;
use Acme\Panel\Http\Controllers\Creature\CreatureController;
use Acme\Panel\Http\Controllers\HomeController;
use Acme\Panel\Http\Controllers\Item\ItemController;
use Acme\Panel\Http\Controllers\ItemOwnership\ItemOwnershipController;
use Acme\Panel\Http\Controllers\LogsController;
use Acme\Panel\Http\Controllers\Mail\MailController;
use Acme\Panel\Http\Controllers\MassMail\MassMailController;
use Acme\Panel\Http\Controllers\Quest\QuestController;
use Acme\Panel\Http\Controllers\RealmController;
use Acme\Panel\Http\Controllers\Setup\SetupController;
use Acme\Panel\Http\Controllers\SmartAi\SmartAiWizardController;
use Acme\Panel\Http\Controllers\Soap\SoapWizardController;
use Acme\Panel\Http\Middleware\AuthMiddleware;
use Acme\Panel\Http\Middleware\CsrfMiddleware;

return static function (Router $router): void {
    $router->match(['GET'], '/setup', [SetupController::class, 'index']);
    $router->match(['POST'], '/setup/post', [SetupController::class, 'post']);
    $router->match(['GET', 'POST'], '/setup/api/realms', [SetupController::class, 'apiRealms']);

    $router->get('/', [HomeController::class, 'index']);

    $router->match(['GET', 'POST'], '/account/login', [AccountController::class, 'login']);
    $router->get('/account/logout', [AccountController::class, 'logout']);

    $router->get('/realm/list', [RealmController::class, 'list']);

    $router->group([AuthMiddleware::class], static function (Router $router): void {
        $router->get('/account', [AccountController::class, 'index']);
        $router->get('/account/api/list', [AccountController::class, 'apiList']);
        $router->get('/account/api/ip-accounts', [AccountController::class, 'apiAccountsByIp']);
        $router->get('/account/api/ip-location', [AccountController::class, 'apiIpLocation']);
        $router->get('/account/api/characters', [AccountController::class, 'apiCharacters']);
        $router->get('/account/api/characters-status', [AccountController::class, 'apiCharactersStatus']);

        $router->get('/character', [CharacterController::class, 'index']);
        $router->get('/character/view', [CharacterController::class, 'show']);
        $router->get('/character/api/list', [CharacterController::class, 'apiList']);
        $router->get('/character/api/show', [CharacterController::class, 'apiShow']);
        $router->get('/character/api/names', [CharacterController::class, 'apiNames']);

        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/account/api/create', [AccountController::class, 'apiCreate']);
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
            $router->post('/character/api/delete', [CharacterController::class, 'apiDelete']);
        });

        $router->get('/bag', [BagQueryController::class, 'index']);
        $router->get('/bag-query', [BagQueryController::class, 'legacyRedirect']);
        $router->get('/bag/api/characters', [BagQueryController::class, 'apiCharacters']);
        $router->get('/bag/api/items', [BagQueryController::class, 'apiItems']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/bag/api/reduce', [BagQueryController::class, 'apiReduce']);
        });

        $router->get('/item-ownership', [ItemOwnershipController::class, 'index']);
        $router->get('/item-ownership/api/search-items', [ItemOwnershipController::class, 'apiSearchItems']);
        $router->get('/item-ownership/api/ownership', [ItemOwnershipController::class, 'apiOwnership']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/item-ownership/api/bulk', [ItemOwnershipController::class, 'apiBulk']);
        });

        $router->get('/creature', [CreatureController::class, 'index']);
        $router->group([CsrfMiddleware::class], static function (Router $router): void {
            $router->post('/creature/api/create', [CreatureController::class, 'apiCreate']);
            $router->post('/creature/api/delete', [CreatureController::class, 'apiDelete']);
            $router->post('/creature/api/save', [CreatureController::class, 'apiSave']);
            $router->post('/creature/api/exec-sql', [CreatureController::class, 'apiExecSql']);
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
            $router->post('/mass-mail/api/boost', [MassMailController::class, 'apiBoost']);
        });

        $router->get('/soap', [SoapWizardController::class, 'index']);
        $router->get('/smart-ai', [SmartAiWizardController::class, 'index']);
    });
};
