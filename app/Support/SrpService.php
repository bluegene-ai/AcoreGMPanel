<?php
/**
 * File: app/Support/SrpService.php
 * Purpose: Defines class SrpService for the app/Support module.
 * Classes:
 *   - SrpService
 * Functions:
 *   - generate()
 *   - generateBinary32()
 *   - generatePair()
 *   - calculateVerifier()
 *   - importLittleEndian()
 *   - exportLittleEndian()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Lang;

class SrpService
{
    private const N_HEX_256 = '894B645E89E1535BBDAD5B8B290650530801B18EBFBF5E8FAB3C82872A3E9BB7';
    private const G = 7;

    public static function generate(string $username, string $plain): array
    {
        if (!function_exists('gmp_init')) {
            throw new \RuntimeException(Lang::get('app.support.srp.errors.gmp_missing'));
        }


        [$salt, $verifier] = self::generatePair($username, $plain);

        return [
            'salt_hex' => strtoupper(bin2hex($salt)),
            'verifier_hex' => strtoupper(bin2hex($verifier)),
        ];
    }

    public static function generateBinary32(string $username, string $plain): array
    {
        if (!function_exists('gmp_init')) {
            throw new \RuntimeException(Lang::get('app.support.srp.errors.gmp_missing_binary'));
        }

        [$salt, $verifier] = self::generatePair($username, $plain);

        return [
            'salt_bin' => $salt,
            'verifier_bin' => $verifier,
        ];
    }


    private static function generatePair(string $username, string $plain): array
    {
        $salt = random_bytes(32);
        $verifier = self::calculateVerifier($username, $plain, $salt);
        return [$salt, $verifier];
    }

    private static function calculateVerifier(string $username, string $plain, string $salt): string
    {
        $userUpper = strtoupper($username);
        $passUpper = strtoupper($plain);

        $h1 = sha1($userUpper . ':' . $passUpper, true);
        $h2 = sha1($salt . $h1, true);

        $exp = self::importLittleEndian($h2);
        $N = gmp_init(self::N_HEX_256, 16);
        $g = gmp_init((string) self::G, 10);

        $v = gmp_powm($g, $exp, $N);

        return self::exportLittleEndian($v, 32);
    }

    private static function importLittleEndian(string $bytes): \GMP
    {
        return gmp_import($bytes, 1, GMP_LSW_FIRST);
    }

    private static function exportLittleEndian(\GMP $value, int $length): string
    {
        $bin = gmp_export($value, 1, GMP_LSW_FIRST);
        if ($bin === false) {
            $bin = '';
        }
        $len = strlen($bin);
        if ($len < $length) {
            return str_pad($bin, $length, "\0", STR_PAD_RIGHT);
        }
        if ($len > $length) {
            return substr($bin, 0, $length);
        }
        return $bin;
    }
}

