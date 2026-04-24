<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Raf;

use Acme\Panel\Core\Config;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Support\Paginator;
use Acme\Panel\Support\ServerContext;
use PDO;

class RafRepository extends MultiServerRepository
{
    private string $customDbName;
    private int $permanentBlockThreshold;
    private ?array $tableAvailability = null;

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);

        $this->customDbName = (string) Config::get('raf.custom_db_name', 'ac_eluna');
        $this->permanentBlockThreshold = (int) Config::get(
            'raf.permanent_block_threshold',
            5
        );
    }

    public function listLinks(array $filters, int $page, int $perPage): Paginator
    {
        $params = [];
        $where = $this->buildWhere($filters, $params);
        $joinRewards = ' LEFT JOIN ' . $this->table('recruit_a_friend_rewards')
            . ' r ON r.recruiter_guid = l.recruiter_guid';

        $countSql = 'SELECT COUNT(*) FROM ' . $this->table('recruit_a_friend_links')
            . ' l' . $joinRewards . ' ' . $where;
        $countStmt = $this->characters()->prepare($countSql);
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        if ($total <= 0)
            return new Paginator([], 0, $page, $perPage);

        $orderBy = $this->orderBy(
            (string) ($filters['sort'] ?? 'time_stamp'),
            (string) ($filters['dir'] ?? 'DESC')
        );
        $offset = max(0, ($page - 1) * $perPage);

        $sql = 'SELECT '
            . 'l.account_id, l.recruiter_guid, l.recruiter_realm, l.time_stamp, '
            . 'l.ip_abuse_counter, l.kick_counter, l.complete, l.comment, '
            . 'COALESCE(r.reward_level, 0) AS reward_level '
            . 'FROM ' . $this->table('recruit_a_friend_links') . ' l'
            . $joinRewards . ' ' . $where
            . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->characters()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === [])
            return new Paginator([], 0, $page, $perPage);

        $this->hydrateAccounts($rows);
        $this->hydrateRecruiters($rows);

        foreach ($rows as &$row) {
            $row = $this->normalizeRow($row);
        }
        unset($row);

        return new Paginator($rows, $total, $page, $perPage);
    }

    public function stats(array $filters): array
    {
        $params = [];
        $where = $this->buildWhere($filters, $params);
        $threshold = $this->permanentBlockThreshold;

        $sql = 'SELECT '
            . 'COUNT(*) AS total, '
            . 'SUM(CASE WHEN l.complete = 1 THEN 1 ELSE 0 END) AS completed, '
            . 'SUM(CASE WHEN l.complete = 0 AND l.time_stamp > 0 '
            . 'AND l.ip_abuse_counter <= :threshold_active THEN 1 ELSE 0 END) '
            . 'AS active, '
            . 'SUM(CASE WHEN l.complete = 0 AND l.time_stamp <= 0 '
            . 'AND l.ip_abuse_counter <= :threshold_inactive THEN 1 ELSE 0 END) '
            . 'AS inactive, '
            . 'SUM(CASE WHEN l.ip_abuse_counter > :threshold_permanent '
            . 'THEN 1 ELSE 0 END) AS permanent_blocked, '
            . 'SUM(CASE WHEN COALESCE(r.reward_level, 0) > 0 THEN 1 ELSE 0 END) '
            . 'AS rewarded_accounts '
            . 'FROM ' . $this->table('recruit_a_friend_links') . ' l '
            . 'LEFT JOIN ' . $this->table('recruit_a_friend_rewards')
            . ' r ON r.recruiter_guid = l.recruiter_guid '
            . $where;

        $stmt = $this->characters()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->bindValue(':threshold_active', $threshold, PDO::PARAM_INT);
        $stmt->bindValue(':threshold_inactive', $threshold, PDO::PARAM_INT);
        $stmt->bindValue(':threshold_permanent', $threshold, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
            'permanent_blocked' => (int) ($row['permanent_blocked'] ?? 0),
            'rewarded_accounts' => (int) ($row['rewarded_accounts'] ?? 0),
        ];
    }

    public function findAccountSummary(int $accountId): ?array
    {
        $stmt = $this->auth()->prepare(
            'SELECT id, username, email FROM account WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $accountId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row))
            return null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'username' => (string) ($row['username'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
        ];
    }

    public function findRecruiterCharacter(int $guid): ?array
    {
        $stmt = $this->characters()->prepare(
            'SELECT guid, name, account FROM characters WHERE guid = :guid LIMIT 1'
        );
        $stmt->bindValue(':guid', $guid, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row))
            return null;

        return [
            'guid' => (int) ($row['guid'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'account_id' => (int) ($row['account'] ?? 0),
        ];
    }

    public function findLink(int $accountId): ?array
    {
        $stmt = $this->characters()->prepare(
            'SELECT '
            . 'l.account_id, l.recruiter_guid, l.recruiter_realm, l.time_stamp, '
            . 'l.ip_abuse_counter, l.kick_counter, l.complete, l.comment, '
            . 'COALESCE(r.reward_level, 0) AS reward_level '
            . 'FROM ' . $this->table('recruit_a_friend_links') . ' l '
            . 'LEFT JOIN ' . $this->table('recruit_a_friend_rewards')
            . ' r ON r.recruiter_guid = l.recruiter_guid '
            . 'WHERE l.account_id = :account_id LIMIT 1'
        );
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row))
            return null;

        $rows = [$row];
        $this->hydrateAccounts($rows);
        $this->hydrateRecruiters($rows);

        return $this->normalizeRow($rows[0]);
    }

    public function updateComment(int $accountId, string $comment): bool
    {
        $stmt = $this->characters()->prepare(
            'UPDATE ' . $this->table('recruit_a_friend_links')
            . ' SET comment = :comment WHERE account_id = :account_id'
        );
        $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function currentRealmId(): int
    {
        $cfg = ServerContext::server($this->serverId);

        return (int) ($cfg['realm_id'] ?? 0);
    }

    public function schemaStatus(): array
    {
        $missingTables = [];

        foreach (['recruit_a_friend_links', 'recruit_a_friend_rewards'] as $table) {
            if (!$this->tableExists($table))
                $missingTables[] = $table;
        }

        return [
            'ready' => $missingTables === [],
            'missing_tables' => $missingTables,
        ];
    }

    private function normalizeRow(array $row): array
    {
        $row['account_id'] = (int) ($row['account_id'] ?? 0);
        $row['recruiter_guid'] = (int) ($row['recruiter_guid'] ?? 0);
        $row['recruiter_realm'] = (int) ($row['recruiter_realm'] ?? 0);
        $row['time_stamp'] = (int) ($row['time_stamp'] ?? 0);
        $row['ip_abuse_counter'] = (int) ($row['ip_abuse_counter'] ?? 0);
        $row['kick_counter'] = (int) ($row['kick_counter'] ?? 0);
        $row['complete'] = (int) ($row['complete'] ?? 0);
        $row['reward_level'] = (int) ($row['reward_level'] ?? 0);
        $row['comment'] = trim((string) ($row['comment'] ?? ''));
        $row['status_key'] = $this->statusKey($row);

        return $row;
    }

    private function statusKey(array $row): string
    {
        if ((int) ($row['ip_abuse_counter'] ?? 0) > $this->permanentBlockThreshold)
            return 'permanent_blocked';

        if ((int) ($row['complete'] ?? 0) === 1)
            return 'completed';

        if ((int) ($row['time_stamp'] ?? 0) <= 0)
            return 'inactive';

        return 'active';
    }

    private function buildWhere(array $filters, array &$params): string
    {
        $where = [];
        $realmId = $this->currentRealmId();
        if ($realmId > 0) {
            $where[] = '(l.recruiter_realm = 0 OR l.recruiter_realm = :realm_id)';
            $params[':realm_id'] = $realmId;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $accountIds = $this->resolveSearchAccountIds($search);
            if ($accountIds === []) {
                $where[] = '1 = 0';
            } else {
                $placeholders = [];
                foreach ($accountIds as $index => $accountId) {
                    $placeholder = ':search_account_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $accountId;
                }
                $where[] = 'l.account_id IN (' . implode(', ', $placeholders) . ')';
            }
        }

        $recruiterGuid = (int) ($filters['recruiter_guid'] ?? 0);
        if ($recruiterGuid > 0) {
            $where[] = 'l.recruiter_guid = :recruiter_guid';
            $params[':recruiter_guid'] = $recruiterGuid;
        }

        switch ((string) ($filters['status'] ?? 'all')) {
            case 'active':
                $where[] = 'l.complete = 0';
                $where[] = 'l.time_stamp > 0';
                $where[] = 'l.ip_abuse_counter <= :status_active_threshold';
                $params[':status_active_threshold'] = $this->permanentBlockThreshold;
                break;

            case 'completed':
                $where[] = 'l.complete = 1';
                break;

            case 'inactive':
                $where[] = 'l.complete = 0';
                $where[] = 'l.time_stamp <= 0';
                $where[] = 'l.ip_abuse_counter <= :status_inactive_threshold';
                $params[':status_inactive_threshold'] = $this->permanentBlockThreshold;
                break;

            case 'permanent':
                $where[] = 'l.ip_abuse_counter > :status_permanent_threshold';
                $params[':status_permanent_threshold']
                    = $this->permanentBlockThreshold;
                break;
        }

        if ($where === [])
            return '';

        return 'WHERE ' . implode(' AND ', $where);
    }

    private function resolveSearchAccountIds(string $search): array
    {
        $ids = [];

        if (preg_match('/^\d+$/', $search)) {
            $accountId = (int) $search;
            if ($accountId > 0)
                $ids[$accountId] = $accountId;
        }

        $stmt = $this->auth()->prepare(
            'SELECT id FROM account WHERE username LIKE :username LIMIT 200'
        );
        $stmt->bindValue(':username', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $value) {
            $accountId = (int) $value;
            if ($accountId > 0)
                $ids[$accountId] = $accountId;
        }

        return array_values($ids);
    }

    private function hydrateAccounts(array &$rows): void
    {
        $accountIds = [];
        foreach ($rows as $row) {
            $accountId = (int) ($row['account_id'] ?? 0);
            if ($accountId > 0)
                $accountIds[$accountId] = $accountId;
        }

        if ($accountIds === [])
            return;

        $placeholders = [];
        $params = [];
        foreach (array_values($accountIds) as $index => $accountId) {
            $placeholder = ':account_id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $accountId;
        }

        $stmt = $this->auth()->prepare(
            'SELECT id, username FROM account WHERE id IN ('
            . implode(', ', $placeholders) . ')'
        );
        $this->bindAll($stmt, $params);
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int) ($row['id'] ?? 0)] = (string) ($row['username'] ?? '');
        }

        foreach ($rows as &$row) {
            $accountId = (int) ($row['account_id'] ?? 0);
            $row['account_username'] = $map[$accountId] ?? '';
        }
        unset($row);
    }

    private function hydrateRecruiters(array &$rows): void
    {
        $guids = [];
        foreach ($rows as $row) {
            $guid = (int) ($row['recruiter_guid'] ?? 0);
            if ($guid > 0)
                $guids[$guid] = $guid;
        }

        if ($guids === [])
            return;

        $placeholders = [];
        $params = [];
        foreach (array_values($guids) as $index => $guid) {
            $placeholder = ':recruiter_guid_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $guid;
        }

        $stmt = $this->characters()->prepare(
            'SELECT guid, name, account FROM characters WHERE guid IN ('
            . implode(', ', $placeholders) . ')'
        );
        $this->bindAll($stmt, $params);
        $stmt->execute();

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(int) ($row['guid'] ?? 0)] = [
                'name' => (string) ($row['name'] ?? ''),
                'account_id' => (int) ($row['account'] ?? 0),
            ];
        }

        foreach ($rows as &$row) {
            $guid = (int) ($row['recruiter_guid'] ?? 0);
            $meta = $map[$guid] ?? null;
            $row['recruiter_name'] = is_array($meta)
                ? (string) ($meta['name'] ?? '')
                : '';
            $row['recruiter_account_id'] = is_array($meta)
                ? (int) ($meta['account_id'] ?? 0)
                : 0;
        }
        unset($row);
    }

    private function orderBy(string $sort, string $dir): string
    {
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $map = [
            'account_id' => 'l.account_id',
            'recruiter_guid' => 'l.recruiter_guid',
            'time_stamp' => 'l.time_stamp',
            'ip_abuse_counter' => 'l.ip_abuse_counter',
            'kick_counter' => 'l.kick_counter',
            'reward_level' => 'COALESCE(r.reward_level, 0)',
        ];

        $column = $map[$sort] ?? $map['time_stamp'];

        return $column . ' ' . $direction . ', l.account_id DESC';
    }

    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(
                $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    private function table(string $table): string
    {
        return '`' . $this->customDbName . '`.`' . $table . '`';
    }

    private function tableExists(string $table): bool
    {
        if ($this->tableAvailability !== null && array_key_exists($table, $this->tableAvailability))
            return $this->tableAvailability[$table];

        $stmt = $this->characters()->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table LIMIT 1'
        );
        $stmt->bindValue(':schema', $this->customDbName, PDO::PARAM_STR);
        $stmt->bindValue(':table', $table, PDO::PARAM_STR);
        $stmt->execute();

        $exists = $stmt->fetchColumn() !== false;
        $this->tableAvailability ??= [];
        $this->tableAvailability[$table] = $exists;

        return $exists;
    }
}