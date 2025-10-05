<?php
/**
 * File: app/Support/Csrf.php
 * Purpose: Defines class Csrf for the app/Support module.
 * Classes:
 *   - Csrf
 * Functions:
 *   - token()
 *   - verify()
 *   - field()
 */

namespace Acme\Panel\Support;

class Csrf
{
    public static function token(): string
    {
        if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }
    public static function verify(?string $token): bool
    { return $token && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'],$token); }
    public static function field(): string
    { return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(self::token(),ENT_QUOTES).'">'; }
}

