<?php
/**
 * File: app/Support/Bitmask.php
 * Purpose: Defines class Bitmask for the app/Support module.
 * Classes:
 *   - Bitmask
 * Functions:
 *   - has()
 *   - set()
 *   - diff()
 */

namespace Acme\Panel\Support;

class Bitmask
{
    public static function has(int $mask,int $bit): bool { return ($mask & (1<<$bit))!==0; }
    public static function set(int $mask,int $bit,bool $value): int {
        $flag = 1<<$bit; return $value? ($mask|$flag): ($mask & ~$flag);
    }
    public static function diff(int $old,int $new): array {
        $added=[]; $removed=[]; for($b=0;$b<32;$b++){ $o=self::has($old,$b); $n=self::has($new,$b); if($o===$n) continue; if($n) $added[]=$b; else $removed[]=$b; }
        return ['added'=>$added,'removed'=>$removed];
    }
}

