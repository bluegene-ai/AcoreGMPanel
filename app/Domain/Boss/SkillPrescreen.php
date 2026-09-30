<?php
/**
 * File: app/Domain/Boss/SkillPrescreen.php
 * Purpose: 活动 Boss 技能池预筛：解析 boss.lua 静态表 + 客户端 DBC + world 库，
 * 按规则 R1–R13 给出每条技能的 红 / 黄 / 绿 结论与证据（规则口径见
 * docs/skill-prescreen-plan.md §4；阈值与白名单在 config/boss_prescreen.php）。
 *
 * 解析失败（取不到全部预设 / 条目缺 spellId 或 name）时返回 parse_error，
 * 绝不当成"0 个问题"：这一点由调用方（页面与 CLI）显式呈现。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\Boss;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Database;
use Acme\Panel\Support\GameNameResolver;
use PDO;
use RuntimeException;
use Throwable;

class SkillPrescreen
{
    public const LEVEL_RED = 'red';
    public const LEVEL_YELLOW = 'yellow';
    public const LEVEL_GREEN = 'green';

    /** 32 位 DBC 里的 -1（无限持续） */
    private const UINT32_MINUS_ONE = 4294967295;

    private array $config;
    private array $thresholds;
    private array $targets;
    private array $enumNames;
    /** 枚举名 → 值（只从核心头文件读；见 loadEnums()） */
    private array $enums = [];
    private array $warnings = [];
    private array $enumsFrom = ['attributes' => 'missing', 'effects' => 'missing', 'auras' => 'missing', 'targets' => 'missing'];

    public function __construct(?array $config = null)
    {
        $configured = $config ?? Config::get('boss_prescreen', []);
        $this->config = is_array($configured) ? $configured : [];
        $this->thresholds = (array) ($this->config['thresholds'] ?? []);
        $this->targets = (array) ($this->config['targets'] ?? []);
        $this->enumNames = (array) ($this->config['enum_names'] ?? []);
    }

    public function ruleVersion(): string
    {
        return (string) ($this->config['rule_version'] ?? 'prescreen-v1');
    }

    public function bossLuaPath(): string
    {
        return (string) ($this->config['boss_lua_path'] ?? '');
    }

    /**
     * 跑一遍预筛。
     *
     * @return array<string,mixed> 见 SkillPrescreenStore 的落盘结构；
     *         'ok' = false 且 'parse_error' 非空表示解析/IO 失败（不是"0 个问题"）。
     */
    public function run(?string $path = null): array
    {
        $this->warnings = [];

        $resolvedPath = trim((string) ($path ?? $this->bossLuaPath()));
        $checkedAt = time();
        $base = [
            'rule_version' => $this->ruleVersion(),
            'checked_at' => $checkedAt,
            'checked_at_text' => date('Y-m-d H:i:s', $checkedAt),
            'file_path' => $resolvedPath,
            'file_sha256' => '',
            'ok' => false,
            'parse_error' => '',
            'entries' => [],
            'summary' => [],
            'stats' => [],
            'warnings' => [],
        ];

        // 枚举真源缺失 = 环境没配好：显性失败，不猜值、也不显示"0 个问题"。
        try {
            $this->loadEnums();
        } catch (Throwable $exception) {
            $base['parse_error'] = $exception->getMessage();

            return $base;
        }

        if ($resolvedPath === '') {
            $base['parse_error'] = '未配置被检脚本路径：请设置环境变量 BOSS_LUA_PATH，'
                . '或在（被忽略的）config/generated/boss_prescreen.php 里给出 boss_lua_path。';

            return $base;
        }

        if (!is_file($resolvedPath)) {
            $base['parse_error'] = '找不到 boss.lua：' . $resolvedPath;

            return $base;
        }

        $source = @file_get_contents($resolvedPath);
        if ($source === false || $source === '') {
            $base['parse_error'] = 'boss.lua 读取失败（空文件或权限不足）。';

            return $base;
        }

        $base['file_sha256'] = hash('sha256', $source);

        try {
            $parsed = $this->parse($source);
        } catch (Throwable $exception) {
            $base['parse_error'] = $exception->getMessage();
            $base['warnings'] = $this->warnings;

            return $base;
        }

        $base['stats'] = $parsed['stats'];
        $base['presets'] = $parsed['preset_order'];

        try {
            $entries = $this->evaluate($parsed);
        } catch (Throwable $exception) {
            $base['parse_error'] = '规则计算失败：' . $exception->getMessage();
            $base['warnings'] = $this->warnings;

            return $base;
        }

        $summary = [self::LEVEL_RED => 0, self::LEVEL_YELLOW => 0, self::LEVEL_GREEN => 0];
        foreach ($entries as $entry) {
            $summary[$entry['level']] = ($summary[$entry['level']] ?? 0) + 1;
        }

        $baseline = (array) ($this->config['baseline'] ?? []);
        $drift = [];
        foreach ($baseline as $key => $expected) {
            $actual = (int) ($parsed['stats'][$key] ?? 0);
            if ((int) $expected !== $actual) {
                $drift[$key] = ['expected' => (int) $expected, 'actual' => $actual];
            }
        }

        $base['entries'] = $entries;
        $base['summary'] = [
            'red' => $summary[self::LEVEL_RED],
            'yellow' => $summary[self::LEVEL_YELLOW],
            'green' => $summary[self::LEVEL_GREEN],
            'total' => count($entries),
            'findings' => $summary[self::LEVEL_RED] + $summary[self::LEVEL_YELLOW],
        ];
        $base['baseline_drift'] = $drift;
        $base['enum_source'] = $this->enumsFrom;
        $base['warnings'] = array_values(array_unique(array_merge(
            $this->warnings,
            $drift === [] ? [] : ['条目数与基线不一致（脚本结构可能变了，请复核解析器）'],
        )));
        $base['ok'] = true;

        return $base;
    }

    // ---------------------------------------------------------------- 解析

    /**
     * @return array<string,mixed>
     */
    private function parse(string $source): array
    {
        // stripComments 把注释字符替换成空白但保留换行，所以两份文本的偏移一一对应，
        // 行号可以直接按原文算。
        $this->originalSource = $source;
        $clean = $this->stripComments($source);

        $orderBlock = $this->findBlockAfter($clean, 'SKILL_PRESET_ORDER');
        if ($orderBlock === null) {
            throw new RuntimeException('解析失败：找不到 SKILL_PRESET_ORDER。');
        }

        $presetOrder = [];
        if (preg_match_all('/"([a-z0-9_]+)"/i', $orderBlock['body'], $matches)) {
            $presetOrder = $matches[1];
        }

        if ($presetOrder === []) {
            throw new RuntimeException('解析失败：SKILL_PRESET_ORDER 里没有预设 key。');
        }

        $library = $this->findBlockAfter($clean, 'SKILL_PRESET_LIBRARY');
        if ($library === null) {
            throw new RuntimeException('解析失败：找不到 SKILL_PRESET_LIBRARY。');
        }

        $presets = [];
        foreach ($this->topLevelAssignments($library['body'], $library['body_offset']) as $assignment) {
            $key = $assignment['key'];
            $block = $this->extractBlock($clean, $assignment['value_offset']);
            if ($block === null) {
                continue;
            }

            $presets[$key] = [
                'key' => $key,
                'line' => $assignment['line'],
                'pools' => $this->parsePools($clean, $block),
                'combos' => $this->parseCombos($clean, $block),
                'openings' => $this->parseOpenings($clean, $block),
            ];
        }

        $poolEntries = 0;
        $openingEntries = 0;
        $comboChains = 0;
        $comboRefs = 0;
        $spellIds = [];

        foreach ($presets as $preset) {
            foreach ($preset['pools'] as $phase => $entries) {
                $poolEntries += count($entries);
                foreach ($entries as $entry) {
                    $spellIds[$entry['spell_id']] = true;
                }
            }
            $openingEntries += count($preset['openings']);
            foreach ($preset['openings'] as $entry) {
                $spellIds[$entry['spell_id']] = true;
            }
            $comboChains += count($preset['combos']);
            foreach ($preset['combos'] as $combo) {
                $comboRefs += count($combo['skills']);
                foreach ($combo['skills'] as $ref) {
                    $spellIds[$ref['spell_id']] = true;
                }
            }
        }

        $interrupts = [];
        $interruptBlock = $this->findBlockAfter($clean, 'INTERRUPT_SPELL_LIBRARY');
        if ($interruptBlock !== null) {
            foreach ($this->topLevelItems($clean, $interruptBlock['body'], $interruptBlock['body_offset']) as $item) {
                $fields = $this->parseFields($item['text']);
                if (!isset($fields['spellId'])) {
                    continue;
                }
                $interrupts[] = [
                    'spell_id' => $fields['spellId'],
                    'name' => (string) ($fields['name'] ?? ''),
                    'line' => $item['line'],
                ];
                $spellIds[$fields['spellId']] = true;
            }
        }

        $phaseSpells = [];
        foreach (['phase2SpellId', 'phase3SpellId'] as $key) {
            if (preg_match('/' . $key . '\s*=\s*(\d+)/', $clean, $m)) {
                $spellId = (int) $m[1];
                if ($spellId > 0) {
                    $phaseSpells[$key] = ['spell_id' => $spellId, 'line' => $this->lineOf($clean, (int) strpos($clean, $m[0]))];
                    $spellIds[$spellId] = true;
                }
            }
        }

        // 硬校验：预设数必须与 SKILL_PRESET_ORDER 一致；每个条目必须能取到 spellId 与 name
        $missing = array_values(array_diff($presetOrder, array_keys($presets)));
        if ($missing !== []) {
            throw new RuntimeException('解析失败：以下预设没解析出内容（' . implode('、', $missing) . '），'
                . '请检查是否改成了变量/表达式写法。');
        }

        $badEntries = [];
        foreach ($presets as $key => $preset) {
            foreach ($preset['pools'] as $phase => $entries) {
                foreach ($entries as $entry) {
                    if ($entry['spell_id'] <= 0 || $entry['name'] === '') {
                        $badEntries[] = $key . ' 阶段' . $phase . ' 第 ' . $entry['line'] . ' 行';
                    }
                }
            }
            foreach ($preset['openings'] as $entry) {
                if ($entry['spell_id'] <= 0 || $entry['name'] === '') {
                    $badEntries[] = $key . ' openingSkills 第 ' . $entry['line'] . ' 行';
                }
            }
        }

        if ($badEntries !== []) {
            throw new RuntimeException('解析失败：有条目取不到 spellId 或 name（'
                . implode('；', array_slice($badEntries, 0, 8)) . '）。'
                . '已知盲区：变量/表达式形式的 spellId、位置式写法、或技能表被改名/拆到别的文件。');
        }

        if ($spellIds === []) {
            throw new RuntimeException('解析失败：没有解析出任何 spellId。');
        }

        $conditions = $this->parseConditionWhitelist($clean);
        if ($conditions === []) {
            throw new RuntimeException('解析失败：没能从 SkillAI:CheckCondition 里解析出 condition 白名单。');
        }

        return [
            'preset_order' => $presetOrder,
            'presets' => $presets,
            'interrupts' => $interrupts,
            'phase_spells' => $phaseSpells,
            'conditions' => $conditions,
            'stats' => [
                'preset_count' => count($presets),
                'pool_entries' => $poolEntries,
                'opening_entries' => $openingEntries,
                'combo_chains' => $comboChains,
                'combo_refs' => $comboRefs,
                'interrupt_entries' => count($interrupts),
                'phase_spells' => count($phaseSpells),
                'unique_spell_ids' => count($spellIds),
            ],
            'spell_ids' => array_keys($spellIds),
        ];
    }

    /**
     * 每个预设的 skillPools：[阶段 => 条目列表]。
     *
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function parsePools(string $source, array $presetBlock): array
    {
        $poolsBlock = $this->findBlockAfter(substr($source, $presetBlock['body_offset'], $presetBlock['end'] - $presetBlock['body_offset']), 'skillPools');
        if ($poolsBlock === null) {
            return [];
        }

        $poolsOffset = $presetBlock['body_offset'] + $poolsBlock['body_offset'];
        $pools = [];

        foreach ($this->topLevelAssignments($poolsBlock['body'], $poolsOffset) as $assignment) {
            $phase = (int) trim($assignment['key'], '[]');
            if ($phase <= 0) {
                continue;
            }

            $phaseBlock = $this->extractBlock($source, $assignment['value_offset']);
            if ($phaseBlock === null) {
                continue;
            }

            $entries = [];
            foreach ($this->topLevelItems($source, $phaseBlock['body'], $phaseBlock['body_offset']) as $item) {
                $fields = $this->parseFields($item['text']);
                if (!array_key_exists('spellId', $fields)) {
                    continue;
                }

                $entries[] = [
                    'spell_id' => $fields['spellId'],
                    'name' => (string) ($fields['name'] ?? ''),
                    'target' => (string) ($fields['target'] ?? ''),
                    'priority' => $fields['priority'] ?? null,
                    'condition' => (string) ($fields['condition'] ?? ''),
                    'line' => $item['line'],
                ];
            }

            $pools[$phase] = $entries;
        }

        ksort($pools);

        return $pools;
    }

    /** @return array<int,array<string,mixed>> */
    private function parseCombos(string $source, array $presetBlock): array
    {
        $block = $this->findBlockAfter(substr($source, $presetBlock['body_offset'], $presetBlock['end'] - $presetBlock['body_offset']), 'comboChains');
        if ($block === null) {
            return [];
        }

        $offset = $presetBlock['body_offset'] + $block['body_offset'];
        $combos = [];

        foreach ($this->topLevelItems($source, $block['body'], $offset) as $item) {
            $name = '';
            if (preg_match('/name\s*=\s*"([^"]*)"/', $item['text'], $m)) {
                $name = $m[1];
            }

            // skills 列表要用括号配平取（里面的每个技能也是 {}，正则会被第一个 } 截断）
            $skills = [];
            $skillsPos = strpos($item['text'], 'skills');
            if ($skillsPos !== false) {
                $brace = strpos($item['text'], '{', $skillsPos);
                if ($brace !== false) {
                    $skillsBlock = $this->extractBlock($source, $item['offset'] + $brace);
                    if ($skillsBlock !== null
                        && preg_match_all('/\{\s*(\d+)\s*,\s*"([a-z_]+)"\s*\}/i', $skillsBlock['body'], $refs, PREG_SET_ORDER)) {
                        foreach ($refs as $ref) {
                            $skills[] = ['spell_id' => (int) $ref[1], 'target' => $ref[2]];
                        }
                    }
                }
            }

            $combos[] = ['name' => $name, 'skills' => $skills, 'line' => $item['line']];
        }

        return $combos;
    }

    /** @return array<int,array<string,mixed>> */
    private function parseOpenings(string $source, array $presetBlock): array
    {
        $block = $this->findBlockAfter(substr($source, $presetBlock['body_offset'], $presetBlock['end'] - $presetBlock['body_offset']), 'openingSkills');
        if ($block === null) {
            return [];
        }

        $offset = $presetBlock['body_offset'] + $block['body_offset'];
        $openings = [];

        foreach ($this->topLevelItems($source, $block['body'], $offset) as $item) {
            $fields = $this->parseFields($item['text']);
            if (!array_key_exists('spellId', $fields)) {
                continue;
            }

            $openings[] = [
                'spell_id' => $fields['spellId'],
                'name' => (string) ($fields['name'] ?? ''),
                'target' => (string) ($fields['target'] ?? ''),
                'line' => $item['line'],
            ];
        }

        return $openings;
    }

    /** SkillAI:CheckCondition 实际识别的 condition 键（从脚本解析，不手工维护）。 */
    private function parseConditionWhitelist(string $source): array
    {
        $position = strpos($source, 'function SkillAI:CheckCondition');
        if ($position === false) {
            return [];
        }

        // Lua 函数体用的是 end 而不是花括号，所以按"下一个顶层 function"截断，
        // 取不到就退化成固定长度窗口。
        $body = substr($source, $position);
        $nextFunction = strpos($body, "\nfunction ", 10);
        if ($nextFunction !== false) {
            $body = substr($body, 0, $nextFunction);
        }

        if (!preg_match_all('/condition\s*==\s*"([a-z_]+)"/i', $body, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /** @return array<string,int|string> */
    private function parseFields(string $item): array
    {
        $fields = [];

        if (preg_match('/spellId\s*=\s*([^,}\s]+)/', $item, $m)) {
            $raw = trim($m[1]);
            // 变量/表达式写法（spellId = SPELL_X）→ 记为 0，由硬校验报解析失败
            $fields['spellId'] = preg_match('/^\d+$/', $raw) === 1 ? (int) $raw : 0;
        }

        foreach (['name', 'target', 'condition'] as $key) {
            if (preg_match('/' . $key . '\s*=\s*"([^"]*)"/', $item, $m)) {
                $fields[$key] = $m[1];
            }
        }

        foreach (['priority', 'minCD', 'maxCD', 'cooldown', 'triggerChance'] as $key) {
            if (preg_match('/' . $key . '\s*=\s*(\d+)/', $item, $m)) {
                $fields[$key] = (int) $m[1];
            }
        }

        return $fields;
    }

    // ---------------------------------------------------------------- 规则

    /**
     * @param array<string,mixed> $parsed
     * @return array<int,array<string,mixed>>
     */
    private function evaluate(array $parsed): array
    {
        $spellIds = $parsed['spell_ids'];
        $this->knownSpellIds = $spellIds;
        $this->dbcNames = [];
        $facts = GameNameResolver::readSpellFacts($spellIds, array_keys(GameNameResolver::SPELL_FIELDS), $this->ruleVersion());

        $ranges = GameNameResolver::dbcLookup('spellrange', ['min_hostile', 'max_hostile'], $this->ruleVersion());
        $radii = GameNameResolver::dbcLookup('spellradius', ['radius_max'], $this->ruleVersion());
        $casts = GameNameResolver::dbcLookup('spellcasttimes', ['cast_time_ms'], $this->ruleVersion());
        $durations = GameNameResolver::dbcLookup('spellduration', ['duration'], $this->ruleVersion());

        $world = $this->worldHits($spellIds);

        $entries = [];
        foreach ($parsed['preset_order'] as $presetKey) {
            $preset = $parsed['presets'][$presetKey] ?? null;
            if ($preset === null) {
                continue;
            }

            $poolSpellIds = [];
            foreach ($preset['pools'] as $entries2) {
                foreach ($entries2 as $entry2) {
                    $poolSpellIds[$entry2['spell_id']] = true;
                }
            }

            // R12：某预设某阶段池为空 → 红
            for ($phase = 1; $phase <= 3; $phase++) {
                if (!isset($preset['pools'][$phase]) || $preset['pools'][$phase] === []) {
                    $entries[] = $this->entry([
                        'kind' => 'stage', 'preset' => $presetKey, 'stage' => $phase,
                        'line' => $preset['line'], 'spell_id' => 0, 'name' => '',
                        'verdicts' => [[
                            'rule' => 'R12', 'level' => self::LEVEL_RED,
                            'message' => '阶段 ' . $phase . ' 的技能池为空',
                            'evidence' => ['preset' => $presetKey, 'stage' => $phase],
                        ]],
                    ]);
                }
            }

            foreach ($preset['pools'] as $phase => $phaseEntries) {
                $seen = [];
                $names = [];
                foreach ($phaseEntries as $entry) {
                    $verdicts = $this->applyRules($entry, $facts, $ranges, $radii, $casts, $durations, $world, $parsed['conditions'], $seen, $names);
                    $entries[] = $this->entry([
                        'kind' => 'pool', 'preset' => $presetKey, 'stage' => (int) $phase,
                        'line' => $entry['line'], 'spell_id' => $entry['spell_id'], 'name' => $entry['name'],
                        'target' => $entry['target'], 'priority' => $entry['priority'], 'condition' => $entry['condition'],
                        'verdicts' => $verdicts,
                    ]);
                }
            }

            foreach ($preset['openings'] as $entry) {
                $seen = [];
                $names = [];
                $verdicts = $this->applyRules($entry, $facts, $ranges, $radii, $casts, $durations, $world, $parsed['conditions'], $seen, $names);
                $entries[] = $this->entry([
                    'kind' => 'opening', 'preset' => $presetKey, 'stage' => 0,
                    'line' => $entry['line'], 'spell_id' => $entry['spell_id'], 'name' => $entry['name'],
                    'target' => $entry['target'], 'priority' => null, 'condition' => '',
                    'verdicts' => $verdicts,
                ]);
            }

            // R12：连招引用的 spellId 必须都在同一预设的池内
            foreach ($preset['combos'] as $combo) {
                $bad = [];
                foreach ($combo['skills'] as $ref) {
                    if (!isset($poolSpellIds[$ref['spell_id']])) {
                        $bad[] = $ref['spell_id'];
                    }
                }
                if ($bad !== []) {
                    $entries[] = $this->entry([
                        'kind' => 'combo', 'preset' => $presetKey, 'stage' => 0,
                        'line' => $combo['line'], 'spell_id' => (int) $bad[0], 'name' => $combo['name'],
                        'verdicts' => [[
                            'rule' => 'R12', 'level' => self::LEVEL_RED,
                            'message' => '连招「' . $combo['name'] . '」引用了本预设池外的技能',
                            'evidence' => ['preset' => $presetKey, 'spell_ids' => $bad],
                        ]],
                    ]);
                }
            }
        }

        foreach ($parsed['interrupts'] as $entry) {
            $seen = [];
            $names = [];
            $verdicts = $this->applyRules($entry, $facts, $ranges, $radii, $casts, $durations, $world, $parsed['conditions'], $seen, $names);
            $entries[] = $this->entry([
                'kind' => 'interrupt', 'preset' => '', 'stage' => 0,
                'line' => $entry['line'], 'spell_id' => $entry['spell_id'], 'name' => $entry['name'],
                'verdicts' => $verdicts,
            ]);
        }

        foreach ($parsed['phase_spells'] as $key => $phase) {
            $seenPhase = [];
            $namesPhase = [];
            $verdicts = $this->applyRules(
                ['spell_id' => $phase['spell_id'], 'name' => '', 'target' => '', 'condition' => '', 'line' => $phase['line']],
                $facts, $ranges, $radii, $casts, $durations, $world, $parsed['conditions'], $seenPhase, $namesPhase
            );
            $entries[] = $this->entry([
                'kind' => 'phase', 'preset' => '', 'stage' => 0, 'line' => $phase['line'],
                'spell_id' => $phase['spell_id'], 'name' => (string) $key,
                'verdicts' => $verdicts,
            ]);
        }

        return $entries;
    }

    /**
     * 组装一条结论：整条的红黄绿 = 它所有判据里最重的那一级。
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function entry(array $data): array
    {
        $verdicts = array_values((array) ($data['verdicts'] ?? []));
        $level = self::LEVEL_GREEN;
        foreach ($verdicts as $verdict) {
            if (($verdict['level'] ?? '') === self::LEVEL_RED) {
                $level = self::LEVEL_RED;
                break;
            }
            if (($verdict['level'] ?? '') === self::LEVEL_YELLOW) {
                $level = self::LEVEL_YELLOW;
            }
        }

        return [
            'kind' => (string) ($data['kind'] ?? 'pool'),
            'preset' => (string) ($data['preset'] ?? ''),
            'stage' => (int) ($data['stage'] ?? 0),
            'line' => (int) ($data['line'] ?? 0),
            'spell_id' => (int) ($data['spell_id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'target' => (string) ($data['target'] ?? ''),
            'priority' => $data['priority'] ?? null,
            'condition' => (string) ($data['condition'] ?? ''),
            'level' => $level,
            'verdicts' => $verdicts,
        ];
    }

    /**
     * 对一条技能条目跑 R1–R13（跳过 R12 的组合完整性，它按预设/连招整体判定）。
     *
     * @param array<string,mixed> $entry
     * @param array<int,array<string,int>> $facts
     * @param array<int,array<string,int>> $ranges
     * @param array<int,array<string,int>> $radii
     * @param array<int,array<string,int>> $casts
     * @param array<int,array<string,int>> $durations
     * @param array<int,array<string,string>> $world
     * @param string[] $conditions
     * @param array<int,bool> $seen 同一预设同一阶段已见过的 spellId
     * @param array<string,int> $names 同一预设同一阶段已见过的名字
     * @return array<int,array<string,mixed>>
     */
    private function applyRules(
        array $entry,
        array $facts,
        array $ranges,
        array $radii,
        array $casts,
        array $durations,
        array $world,
        array $conditions,
        array &$seen,
        array &$names
    ): array {
        $verdicts = [];
        $spellId = (int) $entry['spell_id'];
        $fact = $facts[$spellId] ?? null;

        $red = static fn (string $rule, string $message, array $evidence = []): array => [
            'rule' => $rule, 'level' => self::LEVEL_RED, 'message' => $message, 'evidence' => $evidence,
        ];
        $yellow = static fn (string $rule, string $message, array $evidence = []): array => [
            'rule' => $rule, 'level' => self::LEVEL_YELLOW, 'message' => $message, 'evidence' => $evidence,
        ];

        // R1 存在性
        if ($fact === null) {
            $verdicts[] = $red('R1', 'Spell.dbc 里没有这个 spellId', ['spell_id' => $spellId]);

            return $verdicts;
        }

        // R2 名实比对（只标不判错）
        if ((string) ($entry['name'] ?? '') !== '') {
            $dbcName = $this->dbcName($spellId);
            if ($dbcName !== null && trim($dbcName) !== trim((string) $entry['name'])) {
                $verdicts[] = $yellow('R2', '脚本里的 name 与 DBC 名称不一致', [
                    'script' => (string) $entry['name'], 'dbc' => $dbcName,
                ]);
            }
        }

        // R3 射程（SpellRange 的 RangeMin/RangeMax 是 float 码）
        $range = $ranges[(int) $fact['range_index']] ?? null;
        if ($range !== null) {
            $max = (float) $range['max_hostile'];
            $min = (float) $range['min_hostile'];
            $selfOnlyRanges = array_map('intval', (array) ($this->thresholds['self_only_range_indexes'] ?? [1]));
            if ($min === 0.0 && $max === 0.0 && !in_array((int) $fact['range_index'], $selfOnlyRanges, true)) {
                $verdicts[] = $red('R3', '射程为 0/0（无距离限制）', ['range_index' => (int) $fact['range_index']]);
            } elseif ($max > (float) ($this->thresholds['max_range_yards'] ?? 99)) {
                $verdicts[] = $red('R3', '敌对射程 ' . $this->yards($max) . ' 码超过上限 '
                    . $this->yards((float) $this->thresholds['max_range_yards']) . ' 码', [
                    'range_index' => (int) $fact['range_index'], 'range_max' => $max,
                    'limit' => (float) $this->thresholds['max_range_yards'],
                ]);
            }
        }

        // R4 效果半径
        foreach ([1, 2, 3] as $slot) {
            $radiusIndex = (int) $fact['effect_radius_index_' . $slot];
            if ($radiusIndex <= 0 || !isset($radii[$radiusIndex])) {
                continue;
            }
            $radiusMax = (float) $radii[$radiusIndex]['radius_max'];
            if ($radiusMax >= (float) ($this->thresholds['warn_radius_yards'] ?? 40)) {
                $verdicts[] = $yellow('R4', '效果半径 ' . $this->yards($radiusMax) . ' 码偏大', [
                    'radius_index' => $radiusIndex, 'radius_max' => $radiusMax,
                    'limit' => (float) $this->thresholds['warn_radius_yards'],
                ]);
            }
        }

        // R5 目标类型
        $targetNames = ['targets' => (int) $fact['targets']];
        foreach ([1, 2, 3] as $slot) {
            $targetNames['implicit_a_' . $slot] = (int) $fact['effect_implicit_target_a_' . $slot];
            $targetNames['implicit_b_' . $slot] = (int) $fact['effect_implicit_target_b_' . $slot];
        }
        foreach ($targetNames as $where => $value) {
            if ($value <= 0) {
                continue;
            }
            $verdict = $this->targetVerdict($value);
            if ($verdict !== null) {
                $verdicts[] = $red('R5', '目标类型越界：' . $verdict, ['field' => $where, 'value' => $value]);
            }
        }

        // R6 被动 / 下次挥击
        // R6 被动 / 下次挥击（核心只见 ON_NEXT_SWING_NO_DAMAGE 的读取器 Spell.cpp:8410，
        // 没找到"直接拒绝施放"的门控：同类技能会被排成下次挥击，按方案仍判红，待人工确认）
        $attributes = (int) $fact['attributes'];
        foreach ([
            'SPELL_ATTR0_PASSIVE',
            'SPELL_ATTR0_ON_NEXT_SWING',
            'SPELL_ATTR0_ON_NEXT_SWING_NO_DAMAGE',
        ] as $name) {
            $bit = (int) ($this->enums['attributes'][$name] ?? 0);
            if ($bit > 0 && ($attributes & $bit) !== 0) {
                $verdicts[] = $red('R6', '属性位命中 ' . $name
                    . '（不可主动施放；核心未发现直接拒绝的门控，待人工确认）', [
                    'attributes' => $attributes, 'bit' => $bit,
                ]);
            }
        }

        // R7 外部条件依赖（0xFFFFFFFF 在这几列里是"无/不限制"，不是条件）
        foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $slot) {
            $reagent = (int) $fact['reagent_' . $slot];
            if ($reagent !== 0 && $reagent !== self::UINT32_MINUS_ONE) {
                $verdicts[] = $red('R7', '需要材料（Reagent[' . ($slot - 1) . ']）', ['reagent' => $reagent]);
                break;
            }
        }
        $equipped = (int) $fact['equipped_item_class'];
        if ($equipped > 0 && $equipped !== self::UINT32_MINUS_ONE) {
            // 核心只在 caster 是玩家时强制装备类别；生物施法者只在"无法使用该攻击类型"（被缴械）时才失败，
            // 所以对 Boss 只是提示项，不是必改项（Spell.cpp:7477-7491）。
            $verdicts[] = $yellow('R7', '依赖装备类别：仅对玩家强制，生物只在无法使用该攻击类型时才被拒（Spell.cpp:7477-7491）', [
                'equipped_item_class' => $equipped,
            ]);
        }
        if ((int) $fact['stances'] !== 0) {
            $verdicts[] = $red('R7', '需要形态（Stances）——核心未发现强制门控，按方案仍判红，待人工确认', [
                'stances' => (int) $fact['stances'],
            ]);
        }
        if ((int) $fact['spell_focus_object'] !== 0) {
            $verdicts[] = $red('R7', '需要施法焦点：核心会搜附近的焦点 GameObject，找不到即失败（Spell.cpp:8083-8097）', [
                'focus' => (int) $fact['spell_focus_object'],
            ]);
        }
        foreach ([1, 2] as $slot) {
            if ((int) $fact['totem_' . $slot] !== 0 || (int) $fact['totem_category_' . $slot] !== 0) {
                $verdicts[] = $red('R7', '需要图腾（Totem/TotemCategory）', [
                    'totem' => (int) $fact['totem_' . $slot], 'category' => (int) $fact['totem_category_' . $slot],
                ]);
                break;
            }
        }

        $summonEffects = array_map('intval', (array) ($this->enums['effects'] ?? []));
        foreach ([1, 2, 3] as $slot) {
            $effect = (int) $fact['effect_' . $slot];
            if ($effect > 0 && in_array($effect, $summonEffects, true)) {
                $verdicts[] = $yellow('R7', '效果含召唤/机关/传送类（effect=' . $effect . '）', ['effect' => $effect]);
            }
        }
        $controlAuras = array_map('intval', (array) ($this->enums['auras'] ?? []));
        foreach ([1, 2, 3] as $slot) {
            $aura = (int) $fact['effect_apply_aura_name_' . $slot];
            if ($aura > 0 && in_array($aura, $controlAuras, true)) {
                $verdicts[] = $yellow('R7', '光环含控制载具类（aura=' . $aura . '）', ['aura' => $aura]);
            }
        }

        // R8 无实际效果
        if ((int) $fact['effect_1'] === 0 && (int) $fact['effect_2'] === 0 && (int) $fact['effect_3'] === 0) {
            $verdicts[] = $red('R8', '三个效果位全为 0（无实际效果）');
        }

        // R9 读条时长
        $cast = $casts[(int) $fact['casting_time_index']] ?? null;
        if ($cast !== null) {
            $castMs = (int) $cast['cast_time_ms'];
            if ($castMs > (int) ($this->thresholds['max_cast_ms'] ?? 3000)) {
                $verdicts[] = $yellow('R9', '读条 ' . $castMs . 'ms 偏长（读条模式下可被稳定打断）', [
                    'casting_time_index' => (int) $fact['casting_time_index'], 'cast_ms' => $castMs,
                    'limit' => (int) $this->thresholds['max_cast_ms'],
                ]);
            }
        }

        // R10 无限光环
        $duration = $durations[(int) $fact['duration_index']] ?? null;
        if ($duration !== null && (int) $duration['duration'] === self::UINT32_MINUS_ONE) {
            $verdicts[] = $yellow('R10', 'Duration = -1（无限持续，会被反复刷新）', [
                'duration_index' => (int) $fact['duration_index'],
            ]);
        }

        // R11 核心机制依赖
        $hits = $world[$spellId] ?? [];
        if (!empty($hits['required'])) {
            $verdicts[] = $red('R11', '命中 spell_required（需要任务/天赋）', ['rows' => $hits['required']]);
        }
        if (!empty($hits['script'])) {
            $verdicts[] = $yellow('R11', '命中 spell_script_names（行为依赖 encounter 脚本）', ['rows' => $hits['script']]);
        }
        if (!empty($hits['target_position'])) {
            $verdicts[] = $yellow('R11', '命中 spell_target_position（固定落点）', ['rows' => $hits['target_position']]);
        }

        // R12 condition 白名单
        $condition = trim((string) ($entry['condition'] ?? ''));
        if ($condition !== '' && !in_array($condition, $conditions, true)) {
            $verdicts[] = $red('R12', 'condition「' . $condition . '」不在脚本识别的键集合里（游戏里会被静默放行）', [
                'condition' => $condition,
            ]);
        }

        // R13 重复与命名
        if ($spellId > 0) {
            if (isset($seen[$spellId])) {
                $verdicts[] = $yellow('R13', '同一预设同一阶段重复出现该 spellId', ['spell_id' => $spellId]);
            }
            $seen[$spellId] = true;
        }
        $name = trim((string) ($entry['name'] ?? ''));
        if ($name !== '') {
            if (isset($names[$name]) && $names[$name] !== $spellId) {
                $verdicts[] = $yellow('R13', '同一预设同一阶段里不同 spellId 同名「' . $name . '」', [
                    'name' => $name, 'spell_id' => $spellId, 'other' => $names[$name],
                ]);
            }
            $names[$name] = $spellId;
        }
        if (array_key_exists('priority', $entry) && $entry['priority'] !== null) {
            $priority = (int) $entry['priority'];
            $min = (int) ($this->thresholds['priority_min'] ?? 1);
            $max = (int) ($this->thresholds['priority_max'] ?? 8);
            if ($priority < $min || $priority > $max) {
                $verdicts[] = $yellow('R13', 'priority ' . $priority . ' 不在 ' . $min . '–' . $max . ' 之间', [
                    'priority' => $priority,
                ]);
            }
        }
        $target = trim((string) ($entry['target'] ?? ''));
        if ($target !== '') {
            $allowed = array_map('strval', (array) ($this->targets['target_values'] ?? ['victim', 'self']));
            if (!in_array($target, $allowed, true)) {
                $verdicts[] = $yellow('R13', 'target「' . $target . '」不是 victim/self', ['target' => $target]);
            }
        }

        return $verdicts;
    }

    /** 码 → 文案（去掉多余的小数位：8 → "8"，8.5 → "8.5"） */
    private function yards(float $value): string
    {
        $rounded = round($value, 2);

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }

    /** R5：给定目标类型值 → 违规说明（合法返回 null）。 */
    private function targetVerdict(int $value): ?string
    {
        $names = $this->targetNames();
        $name = $names[$value] ?? null;

        $denyNames = (array) ($this->targets['deny_names'] ?? []);
        $denySubstrings = (array) ($this->targets['deny_substrings'] ?? []);
        $allowNames = (array) ($this->targets['allow_names'] ?? []);
        $allowPrefixes = (array) ($this->targets['allow_prefixes'] ?? []);

        if ($name === null) {
            return '未在核心枚举里找到值 ' . $value;
        }

        if (in_array($name, $denyNames, true)) {
            return $name . '（友方/队伍类，Boss 没有队伍）';
        }
        foreach ($denySubstrings as $substring) {
            if ($substring !== '' && str_contains($name, (string) $substring)) {
                return $name . '（友方/队伍/宠物类）';
            }
        }

        if (in_array($name, $allowNames, true)) {
            return null;
        }
        foreach ($allowPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($name, (string) $prefix)) {
                return null;
            }
        }

        return $name . '（不在白名单内）';
    }

    /**
     * world 库三张表的命中（R11）。表不存在/读不到只记 warning，不当作红。
     *
     * @param int[] $spellIds
     * @return array<int,array<string,array<int,int>>>
     */
    private function worldHits(array $spellIds): array
    {
        if ($spellIds === []) {
            return [];
        }

        $tables = (array) ($this->config['world_tables'] ?? []);
        $map = [
            'script' => ['table' => 'spell_script_names', 'column' => 'spell_id'],
            'required' => ['table' => 'spell_required', 'column' => 'spell_id'],
            'target_position' => ['table' => 'spell_target_position', 'column' => 'ID'],
        ];
        foreach ($map as $kind => $default) {
            $configured = $tables[$kind] ?? null;
            if (is_array($configured)) {
                $map[$kind]['table'] = (string) ($configured['table'] ?? $default['table']);
                $map[$kind]['column'] = (string) ($configured['column'] ?? $default['column']);
            }
        }

        $hits = [];
        try {
            $pdo = Database::forServer(\Acme\Panel\Support\ServerContext::currentId(), 'world');
        } catch (Throwable $exception) {
            $this->warnings[] = 'world 库不可用，R11 未执行：' . $exception->getMessage();

            return [];
        }

        foreach ($map as $kind => $spec) {
            $table = $spec['table'];
            $column = $spec['column'];
            try {
                $placeholders = implode(',', array_fill(0, count($spellIds), '?'));
                $stmt = $pdo->prepare(
                    'SELECT `' . $column . '` AS sid, COUNT(*) AS c FROM `' . $table . '`'
                    . ' WHERE `' . $column . '` IN (' . $placeholders . ') GROUP BY `' . $column . '`'
                );
                $stmt->execute($spellIds);
                foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
                    $hits[(int) $row['sid']][$kind][] = (int) $row['c'];
                }
            } catch (Throwable $exception) {
                $this->warnings[] = 'R11 跳过 ' . $table . '：' . $exception->getMessage();
            }
        }

        return $hits;
    }

    // ---------------------------------------------------------------- 枚举

    /**
     * 从核心头文件取枚举值（目标类型 / 属性位 / 效果 / 光环）。
     *
     * 两份头文件（SharedDefines.h、SpellAuraDefines.h）路径为空或读不到 → 抛异常
     * （run() 转成 parse_error）；解析不出枚举值同样抛。不做"用兜底数字"的降级：
     * R5 白名单的口径必须与核心一致，猜出来的值只会误报。
     */
    private function loadEnums(): void
    {
        $sources = [
            'core_shared_defines' => (string) ($this->config['core_shared_defines'] ?? ''),
            'core_aura_defines' => (string) ($this->config['core_aura_defines'] ?? ''),
        ];

        $values = [];
        foreach ($sources as $key => $path) {
            $envName = $key === 'core_shared_defines' ? 'CORE_SHARED_DEFINES' : 'CORE_AURA_DEFINES';
            $path = trim($path);
            if ($path === '') {
                throw new RuntimeException('未配置核心头文件（' . $envName . '）：请设置该环境变量，'
                    . '或在（被忽略的）config/generated/boss_prescreen.php 里给出 ' . $key . '。');
            }
            if (!is_file($path)) {
                throw new RuntimeException('读不到核心头文件（' . $envName . '）：' . $path);
            }

            $header = @file_get_contents($path);
            if ($header === false || $header === '') {
                throw new RuntimeException('核心头文件读取失败（空文件或权限不足）：' . $path);
            }

            // 枚举行尾常带 // 注释，先把行注释去掉再匹配
            $header = (string) preg_replace('#//[^\n]*#', '', $header);
            if (preg_match_all('/^\s*(TARGET_[A-Z0-9_]+|SPELL_ATTR0_[A-Z0-9_]+|SPELL_EFFECT_[A-Z0-9_]+|SPELL_AURA_[A-Z0-9_]+)\s*=\s*(0x[0-9A-Fa-f]+|\d+)\s*,?\s*$/m', $header, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $values[$match[1]] = (int) (str_starts_with($match[2], '0x') ? hexdec($match[2]) : $match[2]);
                }
            }
        }

        if ($values === []) {
            throw new RuntimeException('核心头文件里没解析出枚举值（格式变了？）。');
        }

        $missing = [];
        foreach (['attributes', 'effects', 'auras'] as $group) {
            foreach ((array) ($this->enumNames[$group] ?? []) as $name) {
                if (!array_key_exists((string) $name, $values)) {
                    $missing[] = (string) $name;
                    continue;
                }
                $this->enums[$group][(string) $name] = $values[(string) $name];
                $this->enumsFrom[$group] = 'header';
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('核心头文件里没有这些枚举（名字对不上？）：' . implode('、', $missing));
        }

        $targets = [];
        foreach ($values as $name => $value) {
            if (str_starts_with($name, 'TARGET_')) {
                $targets[$value] = $name;
            }
        }
        if ($targets === []) {
            throw new RuntimeException('核心头文件里没解析出 TARGET_* 枚举。');
        }

        $this->targetValues = $targets;
        $this->enumsFrom['targets'] = 'header';
    }

    /** @var array<int,string> */
    private array $targetValues = [];

    /** @var int[] 本次预筛涉及的全部 spellId（R2 名字表按需加载用） */
    private array $knownSpellIds = [];

    /** @var array<int,string> spellId => DBC 名称（懒加载，只读一次） */
    private array $dbcNames = [];

    /** 技能名（Spell.dbc）：用于 R2 名实比对；取不到返回 null（跳过该条判定）。 */
    private function dbcName(int $spellId): ?string
    {
        if ($this->dbcNames === [] && $this->knownSpellIds !== []) {
            $this->dbcNames = GameNameResolver::spellNamesFromDbc($this->knownSpellIds, $this->ruleVersion());
        }

        return $this->dbcNames[$spellId] ?? null;
    }

    /** @return array<int,string> */
    private function targetNames(): array
    {
        return $this->targetValues;
    }

    // ---------------------------------------------------------------- Lua 解析工具

    /** 去掉 Lua 注释（保留字符串内的 -- ）。 */
    private function stripComments(string $source): string
    {
        $out = '';
        $length = strlen($source);
        $inString = false;
        $quote = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];

            if ($inString) {
                $out .= $char;
                if ($char === '\\') {
                    if ($i + 1 < $length) {
                        $out .= $source[$i + 1];
                        $i++;
                    }
                    continue;
                }
                if ($char === $quote) {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $quote = $char;
                $out .= $char;
                continue;
            }

            if ($char === '-' && $i + 1 < $length && $source[$i + 1] === '-') {
                // 行注释或块注释：注释内容整体替换成空白（保留行数，行号才不会漂）
                if (substr($source, $i, 4) === '--[[') {
                    $end = strpos($source, ']]', $i + 4);
                    $end = $end === false ? $length : $end + 2;
                } else {
                    $end = strpos($source, "\n", $i);
                    $end = $end === false ? $length : $end;
                }

                for ($j = $i; $j < $end; $j++) {
                    $out .= $source[$j] === "\n" ? "\n" : ' ';
                }
                $i = $end - 1;
                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    /**
     * 从 $marker 之后的第一个 '{' 起做括号配平，返回块内容与其位置。
     *
     * @return array{body:string,body_offset:int,end:int,line:int}|null
     */
    private function findBlockAfter(string $source, string $marker): ?array
    {
        $position = strpos($source, $marker);
        if ($position === false) {
            return null;
        }

        $brace = strpos($source, '{', $position);
        if ($brace === false) {
            return null;
        }

        return $this->extractBlock($source, $brace);
    }

    /**
     * @return array{body:string,body_offset:int,end:int,line:int}|null
     */
    private function extractBlock(string $source, int $openBrace): ?array
    {
        if ($openBrace < 0 || ($source[$openBrace] ?? '') !== '{') {
            return null;
        }

        $depth = 0;
        $length = strlen($source);
        $inString = false;
        $quote = '';

        for ($i = $openBrace; $i < $length; $i++) {
            $char = $source[$i];
            if ($inString) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $inString = false;
                }
                continue;
            }
            if ($char === '"' || $char === "'") {
                $inString = true;
                $quote = $char;
                continue;
            }
            if ($char === '{') {
                $depth++;
                continue;
            }
            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return [
                        'body' => substr($source, $openBrace + 1, $i - $openBrace - 1),
                        'body_offset' => $openBrace + 1,
                        'end' => $i,
                        'line' => $this->lineOf($source, $openBrace),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * 顶层 "键 = { ... }" 赋值。
     *
     * @return array<int,array{key:string,value_offset:int,line:int}>
     */
    private function topLevelAssignments(string $body, int $bodyOffset): array
    {
        $assignments = [];
        foreach ($this->topLevelItemsBody($body, $bodyOffset) as $item) {
            if (!preg_match('/^\s*(?:\[(\d+)\]|([A-Za-z_][A-Za-z0-9_]*))\s*=\s*\{/', $item['text'], $m)) {
                continue;
            }
            $brace = strpos($item['text'], '{');
            $assignments[] = [
                'key' => $m[1] !== '' ? '[' . $m[1] . ']' : $m[2],
                'value_offset' => $item['offset'] + (int) $brace,
                'line' => $item['line'],
            ];
        }

        return $assignments;
    }

    /**
     * 顶层逗号分隔的条目（保留原文与所在行）。
     *
     * @return array<int,array{text:string,offset:int,line:int}>
     */
    private function topLevelItems(string $source, string $body, int $bodyOffset): array
    {
        return $this->topLevelItemsBody($body, $bodyOffset);
    }

    /**
     * @return array<int,array{text:string,offset:int,line:int}>
     */
    private function topLevelItemsBody(string $body, int $bodyOffset): array
    {
        $items = [];
        $depth = 0;
        $inString = false;
        $quote = '';
        $start = 0;
        $length = strlen($body);

        for ($i = 0; $i <= $length; $i++) {
            $char = $i < $length ? $body[$i] : ',';

            if ($inString) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $quote = $char;
                continue;
            }

            if ($char === '{' || $char === '[' || $char === '(') {
                $depth++;
                continue;
            }
            if ($char === '}' || $char === ']' || $char === ')') {
                $depth--;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $text = substr($body, $start, $i - $start);
                if (trim($text) !== '') {
                    $items[] = [
                        'text' => $text,
                        'offset' => $bodyOffset + $start,
                        'line' => $this->lineOf($this->originalSource ?? '', $bodyOffset + $start),
                    ];
                }
                $start = $i + 1;
            }
        }

        return $items;
    }

    /** 当前解析的原文（topLevelItemsBody 需要按原文算行号）。 */
    private ?string $originalSource = null;

    private function lineOf(string $source, int $offset): int
    {
        if ($offset <= 0) {
            return 1;
        }

        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }
}
