<?php
/**
 * File: app/Http/Middleware/AuthMiddleware.php
 * Purpose: Defines class AuthMiddleware for the app/Http/Middleware module.
 * Classes:
 *   - AuthMiddleware
 * Functions:
 *   - handle()
 */

namespace Acme\Panel\Http\Middleware;

use Acme\Panel\Core\{Request,Response,Url};
use Acme\Panel\Support\Auth;

class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (defined('PANEL_CLI_AUTH_BYPASS') && PANEL_CLI_AUTH_BYPASS) {
            return $next($request);
        }
        if(!Auth::check()){
            $login = Url::to('/account/login');
            return new Response('<script>location.href="'.htmlspecialchars($login,ENT_QUOTES,'UTF-8').'";</script>',302,['Location'=>$login]);
        }
        return $next($request);
    }
}

