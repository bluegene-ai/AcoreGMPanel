<?php
/**
 * File: app/Domain/BagQuery/BagQueryRepository.php
 * Purpose: Defines class BagQueryRepository for the app/Domain/BagQuery module.
 * Classes:
 *   - BagQueryRepository
 * Functions:
 *   - __construct()
 *   - searchCharacters()
 *   - characterItems()
 *   - findCharacter()
 *   - listItems()
 *   - reduceItem()
 *   - reduceInstance()
 *   - finalizeReduceResult()
 *   - appendReduceLog()
 *   - appendLog()
 *   - logsDir()
 *   - currentUser()
 */

namespace Acme\Panel\Domain\BagQuery;

use PDO; use Throwable; use Acme\Panel\Domain\Support\MultiServerRepository; use Acme\Panel\Core\Database; use Acme\Panel\Support\ServerContext; use Acme\Panel\Core\Lang;

class BagQueryRepository extends MultiServerRepository
{
    private PDO $auth; private PDO $chars; private PDO $world; private string $logFile;
    public function __construct(?int $serverId=null)
    { parent::__construct($serverId); $this->auth=$this->auth(); $this->chars=$this->characters(); $this->world=$this->world(); $this->logFile=$this->logsDir().DIRECTORY_SEPARATOR.'bag_query_actions.log'; }


    public function searchCharacters(string $type,string $value,int $limit=100): array
    {
        $value=trim($value);
        if($value===''){
            $this->appendLog('search',['type'=>$type,'value'=>'','limit'=>$limit,'result'=>0,'reason'=>'empty_input']);
            return [];
        }
        $limit = max(1,min($limit,200));
        if($type==='username'){
            $st=$this->auth->prepare('SELECT id,username FROM account WHERE username=:u');
            $st->execute([':u'=>$value]); $acc=$st->fetch(PDO::FETCH_ASSOC);
            if(!$acc){
                $this->appendLog('search',['type'=>$type,'value'=>mb_substr($value,0,40),'limit'=>$limit,'result'=>0,'reason'=>'account_not_found']);
                return [];
            }
            $stc=$this->chars->prepare('SELECT guid,name,level,race,class,account FROM characters WHERE account=:aid ORDER BY name ASC LIMIT :lim');
            $stc->bindValue(':aid',(int)$acc['id'],PDO::PARAM_INT); $stc->bindValue(':lim',$limit,PDO::PARAM_INT); $stc->execute();
            $rows=$stc->fetchAll(PDO::FETCH_ASSOC)?:[];
            $rows=$this->attachAccountMeta($rows);
            $this->appendLog('search',['type'=>$type,'value'=>mb_substr($value,0,40),'limit'=>$limit,'result'=>count($rows),'account_id'=>(int)$acc['id'],'account_username'=>$acc['username']??null]);
            return $rows;
        }
        $stc=$this->chars->prepare('SELECT guid,name,level,race,class,account FROM characters WHERE name LIKE :n ORDER BY name ASC LIMIT :lim');
        $stc->bindValue(':n','%'.$value.'%'); $stc->bindValue(':lim',$limit,PDO::PARAM_INT); $stc->execute();
        $rows=$stc->fetchAll(PDO::FETCH_ASSOC)?:[];
        $rows=$this->attachAccountMeta($rows);
        $this->appendLog('search',['type'=>$type,'value'=>mb_substr($value,0,40),'limit'=>$limit,'result'=>count($rows)]);
        return $rows;
    }

    public function characterItems(int $guid): array
    {
        if($guid<=0) return [];
        $sql = "SELECT ci.item AS item_instance_guid, ci.bag, ci.slot, ii.itemEntry, ii.count, ii.durability, ii.charges
                FROM character_inventory ci
                JOIN item_instance ii ON ci.item=ii.guid
                WHERE ci.guid=:g
                ORDER BY FIELD(ci.slot,".implode(',',range(0,18))."), ci.bag, ci.slot";
        $st=$this->chars->prepare($sql); $st->execute([':g'=>$guid]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC)?:[];
        if(!$rows) return [];

        $entries=array_values(array_unique(array_map(static fn($row)=> (int)($row['itemEntry']??0), $rows)));
        $map=[];
        if($entries){
            $placeholders=implode(',',array_fill(0,count($entries),'?'));
            $stmt=$this->world->prepare("SELECT entry,name,Quality FROM item_template WHERE entry IN ($placeholders)");
            $stmt->execute($entries);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $it){
                $map[(int)$it['entry']]=[
                    'name'=>$it['name']??null,
                    'quality'=>isset($it['Quality'])?(int)$it['Quality']:null,
                ];
            }
        }

