<?php
/**
 * File: app/Domain/Boss/RewardPoolRepository.php
 * Purpose: 活动 Boss 奖池表（ac_eluna.boss_reward_pools）的读写：列表 / 新建 / 修改 /
 * 软删除 / 排序。库名与 state_key 复用 BossRepository 的当前区解析。
 *
 * 位图契约（见 docs/reward-pools-v2-plan.md §3）：pool_id = k 对应贡献表
 * reward_pools_mask 的第 k-1 位。所以 pool_id 一经分配**永不复用**（软删除保留行与位号），
 * 新增池取当前最大值 +1，越过上限时拒绝创建而不是静默截断。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Core\Lang;
use InvalidArgumentException;
use PDO;
use Throwable;

class RewardPoolRepository extends BossRepository
{
    /**
     * 位号上限 = 31：reward_pools_mask 是有符号 INT，第 32 位（1 << 31）会溢出成负数，
     * 所以第 32 个池拒绝创建。
     */
    public const MAX_POOL_ID = 31;

    public const TABLE = 'boss_reward_pools';

    /** 新建池的默认序号步长（sort_order 与 pool_id 解耦，这里只给一个不重叠的初值）。 */
    private const SORT_STEP = 10;

    private const ITEMS_MAX_LENGTH = 4000;
    private const NAME_MAX_LENGTH = 120;

    private ?array $labelCache = null;

    // 表是否已由 boss.lua 建好；缺表时页面显示提示而不是抛异常。

    public function available(): bool
    {
        return $this->tableExists(self::TABLE);
    }

    /**
     * 本区奖池行，按 sort_order, pool_id 升序（= 脚本发放顺序）。
     *
     * @param bool $includeDeleted true 时连软删除行一起返回（面板要展示 #id（已删除））
     * @return array<int,array<string,mixed>>
     */
    public function listPools(bool $includeDeleted = true): array
    {
        if (!$this->available())
            return [];

        $sql = 'SELECT ' . $this->columnList()
            . ' FROM ' . $this->table(self::TABLE)
            . ' WHERE state_key = :state_key';

        if (!$includeDeleted)
            $sql .= ' AND deleted_at = 0';

        $sql .= ' ORDER BY sort_order ASC, pool_id ASC';

        try {
            $stmt = $this->characters()->prepare($sql);
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->execute();

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rows[] = $this->normalizeRow($row);
            }

            return $rows;
        } catch (Throwable $exception) {
            $this->logWarning('reward_pools_list_failed', $exception);

            return [];
        }
    }

    /** 单个池（含软删除行）；不存在返回 null。 */
    public function find(int $poolId): ?array
    {
        if (!$this->available() || $poolId <= 0)
            return null;

        try {
            $stmt = $this->characters()->prepare(
                'SELECT ' . $this->columnList()
                . ' FROM ' . $this->table(self::TABLE)
                . ' WHERE state_key = :state_key AND pool_id = :pool_id LIMIT 1'
            );
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->bindValue(':pool_id', $poolId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $this->normalizeRow($row) : null;
        } catch (Throwable $exception) {
            $this->logWarning('reward_pools_find_failed', $exception);

            return null;
        }
    }

    /**
     * 位号 → 展示名（贡献表徽章、页面标题用）。
     *
     * 软删除的池保留原名但标「已删除」，位图里出现库里没有的位号时退回 `#id`。
     *
     * @return array<int,string>
     */
    public function displayNames(): array
    {
        if ($this->labelCache !== null)
            return $this->labelCache;

        $labels = [];
        foreach ($this->listPools(true) as $pool) {
            $name = trim((string) $pool['name']);
            $poolId = (int) $pool['pool_id'];
            $suffix = (int) $pool['deleted_at'] > 0
                ? Lang::get('app.boss.pools.deleted_suffix')
                : '';

            $labels[$poolId] = ($name !== '' ? $name : '#' . $poolId) . $suffix;
        }

        return $this->labelCache = $labels;
    }

    /** 下一个可用位号（= 当前最大 pool_id + 1，含软删除行）；已达 32 时返回 0。 */
    public function nextPoolId(): int
    {
        if (!$this->available())
            return 1;

        try {
            $stmt = $this->characters()->prepare(
                'SELECT MAX(pool_id) FROM ' . $this->table(self::TABLE)
                . ' WHERE state_key = :state_key'
            );
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->execute();
            $max = (int) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            $this->logWarning('reward_pools_max_failed', $exception);

            return 0;
        }

        $next = $max + 1;

        return $next > self::MAX_POOL_ID ? 0 : $next;
    }

    /**
     * 新建奖池：位号 = 当前最大值 + 1（不复用），序号默认 pool_id * 10。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed> 落库后的整行
     */
    public function create(array $data): array
    {
        if (!$this->available())
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.table_missing'));

        $poolId = $this->nextPoolId();
        if ($poolId === 0) {
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.pool_limit', [
                'max' => (string) self::MAX_POOL_ID,
            ]));
        }

        $pool = $this->validate($data, [
            'pool_id' => $poolId,
            'name' => '',
            'enabled' => 1,
            'chance' => 100,
            'winner_mode' => 'count',
            'winner_count' => 1,
            'class_filter' => 1,
            'items_text' => '',
            'gold_min_copper' => 0,
            'gold_max_copper' => 0,
            'announce' => 1,
        ]);
        $pool['sort_order'] = (int) ($data['sort_order'] ?? ($poolId * self::SORT_STEP));
        $pool['pool_id'] = $poolId;

        $this->writeRow($pool, true);
        $this->labelCache = null;

        return $this->find($poolId) ?? $pool;
    }

    /**
     * 修改奖池：只覆盖提交的字段（未提交的保持数据库现值）。软删除行不可改。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function update(int $poolId, array $data): array
    {
        $current = $this->find($poolId);
        if ($current === null)
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.not_found', [
                'pool' => (string) $poolId,
            ]));

        if ((int) $current['deleted_at'] > 0) {
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.deleted_readonly', [
                'pool' => (string) $poolId,
            ]));
        }

        $merged = $current;
        $submitted = [];
        foreach (self::EDITABLE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $submitted[$field] = $data[$field];
            }
        }
        if (array_key_exists('sort_order', $data)) {
            $submitted['sort_order'] = $data['sort_order'];
        }

        $pool = $this->validate($submitted, $merged);
        $pool['pool_id'] = $poolId;
        $pool['sort_order'] = (int) ($submitted['sort_order'] ?? $current['sort_order']);

        $this->writeRow($pool, false);
        $this->labelCache = null;

        return $this->find($poolId) ?? $pool;
    }

    /**
     * 软删除：置 deleted_at 并关闭开关；行与 pool_id 保留（历史位图仍可解释）。
     */
    public function softDelete(int $poolId): bool
    {
        $current = $this->find($poolId);
        if ($current === null || (int) $current['deleted_at'] > 0)
            return false;

        try {
            $stmt = $this->characters()->prepare(
                'UPDATE ' . $this->table(self::TABLE)
                . ' SET deleted_at = :deleted_at, enabled = 0, updated_at = :updated_at'
                . ' WHERE state_key = :state_key AND pool_id = :pool_id'
            );
            $stmt->bindValue(':deleted_at', time(), PDO::PARAM_INT);
            $stmt->bindValue(':updated_at', time(), PDO::PARAM_INT);
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->bindValue(':pool_id', $poolId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $exception) {
            $this->logWarning('reward_pools_delete_failed', $exception);

            return false;
        }

        $this->labelCache = null;

        return true;
    }

    /**
     * 重排：按传入的位号顺序重写 sort_order（步长 10）。位号本身不动。
     *
     * @param array<int,int|string> $order 期望的发放顺序，元素是 pool_id
     */
    public function reorder(array $order): void
    {
        if (!$this->available())
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.table_missing'));

        $ids = [];
        foreach ($order as $value) {
            $poolId = (int) $value;
            if ($poolId <= 0 || isset($ids[$poolId]))
                continue;
            $ids[$poolId] = true;
        }

        if ($ids === [])
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.reorder_empty'));

        $existing = [];
        foreach ($this->listPools(false) as $pool) {
            $existing[(int) $pool['pool_id']] = true;
        }

        foreach (array_keys($ids) as $poolId) {
            if (!isset($existing[$poolId])) {
                throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.not_found', [
                    'pool' => (string) $poolId,
                ]));
            }
        }

        $position = 0;
        try {
            $stmt = $this->characters()->prepare(
                'UPDATE ' . $this->table(self::TABLE)
                . ' SET sort_order = :sort_order, updated_at = :updated_at'
                . ' WHERE state_key = :state_key AND pool_id = :pool_id'
            );

            foreach (array_keys($ids) as $poolId) {
                $position++;
                $stmt->bindValue(':sort_order', $position * self::SORT_STEP, PDO::PARAM_INT);
                $stmt->bindValue(':updated_at', time(), PDO::PARAM_INT);
                $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
                $stmt->bindValue(':pool_id', $poolId, PDO::PARAM_INT);
                $stmt->execute();
            }
        } catch (Throwable $exception) {
            $this->logWarning('reward_pools_reorder_failed', $exception);

            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.reorder_failed'));
        }
    }

    /**
     * 跨区复制：按源区行原样 upsert（**保持 pool_id**，位号与历史快照一致）。
     *
     * @param array<int,array<string,mixed>> $rows
     * @return int 实际写入的行数
     */
    public function importRows(array $rows): int
    {
        if (!$this->available() || $rows === [])
            return 0;

        $written = 0;
        foreach ($rows as $row) {
            if (!is_array($row))
                continue;

            $poolId = (int) ($row['pool_id'] ?? 0);
            if ($poolId <= 0 || $poolId > self::MAX_POOL_ID)
                continue;

            try {
                $this->writeRow([
                    'pool_id' => $poolId,
                    'sort_order' => (int) ($row['sort_order'] ?? ($poolId * self::SORT_STEP)),
                    'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, self::NAME_MAX_LENGTH),
                    'enabled' => (int) ($row['enabled'] ?? 0) === 1 ? 1 : 0,
                    'chance' => $this->clamp((int) ($row['chance'] ?? 0), 0, 100),
                    'winner_mode' => (string) ($row['winner_mode'] ?? 'count') === 'all' ? 'all' : 'count',
                    'winner_count' => $this->clamp((int) ($row['winner_count'] ?? 1), 1, 100),
                    'class_filter' => (int) ($row['class_filter'] ?? 0) === 1 ? 1 : 0,
                    'items_text' => mb_substr((string) ($row['items_text'] ?? ''), 0, self::ITEMS_MAX_LENGTH),
                    'gold_min_copper' => max(0, (int) ($row['gold_min_copper'] ?? 0)),
                    'gold_max_copper' => max(0, (int) ($row['gold_max_copper'] ?? 0)),
                    'announce' => (int) ($row['announce'] ?? 0) === 1 ? 1 : 0,
                    'deleted_at' => max(0, (int) ($row['deleted_at'] ?? 0)),
                ], false);
                $written++;
            } catch (Throwable $exception) {
                $this->logWarning('reward_pools_import_failed', $exception);
            }
        }

        $this->labelCache = null;

        return $written;
    }

    /** 可编辑字段（pool_id / state_key / deleted_at 不在其中）。 */
    private const EDITABLE_FIELDS = [
        'name', 'enabled', 'chance', 'winner_mode', 'winner_count', 'class_filter',
        'items_text', 'gold_min_copper', 'gold_max_copper', 'announce',
    ];

    /**
     * 校验并归一：只认 EDITABLE_FIELDS + sort_order，缺项用 $base 补齐。
     * 违规直接抛 InvalidArgumentException（文案已本地化，控制器原样返回给用户）。
     *
     * @param array<string,mixed> $data 本次提交的字段
     * @param array<string,mixed> $base 缺项兜底（新建时是默认值，修改时是数据库现值）
     * @return array<string,mixed>
     */
    private function validate(array $data, array $base): array
    {
        $merged = $base;
        foreach ($data as $key => $value) {
            $merged[$key] = $value;
        }

        $mode = strtolower(trim((string) ($merged['winner_mode'] ?? 'count')));
        if (!in_array($mode, ['all', 'count'], true)) {
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.winner_mode'));
        }

        $chance = (int) ($merged['chance'] ?? 0);
        if ($chance < 0 || $chance > 100) {
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.chance'));
        }

        $winnerCount = (int) ($merged['winner_count'] ?? 1);
        if ($winnerCount < 1 || $winnerCount > 100) {
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.winner_count'));
        }

        $goldMin = (int) ($merged['gold_min_copper'] ?? 0);
        $goldMax = (int) ($merged['gold_max_copper'] ?? 0);
        if ($goldMin < 0 || $goldMax < 0 || $goldMax < $goldMin) {
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.gold_range'));
        }

        $items = trim((string) ($merged['items_text'] ?? ''));
        if (mb_strlen($items) > self::ITEMS_MAX_LENGTH) {
            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.items_too_long', [
                'max' => (string) self::ITEMS_MAX_LENGTH,
            ]));
        }

        return [
            'name' => mb_substr(trim((string) ($merged['name'] ?? '')), 0, self::NAME_MAX_LENGTH),
            'enabled' => $this->toFlag($merged['enabled'] ?? 0),
            'chance' => $chance,
            'winner_mode' => $mode,
            'winner_count' => $winnerCount,
            'class_filter' => $this->toFlag($merged['class_filter'] ?? 0),
            'items_text' => $items,
            'gold_min_copper' => $goldMin,
            'gold_max_copper' => $goldMax,
            'announce' => $this->toFlag($merged['announce'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $pool */
    private function writeRow(array $pool, bool $insert): void
    {
        $poolId = (int) $pool['pool_id'];
        $columns = [
            'sort_order' => (int) ($pool['sort_order'] ?? 0),
            'name' => (string) ($pool['name'] ?? ''),
            'enabled' => (int) ($pool['enabled'] ?? 0),
            'chance' => (int) ($pool['chance'] ?? 0),
            'winner_mode' => (string) ($pool['winner_mode'] ?? 'count'),
            'winner_count' => (int) ($pool['winner_count'] ?? 1),
            'class_filter' => (int) ($pool['class_filter'] ?? 0),
            'items_text' => (string) ($pool['items_text'] ?? ''),
            'gold_min_copper' => (int) ($pool['gold_min_copper'] ?? 0),
            'gold_max_copper' => (int) ($pool['gold_max_copper'] ?? 0),
            'announce' => (int) ($pool['announce'] ?? 0),
        ];

        if ($insert) {
            $columns['deleted_at'] = max(0, (int) ($pool['deleted_at'] ?? 0));
        }

        $quoted = [];
        $placeholders = [];
        $updates = [];
        foreach (array_keys($columns) as $column) {
            $quoted[] = '`' . $column . '`';
            $placeholders[] = ':' . $column;
            $updates[] = '`' . $column . '` = VALUES(`' . $column . '`)';
        }

        try {
            $stmt = $this->characters()->prepare(
                'INSERT INTO ' . $this->table(self::TABLE)
                . ' (state_key, pool_id, ' . implode(', ', $quoted) . ', updated_at)'
                . ' VALUES (:state_key, :pool_id, ' . implode(', ', $placeholders) . ', :updated_at)'
                . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates) . ', updated_at = VALUES(updated_at)'
            );

            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->bindValue(':pool_id', $poolId, PDO::PARAM_INT);
            $stmt->bindValue(':updated_at', time(), PDO::PARAM_INT);

            foreach ($columns as $column => $value) {
                $stmt->bindValue(
                    ':' . $column,
                    in_array($column, ['name', 'winner_mode', 'items_text'], true) ? (string) $value : (int) $value,
                    in_array($column, ['name', 'winner_mode', 'items_text'], true) ? PDO::PARAM_STR : PDO::PARAM_INT
                );
            }

            $stmt->execute();
        } catch (Throwable $exception) {
            $this->logWarning('reward_pools_write_failed', $exception);

            throw new InvalidArgumentException(Lang::get('app.boss.pools.errors.write_failed'));
        }
    }

    private function columnList(): string
    {
        return 'pool_id, sort_order, name, enabled, chance, winner_mode, winner_count,'
            . ' class_filter, items_text, gold_min_copper, gold_max_copper, announce,'
            . ' deleted_at, updated_at';
    }

    /** @return array<string,mixed> */
    private function normalizeRow(array $row): array
    {
        return [
            'pool_id' => (int) ($row['pool_id'] ?? 0),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'enabled' => (int) ($row['enabled'] ?? 0),
            'chance' => (int) ($row['chance'] ?? 0),
            'winner_mode' => (string) ($row['winner_mode'] ?? 'count'),
            'winner_count' => (int) ($row['winner_count'] ?? 1),
            'class_filter' => (int) ($row['class_filter'] ?? 0),
            'items_text' => (string) ($row['items_text'] ?? ''),
            'gold_min_copper' => (int) ($row['gold_min_copper'] ?? 0),
            'gold_max_copper' => (int) ($row['gold_max_copper'] ?? 0),
            'announce' => (int) ($row['announce'] ?? 0),
            'deleted_at' => (int) ($row['deleted_at'] ?? 0),
            'updated_at' => (int) ($row['updated_at'] ?? 0),
        ];
    }

    private function toFlag(mixed $value): int
    {
        if (is_bool($value))
            return $value ? 1 : 0;

        if (is_string($value) && in_array(strtolower(trim($value)), ['', '0', 'false', 'off'], true))
            return 0;

        return ((int) $value) === 1 ? 1 : 0;
    }

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
