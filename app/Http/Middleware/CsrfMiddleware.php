<?php
/**
 * File: app/Http/Middleware/CsrfMiddleware.php
 * Purpose: Defines class CsrfMiddleware for the app/Http/Middleware module.
 * Classes:
 *   - CsrfMiddleware
 * Functions:
 *   - __construct()
 *   - handle()
 */

namespace Acme\Panel\Http\Middleware;

use Acme\Panel\Core\{Request,Response};
use Acme\Panel\Support\Csrf;

class CsrfMiddleware
{
    private array $methods;
    public function __construct(array $methods=['POST','PUT','PATCH','DELETE']){ $this->methods=$methods; }

    public function handle(Request $request, callable $next): Response
    {
        if(in_array($request->method,$this->methods,true)){


            $token = $request->post['_csrf'] ?? $request->post['_token'] ?? $request->get['_csrf'] ?? $request->get['_token'] ?? null;


            if(!$token){
                $hdrs = $request->headers ?? [];
                foreach(['X-CSRF-TOKEN','X-XSRF-TOKEN'] as $h){
                    if(isset($hdrs[$h])){ $token=$hdrs[$h]; break; }
                }
            }
            if(!Csrf::verify($token)){
                return Response::json(['success'=>false,'message'=>'CSRF token invalid'],419);
            }
        }
        return $next($request);
    }
}

