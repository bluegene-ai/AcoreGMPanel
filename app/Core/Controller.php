<?php
/**
 * File: app/Core/Controller.php
 * Purpose: Defines class Controller for the app/Core module.
 * Classes:
 *   - Controller
 * Functions:
 *   - view()
 *   - json()
 *   - getPost()
 *   - getQuery()
 *   - requireLogin()
 *   - redirect()
 *   - response()
 */

namespace Acme\Panel\Core;

use Acme\Panel\Support\Auth;

abstract class Controller
{
    protected function view(string $view, array $data=[]): Response
    { return Response::view($view,$data); }

    protected function json(array $payload, int $status=200): Response
    { return Response::json($payload,$status); }

    protected function getPost(string $key,$default=null){ return $_POST[$key]??$default; }
    protected function getQuery(string $key,$default=null){ return $_GET[$key]??$default; }
    protected function requireLogin(): void
    {
        if(!Auth::check()){
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if(str_contains($accept,'application/json')){
                Response::json([
                    'success' => false,
                    'message' => Lang::get('app.auth.errors.not_logged_in'),
                ],401)->send();
                exit;
            }
            Response::redirect('/account/login')->send();
            exit;
        }
    }

    protected function redirect(string $location,int $status=302): Response
    { return Response::redirect($location,$status); }

    protected function response(int $status=200,string $content='',array $headers=[]): Response
    { return new Response($content,$status,$headers); }
}

