<?php
/**
 * File: app/Support/Audit.php
 * Purpose: Defines class Audit for the app/Support module.
 * Classes:
 *   - Audit
 * Functions:
 *   - log()
 *   - ensureTable()
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Database; use PDO; use Throwable;

class Audit
{
    public static function log(string $module,string $action,string $target='', array $detail=[]): void
    {
        if(!isset($_SESSION['panel_user'])) return;
        try {
            $pdo = Database::auth();
            self::ensureTable($pdo);
            $stmt = $pdo->prepare('INSERT INTO panel_audit(ts,admin,module,action,target,detail,ip) VALUES(:ts,:admin,:module,:action,:target,:detail,:ip)');
            $stmt->execute([
                ':ts'=>time(), ':admin'=>$_SESSION['panel_user'], ':module'=>$module, ':action'=>$action, ':target'=>$target,
                ':detail'=> $detail? json_encode($detail, JSON_UNESCAPED_UNICODE):null,
                ':ip'=> $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        } catch(Throwable $e){  }
    }

    private static function ensureTable(PDO $pdo): void
    {
        static $done=false; if($done) return; $done=true;
        $pdo->exec("CREATE TABLE IF NOT EXISTS panel_audit(
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          ts INT UNSIGNED NOT NULL,
          admin VARCHAR(64) NOT NULL,
          module VARCHAR(32) NOT NULL,
          action VARCHAR(64) NOT NULL,
          target VARCHAR(128) NOT NULL DEFAULT '',
          detail TEXT NULL,
          ip VARCHAR(45) NOT NULL DEFAULT '',
          INDEX idx_ts(ts), INDEX idx_mod_action(module,action), INDEX idx_admin(admin)
        ) ENGINE=InnoDB CHARSET=utf8mb4");
    }
}

