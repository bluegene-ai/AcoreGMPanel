<?php
/**
 * File: app/Core/ItemMeta.php
 * Purpose: Defines class ItemMeta for the app/Core module.
 * Classes:
 *   - ItemMeta
 * Functions:
 *   - qualityName()
 *   - qualities()
 *   - classes()
 *   - className()
 *   - subclassesOf()
 *   - subclassName()
 *   - allSubclassesFlat()
 *   - get_item_class_name()
 *   - get_item_subclass_name()
 */

namespace Acme\Panel\Core;

final class ItemMeta
{
    private const QUALITY_IDS = [0,1,2,3,4,5,6,7];

    private const CLASS_IDS = [
        0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,
    ];

    private const SUBCLASS_IDS = [
        0 => [0,1,2,3,4,5,6,7,8],
        1 => [0,1,2,3,4,5,6,7,8],
        2 => [0,1,2,3,4,5,6,7,8,9,10,13,14,15,16,17,18,19,20],
        3 => [0,1,2,3,4,5,6,7,8],
        4 => [0,1,2,3,4,5,6,7,8,9,10],
        5 => [0],
        6 => [0,1,2,3,4],
        7 => [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15],
        8 => [0],
        9 => [0,1,2,3,4,5,6,7,8,9,10,11],
        10 => [0],
        11 => [0,1,2,3],
        12 => [0],
        13 => [0,1],
        14 => [0],
        15 => [0,1,2,3,4,5],
        16 => [1,2,3,4,5,6,7,8,9,11],
    ];

    public static function qualityName(int $q): string
    {
        return Lang::get('app.item_meta.qualities.' . $q, [], (string) $q);
    }

    public static function qualities(): array
    {
        $result = [];
        foreach (self::QUALITY_IDS as $id) {
            $result[$id] = self::qualityName($id);
        }
        return $result;
    }

    public static function classes(): array
    {
        $result = [];
        foreach (self::CLASS_IDS as $id) {
            $result[$id] = self::className($id);
        }
        return $result;
    }

    public static function className(int $id): string
    {
        return Lang::get('app.item_meta.classes.' . $id, [], (string) $id);
    }

    public static function subclassesOf(int $classId): array
    {
        $ids = self::SUBCLASS_IDS[$classId] ?? [];
        $result = [];
        foreach ($ids as $subId) {
            $result[$subId] = self::subclassName($classId, $subId);
        }
        return $result;
    }

    public static function subclassName(int $classId, int $subId): string
    {
        return Lang::get('app.item_meta.subclasses.' . $classId . '.' . $subId, [], (string) $subId);
    }

    public static function allSubclassesFlat(): array
    {
        $all = [];
        foreach (self::SUBCLASS_IDS as $cid => $subs) {
            foreach ($subs as $sid) {
                $key = $cid . '-' . $sid;
                if (!isset($all[$key])) {
                    $all[$key] = self::subclassName($cid, $sid);
                }
            }
        }
        return $all;
    }
}


if(!function_exists('get_item_class_name')){
    function get_item_class_name(int $c): string { return ItemMeta::className($c); }
}
if(!function_exists('get_item_subclass_name')){
    function get_item_subclass_name(int $c,int $s): string { return ItemMeta::subclassName($c,$s); }
}

