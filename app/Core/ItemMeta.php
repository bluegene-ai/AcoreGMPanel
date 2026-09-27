<?php
/**
 * File: app/Core/ItemMeta.php
 * Purpose: Defines class ItemMeta for the app/Core module.
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
        // 品质名在 resources/lang/<locale>/item.php 的 meta.qualities 下，也就是 app.item.meta.qualities.*。
        // 这里原先查的是 app.item_meta.qualities.*，而语言路由（ModuleAssets::SPLIT_LANG_SECTIONS）
        // 里没有 item_meta 这一段，于是永远取不到值、静默退化成数字：品质筛选下拉、物品编辑的品质
        // 下拉、APP_ENUMS.qualities（JS 侧）以及共用的 item_name_link() 悬停提示全都吃这个值。
        return Lang::get('app.item.meta.qualities.' . $q, [], '#' . $q);
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
        // 与 qualityName() 同一个毛病：类别名在 resources/lang/<locale>/item.php 的 meta.classes 下
        // （app.item.meta.classes.*），原先查的 app.item_meta.* 没有对应的语言路由段，取不到就退化成
        // 数字——物品列表的类型列、物品编辑的类别下拉、get_item_class_name() 与 APP_ENUMS.classes
        // 因此一直显示 "4" 而不是"护甲"。
        return Lang::get('app.item.meta.classes.' . $id, [], '#' . $id);
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
        // 同上：子类名在 app.item.meta.subclasses.<class>.<subclass>。
        return Lang::get('app.item.meta.subclasses.' . $classId . '.' . $subId, [], '#' . $subId);
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

