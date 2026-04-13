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
        $value = TransientCache::remember('server_stats', 'online_' . $sid, 15, static function () use ($sid): ?int {
            try {
                $pdo = Database::forServer($sid,'characters');
                $st = $pdo->query('SELECT COUNT(*) FROM characters WHERE online=1');
                return (int)$st->fetchColumn();
            } catch(\Throwable $e){
                return null;
            }
        });

        return self::$cache[$sid] = ($value === null ? null : (int)$value);
    }

    public static function totalCharacters(?int $serverId=null): ?int
    {
        $sid = $serverId ?? ServerContext::currentId();
        if(array_key_exists($sid,self::$totalCache)) return self::$totalCache[$sid];
        $value = TransientCache::remember('server_stats', 'total_' . $sid, 60, static function () use ($sid): ?int {
            try {
                $pdo = Database::forServer($sid,'characters');
                $st = $pdo->query('SELECT COUNT(*) FROM characters');
                return (int)$st->fetchColumn();
            } catch(\Throwable $e){
                return null;
            }
        });

        return self::$totalCache[$sid] = ($value === null ? null : (int)$value);
    }
}

