<?php
/**
 * File: app/Support/GameNameResolver.php
 * Purpose: Resolve game object names (quest / faction / spell / skill / achievement /
 * achievementcriteria / item) from the realm database and the client DBC files.
 *
 * 每个 realm + 语言只加载一次（进程内静态缓存 + 磁盘 JSON），避免每次请求解析 49MB 的 Spell.dbc；
 * 数据库优先，DBC 表为空或缺失时回退解析 DataDir/dbc 下的 dbc 文件；解析失败只返回 null，页面继续显示原始 ID。
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;
use Acme\Panel\Core\Database;
use Acme\Panel\Core\Lang;
use PDO;
use Throwable;

final class GameNameResolver
{
    /** 支持的查询类型 */
    public const TYPES = ['spell', 'skill', 'achievement', 'achievementcriteria', 'quest', 'faction', 'item'];

    private const CACHE_VERSION = 3;

    /**
     * DBC 文件里名字字段的起点下标、可用于探测语种的字符掩码字段（按 AzerothCore DBCStructure.h）。
     * 客户端把 16 个语种的字符串连续存放，但实际安装的 DBC 往往只填 1~2 个语种且不一定是标准顺序，
     * 所以语种槽位在运行时探测（见 dbcLocaleOffset）。
     *
     * 表尾四项（spellrange / spellradius / spellcasttimes / spellduration）没有名字字段，
     * 是数值查表：'fields' 给出 字段名 => 下标（见 dbcLookup()），不参与 resolveMany/map。
     */
    private const DBC_SPECS = [
        'faction' => ['file' => 'Faction.dbc', 'idField' => 0, 'nameField' => 23, 'maskField' => 39, 'order' => self::DBC_LOCALE_ORDER],
        'skill' => ['file' => 'SkillLine.dbc', 'idField' => 0, 'nameField' => 3, 'maskField' => 19, 'order' => self::DBC_LOCALE_ORDER],
        'spell' => ['file' => 'Spell.dbc', 'idField' => 0, 'nameField' => 136, 'maskField' => 152, 'order' => self::DBC_LOCALE_ORDER],
        'achievement' => ['file' => 'Achievement.dbc', 'idField' => 0, 'nameField' => 4, 'maskField' => 20, 'order' => self::DBC_LOCALE_ORDER],
        'spellrange' => [
            'file' => 'SpellRange.dbc',
            'idField' => 0,
            'fields' => ['min_hostile' => 1, 'min_friendly' => 2, 'max_hostile' => 3, 'max_friendly' => 4, 'flags' => 5],
            // RangeMin/RangeMax 在 DBC 里是 float（码），不按 uint32 读
            'float_fields' => ['min_hostile', 'min_friendly', 'max_hostile', 'max_friendly'],
        ],
        'spellradius' => [
            'file' => 'SpellRadius.dbc',
            'idField' => 0,
            'fields' => ['radius_min' => 1, 'radius_per_level' => 2, 'radius_max' => 3],
            // 半径同样是 float（码）
            'float_fields' => ['radius_min', 'radius_per_level', 'radius_max'],
        ],
        'spellcasttimes' => [
            'file' => 'SpellCastTimes.dbc',
            'idField' => 0,
            'fields' => ['cast_time_ms' => 1],
        ],
        'spellduration' => [
            'file' => 'SpellDuration.dbc',
            'idField' => 0,
            'fields' => ['duration' => 1, 'duration_max' => 3],
        ],
    ];

    /**
     * Spell.dbc 的可按需字段（下标见 DBCStructure.h / 预筛方案 §3）。
     * 只读调用方点名的那几个字段，不把 234 个字段常驻内存。
     */
    public const SPELL_FIELDS = [
        'id' => 0,
        'attributes' => 4,
        'attributes_ex' => 5,
        'attributes_ex2' => 6,
        'attributes_ex3' => 7,
        'attributes_ex4' => 8,
        'attributes_ex5' => 9,
        'attributes_ex6' => 10,
        'attributes_ex7' => 11,
        'stances' => 12,
        'stances_not' => 14,
        'targets' => 16,
        'casting_time_index' => 28,
        'duration_index' => 40,
        'range_index' => 46,
        'reagent_1' => 52,
        'reagent_2' => 53,
        'reagent_3' => 54,
        'reagent_4' => 55,
        'reagent_5' => 56,
        'reagent_6' => 57,
        'reagent_7' => 58,
        'reagent_8' => 59,
        'equipped_item_class' => 68,
        'effect_1' => 71,
        'effect_2' => 72,
        'effect_3' => 73,
        'effect_implicit_target_a_1' => 86,
        'effect_implicit_target_a_2' => 87,
        'effect_implicit_target_a_3' => 88,
        'effect_implicit_target_b_1' => 89,
        'effect_implicit_target_b_2' => 90,
        'effect_implicit_target_b_3' => 91,
        'effect_radius_index_1' => 92,
        'effect_radius_index_2' => 93,
        'effect_radius_index_3' => 94,
        'effect_apply_aura_name_1' => 95,
        'effect_apply_aura_name_2' => 96,
        'effect_apply_aura_name_3' => 97,
        'effect_item_type_1' => 107,
        'effect_item_type_2' => 108,
        'effect_item_type_3' => 109,
        'effect_misc_value_1' => 110,
        'effect_misc_value_2' => 111,
        'effect_misc_value_3' => 112,
        'spell_focus_object' => 118,
        'totem_1' => 121,
        'totem_2' => 122,
        'totem_category_1' => 123,
        'totem_category_2' => 124,
        'spell_name' => 136,
        'max_affected_targets' => 212,
        'school_mask' => 225,
    ];

    private const DBC_ROW_CACHE_VERSION = 2;

    /** 客户端 16 个语种的标准顺序（DBC 里以此顺序连续存放字符串） */
    private const DBC_LOCALE_ORDER = [
        'enUS', 'koKR', 'frFR', 'deDE', 'enCN', 'zhCN',
        'enTW', 'zhTW', 'esES', 'esMX', 'ruRU', 'ptPT',
        'ptBR', 'itIT', 'Unk', 'Unk2',
    ];

    /** @var array<string, array<int, string>> 已就绪的 id => name 表 */
    private static array $maps = [];

    /** @var array<string, array<int, array<string,int>>> 按需数值表（表 + 字段集合 + 规则版本） */
    private static array $rowCache = [];

    /** @var array<string, array<int, string>> 按 id 直查到的名字（物品/任务的按需路径） */
    private static array $nameById = [];

    /** @var array<string, array<int, true>> 按 id 查询后确认无名，同一请求内不再回库 */
    private static array $nameMisses = [];

    /**
     * 批量解析，返回 id => name（未解析到的 id 不在结果里）。
     *
     * 物品/任务走 `WHERE id IN (...)` 直查：这两个类型的基础表有 46k / 9.5k 行，
     * 整表映射（1.4 MB JSON）对"只问几个名字"的批量场景不划算。其余类型仍走名字映射。
     *
     * @param int[] $ids
     * @return array<int, string>
     */
    public static function resolveMany(string $type, array $ids): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
            return $id > 0;
        })));

        if ($ids === []) {
            return [];
        }

        if ($type === 'item' || $type === 'quest') {
            return self::namesByIds($type, $ids);
        }

        $map = self::map($type);
        if ($map === []) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($map[$id]) && $map[$id] !== '') {
                $out[$id] = $map[$id];
            }
        }

        return $out;
    }

    /** 单个解析，解析不到返回 null。 */
    public static function resolve(string $type, int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $map = self::map(strtolower(trim($type)));

        return $map[$id] ?? null;
    }

    /**
     * 只取某个类型的名字表（id => name），用于页面一次性批量解析。
     * @return array<int, string>
     */
    public static function map(string $type): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            return [];
        }

        $key = self::cacheKey($type);
        if (array_key_exists($key, self::$maps)) {
            return self::$maps[$key];
        }

        self::$maps[$key] = [];

        $fromDisk = self::loadFromDisk($type);
        if ($fromDisk !== null) {
            self::$maps[$key] = $fromDisk;
            return self::$maps[$key];
        }

        $built = self::build($type);
        self::$maps[$key] = $built;
        self::persist($type, $built);

        return self::$maps[$key];
    }

    /** 清空进程内缓存（CLI 工具或切换服务器后使用）。 */
    public static function flush(): void
    {
        self::$maps = [];
        self::$rowCache = [];
        self::$nameById = [];
        self::$nameMisses = [];
    }

    /**
     * 按 id 直查物品/任务名，语义与整表构建一致：语种表在该语种下只要有名字就覆盖基础表，
     * 否则回退基础表列；查到的名字按 id 记忆在请求内复用。
     *
     * @param int[] $ids
     * @return array<int, string>
     */
    private static function namesByIds(string $type, array $ids): array
    {
        $out = [];
        $pending = [];
        foreach ($ids as $id) {
            $name = self::$nameById[$type][$id] ?? null;
            if ($name !== null) {
                $out[$id] = $name;
                continue;
            }
            if (isset(self::$nameMisses[$type][$id])) {
                continue;
            }
            $pending[] = $id;
        }

        if ($pending === []) {
            return $out;
        }

        $fetched = [];
        foreach (array_chunk($pending, 500) as $chunk) {
            $fetched += $type === 'quest' ? self::questNamesByIds($chunk) : self::itemNamesByIds($chunk);
        }

        foreach ($pending as $id) {
            $name = $fetched[$id] ?? '';
            if ($name !== '') {
                self::$nameById[$type][$id] = $name;
                $out[$id] = $name;
                continue;
            }
            self::$nameMisses[$type][$id] = true;
        }

        return $out;
    }

    /**
     * 物品名：本地化表优先，逐行回退 `item_template.name`（与 MailRepository 的 COALESCE 回退一致）。
     *
     * 语种列按请求的 id 走主键（ID,locale）的连接查找，不整表扫描——整表按语种取名字会退化成
 * 按 locale 过滤会让 (ID,locale) 主键退化为全索引扫描；按 id 的 IN 查询可走主键。
     *
     * @param int[] $ids
     * @return array<int, string>
     */
    private static function itemNamesByIds(array $ids): array
    {
        $columns = self::tableColumns('item_template');
        if ($columns === []) {
            return [];
        }

        $baseNameColumn = self::firstExistingColumn($columns, ['name', 'Name']);

        foreach (['item_template_locale', 'locales_item'] as $localeTable) {
            if (!self::tableExists($localeTable)) {
                continue;
            }
            $localeColumns = self::tableColumns($localeTable);
            $nameColumn = self::firstExistingColumn($localeColumns, ['Name', 'name']);
            $localeColumn = self::firstExistingColumn($localeColumns, ['locale']);
            if ($nameColumn === null || $localeColumn === null) {
                continue;
            }

            [$in, $params] = self::idFilter($ids);
            $params[':locale'] = self::localeKey();
            $localeName = self::quoteIdentifier($nameColumn);
            $localeKeyColumn = self::quoteIdentifier($localeColumn);

            if ($baseNameColumn === null) {
                return self::queryMap(
                    'SELECT ID AS id, ' . $localeName . ' AS name'
                    . ' FROM ' . self::quoteIdentifier($localeTable)
                    . ' WHERE ' . $localeKeyColumn . ' = :locale AND ID IN (' . $in . ')',
                    $params
                );
            }

            return self::queryMap(
                'SELECT i.entry AS id, COALESCE(NULLIF(TRIM(li.' . $localeName . "), ''), i."
                . self::quoteIdentifier($baseNameColumn) . ') AS name'
                . ' FROM item_template i LEFT JOIN ' . self::quoteIdentifier($localeTable) . ' li'
                . ' ON li.ID = i.entry AND li.' . $localeKeyColumn . ' = :locale'
                . ' WHERE i.entry IN (' . $in . ')',
                $params
            );
        }

        if ($baseNameColumn === null) {
            return [];
        }

        [$in, $params] = self::idFilter($ids);

        return self::queryMap(
            'SELECT entry AS id, ' . self::quoteIdentifier($baseNameColumn) . ' AS name'
            . ' FROM item_template WHERE entry IN (' . $in . ')',
            $params
        );
    }

    /**
     * 任务名：quest_template_locale.Title 优先，逐行回退 quest_template.LogTitle。
     * @param int[] $ids
     * @return array<int, string>
     */
    private static function questNamesByIds(array $ids): array
    {
        $columns = self::tableColumns('quest_template');
        if ($columns === []) {
            return [];
        }

        $baseColumn = self::firstExistingColumn($columns, ['LogTitle', 'Title']);

        if (self::tableExists('quest_template_locale')) {
            $localeColumns = self::tableColumns('quest_template_locale');
            $titleColumn = self::firstExistingColumn($localeColumns, ['Title', 'LogTitle']);
            $localeColumn = self::firstExistingColumn($localeColumns, ['locale']);
            if ($titleColumn !== null && $localeColumn !== null) {
                [$in, $params] = self::idFilter($ids);
                $params[':locale'] = self::localeKey();
                $title = self::quoteIdentifier($titleColumn);
                $localeKeyColumn = self::quoteIdentifier($localeColumn);

                if ($baseColumn === null) {
                    return self::queryMap(
                        'SELECT ID AS id, ' . $title . ' AS name'
                        . ' FROM quest_template_locale'
                        . ' WHERE ' . $localeKeyColumn . ' = :locale AND ID IN (' . $in . ')',
                        $params
                    );
                }

                return self::queryMap(
                    'SELECT q.ID AS id, COALESCE(NULLIF(TRIM(ql.' . $title . "), ''), q."
                    . self::quoteIdentifier($baseColumn) . ') AS name'
                    . ' FROM quest_template q LEFT JOIN quest_template_locale ql'
                    . ' ON ql.ID = q.ID AND ql.' . $localeKeyColumn . ' = :locale'
                    . ' WHERE q.ID IN (' . $in . ')',
                    $params
                );
            }
        }

        if ($baseColumn === null) {
            return [];
        }

        [$in, $params] = self::idFilter($ids);

        return self::queryMap(
            'SELECT ID AS id, ' . self::quoteIdentifier($baseColumn) . ' AS name'
            . ' FROM quest_template WHERE ID IN (' . $in . ')',
            $params
        );
    }

    /**
     * IN 占位符与绑定参数（id 按整数绑定，命名参数与 queryMap 的绑定方式一致）。
     * @param int[] $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private static function idFilter(array $ids): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $name = ':id' . $index;
            $placeholders[] = $name;
            $params[$name] = (int) $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    /**
     * 按需读数值表（SpellRange / SpellRadius / SpellCastTimes / SpellDuration 之一）。
     *
     * 只解出调用方点名的字段，并按「表 + 字段集合 + 规则版本」缓存到磁盘：
     * 字段集合或 $version 变化即失效重读，与既有名字缓存同一套存储目录。
     *
     * @param string[] $fields DBC_SPECS[$type]['fields'] 里的键
     * @return array<int,array<string,int|float>> id => [field => 值]（读不到文件/字段时返回空数组）
     */
    public static function dbcLookup(string $type, array $fields, string $version = ''): array
    {
        $type = strtolower(trim($type));
        $spec = self::DBC_SPECS[$type] ?? null;
        if ($spec === null || !isset($spec['fields'])) {
            return [];
        }

        $unknown = array_diff($fields, array_keys($spec['fields']));
        if ($unknown !== []) {
            throw new \InvalidArgumentException('unknown dbc fields for ' . $type . ': ' . implode(',', $unknown));
        }

        $fields = array_values(array_unique($fields));
        if ($fields === []) {
            return [];
        }

        $cacheKey = $type . '|' . implode(',', $fields) . '|' . $version;
        if (array_key_exists($cacheKey, self::$rowCache)) {
            return self::$rowCache[$cacheKey];
        }

        $fromDisk = self::loadRowsFromDisk($type, $fields, $version);
        if ($fromDisk !== null) {
            return self::$rowCache[$cacheKey] = $fromDisk;
        }

        $rows = self::buildRows($type, $fields);
        self::persistRows($type, $fields, $version, $rows);

        return self::$rowCache[$cacheKey] = $rows;
    }

    /**
     * 按需读 Spell.dbc 的指定字段（只保留点名的 spellId）。
     *
     * @param int[] $ids
     * @param string[] $fields SPELL_FIELDS 里的键
     * @return array<int,array<string,int>> spellId => [field => 值]（DBC 里没有的 id 不在结果里）
     */
    public static function readSpellFacts(array $ids, array $fields, string $version = ''): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $unknown = array_diff($fields, array_keys(self::SPELL_FIELDS));
        if ($unknown !== []) {
            throw new \InvalidArgumentException('unknown spell fields: ' . implode(',', $unknown));
        }

        $fields = array_values(array_unique($fields));
        if ($fields === []) {
            return [];
        }

        $cacheKey = 'spellfacts|' . implode(',', $fields) . '|' . $version . '|' . self::idsFingerprint($ids);
        $cached = self::$rowCache[$cacheKey] ?? null;
        if ($cached === null) {
            $fromDisk = self::loadSpellFactsFromDisk($fields, $version, $ids);
            if ($fromDisk !== null) {
                $cached = $fromDisk;
            } else {
                $cached = self::buildSpellFacts($fields, $ids);
                self::persistSpellFacts($fields, $version, $ids, $cached);
            }
            self::$rowCache[$cacheKey] = $cached;
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($cached[$id])) {
                $out[$id] = $cached[$id];
            }
        }

        return $out;
    }

    /** Spell.dbc 的字符串槽（技能名）——名字缓存之外的单次读，用于名实比对。 */
    public static function spellName(int $id): ?string
    {
        $names = self::spellNamesFromDbc([$id], 'spell-name-v1');

        return $names[$id] ?? null;
    }

    /**
     * 从 Spell.dbc 直接解析技能名（id => 名称），不经 world 库的 spell_dbc 覆盖表。
     *
     * 名实比对要与客户端 DBC 一致，而 world.spell_dbc 只是自定义覆盖（行数远少于 DBC），
     * 所以这里单独读 DBC 并缓存整张名字表（与既有名字缓存同一目录，按 $version 失效）。
     *
     * @param int[] $ids
     * @return array<int,string>
     */
    public static function spellNamesFromDbc(array $ids, string $version = ''): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $cacheKey = 'dbcspellnames|' . $version;
        $all = self::$rowCache[$cacheKey] ?? null;
        if ($all === null) {
            $file = self::spellNameCacheFile($version);
            $all = self::loadNameCache($file);
            if ($all === null) {
                $all = self::buildSpellNameMap();
                self::writeNameCache($file, $all);
            }
            self::$rowCache[$cacheKey] = $all;
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($all[$id]) && $all[$id] !== '') {
                $out[$id] = $all[$id];
            }
        }

        return $out;
    }

    /** @return array<int,string> */
    private static function buildSpellNameMap(): array
    {
        $spec = self::DBC_SPECS['spell'];
        $path = self::dbcDirectory() . DIRECTORY_SEPARATOR . $spec['file'];
        if (!is_file($path)) {
            return [];
        }

        $reader = DbcReader::open($path);
        if ($reader === null) {
            return [];
        }

        $localeOffset = self::dbcLocaleOffset($reader, $spec, self::wantedDbcLocale());
        $nameField = (int) $spec['nameField'] + $localeOffset;
        $idField = (int) $spec['idField'];

        $out = [];
        foreach ($reader->records([$idField, $nameField]) as $record) {
            $id = (int) ($record[$idField] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = $reader->string((int) ($record[$nameField] ?? 0));
            if ($name !== null) {
                $out[$id] = $name;
            }
        }

        return $out;
    }

    private static function spellNameCacheFile(string $version): string
    {
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'game_names';

        return $base . DIRECTORY_SEPARATOR . 'dbc_spell_names_s' . self::serverId() . '_'
            . substr(sha1($version), 0, 12) . '.json';
    }

    /** @return array<int,string>|null */
    private static function loadNameCache(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || (int) ($decoded['v'] ?? 0) !== self::CACHE_VERSION) {
            return null;
        }

        $names = $decoded['names'] ?? null;
        if (!is_array($names)) {
            return null;
        }

        $out = [];
        foreach ($names as $id => $name) {
            if (is_string($name) && $name !== '') {
                $out[(int) $id] = $name;
            }
        }

        return $out;
    }

    private static function writeNameCache(string $file, array $names): void
    {
        if ($names === []) {
            return;
        }

        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $encoded = json_encode([
            'v' => self::CACHE_VERSION,
            'type' => 'spell_names_dbc',
            'generated_at' => date('c'),
            'names' => $names,
        ], JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $encoded) !== false) {
            @rename($tmp, $file);
        }
    }

    /**
     * 逐条流式读取，只保留点名的 id 与字段（Spell.dbc 5 万多条也只物化需要的那些）。
     *
     * $ids 传空数组表示不过滤（保留全表），供需要整张表的调用方使用。
     *
     * @param string[] $fields SPELL_FIELDS 里的键
     * @param int[]    $ids    只保留这些 spellId；空数组 = 全部
     * @return array<int,array<string,int>>
     */
    private static function buildSpellFacts(array $fields, array $ids = []): array
    {
        $spec = self::DBC_SPECS['spell'];
        $path = self::dbcDirectory() . DIRECTORY_SEPARATOR . $spec['file'];
        if (!is_file($path)) {
            return [];
        }

        $reader = DbcReader::open($path);
        if ($reader === null) {
            return [];
        }

        $indexes = [];
        foreach ($fields as $field) {
            $indexes[$field] = (int) self::SPELL_FIELDS[$field];
        }

        // id 字段（下标 0）必须在读取集合里：它是记录的键
        $wanted = array_values(array_unique(array_merge([(int) self::SPELL_FIELDS['id']], array_values($indexes))));
        $keep = $ids === [] ? null : array_flip(array_map('intval', $ids));

        $out = [];
        foreach ($reader->records($wanted) as $record) {
            $id = (int) ($record[(int) self::SPELL_FIELDS['id']] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($keep !== null && !isset($keep[$id])) {
                continue;
            }

            $row = [];
            foreach ($indexes as $field => $index) {
                $row[$field] = (int) ($record[$index] ?? 0);
            }
            $out[$id] = $row;
        }

        return $out;
    }

    /** @return array<int,array<string,int>> */
    private static function buildRows(string $type, array $fields): array
    {
        $spec = self::DBC_SPECS[$type];
        $path = self::dbcDirectory() . DIRECTORY_SEPARATOR . $spec['file'];
        if (!is_file($path)) {
            return [];
        }

        $reader = DbcReader::open($path);
        if ($reader === null) {
            return [];
        }

        $indexes = [];
        foreach ($fields as $field) {
            $indexes[$field] = (int) $spec['fields'][$field];
        }

        $idField = (int) $spec['idField'];
        $wanted = array_values(array_unique(array_merge([$idField], array_values($indexes))));
        $floatFields = array_flip((array) ($spec['float_fields'] ?? []));

        $out = [];
        foreach ($reader->records($wanted) as $record) {
            $id = (int) ($record[$idField] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $row = [];
            foreach ($indexes as $field => $index) {
                $raw = (int) ($record[$index] ?? 0);
                $row[$field] = isset($floatFields[$field]) ? self::uint32ToFloat($raw) : $raw;
            }
            $out[$id] = $row;
        }

        return $out;
    }

    /** DBC 里的 float 字段是按 uint32 读出来的位模式，还原成 float（小端）。 */
    private static function uint32ToFloat(int $bits): float
    {
        $unpacked = unpack('g', pack('V', $bits));

        return (float) ($unpacked[1] ?? 0.0);
    }

    /** id 集合的稳定指纹：排序去重后取 sha1 前 12 位，保证同一批 id 命中同一份缓存。 */
    private static function idsFingerprint(array $ids): string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return substr(sha1(implode(',', $ids)), 0, 12);
    }

    /**
     * spellfacts 缓存文件。
     *
     * 两点与通用 rowCacheFile() 不同：
     *  - 键里带 id 集合指纹：只落盘被点名的那几十行，而不是整张 Spell.dbc（原先单文件 52 MB）；
     *  - 键里带 DBC 文件身份（路径 + mtime + 大小）而不是区服编号：两个区共用同一份 Spell.dbc 时
     *    共享一份缓存，各区自备 DBC 时自然隔离。
     */
    private static function spellFactsCacheFile(array $fields, string $version, array $ids): string
    {
        $key = substr(sha1(implode(',', $fields) . '|' . $version . '|' . self::idsFingerprint($ids)), 0, 12);
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'game_names';

        return $base . DIRECTORY_SEPARATOR . 'rows_' . self::dbcIdentity('spell') . '_spellfacts_' . $key . '.json';
    }

    /** 已解析 DBC 文件的身份指纹；文件不存在时退化为目录名，避免调用方拿到空串。 */
    private static function dbcIdentity(string $type): string
    {
        static $cache = [];
        if (isset($cache[$type])) {
            return $cache[$type];
        }

        $spec = self::DBC_SPECS[$type] ?? null;
        $path = $spec === null ? '' : self::dbcDirectory() . DIRECTORY_SEPARATOR . $spec['file'];
        $stat = $path !== '' && is_file($path) ? @stat($path) : false;
        $material = $stat === false
            ? 'absent|' . $path
            : $path . '|' . (int) $stat['mtime'] . '|' . (int) $stat['size'];

        return $cache[$type] = 'dbc' . substr(sha1($material), 0, 12);
    }

    private static function loadSpellFactsFromDisk(array $fields, string $version, array $ids): ?array
    {
        return self::decodeRowCache(self::spellFactsCacheFile($fields, $version, $ids));
    }

    private static function persistSpellFacts(array $fields, string $version, array $ids, array $rows): void
    {
        $file = self::spellFactsCacheFile($fields, $version, $ids);
        self::writeRowCache($file, [
            'type' => 'spellfacts',
            'fields' => $fields,
            'rule_version' => $version,
            'ids_fingerprint' => self::idsFingerprint($ids),
            'rows' => $rows,
        ]);
        self::pruneSpellFactsCaches($file);
    }

    /**
     * 保留最多的 $keep 份 spellfacts 缓存（含刚写入的那份），删除更早的。
     * 键里带 id 指纹后文件会随调用方的 id 集合增长，必须封顶，否则又会积累成几百 MB。
     */
    private static function pruneSpellFactsCaches(string $justWritten, int $keep = 8): void
    {
        $files = glob(dirname($justWritten) . DIRECTORY_SEPARATOR . 'rows_*_spellfacts_*.json') ?: [];
        if (count($files) <= $keep) {
            return;
        }

        $entries = [];
        foreach ($files as $file) {
            $mtime = @filemtime($file);
            $entries[] = ['file' => $file, 'mtime' => $mtime === false ? 0 : $mtime];
        }
        usort($entries, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        $kept = 0;
        foreach ($entries as $entry) {
            if ($entry['file'] === $justWritten || $kept < $keep) {
                $kept++;
                continue;
            }
            @unlink($entry['file']);
        }
    }

    private static function loadRowsFromDisk(string $type, array $fields, string $version): ?array
    {
        return self::decodeRowCache(self::rowCacheFile($type, $fields, $version));
    }

    private static function persistRows(string $type, array $fields, string $version, array $rows): void
    {
        self::writeRowCache(self::rowCacheFile($type, $fields, $version), [
            'type' => $type,
            'fields' => $fields,
            'rule_version' => $version,
            'rows' => $rows,
        ]);
    }

    /**
     * 数值表的缓存文件名：表名 + 字段集合哈希 + 规则版本哈希。
     * 字段集合或规则版本一变就是另一个文件（旧的不会命中，也不会被误用）。
     */
    private static function rowCacheFile(string $type, array $fields, string $version): string
    {
        $key = substr(sha1($type . '|' . implode(',', $fields) . '|' . $version), 0, 12);
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'game_names';

        return $base . DIRECTORY_SEPARATOR . 'rows_s' . self::serverId() . '_' . $type . '_' . $key . '.json';
    }

    /** @return array<int,array<string,int>>|null */
    private static function decodeRowCache(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || (int) ($decoded['v'] ?? 0) !== self::DBC_ROW_CACHE_VERSION) {
            return null;
        }

        $rows = $decoded['rows'] ?? null;
        if (!is_array($rows)) {
            return null;
        }

        $out = [];
        foreach ($rows as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $values = [];
            foreach ($row as $field => $value) {
                // float 字段（射程/半径）在缓存里保持浮点，别被压成整数
                $values[(string) $field] = is_float($value) ? (float) $value : (int) $value;
            }
            $out[(int) $id] = $values;
        }

        return $out;
    }

    private static function writeRowCache(string $file, array $payload): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $payload['v'] = self::DBC_ROW_CACHE_VERSION;
        $payload['generated_at'] = date('c');
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $encoded) !== false) {
            @rename($tmp, $file);
        }
    }

    /**
     * 重新构建并落盘指定类型（不传则全部重建）。
     * @param string[] $types
     */
    public static function warm(array $types = []): array
    {
        $types = $types === [] ? self::TYPES : $types;
        $report = [];

        foreach ($types as $type) {
            $type = strtolower(trim($type));
            if (!in_array($type, self::TYPES, true)) {
                continue;
            }

            self::forget($type);
            $map = self::build($type);
            self::$maps[self::cacheKey($type)] = $map;
            self::persist($type, $map);
            $report[$type] = count($map);
        }

        return $report;
    }

    private static function forget(string $type): void
    {
        unset(self::$maps[self::cacheKey($type)]);

        $file = self::cacheFile($type);
        if (is_file($file)) {
            @unlink($file);
        }
    }


    private static function cacheKey(string $type): string
    {
        return self::serverId() . '|' . self::localeKey() . '|' . $type;
    }

    private static function cacheFile(string $type): string
    {
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'game_names';

        return $base . DIRECTORY_SEPARATOR . 's' . self::serverId() . '_' . self::localeKey() . '_' . $type . '.json';
    }

    private static function loadFromDisk(string $type): ?array
    {
        $file = self::cacheFile($type);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        if ((int) ($decoded['v'] ?? 0) !== self::CACHE_VERSION) {
            return null;
        }

        $names = $decoded['names'] ?? null;
        if (!is_array($names)) {
            return null;
        }

        $out = [];
        foreach ($names as $id => $name) {
            if (is_string($name) && $name !== '') {
                $out[(int) $id] = $name;
            }
        }

        return $out;
    }

    private static function persist(string $type, array $map): void
    {
        if ($map === []) {
            return;
        }

        $file = self::cacheFile($type);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $payload = json_encode([
            'v' => self::CACHE_VERSION,
            'type' => $type,
            'locale' => self::localeKey(),
            'server_id' => self::serverId(),
            'generated_at' => date('c'),
            'names' => $map,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return;
        }

        // 先写临时文件再改名，避免并发读写拿到半截 JSON
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $payload) !== false) {
            @rename($tmp, $file);
        }
    }


    private static function build(string $type): array
    {
        $fromDb = self::buildFromDatabase($type);
        if ($fromDb !== []) {
            return $fromDb;
        }

        return self::buildFromDbc($type);
    }

    private static function buildFromDatabase(string $type): array
    {
        return match ($type) {
            'quest' => self::questNames(),
            'item' => self::itemNames(),
            'achievement' => self::achievementNames(),
            'spell' => self::spellNames(),
            default => [],
        };
    }

    private static function achievementNames(): array
    {
        // world 库的 achievement_dbc 常常只是"自定义成就"的补丁表，行数很少；取不到就由 build() 回退到 DBC 文件
        return self::simpleIdNameMap('achievement_dbc', 'ID', ['Title_Lang_zhCN', 'Title_Lang_enUS', 'Title']);
    }

    private static function spellNames(): array
    {
        return self::simpleIdNameMap('spell_dbc', 'ID', ['Name_Lang_zhCN', 'Name_Lang_enUS', 'Name']);
    }

    /**
     * 从 world 库的简单 id/name 表取名字（表或列不存在时返回空数组）。
     * @param string[] $candidateColumns
     * @return array<int, string>
     */
    private static function simpleIdNameMap(string $table, string $idColumn, array $candidateColumns): array
    {
        $columns = self::tableColumns($table);
        if ($columns === []) {
            return [];
        }

        $nameColumn = self::firstExistingColumn($columns, $candidateColumns);
        if ($nameColumn === null) {
            return [];
        }

        return self::queryMap(
            'SELECT ' . self::quoteIdentifier($idColumn) . ' AS id, '
            . self::quoteIdentifier($nameColumn) . ' AS name FROM ' . self::quoteIdentifier($table),
            []
        );
    }

    private static function questNames(): array
    {
        $columns = self::tableColumns('quest_template');
        if ($columns === []) {
            return [];
        }

        // 优先读本地化表，其次退回基础表
        $locale = self::localeKey();
        if (self::tableExists('quest_template_locale')) {
            $localeColumns = self::tableColumns('quest_template_locale');
            $titleColumn = self::firstExistingColumn($localeColumns, ['Title', 'LogTitle']);
            $localeColumn = self::firstExistingColumn($localeColumns, ['locale']);
            if ($titleColumn !== null && $localeColumn !== null) {
                $map = self::queryMap(
                    'SELECT ID AS id, ' . self::quoteIdentifier($titleColumn) . ' AS name'
                    . ' FROM quest_template_locale WHERE ' . self::quoteIdentifier($localeColumn) . ' = :locale',
                    [':locale' => $locale]
                );
                if ($map !== []) {
                    return $map;
                }
            }
        }

        $baseColumn = self::firstExistingColumn($columns, ['LogTitle', 'Title']);
        if ($baseColumn === null) {
            return [];
        }

        return self::queryMap(
            'SELECT ID AS id, ' . self::quoteIdentifier($baseColumn) . ' AS name FROM quest_template',
            []
        );
    }

    private static function itemNames(): array
    {
        $columns = self::tableColumns('item_template');
        if ($columns === []) {
            return [];
        }

        $localeTables = ['item_template_locale', 'locales_item'];
        foreach ($localeTables as $localeTable) {
            if (!self::tableExists($localeTable)) {
                continue;
            }
            $localeColumns = self::tableColumns($localeTable);
            $nameColumn = self::firstExistingColumn($localeColumns, ['Name', 'name']);
            $localeColumn = self::firstExistingColumn($localeColumns, ['locale']);
            if ($nameColumn === null || $localeColumn === null) {
                continue;
            }
            $map = self::queryMap(
                'SELECT ID AS id, ' . self::quoteIdentifier($nameColumn) . ' AS name'
                . ' FROM ' . self::quoteIdentifier($localeTable)
                . ' WHERE ' . self::quoteIdentifier($localeColumn) . ' = :locale',
                [':locale' => self::localeKey()]
            );
            if ($map !== []) {
                return $map;
            }
        }

        $nameColumn = self::firstExistingColumn($columns, ['name', 'Name']);
        if ($nameColumn === null) {
            return [];
        }

        return self::queryMap(
            'SELECT entry AS id, ' . self::quoteIdentifier($nameColumn) . ' AS name FROM item_template',
            []
        );
    }

    private static function queryMap(string $sql, array $params): array
    {
        try {
            $stmt = self::world()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();

            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                $name = trim((string) ($row['name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $out[$id] = $name;
                }
            }

            return $out;
        } catch (Throwable $exception) {
            return [];
        }
    }

    private static function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $cache[$table] = false;
        try {
            $stmt = self::world()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND TABLE_NAME = :table LIMIT 1'
            );
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();

            $cache[$table] = $stmt->fetchColumn() !== false;
        } catch (Throwable $exception) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    private static function tableColumns(string $table): array
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $cache[$table] = [];
        try {
            $stmt = self::world()->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS'
                . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
            );
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();

            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $column) {
                $columns[] = (string) $column;
            }
            $cache[$table] = $columns;
        } catch (Throwable $exception) {
            $cache[$table] = [];
        }

        return $cache[$table];
    }

    private static function firstExistingColumn(array $columns, array $candidates): ?string
    {
        $lookup = [];
        foreach ($columns as $column) {
            $lookup[strtolower($column)] = $column;
        }

        foreach ($candidates as $candidate) {
            $key = strtolower($candidate);
            if (isset($lookup[$key])) {
                return $lookup[$key];
            }
        }

        return null;
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '', $identifier) . '`';
    }


    private static function buildFromDbc(string $type): array
    {
        $spec = self::DBC_SPECS[$type] ?? null;
        if ($spec === null) {
            return [];
        }

        $path = self::dbcDirectory() . DIRECTORY_SEPARATOR . $spec['file'];
        if (!is_file($path)) {
            return [];
        }

        $reader = DbcReader::open($path);
        if ($reader === null) {
            return [];
        }

        $localeOffset = self::dbcLocaleOffset($reader, $spec, self::wantedDbcLocale());
        $nameField = $spec['nameField'] + $localeOffset;

        $out = [];
        foreach ($reader->records([$spec['idField'], $nameField]) as $record) {
            $id = (int) ($record[$spec['idField']] ?? 0);
            $name = $reader->string((int) ($record[$nameField] ?? 0));
            if ($id > 0 && $name !== null) {
                $out[$id] = $name;
            }
        }

        return $out;
    }

    /**
     * 探测 DBC 里实际填充的语种槽位。
     * 字符串掩码常常把"语言位"整片置位（例如 zhCN/enCN/enTW/zhTW 同时为 1），但真正写入数据的只有一个
     * 槽位，所以掩码只能当候选集合，必须再抽样验证：期望语种 → 同语系 → 英文 → 掩码里其它被置位的槽位
     * → 全字段抽样。客户端 DBC 往往只带一个语种（本站是 zhCN），面板切成英文时回退显示 DBC 里既有的
     * 语言；数据库来源（任务/物品）仍按面板语言取。
     */
    private static function dbcLocaleOffset(DbcReader $reader, array $spec, string $wanted): int
    {
        $mask = self::sampleLocaleMask($reader, $spec);

        $candidates = [];
        foreach (self::dbcLocalePreference($wanted) as $locale) {
            $index = array_search($locale, $spec['order'], true);
            if ($index !== false && $index <= 15) {
                $candidates[] = (int) $index;
            }
        }

        if ($mask !== null) {
            for ($slot = 0; $slot < 16; $slot++) {
                if (($mask & (1 << $slot)) !== 0) {
                    $candidates[] = $slot;
                }
            }
        }

        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $slot) {
            if (self::slotHasData($reader, $spec['nameField'] + $slot)) {
                return $slot;
            }
        }

        return 0;
    }

    /**
     * 取一条掩码非零记录的字符串掩码值。
     * @param array{idField:int, nameField:int, maskField:int, order:string[]} $spec
     */
    private static function sampleLocaleMask(DbcReader $reader, array $spec): ?int
    {
        $maskField = (int) ($spec['maskField'] ?? -1);
        if ($maskField < 0 || $maskField >= $reader->fieldCount()) {
            return null;
        }

        $seen = 0;
        foreach ($reader->records([$maskField]) as $record) {
            $mask = (int) ($record[$maskField] ?? 0);
            if ($mask !== 0) {
                return $mask;
            }
            if (++$seen >= 50) {
                break;
            }
        }

        return null;
    }

    /** 抽样判断某个字段是否真的有字符串数据。 */
    private static function slotHasData(DbcReader $reader, int $field, int $sampleSize = 200): bool
    {
        if ($field < 0 || $field >= $reader->fieldCount()) {
            return false;
        }

        $checked = 0;
        $hits = 0;
        foreach ($reader->records([$field]) as $record) {
            $checked++;
            if ($reader->string((int) ($record[$field] ?? 0)) !== null) {
                $hits++;
            }
            if ($checked >= $sampleSize) {
                break;
            }
        }

        return $checked > 0 && $hits >= max(1, (int) floor($checked * 0.5));
    }

    /**
     * 语种优先顺序：期望语种 → 同语系 → 英文 → 任意。
     * @return string[]
     */
    private static function dbcLocalePreference(string $wanted): array
    {
        $preference = [$wanted];

        if ($wanted === 'zhCN' || $wanted === 'zhTW') {
            $preference[] = $wanted === 'zhCN' ? 'zhTW' : 'zhCN';
        } elseif ($wanted !== 'enUS') {
            $preference[] = 'enUS';
        }

        return array_values(array_unique(array_merge($preference, self::DBC_LOCALE_ORDER)));
    }

    /** 面板语言期望的 DBC 语种。 */
    private static function wantedDbcLocale(): string
    {
        $locale = self::localeKey();

        return match (true) {
            str_starts_with($locale, 'zh_tw'), str_starts_with($locale, 'zh_hant') => 'zhTW',
            str_starts_with($locale, 'zh') => 'zhCN',
            str_starts_with($locale, 'ko') => 'koKR',
            str_starts_with($locale, 'fr') => 'frFR',
            str_starts_with($locale, 'de') => 'deDE',
            str_starts_with($locale, 'es') => 'esES',
            str_starts_with($locale, 'ru') => 'ruRU',
            str_starts_with($locale, 'pt') => 'ptPT',
            str_starts_with($locale, 'it') => 'itIT',
            default => 'enUS',
        };
    }

    /** 定位客户端 DBC 目录：优先配置，其次按已安装的 release/<realm>/Data/dbc 推断。 */
    private static function dbcDirectory(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $resolved = '';
        $configured = trim((string) Config::get('app.game_data_dir', ''));
        $candidates = [];

        if ($configured !== '') {
            $candidates[] = rtrim($configured, '\\/') . DIRECTORY_SEPARATOR . 'dbc';
            $candidates[] = rtrim($configured, '\\/');
        }

        $realmId = self::realmId();
        $serverRoot = self::serverRoot();
        // release 目录可能就在服务器根下，也可能是 web 根的同级目录
        $roots = array_values(array_unique(array_filter([
            $serverRoot,
            dirname($serverRoot),
        ])));

        foreach ($roots as $root) {
            foreach (array_unique([$realmId, 80, 70]) as $candidateRealm) {
                if ($candidateRealm <= 0) {
                    continue;
                }
                $candidates[] = $root . DIRECTORY_SEPARATOR . 'release' . DIRECTORY_SEPARATOR
                    . $candidateRealm . DIRECTORY_SEPARATOR . 'Data' . DIRECTORY_SEPARATOR . 'dbc';
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_dir($candidate)) {
                $resolved = $candidate;
                return $resolved;
            }
        }

        return $resolved;
    }

    /** 面板所在服务器根目录（AGMP 的上级目录），用于推断 release/<realm>/Data。 */
    private static function serverRoot(): string
    {
        $configured = trim((string) Config::get('app.server_root', ''));
        if ($configured !== '' && is_dir($configured)) {
            return rtrim($configured, '\\/');
        }

        return dirname(__DIR__, 4);
    }


    private static function serverId(): int
    {
        return ServerContext::currentId();
    }

    private static function realmId(): int
    {
        $server = ServerContext::server();

        return (int) ($server['realm_id'] ?? 0);
    }

    private static function localeKey(): string
    {
        $locale = strtolower(str_replace('-', '_', Lang::locale()));

        return $locale !== '' ? $locale : 'zh_cn';
    }

    private static function world(): PDO
    {
        return Database::forServer(self::serverId(), 'world');
    }
}
