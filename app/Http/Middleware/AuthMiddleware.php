<?php
/**
 * File: app/Http/Middleware/AuthMiddleware.php
 * Purpose: Defines class AuthMiddleware for the app/Http/Middleware module.
 */

namespace Acme\Panel\Http\Middleware;

use Acme\Panel\Core\{Lang,Request,Response};
use Acme\Panel\Support\Auth;

class AuthMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        // 命令行校验脚本需要绕过登录（tools/verify_*.php 注入 $_SESSION 后直接调控制器）。
        // 旁路必须绑定 CLI：只判常量的话，任何一次 define() 都会让整站鉴权失效。
        if (PHP_SAPI === 'cli' && defined('PANEL_CLI_AUTH_BYPASS') && PANEL_CLI_AUTH_BYPASS) {
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

