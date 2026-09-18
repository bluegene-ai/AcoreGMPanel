<?php
/**
 * File: app/Support/GameNameResolver.php
 * Purpose: Resolve game object names (quest / faction / spell / skill / achievement /
 *          achievementcriteria / item) from the realm database and the client DBC files.
 *
 * 设计要点：
 *   - 每个 realm + 语言只加载一次名字表（进程内静态缓存），磁盘上再持久化一份 JSON，
 *     避免每次请求都解析 49MB 的 Spell.dbc。
 *   - 数据库优先：任务标题、物品名等直接来自 world 库；DBC 表为空或缺失时
 *     回退解析 DataDir/dbc/*.dbc（faction / skill / spell / achievement）。
 *   - 任何解析失败都不抛异常，只返回 null，页面继续显示原始 ID。
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
     *
     * 客户端把 16 个语种的字符串连续存放，但实际安装的 DBC 往往只填了 1~2 个语种，
     * 且不一定是标准顺序，所以语种槽位在运行时探测（见 dbcLocaleIndex）。
     */
    private const DBC_SPECS = [
        // FactionEntry.name[16] @23，字符串掩码 @39
        'faction' => ['file' => 'Faction.dbc', 'idField' => 0, 'nameField' => 23, 'maskField' => 39, 'order' => self::DBC_LOCALE_ORDER],
        // SkillLineEntry.name[16] @3，掩码 @19
        'skill' => ['file' => 'SkillLine.dbc', 'idField' => 0, 'nameField' => 3, 'maskField' => 19, 'order' => self::DBC_LOCALE_ORDER],
        // SpellEntry.SpellName[16] @136，掩码 @152
        'spell' => ['file' => 'Spell.dbc', 'idField' => 0, 'nameField' => 136, 'maskField' => 152, 'order' => self::DBC_LOCALE_ORDER],
        // AchievementEntry.name[16] @4，掩码 @20
        'achievement' => ['file' => 'Achievement.dbc', 'idField' => 0, 'nameField' => 4, 'maskField' => 20, 'order' => self::DBC_LOCALE_ORDER],
    ];

    /** 客户端 16 个语种的标准顺序（DBC 里以此顺序连续存放字符串） */
    private const DBC_LOCALE_ORDER = [
        'enUS', 'koKR', 'frFR', 'deDE', 'enCN', 'zhCN',
        'enTW', 'zhTW', 'esES', 'esMX', 'ruRU', 'ptPT',
        'ptBR', 'itIT', 'Unk', 'Unk2',
    ];

    /** @var array<string, array<int, string>> 已就绪的 id => name 表 */
    private static array $maps = [];

    /**
     * 批量解析，返回 id => name（未解析到的 id 不在结果里）。
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

    /**
     * 单个解析，解析不到返回 null。
     */
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
     *
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

    /**
     * 清空进程内缓存（CLI 工具或切换服务器后使用）。
     */
    public static function flush(): void
    {
        self::$maps = [];
    }

    /**
     * 重新构建并落盘指定类型（不传则全部重建）。
     *
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

    // ------------------------------------------------------------------
    // 缓存
    // ------------------------------------------------------------------

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

    /** @param array<int, string> $map */
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

    // ------------------------------------------------------------------
    // 构建
    // ------------------------------------------------------------------

    /** @return array<int, string> */
    private static function build(string $type): array
    {
        $fromDb = self::buildFromDatabase($type);
        if ($fromDb !== []) {
            return $fromDb;
        }

        return self::buildFromDbc($type);
    }

    /** @return array<int, string> */
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
        // world 库的 achievement_dbc 常常只是"自定义成就"的补丁表，行数很少，
        // 这里先取它，取不到就由 build() 回退到 DBC 文件
        return self::simpleIdNameMap('achievement_dbc', 'ID', ['Title_Lang_zhCN', 'Title_Lang_enUS', 'Title']);
    }

    private static function spellNames(): array
    {
        return self::simpleIdNameMap('spell_dbc', 'ID', ['Name_Lang_zhCN', 'Name_Lang_enUS', 'Name']);
    }

    /**
     * 从 world 库的简单 id/name 表取名字（表或列不存在时返回空数组）。
     *
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

    /**
     * @param array<string, mixed> $params
     * @return array<int, string>
     */
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
        try {
            $stmt = self::world()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
                . ' AND TABLE_NAME = :table LIMIT 1'
            );
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetchColumn() !== false;
        } catch (Throwable $exception) {
            return false;
        }
    }

    /** @return string[] */
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

    /**
     * @param string[] $columns
     * @param string[] $candidates
     */
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

    // ------------------------------------------------------------------
    // DBC 回退
    // ------------------------------------------------------------------

    /** @return array<int, string> */
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
     *
     * 字符串掩码常常把"语言位"整片置位（例如 zhCN/enCN/enTW/zhTW 同时为 1），
     * 但真正写入数据的只有一个槽位，所以掩码只能当"候选集合"，必须再抽样验证。
     *
     * 顺序：期望语种 → 同语系 → 英文 → 掩码里其它被置位的槽位 → 全字段抽样。
     * 注意：客户端 DBC 往往只带一个语种（本站是 zhCN），面板切成英文时
     * 回退显示 DBC 里的既有语言；数据库来源（任务/物品）仍按面板语言取。
     *
     * @param array{idField:int, nameField:int, maskField:int, order:string[]} $spec
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
     *
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

    /**
     * 抽样判断某个字段是否真的有字符串数据。
     */
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
     *
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

    /**
     * 面板语言期望的 DBC 语种。
     */
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

    /**
     * 定位客户端 DBC 目录：优先配置，其次按已安装的 release/<realm>/Data/dbc 推断。
     */
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

    /**
     * 面板所在服务器根目录（AGMP 的上级目录），用于推断 release/<realm>/Data。
     */
    private static function serverRoot(): string
    {
        $configured = trim((string) Config::get('app.server_root', ''));
        if ($configured !== '' && is_dir($configured)) {
            return rtrim($configured, '\\/');
        }

        return dirname(__DIR__, 4);
    }

    // ------------------------------------------------------------------
    // 上下文
    // ------------------------------------------------------------------

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
