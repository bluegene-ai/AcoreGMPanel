<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Aegis;

use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Domain\Support\ReadModelCache;
use Acme\Panel\Support\Paginator;
use PDO;

class AegisRepository extends MultiServerRepository
{
    private ReadModelCache $readCache;

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);
        $this->readCache = new ReadModelCache('aegis_server_' . $this->serverId);
    }

    public function rebind(int $serverId): void
    {
        parent::rebind($serverId);
        $this->readCache = new ReadModelCache('aegis_server_' . $this->serverId);
    }

    public function invalidateReadCaches(): void
    {
        $this->readCache->clearNamespace('identity');
        $this->readCache->clearNamespace('accounts');
        $this->readCache->clearNamespace('character_summary');
    }

    public function overview(int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $now = time();
        $windowSql = $this->daysWindowSql($days);

        $offense = $this->characters()->query(
            'SELECT '
            . 'COUNT(*) AS total, '
            . 'SUM(CASE WHEN offense_count > 0 THEN 1 ELSE 0 END) AS tracked, '
            . 'SUM(CASE WHEN debuff_until > ' . $now . ' THEN 1 ELSE 0 END) AS debuffed, '
            . 'SUM(CASE WHEN jail_until > ' . $now . ' THEN 1 ELSE 0 END) AS jailed, '
            . 'SUM(CASE WHEN permanent_ban = 1 OR ban_until > ' . $now . ' THEN 1 ELSE 0 END) AS banned, '
            . 'SUM(CASE WHEN permanent_ban = 1 THEN 1 ELSE 0 END) AS permanent_ban '
            . 'FROM ac_aegis_offense'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $events = $this->characters()->query(
            'SELECT '
            . 'COUNT(*) AS total, '
            . 'SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS last_day, '
            . 'SUM(CASE WHEN created_at >= ' . $windowSql . ' THEN 1 ELSE 0 END) AS window_total '
            . 'FROM ac_aegis_event'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stageRows = $this->characters()->query(
            'SELECT punish_stage, COUNT(*) AS total '
            . 'FROM ac_aegis_offense GROUP BY punish_stage ORDER BY punish_stage ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $cheatRows = $this->characters()->query(
            'SELECT cheat_type, COUNT(*) AS total '
            . 'FROM ac_aegis_event '
            . 'WHERE created_at >= ' . $windowSql . ' '
            . 'GROUP BY cheat_type ORDER BY total DESC, cheat_type ASC LIMIT 8'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $topRows = $this->characters()->query(
            'SELECT guid, account_id, offense_count, offense_tier, punish_stage, '
            . 'last_cheat_type, last_offense_at '
            . 'FROM ac_aegis_offense '
            . 'WHERE offense_count > 0 '
            . 'ORDER BY offense_count DESC, offense_tier DESC, last_offense_at DESC LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->hydrateOffenseRows($topRows);

        return [
            'days' => $days,
            'offense' => [
                'total' => (int) ($offense['total'] ?? 0),
                'tracked' => (int) ($offense['tracked'] ?? 0),
                'debuffed' => (int) ($offense['debuffed'] ?? 0),
                'jailed' => (int) ($offense['jailed'] ?? 0),
                'banned' => (int) ($offense['banned'] ?? 0),
                'permanent_ban' => (int) ($offense['permanent_ban'] ?? 0),
            ],
            'events' => [
                'total' => (int) ($events['total'] ?? 0),
                'last_day' => (int) ($events['last_day'] ?? 0),
                'window_total' => (int) ($events['window_total'] ?? 0),
            ],
            'stages' => array_map(static function (array $row): array {
                return [
                    'value' => (int) ($row['punish_stage'] ?? 0),
                    'total' => (int) ($row['total'] ?? 0),
                ];
            }, $stageRows),
            'cheats' => array_map(static function (array $row): array {
                return [
                    'value' => (int) ($row['cheat_type'] ?? 0),
                    'total' => (int) ($row['total'] ?? 0),
                ];
            }, $cheatRows),
            'top_offenders' => $topRows,
        ];
    }

    public function searchOffenses(array $filters, int $page, int $perPage): Paginator
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $now = time();

        $wheres = [];
        $params = [];

        $query = trim((string) ($filters['query'] ?? ''));
        $stage = (string) ($filters['stage'] ?? '');
        $cheatType = (int) ($filters['cheat_type'] ?? 0);
        $status = trim((string) ($filters['status'] ?? 'all'));

        if ($stage !== '' && preg_match('/^\d+$/', $stage)) {
            $wheres[] = 'o.punish_stage = :stage';
            $params[':stage'] = (int) $stage;
        }

        if ($cheatType > 0) {
            $wheres[] = 'o.last_cheat_type = :cheat_type';
            $params[':cheat_type'] = $cheatType;
        }

        switch ($status) {
            case 'tracked':
                $wheres[] = 'o.offense_count > 0';
                break;
            case 'debuffed':
                $wheres[] = 'o.debuff_until > :now_debuff';
                $params[':now_debuff'] = $now;
                break;
            case 'jailed':
                $wheres[] = 'o.jail_until > :now_jail';
                $params[':now_jail'] = $now;
                break;
            case 'banned':
                $wheres[] = '(o.permanent_ban = 1 OR o.ban_until > :now_ban)';
                $params[':now_ban'] = $now;
                break;
            case 'permanent':
                $wheres[] = 'o.permanent_ban = 1';
                break;
        }

        $this->applyIdentityFilter('o.guid', 'o.account_id', $query, $wheres, $params);

        $whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

        $countSql = 'SELECT COUNT(*) FROM ac_aegis_offense o ' . $whereSql;
        $countStmt = $this->characters()->prepare($countSql);
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sort = strtolower(trim((string) ($filters['sort'] ?? 'last_offense_desc')));
        $orderBy = match ($sort) {
            'offense_desc' => 'o.offense_count DESC, o.last_offense_at DESC',
            'tier_desc' => 'o.offense_tier DESC, o.last_offense_at DESC',
            'risk_like' => 'o.punish_stage DESC, o.offense_tier DESC, o.offense_count DESC',
            default => 'o.last_offense_at DESC, o.offense_count DESC',
        };

        $sql = 'SELECT o.guid, o.account_id, o.offense_count, o.offense_tier, o.punish_stage, '
            . 'o.last_cheat_type, o.last_offense_at, o.debuff_until, o.jail_until, '
            . 'o.ban_until, o.permanent_ban, o.last_reason, o.last_ban_mode, o.last_ban_result '
            . 'FROM ac_aegis_offense o '
            . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset';
        $stmt = $this->characters()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->hydrateOffenseRows($rows);

        return new Paginator($rows, $total, $page, $perPage);
    }

    public function searchEvents(array $filters, int $page, int $perPage): Paginator
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $wheres = [];
        $params = [];

        $query = trim((string) ($filters['query'] ?? ''));
        $cheatType = (int) ($filters['cheat_type'] ?? 0);
        $evidenceLevel = (string) ($filters['evidence_level'] ?? '');
        $days = (int) ($filters['days'] ?? 7);
        $days = max(1, min(90, $days));

        if ($cheatType > 0) {
            $wheres[] = 'e.cheat_type = :cheat_type';
            $params[':cheat_type'] = $cheatType;
        }

        if ($evidenceLevel !== '' && preg_match('/^\d+$/', $evidenceLevel)) {
            $wheres[] = 'e.evidence_level = :evidence_level';
            $params[':evidence_level'] = (int) $evidenceLevel;
        }

        $wheres[] = 'e.created_at >= ' . $this->daysWindowSql($days);

        $this->applyIdentityFilter('e.guid', 'e.account_id', $query, $wheres, $params);

        $whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

        $countSql = 'SELECT COUNT(*) FROM ac_aegis_event e ' . $whereSql;
        $countStmt = $this->characters()->prepare($countSql);
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT e.id, e.guid, e.account_id, e.map_id, e.zone_id, e.area_id, '
            . 'e.cheat_type, e.evidence_level, e.risk_delta, e.total_risk_after, '
            . 'e.evidence_tag, e.detail_text, e.pos_x, e.pos_y, e.pos_z, e.created_at '
            . 'FROM ac_aegis_event e '
            . $whereSql . ' ORDER BY e.created_at DESC, e.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->characters()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->hydrateEventRows($rows);

        return new Paginator($rows, $total, $page, $perPage);
    }

    public function findPlayerSnapshot(?int $guid, ?string $name): ?array
    {
        $guid = $guid !== null ? max(0, $guid) : 0;
        $name = trim((string) $name);

        $summary = null;
        if ($guid > 0) {
            $summary = $this->findCharacterSummaryByGuid($guid);
        } elseif ($name !== '') {
            $summary = $this->findCharacterSummaryByName($name);
            $guid = (int) ($summary['guid'] ?? 0);
        }

        if ($guid <= 0 && !$summary) {
            return null;
        }

        if ($guid <= 0 && $summary) {
            $guid = (int) ($summary['guid'] ?? 0);
        }

        $offense = null;
        if ($guid > 0) {
            $stmt = $this->characters()->prepare(
                'SELECT guid, account_id, offense_count, offense_tier, punish_stage, '
                . 'last_cheat_type, last_offense_at, debuff_until, jail_until, ban_until, '
                . 'permanent_ban, last_reason, last_ban_mode, last_ban_result '
                . 'FROM ac_aegis_offense WHERE guid = :guid LIMIT 1'
            );
            $stmt->execute([':guid' => $guid]);
            $offense = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($offense) {
                $rows = [$offense];
                $this->hydrateOffenseRows($rows);
                $offense = $rows[0];
            }
        }

        if (!$summary && $offense) {
            $summary = $this->findCharacterSummaryByGuid((int) ($offense['guid'] ?? 0));
        }

        if (!$summary && !$offense) {
            return null;
        }

        $recentEvents = [];
        if ($guid > 0) {
            $stmt = $this->characters()->prepare(
                'SELECT id, guid, account_id, map_id, zone_id, area_id, cheat_type, '
                . 'evidence_level, risk_delta, total_risk_after, evidence_tag, detail_text, '
                . 'pos_x, pos_y, pos_z, created_at '
                . 'FROM ac_aegis_event WHERE guid = :guid '
                . 'ORDER BY created_at DESC, id DESC LIMIT 20'
            );
            $stmt->execute([':guid' => $guid]);
            $recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->hydrateEventRows($recentEvents);
        }

        return [
            'player' => $summary,
            'offense' => $offense,
            'recent_events' => $recentEvents,
        ];
    }

    public function resolveCommandTarget(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $identifier)) {
            $summary = $this->findCharacterSummaryByGuid((int) $identifier);
            if ($summary) {
                return $summary;
            }
        }

        return $this->findCharacterSummaryByName($identifier);
    }

    public function recentLog(int $limit): array
    {
        $limit = max(20, min(300, $limit));
        $path = $this->resolveLogPath();
        if ($path === null || !is_file($path)) {
            return [
                'path' => null,
                'lines' => [],
            ];
        }

        return [
            'path' => $path,
            'lines' => $this->readTail($path, $limit),
        ];
    }

    private function hydrateOffenseRows(array &$rows): void
    {
        if (!$rows) {
            return;
        }

        $guids = [];
        $accountIds = [];
        foreach ($rows as $row) {
            $guid = (int) ($row['guid'] ?? 0);
            $accountId = (int) ($row['account_id'] ?? 0);
            if ($guid > 0) {
                $guids[] = $guid;
            }
            if ($accountId > 0) {
                $accountIds[] = $accountId;
            }
        }

        $charMap = $this->fetchCharacterMap($guids);
        $accountMap = $this->fetchAccountMap($accountIds);
        $eventCounts = $this->fetchRecentEventCounts($guids, 7);
        $now = time();

        foreach ($rows as &$row) {
            $guid = (int) ($row['guid'] ?? 0);
            $accountId = (int) ($row['account_id'] ?? 0);
            $row['guid'] = $guid;
            $row['account_id'] = $accountId;
            $row['offense_count'] = (int) ($row['offense_count'] ?? 0);
            $row['offense_tier'] = (int) ($row['offense_tier'] ?? 0);
            $row['punish_stage'] = (int) ($row['punish_stage'] ?? 0);
            $row['last_cheat_type'] = (int) ($row['last_cheat_type'] ?? 0);
            $row['last_offense_at'] = (int) ($row['last_offense_at'] ?? 0);
            $row['debuff_until'] = (int) ($row['debuff_until'] ?? 0);
            $row['jail_until'] = (int) ($row['jail_until'] ?? 0);
            $row['ban_until'] = (int) ($row['ban_until'] ?? 0);
            $row['permanent_ban'] = !empty($row['permanent_ban']);
            $row['player_name'] = $charMap[$guid]['name'] ?? ('#' . $guid);
            $row['player_level'] = (int) ($charMap[$guid]['level'] ?? 0);
            $row['player_class'] = (int) ($charMap[$guid]['class'] ?? 0);
            $row['player_online'] = !empty($charMap[$guid]['online']);
            $row['account_username'] = $accountMap[$accountId]['username'] ?? ('#' . $accountId);
            $row['recent_event_count'] = (int) ($eventCounts[$guid] ?? 0);
            $row['is_debuff_active'] = $row['debuff_until'] > $now;
            $row['is_jail_active'] = $row['jail_until'] > $now;
            $row['is_ban_active'] = $row['permanent_ban'] || $row['ban_until'] > $now;
        }
        unset($row);
    }

    private function hydrateEventRows(array &$rows): void
    {
        if (!$rows) {
            return;
        }

        $guids = [];
        $accountIds = [];
        foreach ($rows as $row) {
            $guid = (int) ($row['guid'] ?? 0);
            $accountId = (int) ($row['account_id'] ?? 0);
            if ($guid > 0) {
                $guids[] = $guid;
            }
            if ($accountId > 0) {
                $accountIds[] = $accountId;
            }
        }

        $charMap = $this->fetchCharacterMap($guids);
        $accountMap = $this->fetchAccountMap($accountIds);

        foreach ($rows as &$row) {
            $guid = (int) ($row['guid'] ?? 0);
            $accountId = (int) ($row['account_id'] ?? 0);
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['guid'] = $guid;
            $row['account_id'] = $accountId;
            $row['map_id'] = (int) ($row['map_id'] ?? 0);
            $row['zone_id'] = (int) ($row['zone_id'] ?? 0);
            $row['area_id'] = (int) ($row['area_id'] ?? 0);
            $row['cheat_type'] = (int) ($row['cheat_type'] ?? 0);
            $row['evidence_level'] = (int) ($row['evidence_level'] ?? 0);
            $row['risk_delta'] = (float) ($row['risk_delta'] ?? 0.0);
            $row['total_risk_after'] = (float) ($row['total_risk_after'] ?? 0.0);
            $row['pos_x'] = (float) ($row['pos_x'] ?? 0.0);
            $row['pos_y'] = (float) ($row['pos_y'] ?? 0.0);
            $row['pos_z'] = (float) ($row['pos_z'] ?? 0.0);
            $row['player_name'] = $charMap[$guid]['name'] ?? ('#' . $guid);
            $row['account_username'] = $accountMap[$accountId]['username'] ?? ('#' . $accountId);
        }
        unset($row);
    }

    private function applyIdentityFilter(string $guidColumn, string $accountColumn, string $query, array &$wheres, array &$params): void
    {
        $query = trim($query);
        if ($query === '') {
            return;
        }

        $match = $this->resolveIdentityMatches($query);
        $parts = [];

        if ($match['guids']) {
            $placeholders = [];
            foreach ($match['guids'] as $index => $guid) {
                $key = ':guid_q_' . $index;
                $params[$key] = $guid;
                $placeholders[] = $key;
            }
            $parts[] = $guidColumn . ' IN (' . implode(',', $placeholders) . ')';
        }

        if ($match['account_ids']) {
            $placeholders = [];
            foreach ($match['account_ids'] as $index => $accountId) {
                $key = ':acct_q_' . $index;
                $params[$key] = $accountId;
                $placeholders[] = $key;
            }
            $parts[] = $accountColumn . ' IN (' . implode(',', $placeholders) . ')';
        }

        if (preg_match('/^\d+$/', $query)) {
            $parts[] = $guidColumn . ' = :guid_exact';
            $params[':guid_exact'] = (int) $query;

            $parts[] = $accountColumn . ' = :account_exact';
            $params[':account_exact'] = (int) $query;
        }

        if ($parts) {
            $wheres[] = '(' . implode(' OR ', $parts) . ')';
        } else {
            $wheres[] = '1 = 0';
        }
    }

    private function resolveIdentityMatches(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['guids' => [], 'account_ids' => []];
        }

        $cacheKey = 'server_' . $this->serverId . '_identity_' . sha1(mb_strtolower($query, 'UTF-8'));

        $cached = $this->readCache->remember('identity', $cacheKey, 30, function () use ($query): array {
            $like = '%' . $query . '%';

            $guids = [];
            $stmt = $this->characters()->prepare('SELECT guid FROM characters WHERE name LIKE :name LIMIT 200');
            $stmt->execute([':name' => $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [] as $guid) {
                $guid = (int) $guid;
                if ($guid > 0) {
                    $guids[] = $guid;
                }
            }

            $accountIds = [];
            $stmt = $this->auth()->prepare('SELECT id FROM account WHERE username LIKE :name LIMIT 200');
            $stmt->execute([':name' => $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [] as $accountId) {
                $accountId = (int) $accountId;
                if ($accountId > 0) {
                    $accountIds[] = $accountId;
                }
            }

            return [
                'guids' => array_values(array_unique($guids)),
                'account_ids' => array_values(array_unique($accountIds)),
            ];
        });

        return is_array($cached) ? $cached : ['guids' => [], 'account_ids' => []];
    }

    private function fetchCharacterMap(array $guids): array
    {
        $guids = array_values(array_unique(array_filter(array_map('intval', $guids), static fn (int $v): bool => $v > 0)));
        if (!$guids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($guids), '?'));
        $stmt = $this->characters()->prepare(
            'SELECT guid, name, account, level, class, map, zone, online '
            . 'FROM characters WHERE guid IN (' . $placeholders . ')'
        );
        foreach ($guids as $index => $guid) {
            $stmt->bindValue($index + 1, $guid, PDO::PARAM_INT);
        }
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $guid = (int) ($row['guid'] ?? 0);
            if ($guid <= 0) {
                continue;
            }
            $map[$guid] = [
                'name' => (string) ($row['name'] ?? ''),
                'account' => (int) ($row['account'] ?? 0),
                'level' => (int) ($row['level'] ?? 0),
                'class' => (int) ($row['class'] ?? 0),
                'map' => (int) ($row['map'] ?? 0),
                'zone' => (int) ($row['zone'] ?? 0),
                'online' => (int) ($row['online'] ?? 0),
            ];
        }

        return $map;
    }

    private function fetchAccountMap(array $accountIds): array
    {
        $accountIds = array_values(array_unique(array_filter(array_map('intval', $accountIds), static fn (int $v): bool => $v > 0)));
        if (!$accountIds) {
            return [];
        }

        $map = [];
        $missing = [];
        foreach ($accountIds as $accountId) {
            $cacheKey = 'server_' . $this->serverId . '_account_' . $accountId;
            $cached = $this->readCache->get('accounts', $cacheKey);
            if (is_array($cached)) {
                $map[$accountId] = $cached;
            } else {
                $missing[] = $accountId;
            }
        }

        if ($missing) {
            $placeholders = implode(',', array_fill(0, count($missing), '?'));
            $stmt = $this->auth()->prepare(
                'SELECT id, username FROM account WHERE id IN (' . $placeholders . ')'
            );
            foreach ($missing as $index => $accountId) {
                $stmt->bindValue($index + 1, $accountId, PDO::PARAM_INT);
            }
            $stmt->execute();

            $found = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $accountId = (int) ($row['id'] ?? 0);
                if ($accountId <= 0) {
                    continue;
                }
                $payload = [
                    'username' => (string) ($row['username'] ?? ''),
                ];
                $map[$accountId] = $payload;
                $found[$accountId] = true;
                $this->readCache->set('accounts', 'server_' . $this->serverId . '_account_' . $accountId, $payload, 60);
            }

            foreach ($missing as $accountId) {
                if (!isset($found[$accountId])) {
                    $this->readCache->set('accounts', 'server_' . $this->serverId . '_account_' . $accountId, ['username' => ''], 60);
                }
            }
        }

        return $map;
    }

    private function fetchRecentEventCounts(array $guids, int $days): array
    {
        $guids = array_values(array_unique(array_filter(array_map('intval', $guids), static fn (int $v): bool => $v > 0)));
        if (!$guids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($guids), '?'));
        $sql = 'SELECT guid, COUNT(*) AS total FROM ac_aegis_event '
            . 'WHERE guid IN (' . $placeholders . ') '
            . 'AND created_at >= ' . $this->daysWindowSql($days) . ' '
            . 'GROUP BY guid';
        $stmt = $this->characters()->prepare($sql);
        foreach ($guids as $index => $guid) {
            $stmt->bindValue($index + 1, $guid, PDO::PARAM_INT);
        }
        $stmt->execute();

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $counts[(int) ($row['guid'] ?? 0)] = (int) ($row['total'] ?? 0);
        }

        return $counts;
    }

    private function findCharacterSummaryByGuid(int $guid): ?array
    {
        if ($guid <= 0) {
            return null;
        }

        $cacheKey = 'server_' . $this->serverId . '_guid_' . $guid;
        $row = $this->readCache->remember('character_summary', $cacheKey, 30, function () use ($guid) {
            $stmt = $this->characters()->prepare(
                'SELECT guid, name, account, level, class, race, map, zone, online '
                . 'FROM characters WHERE guid = :guid LIMIT 1'
            );
            $stmt->execute([':guid' => $guid]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        });

        if (!$row) {
            return null;
        }

        return $this->normalizeCharacterSummary($row);
    }

    private function findCharacterSummaryByName(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $cacheKey = 'server_' . $this->serverId . '_name_' . sha1(mb_strtolower($name, 'UTF-8'));
        $row = $this->readCache->remember('character_summary', $cacheKey, 30, function () use ($name) {
            $stmt = $this->characters()->prepare(
                'SELECT guid, name, account, level, class, race, map, zone, online '
                . 'FROM characters WHERE name = :name LIMIT 1'
            );
            $stmt->execute([':name' => $name]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        });

        if (!$row) {
            return null;
        }

        return $this->normalizeCharacterSummary($row);
    }

    private function normalizeCharacterSummary(array $row): array
    {
        $accountId = (int) ($row['account'] ?? 0);
        $accountMap = $this->fetchAccountMap([$accountId]);

        return [
            'guid' => (int) ($row['guid'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'account' => $accountId,
            'account_username' => $accountMap[$accountId]['username'] ?? ('#' . $accountId),
            'level' => (int) ($row['level'] ?? 0),
            'class' => (int) ($row['class'] ?? 0),
            'race' => (int) ($row['race'] ?? 0),
            'map' => (int) ($row['map'] ?? 0),
            'zone' => (int) ($row['zone'] ?? 0),
            'online' => !empty($row['online']),
        ];
    }

    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function daysWindowSql(int $days): string
    {
        $days = max(1, min(90, $days));
        return 'DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
    }

    private function resolveLogPath(): ?string
    {
        $panelRoot = dirname(__DIR__, 4);
        $workspaceRoot = dirname($panelRoot, 2);
        $candidates = [
            $workspaceRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'aegis.log',
            $workspaceRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'aegis.log',
            $workspaceRoot . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'aegis.log',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $glob = glob($workspaceRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'aegis.log');
        if (is_array($glob) && !empty($glob)) {
            return $glob[0];
        }

        return null;
    }

    private function readTail(string $file, int $limit): array
    {
        $size = @filesize($file);
        if ($size === false) {
            return [];
        }

        if ($size <= 1048576) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES);
            if (!$lines) {
                return [];
            }
            $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
            return array_slice($lines, -$limit);
        }

        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return [];
        }

        $buffer = '';
        $lines = [];
        $position = $size;

        while ($position > 0 && count($lines) < $limit) {
            $chunk = min(8192, $position);
            $position -= $chunk;
            fseek($fp, $position);
            $buffer = fread($fp, $chunk) . $buffer;
            $parts = explode("\n", $buffer);
            if ($position > 0) {
                $buffer = array_shift($parts);
            } else {
                $buffer = '';
            }

            for ($i = count($parts) - 1; $i >= 0; --$i) {
                $line = trim($parts[$i]);
                if ($line === '') {
                    continue;
                }
                array_unshift($lines, $line);
                if (count($lines) >= $limit) {
                    break 2;
                }
            }
        }

        fclose($fp);
        return $lines;
    }
}