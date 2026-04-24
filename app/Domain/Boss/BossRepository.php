<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MultiServerRepository;
use PDO;
use PDOStatement;
use Throwable;

class BossRepository extends MultiServerRepository
{
    private string $customDbName;
    private string $runtimeKey;
    private string $configTable;
    private int $decimalScale;

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);

        $this->customDbName = (string) Config::get('boss.custom_db_name', 'ac_eluna');
        $this->runtimeKey = (string) Config::get('boss.runtime_key', 'current');
        $this->configTable = (string) Config::get('boss.config_table', 'boss_activity_config');
        $this->decimalScale = max(1, (int) Config::get('boss.decimal_scale', 100));
    }

    public function dashboard(int $eventLimit, int $contributorLimit): array
    {
        $warnings = [];

        return [
            'runtime' => $this->loadRuntime($warnings),
            'config' => $this->loadConfig($warnings),
            'stats' => $this->loadStats($warnings),
            'events' => $this->loadEvents($eventLimit, $warnings),
            'contributors' => $this->loadContributors($contributorLimit, $warnings),
            'warnings' => $warnings,
        ];
    }

    public function saveConfig(array $config): array
    {
        $defaults = $this->defaultConfigStorage();
        $normalized = array_replace($defaults, $config, [
            'state_key' => $this->runtimeKey,
            'updated_at' => time(),
        ]);

        $this->ensureConfigStorage($defaults);
        $this->storeConfig($normalized, false);

        return $this->normalizeConfigRow($normalized, $defaults);
    }

    private function loadRuntime(array &$warnings): array
    {
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

        try {
            $this->ensureConfigStorage($defaults);

            $stmt = $this->characters()->prepare(
                'SELECT '
                . 'state_key, boss_entry, boss_name, boss_level, '
                . 'boss_scale_scaled, boss_health_multiplier_scaled, '
                . 'boss_auras_text, ally_level, '
                . 'ally_health_multiplier_scaled, respawn_time_minutes, '
                . 'minion_count_min, minion_count_max, skill_preset, '
                . 'skill_difficulty, guaranteed_reward_enabled, '
                . 'guaranteed_reward_notify, max_random_reward_players, '
                . 'class_reward_chance, formula_reward_chance, '
                . 'mount_reward_chance, random_reward_mode, '
                . 'participation_range, damage_weight, healing_weight, '
                . 'threat_weight, presence_weight, kill_weight, '
                . 'guaranteed_item_id, guaranteed_item_count, '
                . 'gold_min_copper, gold_max_copper, reward_items_text, '
                . 'reward_formulas_text, reward_mounts_text, '
                . 'spawn_points_text, updated_at '
                . 'FROM ' . $this->table($this->configTable)
                . ' WHERE state_key = :state_key LIMIT 1'
            );
            $stmt->bindValue(':state_key', $this->runtimeKey, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                $this->storeConfig($defaults, true);
                return $this->normalizeConfigRow($defaults, $defaults);
            }

            return $this->normalizeConfigRow($row, $defaults);
        } catch (Throwable $exception) {
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.config_unavailable')
            );

            return $this->normalizeConfigRow($defaults, $defaults);
        }
    }

    private function loadStats(array &$warnings): array
    {
        $now = time();
        $lastDay = $now - 86400;
        $lastWeek = $now - 604800;

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
            $this->warn(
                $warnings,
                Lang::get('app.boss.warnings.contributors_unavailable')
            );
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

    private function ensureConfigStorage(array $defaults): void
    {
        $this->characters()->exec(
            'CREATE DATABASE IF NOT EXISTS `' . $this->customDbName . '`'
        );
        $this->characters()->exec($this->createConfigTableSql());
        try {
            $this->characters()->exec(
                'ALTER TABLE ' . $this->table($this->configTable)
                . ' ADD COLUMN `spawn_points_text` TEXT NULL '
                . 'AFTER `reward_mounts_text`'
            );
        } catch (Throwable $exception) {
            // Ignore duplicate-column errors for existing upgraded schemas.
        }
        $this->storeConfig($defaults, true);
    }

    private function createConfigTableSql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS ' . $this->table($this->configTable) . ' ('
            . '`state_key` VARCHAR(32) NOT NULL,'
            . '`boss_entry` INT NOT NULL DEFAULT 647,'
            . '`boss_name` VARCHAR(120) NOT NULL DEFAULT "",'
            . '`boss_level` INT NOT NULL DEFAULT 83,'
            . '`boss_scale_scaled` INT NOT NULL DEFAULT 500,'
            . '`boss_health_multiplier_scaled` INT NOT NULL DEFAULT 2000,'
            . '`boss_auras_text` TEXT NULL,'
            . '`ally_level` INT NOT NULL DEFAULT 20,'
            . '`ally_health_multiplier_scaled` INT NOT NULL DEFAULT 150,'
            . '`respawn_time_minutes` INT NOT NULL DEFAULT 10,'
            . '`minion_count_min` INT NOT NULL DEFAULT 1,'
            . '`minion_count_max` INT NOT NULL DEFAULT 2,'
            . '`skill_preset` VARCHAR(64) NOT NULL DEFAULT "storm_siege",'
            . '`skill_difficulty` VARCHAR(64) NOT NULL DEFAULT "standard",'
            . '`guaranteed_reward_enabled` TINYINT NOT NULL DEFAULT 1,'
            . '`guaranteed_reward_notify` TINYINT NOT NULL DEFAULT 1,'
            . '`max_random_reward_players` INT NOT NULL DEFAULT 3,'
            . '`class_reward_chance` INT NOT NULL DEFAULT 60,'
            . '`formula_reward_chance` INT NOT NULL DEFAULT 10,'
            . '`mount_reward_chance` INT NOT NULL DEFAULT 15,'
            . '`random_reward_mode` VARCHAR(16) NOT NULL DEFAULT "weighted",'
            . '`participation_range` INT NOT NULL DEFAULT 80,'
            . '`damage_weight` INT NOT NULL DEFAULT 100,'
            . '`healing_weight` INT NOT NULL DEFAULT 80,'
            . '`threat_weight` INT NOT NULL DEFAULT 35,'
            . '`presence_weight` INT NOT NULL DEFAULT 10,'
            . '`kill_weight` INT NOT NULL DEFAULT 3,'
            . '`guaranteed_item_id` INT NOT NULL DEFAULT 40753,'
            . '`guaranteed_item_count` INT NOT NULL DEFAULT 2,'
            . '`gold_min_copper` INT NOT NULL DEFAULT 30000,'
            . '`gold_max_copper` INT NOT NULL DEFAULT 50000,'
            . '`reward_items_text` TEXT NULL,'
            . '`reward_formulas_text` TEXT NULL,'
            . '`reward_mounts_text` TEXT NULL,'
                . '`spawn_points_text` TEXT NULL,'
            . '`updated_at` INT NOT NULL DEFAULT 0,'
            . 'PRIMARY KEY (`state_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
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
        $stmt->bindValue(':boss_entry', (int) ($config['boss_entry'] ?? 647), PDO::PARAM_INT);
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
            'boss_entry' => (int) ($resolved['boss_entry'] ?? 647),
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
            'boss_entry' => 647,
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