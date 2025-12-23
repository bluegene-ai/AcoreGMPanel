<?php
/**
 * File: app/Support/GameMaps.php
 * Purpose: Centralized game mapping helpers (race/class/quality/colors/zone).
 *
 * This is a self-contained mapping layer for AGMP.
 * Do NOT depend on external projects/files.
 */

declare(strict_types=1);

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;

final class GameMaps
{
    private const RACES_ZH = [
        1 => '人类',
        2 => '兽人',
        3 => '矮人',
        4 => '暗夜精灵',
        5 => '亡灵',
        6 => '牛头人',
        7 => '侏儒',
        8 => '巨魔',
        10 => '血精灵',
        11 => '德莱尼',
    ];

    private const RACES_EN = [
        1 => 'Human',
        2 => 'Orc',
        3 => 'Dwarf',
        4 => 'Night Elf',
        5 => 'Undead',
        6 => 'Tauren',
        7 => 'Gnome',
        8 => 'Troll',
        10 => 'Blood Elf',
        11 => 'Draenei',
    ];

    private const CLASSES_ZH = [
        1 => '战士',
        2 => '圣骑士',
        3 => '猎人',
        4 => '潜行者',
        5 => '牧师',
        6 => '死亡骑士',
        7 => '萨满祭司',
        8 => '法师',
        9 => '术士',
        11 => '德鲁伊',
    ];

    private const CLASSES_EN = [
        1 => 'Warrior',
        2 => 'Paladin',
        3 => 'Hunter',
        4 => 'Rogue',
        5 => 'Priest',
        6 => 'Death Knight',
        7 => 'Shaman',
        8 => 'Mage',
        9 => 'Warlock',
        11 => 'Druid',
    ];

    private const QUALITY_NAMES_ZH = [
        0 => '粗糙',
        1 => '普通',
        2 => '优秀',
        3 => '精良',
        4 => '史诗',
        5 => '传说',
        6 => '神器',
        7 => '传家宝',
    ];

    private const QUALITY_NAMES_EN = [
        0 => 'Poor',
        1 => 'Common',
        2 => 'Uncommon',
        3 => 'Rare',
        4 => 'Epic',
        5 => 'Legendary',
        6 => 'Artifact',
        7 => 'Heirloom',
    ];

    private const QUALITY_COLORS_HEX = [
        0 => '9D9D9D',
        1 => 'FFFFFF',
        2 => '1EFF00',
        3 => '0070DD',
        4 => 'A335EE',
        5 => 'FF8000',
        6 => 'E6CC80',
        7 => 'E6CC80',
    ];

    private const CLASS_COLORS_HEX = [
        1 => 'C79C6E',
        2 => 'F58CBA',
        3 => 'ABD473',
        4 => 'FFF569',
        5 => 'FFFFFF',
        6 => 'C41F3B',
        7 => '0070DE',
        8 => '40C7EB',
        9 => '8787ED',
        11 => 'FF7D0A',
    ];

