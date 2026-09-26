<?php
/**
 * File: app/Domain/Boss/BossConfigTransferService.php
 * Purpose: 把一个区服的 Boss 配置（扩展配置分组 + 可选主配置）复制到另一个区服，
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Core\Config;
use Throwable;

/**
 * Boss 配置跨区复制。
 *
 * 按区服构造仓储（面板既有的多区约定，见 Domain\Support\MultiServerRepository 与
 * Support\ServerContext）：BossController 走 `new BossRepository()` + `Controller::switchServerAndRebind()`
 * → 内部调 `MultiServerRepository::rebind($serverId)`；本服务则直接吃两个不同 serverId 的仓储：
 *
 *   $service = new BossConfigTransferService(new BossRepository($fromId), new BossRepository($toId));
 *   $service->copyExt(['reward_pools', 'schedule'], true);
 *
 * 读写都只用 BossRepository 的**公开**方法（无自建 SQL）：
 *   - 读源区服：`dashboard(0, 0)` → 它的 'ext' / 'config' 两行（唯一公开的读路径）
 *   - 写目标区服：`saveExtConfig()` / `saveConfig()`（仓储自己处理租户键与时间戳）
 * 不发 SOAP：热加载（.boss config reload）由调用方在复制完成后对**目标**区服执行。
 *
 * 返回结构：
 *   [
 *     'ok' => bool,                    // 至少复制了一列，且没有任何写入失败
 *     'ext_columns' => int,            // 实际写入的扩展配置列数
 *     'main_columns' => int,           // 实际写入的主配置列数
 *     'ext_column_names' => string[],  // 实际写入的扩展配置列名（顺序 = extFieldSchema）
 *     'main_column_names' => string[], // 实际写入的主配置列名
 *     'groups' => string[],            // 实际复制的分组（ext 组名；主配置为 'main'）
 *     'skipped' => string[],           // 明确没碰的列/组（未请求、目标建表缺失、写入失败……）
 *     'warnings' => string[],          // 中文告警
 *   ]
 *
 * 已知边界（都是既有仓储语义，本服务不改，也不该靠改仓储绕开）：
 *   1) `dashboard()` 在源区服**没有配置行**时会用面板出厂默认值兜底，且不给 warning ——
 *      面板侧无法区分"从没配过"和"就是配成了默认值"。所以在源区服可能没有配置行时调用本服务，
 *      会把默认值复制过去。
 *   2) `saveExtConfig()` 是 INSERT ... ON DUPLICATE KEY UPDATE：目标区服**已有该行**时只覆盖
 *      提交的列（其它列原样保留，这正是"只动请求的分组"能成立的原因）；目标区服**还没有行**时，
 *      新插入的行其余列会取面板默认值（而不是目标库里的空值）。
 *   3) `state_key` / `updated_at` 永不复制：目标区服保留自己的租户键，时间戳由仓储写入。
 */
class BossConfigTransferService
{
    /**
     * 主配置里"读取侧是显示值 / 存储侧是缩放整数"的三列。
     * 读取侧（normalizeConfigRow）已经把 *_scaled 换算成 boss_scale 这样的显示值，
     * 落库前必须换算回去，否则这三列会静默地保持目标区服的现值（半截复制）。
     */
    private const MAIN_SCALED_COLUMNS = [
        'boss_scale' => 'boss_scale_scaled',
        'boss_health_multiplier' => 'boss_health_multiplier_scaled',
        'ally_health_multiplier' => 'ally_health_multiplier_scaled',
    ];

    private BossRepository $source;
    private BossRepository $target;

    /**
     * @param BossRepository $source 源区服仓储（serverId = 复制来源）
     * @param BossRepository $target 目标区服仓储（serverId = 复制目标）
     */
    public function __construct(BossRepository $source, BossRepository $target)
    {
        $this->source = $source;
        $this->target = $target;
    }

