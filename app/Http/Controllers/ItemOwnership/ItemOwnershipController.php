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
        $requestedServer = $request->input('server');
        if ($requestedServer !== null) {
            $sid = (int) $requestedServer;
            if (ServerList::valid($sid) && ServerContext::currentId() !== $sid) {
                ServerContext::set($sid);
                $this->repo = new ItemOwnershipRepository($sid);
            }
        }
        return $this->view('item_owner.index', [
            'title' => Lang::get('app.item_owner.page_title'),
            'current_server' => ServerContext::currentId(),
        ]);
    }

    public function apiSearchItems(Request $request): Response
    {
        $this->requireLogin();
        $keyword = (string) $request->input('keyword', '');
        $limit = (int) $request->input('limit', 20);
        $items = $this->repo->searchItems($keyword, $limit);
        return $this->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function apiOwnership(Request $request): Response
    {
        $this->requireLogin();
        $entry = (int) $request->input('entry', 0);
        if ($entry <= 0) {
            return $this->json([
                'success' => false,
                'message' => Lang::get('app.item_owner.api.errors.invalid_entry'),
            ]);
        }
        $data = $this->repo->fetchOwnership($entry);
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
}


