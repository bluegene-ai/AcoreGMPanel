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

use Acme\Panel\Core\{Lang,Request,Response};
use Acme\Panel\Support\Auth;

class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (defined('PANEL_CLI_AUTH_BYPASS') && PANEL_CLI_AUTH_BYPASS) {
            return $next($request);
        }
        if(!Auth::check()){
            if ($request->expectsJsonResponse()) {
                return Response::json([
                    'success' => false,
                    'message' => Lang::get('app.auth.errors.not_logged_in'),
                ], 401);
            }

            return Response::redirect('/account/login');
        }
        return $next($request);
    }
}

