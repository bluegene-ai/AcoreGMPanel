<?php
/**
 * File: app/Domain/Logs/LogManager.php
 * Purpose: 日志管理门面：把请求参数归一化成查询条件，再交给 AuditLogRepository 取数。
 */

namespace Acme\Panel\Domain\Logs;

use Acme\Panel\Support\AuditCatalog;

class LogManager
{
    private AuditLogRepository $repo;

    public function __construct(?AuditLogRepository $repo = null)
    {
        $this->repo = $repo ?? new AuditLogRepository();
    }

    public function repository(): AuditLogRepository
    {
        return $this->repo;
    }

    public function defaults(): array
    {
        return AuditCatalog::defaults();
    }

    public function limits(?string $key = null, mixed $default = null): mixed
    {
        return AuditCatalog::limits($key, $default);
    }

    public function ranges(): array
    {
        return AuditCatalog::ranges();
    }

    /** 页面与前端共用的目录：模块/动作标签、渠道、状态、区服、操作人。 */
    public function catalog(): array
    {
        $facets = $this->repo->facets();

        return [
            'modules' => $facets['modules'],
            'actions' => $facets['actions'],
            'actors' => $facets['actors'],
            'realms' => $facets['realms'],
            'channels' => $facets['channels'],
            'statuses' => $facets['statuses'],
            'ranges' => array_keys($this->ranges()),
        ];
    }

    /**
     * 请求参数 → 查询条件。非法取值一律退回默认值，绝不把用户输入直接拼进 SQL。
     */
    public function normalizeFilters(array $input, ?int $forcedRealm = null): array
    {
        $defaults = $this->defaults();
        $maxPerPage = (int) $this->limits('max_per_page', 200);
        if ($maxPerPage < 1) {
            $maxPerPage = 200;
        }

        $perPage = (int) ($input['per_page'] ?? $this->limits('per_page', 50));
        $perPage = max(1, min($maxPerPage, $perPage > 0 ? $perPage : 50));

        $range = (string) ($input['range'] ?? ($defaults['range'] ?? '7d'));
        $rangeMap = $this->ranges();
        if (!array_key_exists($range, $rangeMap)) {
            $range = 'custom';
        }

        $channel = $this->pick($input['channel'] ?? null, array_keys(AuditCatalog::localizedChannels()));
        $status = $this->pick($input['status'] ?? null, array_keys(AuditCatalog::localizedStatuses()));

        $realm = $forcedRealm !== null ? (string) $forcedRealm : (string) ($input['realm'] ?? 'all');
        if ($realm !== 'all' && !preg_match('/^\d+$/', $realm)) {
            $realm = 'all';
        }

        $from = $this->timestamp($input['from'] ?? null);
        $to = $this->timestamp($input['to'] ?? null);

        if ($range !== 'custom') {
            $spec = (string) ($rangeMap[$range] ?? '');
            if ($spec === '') {
                $from = null;
                $to = null;
            } else {
                $from = date('Y-m-d H:i:s', (int) strtotime($spec));
                $to = null;
            }
        }

        return [
            'keyword' => AuditCatalog::clip((string) ($input['keyword'] ?? ''), 120),
            'channel' => $channel,
            'module' => $this->pick($input['module'] ?? null, null),
            'action' => $this->pick($input['action'] ?? null, null),
            'actor' => $this->pick($input['actor'] ?? null, null),
            'status' => $status,
            'realm' => $realm,
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'page' => max(1, (int) ($input['page'] ?? 1)),
            'per_page' => $perPage,
            'sort' => strtolower((string) ($input['sort'] ?? ($defaults['sort'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * 列表查询：返回行、分页信息与生效的筛选条件（前端据此回填表单）。
     */
    public function search(array $input, ?int $forcedRealm = null): array
    {
        $filters = $this->normalizeFilters($input, $forcedRealm);
        $result = $this->repo->search($filters);

        return $result + ['filters' => $filters];
    }

    public function stats(array $input, ?int $forcedRealm = null): array
    {
        return $this->repo->stats($this->normalizeFilters($input, $forcedRealm));
    }

    public function facets(): array
    {
        return $this->repo->facets();
    }

    /** @return array<int, array<string, mixed>> */
    public function export(array $input, int $limit = 5000, ?int $forcedRealm = null): array
    {
        return $this->repo->exportRows($this->normalizeFilters($input, $forcedRealm), $limit);
    }

    public function purge(int $days): int
    {
        $min = (int) $this->limits('purge_min_days', 1);
        $max = (int) $this->limits('purge_max_days', 3650);
        $days = max($min, min($max, $days));

        return $this->repo->purge($days);
    }

    /**
     * 各模块日志面板共用的取数：按模块 + 动作集合取最近若干条，返回可直接展示的文本行与结构化行。
     *
     * @param array<int,string> $actions 空数组表示该模块全部动作
     * @return array{lines:array<int,string>,entries:array<int,array<string,mixed>>}
     */
    public function moduleLogLines(string $module, array $actions, int $limit): array
    {
        $limit = max(1, min(500, $limit));
        $filters = $this->normalizeFilters([
            'module' => $module,
            'range' => 'all',
            'per_page' => $limit,
            'page' => 1,
        ]);

        $result = $this->repo->search($filters);
        $lines = [];
        foreach ($result['rows'] as $row) {
            $lines[] = $this->formatLine($row);
        }

        return ['lines' => $lines, 'entries' => $result['rows']];
    }

    public function formatLine(array $row): string
    {
        $status = strtoupper((string) ($row['status'] ?? 'ok'));
        $parts = [
            '[' . (string) ($row['ts'] ?? '') . ']',
            (string) ($row['actor'] ?? '-'),
            'S' . (int) ($row['realm_index'] ?? 0),
            (string) ($row['module'] ?? '') . '.' . (string) ($row['action'] ?? ''),
            $status,
        ];
        $target = trim((string) ($row['target'] ?? ''));
        if ($target !== '') {
            $parts[] = $target;
        }
        $summary = trim((string) ($row['summary'] ?? ''));
        if ($summary !== '') {
            $parts[] = $summary;
        }
        $detail = $row['detail'] ?? null;
        if (is_array($detail) && $detail !== []) {
            $json = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($json) && $json !== '' && $json !== '[]') {
                $parts[] = $json;
            }
        }

        return implode(' | ', $parts);
    }

    /** 白名单取值：命中 allowed 返回原值，否则返回 all/空。allowed 为 null 时只做长度与字符校验。 */
    private function pick(mixed $value, ?array $allowed): string
    {
        if (!is_string($value)) {
            return $allowed === null ? '' : 'all';
        }
        $value = trim($value);
        if ($value === '' || $value === 'all') {
            return $allowed === null ? '' : 'all';
        }
        if ($allowed !== null && !in_array($value, $allowed, true)) {
            return 'all';
        }
        if (!preg_match('/^[\w.\-:@ ]{1,64}$/u', $value)) {
            return $allowed === null ? '' : 'all';
        }

        return $value;
    }

    private function timestamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        $parsed = strtotime($value);
        if ($parsed === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $parsed);
    }
}
