<?php
/**
 * File: app/Support/Audit.php
 * Purpose: Defines class Audit for the app/Support module.
 */

namespace Acme\Panel\Support;

use Acme\Panel\Core\Database; use PDO; use Throwable;

class Audit
{
    /** @var array<int,bool> 已确认 panel_audit 结构就绪的区服 */
    private static array $ensured = [];

    public static function log(string $module,string $action,string $target='', array $detail=[]): void
    {
        $admin = Auth::user();
        if($admin===null) return;
        $serverId = ServerContext::currentId();
        try {
            $pdo = self::storageFor($serverId);
            if(!$pdo) return;
            $stmt = $pdo->prepare('INSERT INTO panel_audit(ts,admin,module,action,target,detail,ip,server_id) VALUES(:ts,:admin,:module,:action,:target,:detail,:ip,:server_id)');
            $stmt->execute([
                ':ts'=>time(), ':admin'=>$admin, ':module'=>$module, ':action'=>$action, ':target'=>$target,
                ':detail'=> $detail? json_encode($detail, JSON_UNESCAPED_UNICODE):null,
                ':ip'=> ClientIp::resolve($_SERVER),
                ':server_id'=>$serverId,
            ]);
        } catch(Throwable $e){  }
    }

    /**
     * 取审计库连接并保证表结构含 server_id。
     * 区服未单独配置 auth 库时退回全局 auth 连接（当前三区共用 acore_auth 即走这条）。
     */
    public static function storageFor(?int $serverId=null): ?PDO
    {
        $serverId = $serverId ?? ServerContext::currentId();
        try {
            $pdo = Database::forServer($serverId,'auth');
        } catch(Throwable $e){
            try { $pdo = Database::auth(); } catch(Throwable $e2){ return null; }
        }
        try {
            self::ensureTable($pdo,$serverId);
        } catch(Throwable $e){
            return null;
        }

        return $pdo;
    }

    private static function ensureTable(PDO $pdo,int $serverId): void
    {
        if(isset(self::$ensured[$serverId])) return;
        self::$ensured[$serverId]=true;
        $pdo->exec("CREATE TABLE IF NOT EXISTS panel_audit(
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          ts INT UNSIGNED NOT NULL,
          admin VARCHAR(64) NOT NULL,
          module VARCHAR(32) NOT NULL,
          action VARCHAR(64) NOT NULL,
          target VARCHAR(128) NOT NULL DEFAULT '',
          detail TEXT NULL,
          ip VARCHAR(45) NOT NULL DEFAULT '',
          server_id INT NOT NULL DEFAULT 0,
          INDEX idx_ts(ts), INDEX idx_mod_action(module,action), INDEX idx_admin(admin), INDEX idx_server(server_id)
        ) ENGINE=InnoDB CHARSET=utf8mb4");

        $chk = $pdo->query("SHOW COLUMNS FROM panel_audit LIKE 'server_id'");
        if(!$chk->fetch()){
            $pdo->exec("ALTER TABLE panel_audit ADD server_id INT NOT NULL DEFAULT 0 AFTER target, ADD KEY idx_server(server_id)");
        }
    }
}
