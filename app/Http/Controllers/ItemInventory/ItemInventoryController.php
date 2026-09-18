<?php
/**
 * File: app/Http/Controllers/ItemInventory/ItemInventoryController.php
 * Purpose: Single controller for the unified item/inventory module. Serves both
 *          search axes and every read/write endpoint:
 *
 *            character axis → searchCharacters / apiCharacters / apiCharacterItems
 *            item axis      → apiSearchItems / apiOwnership
 *            mutations      → apiReduce / apiBulk
 *
 * Replaces BagQueryController and ItemOwnershipController.
 *
 * Classes:
 *   - ItemInventoryController
 */

declare(strict_types=1);

namespace Acme\Panel\Http\Controllers\ItemInventory;

use Acme\Panel\Core\{Controller, Lang, Request, Response};
use Acme\Panel\Domain\ItemInventory\ItemInventoryMutationService;
use Acme\Panel\Domain\ItemInventory\ItemInventoryRepository;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\ServerContext;

class ItemInventoryController extends Controller
{
    private const MAX_OWNERSHIP_PER_PAGE = 200;
    private const MAX_OWNERSHIP_ROWS = 1000;

    private ?ItemInventoryRepository $repo = null;
    private ?ItemInventoryMutationService $mutations = null;

    private function requireViewCapability(): void
    {
        $this->requireCapability('inventory.view');
    }

    private function requireManageCapability(): void
    {
        $this->requireCapability('inventory.manage');
    }

    private function repo(): ItemInventoryRepository
    {
        if ($this->repo === null) {
            $this->repo = new ItemInventoryRepository();
        }

        return $this->repo;
    }

    private function mutations(): ItemInventoryMutationService
    {
        if ($this->mutations === null) {
            $this->mutations = new ItemInventoryMutationService();
        }

        return $this->mutations;
    }

    private function rebuild(): void
    {
        $this->repo = null;
        $this->mutations = null;
    }

    /* ------------------------------------------------------------------ *
     * Page
     * ------------------------------------------------------------------ */

    public function index(Request $request): Response
    {
        $this->requireViewCapability();
        $this->switchServerAndRefresh($request, function (): void {
            $this->rebuild();
        });

        return $this->pageView('item_inventory.index', $this->serverViewData([
            'prefill' => $this->resolvePrefill($request),
        ]), [
            'capabilities' => [
                'view' => 'inventory.view',
                'manage' => 'inventory.manage',
                // Lets item ids deep-link into the item editor. The editor itself
                // enforces content.view; this only decides whether to offer a link.
                'content_view' => 'content.view',
            ],
        ]);
    }

    /**
     * Describes what the operator was asking for before being sent here, so the
     * page can restore the right mode and even auto-run the search.
     */
    private function resolvePrefill(Request $request): array
    {
        $value = trim((string) $request->input('value', ''));
        $mode = (string) $request->input('mode', '');
        $entry = max(0, (int) $request->input('entry', 0));

        // Legacy ?name= (BagQuery) and legacy ?keyword= (ItemOwnership) both work.
        $legacyName = trim((string) $request->input('name', ''));
        $legacyKeyword = trim((string) $request->input('keyword', ''));

        if ($entry > 0) {
            return ['mode' => 'item', 'type' => 'entry', 'value' => (string) $entry, 'entry' => $entry, 'auto' => true];
        }
        if ($legacyKeyword !== '') {
            return ['mode' => 'item', 'type' => 'keyword', 'value' => $legacyKeyword, 'entry' => 0, 'auto' => true];
        }
        if ($value !== '') {
            $type = $this->normalizedEnum($request, 'type', ['character_name', 'username'], 'character_name');

            return ['mode' => 'character', 'type' => $type, 'value' => $value, 'entry' => 0, 'auto' => true];
        }
        if ($legacyName !== '') {
            return ['mode' => 'character', 'type' => 'character_name', 'value' => $legacyName, 'entry' => 0, 'auto' => true];
        }

        return [
            'mode' => in_array($mode, ['character', 'item'], true) ? $mode : 'character',
            'type' => 'character_name',
            'value' => '',
            'entry' => 0,
            'auto' => false,
        ];
    }