    // WotLK (3.3.5) 常用 mapId → 名称（包含世界地图、副本、团队副本、战场、竞技场等）
    private const MAPS_ZH = [
        0 => '东部王国',
        1 => '卡利姆多',
        30 => '奥特兰克山谷',
        33 => '影牙城堡',
        34 => '暴风城监狱',
        36 => '死亡矿井',
        43 => '哀嚎洞穴',
        47 => '剃刀沼泽',
        48 => '黑暗深渊',
        70 => '奥达曼',
        90 => '诺莫瑞根',
        109 => '沉没的神庙',
        129 => '剃刀高地',
        189 => '血色修道院',
        209 => '祖尔法拉克',
        229 => '黑石塔上层',
        230 => '黑石深渊',
        249 => '奥妮克希亚的巢穴',
        269 => '黑暗之门（时光之穴）',
        289 => '通灵学院',
        309 => '祖尔格拉布',
        329 => '斯坦索姆',
        349 => '玛拉顿',
        389 => '怒焰裂谷',
        409 => '熔火之心',
        429 => '厄运之槌',
        469 => '黑翼之巢',
        489 => '战歌峡谷',
        509 => '安其拉废墟',
        529 => '阿拉希盆地',
        530 => '外域',
        531 => '安其拉神殿',
        532 => '卡拉赞',
        533 => '纳克萨玛斯',
        534 => '海加尔山之战',
        540 => '破碎大厅',
        542 => '鲜血熔炉',
        543 => '地狱火城墙',
        544 => '玛瑟里顿的巢穴',
        545 => '蒸汽地窟',
        546 => '幽暗沼泽',
        547 => '奴隶围栏',
        548 => '毒蛇神殿',
        550 => '风暴要塞',
        552 => '禁魔监狱',
        553 => '生态船',
        554 => '能源舰',
        555 => '暗影迷宫',
        556 => '塞泰克大厅',
        557 => '法力陵墓',
        558 => '奥金尼地穴',
        559 => '纳格兰竞技场',
        562 => '刀锋山竞技场',
        564 => '黑暗神殿',
        565 => '格鲁尔的巢穴',
        566 => '风暴之眼',
        568 => '祖阿曼',
        571 => '诺森德',
        572 => '洛丹伦废墟（竞技场）',
        574 => '乌特加德城堡',
        575 => '乌特加德之巅',
        576 => '魔枢',
        578 => '魔环',
        580 => '太阳之井高地',
        595 => '净化斯坦索姆',
        599 => '岩石大厅',
        600 => '达克萨隆要塞',
        601 => '艾卓-尼鲁布',
        602 => '闪电大厅',
        603 => '奥杜尔',
        604 => '古达克',
        608 => '紫罗兰监狱',
        615 => '黑曜石圣殿',
        616 => '永恒之眼',
        617 => '达拉然下水道（竞技场）',
        618 => '勇气竞技场（竞技场）',
        619 => '安卡赫特：古代王国',
        624 => '阿尔卡冯的宝库',
        628 => '伊利丹之路（竞技场）',
        631 => '冰冠堡垒',
        632 => '灵魂洪炉',
        649 => '十字军的试炼',
        650 => '冠军的试炼',
        658 => '萨隆矿坑',
        668 => '映像大厅',
        724 => '红玉圣殿',
    ];

    private const MAPS_EN = [
        0 => 'Eastern Kingdoms',
        1 => 'Kalimdor',
        30 => 'Alterac Valley',
        33 => 'Shadowfang Keep',
        34 => 'The Stockade',
        36 => 'The Deadmines',
        43 => 'Wailing Caverns',
        47 => 'Razorfen Kraul',
        48 => 'Blackfathom Deeps',
        70 => 'Uldaman',
        90 => 'Gnomeregan',
        109 => 'The Temple of Atal\'Hakkar',
        129 => 'Razorfen Downs',
        189 => 'Scarlet Monastery',
        209 => 'Zul\'Farrak',
        229 => 'Upper Blackrock Spire',
        230 => 'Blackrock Depths',
        249 => 'Onyxia\'s Lair',
        269 => 'The Dark Portal (CoT)',
        289 => 'Scholomance',
        309 => 'Zul\'Gurub',
        329 => 'Stratholme',
        349 => 'Maraudon',
        389 => 'Ragefire Chasm',
        409 => 'Molten Core',
        429 => 'Dire Maul',
        469 => 'Blackwing Lair',
        489 => 'Warsong Gulch',
        509 => 'Ruins of Ahn\'Qiraj',
        529 => 'Arathi Basin',
        530 => 'Outland',
        531 => 'Temple of Ahn\'Qiraj',
        532 => 'Karazhan',
        533 => 'Naxxramas',
        534 => 'The Battle for Mount Hyjal',
        540 => 'The Shattered Halls',
        542 => 'The Blood Furnace',
        543 => 'Hellfire Ramparts',
        544 => 'Magtheridon\'s Lair',
        545 => 'The Steamvault',
        546 => 'The Underbog',
        547 => 'The Slave Pens',
        548 => 'Serpentshrine Cavern',
        550 => 'Tempest Keep',
        552 => 'The Arcatraz',
        553 => 'The Botanica',
        554 => 'The Mechanar',
        555 => 'Shadow Labyrinth',
        556 => 'Sethekk Halls',
        557 => 'Mana-Tombs',
        558 => 'Auchenai Crypts',
        559 => 'Nagrand Arena',
        562 => 'Blade\'s Edge Arena',
        564 => 'Black Temple',
        565 => 'Gruul\'s Lair',
        566 => 'Eye of the Storm',
        568 => 'Zul\'Aman',
        571 => 'Northrend',
        572 => 'Ruins of Lordaeron (Arena)',
        574 => 'Utgarde Keep',
        575 => 'Utgarde Pinnacle',
        576 => 'The Nexus',
        578 => 'The Oculus',
        580 => 'Sunwell Plateau',
        595 => 'The Culling of Stratholme',
        599 => 'Halls of Stone',
        600 => 'Drak\'Tharon Keep',
        601 => 'Azjol-Nerub',
        602 => 'Halls of Lightning',
        603 => 'Ulduar',
        604 => 'Gundrak',
        608 => 'The Violet Hold',
        615 => 'The Obsidian Sanctum',
        616 => 'The Eye of Eternity',
        617 => 'Dalaran Sewers (Arena)',
        618 => 'The Ring of Valor (Arena)',
        619 => 'Ahn\'kahet: The Old Kingdom',
        624 => 'Vault of Archavon',
        628 => 'Isle of Conquest (Arena)',
        631 => 'Icecrown Citadel',
        632 => 'The Forge of Souls',
        649 => 'Trial of the Crusader',
        650 => 'Trial of the Champion',
        658 => 'Pit of Saron',
        668 => 'Halls of Reflection',
        724 => 'The Ruby Sanctum',
    ];

