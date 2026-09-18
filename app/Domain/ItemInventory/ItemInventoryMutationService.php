<?php
/**
 * File: app/Domain/ItemInventory/ItemInventoryMutationService.php
 * Purpose: The single write path for item_instance / character_inventory data.
 *          Both the character axis and the item axis call into this service, so
 *          there is exactly one transactional strategy instead of the two
 *          divergent ones that used to exist.
 *
 * Guarantees:
 *   - every mutation runs inside one outer transaction with SELECT ... FOR UPDATE
 *     row locks, so a partially applied batch is rolled back instead of leaving
 *     half-modified inventory behind;
 *   - removing a container explicitly decides what happens to the items inside
 *     it (refuse / destroy) instead of silently orphaning them;
 *   - replacing an instance cannot write a stack larger than the new item's
 *     stackable limit: overflowing stacks are split into new instances in free
 *     slots of the same container;
 *   - replacing an instance clears the previous item's instance-level residue
 *     (charges, random property, enchantments) on the columns that exist.
 *
 * Class: ItemInventoryMutationService
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\ItemInventory;

use Acme\Panel\Core\Lang;
use Acme\Panel\Domain\Support\MultiServerRepository;
use PDO;
use Throwable;

class ItemInventoryMutationService extends MultiServerRepository
{
    /** Never hand out a guid at or above this value (keeps the top range free). */
    private const GUID_CEILING = 2999999999;

    private PDO $chars;
    private PDO $world;
    private ItemInstanceSchema $schema;
    private LocationResolver $locations;

    /** @var array<int,array<int,array{bag:int,slot:int,item:int}>> */
    private array $inventoryMaps = [];
    /** @var array<int,array<string,mixed>> */
    private array $metaCache = [];
    /** Cached "current max guid" allocator for item_instance. */
    private ?int $nextInstanceGuid = null;

    public function __construct(?int $serverId = null)
    {
        parent::__construct($serverId);
        $this->chars = $this->characters();
        $this->world = $this->world();
        $this->schema = new ItemInstanceSchema($this->chars);
        $this->locations = new LocationResolver();
    }

    /* ------------------------------------------------------------------ *
     * Reduce / delete one instance
     * ------------------------------------------------------------------ */

    /**
     * Reduce one stack by $qty. A reduction that reaches zero removes the whole
     * instance. When the instance is a container that still holds items, the
     * operation is refused unless $destroyContents is true, in which case the
     * contained items are destroyed in the same transaction.
     *
     * @return array{success:bool,message:string,new_count:int,destroyed?:int,contained?:int}
     */
    public function reduceInstance(
        int $characterGuid,
        int $itemInstanceGuid,
        int $qty,
        ?int $itemEntry = null,
        bool $destroyContents = false
    ): array {
        if ($qty <= 0) {
            return $this->fail('app.item_inventory.api.errors.quantity_positive', -1);
        }
        if ($characterGuid <= 0 || $itemInstanceGuid <= 0) {
            return $this->fail('app.item_inventory.api.errors.invalid_parameters', -1);
        }

        $result = $this->transaction(function () use ($characterGuid, $itemInstanceGuid, $qty, $destroyContents): array {
            $row = $this->lockInstance($characterGuid, $itemInstanceGuid);
            if ($row === null) {
                return $this->fail('app.item_inventory.api.errors.instance_not_found', -1);
            }

            $current = (int) $row['count'];
            if ($qty > $current) {
                return $this->fail('app.item_inventory.api.errors.quantity_exceeds_stack', $current);
            }

            $newCount = $current - $qty;
            if ($newCount > 0) {
                $stmt = $this->chars->prepare('UPDATE item_instance SET count = :c WHERE guid = :g');
                $stmt->execute([':c' => $newCount, ':g' => $itemInstanceGuid]);

                return [
                    'success' => true,
                    'message' => Lang::get('app.item_inventory.api.success.quantity_reduced'),
                    'new_count' => $newCount,
                ];
            }

            $contained = count($this->containedInstances($characterGuid, $itemInstanceGuid));
            if ($contained > 0 && !$destroyContents) {
                // Refuse rather than orphan the contained items.
                return [
                    'success' => false,
                    'message' => Lang::get('app.item_inventory.api.errors.container_not_empty', ['count' => $contained]),
                    'new_count' => $current,
                    'contained' => $contained,
                ];
            }

            $destroyed = 0;
            if ($contained > 0) {
                $destroyed = $this->destroyContainedInstances($characterGuid, $itemInstanceGuid);
            }

            $delInv = $this->chars->prepare('DELETE FROM character_inventory WHERE guid = :cg AND item = :g');
            $delInv->execute([':cg' => $characterGuid, ':g' => $itemInstanceGuid]);
            if ($delInv->rowCount() === 0) {
                return $this->fail('app.item_inventory.api.errors.inventory_mismatch', -1);
            }

            $delInstance = $this->chars->prepare('DELETE FROM item_instance WHERE guid = :g');
            $delInstance->execute([':g' => $itemInstanceGuid]);

            $payload = [
                'success' => true,
                'message' => Lang::get('app.item_inventory.api.success.item_deleted'),
                'new_count' => 0,
            ];
            if ($destroyed > 0) {
                $payload['destroyed'] = $destroyed;
                $payload['message'] = Lang::get('app.item_inventory.api.success.item_deleted_with_contents', ['count' => $destroyed]);
            }

            return $payload;
        });

        $this->record('reduce_instance', [
            'server' => $this->serverId,
            'character_guid' => $characterGuid,
            'item_instance' => $itemInstanceGuid,
            'item_entry' => $itemEntry,
            'quantity' => $qty,
            'success' => (bool) ($result['success'] ?? false),
            'new_count' => $result['new_count'] ?? null,
            'destroyed' => $result['destroyed'] ?? null,
            'message' => $result['message'] ?? null,
        ]);

        return $result;
    }

    /* ------------------------------------------------------------------ *
     * Bulk delete (item axis)
     * ------------------------------------------------------------------ */

    /**
     * Delete every requested instance in ONE transaction. Validation runs over
     * the whole selection first, so a failure is reported without having applied
     * any part of the batch. Empty containers require $destroyContents.
     *
     * @param array<int,int> $instanceIds
     * @return array{success:bool,message:string,deleted:array<int,int>,failed:array<int,array{instance:int,message:string}>,destroyed:int}
     */
    public function bulkDelete(array $instanceIds, bool $destroyContents = false): array
    {
        $instanceIds = $this->normalizeIds($instanceIds);
        if (!$instanceIds) {
            return $this->bulkFailure('app.item_inventory.api.errors.empty_selection');
        }

        $failed = [];
        $deleted = [];
        $destroyedTotal = 0;

        // $deleted / $failed / $destroyedTotal are filled inside the closure; the
        // wrapper rolls the transaction back when the callback reports failure, so
        // outside the closure they are only read once $result['success'] is known.
        $result = $this->transaction(function () use ($instanceIds, $destroyContents, &$failed, &$deleted, &$destroyedTotal): array {
            $instances = [];
            foreach ($instanceIds as $instanceId) {
                $row = $this->lockInstanceAnyOwner($instanceId);
                if ($row === null) {
                    $failed[] = [
                        'instance' => $instanceId,
                        'message' => Lang::get('app.item_inventory.api.errors.invalid_instance'),
                    ];
                    continue;
                }
                $instances[] = $row;
            }

            if (!$instances) {
                return $this->fail('app.item_inventory.api.errors.instances_not_found', -1);
            }

            // Pre-flight: every container in the selection needs a decision.
            $blocked = [];
            foreach ($instances as $row) {
                $contained = count($this->containedInstances($row['character_guid'], $row['instance_guid']));
                if ($contained > 0 && !$destroyContents) {
                    $blocked[] = ['instance' => $row['instance_guid'], 'contained' => $contained];
                }
            }
            if ($blocked) {
                $failed = array_merge($failed, array_map(static fn (array $entry): array => [
                    'instance' => $entry['instance'],
                    'message' => Lang::get('app.item_inventory.api.errors.container_not_empty', ['count' => $entry['contained']]),
                ], $blocked));

                return $this->fail('app.item_inventory.api.errors.container_not_empty_bulk', -1);
            }

            foreach ($instances as $row) {
                $instanceGuid = $row['instance_guid'];
                $characterGuid = $row['character_guid'];

                if ($destroyContents) {
                    $destroyedTotal += $this->destroyContainedInstances($characterGuid, $instanceGuid);
                }

                $delInv = $this->chars->prepare('DELETE FROM character_inventory WHERE guid = :cg AND item = :g');
                $delInv->execute([':cg' => $characterGuid, ':g' => $instanceGuid]);
                if ($delInv->rowCount() === 0) {
                    $failed[] = [
                        'instance' => $instanceGuid,
                        'message' => Lang::get('app.item_inventory.api.errors.inventory_mismatch'),
                    ];
                    continue;
                }

                $delInstance = $this->chars->prepare('DELETE FROM item_instance WHERE guid = :g');
                $delInstance->execute([':g' => $instanceGuid]);
                $deleted[] = $instanceGuid;
            }

            return ['success' => true, 'message' => '', 'new_count' => 0];
        });

        // All-or-nothing, matching bulkReplace: a selection that cannot be fully
        // deleted (for example because someone already removed one of the
        // instances in game) is reported as a failure, never as a silent partial
        // success.
        $ok = (bool) ($result['success'] ?? false) && $deleted !== [] && $failed === [];
        if ($ok) {
            $message = Lang::get('app.item_inventory.api.success.delete_done', ['count' => count($deleted)]);
        } elseif ($deleted !== [] && $failed !== []) {
            $message = Lang::get('app.item_inventory.api.errors.delete_partial', [
                'success' => count($deleted),
                'failed' => count($failed),
            ]);
        } else {
            $message = (string) ($result['message'] ?? Lang::get('app.item_inventory.api.errors.delete_failed'));
        }

        $this->record('bulk_delete', [
            'server' => $this->serverId,
            'selected' => $instanceIds,
            'deleted' => $deleted,
            'failed' => $failed,
            'destroyed' => $destroyedTotal,
            'success' => $ok,
        ]);

        return [
            'success' => $ok,
            'message' => $message,
            'deleted' => $deleted,
            'failed' => $failed,
            'destroyed' => $destroyedTotal,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bulk replace (item axis)
     * ------------------------------------------------------------------ */

    /**
     * Replace every selected instance with $newEntry in ONE transaction.
     *
     * When the existing stack is larger than the new item's stackable limit, the
     * overflow is written as additional instances in free slots of the same
     * container. If there is not enough room, the whole batch is refused.
     *
     * @param array<int,int> $instanceIds
     * @return array{success:bool,message:string,updated:array<int,int>,created:array<int,int>,failed:array<int,array{instance:int,message:string}>}
     */
    public function bulkReplace(array $instanceIds, int $newEntry): array
    {
        $instanceIds = $this->normalizeIds($instanceIds);
        if (!$instanceIds) {
            return $this->bulkReplaceFailure('app.item_inventory.api.errors.empty_selection');
        }
        if ($newEntry <= 0) {
            return $this->bulkReplaceFailure('app.item_inventory.api.errors.invalid_new_entry');
        }

        $newMeta = $this->loadItemMeta($newEntry, true);
        if (!$newMeta) {
            return $this->bulkReplaceFailure('app.item_inventory.api.errors.new_entry_not_found');
        }

        $maxStack = max(1, (int) ($newMeta['stackable'] ?? 1));
        $maxDurability = (int) ($newMeta['max_durability'] ?? 0);
        $clearColumns = $this->schema->clearableColumns();

        $failed = [];
        $updated = [];
        $created = [];
        $plans = [];

        $result = $this->transaction(function () use (
            $instanceIds,
            $newEntry,
            $maxStack,
            $maxDurability,
            $clearColumns,
            &$failed,
            &$updated,
            &$created,
            &$plans
        ): array {
            // Phase 1 — lock and validate everything. Strict mode: if any selected
            // instance cannot be resolved (already gone, or owned by nobody) the
            // whole batch is refused, so a caller never observes a partial replace.
            $instances = [];
            foreach ($instanceIds as $instanceId) {
                $row = $this->lockInstanceAnyOwner($instanceId);
                if ($row === null) {
                    $failed[] = [
                        'instance' => $instanceId,
                        'message' => Lang::get('app.item_inventory.api.errors.invalid_instance'),
                    ];
                    continue;
                }
                $instances[] = $row;
            }

            if (!$instances) {
                return $this->fail('app.item_inventory.api.errors.instances_not_found', -1);
            }

            if ($failed !== []) {
                return $this->fail('app.item_inventory.api.errors.batch_contains_missing', -1);
            }

            // Phase 2 — plan stack splits per (character, container) destination.
            // Snapshot the pristine counts here: Phase 3 mutates the rows, so the
            // plan must never re-read `count` from an already-updated row.
            $slotsNeeded = [];
            foreach ($instances as $row) {
                $count = max(0, (int) $row['count']);
                $pieces = $count <= 0 ? 1 : (int) ceil($count / $maxStack);
                $extra = $pieces - 1;
                if ($extra <= 0) {
                    continue;
                }

                $key = $row['character_guid'] . ':' . $row['bag'];
                if (!isset($slotsNeeded[$key])) {
                    $slotsNeeded[$key] = [
                        'character_guid' => $row['character_guid'],
                        'bag' => $row['bag'],
                        'needed' => 0,
                    ];
                }
                $slotsNeeded[$key]['needed'] += $extra;

                $plans[$row['instance_guid']] = [
                    'count' => $count,
                    'pieces' => $pieces,
                    'extra' => $extra,
                ];
            }

            foreach ($slotsNeeded as $key => $need) {
                $free = $this->availableSlots($need['character_guid'], $need['bag']);
                if (count($free) < $need['needed']) {
                    return $this->fail('app.item_inventory.api.errors.not_enough_slots', -1, [
                        'needed' => $need['needed'],
                        'available' => count($free),
                    ]);
                }
                $slotsNeeded[$key]['slots'] = $free;
            }

            // Phase 3 — apply.
            foreach ($instances as $row) {
                $instanceGuid = $row['instance_guid'];
                $plan = $plans[$instanceGuid] ?? ['count' => max(0, (int) $row['count']), 'pieces' => 1, 'extra' => 0];

                $durability = $this->resolveDurability((int) $row['durability'], $maxDurability);

                // Create the overflow instances first so the original row can be
                // written once with its final quantity. The original always keeps
                // the first chunk; the rest is split into free slots.
                $originalCount = $plan['count'];
                $chunks = [];
                $remaining = $originalCount;
                while ($remaining > 0) {
                    $chunk = min($remaining, $maxStack);
                    $chunks[] = $chunk;
                    $remaining -= $chunk;
                }
                if (!$chunks) {
                    $chunks = [0];
                }

                $newIds = [];
                if ($plan['extra'] > 0) {
                    $key = $row['character_guid'] . ':' . $row['bag'];
                    for ($i = 1; $i < count($chunks); $i++) {
                        $slot = array_shift($slotsNeeded[$key]['slots']);
                        if ($slot === null) {
                            return $this->fail('app.item_inventory.api.errors.not_enough_slots', -1, [
                                'needed' => 1,
                                'available' => 0,
                            ]);
                        }
                        $newIds[] = $this->createInstance(
                            $row['character_guid'],
                            $row['bag'],
                            $slot,
                            $instanceGuid,
                            $newEntry,
                            $chunks[$i],
                            $durability
                        );
                    }
                }

                // The original instance keeps the first chunk.
                $assignments = [
                    ':entry' => $newEntry,
                    ':durability' => $durability,
                    ':count' => $chunks[0],
                    ':guid' => $instanceGuid,
                ];
                $setParts = ['itemEntry = :entry', 'durability = :durability', 'count = :count'];
                foreach ($clearColumns as $index => $column) {
                    $setParts[] = '`' . $column . '` = :clear' . $index;
                    $assignments[':clear' . $index] = $this->schema->clearValue($column);
                }

                $stmt = $this->chars->prepare('UPDATE item_instance SET ' . implode(', ', $setParts) . ' WHERE guid = :guid');
                $stmt->execute($assignments);
                $updated[] = $instanceGuid;

                if ($newIds) {
                    $created = array_merge($created, $newIds);
                }
            }

            return ['success' => true, 'message' => '', 'new_count' => 0];
        });

        $ok = (bool) ($result['success'] ?? false) && $updated !== [];
        if ($ok) {
            $message = $created
                ? Lang::get('app.item_inventory.api.success.replace_done_split', [
                    'count' => count($updated),
                    'created' => count($created),
                ])
                : Lang::get('app.item_inventory.api.success.replace_done', ['count' => count($updated)]);
        } else {
            $message = (string) ($result['message'] ?? Lang::get('app.item_inventory.api.errors.replace_failed'));
        }

        $this->record('bulk_replace', [
            'server' => $this->serverId,
            'new_entry' => $newEntry,
            'selected' => $instanceIds,
            'updated' => $updated,
            'created' => $created,
            'failed' => $failed,
            'max_stack' => $maxStack,
            'cleared_columns' => $clearColumns,
            'success' => $ok,
        ]);

        return [
            'success' => $ok,
            'message' => $message,
            'updated' => $updated,
            'created' => $created,
            'failed' => $failed,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    /**
     * Runs $callback inside a transaction and always returns an array. Any
     * exception rolls the whole thing back and is reported as a failure.
     *
     * @param callable():array<string,mixed> $callback
     * @return array<string,mixed>
     */
    private function transaction(callable $callback): array
    {
        $pdo = $this->chars;
        $pdo->beginTransaction();
        try {
            $result = $callback();
            if (($result['success'] ?? false) === true) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $this->record('mutation_failed', [
                'server' => $this->serverId,
                'message' => $e->getMessage(),
                'sql_state' => $e instanceof \PDOException ? ($e->errorInfo[0] ?? null) : null,
            ]);

            return [
                'success' => false,
                'message' => Lang::get('app.item_inventory.api.errors.reduce_failed', ['message' => $e->getMessage()]),
                'new_count' => -1,
            ];
        }
    }

    /**
     * Lock one instance that must belong to $characterGuid.
     *
     * @return array{instance_guid:int,character_guid:int,entry:int,count:int,durability:int,bag:int,slot:int}|null
     */
    private function lockInstance(int $characterGuid, int $itemInstanceGuid): ?array
    {
        $stmt = $this->chars->prepare(
            'SELECT ii.guid, ii.itemEntry, ii.count, ii.durability, ci.guid AS character_guid, ci.bag, ci.slot
             FROM item_instance ii
             JOIN character_inventory ci ON ci.item = ii.guid
             WHERE ii.guid = :g AND ci.guid = :cg
             FOR UPDATE'
        );
        $stmt->execute([':g' => $itemInstanceGuid, ':cg' => $characterGuid]);

        return $this->normalizeInstanceRow($stmt->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * Lock one instance regardless of owner (the item axis searches globally).
     *
     * @return array{instance_guid:int,character_guid:int,entry:int,count:int,durability:int,bag:int,slot:int}|null
     */
    private function lockInstanceAnyOwner(int $itemInstanceGuid): ?array
    {
        $stmt = $this->chars->prepare(
            'SELECT ii.guid, ii.itemEntry, ii.count, ii.durability, ci.guid AS character_guid, ci.bag, ci.slot
             FROM item_instance ii
             JOIN character_inventory ci ON ci.item = ii.guid
             WHERE ii.guid = :g
             FOR UPDATE'
        );
        $stmt->execute([':g' => $itemInstanceGuid]);

        return $this->normalizeInstanceRow($stmt->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string,mixed>|false $row
     * @return array{instance_guid:int,character_guid:int,entry:int,count:int,durability:int,bag:int,slot:int}|null
     */
    private function normalizeInstanceRow($row): ?array
    {
        if (!$row) {
            return null;
        }

        return [
            'instance_guid' => (int) ($row['guid'] ?? 0),
            'character_guid' => (int) ($row['character_guid'] ?? 0),
            'entry' => (int) ($row['itemEntry'] ?? 0),
            'count' => (int) ($row['count'] ?? 0),
            'durability' => (int) ($row['durability'] ?? 0),
            'bag' => (int) ($row['bag'] ?? 0),
            'slot' => (int) ($row['slot'] ?? 0),
        ];
    }

    /**
     * Instances contained by $containerInstanceGuid.
     *
     * @return array<int,array{item:int,slot:int}>
     */
    private function containedInstances(int $characterGuid, int $containerInstanceGuid): array
    {
        $stmt = $this->chars->prepare(
            'SELECT item, slot FROM character_inventory WHERE guid = :g AND bag = :b ORDER BY slot ASC'
        );
        $stmt->execute([':g' => $characterGuid, ':b' => $containerInstanceGuid]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $rows[] = ['item' => (int) $row['item'], 'slot' => (int) $row['slot']];
        }

        return $rows;
    }

    /**
     * Destroy everything inside a container. Called only when the caller either
     * explicitly asked for it or already validated the selection.
     */
    private function destroyContainedInstances(int $characterGuid, int $containerInstanceGuid): int
    {
        $contained = $this->containedInstances($characterGuid, $containerInstanceGuid);
        if (!$contained) {
            return 0;
        }

        $itemGuids = [];
        foreach ($contained as $row) {
            if ($row['item'] > 0) {
                $itemGuids[] = $row['item'];
            }
        }

        // Remove nested containers first so a bag-in-bag tree unwinds cleanly.
        foreach ($contained as $row) {
            $this->destroyContainedInstances($characterGuid, $row['item']);
        }

        if ($itemGuids) {
            $placeholders = implode(',', array_fill(0, count($itemGuids), '?'));
            $stmt = $this->chars->prepare('DELETE FROM item_instance WHERE guid IN (' . $placeholders . ')');
            $stmt->execute($itemGuids);
        }

        $stmt = $this->chars->prepare('DELETE FROM character_inventory WHERE guid = :g AND bag = :b');
        $stmt->execute([':g' => $characterGuid, ':b' => $containerInstanceGuid]);

        return count($itemGuids);
    }

    /**
     * Free ordered slots in a destination: backpack (bag = 0) or inside a container.
     *
     * @return array<int,int>
     */
    private function availableSlots(int $characterGuid, int $bag): array
    {
        if ($characterGuid <= 0) {
            return [];
        }

        if ($bag === 0) {
            return $this->availableBackpackSlots($characterGuid);
        }

        return $this->availableContainerSlots($characterGuid, $bag);
    }

    /**
     * @return array<int,int>
     */
    private function availableBackpackSlots(int $characterGuid): array
    {
        [$from, $to] = $this->locations->backpackSlotRange();

        $stmt = $this->chars->prepare('SELECT slot FROM character_inventory WHERE guid = :g AND bag = 0 AND slot BETWEEN :a AND :b');
        $stmt->execute([':g' => $characterGuid, ':a' => $from, ':b' => $to]);
        $taken = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $free = [];
        for ($slot = $from; $slot <= $to; $slot++) {
            if (!in_array($slot, $taken, true)) {
                $free[] = $slot;
            }
        }

        return $free;
    }

    /**
     * @return array<int,int>
     */
    private function availableContainerSlots(int $characterGuid, int $containerInstanceGuid): array
    {
        $capacity = $this->containerCapacity($containerInstanceGuid);
        if ($capacity <= 0) {
            return [];
        }

        $stmt = $this->chars->prepare('SELECT slot FROM character_inventory WHERE guid = :g AND bag = :b');
        $stmt->execute([':g' => $characterGuid, ':b' => $containerInstanceGuid]);
        $taken = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $free = [];
        for ($slot = 0; $slot < $capacity; $slot++) {
            if (!in_array($slot, $taken, true)) {
                $free[] = $slot;
            }
        }

        return $free;
    }

    /**
     * Container capacity = the container item's ContainerSlots.
     */
    private function containerCapacity(int $containerInstanceGuid): int
    {
        $stmt = $this->chars->prepare('SELECT itemEntry FROM item_instance WHERE guid = :g LIMIT 1');
        $stmt->execute([':g' => $containerInstanceGuid]);
        $entry = (int) $stmt->fetchColumn();
        if ($entry <= 0) {
            return 0;
        }

        $capacity = $this->templateColumn($entry, 'ContainerSlots');
        if ($capacity === null) {
            return 0;
        }

        // Guard against nonsense template values.
        return max(0, min((int) $capacity, 64));
    }

    /**
     * Read one numeric column from item_template, tolerating schema differences.
     */
    private function templateColumn(int $entry, string $column): ?int
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return null;
        }

        try {
            $stmt = $this->world->prepare('SELECT `' . $column . '` FROM item_template WHERE entry = :e LIMIT 1');
            $stmt->execute([':e' => $entry]);
            $value = $stmt->fetchColumn();

            return $value === false || $value === null ? null : (int) $value;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadItemMeta(int $entry, bool $withStack = false): ?array
    {
        if ($entry <= 0) {
            return null;
        }
        $cacheKey = $entry . ':' . ($withStack ? '1' : '0');
        if (isset($this->metaCache[$cacheKey])) {
            return $this->metaCache[$cacheKey];
        }

        $columns = 'entry, name, Quality AS quality';
        if ($withStack) {
            $columns .= ', stackable, MaxDurability AS max_durability';
        }

        try {
            $stmt = $this->world->prepare('SELECT ' . $columns . ' FROM item_template WHERE entry = :entry LIMIT 1');
            $stmt->bindValue(':entry', $entry, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // `stackable` / MaxDurability are absent on some AzerothCore forks.
            try {
                $stmt = $this->world->prepare('SELECT entry, name, Quality AS quality FROM item_template WHERE entry = :entry LIMIT 1');
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

        $meta = [
            'entry' => (int) $row['entry'],
            'name' => $row['name'] ?? ('#' . $entry),
            'quality' => isset($row['quality']) ? (int) $row['quality'] : null,
            'stackable' => $withStack && isset($row['stackable']) ? (int) $row['stackable'] : null,
            'max_durability' => $withStack && isset($row['max_durability']) ? (int) $row['max_durability'] : null,
        ];

        $this->metaCache[$cacheKey] = $meta;

        return $meta;
    }

    private function resolveDurability(int $current, int $maxDurability): int
    {
        if ($maxDurability <= 0) {
            return 0;
        }
        if ($current <= 0 || $current > $maxDurability) {
            return $maxDurability;
        }

        return $current;
    }

    /**
     * Create a new instance in a free slot, then attach it to the container.
     *
     * The INSERT column list comes from the probed schema (see ItemInstanceSchema)
     * because `guid` is a manual primary key on AzerothCore — not AUTO_INCREMENT —
     * and columns such as `enchantments` are NOT NULL without a default, so a
     * hand-written minimal INSERT cannot work on a live realm.
     *
     * @return int new item_instance guid
     */
    private function createInstance(
        int $characterGuid,
        int $bag,
        int $slot,
        int $sourceInstanceGuid,
        int $entry,
        int $count,
        int $durability
    ): int {
        $newGuid = $this->createInstanceRow($sourceInstanceGuid, $entry, $count, $durability);

        $stmt = $this->chars->prepare(
            'INSERT INTO character_inventory (guid, bag, slot, item) VALUES (:g, :b, :s, :i)'
        );
        $stmt->execute([':g' => $characterGuid, ':b' => $bag, ':s' => $slot, ':i' => $newGuid]);

        return $newGuid;
    }

    /**
     * Insert the item_instance row and return its guid.
     *
     * Id allocation is collision-aware: MAX(guid)+1 is only a hint (out-of-band
     * tooling can hold ids far above the table max), so a duplicate-primary-key
     * failure simply advances to the next candidate instead of aborting a split.
     */
    private function createInstanceRow(int $sourceInstanceGuid, int $entry, int $count, int $durability): int
    {
        $plan = $this->schema->insertPlan();
        $useAutoGuid = $this->schema->useDefaultGuid();

        // Copy the source instance's live values first, then override entry/count.
        $source = $this->chars->prepare('SELECT * FROM item_instance WHERE guid = :g LIMIT 1');
        $source->execute([':g' => $sourceInstanceGuid]);
        $sourceRow = $source->fetch(PDO::FETCH_ASSOC) ?: [];

        $lastError = null;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $newGuid = $useAutoGuid ? 0 : $this->nextInstanceGuid();

            $columns = $useAutoGuid ? $plan['columns'] : array_merge(['guid'], $plan['columns']);
            $placeholders = [];
            foreach ($columns as $column) {
                if ($column === 'guid') {
                    $placeholders[] = ':new_guid';
                } elseif (isset($plan['bindings'][$column])) {
                    $placeholders[] = $plan['bindings'][$column];
                } else {
                    $placeholders[] = $plan['literals'][$column] ?? '0';
                }
            }

            $quoted = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
            $sql = 'INSERT INTO item_instance (' . implode(', ', $quoted) . ') VALUES (' . implode(', ', $placeholders) . ')';

            $stmt = $this->chars->prepare($sql);
            foreach ($plan['bindings'] as $column => $parameter) {
                if (!in_array($column, $columns, true)) {
                    continue;
                }
                $value = match ($column) {
                    'itemEntry' => $entry,
                    'count' => $count,
                    'durability' => $durability,
                    default => $sourceRow[$column] ?? 0,
                };
                $stmt->bindValue($parameter, $value, PDO::PARAM_INT);
            }
            if (!$useAutoGuid) {
                $stmt->bindValue(':new_guid', $newGuid, PDO::PARAM_INT);
            }

            try {
                $stmt->execute();
            } catch (Throwable $e) {
                $lastError = $e;
                if ($this->isDuplicateKey($e) && !$useAutoGuid) {
                    continue; // that guid is taken; try the next candidate
                }

                break;
            }

            if ($useAutoGuid) {
                $inserted = (int) $this->chars->lastInsertId();
                if ($inserted <= 0) {
                    $lastError = new \RuntimeException('auto-increment guid was not reported');

                    break;
                }

                return $inserted;
            }

            return $newGuid;
        }

        $this->record('instance_create_failed', [
            'server' => $this->serverId,
            'source_instance' => $sourceInstanceGuid,
            'entry' => $entry,
            'message' => $lastError?->getMessage(),
        ]);

        throw new \RuntimeException(
            Lang::get('app.item_inventory.api.errors.instance_create_failed', [
                'message' => $lastError?->getMessage() ?? 'unknown',
            ])
        );
    }

    private function isDuplicateKey(Throwable $e): bool
    {
        if (!$e instanceof \PDOException) {
            return false;
        }

        return ($e->errorInfo[0] ?? '') === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * Item instance guids are a manual primary key on AzerothCore, so allocate
     * one above every id this process knows about. `nextInstanceGuid` starts at
     * MAX(guid)+1 and is handed out monotonically.
     *
     * Ids at or above self::GUID_CEILING are never handed out: keeping the top of
     * the range free leaves room for out-of-band maintenance tooling (and for the
     * test harness) without colliding with the allocator.
     */
    private function nextInstanceGuid(): int
    {
        if ($this->nextInstanceGuid === null) {
            try {
                $max = (int) $this->chars->query('SELECT COALESCE(MAX(guid), 0) FROM item_instance')->fetchColumn();
            } catch (Throwable $e) {
                $max = 0;
            }
            $this->nextInstanceGuid = $max + 1;
        }

        if ($this->nextInstanceGuid < 1 || $this->nextInstanceGuid >= self::GUID_CEILING) {
            // The table is full up to the ceiling (or has absurd ids); restart
            // from the bottom rather than emitting an out-of-range guid.
            $this->nextInstanceGuid = 1;
        }

        $guid = $this->nextInstanceGuid;
        $this->nextInstanceGuid = $guid + 1;

        return $guid;
    }

    /**
     * @param array<int,mixed> $ids
     * @return array<int,int>
     */
    private function normalizeIds(array $ids): array
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
            if (is_int($value) || is_float($value)) {
                $intVal = (int) $value;
                if ($intVal > 0) {
                    $collected[$intVal] = $intVal;
                }
            }
        };

        $collect($ids);

        return array_values($collected);
    }

    /**
     * @return array{success:false,message:string,new_count:int}
     */
    private function fail(string $langKey, int $newCount, array $replace = []): array
    {
        return [
            'success' => false,
            'message' => Lang::get($langKey, $replace),
            'new_count' => $newCount,
        ];
    }

    /**
     * @return array{success:false,message:string,deleted:array<int,int>,failed:array<int,array{instance:int,message:string}>,destroyed:int}
     */
    private function bulkFailure(string $langKey): array
    {
        return [
            'success' => false,
            'message' => Lang::get($langKey),
            'deleted' => [],
            'failed' => [],
            'destroyed' => 0,
        ];
    }

    /**
     * @return array{success:false,message:string,updated:array<int,int>,created:array<int,int>,failed:array<int,array{instance:int,message:string}>}
     */
    private function bulkReplaceFailure(string $langKey): array
    {
        return [
            'success' => false,
            'message' => Lang::get($langKey),
            'updated' => [],
            'created' => [],
            'failed' => [],
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function record(string $event, array $context): void
    {
        ItemInventoryLog::action($event, $context);
    }
}