    /** /bag-query was the original URL of the character axis. */
    public function legacyRedirect(Request $request): Response
    {
        $query = $_GET;
        unset($query['name'], $query['keyword']);
        $target = '/item-inventory?mode=character';
        if ($query) {
            $target .= '&' . http_build_query($query);
        }

        return Response::redirect($target, 301);
    }

    /** /bag was the character axis page. */
    public function legacyBagRedirect(Request $request): Response
    {
        $target = '/item-inventory?mode=character';
        $name = trim((string) $request->input('name', ''));
        $value = trim((string) $request->input('value', ''));
        $type = (string) $request->input('type', '');
        $params = [];
        if ($name !== '') {
            $params['value'] = $name;
        } elseif ($value !== '') {
            $params['value'] = $value;
            if ($type === 'username') {
                $params['type'] = 'username';
            }
        }
        if ($params) {
            $target .= '&' . http_build_query($params);
        }

        return Response::redirect($this->appendServer($request, $target), 301);
    }

    /** /item-ownership was the item axis page. */
    public function legacyOwnershipRedirect(Request $request): Response
    {
        $target = '/item-inventory?mode=item';
        $keyword = trim((string) $request->input('keyword', ''));
        $entry = max(0, (int) $request->input('entry', 0));
        $params = [];
        if ($entry > 0) {
            $params['entry'] = $entry;
        } elseif ($keyword !== '') {
            $params['value'] = $keyword;
        }
        if ($params) {
            $target .= '&' . http_build_query($params);
        }

        return Response::redirect($this->appendServer($request, $target), 301);
    }

    private function appendServer(Request $request, string $target): string
    {
        $server = (string) $request->input('server', '');
        if ($server !== '') {
            $target .= '&server=' . urlencode($server);
        }

        return $target;
    }

    /* ------------------------------------------------------------------ *
     * Character axis
     * ------------------------------------------------------------------ */

    public function apiCharacters(Request $request): Response
    {
        $this->requireViewCapability();
        $type = $this->normalizedEnum($request, 'type', ['character_name', 'username'], 'character_name');
        $value = $this->normalizedString($request, 'value');
        $limit = $this->boundedInt($request, 'limit', 100, 1, 200);

        $list = [];
        if ($value !== '') {
            $list = $this->repo()->searchCharacters($type, $value, $limit);
        }

        Audit::log('item_inventory', 'search_characters', 'characters', [
            'server' => ServerContext::currentId(),
            'type' => $type,
            'value' => mb_substr($value, 0, 40),
            'returned' => count($list),
        ]);

        return $this->json(['success' => true, 'data' => $list]);
    }

