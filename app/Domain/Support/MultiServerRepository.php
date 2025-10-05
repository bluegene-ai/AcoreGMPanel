<?php
/**
 * File: app/Domain/Support/MultiServerRepository.php
 * Purpose: Defines class MultiServerRepository for the app/Domain/Support module.
 * Classes:
 *   - MultiServerRepository
 * Functions:
 *   - __construct()
 *   - rebind()
 *   - world()
 *   - characters()
 *   - auth()
 */

namespace Acme\Panel\Domain\Support;

use Acme\Panel\Support\ServerContext;
use Acme\Panel\Core\Database;
use Acme\Panel\Core\Lang;
use PDO;
use RuntimeException;











abstract class MultiServerRepository
{
    protected int $serverId;
    private ?PDO $world = null; private ?PDO $characters = null; private ?PDO $auth = null;

    public function __construct(?int $serverId=null)
    { $this->serverId = $serverId ?? ServerContext::currentId(); }



    public function rebind(int $serverId): void
    { if($serverId === $this->serverId) return; $this->serverId=$serverId; $this->world=$this->characters=$this->auth=null; }

    protected function world(): PDO
    { if(!$this->world){ $this->world = Database::forServer($this->serverId,'world'); $this->world->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); } return $this->world; }
    protected function characters(): PDO
    { if(!$this->characters){ $this->characters = Database::forServer($this->serverId,'characters'); $this->characters->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); } return $this->characters; }
    protected function auth(): PDO
    {
        if(!$this->auth){
            $cfg = ServerContext::server($this->serverId)['auth'] ?? null;
            if(!$cfg){
                throw new RuntimeException(Lang::get('app.support.multi_server.errors.auth_config_missing', [
                    'server' => $this->serverId,
                ]));
            }
            $this->auth = Database::forServer($this->serverId,'auth');
            $this->auth->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        }
        return $this->auth;
    }
}

