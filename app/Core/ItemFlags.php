<?php
/**
 * File: app/Core/ItemFlags.php
 * Purpose: Defines class ItemFlags for the app/Core module.
 * Classes:
 *   - ItemFlags
 * Functions:
 *   - regular()
 *   - extra()
 *   - custom()
 *   - namesForMask()
 *   - labelString()
 *   - translate()
 */

namespace Acme\Panel\Core;

class ItemFlags
{
    private const REGULAR_KEYS = [
        1 => 'regular.not_lootable',
        2 => 'regular.conjured',
        4 => 'regular.openable',
        32 => 'regular.indestructible',
        128 => 'regular.no_equip_cooldown',
        512 => 'regular.wrapper_container',
        2048 => 'regular.party_loot_shared',
        4096 => 'regular.refundable',
        524288 => 'regular.unique_equipped',
        2097152 => 'regular.arena_usable',
        4194304 => 'regular.throwable',
        8388608 => 'regular.shapeshift_usable',
        33554432 => 'regular.profession_recipe',
        134217728 => 'regular.account_bound',
        268435456 => 'regular.ignore_reagent',
        536870912 => 'regular.millable',
    ];

    private const EXTRA_KEYS = [
        1 => 'extra.horde_only',
        2 => 'extra.alliance_only',
        4 => 'extra.extended_cost_requires_gold',
        256 => 'extra.disable_need_roll',
        512 => 'extra.disable_need_roll_alt',
        16384 => 'extra.standard_pricing',
        131072 => 'extra.battle_net_bound',
    ];

    private const CUSTOM_KEYS = [
        1 => 'custom.real_time_duration',
        2 => 'custom.ignore_quest_status',
        4 => 'custom.party_loot_rules',
    ];

    public static function regular(): array
    {
        return self::translate(self::REGULAR_KEYS);
    }

    public static function extra(): array
    {
        return self::translate(self::EXTRA_KEYS);
    }

    public static function custom(): array
    {
        return self::translate(self::CUSTOM_KEYS);
    }


    public static function namesForMask(int $mask, array $dict): array
    {
        if ($mask <= 0) {
            return [];
        }
        $out = [];
        foreach ($dict as $bit => $name) {
            if (($mask & (int) $bit) === (int) $bit) {
                $out[] = $name;
            }
        }
        return $out;
    }


    public static function labelString(int $mask, array $dict, ?string $sep = null): string
    {
        $names = self::namesForMask($mask, $dict);
        if (!$names) {
            return Lang::get('app.item.flags.empty', [], '(none)');
        }
        $separator = $sep ?? Lang::get('app.item.flags.separator', [], ', ');
        return implode($separator, $names);
    }


    private static function translate(array $map): array
    {
        $translated = [];
        foreach ($map as $bit => $key) {
            $translated[$bit] = Lang::get('app.item.flags.' . $key);
        }
        return $translated;
    }
}
