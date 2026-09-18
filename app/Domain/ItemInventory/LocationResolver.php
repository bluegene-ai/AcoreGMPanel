<?php
/**
 * File: app/Domain/ItemInventory/LocationResolver.php
 * Purpose: Translates raw (bag, slot) coordinates from character_inventory into
 *          human readable inventory locations, and answers slot-allocation
 *          questions (is this a container? which slots are free?).
 *
 * ACDB coordinate contract (unchanged, no schema migration required):
 *   bag = 0              the item sits directly in a character slot
 *                          slot  0..18   equipped
 *                          slot 19..22   backpack bag slot (a bag container, or a bag-shaped item)
 *                          slot 23..38   backpack content
 *                          slot 39..66   bank content
 *                          slot 67..73   bank bag slot (a bag container)
 *                          slot 86..117  keyring content
 *                          slot 118..135 currency content
 *   bag = <item guid>    the item sits inside that container; `slot` is
 *                        container-relative, so the displayed inner slot is slot+1.
 *
 * Classes:
 *   - LocationResolver
 * Functions:
 *   - resolve()
 *   - duplicateOf()
 *   - isBagSlot()
 *   - isBankBagSlot()
 *   - backpackSlotRange()
 *   - bankBagSlotRange()
 *   - isContainerBag()
 *   - emptyLocation()
 *   - equipmentCodes()
 *   - slotGroups()
 *   - label()
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\ItemInventory;

use Acme\Panel\Core\Lang;

final class LocationResolver
{
    /** bag = 0, slot 0..18 → equipped. */
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

    /** bag = 0, contiguous slot ranges with their first slot. */
    private const SLOT_GROUPS = [
        'inventory.backpack' => [23, 38],
        'inventory.bank_main' => [39, 66],
        'inventory.keyring' => [86, 117],
        'inventory.currency' => [118, 135],
    ];

    /** Slot where bag-shaped containers live (inventory). */
    private const BACKPACK_BAG_SLOTS = [19, 22];

    /** Slot where bag-shaped containers live (bank). */
    private const BANK_BAG_SLOTS = [67, 73];

    /**
     * Resolve one inventory coordinate into a normalized location payload.
     *
     * @param array{bag:int,slot:int,item:int}|null $containerRow character_inventory row of the
     *                                                           container itself (bag !== 0), when known.
     * @return array{code:string,label:string,area:string,inner_slot:?int,container:?array}
     */
    public function resolve(int $bag, int $slot, ?array $containerRow = null): array
    {
        return $bag === 0
            ? $this->resolveDirect($slot)
            : $this->resolveNested($slot, $containerRow);
    }

    /**
     * A copy of a location payload carrying only the container branch, used when
     * reporting an item that lives inside a container.
     *
     * @param array{bag:int,slot:int,item:int}|null $containerRow
     * @return array{slot:int,location_code:string,location_label:string,location_area:string,name:?string}
     */
    public function containerPayload(?array $containerRow): ?array
    {
        if ($containerRow === null) {
            return null;
        }

        $bagSlot = (int) ($containerRow['slot'] ?? -1);
        if ($bagSlot < 0) {
            return null;
        }

        $resolved = $this->resolve(0, $bagSlot);

        return [
            'slot' => $bagSlot,
            'location_code' => $resolved['code'],
            'location_label' => $resolved['label'],
            'location_area' => $resolved['area'],
            'name' => null,
        ];
    }

    /** bag = 0 branch. */
    private function resolveDirect(int $slot): array
    {
        if (isset(self::EQUIPMENT_SLOTS[$slot])) {
            $code = self::EQUIPMENT_SLOTS[$slot];

            return $this->payload($code, 'equipment', null, null);
        }

        foreach (self::SLOT_GROUPS as $code => [$from, $to]) {
            if ($slot >= $from && $slot <= $to) {
                $index = $slot - $from + 1;

                return $this->payload($code, $this->areaOf($code), $index, null, ['slot' => $index]);
            }
        }

        // Container item sitting in a bag slot: the label needs the slot number.
        if ($this->inRange($slot, self::BACKPACK_BAG_SLOTS)) {
            $index = $slot - self::BACKPACK_BAG_SLOTS[0] + 1;

            return $this->payload('inventory.bag_slot', 'inventory', $index, null, ['slot' => $index]);
        }

        if ($this->inRange($slot, self::BANK_BAG_SLOTS)) {
            $index = $slot - self::BANK_BAG_SLOTS[0] + 1;

            return $this->payload('bank.bag_slot', 'bank', $index, null, ['slot' => $index]);
        }

        return $this->payload('inventory.unknown', 'inventory', null, null, ['slot' => $slot]);
    }

    /** bag !== 0 branch: the item lives inside a container. */
    private function resolveNested(int $slot, ?array $containerRow): array
    {
        $innerSlot = $slot + 1;
        $container = $this->containerPayload($containerRow);

        if ($containerRow !== null) {
            $bagSlot = (int) ($containerRow['slot'] ?? -1);

            if ($this->inRange($bagSlot, self::BACKPACK_BAG_SLOTS)) {
                return $this->payload(
                    'inventory.bag_inner',
                    'inventory',
                    $innerSlot,
                    $container,
                    ['slot' => $innerSlot, 'bag' => $bagSlot - 19 + 1]
                );
            }

            if ($this->inRange($bagSlot, self::BANK_BAG_SLOTS)) {
                return $this->payload(
                    'bank.bag_inner',
                    'bank',
                    $innerSlot,
                    $container,
                    ['slot' => $innerSlot, 'bag' => $bagSlot - 67 + 1]
                );
            }
        }

        // Unknown container slot (custom/legacy layout): keep the real coordinates visible.
        return $this->payload(
            'inventory.bag_inner',
            'inventory',
            $innerSlot,
            $container,
            ['slot' => $innerSlot, 'bag' => $containerRow !== null ? ((int) ($containerRow['slot'] ?? 0)) : '?']
        );
    }

    private function payload(
        string $code,
        string $area,
        ?int $innerSlot,
        ?array $container,
        array $replace = []
    ): array {
        return [
            'code' => $code,
            'label' => $this->label($code, $replace),
            'area' => $area,
            'inner_slot' => $innerSlot,
            'container' => $container,
        ];
    }

    public function label(string $code, array $replace = []): string
    {
        return Lang::get('app.item_inventory.locations.' . $code, $replace);
    }

    /** True when the character slot holds a container (bag shaped item). */
    public function isBagSlot(int $slot, int $bag = 0): bool
    {
        return $bag === 0 && $this->inRange($slot, self::BACKPACK_BAG_SLOTS);
    }

    /** True when the character slot holds a bank container. */
    public function isBankBagSlot(int $slot, int $bag = 0): bool
    {
        return $bag === 0 && $this->inRange($slot, self::BANK_BAG_SLOTS);
    }

    /**
     * Container slot coordinates that can hold new instances on expansion.
     *
     * @return array{0:int,1:int}
     */
    public function bagSlotRange(int $bag): array
    {
        return $bag === 0 ? self::BACKPACK_BAG_SLOTS : self::BANK_BAG_SLOTS;
    }

    /** @return array{0:int,1:int} */
    public function backpackSlotRange(): array
    {
        return self::SLOT_GROUPS['inventory.backpack'];
    }

    /** @return array{0:int,1:int} */
    public function bankBagSlotRange(): array
    {
        return self::BANK_BAG_SLOTS;
    }

    private function inRange(int $value, array $range): bool
    {
        return $value >= $range[0] && $value <= $range[1];
    }

    private function areaOf(string $code): string
    {
        return str_starts_with($code, 'bank.') ? 'bank' : 'inventory';
    }

    /** @return array<int,string> */
    public static function equipmentCodes(): array
    {
        return self::EQUIPMENT_SLOTS;
    }

    /** @return array<string,array{0:int,1:int}> */
    public static function slotGroups(): array
    {
        return self::SLOT_GROUPS;
    }
}
