<?php
/**
 * File: app/Http/Controllers/Auctionator/AuctionatorController.php
 * Purpose: Web management surface for the mod-auctionator seller (拍卖机器人).
 *
 * Four surfaces, one page:
 *   - dashboard : live listing counters, market table state, log tail, warnings
 *   - settings  : read/write the Auctionator.* keys of mod_auctionator.conf
 *   - policy    : mod_auctionator_disabled_items / itemclass_config / gm_list CRUD
 *   - actions   : the module's own GM commands through the worldserver SOAP channel
 *
 * The module reads its configuration while the worldserver builds the Auctionator
 * singleton, so a saved setting is inert until the worldserver is restarted; the page
 * says so and links to /supervisor.
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

    private ?AuctionatorRepository $repo = null;

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
            'itemclass_save', 'itemclass_delete',
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
                    $class = $this->boundedInt($request, 'class', -1, 0, 15);
                    $subclass = $this->boundedInt($request, 'subclass', -1, 0, 255);
                    if ($class < 0 || $subclass < 0) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.invalid_class')], 422);
                    }
                    $this->repo()->saveItemclassRow(
                        $class,
                        $subclass,
                        $this->boundedInt($request, 'bonding', 0, 0, 3),
                        $this->boundedInt($request, 'max_count', 1, 0, 1000),
                        $this->boundedInt($request, 'stack_count', 1, 0, 1000)
                    );
                    $message = Lang::get('app.auctionator.feedback.itemclass_saved', ['class' => $class, 'subclass' => $subclass]);
                    break;

                case 'itemclass_delete':
                    $class = $this->boundedInt($request, 'class', -1, 0, 15);
                    $subclass = $this->boundedInt($request, 'subclass', -1, 0, 255);
                    if ($class < 0 || $subclass < 0) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.invalid_class')], 422);
                    }
                    $this->repo()->deleteItemclassRow($class, $subclass);
                    $message = Lang::get('app.auctionator.feedback.itemclass_deleted', ['class' => $class, 'subclass' => $subclass]);
                    break;

                case 'gm_save':
                    $item = $this->boundedInt($request, 'item', 0, 1, 16777215);
                    if (!$this->repo()->itemExists($item)) {
                        return $this->json(['success' => false, 'message' => Lang::get('app.auctionator.errors.item_not_found', ['item' => $item])], 422);
                    }
                    $price = $this->boundedInt($request, 'price', 0, 0, (int) Config::get('auctionator.price_max_copper', 4294967295));
                    $hours = $this->boundedInt($request, 'hours', 48, (int) Config::get('auctionator.listing_hours_min', 1), (int) Config::get('auctionator.listing_hours_max', 720));
                    $house = $this->normalizedEnum($request, 'house', ['2', '6', '7'], '7');
                    $this->repo()->saveGmListRow(
                        $item,
                        $price,
                        $this->boundedInt($request, 'stack', 1, 1, 1000),
                        $hours,
                        (int) $house,
                        $this->boundedInt($request, 'owner', 0, 0, 4294967295),
                        $this->normalizedBoolFlag($request, 'enabled') ? 1 : 0
                    );
                    $message = Lang::get('app.auctionator.feedback.gm_saved', ['item' => $item]);
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
            'multiplier', 'marketimport', 'marketprune', 'auctionspercycle', 'bidspercycle', 'bidonown', 'add',
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

    // ------------------------------------------------------------------ command builder

    /**
     * @return array{command: string, error: string}
     */
    private function buildCommand(string $action, Request $request): array
    {
        $house = $this->normalizedEnum($request, 'house', ['2', '6', '7'], '7');
        $owner = $this->normalizedOwner($request);

        switch ($action) {
            case 'status':
                return ['command' => '.auctionator status', 'error' => ''];

            case 'market':
                return ['command' => '.auctionator market', 'error' => ''];

            case 'addlist':
                return ['command' => trim('.auctionator addlist ' . $house . ' ' . $owner), 'error' => ''];

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
                return $this->buildAddCommand($request, $house, $owner);
        }
    }

    /**
     * ".auctionator add <house> <item[,item...]> <price> [stack] [hours] [owner]"
     *
     * @return array{command: string, error: string}
     */
    private function buildAddCommand(Request $request, string $house, string $owner): array
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

        $price = $this->boundedInt($request, 'price', 0, 0, (int) Config::get('auctionator.price_max_copper', 4294967295));
        if ($price <= 0) {
            return ['command' => '', 'error' => Lang::get('app.auctionator.errors.price_required')];
        }

        $stack = $this->boundedInt($request, 'stack', 1, 1, 1000);
        $hours = $this->boundedInt(
            $request,
            'hours',
            48,
            (int) Config::get('auctionator.listing_hours_min', 1),
            (int) Config::get('auctionator.listing_hours_max', 720)
        );

        $command = '.auctionator add ' . $house . ' ' . implode(',', $items) . ' ' . $price . ' ' . $stack . ' ' . $hours . ' ' . $owner;

        return ['command' => $command, 'error' => ''];
    }

    private function normalizedOwner(Request $request): string
    {
        $owner = trim((string) $request->input('owner', 'bot'));
        if ($owner === '' || $owner === 'bot') {
            return 'bot';
        }

        if ($owner === 'me') {
            return 'me';
        }

        return preg_match('/^\d{1,10}$/', $owner) ? $owner : 'bot';
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
        if (!$this->serverSupported()) {
            $warnings[] = Lang::get('app.auctionator.warnings.server_not_supported', ['server' => (string) (ServerContext::server()['name'] ?? ServerContext::currentId())]);
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
                'supported' => $this->serverSupported(),
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

    private function serverSupported(): bool
    {
        $supported = (array) Config::get('auctionator.supported_server_ids', []);

        return $supported === [] || in_array(ServerContext::currentId(), array_map('intval', $supported), true);
    }

    private function unsupportedServerResponse(): ?Response
    {
        if ($this->serverSupported()) {
            return null;
        }

        return $this->json([
            'success' => false,
            'message' => Lang::get('app.auctionator.warnings.server_not_supported', [
                'server' => (string) (ServerContext::server()['name'] ?? ServerContext::currentId()),
            ]),
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
