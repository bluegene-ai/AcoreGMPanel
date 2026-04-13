<?php
/**
 * File: app/Http/Controllers/ItemOwnership/ItemOwnershipController.php
 * Purpose: Defines class ItemOwnershipController for the app/Http/Controllers/ItemOwnership module.
 * Classes:
 *   - ItemOwnershipController
 * Functions:
 *   - __construct()
 *   - index()
 *   - apiSearchItems()
 *   - apiOwnership()
 *   - apiBulk()
 */

namespace Acme\Panel\Http\Controllers\ItemOwnership;

use Acme\Panel\Core\Controller;
use Acme\Panel\Core\Lang;
use Acme\Panel\Core\Request;
use Acme\Panel\Core\Response;
use Acme\Panel\Domain\ItemOwnership\ItemOwnershipRepository;
use Acme\Panel\Support\ServerContext;
use Acme\Panel\Support\ServerList;

class ItemOwnershipController extends Controller
{
    private ItemOwnershipRepository $repo;

    public function __construct()
    {
        $this->repo = new ItemOwnershipRepository();
    }

    public function index(Request $request): Response
    {
        $this->requireLogin();
        $this->switchServerAndRefresh($request, function (int $serverId): void {
            $this->repo = new ItemOwnershipRepository($serverId);
        });
        return $this->pageView('item_owner.index', $this->serverViewData());
    }

    public function apiSearchItems(Request $request): Response
    {
        $this->requireLogin();
        $state = $this->prepareItemSearchState($request);
        $items = $this->repo->searchItems($state['keyword'], $state['limit']);
        return $this->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function apiOwnership(Request $request): Response
    {
        $this->requireLogin();
        $state = $this->prepareOwnershipState($request);
        if ($state['entry'] <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.item_owner.api.errors.invalid_entry'),
            ]);
        }
        $data = $this->repo->fetchOwnership($state['entry']);
        if (!$data['item']) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.item_owner.api.errors.entry_not_found'),
            ]);
        }
        return $this->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function apiBulk(Request $request): Response
    {
        $this->requireLogin();
        $action = (string) $request->input('action', '');
        $instances = $request->input('instances', []);
        if (!is_array($instances)) {
            $instances = [$instances];
        }
        if ($action === 'delete') {
            $result = $this->repo->bulkDelete($instances);
            return $this->json($result);
        }
        if ($action === 'replace') {
            $newEntry = (int) $request->input('new_entry', 0);
            $result = $this->repo->bulkReplace($instances, $newEntry);
            return $this->json($result);
        }
        return $this->json([
            'success' => false,
            'message' => Lang::get('app.item_owner.api.errors.unknown_action'),
        ]);
    }

    private function prepareItemSearchState(Request $request): array
    {
        return [
            'keyword' => $this->normalizedString($request, 'keyword'),
            'limit' => $this->boundedInt($request, 'limit', 20, 1, 200),
        ];
    }

    private function prepareOwnershipState(Request $request): array
    {
        return [
            'entry' => max(0, (int) $request->input('entry', 0)),
        ];
    }
}


