<?php
/**
 * File: app/Support/GameMeta.php
 * Purpose: Defines class GameMeta for the app/Support module.
 * Classes:
 *   - GameMeta
 * Functions:
 *   - className()
 *   - classColorHex()
 *   - itemQualityColorHex()
 *   - qualityName()
 *   - classColorStyle()
 *   - qualityColorStyle()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;













class GameMeta
{

    private const CLASS_COLORS = [
        1=>'C69B6D',
        2=>'F48CBA',
        3=>'AAD372',
        4=>'FFF468',
        5=>'FFFFFF',
        6=>'C41E3A',
        7=>'0070DD',
        8=>'3FC7EB',
        9=>'8788EE',
        10=>'00FF96',
        11=>'FF7C0A',
        12=>'A330C9',
    ];


    private const QUALITY_COLORS=[
        0=>'9D9D9D',
        1=>'FFFFFF',
        2=>'1EFF00',
        3=>'0070DD',
        4=>'A335EE',
        5=>'FF8000',
        6=>'E6CC80',
        7=>'00CCFF',
    ];

    public static function className(int $id): string
    {
        $fallback = Lang::get('app.game_meta.fallbacks.class', ['id'=>$id], 'Class #:id');
        return Lang::get('app.game_meta.classes.'.$id, ['id'=>$id], $fallback);
    }
    public static function classColorHex(int $id): string { return self::CLASS_COLORS[$id] ?? 'FFFFFF'; }
    public static function itemQualityColorHex(int $q): string { return self::QUALITY_COLORS[$q] ?? 'FFFFFF'; }
    public static function qualityName(int $q): string
    {
        $fallback = Lang::get('app.game_meta.fallbacks.quality', ['id'=>$q], 'Quality #:id');
        return Lang::get('app.game_meta.qualities.'.$q, ['id'=>$q], $fallback);
    }

    public static function classColorStyle(int $id): string { return 'style="color:#'.self::classColorHex($id).'"'; }
    public static function qualityColorStyle(int $q): string { return 'style="color:#'.self::itemQualityColorHex($q).'"'; }
}

