<?php
/**
 * File: app/Support/SrpService.php
 * Purpose: Defines class SrpService for the app/Support module.
 * Classes:
 *   - SrpService
 * Functions:
 *   - generate()
 *   - generateBinary32()
 *   - bigHex()
 *   - bigFromBin()
 *   - bigPowMod()
 *   - bigToHex()
 *   - bigToLittleEndian32()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;










class SrpService
{






    private const N_HEX_256 = '894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7';
    private const G = 7;

    public static function generate(string $username,string $plain): array
    {
        if(!function_exists('gmp_init')){
            throw new \RuntimeException(Lang::get('app.support.srp.errors.gmp_missing'));
        }


        $userUpper = strtoupper($username);
        $passUpper = strtoupper($plain);
        $salt = random_bytes(32);

        $inner = sha1($userUpper.':'.$passUpper,true);
        $xH = sha1($salt.$inner,true);
        $x = self::bigFromBin($xH);
        $N = self::bigHex(self::N_HEX_256);
        $v = self::bigPowMod(self::G,$x,$N);

        $verLE = self::bigToLittleEndian32($v);
        return [
            'salt_hex' => bin2hex($salt),

            'verifier_hex' => strtoupper(bin2hex($verLE)),
        ];
    }











    public static function generateBinary32(string $username,string $plain): array
    {
        if(!function_exists('gmp_init')){
            throw new \RuntimeException(Lang::get('app.support.srp.errors.gmp_missing_binary'));
        }
        $userUpper = strtoupper($username);
        $passUpper = strtoupper($plain);
        $salt = random_bytes(32);
        $inner = sha1($userUpper.':'.$passUpper,true);
        $xH = sha1($salt.$inner,true);
        $x = self::bigFromBin($xH);
        $N = self::bigHex(self::N_HEX_256);
        $v = self::bigPowMod(self::G,$x,$N);
        $verLE = self::bigToLittleEndian32($v);
        return [ 'salt_bin'=>$salt, 'verifier_bin'=>$verLE ];
    }


    private static function bigHex(string $hex){ return gmp_init($hex,16); }
    private static function bigFromBin(string $bin){ return gmp_import($bin,1,GMP_MSW_FIRST|GMP_BIG_ENDIAN); }
    private static function bigPowMod(int $g,$exp,$mod){ return gmp_powm(gmp_init($g,10), $exp, $mod); }
    private static function bigToHex($n){ return gmp_strval($n,16); }
    private static function bigToLittleEndian32($n): string
    {

        $hex = self::bigToHex($n);
        if(strlen($hex)%2===1) $hex='0'.$hex;
        $bin = hex2bin($hex);

        if(strlen($bin) < 32) $bin = str_repeat("\x00", 32 - strlen($bin)).$bin;
        elseif(strlen($bin) > 32) $bin = substr($bin, -32);


        return strrev($bin);
    }
}