    // 这些 mapId 列表来自仓库内置在线地图工具（用于归类展示，避免仍显示纯数字）
    private const OUTLAND_INST = [540, 542, 543, 544, 545, 546, 547, 548, 550, 552, 553, 554, 555, 556, 557, 558, 559, 562, 564, 565];
    private const NORTHREND_INST = [533, 574, 575, 576, 578, 599, 600, 601, 602, 603, 604, 608, 615, 616, 617, 619, 624, 631, 632, 649, 650, 658, 668, 724];

    public static function raceName(int $id): string
    {
        $races = Lang::getArray('app.game_meta.races', []);
        if (isset($races[$id]) && is_string($races[$id]) && $races[$id] !== '') {
            return $races[$id];
        }

        $fallback = self::isZh() ? (self::RACES_ZH[$id] ?? null) : (self::RACES_EN[$id] ?? null);
        return $fallback ?? (self::isZh() ? ('未知(' . $id . ')') : ('Unknown(' . $id . ')'));
    }

    public static function className(int $id): string
    {
        $classes = Lang::getArray('app.game_meta.classes', []);
        if (isset($classes[$id]) && is_string($classes[$id]) && $classes[$id] !== '') {
            return $classes[$id];
        }

        $fallback = self::isZh() ? (self::CLASSES_ZH[$id] ?? null) : (self::CLASSES_EN[$id] ?? null);
        return $fallback ?? (self::isZh() ? ('未知(' . $id . ')') : ('Unknown(' . $id . ')'));
    }

    public static function zoneName(int $zoneId): ?string
    {
        return ZoneNames::name($zoneId);
    }

    public static function zoneLabel(int $zoneId): string
    {
        return ZoneNames::label($zoneId);
    }

    public static function mapName(int $mapId): ?string
    {
        if ($mapId < 0) {
            return null;
        }

        $maps = Lang::getArray('app.game_meta.maps', []);
        if (isset($maps[$mapId]) && is_string($maps[$mapId]) && $maps[$mapId] !== '') {
            return $maps[$mapId];
        }

        if (in_array($mapId, self::OUTLAND_INST, true)) {
            return self::isZh() ? '外域（副本）' : 'Outland (Instance)';
        }
        if (in_array($mapId, self::NORTHREND_INST, true)) {
            return self::isZh() ? '诺森德（副本）' : 'Northrend (Instance)';
        }

        $fallback = self::isZh() ? (self::MAPS_ZH[$mapId] ?? null) : (self::MAPS_EN[$mapId] ?? null);
        return $fallback;
    }

    public static function mapLabel(int $mapId): string
    {
        $name = self::mapName($mapId);
        if ($name !== null && $name !== '') {
            return $name;
        }
        return '#' . $mapId;
    }

    public static function qualityName(int $q): string
    {
        $qualities = Lang::getArray('app.game_meta.qualities', []);
        if (isset($qualities[$q]) && is_string($qualities[$q]) && $qualities[$q] !== '') {
            return $qualities[$q];
        }

        $fallback = self::isZh() ? (self::QUALITY_NAMES_ZH[$q] ?? null) : (self::QUALITY_NAMES_EN[$q] ?? null);
        return $fallback ?? (self::isZh() ? ('未知(' . $q . ')') : ('Unknown(' . $q . ')'));
    }

    public static function qualityColorHex(int $q): string
    {
        return self::QUALITY_COLORS_HEX[$q] ?? 'FFFFFF';
    }

    public static function classColorHex(int $classId): string
    {
        return self::CLASS_COLORS_HEX[$classId] ?? 'FFFFFF';
    }

    private static function isZh(): bool
    {
        return str_starts_with(strtolower(Lang::locale()), 'zh');
    }
}
