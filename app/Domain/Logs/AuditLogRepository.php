<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Logs;

use Acme\Panel\Support\AuditCatalog;
use Acme\Panel\Support\AuditStore;
use Acme\Panel\Support\TransientCache;
use PDO;
use Throwable;

/**
 * File: app/Domain/Logs/AuditLogRepository.php
 * Purpose: 审计日志（panel_audit_log）的查询层：筛选、分页、聚合、导出与按龄清理。
 *
 * 全部查询都走 AuditStore 解析出的库/表名（默认 ac_eluna.panel_audit_log），
 * 因此查询层不关心区服连接。
 */
class AuditLogRepository
{
    private const FILTERABLE_COLUMNS = [
        'channel' => 'channel',
        'module' => 'module',
        'action' => 'action',
        'actor' => 'actor',
        'status' => 'status',
    ];

    private const SELECT_COLUMNS = 'id,ts,channel,severity,status,module,action,target,summary,actor,'
        . 'realm_index,realm_id,realm_name,method,uri,ip,user_agent,duration_ms,detail';

    /** 日志库不可达时为 true，页面据此显示只读提示而不是空白。 */
    public function storageReady(): bool
    {
        return AuditStore::table() !== null;
    }

    public function total(): int
    {
        return AuditStore::count();
    }

    /**
     * @param array $filters 归一化后的筛选条件（见 LogManager::normalizeFilters）
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function search(array $filters): array
    {
        $perPage = max(1, (int) ($filters['per_page'] ?? 50));
        $page = max(1, (int) ($filters['page'] ?? 1));

        $table = AuditStore::table();
        $pdo = AuditStore::pdo();
        if ($table === null || $pdo === null) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'pages' => 0];
        }

        [$where, $params] = $this->buildWhere($filters);

        try {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table . $where);
            $this->bind($countStmt, $params);
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();

            $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            if ($pages > 0 && $page > $pages) {
                $page = $pages;
            }
            $offset = ($page - 1) * $perPage;

            $direction = strtoupper((string) ($filters['sort'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $sql = 'SELECT ' . self::SELECT_COLUMNS . ' FROM ' . $table . $where
                . ' ORDER BY id ' . $direction . ' LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            $this->bind($stmt, $params);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = $this->shape($row);
            }
        } catch (Throwable $e) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'pages' => 0];
        }

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    /**
     * 下拉选项：目录里的模块/动作（保证选项稳定）+ 近 90 天实际出现过的模块/动作/操作人/区服（带计数）。
     *
     * @return array{modules:array,actors:array,realms:array,channels:array,statuses:array}
     */
    public function facets(int $cacheSeconds = 60): array
    {
        $channels = AuditCatalog::localizedChannels();
        $statuses = AuditCatalog::localizedStatuses();
        $modules = AuditCatalog::localizedModules();

        $live = $this->liveFacets($cacheSeconds);

        $moduleOptions = [];
        foreach ($modules as $id => $meta) {
            $moduleOptions[] = [
                'id' => $id,
                'label' => $meta['label'],
                'count' => (int) ($live['modules'][$id] ?? 0),
            ];
        }
        foreach ($live['modules'] as $id => $count) {
            if (isset($modules[$id])) {
                continue;
            }
            $moduleOptions[] = ['id' => $id, 'label' => $id, 'count' => (int) $count];
        }
        usort($moduleOptions, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['id'], $b['id']));

