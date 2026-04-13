<?php
/**
 * File: app/Core/ErrorHandler.php
 * Purpose: Defines class ErrorHandler for the app/Core module.
 * Classes:
 *   - ErrorHandler
 * Functions:
 *   - register()
 *   - handleException()
 *   - handleError()
 */

namespace Acme\Panel\Core;

use Throwable;

class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class,'handleException']);
        set_error_handler([self::class,'handleError']);
    }

    public static function handleException(Throwable $e): void
    {
        try {
            $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
            $path = (string)(parse_url($uri, PHP_URL_PATH) ?? $uri);
            $line = date('Y-m-d H:i:s')
                . ' [exception] '
                . json_encode([
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'path' => $path,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            \Acme\Panel\Support\LogPath::appendLine('error.log', $line, true, 0775);
        } catch (\Throwable $ignore) {
            // swallow
        }

        http_response_code(500);
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $pathOnly = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $isApiPath = $pathOnly !== '' && str_contains($pathOnly, '/api/');
        $xhr = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $wantsJson = $isApiPath || $xhr || str_contains($accept,'application/json') || (($_GET['__api'] ?? '')==='1');
        if($wantsJson){
            header('Content-Type: application/json; charset=utf-8');
            if(Config::get('app.debug', false)){
                echo json_encode(['success'=>false,'error'=>'exception','message'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
            } else {
                echo json_encode(['success'=>false,'error'=>'server_error']);
            }
            return;
        }
        if(Config::get('app.debug', false)){
            echo '<h1>Exception</h1><pre>'.htmlspecialchars($e->getMessage().'\n'.$e->getTraceAsString()).'</pre>';
        } else {
            echo '<h1>'.htmlspecialchars(Lang::get('app.errors.internal_server_error_title')).'</h1>';
        }
    }

    public static function handleError(int $severity, string $message, string $file='', int $line=0): bool
    {
        self::handleException(new \ErrorException($message,0,$severity,$file,$line));
        return true;
    }
}

