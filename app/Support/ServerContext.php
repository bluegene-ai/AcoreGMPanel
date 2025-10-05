<?php
/**
 * File: app/Support/ServerContext.php
 * Purpose: Defines class ServerContext for the app/Support module.
 * Classes:
 *   - ServerContext
 * Functions:
 *   - cfg()
 *   - defaultId()
 *   - currentId()
 *   - set()
 *   - debugEnabled()
 *   - logSwitch()
 *   - server()
 *   - soap()
 *   - db()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Config;

class ServerContext
{
    private static array $configCache=[];
    private static ?bool $debug=null;


    private static function cfg(): array
    {
        if(!self::$configCache){
            $config = Config::get('servers', null);

            if(!is_array($config) || !$config){
                $baseDir = dirname(__DIR__,2).DIRECTORY_SEPARATOR.'config';
                $file = $baseDir.DIRECTORY_SEPARATOR.'servers.php';

                if(is_file($file)){
                    $config = require $file;
                } else {
                    $generated = $baseDir.DIRECTORY_SEPARATOR.'generated'.DIRECTORY_SEPARATOR.'servers.php';
                    if(is_file($generated)){
                        $config = require $generated;
                    } else {
                        $config = [];
                    }
                }
            }

            if(!is_array($config)){
                $config = [];
            }

            $servers = $config['servers'] ?? [];
            if(!is_array($servers)){
                $servers = [];
            }

            $config['servers'] = $servers;
            $config['default'] = isset($config['default']) ? (int)$config['default'] : 0;

            if(!array_key_exists('base_path',$config)){
                $config['base_path'] = Config::get('app.base_path','');
            }

            self::$configCache = $config;
        }

        return self::$configCache;
    }

    public static function list(): array { return self::cfg()['servers'] ?? []; }
    public static function defaultId(): int { return (int)(self::cfg()['default'] ?? 0); }
    public static function currentId(): int {
        if(isset($_SESSION['server_id'])) return (int)$_SESSION['server_id'];

        if(!empty($_COOKIE['acp_server'])){
            $cid=(int)$_COOKIE['acp_server'];
            $all=self::list(); if(isset($all[$cid])){ $_SESSION['server_id']=$cid; return $cid; }
        }
        return self::defaultId();
    }
    public static function set(int $id): bool {
        $all=self::list(); if(!isset($all[$id])) return false; $_SESSION['server_id']=$id;



        $base = self::cfg()['base_path'] ?? ($_SESSION['__auto_base_path'] ?? '');
        $cookiePath = $base? $base : '/';
        $ck = @setcookie('acp_server',(string)$id,time()+31536000,$cookiePath,'',false,true);
        self::logSwitch($id,$ck,$cookiePath);



        \Acme\Panel\Core\Database::purge();
        return true; }

    private static function debugEnabled(): bool
    {
        if(self::$debug===null){ self::$debug = (bool)(self::cfg()['debug_server_switch'] ?? false); }
        return self::$debug;
    }

    private static function logSwitch(int $id,bool $cookieOk,string $cookiePath): void
    {
        if(!self::debugEnabled()) return;

        if(empty($_SERVER['DOCUMENT_ROOT'])) return;
        $logDir = dirname(__DIR__,2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
        if(!is_dir($logDir)) @mkdir($logDir,0777,true);
        $line = sprintf('[%s] switch_server id=%d cookie=%s path=%s sess=%s\n',date('Y-m-d H:i:s'),$id,$cookieOk?'ok':'fail',$cookiePath,session_id());
        @file_put_contents($logDir.DIRECTORY_SEPARATOR.'server_switch_debug.log',$line,FILE_APPEND);
    }
    public static function server(?int $id=null): ?array { $id=$id??self::currentId(); $all=self::list(); return $all[$id]??null; }
    public static function soap(): ?array { $s=self::server(); return $s['soap']??null; }
    public static function db(string $role): ?array { $s=self::server(); return $s[$role]??null; }
}