        $actionOptions = [];
        foreach ($live['actions'] as $module => $actions) {
            foreach ($actions as $action => $count) {
                $actionOptions[$module][] = [
                    'id' => $action,
                    'label' => $modules[$module]['actions'][$action] ?? $action,
                    'count' => (int) $count,
                ];
            }
        }
        foreach ($modules as $id => $meta) {
            $known = [];
            foreach (($actionOptions[$id] ?? []) as $option) {
                $known[$option['id']] = true;
            }
            foreach ($meta['actions'] as $action => $label) {
                if (isset($known[$action])) {
                    continue;
                }
                $actionOptions[$id][] = ['id' => $action, 'label' => $label, 'count' => 0];
            }
            if (isset($actionOptions[$id])) {
                usort($actionOptions[$id], static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['id'], $b['id']));
            }
        }

        $actors = [];
        foreach ($live['actors'] as $actor => $count) {
            $actors[] = ['id' => (string) $actor, 'count' => (int) $count];
        }

        return [
            'modules' => $moduleOptions,
            'actions' => $actionOptions,
            'actors' => $actors,
            'realms' => $live['realms'],
            'channels' => $channels,
            'statuses' => $statuses,
        ];
    }

    /**
     * 顶部概览：命中总数、按状态/渠道/严重级别/模块的分布、最早最晚时间。
     */
    public function stats(array $filters): array
    {
        $table = AuditStore::table();
        $pdo = AuditStore::pdo();
        if ($table === null || $pdo === null) {
            return ['total' => 0, 'status' => [], 'channel' => [], 'severity' => [], 'module' => [], 'range' => null];
        }

        [$where, $params] = $this->buildWhere($filters);

        $stats = ['total' => 0, 'status' => [], 'channel' => [], 'severity' => [], 'module' => [], 'range' => null];

        try {
            $stmt = $pdo->prepare('SELECT status,channel,severity,module,COUNT(*) AS c FROM ' . $table . $where
                . ' GROUP BY status,channel,severity,module');
            $this->bind($stmt, $params);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $count = (int) $row['c'];
                $stats['total'] += $count;
                $stats['status'][(string) $row['status']] = ($stats['status'][(string) $row['status']] ?? 0) + $count;
                $stats['channel'][(string) $row['channel']] = ($stats['channel'][(string) $row['channel']] ?? 0) + $count;
                $stats['severity'][(int) $row['severity']] = ($stats['severity'][(int) $row['severity']] ?? 0) + $count;
                $stats['module'][(string) $row['module']] = ($stats['module'][(string) $row['module']] ?? 0) + $count;
            }
            arsort($stats['module']);

            $rangeStmt = $pdo->prepare('SELECT MIN(ts) AS first_ts, MAX(ts) AS last_ts FROM ' . $table . $where);
            $this->bind($rangeStmt, $params);
            $rangeStmt->execute();
            $range = $rangeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($range && $range['first_ts'] !== null) {
                $stats['range'] = ['first' => (string) $range['first_ts'], 'last' => (string) $range['last_ts']];
            }
        } catch (Throwable $e) {
            return $stats;
        }

        return $stats;
    }

    /**
     * 导出用的一次性取行（不分页，但有硬上限）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function exportRows(array $filters, int $limit = 5000): array
    {
        $table = AuditStore::table();
        $pdo = AuditStore::pdo();
        if ($table === null || $pdo === null) {
            return [];
        }

        $limit = max(1, min($limit, 20000));
        [$where, $params] = $this->buildWhere($filters);
        $direction = strtoupper((string) ($filters['sort'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        try {
            $stmt = $pdo->prepare('SELECT ' . self::SELECT_COLUMNS . ' FROM ' . $table . $where
                . ' ORDER BY id ' . $direction . ' LIMIT :limit');
            $this->bind($stmt, $params);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = $this->shape($row);
            }

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    public function purge(int $days): int
    {
        return AuditStore::prune($days);
    }

    /**
     * @return array{modules:array<string,int>,actions:array<string,array<string,int>>,actors:array<string,int>,realms:array<int,array{id:int,label:string,count:int}>}
     */
    private function liveFacets(int $cacheSeconds): array
    {
        $empty = ['modules' => [], 'actions' => [], 'actors' => [], 'realms' => []];

        $table = AuditStore::table();
        $pdo = AuditStore::pdo();
        if ($table === null || $pdo === null) {
            return $empty;
        }

        $cacheKey = 'facets_' . md5($table);
        $cached = TransientCache::remember('audit_logs', $cacheKey, max(1, $cacheSeconds), function () use ($pdo, $table): ?array {
            $result = ['modules' => [], 'actions' => [], 'actors' => [], 'realms' => []];

            try {
                $rows = $pdo->query(
                    'SELECT module,action,COUNT(*) AS c FROM ' . $table
                    . ' WHERE ts >= (NOW() - INTERVAL 90 DAY) GROUP BY module,action'
                );
                foreach (($rows === false ? [] : $rows->fetchAll(PDO::FETCH_ASSOC)) as $row) {
                    $module = (string) $row['module'];
                    $action = (string) $row['action'];
                    $count = (int) $row['c'];
                    $result['modules'][$module] = ($result['modules'][$module] ?? 0) + $count;
                    $result['actions'][$module][$action] = ($result['actions'][$module][$action] ?? 0) + $count;
                }
                foreach ($result['actions'] as $module => $actions) {
                    arsort($result['actions'][$module]);
                }
                arsort($result['modules']);

                $rows = $pdo->query(
                    'SELECT actor,COUNT(*) AS c FROM ' . $table
                    . ' WHERE ts >= (NOW() - INTERVAL 90 DAY) AND actor <> \'\' GROUP BY actor ORDER BY c DESC LIMIT 100'
                );
                foreach (($rows === false ? [] : $rows->fetchAll(PDO::FETCH_ASSOC)) as $row) {
                    $result['actors'][(string) $row['actor']] = (int) $row['c'];
                }

                $rows = $pdo->query(
                    'SELECT realm_index,MAX(realm_name) AS realm_name,COUNT(*) AS c FROM ' . $table
                    . ' WHERE ts >= (NOW() - INTERVAL 90 DAY) GROUP BY realm_index'
                );
                foreach (($rows === false ? [] : $rows->fetchAll(PDO::FETCH_ASSOC)) as $row) {
                    $index = (int) $row['realm_index'];
                    $name = trim((string) $row['realm_name']);
                    $result['realms'][] = [
                        'id' => $index,
                        'label' => $name !== '' ? $name : ('#' . $index),
                        'count' => (int) $row['c'],
                    ];
                }
            } catch (Throwable $e) {
                return null;
            }

            return $result;
        });

        return is_array($cached) ? $cached + $empty : $empty;
    }

    /**
     * @return array{0:string,1:array<string,array{0:mixed,1:int}>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];

        foreach (self::FILTERABLE_COLUMNS as $key => $column) {
            $value = $filters[$key] ?? null;
            if (!is_string($value) || $value === '' || $value === 'all') {
                continue;
            }
            $conditions[] = $column . ' = :' . $key;
            $params[':' . $key] = [$value, PDO::PARAM_STR];
        }

        $realm = $filters['realm'] ?? 'all';
        if ($realm !== 'all' && $realm !== null && $realm !== '') {
            $conditions[] = 'realm_index = :realm';
            $params[':realm'] = [(int) $realm, PDO::PARAM_INT];
        }

        foreach (['from' => 'ts >= :from_ts', 'to' => 'ts <= :to_ts'] as $key => $clause) {
            $value = $filters[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $conditions[] = $clause;
            $params[$key === 'from' ? ':from_ts' : ':to_ts'] = [trim($value), PDO::PARAM_STR];
        }

        $keyword = $filters['keyword'] ?? '';
        if (is_string($keyword) && trim($keyword) !== '') {
            // 原生预处理不允许同名占位符重复出现，逐列生成独立占位符（值相同）。
            $columns = ['summary', 'target', 'actor', 'uri', 'module', 'action', 'detail'];
            $clauses = [];
            foreach ($columns as $index => $column) {
                $placeholder = ':kw' . $index;
                $clauses[] = $column . ' LIKE ' . $placeholder;
                $params[$placeholder] = ['%' . self::escapeLike(trim($keyword)) . '%', PDO::PARAM_STR];
            }
            $conditions[] = '(' . implode(' OR ', $clauses) . ')';
        }

        return [$conditions === [] ? '' : (' WHERE ' . implode(' AND ', $conditions)), $params];
    }

    /** @param array<string,array{0:mixed,1:int}> $params */
    private function bind(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => [$value, $type]) {
            $stmt->bindValue($name, $value, $type);
        }
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** @return array<string,mixed> */
    private function shape(array $row): array
    {
        $detail = $row['detail'] ?? null;
        $decoded = null;
        if (is_string($detail) && $detail !== '') {
            $parsed = json_decode($detail, true);
            $decoded = is_array($parsed) ? $parsed : ['raw' => $detail];
        }

        $module = (string) ($row['module'] ?? '');
        $action = (string) ($row['action'] ?? '');
        $channel = (string) ($row['channel'] ?? 'audit');
        $status = (string) ($row['status'] ?? 'ok');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'ts' => (string) ($row['ts'] ?? ''),
            'channel' => $channel,
            'channel_label' => AuditCatalog::channelLabel($channel),
            'severity' => (int) ($row['severity'] ?? 1),
            'status' => $status,
            'status_label' => AuditCatalog::statusLabel($status),
            'module' => $module,
            'module_label' => AuditCatalog::moduleLabel($module),
            'action' => $action,
            'action_label' => AuditCatalog::actionLabel($module, $action),
            'target' => (string) ($row['target'] ?? ''),
            'summary' => (string) ($row['summary'] ?? ''),
            'actor' => (string) ($row['actor'] ?? ''),
            'realm_index' => (int) ($row['realm_index'] ?? 0),
            'realm_id' => (int) ($row['realm_id'] ?? 0),
            'realm_name' => (string) ($row['realm_name'] ?? ''),
            'method' => (string) ($row['method'] ?? ''),
            'uri' => (string) ($row['uri'] ?? ''),
            'ip' => (string) ($row['ip'] ?? ''),
            'user_agent' => (string) ($row['user_agent'] ?? ''),
            'duration_ms' => (int) ($row['duration_ms'] ?? 0),
            'detail' => $decoded,
        ];
    }
}
