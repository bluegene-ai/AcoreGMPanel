<?php
/**
 * File: app/Domain/Account/AccountRepository.php
 * Purpose: Defines class AccountRepository for the app/Domain/Account module.
 * Classes:
 *   - AccountRepository
 * Functions:
 *   - __construct()
 *   - rebind()
 *   - search()
 *   - findByUsername()
 *   - listCharacters()
 *   - setGmLevel()
 *   - ban()
 *   - unban()
 *   - changePassword()
 *   - createAccount()
 *   - banStatus()
 *   - accountsByLastIp()
 *   - fetchAccountColumns()
 *   - inspectAccountColumns()
 *   - inspectAccountSchema()
 *   - hasColumn()
 *   - columnName()
 *   - getColumnInfo()
 *   - columnRequiresValue()
 *   - columnLength()
 *   - columnIsBinary()
 */

namespace Acme\Panel\Domain\Account;

use PDO;
use Acme\Panel\Support\SrpService;
use Acme\Panel\Core\Lang;
use Acme\Panel\Support\Paginator;
use Acme\Panel\Domain\Support\MultiServerRepository;

class AccountRepository extends MultiServerRepository
{
    private PDO $authPdo;
    private ?string $srpColV = null; private ?string $srpColS = null;
    private bool $srpBinary32 = false;
    private bool $schemaChecked = false;
    private bool $hasSrpVerifier = false;
    private bool $hasSrpSalt = false;
    private bool $hasShaHash = false;
    private bool $hasSessionKeyColumn = false;
    private ?string $sessionKeyColumn = null;
    private array $accountColumnsMeta = [];
    private array $accountColumnsLower = [];
    private array $accountColumnTypes = [];
    public function __construct(?int $serverId = null){
        parent::__construct($serverId);
        $this->authPdo = $this->auth();
    }

    public function rebind(int $serverId): void
    {
        parent::rebind($serverId);
        $this->authPdo = $this->auth();

        $this->srpColV = null;
        $this->srpColS = null;
        $this->srpBinary32 = false;
        $this->schemaChecked = false;
        $this->hasSrpVerifier = false;
        $this->hasSrpSalt = false;
        $this->hasShaHash = false;
        $this->hasSessionKeyColumn = false;
        $this->sessionKeyColumn = null;
        $this->accountColumnsMeta = [];
        $this->accountColumnsLower = [];
        $this->accountColumnTypes = [];
    }