    public function apiCharacterItems(Request $request): Response
    {
        $this->requireViewCapability();
        $guid = max(0, (int) $request->input('guid', 0));
        if ($guid <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.item_inventory.api.errors.invalid_guid'),
            ]);
        }

        $items = $this->repo()->characterItems($guid);

        Audit::log('item_inventory', 'view_character_items', (string) $guid, [
            'server' => ServerContext::currentId(),
            'count' => count($items),
        ]);

        return $this->json([
            'success' => true,
            'data' => $items,
            'summary' => [
                'instances' => count($items),
                'count' => array_sum(array_map(static fn (array $row): int => (int) ($row['count'] ?? 0), $items)),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Item axis
     * ------------------------------------------------------------------ */

    public function apiSearchItems(Request $request): Response
    {
        $this->requireViewCapability();
        $keyword = $this->normalizedString($request, 'keyword');
        $limit = $this->boundedInt($request, 'limit', 20, 1, 50);

        $items = $keyword === '' ? [] : $this->repo()->searchItems($keyword, $limit);

        return $this->json(['success' => true, 'data' => $items]);
    }

    public function apiOwnership(Request $request): Response
    {
        $this->requireViewCapability();
        $entry = max(0, (int) $request->input('entry', 0));
        if ($entry <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.item_inventory.api.errors.invalid_entry'),
            ]);
        }

        $paginate = (string) $request->input('paginate', '1') !== '0';
        $page = max(1, (int) $request->input('page', 1));
        $perPage = $this->boundedInt($request, 'per_page', 100, 1, self::MAX_OWNERSHIP_PER_PAGE);

        $data = $this->repo()->fetchOwnership($entry, $paginate, $page, $perPage, self::MAX_OWNERSHIP_ROWS);
        if ($data['item'] === null) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.item_inventory.api.errors.entry_not_found'),
            ]);
        }

        Audit::log('item_inventory', 'view_ownership', (string) $entry, [
            'server' => ServerContext::currentId(),
            'characters' => $data['summary']['characters'] ?? 0,
            'instances' => $data['summary']['instances'] ?? 0,
            'page' => $data['page'],
        ]);

        return $this->json([
            'success' => true,
            'data' => [
                'item' => $data['item'],
                'rows' => $data['rows'],
                'summary' => $data['summary'],
                'page' => $data['page'],
                'per_page' => $data['per_page'],
                'paginated' => $data['paginated'],
            ],
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Mutations
     * ------------------------------------------------------------------ */

    public function apiReduce(Request $request): Response
    {
        $this->requireManageCapability();

        $guid = (int) $request->input('character_guid', 0);
        $instance = (int) $request->input('item_instance_guid', 0);
        $qty = (int) $request->input('quantity', 0);
        $entry = (int) $request->input('item_entry', 0);
        $destroyContents = (int) $request->input('destroy_contents', 0) === 1;

        if ($guid <= 0 || $instance <= 0 || $qty <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.item_inventory.api.errors.invalid_parameters'),
            ]);
        }

        $result = $this->mutations()->reduceInstance($guid, $instance, $qty, $entry > 0 ? $entry : null, $destroyContents);

        Audit::log('item_inventory', 'reduce_instance', (string) $instance, [
            'server' => ServerContext::currentId(),
            'character_guid' => $guid,
            'item_entry' => $entry > 0 ? $entry : null,
            'quantity' => $qty,
            'destroy_contents' => $destroyContents,
            'success' => $result['success'] ?? false,
            'new_count' => $result['new_count'] ?? null,
            'message' => $result['message'] ?? null,
        ]);

        return $this->json($result);
    }

    public function apiBulk(Request $request): Response
    {
        $this->requireManageCapability();

        $action = (string) $request->input('action', '');
        $instances = $request->input('instances', []);
        if (!is_array($instances)) {
            $instances = [$instances];
        }

        if ($action === 'delete') {
            $destroyContents = (int) $request->input('destroy_contents', 0) === 1;
            $result = $this->mutations()->bulkDelete($instances, $destroyContents);

            Audit::log('item_inventory', 'bulk_delete', (string) count($result['deleted']), [
                'server' => ServerContext::currentId(),
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'destroyed' => $result['destroyed'],
                'success' => $result['success'],
            ]);

            return $this->json($result);
        }

        if ($action === 'replace') {
            $newEntry = (int) $request->input('new_entry', 0);
            $result = $this->mutations()->bulkReplace($instances, $newEntry);

            Audit::log('item_inventory', 'bulk_replace', (string) count($result['updated']), [
                'server' => ServerContext::currentId(),
                'new_entry' => $newEntry,
                'updated' => $result['updated'],
                'created' => $result['created'],
                'failed' => $result['failed'],
                'success' => $result['success'],
            ]);

            return $this->json($result);
        }

        return $this->json([
            'success' => false,
            'message' => Lang::get('app.item_inventory.api.errors.unknown_action'),
        ]);
    }
}
