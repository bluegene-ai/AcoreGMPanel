<?php
/**
 * File: app/Http/Controllers/Auctionator/AuctionatorController.php
 * Purpose: Web management surface for the mod-auctionator seller (拍卖机器人).
 *
 * Four surfaces, one page:
 *   - dashboard : live listing counters, market table state, log tail, warnings
 *   - settings  : read/write the Auctionator.* keys of mod_auctionator.conf
 *   - policy    : mod_auctionator_disabled_items / itemclass_config / quality_config / gm_list CRUD
 *   - actions   : the module's own GM commands through the worldserver SOAP channel
 *
 * The module reads its configuration while the worldserver builds the Auctionator
 * singleton, so a saved setting is inert until the worldserver is restarted; the page
 * says so and links to /supervisor. Two exceptions apply the change at once by also sending a
 * runtime command: the master switch (Auctionator.Enabled → ".auctionator start|stop") and the
 * buyout-mode switch (Auctionator.Seller.BidOnly → ".auctionator buyout 0|1"). The item policy
 * tables  the disabled blacklist, the class/subclass whitelist and the per-quality gate  are
 * read by the seller on every run, so those edits never need a restart.
 *
 * Every path, table and command on this page belongs to the realm selected in the header
 * (config/auctionator.php server_overrides), which is what makes the module manageable
 * per realm when several realms share one auth database.
 *
 * Class:
 *   - AuctionatorController
 */

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\Auctionator;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\Auctionator\AuctionatorConfigFile;
use Acme\Panel\Domain\Auctionator\AuctionatorRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\SoapCommandRunner;
use Throwable;

class AuctionatorController extends Controller
{
    private const SELLER_TARGETS = ['hordeseller', 'allianceseller', 'neutralseller', 'hordebidder', 'alliancebidder', 'neutralbidder', 'all'];
    private const QUALITIES = ['poor', 'normal', 'uncommon', 'rare', 'epic', 'legendary'];
    private const HOUSES = [2, 6, 7];

    /**
     * Listing shapes the panel is allowed to write for a GM listing.
     *
     * The module also knows a third value, `legacy` (follow the realm-wide
     * Auctionator.Seller.BidOnly switch). Rows carried over from before the mode column
     * exist are still on it, but the panel never writes it: the GM picks a real mode so a
     * listing's shape is never left to a global switch by accident.
     */
    private const LISTING_MODES = ['buyout', 'bid'];

    private ?AuctionatorRepository $repo = null;

    /** @var array{supported: bool, reason: string}|null 本区是否可管理（每请求只判定一次） */
    private ?array $support = null;

    private function repo(): AuctionatorRepository
    {
        if ($this->repo === null) {
            $this->repo = new AuctionatorRepository();
        }

        return $this->repo;
    }

    private function maybeSwitchServer(Request $request): void
    {
        $this->switchServerAndRebind($request, $this->repo());
    }

    private function requireViewCapability(): void
    {
        $this->requireCapability('auctionator.view');
    }

    private function requireManageCapability(): void
    {
        $this->requireCapability('auctionator.manage');
    }

    private function requireControlCapability(): void
    {
        $this->requireCapability('auctionator.control');
    }

    /**
     * Read an integer that must be present and inside a range.
     *
     * Deliberately not boundedInt(): that clamps, so `boundedInt($request, 'class', -1, 0, 15)`
     * turned a *missing* class into class 0 and made the "-1 means invalid" check below it dead
     * code - a malformed request silently edited the wrong row. The old 0..15 range also cut off
     * class 16 (glyphs), which is a real row of the item class table.
     *
     * @return array{value: int, ok: bool}
     */
    private function requiredInt(Request $request, string $key, int $min, int $max): array
    {
        $raw = $request->input($key, null);
        if ($raw === null || $raw === '' || !is_numeric($raw)) {
            return ['value' => 0, 'ok' => false];
        }

        $value = (int) $raw;

        return ['value' => $value, 'ok' => $value >= $min && $value <= $max];
    }

    public function index(Request $request): Response
    {
        $this->requireViewCapability();
        $this->maybeSwitchServer($request);

        $snapshot = $this->snapshot($this->boundedInt($request, 'disabled_from', 0, 0, 16777215));
        $server = ServerContext::server();

        return $this->pageView('auctionator.index', $this->serverViewData([
            'auctionator' => $snapshot,
        ]), [
            'module' => 'auctionator',
            'capabilities' => [
                'view' => 'auctionator.view',
                'manage' => 'auctionator.manage',
                'control' => 'auctionator.control',
            ],
            'header' => [
                'intro' => __('app.auctionator.intro'),
                'note' => __('app.auctionator.scope_note', [
                    'server' => (string) ($server['name'] ?? ''),
                    'path' => (string) ($snapshot['paths']['conf_file'] ?? ''),
                ]),
            ],
            'meta' => [
                'title' => __('app.auctionator.page_title'),
            ],
        ]);
    }

    public function apiStatus(Request $request): Response
    {
        $this->requireViewCapability();
        $this->maybeSwitchServer($request);

        return $this->json([
            'success' => true,
            'payload' => $this->snapshot($this->boundedInt($request, 'disabled_from', 0, 0, 16777215)),
        ]);
    }

    public function apiConfigSave(Request $request): Response
    {
        $this->requireManageCapability();
        $this->maybeSwitchServer($request);

        if (($refusal = $this->unsupportedServerResponse()) !== null) {
            return $refusal;
        }

        $fields = (array) Config::get('auctionator.fields', []);
        $submitted = $request->input('fields', []);
        if (!is_array($submitted) || $submitted === []) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.empty_payload')], 422);
        }

