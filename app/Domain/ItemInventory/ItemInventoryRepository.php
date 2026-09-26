<?php
/**
 * File: app/Domain/ItemInventory/ItemInventoryRepository.php
 * Purpose: Read model for the unified item/inventory module, both directions of the
 * item_instance x character_inventory model (character axis and item axis).
 *
 * This class performs reads only: every write path lives in ItemInventoryMutationService, so no read
 * model can silently mutate data.
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\ItemInventory;

use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MultiServerRepository;
use PDO;
use Throwable;

class ItemInventoryRepository extends MultiServerRepository
{
    private PDO $auth;
    private PDO $chars;
    private PDO $world;
    private LocationResolver $locations;

    private array $inventoryMaps = [];
    private array $itemMetaCache = [];
    /**
     * entry => {entry, name, quality}, filled by resolveItemMeta(): one query serves both the display name
     * and the template quality.
     * @var array<int,array{entry:int,name:string,quality:?int}>
     */
    private array $itemNameCache = [];
    private ?array $localeProbe = null;

    /** Item instance columns holding non-currency metadata, cleared on replace when present. */
    private const RESIDUE_COLUMNS = [
        'charges',
        'randomPropertyId',
        'enchantments',
        'enchantments2',
        'enchantments3',
    ];

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);
        $this->auth = $this->auth();
        $this->chars = $this->characters();
        $this->world = $this->world();
        $this->locations = new LocationResolver();
    }

    /** Switching realms must drop every per-realm cache, not just the PDO handles. */
    public function rebind(int $serverId): void
    {
        if ($serverId === $this->serverId) {
            return;
        }

        parent::rebind($serverId);
        $this->inventoryMaps = [];
        $this->itemMetaCache = [];
        $this->itemNameCache = [];
        $this->localeProbe = null;
    }

    /**
     * Character-axis entry point: find characters by exact account username or by (partial) character name.
     * @return array<int,array<string,mixed>>
     */
    public function searchCharacters(string $type, string $value, int $limit = 100): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $limit = $this->normalizeLimit($limit, 200);

        if ($type === 'username') {
            $stmt = $this->auth->prepare('SELECT id, username FROM account WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $value]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$account) {
                return [];
            }

            $stmt = $this->chars->prepare(
                'SELECT guid, name, level, race, class, account FROM characters WHERE account = :aid ORDER BY name ASC LIMIT :lim'
            );
            $stmt->bindValue(':aid', (int) $account['id'], PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return $this->attachAccountMeta($rows, (int) $account['id'], (string) ($account['username'] ?? ''));
        }

        $stmt = $this->chars->prepare(
            'SELECT guid, name, level, race, class, account FROM characters WHERE name LIKE :n ORDER BY name ASC LIMIT :lim'
        );
        $stmt->bindValue(':n', '%' . $value . '%');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->attachAccountMeta($rows);
    }

    /**
     * Everything one character carries: equipped, backpack, bank, keyring, currency, every bag container
     * and the contents inside those containers.
     * @return array<int,array<string,mixed>>
     */
    public function characterItems(int $guid): array
    {
        if ($guid <= 0) {
            return [];
        }

        $map = $this->characterInventoryMap($guid);
        if (!$map) {
            return [];
        }

        $itemGuids = [];
        foreach ($map as $row) {
            $itemGuids[] = $row['item'];
        }
        $itemGuids = array_values(array_unique(array_filter($itemGuids, static fn (int $id): bool => $id > 0)));

        $instances = [];
        if ($itemGuids) {
            $placeholders = implode(',', array_fill(0, count($itemGuids), '?'));
            $stmt = $this->chars->prepare(
                'SELECT guid, itemEntry, count, durability, charges FROM item_instance WHERE guid IN (' . $placeholders . ')'
            );
            $stmt->execute($itemGuids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $instances[(int) $row['guid']] = $row;
            }
        }

        $nameMap = $this->resolveItemNames(array_map(
            static fn (array $row): int => (int) ($row['itemEntry'] ?? 0),
            $instances
        ));

        $items = [];
        foreach ($map as $row) {
            $instanceGuid = $row['item'];
            $instance = $instances[$instanceGuid] ?? null;
            if ($instance === null) {
                // Orphaned character_inventory row (instance already gone).
                continue;
            }

            $bag = $row['bag'];
            $slot = $row['slot'];
            $containerRow = ($bag !== 0 && isset($map[$bag])) ? $map[$bag] : null;
            $location = $this->locations->resolve($bag, $slot, $containerRow);
            $entry = (int) ($instance['itemEntry'] ?? 0);
            $isContainer = $bag === 0 && ($this->locations->isBagSlot($slot) || $this->locations->isBankBagSlot($slot));
            $nestedCount = $isContainer ? $this->containedItemCount($guid, $instanceGuid) : 0;

            $items[] = [
                'instance_guid' => $instanceGuid,
                'entry' => $entry,
                'name' => $nameMap[$entry] ?? null,
                'quality' => $this->qualityOf($entry),
                'count' => (int) ($instance['count'] ?? 0),
                'durability' => (int) ($instance['durability'] ?? 0),
                'charges' => (int) ($instance['charges'] ?? 0),
                'bag' => $bag,
                'slot' => $slot,
                'location_code' => $location['code'],
                'location_label' => $location['label'],
                'location_area' => $location['area'],
                'inner_slot' => $location['inner_slot'],
                'container' => $location['container'],
                'is_container' => $isContainer,
                'contained_count' => $nestedCount,
            ];
        }

        $this->sortItems($items);

        return $items;
    }

    /**
     * Raw character_inventory rows for one character, keyed by the item guid so a container can be looked
     * up in O(1).
     * @return array<int,array{bag:int,slot:int,item:int}>
     */
    public function characterInventoryMap(int $guid): array
    {
        if ($guid <= 0) {
            return [];
        }
        if (isset($this->inventoryMaps[$guid])) {
            return $this->inventoryMaps[$guid];
        }

        $stmt = $this->chars->prepare('SELECT bag, slot, item FROM character_inventory WHERE guid = :g');
        $stmt->execute([':g' => $guid]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $item = (int) ($row['item'] ?? 0);
            if ($item <= 0) {
                continue;
            }
            $map[$item] = [
                'bag' => (int) ($row['bag'] ?? 0),
                'slot' => (int) ($row['slot'] ?? 0),
                'item' => $item,
            ];
        }

        $this->inventoryMaps[$guid] = $map;

        return $map;
    }

    /**
     * Item-axis entry point: find item templates by localized name or exact entry.
     * @return array<int,array<string,mixed>>
     */
    public function searchItems(string $keyword, int $limit = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $limit = $this->normalizeLimit($limit, 50);
        $like = '%' . $keyword . '%';
        $entry = ctype_digit($keyword) ? (int) $keyword : null;

        // localized names live in locales_item; when the table is absent (many AzerothCore installs never create it) fall back to item_template.name
        $withLocales = $this->hasLocaleTable();
        $hasLocales = $withLocales['table'];

        $nameConditions = ['i.name LIKE :kw'];
        if ($hasLocales) {
            foreach ($this->localeColumns() as $column) {
                $nameConditions[] = '(li.' . $column . ' LIKE :kw AND COALESCE(li.' . $column . ", '') <> '')";
            }
        }

        $sql = 'SELECT i.entry, i.name, i.Quality AS quality, i.stackable, '
            . ($hasLocales ? $this->localizedNameExpression() : 'i.name') . ' AS local_name
                FROM item_template i'
            . ($hasLocales ? ' LEFT JOIN locales_item li ON li.entry = i.entry' : '') . '
                WHERE (' . implode(' OR ', $nameConditions) . ')';
        if ($entry !== null) {
            $sql .= ' OR i.entry = :entry';
        }
        $sql .= ' ORDER BY i.Quality DESC, i.entry ASC LIMIT ' . $limit;

        $fallbackSql = 'SELECT entry, name, Quality AS quality, stackable, name AS local_name
                FROM item_template
                WHERE name LIKE :kw';
        if ($entry !== null) {
            $fallbackSql .= ' OR entry = :entry';
        }
        $fallbackSql .= ' ORDER BY Quality DESC, entry ASC LIMIT ' . $limit;

        $rows = $this->fetchRows([$sql, $fallbackSql], $like, $entry);

        return array_map(function (array $row): array {
            $itemEntry = (int) ($row['entry'] ?? 0);

            return [
                'entry' => $itemEntry,
                'name' => $this->nonEmpty($row['local_name'] ?? null)
                    ?? $this->nonEmpty($row['name'] ?? null)
                    ?? ('#' . $itemEntry),
                'name_en' => $row['name'] ?? '',
                'quality' => isset($row['quality']) ? (int) $row['quality'] : null,
                'stackable' => isset($row['stackable']) ? (int) $row['stackable'] : null,
            ];
        }, $rows);
    }

    /**
     * Item axis result: every stack of $entry across all characters, paginated, with summary totals
     * computed over the whole result set.
     * @return array{item:?array,rows:array<int,array<string,mixed>>,summary:array<string,int>,paginated:bool,page:int,per_page:int}
     */
    public function fetchOwnership(int $entry, bool $paginate = true, int $page = 1, int $perPage = 100, int $maxRows = 1000): array
    {
        $empty = [
            'item' => null,
            'rows' => [],
            'summary' => ['characters' => 0, 'instances' => 0, 'count' => 0, 'pages' => 0, 'page' => 1],
            'paginated' => $paginate,
            'page' => 1,
            'per_page' => $perPage,
        ];
        if ($entry <= 0) {
            return $empty;
        }

        $itemMeta = $this->loadItemMeta($entry);
        if (!$itemMeta) {
            return $empty;
        }

        $summary = $this->ownershipSummary($entry);
        $total = $summary['instances'];
        $perPage = $this->normalizeLimit($perPage, $maxRows);
        $page = $this->normalizePage($page);

        if (!$paginate) {
            $perPage = max(1, min($total > 0 ? $total : 1, $maxRows));
            $page = 1;
        }

        $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($pages > 0 && $page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT ii.guid AS instance_guid, ii.count, ii.durability, ii.charges, ii.itemEntry,
                       ci.guid AS character_guid, ci.bag, ci.slot
                FROM item_instance ii
                JOIN character_inventory ci ON ci.item = ii.guid
                WHERE ii.itemEntry = :entry
                ORDER BY ci.guid ASC, ii.guid ASC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $this->chars->prepare($sql);
        $stmt->bindValue(':entry', $entry, PDO::PARAM_INT);
        $stmt->execute();
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $characterGuids = [];
        foreach ($rawRows as $row) {
            $characterGuids[(int) ($row['character_guid'] ?? 0)] = true;
        }
        unset($characterGuids[0]);

        $characters = $this->loadCharacters(array_keys($characterGuids));
        $pageCharacterTotals = $this->ownershipCountsByCharacter($entry, array_keys($characterGuids));

        $rows = [];
        foreach ($rawRows as $row) {
            $characterGuid = (int) ($row['character_guid'] ?? 0);
            if ($characterGuid <= 0) {
                continue;
            }

            $bag = (int) ($row['bag'] ?? 0);
            $slot = (int) ($row['slot'] ?? 0);
            $containerRow = null;
            if ($bag !== 0) {
                $map = $this->characterInventoryMap($characterGuid);
                $containerRow = $map[$bag] ?? null;
            }

            $location = $this->locations->resolve($bag, $slot, $containerRow);
            $container = $location['container'];
            if ($container !== null) {
                $containerEntry = 0;
                if ($containerRow !== null && isset($this->characterInventoryMap($characterGuid)[$containerRow['item']])) {
                    $containerEntry = $this->instanceEntry($containerRow['item']);
                }
                if ($containerEntry > 0) {
                    $containerMeta = $this->resolveItemMeta([$containerEntry])[$containerEntry] ?? null;
                    $container['name'] = $containerMeta['name'] ?? null;
                    $container['entry'] = $containerEntry;
                    $container['quality'] = $containerMeta['quality'] ?? null;
                } else {
                    $container['name'] = null;
                    $container['entry'] = null;
                    $container['quality'] = null;
                }
            }

            $char = $characters[$characterGuid] ?? null;

            $rows[] = [
                'instance_guid' => (int) ($row['instance_guid'] ?? 0),
                'entry' => (int) ($row['itemEntry'] ?? 0),
                // Every row shares $entry, so the quality is already known here.
                'quality' => $itemMeta['quality'],
                'count' => (int) ($row['count'] ?? 0),
                'durability' => (int) ($row['durability'] ?? 0),
                'charges' => (int) ($row['charges'] ?? 0),
                'character_guid' => $characterGuid,
                'character_name' => $char['name'] ?? null,
                'character_level' => $char['level'] ?? null,
                'character_class' => $char['class'] ?? null,
                'character_race' => $char['race'] ?? null,
                'bag' => $bag,
                'slot' => $slot,
                'location_code' => $location['code'],
                'location_label' => $location['label'],
                'location_area' => $location['area'],
                'inner_slot' => $location['inner_slot'],
                'container' => $container,
                // Per-character total over the whole result set, not just this page.
                'character_total_count' => $pageCharacterTotals[$characterGuid]['count'] ?? (int) ($row['count'] ?? 0),
                'character_total_instances' => $pageCharacterTotals[$characterGuid]['instances'] ?? 1,
            ];
        }

        return [
            'item' => $itemMeta,
            'rows' => $rows,
            'summary' => [
                'characters' => $summary['characters'],
                'instances' => $summary['instances'],
                'count' => $summary['count'],
                'pages' => $pages,
                'page' => $page,
            ],
            'paginated' => $paginate,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @return array{characters:int,instances:int,count:int} */
    private function ownershipSummary(int $entry): array
    {
        $stmt = $this->chars->prepare(
            'SELECT COUNT(*) AS instances, COUNT(DISTINCT ci.guid) AS characters, COALESCE(SUM(ii.count), 0) AS total
             FROM item_instance ii
             JOIN character_inventory ci ON ci.item = ii.guid
             WHERE ii.itemEntry = :entry'
        );
        $stmt->bindValue(':entry', $entry, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'characters' => (int) ($row['characters'] ?? 0),
            'instances' => (int) ($row['instances'] ?? 0),
            'count' => (int) ($row['total'] ?? 0),
        ];
    }

    /**
     * Whole-result-set per-character totals for the characters on the current page.
     * @param array<int,int> $characterGuids
     * @return array<int,array{count:int,instances:int}>
     */
    private function ownershipCountsByCharacter(int $entry, array $characterGuids): array
    {
        $characterGuids = array_values(array_filter(array_map('intval', $characterGuids), static fn (int $id): bool => $id > 0));
        if (!$characterGuids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($characterGuids), '?'));
        $sql = 'SELECT ci.guid AS character_guid, COUNT(*) AS instances, COALESCE(SUM(ii.count), 0) AS total
                FROM item_instance ii
                JOIN character_inventory ci ON ci.item = ii.guid
                WHERE ii.itemEntry = ? AND ci.guid IN (' . $placeholders . ')
                GROUP BY ci.guid';
        $stmt = $this->chars->prepare($sql);
        $stmt->execute(array_merge([$entry], $characterGuids));

        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $counts[(int) $row['character_guid']] = [
                'instances' => (int) $row['instances'],
                'count' => (int) $row['total'],
            ];
        }

        return $counts;
    }

    /**
     * @param array<int,int> $guids
     * @return array<int,array{name:?string,level:?int,class:?int,race:?int}>
     */
    private function loadCharacters(array $guids): array
    {
        $guids = array_values(array_filter(array_map('intval', $guids), static fn (int $id): bool => $id > 0));
        if (!$guids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($guids), '?'));
        $stmt = $this->chars->prepare(
            'SELECT guid, name, level, class, race FROM characters WHERE guid IN (' . $placeholders . ')'
        );
        $stmt->execute($guids);

        $characters = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $guid = (int) $row['guid'];
            $characters[$guid] = [
                'name' => $row['name'] ?? null,
                'level' => isset($row['level']) ? (int) $row['level'] : null,
                'class' => isset($row['class']) ? (int) $row['class'] : null,
                'race' => isset($row['race']) ? (int) $row['race'] : null,
            ];
        }

        return $characters;
    }

    private function instanceEntry(int $instanceGuid): int
    {
        if ($instanceGuid <= 0) {
            return 0;
        }

        $stmt = $this->chars->prepare('SELECT itemEntry FROM item_instance WHERE guid = :g LIMIT 1');
        $stmt->execute([':g' => $instanceGuid]);
        $value = $stmt->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    /** Number of item instances sitting inside a container. */
    public function containedItemCount(int $characterGuid, int $containerInstanceGuid): int
    {
        if ($characterGuid <= 0 || $containerInstanceGuid <= 0) {
            return 0;
        }

        $stmt = $this->chars->prepare(
            'SELECT COUNT(*) FROM character_inventory WHERE guid = :g AND bag = :b'
        );
        $stmt->execute([':g' => $characterGuid, ':b' => $containerInstanceGuid]);

        return (int) $stmt->fetchColumn();
    }

    public function loadItemMeta(int $entry, bool $withStack = false): ?array
    {
        if ($entry <= 0) {
            return null;
        }

        $cacheKey = $entry . ':' . ($withStack ? '1' : '0');
        if (isset($this->itemMetaCache[$cacheKey])) {
            return $this->itemMetaCache[$cacheKey];
        }

        $columns = 'i.entry, i.name, i.Quality AS quality';
        if ($withStack) {
            $columns .= ', i.stackable, i.MaxDurability AS max_durability';
        }

        $hasLocales = $this->hasLocaleTable()['table'];
        $sql = 'SELECT ' . $columns
            . ($hasLocales ? ', ' . $this->localizedNameExpression() . ' AS local_name' : ', i.name AS local_name')
            . ' FROM item_template i'
            . ($hasLocales ? ' LEFT JOIN locales_item li ON li.entry = i.entry' : '')
            . ' WHERE i.entry = :entry LIMIT 1';
        $fallback = 'SELECT ' . ($withStack
                ? 'entry, name, Quality AS quality, stackable, MaxDurability AS max_durability, name AS local_name'
                : 'entry, name, Quality AS quality, name AS local_name')
            . ' FROM item_template WHERE entry = :entry LIMIT 1';

        $row = $this->firstRow([$sql, $fallback], $entry);
        if (!$row) {
            return null;
        }

        $meta = [
            'entry' => (int) $row['entry'],
            'name' => $this->nonEmpty($row['local_name'] ?? null)
                ?? $this->nonEmpty($row['name'] ?? null)
                ?? ('#' . $entry),
            'name_en' => $row['name'] ?? null,
            'quality' => isset($row['quality']) ? (int) $row['quality'] : null,
            'stackable' => $withStack && isset($row['stackable']) ? (int) $row['stackable'] : null,
            'max_durability' => $withStack && isset($row['max_durability']) ? (int) $row['max_durability'] : null,
        ];

        $this->itemMetaCache[$cacheKey] = $meta;

        return $meta;
    }

    /** @param array<int,int> $entries @return array<int,string> entry => display name */
    public function resolveItemNames(array $entries): array
    {
        $meta = $this->resolveItemMeta($entries);

        return array_map(static fn (array $row): string => $row['name'], $meta);
    }

    /**
     * Resolve localized display name AND template quality for a set of entries in one query (cached per
     * request). Quality drives the WoW colour coding, so resolving it here avoids one item_template query
     * per entry on the character axis.
     * @param array<int,int> $entries
     * @return array<int,array{entry:int,name:string,quality:?int}>
     */
    public function resolveItemMeta(array $entries): array
    {
        $entries = array_values(array_unique(array_filter(array_map('intval', $entries), static fn (int $v): bool => $v > 0)));
        if (!$entries) {
            return [];
        }

        $missing = array_values(array_diff($entries, array_keys($this->itemNameCache)));
        if ($missing) {
            $placeholders = implode(',', array_fill(0, count($missing), '?'));
            $hasLocales = $this->hasLocaleTable()['table'];
            $sql = 'SELECT i.entry, i.Quality AS quality, '
                . ($hasLocales ? $this->localizedNameExpression(true) : 'i.name')
                . ' AS name FROM item_template i'
                . ($hasLocales ? ' LEFT JOIN locales_item li ON li.entry = i.entry' : '')
                . ' WHERE i.entry IN (' . $placeholders . ')';
            $fallbackPlain = 'SELECT entry, name, Quality AS quality FROM item_template WHERE entry IN (' . $placeholders . ')';

            $rows = $this->fetchRowsByList([$sql, $fallbackPlain], $missing);
            foreach ($rows as $row) {
                $entry = (int) ($row['entry'] ?? 0);
                if ($entry <= 0) {
                    continue;
                }
                $this->itemNameCache[$entry] = [
                    'entry' => $entry,
                    'name' => $this->nonEmpty($row['name'] ?? null) ?? ('#' . $entry),
                    // a NULL Quality is a real possibility on custom templates; the client renders those with the neutral "unknown" colour
                    'quality' => isset($row['quality']) && $row['quality'] !== null ? (int) $row['quality'] : null,
                ];
            }
        }

        $resolved = [];
        foreach ($entries as $entry) {
            if (isset($this->itemNameCache[$entry])) {
                $resolved[$entry] = $this->itemNameCache[$entry];
            }
        }

        return $resolved;
    }

    /** Quality for one entry, or null when unknown. */
    private function qualityOf(int $entry): ?int
    {
        if ($entry <= 0) {
            return null;
        }
        if (!isset($this->itemNameCache[$entry])) {
            $this->resolveItemMeta([$entry]);
        }

        return $this->itemNameCache[$entry]['quality'] ?? null;
    }

    /**
     * Sorts by storage area, then bag, then slot - mirroring how the game client orders the bags
     * (equipment first, then backpack, then bank, then nested).
     * @param array<int,array<string,mixed>> $items
     */
    private function sortItems(array &$items): void
    {
        $areaRank = ['equipment' => 0, 'inventory' => 1, 'bank' => 2];
        usort($items, static function (array $a, array $b) use ($areaRank): int {
            $rankA = $areaRank[$a['location_area'] ?? 'inventory'] ?? 1;
            $rankB = $areaRank[$b['location_area'] ?? 'inventory'] ?? 1;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            if (($a['bag'] ?? 0) !== ($b['bag'] ?? 0)) {
                return ($a['bag'] ?? 0) <=> ($b['bag'] ?? 0);
            }

            return ($a['slot'] ?? 0) <=> ($b['slot'] ?? 0);
        });
    }

    private function localeColumns(): array
    {
        return ['name_loc4', 'name_loc8', 'name_loc5', 'name_loc6', 'name_loc7'];
    }

    /**
     * Detects whether locales_item exists AND exposes name_loc* columns, once per request. Many AzerothCore
     * installs never create the table and it must then not be JOINed at all: a JOIN against a missing table
     * throws for the whole WHERE clause, and a naive catch-and-retry can be short-circuited by SQL operator
     * precedence.
     * @return array{table:bool,columns:array<int,string>}
     */
    private function hasLocaleTable(): array
    {
        if ($this->localeProbe !== null) {
            return $this->localeProbe;
        }

        $available = [];
        try {
            $stmt = $this->world->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME LIKE :c'
            );
            $stmt->execute([':t' => 'locales_item', ':c' => 'name_loc%']);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $column) {
                if (is_string($column) && preg_match('/^name_loc[0-9]+$/', $column)) {
                    $available[] = $column;
                }
            }
        } catch (Throwable $e) {
            $this->logDegraded('locale_probe', $e);
        }

        // prefer the display order used by the rest of the panel, then append any locale column this schema defines
        $ordered = [];
        foreach ($this->localeColumns() as $column) {
            if (in_array($column, $available, true)) {
                $ordered[] = $column;
            }
        }
        foreach ($available as $column) {
            if (!in_array($column, $ordered, true)) {
                $ordered[] = $column;
            }
        }

        $this->localeProbe = ['table' => $ordered !== [], 'columns' => $ordered];

        return $this->localeProbe;
    }

    private function nonEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * COALESCE() alone is not enough: AzerothCore often stores empty strings rather than NULL in
     * locales_item, which would win the COALESCE and show a blank name, so NULLIF(col, '') keeps the
     * fallback chain walking.
     */
    private function localizedNameExpression(bool $withEnglishFallback = false): string
    {
        $columns = $this->hasLocaleTable()['columns'];
        $parts = [];
        foreach ($columns as $column) {
            $parts[] = "NULLIF(li." . $column . ", '')";
        }
        if ($withEnglishFallback) {
            $parts[] = 'i.name';
        }
        if (!$parts) {
            return $withEnglishFallback ? 'i.name' : 'NULL';
        }

        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    /**
     * Try each candidate statement in order; the first one that executes wins. Statement failures are
     * recorded instead of vanishing.
     * @param array<int,string> $candidates
     * @return array<int,array<string,mixed>>
     */
    private function fetchRows(array $candidates, string $like, ?int $entry): array
    {
        $lastError = null;
        foreach ($candidates as $sql) {
            try {
                $stmt = $this->world->prepare($sql);
                $stmt->bindValue(':kw', $like, PDO::PARAM_STR);
                if ($entry !== null && str_contains($sql, ':entry')) {
                    $stmt->bindValue(':entry', $entry, PDO::PARAM_INT);
                }
                $stmt->execute();

                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $lastError = $e;
            }
        }

        if ($lastError !== null) {
            $this->logDegraded('search_items', $lastError);
        }

        return [];
    }

    /**
     * @param array<int,string> $candidates
     * @param array<int,int> $values
     * @return array<int,array<string,mixed>>
     */
    private function fetchRowsByList(array $candidates, array $values): array
    {
        $lastError = null;
        foreach ($candidates as $candidate) {
            try {
                $stmt = $this->world->prepare($candidate);
                foreach ($values as $index => $value) {
                    $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
                }
                $stmt->execute();

                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $lastError = $e;
            }
        }

        if ($lastError !== null) {
            $this->logDegraded('resolve_item_names', $lastError);
        }

        return [];
    }

    /**
     * @param array<int,string> $candidates
     * @return array<string,mixed>|null
     */
    private function firstRow(array $candidates, int $entry): ?array
    {
        $lastError = null;
        foreach ($candidates as $candidate) {
            try {
                $stmt = $this->world->prepare($candidate);
                $stmt->bindValue(':entry', $entry, PDO::PARAM_INT);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return $row;
                }
            } catch (Throwable $e) {
                $lastError = $e;
            }
        }

        if ($lastError !== null) {
            $this->logDegraded('load_item_meta', $lastError);
        }

        return null;
    }

    /** Degraded localized reads are recorded in the module action log instead of failing silently. */
    private function logDegraded(string $operation, Throwable $e): void
    {
        try {
            ItemInventoryLog::action('read_degraded', [
                'operation' => $operation,
                'message' => $e->getMessage(),
                'sql_state' => $e instanceof \PDOException ? ($e->errorInfo[0] ?? null) : null,
            ]);
        } catch (Throwable $ignored) {
        }
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> rows + account name/id */
    private function attachAccountMeta(array $rows, ?int $knownAccountId = null, ?string $knownUsername = null): array
    {
        if (!$rows) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $accountId = (int) ($row['account'] ?? 0);
            if ($accountId > 0) {
                $ids[$accountId] = true;
            }
        }

        $map = [];
        if ($knownAccountId !== null && $knownAccountId > 0 && $knownUsername !== null) {
            $map[$knownAccountId] = $knownUsername;
            unset($ids[$knownAccountId]);
        }

        if ($ids) {
            $accountIds = array_map('intval', array_keys($ids));
            $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
            $stmt = $this->auth->prepare('SELECT id, username FROM account WHERE id IN (' . $placeholders . ')');
            $stmt->execute($accountIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $map[(int) $row['id']] = $row['username'] ?? null;
            }
        }

        foreach ($rows as &$row) {
            $accountId = (int) ($row['account'] ?? 0);
            $row['account_id'] = $accountId;
            $row['account_username'] = $map[$accountId] ?? null;
        }
        unset($row);

        return $rows;
    }

    private function normalizeLimit(int $limit, int $max): int
    {
        return max(1, min($limit, $max));
    }

    private function normalizePage(int $page): int
    {
        return max(1, $page);
    }

    public function localizationFallbackMessage(): string
    {
        return Lang::get('app.item_inventory.api.errors.lookup_failed');
    }
}
