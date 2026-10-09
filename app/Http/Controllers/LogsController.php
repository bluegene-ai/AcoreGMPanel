<?php
/**
 * File: app/Http/Controllers/LogsController.php
 * Purpose: 审计日志页与 API：筛选查询、下拉目录、CSV 导出与按龄清理。
 *
 * 日志表是跨区服共用的（ac_eluna.panel_audit_log），所以这里不切换区服上下文，
 * 区服只作为一个筛选维度，页面默认能看到全部区服的操作。
 */

namespace Acme\Panel\Http\Controllers;

use Acme\Panel\Core\{Controller,Lang,Request,Response};
use Acme\Panel\Domain\Logs\LogManager;
use Acme\Panel\Support\{Audit,AuditCatalog};

class LogsController extends Controller
{
    private LogManager $manager;

    public function __construct()
    {
        $this->manager = new LogManager();
    }

    private function requireCatalogCapability(): void
    {
        $this->requireCapability('logs.catalog');
    }

    private function requireReadCapability(): void
    {
        $this->requireCapability('logs.read');
    }

    private function requirePurgeCapability(): void
    {
        $this->requireCapability('logs.purge');
    }

    public function index(Request $request): Response
    {
        $this->requireCatalogCapability();

        $repo = $this->manager->repository();

        return $this->pageView('logs.index', [
            'catalog' => $this->manager->catalog(),
            'filters' => $this->manager->normalizeFilters($request->all()),
            'limits' => [
                'per_page' => (int) $this->manager->limits('per_page', 50),
                'max_per_page' => (int) $this->manager->limits('max_per_page', 200),
                'retention_days' => (int) AuditCatalog::storage('retention_days', 180),
            ],
            'storage_ready' => $repo->storageReady(),
            'total' => $repo->total(),
        ], [
            'capabilities' => [
                'catalog' => 'logs.catalog',
                'read' => 'logs.read',
                'purge' => 'logs.purge',
            ],
        ]);
    }

    public function apiList(Request $request): Response
    {
        $this->requireReadCapability();

        $params = $request->all();
        $result = $this->manager->search($params);

        return $this->json([
            'success' => true,
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'per_page' => $result['per_page'],
            'filters' => $result['filters'],
            'stats' => $this->manager->stats($params),
            'storage_ready' => $this->manager->repository()->storageReady(),
        ]);
    }

    public function apiFacets(Request $request): Response
    {
        $this->requireReadCapability();

        return $this->json(['success' => true, 'facets' => $this->manager->facets()]);
    }

    public function apiPurge(Request $request): Response
    {
        $this->requirePurgeCapability();

        $days = $this->boundedInt($request, 'days', (int) AuditCatalog::storage('retention_days', 180), 1, 3650);
        $deleted = $this->manager->purge($days);

        Audit::log('logs', 'purge', $days . 'd', [
            'summary' => Lang::get('app.logs.api.purge_summary', ['days' => $days, 'deleted' => $deleted]),
            'days' => $days,
            'deleted' => $deleted,
        ]);

        return $this->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => Lang::get('app.logs.api.purge_done', ['days' => $days, 'deleted' => $deleted]),
        ]);
    }

    /** CSV 导出走普通链接（GET），浏览器直接下载，不经前端 fetch。 */
    public function export(Request $request): Response
    {
        $this->requireReadCapability();

        $params = $request->all();
        $rows = $this->manager->export($params, 5000);
        $filters = $this->manager->normalizeFilters($params);

        Audit::log('logs', 'export', count($rows) . ' rows', [
            'summary' => Lang::get('app.logs.api.export_summary', ['count' => count($rows)]),
            'filters' => $filters,
            'rows' => count($rows),
        ]);

        $filename = 'agmp-audit-' . date('Ymd-His') . '.csv';
        $handle = fopen('php://temp', 'r+');
        // Excel 认 BOM 才不乱码；表头用英文列名，避免不同语种导出后难以对齐。
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'ts', 'realm', 'actor', 'channel', 'module', 'action', 'status', 'severity',
            'target', 'summary', 'method', 'uri', 'ip', 'duration_ms', 'detail',
        ]);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['ts'],
                $row['realm_index'] . ($row['realm_name'] !== '' ? ' ' . $row['realm_name'] : ''),
                $row['actor'],
                $row['channel'],
                $row['module'],
                $row['action'],
                $row['status'],
                $row['severity'],
                $row['target'],
                $row['summary'],
                $row['method'],
                $row['uri'],
                $row['ip'],
                $row['duration_ms'],
                $row['detail'] === null ? '' : json_encode($row['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $this->response(200, $csv, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
