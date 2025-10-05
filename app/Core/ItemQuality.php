<?php
/**
 * File: app/Core/ItemQuality.php
 * Purpose: Defines class ItemQuality for the app/Core module.
 * Classes:
 *   - ItemQuality
 * Functions:
 *   - code()
 *   - english()
 *   - label()
 *   - css()
 *   - allLocalized()
 */

namespace Acme\Panel\Core;

class ItemQuality
{
    protected static array $codes = [0=>'poor',1=>'common',2=>'uncommon',3=>'rare',4=>'epic',5=>'legendary',6=>'artifact',7=>'heirloom'];

    protected static array $english = [
        'poor' => 'Poor',
        'common' => 'Common',
        'uncommon' => 'Uncommon',
        'rare' => 'Rare',
        'epic' => 'Epic',
        'legendary' => 'Legendary',
        'artifact' => 'Artifact',
        'heirloom' => 'Heirloom',
        'unknown' => 'Unknown',
    ];

    public static function code(int $q): string
    {
        return self::$codes[$q] ?? 'unknown';
    }

    public static function english(int|string $q): string
    {
        $code = is_int($q) ? self::code($q) : (string) $q;
        return self::$english[$code] ?? self::$english['unknown'];
    }

    public static function label(int|string $q, bool $withEn = true): string
    {
        $qualityId = is_int($q) ? $q : array_search((string) $q, self::$codes, true);
        if ($qualityId === false || !is_int($qualityId)) {
            $qualityId = (int) $q;
        }
        $name = ItemMeta::qualityName($qualityId);
        if (!$withEn) {
            return $name;
        }
        return $name . ' (' . self::english($q) . ')';
    }

    public static function css(int|string $q): string
    {
        return 'item-quality-' . (is_int($q) ? self::code($q) : $q);
    }

    public static function allLocalized(): array
    {
        return ItemMeta::qualities();
    }
}
