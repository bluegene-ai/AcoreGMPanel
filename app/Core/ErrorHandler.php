<?php
/**
 * File: app/Core/ErrorHandler.php
 * Purpose: Defines class ErrorHandler for the app/Core module.
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

    /**
     * 警告级错误只落日志，不中断请求。
     *
     * 两个必须遵守的点：
     *  - 先看 error_reporting() 掩码：@ 抑制与配置掩码都靠它生效。否则 TransientCache 里
     *    那些 @unlink / @file_put_contents 的容错写法会被升级成 500（历史日志已出现过）；
     *  - E_WARNING/E_NOTICE/E_DEPRECATED 不抛异常：把告警当致命错会把可恢复的场景整页打挂。
     */
    public static function handleError(int $severity, string $message, string $file='', int $line=0): bool
    {
        if (!(error_reporting() & $severity)) {
            return true;
        }

        if (in_array($severity, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)) {
            self::logWarning($message, $file, $line);

            return true;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    private static function logWarning(string $message, string $file, int $line): void
    {
        try {
            $line_ = date('Y-m-d H:i:s') . ' [warning] ' . json_encode([
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'path' => (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            \Acme\Panel\Support\LogPath::appendLine('error.log', $line_, true, 0775);
        } catch (\Throwable $ignore) {
        }
    }
}