        foreach($rows as &$row){
            $entry=(int)($row['itemEntry']??0);
            $meta=$map[$entry]??null;
            $row['name']=$meta['name']??null;
            $row['quality']=$meta['quality']??null;
        }
        unset($row);

    $this->appendLog('view_items',['character_guid'=>$guid,'result'=>count($rows)]);
    return $rows;
    }

    public function findCharacter(string $name): ?array
    {
        $name=trim($name); if($name==='') return null;
    $st=$this->chars->prepare('SELECT guid,name,level,race,class,account FROM characters WHERE name=:n LIMIT 1');
    $st->execute([':n'=>$name]);
        $row=$st->fetch(PDO::FETCH_ASSOC);
        if(!$row){

            $st=$this->chars->prepare('SELECT guid,name,level,race,class,account FROM characters WHERE name LIKE :n ORDER BY level DESC LIMIT 1');
            $st->execute([':n'=>'%'.$name.'%']);
            $row=$st->fetch(PDO::FETCH_ASSOC);
        }
        return $row?:null;
    }

    public function listItems(int $guid): array
    {
        $rows=$this->characterItems($guid);
        if(!$rows) return [];
        return array_map(static function(array $row): array{
            return [
                'inst_guid'=>(int)($row['item_instance_guid']??0),
                'itemEntry'=>(int)($row['itemEntry']??0),
                'item_name'=>$row['name']??null,
                'count'=>(int)($row['count']??0),
                'durability'=>(int)($row['durability']??0),
                'charges'=>(int)($row['charges']??0),
            ];
        },$rows);
    }

    public function reduceItem(int $characterGuid,int $entry,int $amount): int
    {
        if($characterGuid<=0||$entry<=0||$amount<=0) return 0;
        $st=$this->chars->prepare('SELECT ii.guid, ii.count FROM character_inventory ci JOIN item_instance ii ON ci.item=ii.guid WHERE ci.guid=:cg AND ii.itemEntry=:entry ORDER BY ii.count DESC');
        $st->execute([':cg'=>$characterGuid,':entry'=>$entry]);
        $instances=$st->fetchAll(PDO::FETCH_ASSOC)?:[];
        if(!$instances) return 0;
        $remaining=$amount; $affected=0;
        foreach($instances as $inst){
            if($remaining<=0) break;
            $instGuid=(int)$inst['guid']; $current=(int)$inst['count']; if($current<=0) continue;
            $remove=min($current,$remaining);
            $res=$this->reduceInstance($characterGuid,$instGuid,$remove,$entry);
            if(empty($res['success'])) continue;
            $newCount=(int)($res['new_count']??0);
            $removed=$current-$newCount;
            if($removed<=0) continue;
            $affected+=$removed; $remaining-=$removed;
        }
    $this->appendLog('reduce_entry',[ 'character_guid'=>$characterGuid,'item_entry'=>$entry,'requested'=>$amount,'affected'=>$affected ]);
    return $affected;
    }

    public function reduceInstance(int $characterGuid,int $itemInstanceGuid,int $qty,?int $itemEntry=null): array
    {
        if($qty<=0){
            $result=['success'=>false,'message'=>Lang::get('app.bag_query.api.errors.quantity_positive'),'new_count'=>-1];
            return $this->finalizeReduceResult($characterGuid,$itemInstanceGuid,$qty,$itemEntry,$result);
        }
        $pdo=$this->chars; $pdo->beginTransaction();
        try {
            $st=$pdo->prepare('SELECT ii.count FROM item_instance ii JOIN character_inventory ci ON ci.item=ii.guid AND ci.guid=:cg WHERE ii.guid=:g FOR UPDATE');
            $st->execute([':g'=>$itemInstanceGuid,':cg'=>$characterGuid]); $row=$st->fetch(PDO::FETCH_ASSOC);
            if(!$row){
                $pdo->rollBack();
                $result=['success'=>false,'message'=>Lang::get('app.bag_query.api.errors.instance_not_found'),'new_count'=>-1];
                return $this->finalizeReduceResult($characterGuid,$itemInstanceGuid,$qty,$itemEntry,$result);
            }
            $current=(int)$row['count']; if($qty>$current){ $pdo->rollBack(); $result=['success'=>false,'message'=>Lang::get('app.bag_query.api.errors.quantity_exceeds_stack'),'new_count'=>$current]; return $this->finalizeReduceResult($characterGuid,$itemInstanceGuid,$qty,$itemEntry,$result); }
            $new=$current-$qty;
            if($new<=0){
                $delInv=$pdo->prepare('DELETE FROM character_inventory WHERE guid=:cg AND item=:g');
                $delInv->execute([':cg'=>$characterGuid,':g'=>$itemInstanceGuid]);
                if($delInv->rowCount()===0){ $pdo->rollBack(); $result=['success'=>false,'message'=>Lang::get('app.bag_query.api.errors.inventory_mismatch'),'new_count'=>-1]; return $this->finalizeReduceResult($characterGuid,$itemInstanceGuid,$qty,$itemEntry,$result); }
                $delInst=$pdo->prepare('DELETE FROM item_instance WHERE guid=:g'); $delInst->execute([':g'=>$itemInstanceGuid]);
                $pdo->commit();
                $result=['success'=>true,'message'=>Lang::get('app.bag_query.api.success.item_deleted'),'new_count'=>0];
                return $this->finalizeReduceResult($characterGuid,$itemInstanceGuid,$qty,$itemEntry,$result);
            }
            $upd=$pdo->prepare('UPDATE item_instance SET count=:c WHERE guid=:g');
            $upd->execute([':c'=>$new,':g'=>$itemInstanceGuid]); $pdo->commit();
            $result=['success'=>true,'message'=>Lang::get('app.bag_query.api.success.quantity_reduced'),'new_count'=>$new];
            return $this->finalizeReduceResult($characterGuid,$itemInstanceGuid,$qty,$itemEntry,$result);
        } catch(Throwable $e){
            $pdo->rollBack();
            $result=['success'=>false,'message'=>Lang::get('app.bag_query.api.errors.reduce_failed',['message'=>$e->getMessage()]),'new_count'=>-1];
            return $this->finalizeReduceResult($characterGuid,$itemInstanceGuid,$qty,$itemEntry,$result);
        }
    }

    private function finalizeReduceResult(int $characterGuid,int $itemInstanceGuid,int $qty,?int $itemEntry,array $result): array
    {
        $this->appendReduceLog($characterGuid,$itemInstanceGuid,$qty,$itemEntry,$result);
        return $result;
    }

    private function appendReduceLog(int $characterGuid,int $itemInstanceGuid,int $qty,?int $itemEntry,array $result): void
    {
        $this->appendLog('reduce_instance',[
            'character_guid'=>$characterGuid,
            'item_instance'=>$itemInstanceGuid,
            'item_entry'=>$itemEntry,
            'quantity'=>$qty,
            'success'=>(bool)($result['success']??false),
            'new_count'=>$result['new_count']??null,
            'message'=>$result['message']??null,
        ]);
    }

    private function attachAccountMeta(array $rows): array
    {
        if(!$rows){
            return [];
        }

        $ids=[];
        foreach($rows as $row){
            $accId=(int)($row['account']??0);
            if($accId>0){
                $ids[$accId]=true;
            }
        }

        $map=[];
        if($ids){
            $acctIds=array_map('intval',array_keys($ids));
            $placeholders=implode(',',array_fill(0,count($acctIds),'?'));
            $stmt=$this->auth->prepare("SELECT id,username FROM account WHERE id IN ($placeholders)");
            $stmt->execute($acctIds);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
                $map[(int)$row['id']]=$row['username']??null;
            }
        }

        foreach($rows as &$row){
            $accId=(int)($row['account']??0);
            $row['account_id']=$accId;
            $row['account_username']=$map[$accId]??null;
        }
        unset($row);

        return $rows;
    }

    private function appendLog(string $event,array $context): void
    {
        try {
            $data=$context + [
                'admin'=>$this->currentUser(),
                'server'=>$this->serverId,
            ];
            if(!array_key_exists('ip',$data)){
                $data['ip']=$_SERVER['REMOTE_ADDR'] ?? '';
            }
            $data=array_filter($data,static fn($v)=>$v!==null);
            $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $line=sprintf('[%s] bag.%s %s%s',date('Y-m-d H:i:s'),$event,$json?:'{}',PHP_EOL);
            @file_put_contents($this->logFile,$line,FILE_APPEND);
        } catch(Throwable $e){  }
    }

    private function logsDir(): string
    {
        $dir=dirname(__DIR__,3).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs';
        if(!is_dir($dir)) @mkdir($dir,0777,true);
        return $dir;
    }

    private function currentUser(): string
    {
        return $_SESSION['panel_user'] ?? ($_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown'));
    }
}
?>