    public function search(string $type,string $value,int $page,int $perPage,array $filters = [], bool $loadAll = false, string $sort = ''): Paginator
    {
        $value = trim($value);
        $filters = $filters ?: [];

        $sort = (string)$sort;

        $onlineFilter = $filters['online'] ?? 'any';
        $banFilter = $filters['ban'] ?? 'any';

        $hasOnlineFilter = in_array($onlineFilter, ['online','offline'], true);
        $hasBanFilter = in_array($banFilter, ['banned','unbanned'], true);

        $hasCriteria = $loadAll || ($value !== '') || $hasOnlineFilter || $hasBanFilter;
        if(!$hasCriteria){ return new Paginator([],0,$page,$perPage); }

        $wheres = [];
        $param = [];

        if($value !== ''){
            if($type === 'id'){
                $wheres[] = 'a.id = :v';
                $param[':v'] = (int)$value;
            } else {
                $wheres[] = 'a.username LIKE :v';
                $param[':v'] = '%'.$value.'%';
            }
        }

        $onlineColumn = $this->hasColumn('online') ? $this->columnName('online') : null;
        if($hasOnlineFilter){
            if($onlineColumn){
                $col = '`'.str_replace('`','``',$onlineColumn).'`';
                if($onlineFilter === 'online'){
                    $wheres[] = "a.$col = 1";
                } elseif($onlineFilter === 'offline'){
                    $wheres[] = "a.$col = 0";
                }
            } else {
                try {
                    $charsPdo = $this->characters();
                    $onlineIds = $charsPdo->query('SELECT DISTINCT account FROM characters WHERE online=1')->fetchAll(PDO::FETCH_COLUMN,0);
                    $onlineIds = array_values(array_unique(array_map('intval',$onlineIds)));
                    if($onlineFilter === 'online'){
                        if(!$onlineIds){ return new Paginator([],0,$page,$perPage); }
                        $ph = [];
                        foreach($onlineIds as $idx=>$id){ $key=':on'.$idx; $ph[]=$key; $param[$key]=$id; }
                        $wheres[] = 'a.id IN ('.implode(',',$ph).')';
                    } elseif($onlineFilter === 'offline' && $onlineIds){
                        $ph = [];
                        foreach($onlineIds as $idx=>$id){ $key=':off'.$idx; $ph[]=$key; $param[$key]=$id; }
                        $wheres[] = 'a.id NOT IN ('.implode(',',$ph).')';
                    }
                } catch(\Throwable $e){ }
            }
        }

        if($hasBanFilter){
            $banSql = 'EXISTS (SELECT 1 FROM account_banned b WHERE b.id=a.id AND b.active=1 AND (b.unbandate=0 OR b.unbandate>UNIX_TIMESTAMP()))';
            if($banFilter === 'banned'){
                $wheres[] = $banSql;
            } elseif($banFilter === 'unbanned'){
                $wheres[] = 'NOT '.$banSql;
            }
        }

        $where = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

        $cnt = $this->authPdo->prepare("SELECT COUNT(*) FROM account a $where");
        foreach($param as $k=>$v){ $cnt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
        $cnt->execute(); $total=(int)$cnt->fetchColumn();
        $offset=($page-1)*$perPage;

        $orderMap = [
            '' => 'a.id DESC',
            'id_desc' => 'a.id DESC',
            'id_asc' => 'a.id ASC',
            'last_login_desc' => 'a.last_login DESC, a.id DESC',
            'last_login_asc' => 'a.last_login ASC, a.id ASC',
        ];
        if($onlineColumn){
            $col = '`'.str_replace('`','``',$onlineColumn).'`';
            $orderMap['online_desc'] = "a.$col DESC, a.id DESC";
            $orderMap['online_asc'] = "a.$col ASC, a.id ASC";
        }
        $orderBy = $orderMap[$sort] ?? $orderMap[''];

        $selectOnline = $onlineColumn ? ', a.`'.str_replace('`','``',$onlineColumn).'` AS account_online' : '';
        $sql="SELECT a.id,a.username,aa.gmlevel,a.last_login,a.last_ip{$selectOnline}
              FROM account a
              LEFT JOIN account_access aa ON aa.id=a.id
              $where ORDER BY $orderBy LIMIT :limit OFFSET :offset";
        $st=$this->authPdo->prepare($sql);
        foreach($param as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
        $st->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $st->bindValue(':offset',$offset,PDO::PARAM_INT);
        $st->execute();
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);

        if(!$rows){ return new Paginator([],0,$page,$perPage); }

        $ids=[]; foreach($rows as &$r){
            $r['online']=isset($r['account_online'])?(int)$r['account_online']:0; unset($r['account_online']);
            $ids[]=(int)$r['id'];
        }
        unset($r);

        try {
            $charsPdo = $this->characters();

            $in = implode(',', array_fill(0,count($ids),'?'));
            $q = $charsPdo->prepare("SELECT DISTINCT account FROM characters WHERE online=1 AND account IN ($in)");
            $q->execute($ids);
            $onlineIds = array_column($q->fetchAll(PDO::FETCH_ASSOC),'account');
            if($onlineIds){
                $onlineMap = array_flip($onlineIds);
                foreach($rows as &$r){ if(isset($onlineMap[$r['id']])) $r['online']=1; }
                unset($r);
            }
    } catch(\Throwable $e){  }


        try {
            $in = implode(',', array_fill(0,count($ids),'?'));
            $stBan = $this->authPdo->prepare("SELECT id,bandate,unbandate,banreason,active FROM account_banned WHERE active=1 AND id IN ($in)");
            $stBan->execute($ids);
            $banRows = $stBan->fetchAll(PDO::FETCH_ASSOC);
            $banMap=[]; $now=time();
            foreach($banRows as $b){
                $permanent = ((int)$b['unbandate'])===0;
                $remaining = $permanent? -1 : max(0, ((int)$b['unbandate']) - $now);
                $banMap[$b['id']] = [
                    'bandate'=>(int)$b['bandate'],
                    'unbandate'=>(int)$b['unbandate'],
                    'banreason'=>$b['banreason'],
                    'permanent'=>$permanent,
                    'remaining_seconds'=>$remaining,
                ];
            }
            foreach($rows as &$r){ if(isset($banMap[$r['id']])) $r['ban']=$banMap[$r['id']]; }
            unset($r);
    } catch(\Throwable $e){  }

        return new Paginator($rows,$total,$page,$perPage);
    }

    public function findByUsername(string $u): ?array
    { $st=$this->authPdo->prepare('SELECT a.id,a.username FROM account a WHERE a.username=:u LIMIT 1'); $st->execute([':u'=>$u]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?:null; }


    public function listCharacters(int $accountId): array
    {
        $pdo = $this->characters();
        $st=$pdo->prepare('SELECT guid,name,class,level,online FROM characters WHERE account=:a ORDER BY guid DESC LIMIT 50');
        $st->execute([':a'=>$accountId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setGmLevel(int $accountId,int $gmLevel,int $realmId= -1): bool
    {

        $this->authPdo->beginTransaction();
        try{
            $del=$this->authPdo->prepare('DELETE FROM account_access WHERE id=:id AND RealmID=:r');
            $del->execute([':id'=>$accountId,':r'=>$realmId]);
            if($gmLevel>0){
                $ins=$this->authPdo->prepare('INSERT INTO account_access (id,gmlevel,RealmID) VALUES (:id,:g,:r)');
                $ins->execute([':id'=>$accountId,':g'=>$gmLevel,':r'=>$realmId]);
            }
            $this->authPdo->commit();
            return true;
        }catch(\Throwable $e){ $this->authPdo->rollBack(); return false; }
    }

    public function ban(int $accountId,string $reason,int $durationHours=0): bool
    {

        $bandate=time();
        $unban=$durationHours>0? $bandate + $durationHours*3600 : 0;
        $st=$this->authPdo->prepare('INSERT INTO account_banned (id,bandate,unbandate,bannedby,banreason,active) VALUES (:id,:bd,:ud,:bb,:br,1)');
        return $st->execute([':id'=>$accountId,':bd'=>$bandate,':ud'=>$unban,':bb'=>'panel',':br'=>$reason]);
    }

    public function unban(int $accountId): int
    {
        $st=$this->authPdo->prepare('UPDATE account_banned SET active=0 WHERE id=:id AND active=1');
        $st->execute([':id'=>$accountId]);
        return $st->rowCount();
    }

    public function changePassword(int $accountId,string $username,string $newPlain): bool
    {

        if(strlen($newPlain) < 8){ return false; }


        if(!$this->schemaChecked){ $this->inspectAccountSchema(); }

        $userUpper = strtoupper($username);
        $passUpper = strtoupper($newPlain);


        $updated=false; $params=[':id'=>$accountId]; $sets=[];


        if($this->hasSrpVerifier && $this->hasSrpSalt && $this->srpColV && $this->srpColS){
            try {
                if($this->srpBinary32){
                    $srp = SrpService::generateBinary32($username,$newPlain);
                    $sets[] = "{$this->srpColV}=:v"; $params[':v']=$srp['verifier_bin'];
                    $sets[] = "{$this->srpColS}=:s"; $params[':s']=$srp['salt_bin'];
                } else {
                    $srp = SrpService::generate($username,$newPlain);
                    $sets[] = "{$this->srpColV}=:v"; $params[':v']=$srp['verifier_hex'];
                    $sets[] = "{$this->srpColS}=:s"; $params[':s']=$srp['salt_hex'];
                }
                $updated=true;
            } catch(\Throwable $e){  }
        }


        if($this->hasShaHash){
            $sha1 = strtoupper(sha1($userUpper.':'.$passUpper));
            $sets[]='sha_pass_hash=:h'; $params[':h']=$sha1; $updated=true;
        }


        if($this->hasSessionKeyColumn && $this->sessionKeyColumn){
            $col = '`'.str_replace('`','``',$this->sessionKeyColumn).'`';
            $sets[] = $col . "=''";
        }

        if($updated && $sets){
            $sql='UPDATE account SET '.implode(',', $sets).' WHERE id=:id';
            $st=$this->authPdo->prepare($sql);
            try { return $st->execute($params); }
            catch(\PDOException $e){
                $msg=$e->getMessage();

                if(stripos($msg,'22001')!==false && $this->srpColV && $this->srpColS){
                    try{
                        $bin = SrpService::generateBinary32($username,$newPlain);

                        $sets2=[]; $params2=[':id'=>$accountId];
                        foreach($sets as $s){
                            if(str_starts_with($s,$this->srpColV.'=')) { $sets2[]=$this->srpColV.'=:v2'; $params2[':v2']=$bin['verifier_bin']; continue; }
                            if(str_starts_with($s,$this->srpColS.'=')) { $sets2[]=$this->srpColS.'=:s2'; $params2[':s2']=$bin['salt_bin']; continue; }
                            $sets2[]=$s;

                            if($s==='sha_pass_hash=:h') $params2[':h']=$params[':h']??null;
                        }
                        $sql2='UPDATE account SET '.implode(',', $sets2).' WHERE id=:id';
                        $st2=$this->authPdo->prepare($sql2);
                        return $st2->execute($params2);
                    }catch(\Throwable $e2){  }
                }
                if(stripos($msg,'Unknown column')!==false) return false;
                throw $e;
            }
        }

    return false;
    }

    public function createAccount(string $username, string $password, string $email = ''): int
    {
        $username = trim($username);
        if($username === '' || strlen($username) < 3){
            throw new \InvalidArgumentException(Lang::get('app.account.api.validation.username_min'));
        }
        if(strlen($username) > 32){
            throw new \InvalidArgumentException(Lang::get('app.account.api.validation.username_max'));
        }
        if(strlen($password) < 8){
            throw new \InvalidArgumentException(Lang::get('app.account.api.validation.password_min'));
        }
        $email = trim($email);
        if(!$this->schemaChecked){ $this->inspectAccountSchema(); }
        if(!$this->accountColumnsMeta){ $this->inspectAccountColumns(); }

        if(!$this->hasColumn('username')){
            throw new \RuntimeException(Lang::get('app.account.api.errors.missing_username_column'));
        }

        $this->authPdo->beginTransaction();
        try {
            $check = $this->authPdo->prepare('SELECT id FROM account WHERE username = :u LIMIT 1');
            $check->execute([':u'=>$username]);
            if($check->fetch()){
                throw new \RuntimeException(Lang::get('app.account.api.errors.username_exists'));
            }

            $now = gmdate('Y-m-d H:i:s');
            $columns = [];
            $placeholders = [];
            $params = [];
            $paramTypes = [];
            $addedColumns = [];

            $makePlaceholder = function(string $field, string $suffix = '') use (&$params): string {
                $base = strtolower($field);
                $base = preg_replace('/[^a-z0-9_]/','_', $base);
                if($base === ''){ $base = 'p'; }
                if($suffix !== ''){
                    $suffixClean = preg_replace('/[^a-z0-9_]/','_', strtolower($suffix));
                    if($suffixClean !== ''){ $base .= '_' . $suffixClean; }
                }
                $placeholder = ':' . $base;
                $counter = 1;
                while(array_key_exists($placeholder,$params)){
                    $placeholder = ':' . $base . '_' . $counter;
                    $counter++;
                }
                return $placeholder;
            };

            $addColumn = function(string $field,$value,?int $pdoType=null) use (&$columns,&$placeholders,&$params,&$paramTypes,&$addedColumns,$makePlaceholder){
                if(!$this->hasColumn($field)){
                    return;
                }
                $column = $this->columnName($field);
                if($column === '' || isset($addedColumns[$column])){
                    return;
                }
                $info = $this->getColumnInfo($field);
                if($pdoType === null){
                    if($value === null){
                        $pdoType = PDO::PARAM_NULL;
                    } elseif($info && $this->columnIsBinary($info)){
                        $pdoType = PDO::PARAM_LOB;
                    } elseif(is_int($value)){
                        $pdoType = PDO::PARAM_INT;
                    } elseif(is_bool($value)){
                        $pdoType = PDO::PARAM_BOOL;
                    } else {
                        $pdoType = PDO::PARAM_STR;
                    }
                }
                $placeholder = $makePlaceholder($column);
                $columns[] = $column;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $value;
                $paramTypes[$placeholder] = $pdoType;
                $addedColumns[$column] = true;
            };

            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

            $addColumn('username',$username);
            if($this->hasColumn('email')){ $addColumn('email',$email); }
            if($this->hasColumn('reg_mail')){ $addColumn('reg_mail',$email); }
            if($this->hasColumn('expansion')){ $addColumn('expansion',2); }
            if($this->hasColumn('joindate')){ $addColumn('joindate',$now); }
            if($this->hasColumn('last_ip')){ $addColumn('last_ip',$clientIp); }
            if($this->hasColumn('last_attempt_ip')){ $addColumn('last_attempt_ip',$clientIp); }
            if($this->hasColumn('failed_logins')){ $addColumn('failed_logins',0); }
            if($this->hasColumn('locked')){ $addColumn('locked',0); }
            if($this->hasColumn('lock_country')){ $addColumn('lock_country','00'); }
            if($this->hasColumn('online')){ $addColumn('online',0); }
            if($this->hasColumn('flags')){ $addColumn('flags',0); }
            if($this->hasColumn('mutetime')){ $addColumn('mutetime',0); }
            if($this->hasColumn('mutereason')){ $addColumn('mutereason',''); }
            if($this->hasColumn('muteby')){ $addColumn('muteby',''); }
            if($this->hasColumn('locale')){ $addColumn('locale',0); }
            if($this->hasColumn('os')){ $addColumn('os',''); }
            if($this->hasColumn('recruiter')){ $addColumn('recruiter',0); }
            if($this->hasColumn('totaltime')){ $addColumn('totaltime',0); }

            $saltCandidates = array_values(array_unique(array_filter([
                $this->srpColS,
                $this->srpColS ? null : ($this->hasColumn('salt') ? 'salt' : null),
                $this->srpColS ? null : ($this->hasColumn('s') ? 's' : null),
            ], fn($v) => $v !== null)));

            foreach($saltCandidates as $saltField){
                if(!$this->columnRequiresValue($saltField)){
                    continue;
                }
                $info = $this->getColumnInfo($saltField);
                $len = max(1,$this->columnLength($info) ?: 32);
                if($info && $this->columnIsBinary($info)){
                    try { $saltValue = random_bytes($len); }
                    catch(\Throwable $e){ $saltValue = str_repeat("\0",$len); }
                } else {
                    $byteCount = max(1,(int)ceil($len / 2));
                    try { $raw = random_bytes($byteCount); }
                    catch(\Throwable $e){ $raw = str_repeat("\0",$byteCount); }
                    $hex = bin2hex($raw);
                    if(strlen($hex) > $len){ $hex = substr($hex,0,$len); }
                    if(strlen($hex) < $len){ $hex = str_pad($hex,$len,'0'); }
                    $saltValue = $hex;
                }
                $addColumn($saltField,$saltValue);
            }

            $verifierCandidates = array_values(array_unique(array_filter([
                $this->srpColV,
                $this->srpColV ? null : ($this->hasColumn('verifier') ? 'verifier' : null),
                $this->srpColV ? null : ($this->hasColumn('v') ? 'v' : null),
            ], fn($v) => $v !== null)));

            foreach($verifierCandidates as $verField){
                if(!$this->columnRequiresValue($verField)){
                    continue;
                }
                $info = $this->getColumnInfo($verField);
                $len = max(1,$this->columnLength($info) ?: 32);
                if($info && $this->columnIsBinary($info)){
                    $verifierValue = str_repeat("\0",$len);
                } else {
                    $verifierValue = str_repeat('0',$len);
                }
                $addColumn($verField,$verifierValue);
            }

            if($this->hasColumn('sha_pass_hash') && $this->columnRequiresValue('sha_pass_hash')){
                $addColumn('sha_pass_hash','');
            }

            if(!$columns){
                throw new \RuntimeException(Lang::get('app.account.api.errors.build_columns_failed'));
            }

            $quotedColumns = array_map(function($col){
                return '`'.str_replace('`','``',$col).'`';
            }, $columns);
            $insertSql = 'INSERT INTO account ('.implode(',', $quotedColumns).') VALUES ('.implode(',', $placeholders).')';
            $ins = $this->authPdo->prepare($insertSql);
            foreach($params as $placeholder=>$value){
                $type = $paramTypes[$placeholder] ?? PDO::PARAM_STR;
                switch($type){
                    case PDO::PARAM_INT:
                        $ins->bindValue($placeholder,(int)$value,PDO::PARAM_INT);
                        break;
                    case PDO::PARAM_BOOL:
                        $ins->bindValue($placeholder,$value ? 1 : 0,PDO::PARAM_INT);
                        break;
                    case PDO::PARAM_NULL:
                        $ins->bindValue($placeholder,null,PDO::PARAM_NULL);
                        break;
                    case PDO::PARAM_LOB:
                        $ins->bindValue($placeholder,$value,PDO::PARAM_LOB);
                        break;
                    default:
                        $ins->bindValue($placeholder,(string)$value,PDO::PARAM_STR);
                        break;
                }
            }
            $ins->execute();

            $id = (int)$this->authPdo->lastInsertId();
            if($id <= 0){
                $fetch = $this->authPdo->prepare('SELECT id FROM account WHERE username = :u ORDER BY id DESC LIMIT 1');
                $fetch->execute([':u'=>$username]);
                $id = (int)$fetch->fetchColumn();
            }
            if($id <= 0){
                throw new \RuntimeException(Lang::get('app.account.api.errors.missing_account_id'));
            }

            if($email !== ''){
                try {
                    $upd = $this->authPdo->prepare('UPDATE account SET email=:e, reg_mail=:e WHERE id=:id');
                    $upd->execute([':e'=>$email, ':id'=>$id]);
                } catch(\PDOException $e){
                    if(stripos($e->getMessage(),'Unknown column')===false){
                        throw $e;
                    }
                }
            }

            if(!$this->changePassword($id,$username,$password)){
                throw new \RuntimeException(Lang::get('app.account.api.errors.password_set_failed'));
            }

            $this->authPdo->commit();
            return $id;
        } catch(\Throwable $e){
            $this->authPdo->rollBack();
            throw $e;
        }
    }







    public function banStatus(int $accountId): ?array
    {
        $st=$this->authPdo->prepare('SELECT bandate,unbandate,banreason,active FROM account_banned WHERE id=:id AND active=1 ORDER BY bandate DESC LIMIT 1');
        $st->execute([':id'=>$accountId]);
        $row=$st->fetch(PDO::FETCH_ASSOC); if(!$row) return null;
        $permanent = ((int)$row['unbandate']) === 0;
        $now=time();
        $remaining = $permanent? -1 : max(0, ((int)$row['unbandate']) - $now);
        return [
            'bandate'=>(int)$row['bandate'],
            'unbandate'=>(int)$row['unbandate'],
            'banreason'=>$row['banreason'],
            'permanent'=>$permanent,
            'remaining_seconds'=>$remaining,
        ];
    }

    public function accountsByLastIp(string $ip, int $excludeAccountId = 0, int $limit = 50): array
    {
        $ip = trim($ip);
        if($ip === '') return [];

        $sql = 'SELECT a.id,a.username,aa.gmlevel,a.last_login,a.last_ip FROM account a LEFT JOIN account_access aa ON aa.id=a.id WHERE a.last_ip = :ip';
        if($excludeAccountId > 0){
            $sql .= ' AND a.id <> :exclude';
        }
        $sql .= ' ORDER BY a.id DESC LIMIT :limit';

        $st = $this->authPdo->prepare($sql);
        $st->bindValue(':ip', $ip, PDO::PARAM_STR);
        if($excludeAccountId > 0){
            $st->bindValue(':exclude', $excludeAccountId, PDO::PARAM_INT);
        }
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows) return [];

        $ids = [];
        foreach($rows as &$row){
            $row['online'] = 0;
            $ids[] = (int)$row['id'];
        }
        unset($row);

        try {
            if($ids){
                $charsPdo = $this->characters();
                $in = implode(',', array_fill(0,count($ids),'?'));
                $stOnline = $charsPdo->prepare("SELECT DISTINCT account FROM characters WHERE online=1 AND account IN ($in)");
                $stOnline->execute($ids);
                $onlineIds = array_column($stOnline->fetchAll(PDO::FETCH_ASSOC),'account');
                if($onlineIds){
                    $map = array_flip($onlineIds);
                    foreach($rows as &$row){ if(isset($map[$row['id']])) $row['online'] = 1; }
                    unset($row);
                }
            }
        } catch(\Throwable $e){  }

        try {
            if($ids){
                $in = implode(',', array_fill(0,count($ids),'?'));
                $stBan = $this->authPdo->prepare("SELECT id,bandate,unbandate,banreason,active FROM account_banned WHERE active=1 AND id IN ($in)");
                $stBan->execute($ids);
                $banRows = $stBan->fetchAll(PDO::FETCH_ASSOC);
                if($banRows){
                    $now = time();
                    $banMap = [];
                    foreach($banRows as $ban){
                        $permanent = ((int)$ban['unbandate']) === 0;
                        $remaining = $permanent ? -1 : max(0, ((int)$ban['unbandate']) - $now);
                        $banMap[$ban['id']] = [
                            'bandate'=>(int)$ban['bandate'],
                            'unbandate'=>(int)$ban['unbandate'],
                            'banreason'=>$ban['banreason'],
                            'permanent'=>$permanent,
                            'remaining_seconds'=>$remaining,
                        ];
                    }
                    foreach($rows as &$row){ if(isset($banMap[$row['id']])) $row['ban'] = $banMap[$row['id']]; }
                    unset($row);
                }
            }
        } catch(\Throwable $e){  }

        return $rows;
    }

    private function fetchAccountColumns(): void
    {
        if($this->accountColumnsMeta){
            return;
        }
        $cols = [];
        try {
            $st = $this->authPdo->query('SHOW COLUMNS FROM account');
            if($st){
                $cols = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch(\Throwable $e){
            $cols = [];
        }

        $this->accountColumnsMeta = [];
        $this->accountColumnsLower = [];
        $this->accountColumnTypes = [];
        foreach($cols as $row){
            $name = $row['Field'];
            $this->accountColumnsMeta[$name] = $row;
            $lower = strtolower($name);
            $this->accountColumnsLower[$lower] = $name;
            $this->accountColumnTypes[$lower] = strtolower($row['Type'] ?? '');
        }
    }

    private function inspectAccountColumns(): void
    {
        $this->fetchAccountColumns();
    }

    private function inspectAccountSchema(): void
    {
        if($this->schemaChecked){
            return;
        }

        $this->fetchAccountColumns();

        if(!$this->accountColumnsMeta){
            $this->hasSrpVerifier = false;
            $this->hasSrpSalt = false;
            $this->hasShaHash = true;
            $this->hasSessionKeyColumn = false;
            $this->sessionKeyColumn = null;
            $this->srpColV = null;
            $this->srpColS = null;
            $this->srpBinary32 = false;
            $this->schemaChecked = true;
            return;
        }

        $lower = $this->accountColumnsLower;
        $types = $this->accountColumnTypes;

        $this->hasSrpVerifier = isset($lower['v']) || isset($lower['verifier']);
        $this->hasSrpSalt = isset($lower['s']) || isset($lower['salt']);
        $this->hasShaHash = isset($lower['sha_pass_hash']);
        $this->sessionKeyColumn = $lower['sessionkey'] ?? ($lower['session_key'] ?? null);
        $this->hasSessionKeyColumn = $this->sessionKeyColumn !== null;
        $this->srpColV = $this->hasSrpVerifier ? ($lower['v'] ?? $lower['verifier']) : null;
        $this->srpColS = $this->hasSrpSalt ? ($lower['s'] ?? $lower['salt']) : null;

        $this->srpBinary32 = false;
        if($this->srpColV){
            $type = $types[strtolower($this->srpColV)] ?? '';
            if(preg_match('/varbinary\(\d+\)|binary\(\d+\)/',$type)){
                $this->srpBinary32 = true;
            }
        }
        if(!$this->srpBinary32 && $this->srpColS){
            $type = $types[strtolower($this->srpColS)] ?? '';
            if(preg_match('/varbinary\(\d+\)|binary\(\d+\)/',$type)){
                $this->srpBinary32 = true;
            }
        }

        if(!$this->hasShaHash && !$this->hasSrpVerifier && !$this->hasSrpSalt){
            $this->hasShaHash = true;
        }

        $this->schemaChecked = true;
    }

    private function hasColumn(string $field): bool
    {
        $this->inspectAccountColumns();
        $lower = strtolower($field);
        return isset($this->accountColumnsLower[$lower]) || isset($this->accountColumnsMeta[$field]);
    }

    private function columnName(string $field): string
    {
        $this->inspectAccountColumns();
        $lower = strtolower($field);
        return $this->accountColumnsLower[$lower] ?? $field;
    }

    private function getColumnInfo(string $field): ?array
    {
        $this->inspectAccountColumns();
        $name = $this->columnName($field);
        return $this->accountColumnsMeta[$name] ?? null;
    }

    private function columnRequiresValue(string $field): bool
    {
        $info = $this->getColumnInfo($field);
        if(!$info){
            return false;
        }
        $extra = strtolower($info['Extra'] ?? '');
        if(str_contains($extra,'auto_increment')){
            return false;
        }
        if(array_key_exists('Default',$info) && $info['Default'] !== null){
            return false;
        }
        $null = strtoupper($info['Null'] ?? 'YES');
        return $null === 'NO';
    }

    private function columnLength(?array $info): ?int
    {
        if(!$info){
            return null;
        }
        $type = strtolower($info['Type'] ?? '');
        if(preg_match('/\((\d+)\)/',$type,$m)){
            return (int)$m[1];
        }
        return null;
    }

    private function columnIsBinary(?array $info): bool
    {
        if(!$info){
            return false;
        }
        $type = strtolower($info['Type'] ?? '');
        return str_contains($type,'binary') || str_contains($type,'blob');
    }
}

