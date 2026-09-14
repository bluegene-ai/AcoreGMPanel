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

        if ($stmt->rowCount() > 0)
            return true;

        // MySQL 在"新旧备注完全相同"时返回 0 affected rows，这不代表失败；
        // 再确认一次当前值，可同时覆盖"值未变化"与"行不存在"两种情况
        $check = $this->characters()->prepare(
            'SELECT comment FROM ' . $this->table('recruit_a_friend_links')
            . ' WHERE account_id = :account_id LIMIT 1'
        );
        $check->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $check->execute();

        $current = $check->fetchColumn();
        if ($current === false)
            return false;

        return trim((string) $current) === $comment;
    }

    /**
     * 奖励发放记录：由 RecruitAFriend.lua 在成功寄出奖励邮件时写入。
     * 每条记录回答"因哪次招募达标、何时、给谁发了什么奖励"。
     */
    public function listRewardLogs(array $filters, int $page, int $perPage): Paginator
    {
        $table = $this->table('recruit_a_friend_reward_log');
        $params = [];
        $where = $this->buildRewardLogWhere($filters, $params);

        $countStmt = $this->characters()->prepare(
            'SELECT COUNT(*) FROM ' . $table . ' ' . $where
        );
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        if ($total <= 0)
            return new Paginator([], 0, $page, $perPage);

        $orderBy = $this->rewardLogOrderBy(
            (string) ($filters['sort'] ?? 'granted_at'),
            (string) ($filters['dir'] ?? 'DESC')
        );
        $offset = max(0, ($page - 1) * $perPage);

        $sql = 'SELECT id, granted_at, recruiter_guid, recruiter_account, recruiter_realm, '
            . 'recruit_account_id, reward_level, target_level, reward_items, reward_money, '
            . 'reward_source, used_default, mail_subject '
            . 'FROM ' . $table . ' ' . $where
            . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->characters()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === [])
            return new Paginator([], 0, $page, $perPage);

        $this->hydrateRecruiters($rows);
        $this->hydrateRewardLogAccounts($rows);
        $this->hydrateRewardItems($rows);

        foreach ($rows as &$row) {
            $row = $this->normalizeRewardLogRow($row);
        }
        unset($row);

        return new Paginator($rows, $total, $page, $perPage);
    }

    public function rewardLogStats(array $filters = []): array
    {
        $params = [];
        $where = $this->buildRewardLogWhere($filters, $params);

        $sql = 'SELECT COUNT(*) AS total, '
            . 'COUNT(DISTINCT recruiter_guid) AS recruiters, '
            . 'COUNT(DISTINCT recruit_account_id) AS recruits, '
            . 'SUM(CASE WHEN used_default = 1 THEN 1 ELSE 0 END) AS default_rewards, '
            . 'MAX(granted_at) AS latest_granted_at '
            . 'FROM ' . $this->table('recruit_a_friend_reward_log') . ' ' . $where;

        $stmt = $this->characters()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'recruiters' => (int) ($row['recruiters'] ?? 0),
            'recruits' => (int) ($row['recruits'] ?? 0),
            'default_rewards' => (int) ($row['default_rewards'] ?? 0),
            'latest_granted_at' => (int) ($row['latest_granted_at'] ?? 0),
        ];
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

        // 奖励发放记录表由较新版本的 RecruitAFriend.lua 创建，缺失时只影响记录区块
        $missingRewardLogTables = [];
        if (!$this->tableExists('recruit_a_friend_reward_log'))
            $missingRewardLogTables[] = 'recruit_a_friend_reward_log';

        return [
            'ready' => $missingTables === [],
            'missing_tables' => $missingTables,
            'reward_log_ready' => $missingRewardLogTables === [],
            'missing_reward_log_tables' => $missingRewardLogTables,
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
            // 支持账号 ID、账号名，以及招募角色名
            $accountIds = $this->resolveSearchAccountIds($search);
            $guids = $this->resolveSearchCharacterGuids($search);
            $clauses = [];

            if ($accountIds !== []) {
                $placeholders = [];
                foreach (array_values($accountIds) as $index => $accountId) {
                    $placeholder = ':search_account_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $accountId;
                }
                $clauses[] = 'l.account_id IN (' . implode(', ', $placeholders) . ')';
            }

            if ($guids !== []) {
                $placeholders = [];
                foreach (array_values($guids) as $index => $guid) {
                    $placeholder = ':search_recruiter_guid_' . $index;
                    $placeholders[] = $placeholder;
                    $params[$placeholder] = $guid;
                }
                $clauses[] = 'l.recruiter_guid IN (' . implode(', ', $placeholders) . ')';
            }

            $where[] = $clauses === [] ? '1 = 0' : '(' . implode(' OR ', $clauses) . ')';
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

    private function buildRewardLogWhere(array $filters, array &$params): string
    {
        $where = [];
        $realmId = $this->currentRealmId();
        if ($realmId > 0) {
            $where[] = '(recruiter_realm = 0 OR recruiter_realm = :log_realm_id)';
            $params[':log_realm_id'] = $realmId;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            // 命中条件：被招募账号、招募者账号，或招募角色名
            $accountIds = $this->resolveSearchAccountIds($search);
            $guids = $this->resolveSearchCharacterGuids($search);
            $clauses = [];

            if ($accountIds !== []) {
                // 同一个值出现在两个 IN 中，必须使用不同的占位符（原生预处理不允许复用命名参数）
                $recruitPlaceholders = [];
                $recruiterPlaceholders = [];
                foreach (array_values($accountIds) as $index => $accountId) {
                    $recruitPlaceholder = ':log_recruit_account_' . $index;
                    $recruitPlaceholders[] = $recruitPlaceholder;
                    $params[$recruitPlaceholder] = $accountId;

                    $recruiterPlaceholder = ':log_recruiter_account_' . $index;
                    $recruiterPlaceholders[] = $recruiterPlaceholder;
                    $params[$recruiterPlaceholder] = $accountId;
                }

                $clauses[] = 'recruit_account_id IN (' . implode(', ', $recruitPlaceholders) . ')';
                $clauses[] = 'recruiter_account IN (' . implode(', ', $recruiterPlaceholders) . ')';
            }

            if ($guids !== []) {
                $guidPlaceholders = [];
                foreach (array_values($guids) as $index => $guid) {
                    $placeholder = ':log_guid_' . $index;
                    $guidPlaceholders[] = $placeholder;
                    $params[$placeholder] = $guid;
                }
                $clauses[] = 'recruiter_guid IN (' . implode(', ', $guidPlaceholders) . ')';
            }

            $where[] = $clauses === [] ? '1 = 0' : '(' . implode(' OR ', $clauses) . ')';
        }

        $level = (int) ($filters['level'] ?? 0);
        if ($level > 0) {
            $where[] = 'reward_level = :log_level';
            $params[':log_level'] = $level;
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if (in_array($source, ['login', 'level_change'], true)) {
            $where[] = 'reward_source = :log_source';
            $params[':log_source'] = $source;
        }

        $from = (int) ($filters['from'] ?? 0);
        if ($from > 0) {
            $where[] = 'granted_at >= :log_from';
            $params[':log_from'] = $from;
        }

        $to = (int) ($filters['to'] ?? 0);
        if ($to > 0) {
            $where[] = 'granted_at <= :log_to';
            $params[':log_to'] = $to;
        }

        if ($where === [])
            return '';

        return 'WHERE ' . implode(' AND ', $where);
    }

    private function rewardLogOrderBy(string $sort, string $dir): string
    {
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $map = [
            'granted_at' => 'granted_at',
            'reward_level' => 'reward_level',
            'recruiter_guid' => 'recruiter_guid',
            'recruit_account_id' => 'recruit_account_id',
            'id' => 'id',
        ];

        $column = $map[$sort] ?? $map['granted_at'];

        return $column . ' ' . $direction . ', id DESC';
    }

    private function normalizeRewardLogRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['granted_at'] = (int) ($row['granted_at'] ?? 0);
        $row['recruiter_guid'] = (int) ($row['recruiter_guid'] ?? 0);
        $row['recruiter_account'] = (int) ($row['recruiter_account'] ?? 0);
        $row['recruiter_realm'] = (int) ($row['recruiter_realm'] ?? 0);
        $row['recruit_account_id'] = (int) ($row['recruit_account_id'] ?? 0);
        $row['reward_level'] = (int) ($row['reward_level'] ?? 0);
        $row['target_level'] = (int) ($row['target_level'] ?? 0);
        $row['reward_money'] = (int) ($row['reward_money'] ?? 0);
        $row['used_default'] = (int) ($row['used_default'] ?? 0);
        $row['reward_source'] = trim((string) ($row['reward_source'] ?? ''));
        $row['mail_subject'] = trim((string) ($row['mail_subject'] ?? ''));
        $row['reward_items_raw'] = trim((string) ($row['reward_items'] ?? ''));

        if (!isset($row['reward_item_list']) || !is_array($row['reward_item_list'])) {
            $row['reward_item_list'] = $this->parseRewardItems($row['reward_items_raw']);
        }

        return $row;
    }

    /**
     * 解析 Lua 写入的 "itemId:count,itemId:count" 文本
     *
     * @return array<int, array{entry:int, count:int, name:string, quality:?int}>
     */
    private function parseRewardItems(string $raw): array
    {
        $items = [];

        foreach (explode(',', $raw) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '')
                continue;

            $parts = explode(':', $chunk);
            $entry = (int) ($parts[0] ?? 0);
            $count = (int) ($parts[1] ?? 0);

            if ($entry <= 0 || $count <= 0)
                continue;

            $items[] = [
                'entry' => $entry,
                'count' => $count,
                'name' => '',
                'quality' => null,
            ];
        }

        return $items;
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

    private function resolveSearchCharacterGuids(string $search): array
    {
        $guids = [];

        $stmt = $this->characters()->prepare(
            'SELECT guid FROM characters WHERE name LIKE :name LIMIT 200'
        );
        $stmt->bindValue(':name', '%' . $search . '%', PDO::PARAM_STR);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $value) {
            $guid = (int) $value;
            if ($guid > 0)
                $guids[$guid] = $guid;
        }

        return array_values($guids);
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

    private function hydrateRewardLogAccounts(array &$rows): void
    {
        $accountIds = [];
        foreach ($rows as $row) {
            foreach (['recruit_account_id', 'recruiter_account'] as $column) {
                $accountId = (int) ($row[$column] ?? 0);
                if ($accountId > 0)
                    $accountIds[$accountId] = $accountId;
            }
        }

        if ($accountIds === [])
            return;

        $placeholders = [];
        $params = [];
        foreach (array_values($accountIds) as $index => $accountId) {
            $placeholder = ':log_account_' . $index;
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
            $recruitAccountId = (int) ($row['recruit_account_id'] ?? 0);
            $recruiterAccount = (int) ($row['recruiter_account'] ?? 0);
            $row['recruit_account_username'] = $map[$recruitAccountId] ?? '';
            $row['recruiter_account_username'] = $map[$recruiterAccount] ?? '';
        }
        unset($row);
    }

    /**
     * 为 reward_items 中的物品补充名称与品质；物品库不可用时只显示 ID。
     */
    private function hydrateRewardItems(array &$rows): void
    {
        $entries = [];
        foreach ($rows as $row) {
            foreach ($this->parseRewardItems((string) ($row['reward_items'] ?? '')) as $item) {
                $entries[$item['entry']] = $item['entry'];
            }
        }

        $names = [];
        $qualities = [];

        if ($entries !== []) {
            $ids = array_values($entries);
            try {
                $placeholders = implode(', ', array_fill(0, count($ids), '?'));
                $sql = 'SELECT i.entry, i.name, i.Quality, '
                    . 'COALESCE(li.name_loc4, li.name_loc8, li.name_loc6, li.name_loc5) AS name_localized '
                    . 'FROM item_template i LEFT JOIN locales_item li ON li.entry = i.entry '
                    . 'WHERE i.entry IN (' . $placeholders . ')';

                $stmt = $this->world()->prepare($sql);
                foreach ($ids as $index => $value) {
                    $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
                }
                $stmt->execute();

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $itemRow) {
                    $entry = (int) ($itemRow['entry'] ?? 0);
                    if ($entry <= 0)
                        continue;

                    $localized = trim((string) ($itemRow['name_localized'] ?? ''));
                    $fallback = trim((string) ($itemRow['name'] ?? ''));
                    $names[$entry] = $localized !== '' ? $localized : $fallback;
                    $qualities[$entry] = (int) ($itemRow['Quality'] ?? 0);
                }
            } catch (\Throwable $exception) {
                // 物品库查询失败不应影响记录本身的展示
            }
        }

        foreach ($rows as &$row) {
            $items = $this->parseRewardItems((string) ($row['reward_items'] ?? ''));
            foreach ($items as &$item) {
                $entry = $item['entry'];
                $item['name'] = $names[$entry] ?? '';
                $item['quality'] = $qualities[$entry] ?? null;
            }
            unset($item);
            $row['reward_item_list'] = $items;
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