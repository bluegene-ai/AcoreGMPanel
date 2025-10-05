<?php
/**
 * File: app/Support/Auth.php
 * Purpose: Defines class Auth for the app/Support module.
 * Classes:
 *   - Auth
 * Functions:
 *   - check()
 *   - attempt()
 *   - logout()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;

class Auth
{
    public static function check(): bool {
        return !empty($_SESSION['panel_logged_in']);
    }

    public static function attempt(string $u, string $p): bool
    {
        $cfg = Config::get('auth.admin');
        if(!$cfg) return false;
        if($u === ($cfg['username']??'') && password_verify($p, $cfg['password_hash']??'')){
            $_SESSION['panel_logged_in']=true; $_SESSION['panel_user']=$u; return true;
        }
        return false;
    }

    public static function logout(): void
    { $_SESSION=[]; session_destroy(); }
}

