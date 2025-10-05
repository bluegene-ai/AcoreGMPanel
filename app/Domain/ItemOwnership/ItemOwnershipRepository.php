<?php
/**
 * File: app/Domain/ItemOwnership/ItemOwnershipRepository.php
 * Purpose: Defines class ItemOwnershipRepository for the app/Domain/ItemOwnership module.
 * Classes:
 *   - ItemOwnershipRepository
 * Functions:
 *   - __construct()
 *   - searchItems()
 *   - fetchOwnership()
 *   - bulkDelete()
 *   - bulkReplace()
 *   - determineLocation()
 *   - normalizeIds()
 *   - bagRepo()
 *   - loadItemMeta()
 *   - resolveItemNames()
 *   - logsDir()
 *   - logAction()
 */

namespace Acme\Panel\Domain\ItemOwnership;

use PDO;
use Throwable;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Domain\BagQuery\BagQueryRepository;
use Acme\Panel\Core\Lang;
use Acme\Panel\Support\Audit;

class ItemOwnershipRepository extends MultiServerRepository
{
    private PDO $world;
    private PDO $chars;
    private ?BagQueryRepository $bagRepo = null;
    private string $logFile;

    private const EQUIPMENT_SLOTS = [
        0 => 'equipment.head',
        1 => 'equipment.neck',
        2 => 'equipment.shoulders',
        3 => 'equipment.body',
        4 => 'equipment.chest',
        5 => 'equipment.waist',
        6 => 'equipment.legs',
        7 => 'equipment.feet',
        8 => 'equipment.wrist',
        9 => 'equipment.hands',
        10 => 'equipment.finger1',
        11 => 'equipment.finger2',
        12 => 'equipment.trinket1',
        13 => 'equipment.trinket2',
        14 => 'equipment.back',
        15 => 'equipment.main_hand',
        16 => 'equipment.off_hand',
        17 => 'equipment.ranged',
        18 => 'equipment.tabard',
    ];

