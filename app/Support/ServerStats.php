<?php
/**
 * File: app/Support/ServerStats.php
 * Purpose: Defines class ServerStats for the app/Support module.
 * Classes:
 *   - ServerStats
 * Functions:
 *   - onlineCount()
 *   - totalCharacters()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Database; use PDO; use RuntimeException;









class ServerStats
{
    private static array $cache = [];
    private static array $totalCache = [];

    public static function onlineCount(?int $serverId=null): ?int
    {
        $sid = $serverId ?? ServerContext::currentId();
        if(array_key_exists($sid,self::$cache)) return self::$cache[$sid];
        try {

            $pdo = Database::forServer($sid,'characters');
            $st = $pdo->query('SELECT COUNT(*) FROM characters WHERE online=1');
            $val = (int)$st->fetchColumn();
            return self::$cache[$sid] = $val;
        } catch(\Throwable $e){
            return self::$cache[$sid] = null;

        }
    }

    public static function totalCharacters(?int $serverId=null): ?int
    {
        $sid = $serverId ?? ServerContext::currentId();
        if(array_key_exists($sid,self::$totalCache)) return self::$totalCache[$sid];
        try {
            $pdo = Database::forServer($sid,'characters');
            $st = $pdo->query('SELECT COUNT(*) FROM characters');
            $val = (int)$st->fetchColumn();
            return self::$totalCache[$sid] = $val;
        } catch(\Throwable $e){
            return self::$totalCache[$sid] = null;
        }
    }
}

