<?php
/**
 * File: app/Core/Database.php
 * Purpose: Defines class Database for the app/Core module.
 * Classes:
 *   - Database
 * Functions:
 *   - purge()
 *   - connection()
 *   - auth()
 *   - world()
 *   - characters()
 *   - forServer()
 */

namespace Acme\Panel\Core;

use PDO; use PDOException; use RuntimeException; use Acme\Panel\Support\ServerContext;

class Database
{
    private static array $pool = [];

    public static function purge(?int $serverId=null): void
    {
        if($serverId===null){ self::$pool = []; return; }

        foreach(array_keys(self::$pool) as $k){ if(str_starts_with($k,'srv'.$serverId.'_')) unset(self::$pool[$k]); }
    }







    public static function connection(string $name): PDO
    {
        if(isset(self::$pool[$name])) return self::$pool[$name];
        $cfg = Config::get('database.connections.'.$name);
        if(!$cfg){
            throw new RuntimeException(Lang::get('app.database.errors.config_missing', [
                'name' => $name,
            ]));
        }
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$cfg['host'],$cfg['port'],$cfg['database'],$cfg['charset']??'utf8mb4');
        try {
            $pdo = new PDO($dsn,$cfg['username'],$cfg['password'],[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
        } catch(PDOException $e){
            throw new RuntimeException(Lang::get('app.database.errors.connection_failed', [
                'database' => $cfg['database'],
                'host' => $cfg['host'],
                'port' => $cfg['port'],
                'error' => $e->getMessage(),
            ]));
        }
        return self::$pool[$name]=$pdo;
    }

    public static function auth(): PDO { return self::connection('auth'); }
    public static function world(): PDO { return self::connection('world'); }
    public static function characters(): PDO { return self::connection('characters'); }


    public static function forServer(int $serverId,string $role): PDO
    {
        $key='srv'.$serverId.'_'.$role; if(isset(self::$pool[$key])) return self::$pool[$key];
        $srv = ServerContext::server($serverId);
        if(!$srv || !isset($srv[$role])){
            throw new RuntimeException(Lang::get('app.database.errors.server_config_missing', [
                'server' => $serverId,
                'role' => $role,
            ]));
        }
        $cfg=$srv[$role];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',$cfg['host'],$cfg['port'],$cfg['database'],$cfg['charset']??'utf8mb4');
        $pdo = new PDO($dsn,$cfg['username'],$cfg['password'],[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        return self::$pool[$key]=$pdo;
    }
}