    /**
     * 复制扩展配置（可只复制部分分组），可选带上主配置。
     *
     * @param array<int,string> $groups 分组名：可以是 config/boss.php `ext_fields` 的组
     *        （yells / taunts / ai / phase / patrol / minion / helper / class / tier /
     *        skill_random / reward_pool_1..6 / schedule），也可以是 `ext_tabs` 的 Tab
     *        （ai / patrol / support / skill_random / reward_pools / schedule —— 会自动展开成组）。
     *        空数组 = 全部扩展配置组。
     * @param bool $includeMainConfig 是否连主配置（boss_activity_config）一起复制
     * @return array<string,mixed> 见类 docblock
     */
    public function copyExt(array $groups = [], bool $includeMainConfig = false): array
    {
        $skipped = [];
        $warnings = [];
        $failures = 0;
        $copiedGroups = [];

        
        $dashboard = $this->source->dashboard(0, 0);
        $extRow = is_array($dashboard['ext'] ?? null) ? $dashboard['ext'] : [];
        $configRow = is_array($dashboard['config'] ?? null) ? $dashboard['config'] : [];

        $groupColumns = $this->groupColumns();
        $requested = $this->resolveRequestedGroups($groups, $groupColumns, $skipped, $warnings);

        
        $schema = $this->source->extFieldSchema();
        $selected = [];
        foreach ($requested as $group) {
            foreach ($groupColumns[$group] as $column) {
                if (!array_key_exists($column, $schema)) {
                    $skipped[] = 'ext.' . $column . '（不在本面板 ext 字段 schema 里）';
                    continue;
                }

                $selected[$column] = true;
            }
        }

        $extPayload = [];
        foreach ($schema as $column => $spec) {
            if (!isset($selected[$column])) {
                continue;
            }

            if (!array_key_exists($column, $extRow)) {
                
                $warnings[] = 'ext.' . $column . ' 在源区服读数里缺失，按该字段默认值复制';
                $extPayload[$column] = $this->defaultExtValue($spec);
                continue;
            }

            $extPayload[$column] = $extRow[$column];
        }

        $extColumns = $this->writeExtConfig($extPayload, $requested, $skipped, $warnings, $failures, $copiedGroups);

        $mainColumns = [];
        if ($includeMainConfig === false) {
            $skipped[] = 'main.*（未请求：includeMainConfig=false）';
        } else {
            $mainPayload = $this->buildMainPayload($configRow, $skipped, $warnings);

            try {
                $this->target->saveConfig($mainPayload);
                $mainColumns = array_keys($mainPayload);
                $copiedGroups[] = 'main';
            } catch (Throwable $exception) {
                $warnings[] = '主配置写入失败：' . $exception->getMessage();
                foreach (array_keys($mainPayload) as $column) {
                    $skipped[] = 'main.' . $column . '（写入失败）';
                }
                $failures++;
            }
        }

        $ok = $failures === 0 && ($extColumns !== [] || $mainColumns !== []);
        if ($failures === 0 && !$ok) {
            $warnings[] = '没有任何配置列被复制';
        }

        return [
            'ok' => $ok,
            'ext_columns' => count($extColumns),
            'main_columns' => count($mainColumns),
            'ext_column_names' => $extColumns,
            'main_column_names' => $mainColumns,
            'groups' => array_values(array_unique($copiedGroups)),
            'skipped' => $skipped,
            'warnings' => $warnings,
        ];
    }

    /**
     * 写扩展配置：源/目标建表缺失与写入异常都只降级成 warning + skipped，不抛异常。
     *
     * @param array<string,mixed> $payload
     * @param array<int,string> $requested
     * @param array<int,string> $skipped
     * @param array<int,string> $warnings
     * @param array<int,string> $copiedGroups
     * @return array<int,string> 实际写入的列名
     */
    private function writeExtConfig(
        array $payload,
        array $requested,
        array &$skipped,
        array &$warnings,
        int &$failures,
        array &$copiedGroups
    ): array {
        $skipped[] = 'ext.state_key（保留目标区服的租户键）';
        $skipped[] = 'ext.updated_at（由仓储刷新）';

        if ($payload === []) {
            
            if ($requested !== []) {
                $warnings[] = '没有选中任何扩展配置列，扩展配置未复制';
            }

            return [];
        }

        if (!$this->source->extConfigAvailable()) {
            $warnings[] = '源区服没有 boss_activity_config_ext 表，扩展配置读不到';
            foreach (array_keys($payload) as $column) {
                $skipped[] = 'ext.' . $column . '（源区服扩展配置表不存在）';
            }
            $failures++;

            return [];
        }

        if (!$this->target->extConfigAvailable()) {
            $warnings[] = '目标区服没有 boss_activity_config_ext 表（boss.lua 尚未建表），扩展配置未写入';
            foreach (array_keys($payload) as $column) {
                $skipped[] = 'ext.' . $column . '（目标区服扩展配置表不存在）';
            }
            $failures++;

            return [];
        }

        try {
            $this->target->saveExtConfig($payload);
        } catch (Throwable $exception) {
            $warnings[] = '扩展配置写入失败：' . $exception->getMessage();
            foreach (array_keys($payload) as $column) {
                $skipped[] = 'ext.' . $column . '（写入失败）';
            }
            $failures++;

            return [];
        }

        foreach ($requested as $group) {
            $copiedGroups[] = $group;
        }

        return array_keys($payload);
    }