    private const SLOT_GROUPS = [
        'inventory.backpack' => [23, 38],
        'inventory.bank_main' => [39, 66],
        'inventory.keyring' => [86, 117],
        'inventory.currency' => [118, 135],
    ];

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);
        $this->world = $this->world();
        $this->chars = $this->characters();
        $this->logFile = $this->logsDir() . DIRECTORY_SEPARATOR . 'item_ownership_actions.log';
    }

    public function searchItems(string $keyword, int $limit = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $like = '%' . $keyword . '%';
        $numeric = ctype_digit($keyword) ? (int) $keyword : null;
        $params = [':kw' => $like];
        if ($numeric !== null) {
            $params[':entry'] = $numeric;
        }

        $rows = [];
        try {
            $baseSql = 'SELECT i.entry, i.name, i.Quality AS quality, i.stackable,
                COALESCE(li.name_loc4, li.name_loc8, li.name_loc5, li.name_loc6, li.name_loc7) AS local_name
                FROM item_template i
                LEFT JOIN locales_item li ON li.entry = i.entry
                WHERE (i.name LIKE :kw OR li.name_loc4 LIKE :kw OR li.name_loc8 LIKE :kw OR li.name_loc5 LIKE :kw OR li.name_loc6 LIKE :kw OR li.name_loc7 LIKE :kw)';
            if ($numeric !== null) {
                $baseSql .= ' OR i.entry = :entry';
            }
            $baseSql .= ' ORDER BY i.Quality DESC, i.entry ASC LIMIT ' . $limit;

            $stmt = $this->world->prepare($baseSql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $key === ':entry' ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $fallbackSql = 'SELECT i.entry, i.name, i.Quality AS quality, i.stackable
                FROM item_template i
                WHERE i.name LIKE :kw';
            if ($numeric !== null) {
                $fallbackSql .= ' OR i.entry = :entry';
            }
            $fallbackSql .= ' ORDER BY i.Quality DESC, i.entry ASC LIMIT ' . $limit;
            try {
                $stmt = $this->world->prepare($fallbackSql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, $key === ':entry' ? PDO::PARAM_INT : PDO::PARAM_STR);
                }
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $ignored) {
                $rows = [];
            }
        }
        return array_map(static function (array $row): array {
            $entry = (int) ($row['entry'] ?? 0);
            return [
                'entry' => $entry,
                'name' => $row['local_name'] ?? $row['name'] ?? ('#' . $entry),
                'name_en' => $row['name'] ?? '',
                'quality' => isset($row['quality']) ? (int) $row['quality'] : null,
                'stackable' => isset($row['stackable']) ? (int) $row['stackable'] : null,
            ];
        }, $rows);
    }

    public function fetchOwnership(int $entry): array
    {
        if ($entry <= 0) {
            return ['item' => null, 'owners' => [], 'totals' => ['characters' => 0, 'instances' => 0, 'count' => 0]];
        }
        $itemMeta = $this->loadItemMeta($entry);
        if (!$itemMeta) {
            return ['item' => null, 'owners' => [], 'totals' => ['characters' => 0, 'instances' => 0, 'count' => 0]];
        }
        $sql = 'SELECT ii.guid AS item_guid, ii.count, ii.durability, ii.charges, ii.itemEntry, ii.owner_guid,
                       ci.guid AS character_guid, ci.bag, ci.slot,
                       c.name AS character_name, c.level, c.class, c.race,
                       container.slot AS container_slot,
                       container.bag AS container_bag,
                       container_item.itemEntry AS container_entry
                FROM item_instance ii
                JOIN character_inventory ci ON ci.item = ii.guid
                JOIN characters c ON c.guid = ci.guid
                LEFT JOIN character_inventory container ON container.item = ci.bag AND container.guid = ci.guid
                LEFT JOIN item_instance container_item ON container_item.guid = ci.bag
                WHERE ii.itemEntry = :entry
                ORDER BY c.name ASC, ii.guid ASC';
        $stmt = $this->chars->prepare($sql);
        $stmt->bindValue(':entry', $entry, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return ['item' => $itemMeta, 'owners' => [], 'totals' => ['characters' => 0, 'instances' => 0, 'count' => 0]];
        }

        $containerEntries = [];
        foreach ($rows as $row) {
            $contEntry = isset($row['container_entry']) ? (int) $row['container_entry'] : 0;
            if ($contEntry > 0) {
                $containerEntries[$contEntry] = true;
            }
        }
        $containerNames = $containerEntries ? $this->resolveItemNames(array_keys($containerEntries)) : [];

        $owners = [];
        $totals = ['characters' => 0, 'instances' => 0, 'count' => 0];
        foreach ($rows as $row) {
            $charGuid = (int) ($row['character_guid'] ?? 0);
            if ($charGuid <= 0) {
                continue;
            }
            if (!isset($owners[$charGuid])) {
                $owners[$charGuid] = [
                    'guid' => $charGuid,
                    'name' => $row['character_name'] ?? null,
                    'level' => isset($row['level']) ? (int) $row['level'] : null,
                    'class' => isset($row['class']) ? (int) $row['class'] : null,
                    'race' => isset($row['race']) ? (int) $row['race'] : null,
                    'total_count' => 0,
                    'instances' => [],
                ];
            }
            $instanceGuid = (int) ($row['item_guid'] ?? 0);
            $count = isset($row['count']) ? (int) $row['count'] : 0;
            $bag = isset($row['bag']) ? (int) $row['bag'] : 0;
            $slot = isset($row['slot']) ? (int) $row['slot'] : 0;
            $containerSlot = isset($row['container_slot']) ? (int) $row['container_slot'] : null;
            $containerEntry = isset($row['container_entry']) ? (int) $row['container_entry'] : null;
            $location = $this->determineLocation($bag, $slot, $containerSlot);
            $containerLabel = null;
            if ($containerEntry && isset($containerNames[$containerEntry])) {
                $containerLabel = $containerNames[$containerEntry];
            }
            $owners[$charGuid]['instances'][] = [
                'instance_guid' => $instanceGuid,
                'count' => $count,
                'bag' => $bag,
                'slot' => $slot,
                'location_code' => $location['code'],
                'location_label' => $location['label'],
                'inner_slot' => $location['inner_slot'],
                'container' => $containerSlot !== null ? [
                    'slot' => $containerSlot,
                    'location_code' => $location['container']['code'] ?? null,
                    'location_label' => $location['container']['label'] ?? null,
                    'name' => $containerLabel,
                ] : null,
            ];
            $owners[$charGuid]['total_count'] += $count;
            $totals['instances'] += 1;
            $totals['count'] += $count;
        }
        $totals['characters'] = count($owners);
        return [
            'item' => $itemMeta,
            'owners' => array_values($owners),
            'totals' => $totals,
        ];
    }

    public function bulkDelete(array|string|int|null $instanceIds): array
    {
        $ids = $this->normalizeIds($instanceIds);
        if (!$ids) {
            return ['success' => false, 'message' => Lang::get('app.item_owner.api.errors.empty_selection')];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT ii.guid, ii.count, ii.itemEntry, ci.guid AS character_guid, c.name AS character_name
        FROM item_instance ii
        JOIN character_inventory ci ON ci.item = ii.guid
        JOIN characters c ON c.guid = ci.guid
                WHERE ii.guid IN (' . $placeholders . ')';
        $stmt = $this->chars->prepare($sql);
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return ['success' => false, 'message' => Lang::get('app.item_owner.api.errors.instances_not_found')];
        }
        $nameMap = $this->resolveItemNames(array_column($rows, 'itemEntry'));
        $selection = array_map(static function (array $row) use ($nameMap): array {
            $entry = (int) ($row['itemEntry'] ?? 0);
            return [
                'instance' => (int) ($row['guid'] ?? 0),
                'item_entry' => $entry,
                'item_name' => $nameMap[$entry] ?? null,
                'count' => (int) ($row['count'] ?? 0),
                'character_guid' => (int) ($row['character_guid'] ?? 0),
                'character_name' => $row['character_name'] ?? null,
            ];
        }, $rows);
        $bagRepo = $this->bagRepo();
        $results = ['deleted' => [], 'failed' => []];
        foreach ($rows as $row) {
            $guid = (int) ($row['guid'] ?? 0);
            $charGuid = (int) ($row['character_guid'] ?? 0);
            $count = (int) ($row['count'] ?? 0);
            $entry = (int) ($row['itemEntry'] ?? 0);
            if ($guid <= 0 || $charGuid <= 0) {
                $results['failed'][] = [
                    'instance' => $guid,
                    'message' => Lang::get('app.item_owner.api.errors.invalid_instance'),
                ];
                continue;
            }
            $res = $bagRepo->reduceInstance($charGuid, $guid, max(1, $count), $entry);
            if (!empty($res['success'])) {
                $results['deleted'][] = $guid;
            } else {
                $results['failed'][] = [
                    'instance' => $guid,
                    'message' => $res['message'] ?? Lang::get('app.common.api.errors.unknown'),
                ];
            }
        }
        $selectedCount = count($ids);
        $deletedCount = count($results['deleted']);
        $failedCount = count($results['failed']);
        $this->logAction('delete', [
            'selection' => $selection,
            'results' => [
                'deleted' => $results['deleted'],
                'failed' => $results['failed'],
            ],
            'counts' => [
                'selected' => $selectedCount,
                'deleted' => $deletedCount,
                'failed' => $failedCount,
            ],
        ], sprintf('Delete %d/%d item instances (failed %d)', $deletedCount, $selectedCount, $failedCount));
        Audit::log('item_owner', 'bulk_delete', (string) count($results['deleted']), [
            'server' => $this->serverId,
            'deleted' => $results['deleted'],
            'failed' => $results['failed'],
            'selection' => $selection,
        ]);
        $ok = $results['failed'] === [];
        $message = $ok
            ? Lang::get('app.item_owner.api.success.delete_done', ['count' => count($results['deleted'])])
            : Lang::get('app.item_owner.api.errors.delete_partial', ['success' => count($results['deleted']), 'failed' => count($results['failed'])]);
        return ['success' => $ok, 'message' => $message, 'result' => $results];
    }

    public function bulkReplace(array|string|int|null $instanceIds, int $newEntry): array
    {
        $ids = $this->normalizeIds($instanceIds);
        if (!$ids) {
            return ['success' => false, 'message' => Lang::get('app.item_owner.api.errors.empty_selection')];
        }
        if ($newEntry <= 0) {
            return ['success' => false, 'message' => Lang::get('app.item_owner.api.errors.invalid_new_entry')];
        }
        $newMeta = $this->loadItemMeta($newEntry, true);
        if (!$newMeta) {
            return ['success' => false, 'message' => Lang::get('app.item_owner.api.errors.new_entry_not_found')];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT ii.guid, ii.count, ii.durability, ii.itemEntry, ci.guid AS character_guid, c.name AS character_name
        FROM item_instance ii
        JOIN character_inventory ci ON ci.item = ii.guid
        JOIN characters c ON c.guid = ci.guid
                WHERE ii.guid IN (' . $placeholders . ')';
        $stmt = $this->chars->prepare($sql);
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return ['success' => false, 'message' => Lang::get('app.item_owner.api.errors.instances_not_found')];
        }
        $maxStack = $newMeta['stackable'] ?? 1;
        $maxStack = $maxStack === 0 ? 1 : $maxStack;
        $maxDurability = $newMeta['max_durability'] ?? 0;
        $nameMap = $this->resolveItemNames(array_column($rows, 'itemEntry'));
        $selection = array_map(static function (array $row) use ($nameMap): array {
            $entry = (int) ($row['itemEntry'] ?? 0);
            return [
                'instance' => (int) ($row['guid'] ?? 0),
                'item_entry' => $entry,
                'item_name' => $nameMap[$entry] ?? null,
                'count' => (int) ($row['count'] ?? 0),
                'character_guid' => (int) ($row['character_guid'] ?? 0),
                'character_name' => $row['character_name'] ?? null,
            ];
        }, $rows);
        $updated = [];
        $failed = [];
        $updateStmt = $this->chars->prepare('UPDATE item_instance SET itemEntry = :entry, durability = :durability WHERE guid = :guid');
        foreach ($rows as $row) {
            $guid = (int) ($row['guid'] ?? 0);
            $count = (int) ($row['count'] ?? 0);
            $durability = isset($row['durability']) ? (int) $row['durability'] : 0;
            if ($guid <= 0) {
                continue;
            }
            if ($count > $maxStack && $maxStack > 0) {
                $failed[] = [
                    'instance' => $guid,
                    'message' => Lang::get('app.item_owner.api.errors.stack_too_large', ['stack' => $count, 'limit' => $maxStack]),
                ];
                continue;
            }
            if ($maxDurability > 0) {
                if ($durability <= 0) {
                    $durability = $maxDurability;
                } elseif ($durability > $maxDurability) {
                    $durability = $maxDurability;
                }
            } else {
                $durability = 0;
            }
            $updateStmt->bindValue(':entry', $newEntry, PDO::PARAM_INT);
            $updateStmt->bindValue(':durability', $durability, PDO::PARAM_INT);
            $updateStmt->bindValue(':guid', $guid, PDO::PARAM_INT);
            $updateStmt->execute();
            if ($updateStmt->rowCount() > 0) {
                $updated[] = $guid;
            } else {
                $failed[] = [
                    'instance' => $guid,
                    'message' => Lang::get('app.item_owner.api.errors.replace_failed'),
                ];
            }
        }
        $selectedCount = count($ids);
        $updatedCount = count($updated);
        $failedCount = count($failed);
        $this->logAction('replace', [
            'selection' => $selection,
            'results' => [
                'updated' => $updated,
                'failed' => $failed,
            ],
            'counts' => [
                'selected' => $selectedCount,
                'updated' => $updatedCount,
                'failed' => $failedCount,
            ],
            'new_item' => [
                'entry' => $newEntry,
                'name' => $newMeta['name'] ?? null,
                'stackable' => $newMeta['stackable'] ?? null,
                'max_durability' => $newMeta['max_durability'] ?? null,
            ],
        ], sprintf('Replace %d/%d item instances with #%d (failed %d)', $updatedCount, $selectedCount, $newEntry, $failedCount));
        Audit::log('item_owner', 'bulk_replace', (string) count($updated), [
            'server' => $this->serverId,
            'new_entry' => $newEntry,
            'updated' => $updated,
            'failed' => $failed,
            'selection' => $selection,
        ]);
        $ok = $failed === [];
        $message = $ok
            ? Lang::get('app.item_owner.api.success.replace_done', ['count' => count($updated)])
            : Lang::get('app.item_owner.api.errors.replace_partial', ['success' => count($updated), 'failed' => count($failed)]);
        return ['success' => $ok, 'message' => $message, 'result' => ['updated' => $updated, 'failed' => $failed]];
    }

    private function determineLocation(int $bag, int $slot, ?int $containerSlot = null): array
    {
        if ($bag === 0) {
            if (isset(self::EQUIPMENT_SLOTS[$slot])) {
                return [
                    'code' => self::EQUIPMENT_SLOTS[$slot],
                    'label' => Lang::get('app.item_owner.locations.' . self::EQUIPMENT_SLOTS[$slot]),
                    'inner_slot' => null,
                    'container' => [],
                ];
            }
            foreach (self::SLOT_GROUPS as $code => $range) {
                if ($slot >= $range[0] && $slot <= $range[1]) {
                    $index = $slot - $range[0] + 1;
                    return [
                        'code' => $code,
                        'label' => Lang::get('app.item_owner.locations.' . $code, ['slot' => $index]),
                        'inner_slot' => $index,
                        'container' => [],
                    ];
                }
            }
            if ($slot >= 19 && $slot <= 22) {
                $idx = $slot - 19 + 1;
                $code = 'inventory.bag_slot';
                return [
                    'code' => $code,
                    'label' => Lang::get('app.item_owner.locations.' . $code, ['slot' => $idx]),
                    'inner_slot' => $idx,
                    'container' => [],
                ];
            }
            if ($slot >= 67 && $slot <= 73) {
                $idx = $slot - 67 + 1;
                $code = 'bank.bag_slot';
                return [
                    'code' => $code,
                    'label' => Lang::get('app.item_owner.locations.' . $code, ['slot' => $idx]),
                    'inner_slot' => $idx,
                    'container' => [],
                ];
            }
            return [
                'code' => 'inventory.unknown',
                'label' => Lang::get('app.item_owner.locations.inventory.unknown'),
                'inner_slot' => null,
                'container' => [],
            ];
        }
        $innerSlot = $slot + 1;
        $container = null;
        if ($containerSlot !== null) {
            if ($containerSlot >= 19 && $containerSlot <= 22) {
                $idx = $containerSlot - 19 + 1;
                $container = [
                    'code' => 'inventory.bag_slot',
                    'label' => Lang::get('app.item_owner.locations.inventory.bag_slot', ['slot' => $idx]),
                ];
                $label = Lang::get('app.item_owner.locations.inventory.bag_inner', ['bag' => $idx, 'slot' => $innerSlot]);
                return [
                    'code' => 'inventory.bag_inner',
                    'label' => $label,
                    'inner_slot' => $innerSlot,
                    'container' => $container,
                ];
            }
            if ($containerSlot >= 67 && $containerSlot <= 73) {
                $idx = $containerSlot - 67 + 1;
                $container = [
                    'code' => 'bank.bag_slot',
                    'label' => Lang::get('app.item_owner.locations.bank.bag_slot', ['slot' => $idx]),
                ];
                $label = Lang::get('app.item_owner.locations.bank.bag_inner', ['bag' => $idx, 'slot' => $innerSlot]);
                return [
                    'code' => 'bank.bag_inner',
                    'label' => $label,
                    'inner_slot' => $innerSlot,
                    'container' => $container,
                ];
            }
        }
        return [
            'code' => 'inventory.bag_inner',
            'label' => Lang::get('app.item_owner.locations.inventory.bag_inner', ['bag' => '?', 'slot' => $innerSlot]),
            'inner_slot' => $innerSlot,
            'container' => $container ?? [],
        ];
    }

    private function normalizeIds(array|string|int|null $ids): array
    {
        $collected = [];

        $collect = static function ($value) use (&$collected, &$collect): void {
            if (is_array($value)) {
                foreach ($value as $inner) {
                    $collect($inner);
                }

                return;
            }

            if ($value === null) {
                return;
            }

            if ($value instanceof \Stringable) {
                $value = (string) $value;
            }

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '') {
                    return;
                }

                if (preg_match_all('/\d+/', $value, $matches) && !empty($matches[0])) {
                    foreach ($matches[0] as $match) {
                        $intVal = (int) $match;

                        if ($intVal > 0) {
                            $collected[$intVal] = $intVal;
                        }
                    }

                    return;
                }

                $value = (int) $value;
            }

            if (is_float($value) || is_int($value)) {
                $intVal = (int) $value;

                if ($intVal > 0) {
                    $collected[$intVal] = $intVal;
                }
            }
        };

        if ($ids !== null) {
            $collect($ids);
        }

        return array_values($collected);
    }

    private function bagRepo(): BagQueryRepository
    {
        if (!$this->bagRepo) {
            $this->bagRepo = new BagQueryRepository($this->serverId);
        }
        return $this->bagRepo;
    }

    private function loadItemMeta(int $entry, bool $withStack = false): ?array
    {
        $columns = 'i.entry, i.name, i.Quality AS quality';
        if ($withStack) {
            $columns .= ', i.stackable, i.MaxDurability AS max_durability';
        }
        $sql = 'SELECT ' . $columns . ', COALESCE(li.name_loc4, li.name_loc8, li.name_loc5, li.name_loc6, li.name_loc7) AS local_name
                FROM item_template i
                LEFT JOIN locales_item li ON li.entry = i.entry
                WHERE i.entry = :entry LIMIT 1';
        try {
            $stmt = $this->world->prepare($sql);
            $stmt->bindValue(':entry', $entry, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $fallback = 'SELECT ' . ($withStack ? 'entry, name, Quality AS quality, stackable, MaxDurability AS max_durability' : 'entry, name, Quality AS quality') . '
                FROM item_template WHERE entry = :entry LIMIT 1';
            try {
                $stmt = $this->world->prepare($fallback);
                $stmt->bindValue(':entry', $entry, PDO::PARAM_INT);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $ignored) {
                $row = false;
            }
        }
        if (!$row) {
            return null;
        }
        return [
            'entry' => (int) $row['entry'],
            'name' => $row['local_name'] ?? $row['name'] ?? ('#' . $entry),
            'name_en' => $row['name'] ?? null,
            'quality' => isset($row['quality']) ? (int) $row['quality'] : null,
            'stackable' => $withStack && isset($row['stackable']) ? (int) $row['stackable'] : null,
            'max_durability' => $withStack && isset($row['max_durability']) ? (int) $row['max_durability'] : null,
        ];
    }

    private function resolveItemNames(array $entries): array
    {
        if (!$entries) {
            return [];
        }
        $entries = array_values(array_unique(array_filter(array_map('intval', $entries), fn ($v) => $v > 0)));
        if (!$entries) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($entries), '?'));
        $sql = 'SELECT i.entry, COALESCE(li.name_loc4, li.name_loc8, li.name_loc5, li.name_loc6, li.name_loc7, i.name) AS name
                FROM item_template i
                LEFT JOIN locales_item li ON li.entry = i.entry
                WHERE i.entry IN (' . $placeholders . ')';
        try {
            $stmt = $this->world->prepare($sql);
            foreach ($entries as $index => $entry) {
                $stmt->bindValue($index + 1, $entry, PDO::PARAM_INT);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $fallback = 'SELECT entry, name FROM item_template WHERE entry IN (' . $placeholders . ')';
            try {
                $stmt = $this->world->prepare($fallback);
                foreach ($entries as $index => $entry) {
                    $stmt->bindValue($index + 1, $entry, PDO::PARAM_INT);
                }
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $ignored) {
                $rows = [];
            }
        }
        $map = [];
        foreach ($rows as $row) {
            $entry = (int) ($row['entry'] ?? 0);
            if ($entry > 0) {
                $map[$entry] = $row['name'] ?? ('#' . $entry);
            }
        }
        return $map;
    }

    private function logsDir(): string
    {
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function logAction(string $action, array $context, ?string $summary = null): void
    {
        try {
            $data = $context + [
                'action' => $action,
                'server' => $this->serverId,
                'admin' => $_SESSION['panel_user'] ?? ($_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown')),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'time_iso' => date('c'),
            ];
            if ($summary !== null) {
                $data['message'] = $summary;
            } elseif (!isset($data['message']) || !is_string($data['message'])) {
                $data['message'] = ucfirst($action) . ' action executed';
            }
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $line = sprintf('[%s] item_owner.%s %s%s', date('Y-m-d H:i:s'), $action, $json ?: '{}', PHP_EOL);
            @file_put_contents($this->logFile, $line, FILE_APPEND);
        } catch (Throwable $e) {

        }
    }
}


