<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Support\LogPath;
use Acme\Panel\Support\ServerContext;
use PDO;
use PDOStatement;
use Throwable;

class BossRepository extends MultiServerRepository
{
    /**
     * config 表的原始列清单，loadConfig / loadConfigStorageRow 共用。
     */
    private const CONFIG_COLUMNS = [
        'state_key', 'boss_entry', 'boss_name', 'boss_level',
        'boss_scale_scaled', 'boss_health_multiplier_scaled',
        'boss_auras_text', 'ally_level',
        'ally_health_multiplier_scaled', 'respawn_time_minutes',
        'minion_count_min', 'minion_count_max', 'skill_preset',
        'skill_difficulty', 'guaranteed_reward_enabled',
        'guaranteed_reward_notify', 'max_random_reward_players',
        'class_reward_chance', 'formula_reward_chance',
        'mount_reward_chance', 'random_reward_mode', 'participation_range',
        'damage_weight', 'healing_weight', 'threat_weight',
        'presence_weight', 'kill_weight', 'guaranteed_item_id',
        'guaranteed_item_count', 'gold_min_copper', 'gold_max_copper',
        'reward_items_text', 'reward_formulas_text', 'reward_mounts_text',
        'spawn_points_text', 'updated_at',
    ];

    private string $customDbName;
    private string $runtimeKey;
    private string $configTable;
    private string $extTable;
    private int $decimalScale;
    private ?array $tableAvailability = null;

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);

        $override = $this->serverOverride($this->serverId);

        $this->customDbName = (string) (
            $override['custom_db_name']
            ?? Config::get('boss.custom_db_name', 'ac_eluna')
        );
        $this->runtimeKey = (string) (
            $override['runtime_key']
            ?? Config::get('boss.runtime_key', 'current')
        );
        $this->configTable = (string) Config::get('boss.config_table', 'boss_activity_config');
        $this->extTable = (string) Config::get('boss.ext_table', 'boss_activity_config_ext');
        $this->decimalScale = max(1, (int) Config::get('boss.decimal_scale', 100));
    }

    /**
     * 当前区服是否部署了 boss.lua（config/boss.php supported_server_ids）。
     */
    public function serverSupported(?int $serverId = null): bool
    {
        $supported = Config::get('boss.supported_server_ids', []);

        if (!is_array($supported) || $supported === [])
            return true;

        $resolved = $serverId ?? $this->serverId;

        foreach ($supported as $candidate) {
            if ((int) $candidate === $resolved)
                return true;
        }

        return false;
    }

    /**
     * 本区 boss.lua 的数据源绑定。多区部署时每个区一个库（config/boss.php server_overrides），
     * 页面用它自证"现在管的是哪个区"，避免看错区还以为配置没生效。
     *
     * @return array{database: string, runtime_key: string}
     */
    public function dataSource(): array
    {
        return [
            'database' => $this->customDbName,
            'runtime_key' => $this->runtimeKey,
        ];
    }

    /**
     * 当前区服显示名，用于 :server 占位替换；取不到时退回区服索引。
     */
    private function serverName(): string
    {
        try {
            $server = ServerContext::server($this->serverId);
            $name = trim((string) ($server['name'] ?? ''));

            if ($name !== '')
                return $name;
        } catch (Throwable $exception) {
            $this->logWarning('server_name_unavailable', $exception);
        }

        return (string) $this->serverId;
    }

    /**
     * 取当前区服的 ac_eluna / state_key 覆盖项，没有配置时返回空数组。
     */
    private function serverOverride(int $serverId): array
    {
        $overrides = Config::get('boss.server_overrides', []);

        if (!is_array($overrides))
            return [];

        $override = $overrides[$serverId] ?? null;

        return is_array($override) ? $override : [];
    }

    public function dashboard(int $eventLimit, int $contributorLimit): array
    {
        try {
            return $this->buildDashboard($eventLimit, $contributorLimit);
        } catch (Throwable $exception) {
            // public/index.php 没有全局异常兜底：DB/配置异常时降级为警告 + 默认值。
            $this->logWarning('dashboard_degraded', $exception);

            $warnings = [];
            $this->warn($warnings, Lang::get('app.boss.warnings.runtime_unavailable'));
            $this->warn($warnings, Lang::get('app.boss.warnings.config_unavailable'));
            $this->warn($warnings, Lang::get('app.boss.warnings.events_unavailable'));
            $this->warn($warnings, Lang::get('app.boss.warnings.contributors_unavailable'));

            $defaults = $this->defaultConfigStorage();

            return [
                'runtime' => $this->defaultRuntime(),
                'config' => $this->normalizeConfigRow($defaults, $defaults),
                'ext' => $this->normalizeExtRow($this->defaultExtStorage(), $this->defaultExtStorage()),
                'stats' => [
                    'events_24h' => 0,
                    'kills_7d' => 0,
                    'contributors_7d' => 0,
                    'random_rewarded_7d' => 0,
                ],
                'events' => [],
                'contributors' => [],
                'critical_warnings' => [
                    Lang::get('app.boss.warnings.dashboard_degraded'),
                ],
                'warnings' => $warnings,
            ];
        }
    }

    private function buildDashboard(int $eventLimit, int $contributorLimit): array
    {
        $warnings = [];
        $criticalWarnings = [];

        if (!$this->serverSupported()) {
            $criticalWarnings[] = Lang::get('app.boss.warnings.server_not_supported', [
                'server' => $this->serverName(),
            ]);
        }

        $missingTables = $this->missingTables([
            'boss_activity_runtime',
            $this->configTable,
            $this->extTable,
            'boss_activity_events',
            'boss_activity_contributors',
        ]);

        if ($missingTables !== []) {
            $criticalWarnings[] = Lang::get('app.boss.warnings.schema_missing', [
                'tables' => implode(', ', $missingTables),
            ]);
        }

        return [
            'runtime' => $this->loadRuntime($warnings),
            'config' => $this->loadConfig($warnings),
            'ext' => $this->loadExtConfig($warnings),
            'stats' => $this->loadStats($warnings),
            'events' => $this->loadEvents($eventLimit, $warnings),
            'contributors' => $this->loadContributors($contributorLimit, $warnings),
            'critical_warnings' => $criticalWarnings,
            'warnings' => $warnings,
        ];
    }

    public function saveConfig(array $config): array
    {
        if (!$this->tableExists($this->configTable))
            throw new \RuntimeException('boss_config_storage_missing');

        // 语义：缺失字段保留数据库现值，只有显式提交的字段才被覆盖。
        $currentRow = $this->loadConfigStorageRow($this->configTable) ?? [];
        $defaults = $this->defaultConfigStorage();

        $normalized = array_replace($defaults, $currentRow, $config, [
            'state_key' => $this->runtimeKey,
            'updated_at' => time(),
        ]);

        $this->storeConfig($normalized, false);

        return $this->normalizeConfigRow($normalized, $defaults);
    }

    /**
     * 读取 config 表当前行的原始列值；表不存在或行缺失时返回 null。
     */
    private function loadConfigStorageRow(string $table): ?array
    {
        if (!$this->tableExists($table))
            return null;

        try {
            $stmt = $this->characters()->prepare(
                'SELECT ' . implode(', ', self::CONFIG_COLUMNS)
                . ' FROM ' . $this->table($table)
                . ' WHERE state_key = :state_key LIMIT 1'
            );
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable $exception) {
            $this->logWarning('config_row_unavailable', $exception);

            return null;
        }
    }

    private function loadRuntime(array &$warnings): array
    {
        if (!$this->tableExists('boss_activity_runtime')) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.runtime_unavailable')
            );

            return $this->defaultRuntime();
        }

        try {
            $stmt = $this->characters()->prepare(
                'SELECT '
                . 'state_key, boss_guid, boss_entry, boss_name, map_id, '
                . 'instance_id, home_x, home_y, home_z, phase, status, '
                . 'skill_preset, skill_difficulty, respawn_at, last_spawn_at, '
                . 'last_engage_at, last_death_at, last_reset_at, updated_at '
                . 'FROM ' . $this->table('boss_activity_runtime')
                . ' WHERE state_key = :state_key LIMIT 1'
            );
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row))
                return $this->defaultRuntime();

            return $this->normalizeRuntime($row);
        } catch (Throwable $exception) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.runtime_unavailable')
            );

            return $this->defaultRuntime();
        }
    }

    private function loadConfig(array &$warnings): array
    {
        $defaults = $this->defaultConfigStorage();

        if (!$this->tableExists($this->configTable)) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.config_unavailable')
            );

            return $this->normalizeConfigRow($defaults, $defaults);
        }

        try {
            $stmt = $this->characters()->prepare(
                'SELECT ' . implode(', ', self::CONFIG_COLUMNS)
                . ' FROM ' . $this->table($this->configTable)
                . ' WHERE state_key = :state_key LIMIT 1'
            );
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row))
                return $this->normalizeConfigRow($defaults, $defaults);

            return $this->normalizeConfigRow($row, $defaults);
        } catch (Throwable $exception) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.config_unavailable')
            );

            return $this->normalizeConfigRow($defaults, $defaults);
        }
    }

    /**
     * 扩展配置（脚本私有列）的字段 schema：列名 → 规格。
     * 来源是 config/boss.php 的 boss.ext_fields，是 boss.lua §3 描述表的镜像。
     * 面板的渲染/校验与这里共用同一份 schema。
     */
    public function extFieldSchema(): array
    {
        $schema = [];
        $groups = Config::get('boss.ext_fields', []);

        if (!is_array($groups))
            return $schema;

        foreach ($groups as $fields) {
            if (!is_array($fields))
                continue;

            foreach ($fields as $field) {
                if (!is_array($field))
                    continue;

                $name = trim((string) ($field['name'] ?? ''));
                if ($name === '')
                    continue;

                $schema[$name] = $field;
            }
        }

        return $schema;
    }

    /**
     * 扩展配置表是否已由 boss.lua 创建；没有就没法保存（只能看默认值）。
     */
    public function extConfigAvailable(): bool
    {
        return $this->tableExists($this->extTable);
    }

    /**
     * 读取扩展配置：表/行缺失或读取异常时回退到内置默认值并记一条 warning。
     */
    private function loadExtConfig(array &$warnings): array
    {
        $defaults = $this->defaultExtStorage();

        if (!$this->tableExists($this->extTable)) {
            $this->warn($warnings, Lang::get('app.boss.warnings.ext_unavailable'));

            return $this->normalizeExtRow($defaults, $defaults);
        }

        try {
            $row = $this->loadExtStorageRow();

            if (!is_array($row))
                return $this->normalizeExtRow($defaults, $defaults);

            return $this->normalizeExtRow($row, $defaults);
        } catch (Throwable $exception) {
            $this->logWarning('ext_config_unavailable', $exception);
            $this->warn($warnings, Lang::get('app.boss.warnings.ext_unavailable'));

            return $this->normalizeExtRow($defaults, $defaults);
        }
    }

    /**
     * 保存扩展配置。语义与主表一致：只覆盖显式提交的字段，其余保留数据库现值。
     *
     * 用 INSERT ... ON DUPLICATE KEY UPDATE（而不是 REPLACE INTO）：REPLACE 会先删行，
     * 脚本新增的列会被重置为建表默认值。
     */
    public function saveExtConfig(array $config): array
    {
        if (!$this->tableExists($this->extTable))
            throw new \RuntimeException('boss_ext_storage_missing');

        $currentRow = $this->loadExtStorageRow() ?? [];
        $defaults = $this->defaultExtStorage();

        $normalized = array_replace($defaults, $currentRow, $config, [
            'state_key' => $this->runtimeKey,
            'updated_at' => time(),
        ]);

        $this->storeExtConfig($normalized);

        return $this->normalizeExtRow($normalized, $defaults);
    }

    private function loadExtStorageRow(): ?array
    {
        $columns = array_keys($this->extFieldSchema());
        if ($columns === [])
            return null;

        try {
            $stmt = $this->characters()->prepare(
                'SELECT ' . $this->quoteColumns($columns)
                . ' FROM ' . $this->table($this->extTable)
                . ' WHERE state_key = :state_key LIMIT 1'
            );
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable $exception) {
            $this->logWarning('ext_config_row_unavailable', $exception);

            return null;
        }
    }

    private function storeExtConfig(array $config): void
    {
        $schema = $this->extFieldSchema();
        if ($schema === [])
            return;

        $columns = array_keys($schema);
        $quoted = [];
        $placeholders = [];
        $updates = [];

        foreach ($columns as $column) {
            $quoted[] = '`' . $column . '`';
            $placeholders[] = ':' . $column;
            $updates[] = '`' . $column . '` = VALUES(`' . $column . '`)';
        }

        $stmt = $this->characters()->prepare(
            'INSERT INTO ' . $this->table($this->extTable)
            . ' (state_key, ' . implode(', ', $quoted) . ', updated_at)'
            . ' VALUES (:state_key, ' . implode(', ', $placeholders) . ', :updated_at)'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
            . ', updated_at = VALUES(updated_at)'
        );

        $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
        $stmt->bindValue(':updated_at', (int) ($config['updated_at'] ?? time()), PDO::PARAM_INT);

        foreach ($schema as $column => $spec) {
            $kind = (string) ($spec['kind'] ?? 'text');
            $value = $config[$column] ?? null;

            if ($kind === 'int' || $kind === 'bool') {
                $stmt->bindValue(':' . $column, (int) $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue(':' . $column, (string) $value, PDO::PARAM_STR);
        }

        $stmt->execute();
    }

    /**
     * 扩展配置的类型归一（整数/开关转 int，其余转 string），键序与 schema 一致。
     */
    private function normalizeExtRow(array $row, array $defaults): array
    {
        $resolved = array_replace($defaults, $row);
        $normalized = [];

        foreach ($this->extFieldSchema() as $name => $spec) {
            $kind = (string) ($spec['kind'] ?? 'text');
            $value = $resolved[$name] ?? null;

            $normalized[$name] = ($kind === 'int' || $kind === 'bool')
                ? (int) ($value ?? 0)
                : (string) ($value ?? '');
        }

        return $normalized;
    }

    /**
     * 出厂默认值：先按 schema 给零值兜底，再用 config/boss.php 的 ext_defaults 覆盖。
     */
    private function defaultExtStorage(): array
    {
        $defaults = [];

        foreach ($this->extFieldSchema() as $name => $spec) {
            $kind = (string) ($spec['kind'] ?? 'text');
            $defaults[$name] = ($kind === 'int' || $kind === 'bool') ? 0 : '';
        }

        $configured = Config::get('boss.ext_defaults', []);
        if (is_array($configured))
            $defaults = array_replace($defaults, $configured);

        return $defaults;
    }

    private function quoteColumns(array $columns): string
    {
        $quoted = [];
        foreach ($columns as $column) {
            $quoted[] = '`' . $column . '`';
        }

        return implode(', ', $quoted);
    }

    private function loadStats(array &$warnings): array
    {
        $now = time();
        $lastDay = $now - 86400;
        $lastWeek = $now - 604800;

        if ($this->tableExists('boss_activity_events')) {
            try {
                $eventStmt = $this->characters()->prepare(
                    'SELECT '
                    . 'SUM(CASE WHEN created_at >= :last_day THEN 1 ELSE 0 END) '
                    . 'AS events_24h, '
                    . 'SUM(CASE WHEN event_type = :death_type '
                    . 'AND created_at >= :last_week THEN 1 ELSE 0 END) AS kills_7d '
                    . 'FROM ' . $this->table('boss_activity_events')
                );
                $eventStmt->bindValue(':last_day', $lastDay, PDO::PARAM_INT);
                $eventStmt->bindValue(':last_week', $lastWeek, PDO::PARAM_INT);
                $eventStmt->bindValue(':death_type', 'death', PDO::PARAM_STR);
                $eventStmt->execute();
                $eventRow = $eventStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $exception) {
                $this->warn(
                    $warnings,
                    Lang::get('app.boss.warnings.events_unavailable')
                );
                $eventRow = [];
            }
        } else {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.events_unavailable')
            );
            $eventRow = [];
        }

        if ($this->tableExists('boss_activity_contributors')) {
            try {
                $contributorStmt = $this->characters()->prepare(
                    'SELECT '
                    . 'SUM(CASE WHEN created_at >= :last_week THEN 1 ELSE 0 END) '
                    . 'AS contributors_7d, '
                    . 'SUM(CASE WHEN created_at >= :last_week THEN rewarded_random '
                    . 'ELSE 0 END) AS random_rewarded_7d '
                    . 'FROM ' . $this->table('boss_activity_contributors')
                );
                $contributorStmt->bindValue(':last_week', $lastWeek, PDO::PARAM_INT);
                $contributorStmt->execute();
                $contributorRow = $contributorStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $exception) {
                $contributorRow = [];
            }
        } else {
            $contributorRow = [];
        }

        return [
            'events_24h' => (int) ($eventRow['events_24h'] ?? 0),
            'kills_7d' => (int) ($eventRow['kills_7d'] ?? 0),
            'contributors_7d' => (int) ($contributorRow['contributors_7d'] ?? 0),
            'random_rewarded_7d' => (int) (
                $contributorRow['random_rewarded_7d'] ?? 0
            ),
        ];
    }

    private function loadEvents(int $limit, array &$warnings): array
    {
        if (!$this->tableExists('boss_activity_events')) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.events_unavailable')
            );

            return [];
        }

        try {
            $stmt = $this->characters()->prepare(
                'SELECT '
                . 'id, boss_guid, boss_entry, boss_name, event_type, '
                . 'event_note, actor_name, actor_guid, payload_json, created_at '
                . 'FROM ' . $this->table('boss_activity_events')
                . ' ORDER BY id DESC LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.events_unavailable')
            );

            return [];
        }

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['boss_guid'] = (int) ($row['boss_guid'] ?? 0);
            $row['boss_entry'] = (int) ($row['boss_entry'] ?? 0);
            $row['actor_guid'] = (int) ($row['actor_guid'] ?? 0);
            $row['created_at'] = (int) ($row['created_at'] ?? 0);
            $row['payload'] = $this->decodePayload(
                (string) ($row['payload_json'] ?? '')
            );
        }
        unset($row);

        return $rows;
    }

    private function loadContributors(int $limit, array &$warnings): array
    {
        if (!$this->tableExists('boss_activity_contributors')) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.contributors_unavailable')
            );

            return [];
        }

        try {
            $stmt = $this->characters()->prepare(
                'SELECT '
                . 'id, boss_guid, boss_entry, boss_name, player_guid, '
                . 'player_name, account_id, damage_done, healing_done, '
                . 'threat_samples, presence_samples, contribution_score, '
                . 'was_killer, rewarded_random, guaranteed_reward, created_at '
                . 'FROM ' . $this->table('boss_activity_contributors')
                . ' ORDER BY id DESC LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.contributors_unavailable')
            );

            return [];
        }

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['boss_guid'] = (int) ($row['boss_guid'] ?? 0);
            $row['boss_entry'] = (int) ($row['boss_entry'] ?? 0);
            $row['player_guid'] = (int) ($row['player_guid'] ?? 0);
            $row['account_id'] = (int) ($row['account_id'] ?? 0);
            $row['damage_done'] = (int) ($row['damage_done'] ?? 0);
            $row['healing_done'] = (int) ($row['healing_done'] ?? 0);
            $row['threat_samples'] = (int) ($row['threat_samples'] ?? 0);
            $row['presence_samples'] = (int) ($row['presence_samples'] ?? 0);
            $row['contribution_score'] = (float) (
                $row['contribution_score'] ?? 0
            );
            $row['was_killer'] = (int) ($row['was_killer'] ?? 0);
            $row['rewarded_random'] = (int) ($row['rewarded_random'] ?? 0);
            $row['guaranteed_reward'] = (int) (
                $row['guaranteed_reward'] ?? 0
            );
            $row['created_at'] = (int) ($row['created_at'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    private function decodePayload(string $value): ?array
    {
        if ($value === '')
            return null;

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function missingTables(array $tables): array
    {
        $missing = [];

        foreach ($tables as $table) {
            if (!$this->tableExists($table))
                $missing[] = $table;
        }

        return $missing;
    }

    private function tableExists(string $table): bool
    {
        if ($this->tableAvailability !== null && array_key_exists($table, $this->tableAvailability))
            return $this->tableAvailability[$table];

        try {
            $stmt = $this->characters()->prepare(
                'SELECT 1 FROM information_schema.TABLES '
                . 'WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table LIMIT 1'
            );
            $stmt->bindValue(':schema', $this->customDbName, PDO::PARAM_STR);
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();

            $exists = $stmt->fetchColumn() !== false;
        } catch (Throwable $exception) {
            // 探测失败（连接/权限异常）不能冒泡：按「不存在」处理并记录一条 warning。
            $this->logWarning('table_probe_failed:' . $table, $exception);
            $exists = false;
        }

        $this->tableAvailability ??= [];
        $this->tableAvailability[$table] = $exists;

        return $exists;
    }

    /**
     * 在 storage/logs 下落一条 warning，避免异常被完全吞掉（与既有 LogPath 约定一致）。
     */
    private function logWarning(string $context, Throwable $exception): void
    {
        try {
            if (!class_exists(LogPath::class))
                return;

            LogPath::appendLine('boss_repository_warnings.log', sprintf(
                '[%s] %s: %s (%s:%d)',
                date('Y-m-d H:i:s'),
                $context,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ), true, 0777);
        } catch (Throwable $ignored) {
            // 日志不可写时静默降级，绝不能因为记录日志再次抛异常。
        }
    }

    private function storeConfig(array $config, bool $ignoreExisting): void
    {
        $verb = $ignoreExisting ? 'INSERT IGNORE INTO' : 'REPLACE INTO';
        $stmt = $this->characters()->prepare(
            $verb . ' ' . $this->table($this->configTable) . ' ('
            . 'state_key, boss_entry, boss_name, boss_level, '
            . 'boss_scale_scaled, boss_health_multiplier_scaled, '
            . 'boss_auras_text, ally_level, ally_health_multiplier_scaled, '
            . 'respawn_time_minutes, minion_count_min, minion_count_max, '
            . 'skill_preset, skill_difficulty, guaranteed_reward_enabled, '
            . 'guaranteed_reward_notify, max_random_reward_players, '
            . 'class_reward_chance, formula_reward_chance, '
            . 'mount_reward_chance, random_reward_mode, participation_range, '
            . 'damage_weight, healing_weight, threat_weight, '
            . 'presence_weight, kill_weight, guaranteed_item_id, '
            . 'guaranteed_item_count, gold_min_copper, gold_max_copper, '
            . 'reward_items_text, reward_formulas_text, reward_mounts_text, '
            . 'spawn_points_text, '
            . 'updated_at'
            . ') VALUES ('
            . ':state_key, :boss_entry, :boss_name, :boss_level, '
            . ':boss_scale_scaled, :boss_health_multiplier_scaled, '
            . ':boss_auras_text, :ally_level, :ally_health_multiplier_scaled, '
            . ':respawn_time_minutes, :minion_count_min, :minion_count_max, '
            . ':skill_preset, :skill_difficulty, :guaranteed_reward_enabled, '
            . ':guaranteed_reward_notify, :max_random_reward_players, '
            . ':class_reward_chance, :formula_reward_chance, '
            . ':mount_reward_chance, :random_reward_mode, :participation_range, '
            . ':damage_weight, :healing_weight, :threat_weight, '
            . ':presence_weight, :kill_weight, :guaranteed_item_id, '
            . ':guaranteed_item_count, :gold_min_copper, :gold_max_copper, '
            . ':reward_items_text, :reward_formulas_text, :reward_mounts_text, '
            . ':spawn_points_text, '
            . ':updated_at'
            . ')'
        );

        $this->bindConfigStatement($stmt, $config);
        $stmt->execute();
    }

    private function bindConfigStatement(PDOStatement $stmt, array $config): void
    {
        $stmt->bindValue(':state_key', (string) ($config['state_key'] ?? $this->runtimeKey), PDO::PARAM_STR);
        $stmt->bindValue(':boss_entry', (int) ($config['boss_entry'] ?? Config::get('boss.default_tier_entry', 190090)), PDO::PARAM_INT);
        $stmt->bindValue(':boss_name', (string) ($config['boss_name'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':boss_level', (int) ($config['boss_level'] ?? 83), PDO::PARAM_INT);
        $stmt->bindValue(':boss_scale_scaled', (int) ($config['boss_scale_scaled'] ?? 500), PDO::PARAM_INT);
        $stmt->bindValue(':boss_health_multiplier_scaled', (int) ($config['boss_health_multiplier_scaled'] ?? 2000), PDO::PARAM_INT);
        $stmt->bindValue(':boss_auras_text', (string) ($config['boss_auras_text'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':ally_level', (int) ($config['ally_level'] ?? 20), PDO::PARAM_INT);
        $stmt->bindValue(':ally_health_multiplier_scaled', (int) ($config['ally_health_multiplier_scaled'] ?? 150), PDO::PARAM_INT);
        $stmt->bindValue(':respawn_time_minutes', (int) ($config['respawn_time_minutes'] ?? 10), PDO::PARAM_INT);
        $stmt->bindValue(':minion_count_min', (int) ($config['minion_count_min'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':minion_count_max', (int) ($config['minion_count_max'] ?? 2), PDO::PARAM_INT);
        $stmt->bindValue(':skill_preset', (string) ($config['skill_preset'] ?? 'storm_siege'), PDO::PARAM_STR);
        $stmt->bindValue(':skill_difficulty', (string) ($config['skill_difficulty'] ?? 'standard'), PDO::PARAM_STR);
        $stmt->bindValue(':guaranteed_reward_enabled', (int) ($config['guaranteed_reward_enabled'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':guaranteed_reward_notify', (int) ($config['guaranteed_reward_notify'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':max_random_reward_players', (int) ($config['max_random_reward_players'] ?? 3), PDO::PARAM_INT);
        $stmt->bindValue(':class_reward_chance', (int) ($config['class_reward_chance'] ?? 60), PDO::PARAM_INT);
        $stmt->bindValue(':formula_reward_chance', (int) ($config['formula_reward_chance'] ?? 10), PDO::PARAM_INT);
        $stmt->bindValue(':mount_reward_chance', (int) ($config['mount_reward_chance'] ?? 15), PDO::PARAM_INT);
        $stmt->bindValue(':random_reward_mode', (string) ($config['random_reward_mode'] ?? 'weighted'), PDO::PARAM_STR);
        $stmt->bindValue(':participation_range', (int) ($config['participation_range'] ?? 80), PDO::PARAM_INT);
        $stmt->bindValue(':damage_weight', (int) ($config['damage_weight'] ?? 100), PDO::PARAM_INT);
        $stmt->bindValue(':healing_weight', (int) ($config['healing_weight'] ?? 80), PDO::PARAM_INT);
        $stmt->bindValue(':threat_weight', (int) ($config['threat_weight'] ?? 35), PDO::PARAM_INT);
        $stmt->bindValue(':presence_weight', (int) ($config['presence_weight'] ?? 10), PDO::PARAM_INT);
        $stmt->bindValue(':kill_weight', (int) ($config['kill_weight'] ?? 3), PDO::PARAM_INT);
        $stmt->bindValue(':guaranteed_item_id', (int) ($config['guaranteed_item_id'] ?? 40753), PDO::PARAM_INT);
        $stmt->bindValue(':guaranteed_item_count', (int) ($config['guaranteed_item_count'] ?? 2), PDO::PARAM_INT);
        $stmt->bindValue(':gold_min_copper', (int) ($config['gold_min_copper'] ?? 30000), PDO::PARAM_INT);
        $stmt->bindValue(':gold_max_copper', (int) ($config['gold_max_copper'] ?? 50000), PDO::PARAM_INT);
        $stmt->bindValue(':reward_items_text', (string) ($config['reward_items_text'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':reward_formulas_text', (string) ($config['reward_formulas_text'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':reward_mounts_text', (string) ($config['reward_mounts_text'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':spawn_points_text', (string) ($config['spawn_points_text'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':updated_at', (int) ($config['updated_at'] ?? time()), PDO::PARAM_INT);
    }

    private function normalizeRuntime(array $row): array
    {
        return [
            'state_key' => (string) ($row['state_key'] ?? $this->runtimeKey),
            'boss_guid' => (int) ($row['boss_guid'] ?? 0),
            'boss_entry' => (int) ($row['boss_entry'] ?? 0),
            'boss_name' => (string) ($row['boss_name'] ?? ''),
            'map_id' => (int) ($row['map_id'] ?? 0),
            'instance_id' => (int) ($row['instance_id'] ?? 0),
            'home_x' => (float) ($row['home_x'] ?? 0),
            'home_y' => (float) ($row['home_y'] ?? 0),
            'home_z' => (float) ($row['home_z'] ?? 0),
            'phase' => (int) ($row['phase'] ?? 0),
            'status' => (string) ($row['status'] ?? 'idle'),
            'skill_preset' => (string) ($row['skill_preset'] ?? ''),
            'skill_difficulty' => (string) ($row['skill_difficulty'] ?? ''),
            'respawn_at' => (int) ($row['respawn_at'] ?? 0),
            'last_spawn_at' => (int) ($row['last_spawn_at'] ?? 0),
            'last_engage_at' => (int) ($row['last_engage_at'] ?? 0),
            'last_death_at' => (int) ($row['last_death_at'] ?? 0),
            'last_reset_at' => (int) ($row['last_reset_at'] ?? 0),
            'updated_at' => (int) ($row['updated_at'] ?? 0),
        ];
    }

    private function normalizeConfigRow(array $row, array $defaults): array
    {
        $resolved = array_replace($defaults, $row);

        return [
            'state_key' => (string) ($resolved['state_key'] ?? $this->runtimeKey),
            'boss_entry' => (int) ($resolved['boss_entry'] ?? Config::get('boss.default_tier_entry', 190090)),
            'boss_name' => (string) ($resolved['boss_name'] ?? ''),
            'boss_level' => (int) ($resolved['boss_level'] ?? 83),
            'boss_scale' => $this->scaledToDisplay((int) ($resolved['boss_scale_scaled'] ?? 500)),
            'boss_health_multiplier' => $this->scaledToDisplay((int) ($resolved['boss_health_multiplier_scaled'] ?? 2000)),
            'boss_auras_text' => (string) ($resolved['boss_auras_text'] ?? ''),
            'ally_level' => (int) ($resolved['ally_level'] ?? 20),
            'ally_health_multiplier' => $this->scaledToDisplay((int) ($resolved['ally_health_multiplier_scaled'] ?? 150)),
            'respawn_time_minutes' => (int) ($resolved['respawn_time_minutes'] ?? 10),
            'minion_count_min' => (int) ($resolved['minion_count_min'] ?? 1),
            'minion_count_max' => (int) ($resolved['minion_count_max'] ?? 2),
            'skill_preset' => (string) ($resolved['skill_preset'] ?? ''),
            'skill_difficulty' => (string) ($resolved['skill_difficulty'] ?? ''),
            'guaranteed_reward_enabled' => (int) ($resolved['guaranteed_reward_enabled'] ?? 1),
            'guaranteed_reward_notify' => (int) ($resolved['guaranteed_reward_notify'] ?? 1),
            'max_random_reward_players' => (int) ($resolved['max_random_reward_players'] ?? 3),
            'class_reward_chance' => (int) ($resolved['class_reward_chance'] ?? 60),
            'formula_reward_chance' => (int) ($resolved['formula_reward_chance'] ?? 10),
            'mount_reward_chance' => (int) ($resolved['mount_reward_chance'] ?? 15),
            'random_reward_mode' => (string) ($resolved['random_reward_mode'] ?? 'weighted'),
            'participation_range' => (int) ($resolved['participation_range'] ?? 80),
            'damage_weight' => (int) ($resolved['damage_weight'] ?? 100),
            'healing_weight' => (int) ($resolved['healing_weight'] ?? 80),
            'threat_weight' => (int) ($resolved['threat_weight'] ?? 35),
            'presence_weight' => (int) ($resolved['presence_weight'] ?? 10),
            'kill_weight' => (int) ($resolved['kill_weight'] ?? 3),
            'guaranteed_item_id' => (int) ($resolved['guaranteed_item_id'] ?? 40753),
            'guaranteed_item_count' => (int) ($resolved['guaranteed_item_count'] ?? 2),
            'gold_min_copper' => (int) ($resolved['gold_min_copper'] ?? 30000),
            'gold_max_copper' => (int) ($resolved['gold_max_copper'] ?? 50000),
            'reward_items_text' => (string) ($resolved['reward_items_text'] ?? ''),
            'reward_formulas_text' => (string) ($resolved['reward_formulas_text'] ?? ''),
            'reward_mounts_text' => (string) ($resolved['reward_mounts_text'] ?? ''),
            'spawn_points_text' => (string) ($resolved['spawn_points_text'] ?? ''),
            'updated_at' => (int) ($resolved['updated_at'] ?? 0),
        ];
    }

    private function defaultRuntime(): array
    {
        return [
            'state_key' => $this->runtimeKey,
            'boss_guid' => 0,
            'boss_entry' => 0,
            'boss_name' => '',
            'map_id' => 0,
            'instance_id' => 0,
            'home_x' => 0.0,
            'home_y' => 0.0,
            'home_z' => 0.0,
            'phase' => 0,
            'status' => 'idle',
            'skill_preset' => (string) Config::get('boss.preset_values.0', ''),
            'skill_difficulty' => (string) Config::get(
                'boss.difficulty_values.1',
                'standard'
            ),
            'respawn_at' => 0,
            'last_spawn_at' => 0,
            'last_engage_at' => 0,
            'last_death_at' => 0,
            'last_reset_at' => 0,
            'updated_at' => 0,
        ];
    }

    private function defaultConfigStorage(): array
    {
        $defaults = [
            'state_key' => $this->runtimeKey,
            'boss_entry' => (int) Config::get('boss.default_tier_entry', 190090),
            'boss_name' => '净土年兽',
            'boss_level' => 83,
            'boss_scale_scaled' => 500,
            'boss_health_multiplier_scaled' => 2000,
            'boss_auras_text' => '21562,1126,467,20217',
            'ally_level' => 20,
            'ally_health_multiplier_scaled' => 150,
            'respawn_time_minutes' => 10,
            'minion_count_min' => 1,
            'minion_count_max' => 2,
            'skill_preset' => (string) Config::get('boss.preset_values.0', 'storm_siege'),
            'skill_difficulty' => (string) Config::get('boss.difficulty_values.1', 'standard'),
            'guaranteed_reward_enabled' => 1,
            'guaranteed_reward_notify' => 1,
            'max_random_reward_players' => 3,
            'class_reward_chance' => 60,
            'formula_reward_chance' => 10,
            'mount_reward_chance' => 15,
            'random_reward_mode' => 'weighted',
            'participation_range' => 80,
            'damage_weight' => 100,
            'healing_weight' => 80,
            'threat_weight' => 35,
            'presence_weight' => 10,
            'kill_weight' => 3,
            'guaranteed_item_id' => 40753,
            'guaranteed_item_count' => 2,
            'gold_min_copper' => 30000,
            'gold_max_copper' => 50000,
            'reward_items_text' => '38082,41600,51809,34067',
            'reward_formulas_text' => '45059,44491',
            'reward_mounts_text' => '32768,30480,13335,37719,49282,49290,19872,33977,33809,37828,43963,54068,33183,33189,35513,43964,19902,43963,46109,50250,49286,30609,54860,37012',
            'spawn_points_text' => (string) Config::get('boss.defaults.spawn_points_text', ''),
            'updated_at' => 0,
        ];

        $configuredDefaults = Config::get('boss.defaults', []);
        if (is_array($configuredDefaults)) {
            $defaults = array_replace($defaults, $configuredDefaults);
        }

        return $defaults;
    }

    private function scaledToDisplay(int $scaledValue): string
    {
        return number_format($scaledValue / $this->decimalScale, 2, '.', '');
    }

    private function warn(array &$warnings, string $message): void
    {
        if ($message === '' || in_array($message, $warnings, true))
            return;

        $warnings[] = $message;
    }

    private function table(string $table): string
    {
        return '`' . $this->customDbName . '`.`' . $table . '`';
    }
}