    /**
     * 主配置 payload：把读取侧的键（含三个显示值）映射回 `boss_activity_config` 的真实列。
     * 列名与 BossRepository::storeConfig() 的绑定清单一一对应（不含 state_key / updated_at）。
     *
     * @param array<string,mixed> $configRow dashboard()['config']
     * @param array<int,string> $skipped
     * @param array<int,string> $warnings
     * @return array<string,mixed>
     */
    private function buildMainPayload(array $configRow, array &$skipped, array &$warnings): array
    {
        $payload = [
            'boss_entry' => (int) ($configRow['boss_entry'] ?? 0),
            'boss_name' => (string) ($configRow['boss_name'] ?? ''),
            'boss_level' => (int) ($configRow['boss_level'] ?? 0),
            'boss_auras_text' => (string) ($configRow['boss_auras_text'] ?? ''),
            'ally_level' => (int) ($configRow['ally_level'] ?? 0),
            'respawn_time_minutes' => (int) ($configRow['respawn_time_minutes'] ?? 0),
            'minion_count_min' => (int) ($configRow['minion_count_min'] ?? 0),
            'minion_count_max' => (int) ($configRow['minion_count_max'] ?? 0),
            'skill_preset' => (string) ($configRow['skill_preset'] ?? ''),
            'skill_difficulty' => (string) ($configRow['skill_difficulty'] ?? ''),
            'random_reward_mode' => (string) ($configRow['random_reward_mode'] ?? ''),
            'participation_range' => (int) ($configRow['participation_range'] ?? 0),
            'damage_weight' => (int) ($configRow['damage_weight'] ?? 0),
            'healing_weight' => (int) ($configRow['healing_weight'] ?? 0),
            'threat_weight' => (int) ($configRow['threat_weight'] ?? 0),
            'presence_weight' => (int) ($configRow['presence_weight'] ?? 0),
            'kill_weight' => (int) ($configRow['kill_weight'] ?? 0),
            'spawn_points_text' => (string) ($configRow['spawn_points_text'] ?? ''),
        ];

        
        foreach (self::MAIN_SCALED_COLUMNS as $displayKey => $storageColumn) {
            if (!array_key_exists($displayKey, $configRow)) {
                $skipped[] = 'main.' . $storageColumn . '（源区服读数里没有 ' . $displayKey . '）';
                continue;
            }

            if (!is_numeric($configRow[$displayKey])) {
                $warnings[] = 'main.' . $displayKey . ' 不是数字，按出厂默认缩放值复制';
            }

            $payload[$storageColumn] = BossTierOptions::toScaled($configRow[$displayKey]);
        }

        $skipped[] = 'main.state_key（保留目标区服的租户键）';
        $skipped[] = 'main.updated_at（由仓储刷新）';

        return $payload;
    }

    /**
     * config/boss.php `ext_fields` 的组 → 列映射（顺序即落库的列顺序）。
     *
     * @return array<string,array<int,string>>
     */
    private function groupColumns(): array
    {
        $configured = Config::get('boss.ext_fields', []);
        if (!is_array($configured)) {
            return [];
        }

        $groups = [];
        foreach ($configured as $group => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            $columns = [];
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $name = trim((string) ($field['name'] ?? ''));
                if ($name !== '') {
                    $columns[] = $name;
                }
            }

            if ($columns !== []) {
                $groups[(string) $group] = $columns;
            }
        }

        return $groups;
    }

    /**
     * 归一请求的分组：空数组 = 全部组；`ext_tabs` 的 Tab 名展开成它包含的组；
     * 认不出的名字进 skipped，且整组都认不出时给一条 warning。
     *
     * @param array<int,string> $groups
     * @param array<string,array<int,string>> $groupColumns
     * @param array<int,string> $skipped
     * @param array<int,string> $warnings
     * @return array<int,string>
     */
    private function resolveRequestedGroups(
        array $groups,
        array $groupColumns,
        array &$skipped,
        array &$warnings
    ): array {
        if ($groups === []) {
            return array_keys($groupColumns);
        }

        $tabs = Config::get('boss.ext_tabs', []);
        $requested = [];

        foreach ($groups as $group) {
            $name = trim((string) $group);
            if ($name === '') {
                continue;
            }

            if (array_key_exists($name, $groupColumns)) {
                $requested[$name] = true;
                continue;
            }

            
            $tabGroups = is_array($tabs) ? ($tabs[$name] ?? null) : null;
            if (is_array($tabGroups)) {
                $expanded = 0;
                foreach ($tabGroups as $tabGroup) {
                    $tabGroup = trim((string) $tabGroup);
                    if ($tabGroup !== '' && array_key_exists($tabGroup, $groupColumns)) {
                        $requested[$tabGroup] = true;
                        $expanded++;
                    }
                }

                if ($expanded > 0) {
                    continue;
                }
            }

            $skipped[] = 'group:' . $name . '（config/boss.php ext_fields 里没有这个组）';
        }

        if ($requested === []) {
            $warnings[] = '请求的扩展配置分组都不存在，扩展配置未复制';
        }

        return array_keys($requested);
    }

    /**
     * 扩展配置字段的零值兜底（与 BossRepository::defaultExtStorage() 同规则）。
     *
     * @param array<string,mixed> $spec
     */
    private function defaultExtValue(array $spec): int|string
    {
        $kind = (string) ($spec['kind'] ?? 'text');

        return ($kind === 'int' || $kind === 'bool') ? 0 : '';
    }
}
