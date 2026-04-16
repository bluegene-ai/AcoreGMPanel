<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MultiServerRepository;
use PDO;
use Throwable;

class BossRepository extends MultiServerRepository
{
    private string $customDbName;
    private string $runtimeKey;

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);

        $this->customDbName = (string) Config::get('boss.custom_db_name', 'ac_eluna');
        $this->runtimeKey = (string) Config::get('boss.runtime_key', 'current');
    }

    public function dashboard(int $eventLimit, int $contributorLimit): array
    {
        $warnings = [];

        return [
            'runtime' => $this->loadRuntime($warnings),
            'stats' => $this->loadStats($warnings),
            'events' => $this->loadEvents($eventLimit, $warnings),
            'contributors' => $this->loadContributors($contributorLimit, $warnings),
            'warnings' => $warnings,
        ];
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