        $values = [];
        $errors = [];
        foreach ($fields as $key => $spec) {
            if (!array_key_exists($key, $submitted)) {
                continue;
            }

            $parsed = $this->validateField((string) $key, $spec, $submitted[$key]);
            if ($parsed['error'] !== '') {
                $errors[] = $parsed['error'];
                continue;
            }

            $values[(string) $key] = $parsed['value'];
        }

        if ($errors !== []) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.auctionator.errors.invalid_values', ['count' => count($errors)]),
                'payload' => ['errors' => $errors],
            ], 422);
        }

        if ($values === []) {
            return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.nothing_editable')], 422);
        }

        try {
            $file = new AuctionatorConfigFile($this->confPath());
            $result = $file->write($values, $fields);
        } catch (Throwable $exception) {
            Audit::log('auctionator', 'config_save', 'conf', ['error' => $exception->getMessage()]);

            return $this->json([
                'success' => false,
                'message' => Lang::get('app.auctionator.errors.config_write_failed', ['message' => $exception->getMessage()]),
            ], 500);
        }

        if (!$result['ok']) {
            Audit::log('auctionator', 'config_save', 'conf', ['error' => $result['error']]);

            return $this->json([
                'success' => false,
                'message' => Lang::get('app.auctionator.errors.config_' . $result['error']),
            ], 422);
        }

        Audit::log('auctionator', 'config_save', 'conf', [
            'server_id' => ServerContext::currentId(),
            'changed' => $result['changed'],
            'appended' => $result['appended'],
            'backup' => $result['backup'],
        ]);

        $changedCount = count($result['changed']) + count($result['appended']);

        return $this->json([
            'success' => true,
            'message' => $changedCount === 0
                ? Lang::get('app.auctionator.feedback.config_unchanged')
                : Lang::get('app.auctionator.feedback.config_saved', ['count' => $changedCount]),
            'payload' => [
                'changed' => $result['changed'],
                'appended' => $result['appended'],
                'backup' => $result['backup'],
                'restart_required' => $changedCount > 0,
                'snapshot' => $this->snapshot(),
            ],
        ]);
    }

    public function apiItem(Request $request): Response
    {
        $this->requireManageCapability();
        $this->maybeSwitchServer($request);

        if (($refusal = $this->unsupportedServerResponse()) !== null) {
            return $refusal;
        }

        $action = $this->normalizedEnum($request, 'action', [
            'disabled_add', 'disabled_remove',
            'itemclass_save', 'itemclass_delete', 'itemclass_class_save',
            'quality_save',
            'gm_save', 'gm_delete', 'gm_toggle',
        ], '');

        if ($action === '') {
            return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.invalid_action')], 422);
        }

        try {
            switch ($action) {
                case 'disabled_add':
                    $item = $this->boundedInt($request, 'item', 0, 1, 16777215);
                    if (!$this->repo()->itemExists($item)) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.item_not_found', ['item' => $item])], 422);
                    }
                    $this->repo()->addDisabledItem($item);
                    $message = Lang::get('app.auctionator.feedback.disabled_added', ['item' => $item]);
                    break;

                case 'disabled_remove':
                    $item = $this->boundedInt($request, 'item', 0, 1, 16777215);
                    $removed = $this->repo()->deleteDisabledItem($item);
                    if (!$removed) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.not_found')], 404);
                    }
                    $message = Lang::get('app.auctionator.feedback.disabled_removed', ['item' => $item]);
                    break;

                case 'itemclass_save':
                    $class = $this->requiredInt($request, 'class', 0, 16);
                    $subclass = $this->requiredInt($request, 'subclass', 0, 255);
                    if (!$class['ok'] || !$subclass['ok']) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.invalid_class')], 422);
                    }
                    $this->repo()->saveItemclassRow(
                        $class['value'],
                        $subclass['value'],
                        $this->boundedInt($request, 'bonding', 0, 0, 3),
                        $this->boundedInt($request, 'max_count', 1, 0, 1000),
                        $this->boundedInt($request, 'stack_count', 1, 0, 1000)
                    );
                    $message = Lang::get('app.auctionator.feedback.itemclass_saved', ['class' => $class['value'], 'subclass' => $subclass['value']]);
                    break;

                case 'itemclass_delete':
                    $class = $this->requiredInt($request, 'class', 0, 16);
                    $subclass = $this->requiredInt($request, 'subclass', 0, 255);
                    if (!$class['ok'] || !$subclass['ok']) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.invalid_class')], 422);
                    }
                    $this->repo()->deleteItemclassRow($class['value'], $subclass['value']);
                    $message = Lang::get('app.auctionator.feedback.itemclass_deleted', ['class' => $class['value'], 'subclass' => $subclass['value']]);
                    break;

                // Whole-type switch: one quota for every subclass of an item class, so "stop
                // listing armour" is one click instead of eleven saved rows. Quota 0 means never
                // list the type; a quota above 0 also (re)creates the rows a type needs to be in
                // the seller's whitelist at all.
                case 'itemclass_class_save':
                    $class = $this->requiredInt($request, 'class', 0, 16);
                    if (!$class['ok']) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.invalid_class')], 422);
                    }
                    $quota = $this->boundedInt($request, 'max_count', 1, 0, 1000);
                    $applied = $this->repo()->saveItemclassQuotaForClass($class['value'], $quota);
                    $message = Lang::get('app.auctionator.feedback.itemclass_class_saved', [
                        'class' => Lang::get('app.auctionator.types.' . $class['value']),
                        'quota' => $quota,
                        'updated' => $applied['updated'],
                        'inserted' => $applied['inserted'],
                    ]);
                    break;

                // Per-quality gate of the automatic seller. Read by the seller's own candidate
                // query, so it applies on the next run without a worldserver restart.
                case 'quality_save':
                    $quality = $this->requiredInt($request, 'quality', 0, 7);
                    if (!$quality['ok']) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.invalid_quality')], 422);
                    }
                    $listed = $this->normalizedBoolFlag($request, 'enabled');
                    $this->repo()->saveQualityRow($quality['value'], $listed ? 1 : 0);
                    $message = Lang::get('app.auctionator.feedback.quality_saved', [
                        'quality' => Lang::get('app.auctionator.qualities.' . $quality['value']),
                        'state' => Lang::get($listed ? 'app.auctionator.state.listed' : 'app.auctionator.state.not_listed'),
                    ]);
                    break;

                case 'gm_save':
                    $item = $this->boundedInt($request, 'item', 0, 1, 16777215);
                    if (!$this->repo()->itemExists($item)) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.item_not_found', ['item' => $item])], 422);
                    }
                    $pricing = $this->listingPricing($request);
                    if ($pricing['error'] !== '') {
                        return $this->json(['success' => false, 'message' => $pricing['error']], 422);
                    }
                    $hours = $this->boundedInt($request, 'hours', 48, (int) Config::get('auctionator.listing_hours_min', 1), (int) Config::get('auctionator.listing_hours_max', 720));
                    $house = $this->normalizedEnum($request, 'house', ['2', '6', '7'], '7');
                    $this->repo()->saveGmListRow(
                        $item,
                        $pricing['mode'],
                        $pricing['price'],
                        $pricing['bid'],
                        $this->boundedInt($request, 'stack', 1, 1, 1000),
                        $hours,
                        (int) $house,
                        $this->boundedInt($request, 'owner', 0, 0, 4294967295),
                        $this->normalizedBoolFlag($request, 'enabled') ? 1 : 0
                    );
                    $message = Lang::get('app.auctionator.feedback.gm_saved', [
                        'item' => $item,
                        'mode' => Lang::get('app.auctionator.modes.' . $pricing['mode']),
                    ]);
                    break;

                case 'gm_toggle':
                    $item = $this->boundedInt($request, 'item', 0, 1, 16777215);
                    $this->repo()->toggleGmListRow($item, $this->normalizedBoolFlag($request, 'enabled'));
                    $message = Lang::get('app.auctionator.feedback.gm_toggled', ['item' => $item]);
                    break;

                default:
                    $item = $this->boundedInt($request, 'item', 0, 1, 16777215);
                    $removed = $this->repo()->deleteGmListRow($item);
                    if (!$removed) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.not_found')], 404);
                    }
                    $message = Lang::get('app.auctionator.feedback.gm_deleted', ['item' => $item]);
                    break;
            }
        } catch (Throwable $exception) {
            Audit::log('auctionator', 'item_policy', $action, ['error' => $exception->getMessage()]);

            return $this->json([
                'success' => false,
                'message' => Lang::get('app.auctionator.errors.policy_write_failed', ['message' => $exception->getMessage()]),
            ], 500);
        }

        Audit::log('auctionator', 'item_policy', $action, [
            'server_id' => ServerContext::currentId(),
            'input' => $request->all(),
        ]);

        return $this->json([
            'success' => true,
            'message' => $message,
            'payload' => ['policy' => $this->snapshot()['policy']],
        ]);
    }

    public function apiAction(Request $request): Response
    {
        $this->requireControlCapability();
        $this->maybeSwitchServer($request);

        if (($refusal = $this->unsupportedServerResponse()) !== null) {
            return $refusal;
        }

        $action = $this->normalizedEnum($request, 'action', [
            'status', 'market', 'addlist', 'expireall', 'enable', 'disable',
            'multiplier', 'marketimport', 'marketscan', 'marketprune', 'auctionspercycle', 'bidspercycle', 'bidonown', 'add',
        ], '');

        if ($action === '') {
            return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.invalid_action')], 422);
        }

        $built = $this->buildCommand($action, $request);
        if ($built['error'] !== '') {
            return $this->json(['success' => false, 'message' => $built['error']], 422);
        }

        $result = SoapCommandRunner::execute($built['command'], ['server_id' => ServerContext::currentId()]);

        Audit::log('auctionator', 'command', $action, [
            'server_id' => ServerContext::currentId(),
            'command' => $built['command'],
            'success' => (bool) ($result['success'] ?? false),
            'output' => (string) ($result['output'] ?? ''),
        ]);

        return $this->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? '') !== ''
                ? (string) $result['message']
                : ($result['success'] ?? false
                    ? Lang::get('app.auctionator.feedback.command_success')
                    : Lang::get('app.auctionator.errors.command_failed')),
            'payload' => [
                'action' => $action,
                'command' => $built['command'],
                'output' => (string) ($result['output'] ?? ''),
                'execution' => $result['execution'] ?? null,
            ],
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * 按区一键启停拍卖机器人（多区部署下每个区各自一份 conf + 一个 worldserver）。
     *
     * 两步都要做，缺一不可：
     *   1. 写本区 configs/modules/mod_auctionator.conf 的 Auctionator.Enabled —— 跨重启保持；
     *   2. 向本区 worldserver 发 ".auctionator start|stop" —— 立刻生效，不必重启该区。
     *
     * 返回值区分"已落库"和"已生效"：命令没送到（比如该区 worldserver 没在跑）时 conf 仍然写好，
     * 页面据此提示"下次启动生效"，而不是谎报成功。
     */
    public function apiPower(Request $request): Response
    {
        $this->requireControlCapability();
        $this->maybeSwitchServer($request);

        if (($refusal = $this->unsupportedServerResponse()) !== null) {
            return $refusal;
        }

        $enable = $this->normalizedBoolFlag($request, 'enable');
        $serverId = ServerContext::currentId();
        $paths = $this->realmPaths();

        $fields = (array) Config::get('auctionator.fields', []);
        if (!isset($fields['Auctionator.Enabled'])) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.auctionator.errors.power_field_missing'),
            ], 500);
        }

        $confWritten = false;
        $confError = '';
        try {
            $file = new AuctionatorConfigFile($paths['conf_file']);
            $written = $file->write(['Auctionator.Enabled' => $enable ? 1 : 0], $fields);
            if ($written['ok']) {
                $confWritten = true;
            } else {
                $confError = (string) $written['error'];
            }
        } catch (Throwable $exception) {
            $confError = $exception->getMessage();
        }

        $command = $enable ? '.auctionator start' : '.auctionator stop';
        $result = SoapCommandRunner::execute($command, ['server_id' => $serverId, 'strict_marker' => true]);
        $commandOk = (bool) ($result['success'] ?? false);

        Audit::log('auctionator', 'power', $command, [
            'server_id' => $serverId,
            'enable' => $enable,
            'conf_file' => $paths['conf_file'],
            'conf_written' => $confWritten,
            'conf_error' => $confError,
            'command_ok' => $commandOk,
            'output' => (string) ($result['output'] ?? ''),
        ]);

        if ($commandOk && $confWritten) {
            $message = Lang::get($enable
                ? 'app.auctionator.feedback.power_started'
                : 'app.auctionator.feedback.power_stopped', ['server' => $this->serverLabel()]);
        } elseif ($commandOk) {
            $message = Lang::get('app.auctionator.feedback.power_runtime_only', [
                'message' => $confError !== ''
                    ? $confError
                    : Lang::get('app.auctionator.errors.config_unreadable'),
            ]);
        } else {
            $message = Lang::get('app.auctionator.feedback.power_offline', [
                'server' => $this->serverLabel(),
                'message' => $this->firstNonEmptyString([
                    (string) ($result['message'] ?? ''),
                    (string) ($result['output'] ?? ''),
                    Lang::get('app.auctionator.errors.command_failed'),
                ]),
                'state' => $confWritten
                    ? Lang::get('app.auctionator.feedback.power_conf_saved')
                    : Lang::get('app.auctionator.feedback.power_conf_failed'),
            ]);
        }

        return $this->json([
            'success' => $commandOk,
            'message' => $message,
            'payload' => [
                'enable' => $enable,
                'command' => $command,
                'command_ok' => $commandOk,
                'conf_written' => $confWritten,
                'conf_error' => $confError,
                'conf_file' => $paths['conf_file'],
                'restart_required' => !$commandOk,
                'output' => (string) ($result['output'] ?? ''),
                'snapshot' => $this->snapshot(),
            ],
        ], $commandOk ? 200 : 422);
    }

    /**
     * 买断模式快速开关（按区，立即生效）。
     *
     * 和总开关一样是两步，缺一不可：
     *   1. 写本区 configs/modules/mod_auctionator.conf 的 Auctionator.Seller.BidOnly —— 跨重启保持；
     *   2. 向本区 worldserver 发 ".auctionator buyout 0|1" —— 卖家每条挂单都会重读该开关，
     *      所以下一轮就按新值上架，不必重启该区。
     *
     * 注意配置键与界面上的开关是**反向**的：买断模式关闭 = Auctionator.Seller.BidOnly = 1，
     * 也就是条目完全不设一口价、只能靠竞拍成交。
     *
     * 返回值同样区分"已落库"与"已生效"：命令没送到（该区 worldserver 没在跑）时 conf 仍然写好，
     * 页面据此提示"下次启动生效"，而不是谎报成功。
     */
    public function apiBuyout(Request $request): Response
    {
        $this->requireControlCapability();
        $this->maybeSwitchServer($request);

        if (($refusal = $this->unsupportedServerResponse()) !== null) {
            return $refusal;
        }

        $enable = $this->normalizedBoolFlag($request, 'enable');
        $serverId = ServerContext::currentId();
        $paths = $this->realmPaths();

        $fields = (array) Config::get('auctionator.fields', []);
        if (!isset($fields['Auctionator.Seller.BidOnly'])) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.auctionator.errors.buyout_field_missing'),
            ], 500);
        }

        // The switch and the config key are inverses: buyout mode ON is BidOnly = 0.
        $confWritten = false;
        $confError = '';
        try {
            $file = new AuctionatorConfigFile($paths['conf_file']);
            $written = $file->write(['Auctionator.Seller.BidOnly' => $enable ? 0 : 1], $fields);
            if ($written['ok']) {
                $confWritten = true;
            } else {
                $confError = (string) $written['error'];
            }
        } catch (Throwable $exception) {
            $confError = $exception->getMessage();
        }

        $command = '.auctionator buyout ' . ($enable ? '1' : '0');
        $result = SoapCommandRunner::execute($command, ['server_id' => $serverId]);
        $commandOk = (bool) ($result['success'] ?? false);

        Audit::log('auctionator', 'buyout', $command, [
            'server_id' => $serverId,
            'enable' => $enable,
            'conf_file' => $paths['conf_file'],
            'conf_written' => $confWritten,
            'conf_error' => $confError,
            'command_ok' => $commandOk,
            'output' => (string) ($result['output'] ?? ''),
        ]);

        if ($commandOk && $confWritten) {
            $message = Lang::get($enable
                ? 'app.auctionator.feedback.buyout_enabled'
                : 'app.auctionator.feedback.buyout_disabled', ['server' => $this->serverLabel()]);
        } elseif ($commandOk) {
            $message = Lang::get('app.auctionator.feedback.power_runtime_only', [
                'message' => $confError !== ''
                    ? $confError
                    : Lang::get('app.auctionator.errors.config_unreadable'),
            ]);
        } else {
            $message = Lang::get('app.auctionator.feedback.buyout_offline', [
                'server' => $this->serverLabel(),
                'message' => $this->firstNonEmptyString([
                    (string) ($result['message'] ?? ''),
                    (string) ($result['output'] ?? ''),
                    Lang::get('app.auctionator.errors.command_failed'),
                ]),
                'state' => $confWritten
                    ? Lang::get('app.auctionator.feedback.power_conf_saved')
                    : Lang::get('app.auctionator.feedback.power_conf_failed'),
            ]);
        }

        return $this->json([
            'success' => $commandOk,
            'message' => $message,
            'payload' => [
                'enable' => $enable,
                'command' => $command,
                'command_ok' => $commandOk,
                'conf_written' => $confWritten,
                'conf_error' => $confError,
                'conf_file' => $paths['conf_file'],
                'restart_required' => !$commandOk,
                'output' => (string) ($result['output'] ?? ''),
                'snapshot' => $this->snapshot(),
            ],
        ], $commandOk ? 200 : 422);
    }

    /**
     * 当前区服显示名（用于反馈文案），取不到时退回区服索引。
     */
    private function serverLabel(): string
    {
        $server = ServerContext::server();
        $name = trim((string) ($server['name'] ?? ''));

        return $name !== '' ? $name : (string) ServerContext::currentId();
    }

    /**
     * 第一个非空字符串；全为空时返回空串。
     *
     * @param string[] $candidates
     */
    private function firstNonEmptyString(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (trim((string) $candidate) !== '') {
                return (string) $candidate;
            }
        }

        return '';
    }

    // ------------------------------------------------------------------ listing parameters

    /**
     * Normalise the mode + 起拍单价 + 买断单价 triple the two GM listing surfaces take.
     *
     * 一口价 (buyout) is one price the buyer pays and nobody can underbid: the module pins
     * the start bid to the buyout, so a start bid sent alongside it would only be a second,
     * disagreeing source of truth. 竞拍 (bid) needs a start bid and takes an optional
     * buyout that may not sit below it.
     *
     * All three values are UNIT copper: the listing price is the value times the stack,
     * which is what the card's preview spells out.
     *
     * @return array{mode: string, price: int, bid: int, error: string}
     */
    private function listingPricing(Request $request): array
    {
        $max = (int) Config::get('auctionator.price_max_copper', 4294967295);
        $mode = $this->normalizedEnum($request, 'mode', self::LISTING_MODES, '');
        $price = $this->boundedInt($request, 'price', 0, 0, $max);
        $bid = $this->boundedInt($request, 'bid', 0, 0, $max);

        if ($mode === 'buyout') {
            if ($price <= 0) {
                return ['mode' => $mode, 'price' => $price, 'bid' => 0, 'error' => Lang::get('app.auctionator.errors.buyout_price_required')];
            }

            return ['mode' => $mode, 'price' => $price, 'bid' => 0, 'error' => ''];
        }

        if ($mode === 'bid') {
            if ($bid <= 0) {
                return ['mode' => $mode, 'price' => $price, 'bid' => $bid, 'error' => Lang::get('app.auctionator.errors.bid_price_required')];
            }

            if ($price !== 0 && $price < $bid) {
                return [
                    'mode' => $mode,
                    'price' => $price,
                    'bid' => $bid,
                    'error' => Lang::get('app.auctionator.errors.buyout_below_bid', ['bid' => $bid, 'price' => $price]),
                ];
            }

            return ['mode' => $mode, 'price' => $price, 'bid' => $bid, 'error' => ''];
        }

        return ['mode' => '', 'price' => 0, 'bid' => 0, 'error' => Lang::get('app.auctionator.errors.mode_required')];
    }

    /**
     * The owner argument of ".auctionator add": "bot" recycles the sale money (gold sink),
     * a character guid pays that character.
     *
     * "me" is deliberately not offered: the panel reaches the worldserver over SOAP, whose
     * console handler has no session, so the module always answers "me is only available for
     * an in-game GM". Any other unrecognised value used to fall back to "bot" silently, which
     * would pay the wrong character; it is refused instead.
     *
     * @return array{value: string, error: string}
     */
    private function normalizedOwner(Request $request): array
    {
        $owner = trim((string) $request->input('owner', 'bot'));
        if ($owner === '' || $owner === 'bot') {
            return ['value' => 'bot', 'error' => ''];
        }

        if (preg_match('/^\d{1,10}$/', $owner) === 1) {
            return ['value' => $owner, 'error' => ''];
        }

        return ['value' => '', 'error' => Lang::get('app.auctionator.errors.invalid_owner')];
    }

    /**
     * addlist's optional owner override: "row" keeps each row's own owner column, "bot"
     * forces the gold sink, a guid pays that character.
     *
     * @return array{value: string, error: string}
     */
    private function addlistOwnerOverride(Request $request): array
    {
        $owner = trim((string) $request->input('owner_override', 'row'));
        if ($owner === '' || $owner === 'row') {
            return ['value' => 'row', 'error' => ''];
        }

        if ($owner === 'bot') {
            return ['value' => 'bot', 'error' => ''];
        }

        if (preg_match('/^\d{1,10}$/', $owner) === 1) {
            return ['value' => $owner, 'error' => ''];
        }

        return ['value' => '', 'error' => Lang::get('app.auctionator.errors.invalid_owner')];
    }

    // ------------------------------------------------------------------ command builder
    /**
     * @return array{command: string, error: string}
     */
    private function buildCommand(string $action, Request $request): array
    {
        $house = $this->normalizedEnum($request, 'house', ['2', '6', '7'], '7');

        switch ($action) {
            case 'status':
                return ['command' => '.auctionator status', 'error' => ''];

            case 'market':
                return ['command' => '.auctionator market', 'error' => ''];

            case 'addlist':
                $override = $this->addlistOwnerOverride($request);
                if ($override['error'] !== '') {
                    return ['command' => '', 'error' => $override['error']];
                }

                // Omitting the owner argument is what keeps each row's own owner column; the
                // panel used to always send one, which overrode every row with "bot".
                return [
                    'command' => '.auctionator addlist ' . $house . ($override['value'] === 'row' ? '' : ' ' . $override['value']),
                    'error' => '',
                ];

            case 'expireall':
                $all = $this->normalizedBoolFlag($request, 'all') ? ' all' : '';

                return ['command' => '.auctionator expireall ' . $house . $all, 'error' => ''];

            case 'enable':
            case 'disable':
                $target = $this->normalizedEnum($request, 'target', self::SELLER_TARGETS, '');
                if ($target === '') {
                    return ['command' => '', 'error' => Lang::get('app.auctionator.errors.invalid_target')];
                }

                return ['command' => '.auctionator ' . $action . ' ' . $target, 'error' => ''];

            case 'multiplier':
                $type = $this->normalizedEnum($request, 'type', ['seller', 'bidder'], '');
                $quality = $this->normalizedEnum($request, 'quality', self::QUALITIES, '');
                if ($type === '' || $quality === '') {
                    return ['command' => '', 'error' => Lang::get('app.auctionator.errors.invalid_multiplier')];
                }

                $raw = trim((string) $request->input('value', ''));
                if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 1000) {
                    return ['command' => '', 'error' => Lang::get('app.auctionator.errors.invalid_number')];
                }

                return ['command' => '.auctionator multiplier ' . $type . ' ' . $quality . ' ' . (float) $raw, 'error' => ''];

            case 'marketimport':
                return ['command' => '.auctionator marketimport' . ($this->normalizedBoolFlag($request, 'force') ? ' force' : ''), 'error' => ''];

            // Samples this realm's auction house into mod_auctionator_market_price: the
            // aggregation is pure SQL inside the module, so the panel just triggers it.
            case 'marketscan':
                return ['command' => '.auctionator marketscan', 'error' => ''];

            case 'marketprune':
                $days = $this->boundedInt($request, 'days', 30, 1, 3650);

                return ['command' => '.auctionator marketprune ' . $days, 'error' => ''];

            case 'auctionspercycle':
                return ['command' => '.auctionator auctionspercycle ' . $this->boundedInt($request, 'value', 10, 0, (int) Config::get('auctionator.auctions_per_run_max', 1000)), 'error' => ''];

            case 'bidspercycle':
                return ['command' => '.auctionator bidspercycle ' . $this->boundedInt($request, 'value', 2, 0, (int) Config::get('auctionator.max_per_cycle_max', 1000)), 'error' => ''];

            case 'bidonown':
                return ['command' => '.auctionator bidonown ' . ($this->normalizedBoolFlag($request, 'value') ? '1' : '0'), 'error' => ''];

            default:
                return $this->buildAddCommand($request, $house);
        }
    }

    /**
     * ".auctionator add <house> <item[,item...]> mode=<buyout|bid> [bid=<n>] [buyout=<n>]
     *  stack=<n> hours=<n> owner=<bot|guid>"
     *
     * The option form is used on purpose. The positional form takes a bare <price> and lets
     * the realm-wide Auctionator.Seller.BidOnly switch decide whether that price is a buyout
     * or a start bid, which is exactly the ambiguity this card had; the option form states
     * the mode and both prices for this one listing.
     *
     * @return array{command: string, error: string}
     */
    private function buildAddCommand(Request $request, string $house): array
    {
        $itemsRaw = trim((string) $request->input('items', ''));
        $items = array_values(array_filter(array_map('trim', explode(',', $itemsRaw)), static fn (string $value): bool => $value !== ''));

        if ($items === []) {
            return ['command' => '', 'error' => Lang::get('app.auctionator.errors.items_required')];
        }

        $maxItems = (int) Config::get('auctionator.add_max_items', 200);
        if (count($items) > $maxItems) {
            return ['command' => '', 'error' => Lang::get('app.auctionator.errors.too_many_items', ['max' => $maxItems])];
        }

        foreach ($items as $item) {
            if (!preg_match('/^\d{1,8}$/', $item)) {
                return ['command' => '', 'error' => Lang::get('app.auctionator.errors.invalid_item', ['item' => $item])];
            }
        }

        $pricing = $this->listingPricing($request);
        if ($pricing['error'] !== '') {
            return ['command' => '', 'error' => $pricing['error']];
        }

        $owner = $this->normalizedOwner($request);
        if ($owner['error'] !== '') {
            return ['command' => '', 'error' => $owner['error']];
        }

        $stack = $this->boundedInt($request, 'stack', 1, 1, 1000);
        $hours = $this->boundedInt(
            $request,
            'hours',
            48,
            (int) Config::get('auctionator.listing_hours_min', 1),
            (int) Config::get('auctionator.listing_hours_max', 720)
        );

        $options = ['mode=' . $pricing['mode']];
        if ($pricing['mode'] === 'bid') {
            $options[] = 'bid=' . $pricing['bid'];
            if ($pricing['price'] > 0) {
                $options[] = 'buyout=' . $pricing['price'];
            }
        } else {
            $options[] = 'buyout=' . $pricing['price'];
        }

        $options[] = 'stack=' . $stack;
        $options[] = 'hours=' . $hours;
        $options[] = 'owner=' . $owner['value'];

        $command = '.auctionator add ' . $house . ' ' . implode(',', $items) . ' ' . implode(' ', $options);

        return ['command' => $command, 'error' => ''];
    }

    // ------------------------------------------------------------------ snapshot

    /**
     * Everything the page and the status endpoint need.
     *
     * @return array<string, mixed>
     */
    private function snapshot(int $disabledFrom = 0): array
    {
        $fields = (array) Config::get('auctionator.fields', []);
        $paths = $this->realmPaths();
        $file = new AuctionatorConfigFile($paths['conf_file']);

        $read = $file->read();
        $typed = $file->typedValues($fields);

        // 本区管不了（没装模块 / 库连不上 / 被 denied）：只回答"当前是哪个区、它的 conf 在哪、
        // 为什么是只读"，**不去碰该区的库表**。否则页面会在说明上面先刷一屏 Unknown table 的
        // SQL 报错，看起来像面板坏了，而不是"这个区还没装 mod-auctionator"。
        if (!$this->serverSupported()) {
            $reason = $this->serverSupport()['reason'];
            // 连不上库 ≠ 没装模块：前者要按"读取失败/连接问题"说，后者才是"没部署"。
            $unreachable = $reason === 'db_unreachable';

            return [
                'paths' => $paths,
                'conf' => [
                    'ok' => $read['ok'],
                    'error' => $read['error'],
                    'exists' => $file->exists(),
                    'writable' => $file->exists() ? is_writable($paths['conf_file']) : is_writable(dirname($paths['conf_file'])),
                    'values' => $read['values'],
                    'typed' => $typed,
                ],
                'fields' => $fields,
                'groups' => $this->fieldGroups($fields),
                'listings' => ['ok' => false, 'error' => $unreachable ? 'unavailable' : 'not_deployed'],
                'market' => ['ok' => false, 'error' => $unreachable ? 'unreadable' : 'not_deployed'],
                'policy' => [
                    'disabled_from' => $disabledFrom,
                    'disabled' => [],
                    'itemclass' => [],
                    'itemclass_by_class' => [],
                    'quality' => [],
                    'gm_list' => [],
                    'tables' => [],
                    'totals' => [],
                ],
                'log' => [],
                'warnings' => [$this->serverSupportMessage()],
                'notes' => [
                    'listing_hours' => (int) Config::get('auctionator.notes.listing_hours', 12),
                    'supported' => false,
                    'support_reason' => $reason,
                ],
            ];
        }

        $botGuid = (int) ($typed['Auctionator.CharacterGuid'] ?? 0);
        $maxAgeDays = (int) ($typed['Auctionator.MarketData.MaxAgeDays'] ?? 14);

        $repository = $this->repo();
        $listings = $repository->listingStats($botGuid);
        $market = $repository->marketStats($maxAgeDays);
        $policy = $repository->policyRows(
            0,
            (int) Config::get('auctionator.disabled_items_limit', 300),
            (int) Config::get('auctionator.itemclass_limit', 300),
            (int) Config::get('auctionator.gm_list_limit', 300),
            $disabledFrom
        );

        $warnings = $repository->warnings();
        if (!$read['ok']) {
            $warnings[] = Lang::get('app.auctionator.warnings.conf_' . ($read['error'] === 'missing' ? 'missing' : 'unreadable'), ['path' => $paths['conf_file']]);
        }
        if (($typed['Auctionator.Enabled'] ?? 0) === 0) {
            $warnings[] = Lang::get('app.auctionator.warnings.module_disabled');
        }
        if (($typed['Auctionator.Seller.BidOnly'] ?? 0) === 1) {
            $warnings[] = Lang::get('app.auctionator.warnings.bid_only');
        }
        if (($typed['Auctionator.NeutralBidder.Enabled'] ?? 0) === 1
            || ($typed['Auctionator.AllianceBidder.Enabled'] ?? 0) === 1
            || ($typed['Auctionator.HordeBidder.Enabled'] ?? 0) === 1) {
            $warnings[] = Lang::get('app.auctionator.warnings.bidder_enabled');
        }
        if (($typed['Auctionator.AllianceSeller.Enabled'] ?? 0) === 1 || ($typed['Auctionator.HordeSeller.Enabled'] ?? 0) === 1) {
            $warnings[] = Lang::get('app.auctionator.warnings.faction_seller_enabled');
        }
        if ($market['ok'] && $market['rows'] === 0 && trim((string) ($typed['Auctionator.MarketData.ImportFile'] ?? '')) === '') {
            $warnings[] = Lang::get('app.auctionator.warnings.no_market_data');
        }

        return [
            'paths' => $paths,
            'conf' => [
                'ok' => $read['ok'],
                'error' => $read['error'],
                'exists' => $file->exists(),
                'writable' => $file->exists() ? is_writable($paths['conf_file']) : is_writable(dirname($paths['conf_file'])),
                'values' => $read['values'],
                'typed' => $typed,
            ],
            'fields' => $fields,
            'groups' => $this->fieldGroups($fields),
            'listings' => $listings,
            'market' => $market,
            'policy' => $policy,
            'log' => $this->logTail((int) Config::get('auctionator.log_tail_lines', 40)),
            'warnings' => array_values(array_unique($warnings)),
            'notes' => [
                'listing_hours' => (int) Config::get('auctionator.notes.listing_hours', 12),
                'supported' => true,
                'support_reason' => $this->serverSupport()['reason'],
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<int, array{key: string, fields: string[]}>
     */
    private function fieldGroups(array $fields): array
    {
        $groups = [];
        foreach ($fields as $key => $spec) {
            $group = (string) ($spec['group'] ?? 'other');
            if (!isset($groups[$group])) {
                $groups[$group] = ['key' => $group, 'fields' => []];
            }
            $groups[$group]['fields'][] = (string) $key;
        }

        return array_values($groups);
    }

    /**
     * @return array{conf_file: string, log_file: string, server_root: string}
     */
    private function realmPaths(): array
    {
        $serverId = ServerContext::currentId();
        $overrides = (array) Config::get('auctionator.server_overrides', []);
        $override = is_array($overrides[$serverId] ?? null) ? $overrides[$serverId] : [];

        $root = (string) ($override['server_root'] ?? Config::get('auctionator.server_root', ''));
        $conf = (string) ($override['conf_file'] ?? Config::get('auctionator.conf_file', 'configs/modules/mod_auctionator.conf'));
        $log = (string) ($override['log_file'] ?? Config::get('auctionator.log_file', 'logs/auctionator.log'));

        return [
            'server_root' => $root,
            'conf_file' => $this->joinPath($root, $conf),
            'log_file' => $this->joinPath($root, $log),
        ];
    }

    private function confPath(): string
    {
        return $this->realmPaths()['conf_file'];
    }

    private function joinPath(string $root, string $relative): string
    {
        if ($relative === '') {
            return $root;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $relative) === 1) {
            return $relative;
        }

        return rtrim($root, "\\/") . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
    }

    /**
     * 本区能不能管拍卖机器人，以及"不能"的具体原因。
     *
     * 多区面板里 mod-auctionator 是按区部署的，判定顺序（先否决、再显式允许、最后看实据）：
     *   1. `unsupported_server_ids` 里 → 明确不管（denied，给不想在面板里暴露的区留出口）；
     *   2. `supported_server_ids` 里 → 直接算已部署（显式白名单，省掉一次探测）；
     *   3. 否则**探测本区 world 库有没有模块自己的表**——装了就能管，不必再手改白名单；
     *      库连不上（db_unreachable）或表不存在（not_deployed）才落到"只读 + 说明"。
     *
     * @return array{supported: bool, reason: string} reason ∈ explicit|denied|deployed|not_deployed|db_unreachable
     */
    private function serverSupport(): array
    {
        if ($this->support !== null) {
            return $this->support;
        }

        $serverId = ServerContext::currentId();
        $denied = array_map('intval', (array) Config::get('auctionator.unsupported_server_ids', []));
        if (in_array($serverId, $denied, true)) {
            return $this->support = ['supported' => false, 'reason' => 'denied'];
        }

        $allowed = array_map('intval', (array) Config::get('auctionator.supported_server_ids', []));
        if (in_array($serverId, $allowed, true)) {
            return $this->support = ['supported' => true, 'reason' => 'explicit'];
        }

        $probe = $this->repo()->deploymentProbe();

        return $this->support = ['supported' => (bool) $probe['deployed'], 'reason' => (string) $probe['reason']];
    }

    private function serverSupported(): bool
    {
        return $this->serverSupport()['supported'];
    }

    /**
     * 只读原因对应的文案 key（denied / 连不上库 / 没装模块 三种说法不一样）。
     */
    private function serverSupportMessage(): string
    {
        $key = match ($this->serverSupport()['reason']) {
            'denied' => 'server_denied',
            'db_unreachable' => 'server_db_unreachable',
            default => 'server_not_supported',
        };

        return Lang::get('app.auctionator.warnings.' . $key, ['server' => $this->serverLabel()]);
    }

    private function unsupportedServerResponse(): ?Response
    {
        if ($this->serverSupported()) {
            return null;
        }

        return $this->json([
            'success' => false,
            'message' => $this->serverSupportMessage(),
        ], 422);
    }

    /**
     * @return array<int, array{line: string, tone: string}>
     */
    private function logTail(int $lines): array
    {
        $path = $this->realmPaths()['log_file'];
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $contents = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($contents)) {
            return [];
        }

        $tail = array_slice($contents, -max(1, min(400, $lines)));
        $entries = [];
        foreach ($tail as $line) {
            $tone = 'muted';
            if (stripos($line, 'error') !== false) {
                $tone = 'error';
            } elseif (stripos($line, 'warn') !== false || stripos($line, 'skipped') !== false) {
                $tone = 'warn';
            } elseif (stripos($line, 'items added') !== false || stripos($line, 'enabled') !== false) {
                $tone = 'ok';
            }

            $entries[] = ['line' => (string) $line, 'tone' => $tone];
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $spec
     * @return array{value: mixed, error: string}
     */
    private function validateField(string $key, array $spec, mixed $raw): array
    {
        $type = (string) ($spec['type'] ?? 'string');
        $label = Lang::get('app.auctionator.fields.' . (string) ($spec['label'] ?? 'value'));

        if ($type === 'bool') {
            $text = strtolower(trim((string) (is_bool($raw) ? ($raw ? '1' : '0') : $raw)));

            return ['value' => in_array($text, ['1', 'true', 'on', 'yes'], true) ? 1 : 0, 'error' => ''];
        }

        if ($type === 'int' || $type === 'float') {
            $text = trim((string) $raw);
            if ($text === '' || !is_numeric($text)) {
                return ['value' => 0, 'error' => $label . ': ' . Lang::get('app.auctionator.errors.not_a_number')];
            }

            $number = $type === 'int' ? (int) round((float) $text) : (float) $text;
            $min = $spec['min'] ?? null;
            $max = $spec['max'] ?? null;
            if (is_numeric($min) && $number < (float) $min) {
                return ['value' => $number, 'error' => $label . ': ' . Lang::get('app.auctionator.errors.below_min', ['min' => (string) $min])];
            }
            if (is_numeric($max) && $number > (float) $max) {
                return ['value' => $number, 'error' => $label . ': ' . Lang::get('app.auctionator.errors.above_max', ['max' => (string) $max])];
            }

            return ['value' => $number, 'error' => ''];
        }

        $text = trim((string) $raw);
        if (strlen($text) > 255) {
            return ['value' => $text, 'error' => $label . ': ' . Lang::get('app.auctionator.errors.too_long', ['max' => 255])];
        }

        if (str_contains($key, 'ImportSource') && $text !== '' && preg_match('/^[A-Za-z0-9_.-]{1,32}$/', $text) !== 1) {
            return ['value' => $text, 'error' => $label . ': ' . Lang::get('app.auctionator.errors.invalid_source')];
        }

        return ['value' => $text, 'error' => ''];
    }
}
