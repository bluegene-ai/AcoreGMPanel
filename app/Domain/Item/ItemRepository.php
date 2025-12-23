<?php
/**
 * File: app/Domain/Item/ItemRepository.php
 * Purpose: Defines class ItemRepository for the app/Domain/Item module.
 * Classes:
 *   - ItemRepository
 * Functions:
 *   - __construct()
 *   - repoMessage()
 *   - repoError()
 *   - validColumns()
 *   - search()
 *   - find()
 *   - create()
 *   - delete()
 *   - updatePartial()
 *   - execLimitedSql()
 *   - shortVal()
 *   - logsDir()
 *   - writeLogLine()
 *   - appendActionLog()
 *   - appendDeletedLog()
 *   - appendSqlLog()
 *   - currentUser()
 */

namespace Acme\Panel\Domain\Item;

use PDO;
use Acme\Panel\Support\Paginator;
use Acme\Panel\Support\Audit;
use Acme\Panel\Support\Snapshot;
use Acme\Panel\Domain\Support\MultiServerRepository;
use Acme\Panel\Core\Lang;

class ItemRepository extends MultiServerRepository
{
    private PDO $world;
    public function __construct(){ parent::__construct(); $this->world = $this->world(); }

    private function repoMessage(string $key, array $params = []): string
    {
        return Lang::get('app.item.repository.messages.'.$key, $params);
    }

    private function repoError(string $key, array $params = []): string
    {
        return Lang::get('app.item.repository.errors.'.$key, $params);
    }




    public static function validColumns(): array
    {
        return [
            'entry','class','subclass','soundoverride_subclass','name','displayid','quality','flags','flags_extra','buycount','buyprice','sellprice','inventorytype','allowableclass','allowablerace','itemlevel','requiredlevel','requiredskill','requiredskillrank','requiredspell','requiredhonorrank','requiredcityrank','requiredreputationfaction','requiredreputationrank','maxcount','stackable','containerSlots','stat_type1','stat_value1','stat_type2','stat_value2','stat_type3','stat_value3','stat_type4','stat_value4','stat_type5','stat_value5','stat_type6','stat_value6','stat_type7','stat_value7','stat_type8','stat_value8','stat_type9','stat_value9','stat_type10','stat_value10','scalingstatdistribution','scalingstatvalue','dmg_min1','dmg_max1','dmg_type1','dmg_min2','dmg_max2','dmg_type2','armor','holy_res','fire_res','nature_res','frost_res','shadow_res','arcane_res','delay','ammo_type','range_mod','spellid_1','spelltrigger_1','spellcharges_1','spellcooldown_1','spellcategory_1','spellcategorycooldown_1','spellid_2','spelltrigger_2','spellcharges_2','spellcooldown_2','spellcategory_2','spellcategorycooldown_2','spellid_3','spelltrigger_3','spellcharges_3','spellcooldown_3','spellcategory_3','spellcategorycooldown_3','spellid_4','spelltrigger_4','spellcharges_4','spellcooldown_4','spellcategory_4','spellcategorycooldown_4','spellid_5','spelltrigger_5','spellcharges_5','spellcooldown_5','spellcategory_5','spellcategorycooldown_5','bonding','description','pagetext','languageid','pagematerial','startquest','lockid','material','sheath','randomproperty','randomsuffix','itemset','area','map','bagfamily','totemcategory','socketcolor_1','socketcontent_1','socketcolor_2','socketcontent_2','socketcolor_3','socketcontent_3','socketbonus','gemproperties','requireddisenchantskill','armorDamageModifier','duration','itemlimitcategory','holidayId','disenchantid','foodType','minMoneyLoot','maxMoneyLoot','flagscustom','VerifiedBuild'
        ];
    }

    public function search(array $opts): Paginator
    {
        $searchType=$opts['search_type']??'name'; $searchValue=trim($opts['search_value']??'');
        $page=max(1,(int)($opts['page']??1)); $limit=max(10,min(200,(int)($opts['limit']??50))); $offset=($page-1)*$limit;
        $filter_quality = isset($opts['filter_quality']) ? (int)$opts['filter_quality'] : -1;
        $filter_class = isset($opts['filter_class']) ? (int)$opts['filter_class'] : -1;
        $filter_subclass = isset($opts['filter_subclass']) ? (int)$opts['filter_subclass'] : -1;
        $filter_itemlevel_op = $opts['filter_itemlevel_op'] ?? 'any';
        $filter_itemlevel_val = $opts['filter_itemlevel_val']!=='' ? (int)$opts['filter_itemlevel_val'] : null;
        $sort_by=$opts['sort_by']??'entry'; $sort_dir=strtoupper($opts['sort_dir']??'ASC');
        $allowedSort=['entry','name','quality','itemlevel','class','subclass']; if(!in_array($sort_by,$allowedSort,true)) $sort_by='entry'; if($sort_dir!=='ASC' && $sort_dir!=='DESC') $sort_dir='ASC';
        $where=[]; $params=[];
        if($searchValue!==''){
            if($searchType==='id'){
                $id=filter_var($searchValue,FILTER_VALIDATE_INT); if($id){ $where[]='entry = :eid'; $params[':eid']=$id; } else { return new Paginator([],0,$page,$limit); }
            }else{ $where[]='name LIKE :name'; $params[':name']='%'.$searchValue.'%'; }
        }
        if($filter_quality!==-1){ $where[]='quality = :quality'; $params[':quality']=$filter_quality; }
        if($filter_class!==-1){ $where[]='class = :class'; $params[':class']=$filter_class; }
        if($filter_subclass!==-1){ $where[]='subclass = :subclass'; $params[':subclass']=$filter_subclass; }
        if($filter_itemlevel_val!==null){
            if($filter_itemlevel_op==='ge') { $where[]='itemlevel >= :ilv'; }
            elseif($filter_itemlevel_op==='le'){ $where[]='itemlevel <= :ilv'; }
            elseif($filter_itemlevel_op==='eq'){ $where[]='itemlevel = :ilv'; }
            $params[':ilv']=$filter_itemlevel_val;
        }
        $whereSql=$where?(' WHERE '.implode(' AND ',$where)) : '';
        $cnt=$this->world->prepare("SELECT COUNT(*) FROM item_template$whereSql"); foreach($params as $k=>$v){ $cnt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);} $cnt->execute(); $total=(int)$cnt->fetchColumn();


    $sql="SELECT entry,name,quality,class,subclass,itemlevel FROM item_template $whereSql ORDER BY `$sort_by` $sort_dir LIMIT :limit OFFSET :offset";
        $st=$this->world->prepare($sql); foreach($params as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);} $st->bindValue(':limit',$limit,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT); $st->execute(); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        return new Paginator($rows,$total,$page,$limit);
    }

    public function find(int $id): ?array
    { if($id<=0) return null; $st=$this->world->prepare('SELECT * FROM item_template WHERE entry=:id'); $st->execute([':id'=>$id]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?array_change_key_case($r,CASE_LOWER):null; }

    public function create(int $newId, ?int $copyId=null): array
    {
        if($newId<=0){
            $this->appendActionLog('create.validation_fail',['new_id'=>$newId,'copy'=>$copyId]);
            return ['success'=>false,'message'=>$this->repoError('invalid_new_id')];
        }
        $ex=$this->world->prepare('SELECT 1 FROM item_template WHERE entry=:e'); $ex->execute([':e'=>$newId]);
        if($ex->fetch()){
            $this->appendActionLog('create.duplicate',['new_id'=>$newId,'copy'=>$copyId]);
            return ['success'=>false,'message'=>$this->repoError('id_exists')];
        }
        if($copyId){
            $src=$this->world->prepare('SELECT * FROM item_template WHERE entry=:c'); $src->execute([':c'=>$copyId]);
            $data=$src->fetch(PDO::FETCH_ASSOC);
            if(!$data){
                $this->appendActionLog('create.copy_missing',['new_id'=>$newId,'copy'=>$copyId]);
                return ['success'=>false,'message'=>$this->repoError('copy_source_missing')];
            }
            $data['entry']=$newId; $cols=array_keys($data); $ph=array_map(fn($c)=>':'.$c,$cols);
            $sql='INSERT INTO item_template(`'.implode('`,`',$cols).'`) VALUES('.implode(',',$ph).')';
            $ins=$this->world->prepare($sql); foreach($data as $k=>$v){ $ins->bindValue(':'.$k,$v===null?null:$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
            $ok=$ins->execute();
            if($ok){
                Audit::log('item','create',(string)$newId,['copy'=>$copyId]);
                $row=$this->find($newId); if($row){ $this->appendDeletedLog('CREATE',$newId,Snapshot::buildInsert('item_template',$row)); }
                $this->appendActionLog('create.success',['new_id'=>$newId,'copy'=>$copyId,'mode'=>'clone']);
                return ['success'=>true,'message'=>$this->repoMessage('copy_created'),'new_id'=>$newId];
            }
            $this->appendActionLog('create.error',['new_id'=>$newId,'copy'=>$copyId,'mode'=>'clone']);
            return ['success'=>false,'message'=>$this->repoError('copy_failed')];
        }



        $sql='INSERT INTO item_template(entry,name,class,subclass,quality,itemlevel,requiredlevel,stackable) VALUES(:e,:n,0,0,1,1,1,1)';
        $st=$this->world->prepare($sql); $ok=$st->execute([':e'=>$newId,':n'=>'New Item '.$newId]);
        if($ok){
            Audit::log('item','create',(string)$newId,['blank'=>true]);
            $row=$this->find($newId); if($row){ $this->appendDeletedLog('CREATE',$newId,Snapshot::buildInsert('item_template',$row)); }
            $this->appendActionLog('create.success',['new_id'=>$newId,'mode'=>'blank']);
            return ['success'=>true,'message'=>$this->repoMessage('created'),'new_id'=>$newId];
        }
        $this->appendActionLog('create.error',['new_id'=>$newId,'mode'=>'blank']);
        return ['success'=>false,'message'=>$this->repoError('create_failed')];
    }

    public function delete(int $id): array
    {
        if($id<=0){
            $this->appendActionLog('delete.validation_fail',['entry'=>$id]);
            return ['success'=>false,'message'=>$this->repoError('invalid_id')];
        }

        $row=$this->find($id);
        $st=$this->world->prepare('DELETE FROM item_template WHERE entry=:id'); $st->execute([':id'=>$id]); $cnt=$st->rowCount();
        Audit::log('item','delete',(string)$id,['affected'=>$cnt]);
        if($row && $cnt){ $this->appendDeletedLog('DELETE',$id,Snapshot::buildInsert('item_template',$row)); }
        $this->appendActionLog('delete.'.($cnt?'success':'noop'),['entry'=>$id,'affected'=>$cnt]);
        $message = $cnt
            ? $this->repoMessage('delete_success', ['id'=>$id])
            : $this->repoMessage('delete_none');
        return ['success'=>true,'message'=>$message];
    }

    public function updatePartial(int $id, array $changes): array
    {
        if($id<=0){
            $this->appendActionLog('update.validation_fail',['entry'=>$id]);
            return ['success'=>false,'message'=>$this->repoError('invalid_id')];
        }
        if(!$changes){
            $this->appendActionLog('update.no_changes',['entry'=>$id]);
            return ['success'=>true,'message'=>$this->repoMessage('no_changes')];
        }
        $valid=array_flip(self::validColumns());
        $wanted=[]; foreach($changes as $k=>$v){ $lk=strtolower($k); if(isset($valid[$lk]) && $lk!=='entry') $wanted[$lk]=true; }
        if(!$wanted){
            $this->appendActionLog('update.no_valid_fields',['entry'=>$id]);
            return ['success'=>true,'message'=>$this->repoMessage('no_valid_fields')];
        }
        $old=[]; try{ $cols=implode(',',array_map(fn($c)=>"`$c`", array_keys($wanted))); $stOld=$this->world->prepare("SELECT $cols FROM item_template WHERE entry=:id LIMIT 1"); $stOld->execute([':id'=>$id]); $old=$stOld->fetch(PDO::FETCH_ASSOC)?:[]; }catch(\Throwable $e){}
        $sets=[]; $params=[':id'=>$id]; $diff=[];
        foreach($changes as $k=>$v){
            $lk=strtolower($k); if(!isset($valid[$lk])||$lk==='entry') continue; $ph=':c_'.preg_replace('/[^a-z0-9_]/','',$lk);
            $sets[]="`$lk`=$ph"; $params[$ph]=($v===''||$v===null)?null:$v;
            $newVal=$params[$ph]; $oldVal=$old[$lk]??null; if($oldVal!==$newVal){ $diff[$lk]=['old'=>$oldVal,'new'=>$newVal]; }
        }
        if(!$sets){
            $this->appendActionLog('update.no_valid_fields',['entry'=>$id]);
            return ['success'=>true,'message'=>$this->repoMessage('no_valid_fields')];
        }
        if(!$diff){
            $this->appendActionLog('update.no_effect',['entry'=>$id]);
            return ['success'=>true,'message'=>$this->repoMessage('no_values_changed')];
        }
        $sql='UPDATE item_template SET '.implode(',',$sets).' WHERE entry=:id'; $st=$this->world->prepare($sql); $ok=$st->execute($params);
        $trimmed=[]; $count=0; foreach($diff as $col=>$pair){ if($count>=40){ $trimmed['__more__']='truncated'; break; } $trimmed[$col]=['old'=>self::shortVal($pair['old']),'new'=>self::shortVal($pair['new'])]; $count++; }
        $changedCols=array_keys($diff);
        Audit::log('item','update',(string)$id,['changed'=>$trimmed,'success'=>$ok]);
        $this->appendActionLog('update.'.($ok?'success':'error'),['entry'=>$id,'fields'=>$changedCols,'success'=>$ok]);
        return $ok
            ? ['success'=>true,'message'=>$this->repoMessage('update_done'),'changed'=>$changedCols]
            : ['success'=>false,'message'=>$this->repoError('update_failed')];
    }

    public function execLimitedSql(string $sql): array
    {
        $sql = trim($sql);
        if($sql === ''){
            return ['success'=>false,'message'=>$this->repoError('sql_empty')];
        }
        if(preg_match('/;.+/s', $sql)){
            return ['success'=>false,'message'=>$this->repoError('sql_multiple')];
        }

        $validMap = array_fill_keys(self::validColumns(), true);
        $pdo = $this->world;
        $type = '';
        $ok = false;
        $error = '';
        $affected = 0;
        $norm = rtrim($sql, ";\r\n\t ");
        $targetEntry = null;

        try{
            if(preg_match('/^UPDATE\s+`?item_template`?\s+SET\s+(.*?)\s+WHERE\s+/is', $norm, $m)){
                $type = 'UPDATE';
                $assigns = array_map('trim', explode(',', $m[1]));
                foreach($assigns as $as){
                    if(!preg_match('/^`?(\w+)`?\s*=/', $as, $cm)){
                        throw new \RuntimeException($this->repoError('sql_parse_column', ['column'=>$as]));
                    }
                    $col = $cm[1];
                    if(!isset($validMap[$col])){
                        throw new \RuntimeException($this->repoError('sql_invalid_column', ['column'=>$col]));
                    }
                }
                if(!preg_match('/WHERE\s+`?entry`?\s*=\s*(\d+)\s*$/i', $norm, $mm)){
                    throw new \RuntimeException($this->repoError('sql_update_where'));
                }
                $targetEntry = (int)$mm[1];
                $affected = $pdo->exec($norm);
                $ok = true;
            } elseif(preg_match('/^INSERT\s+INTO\s+`?item_template`?\s*\((.*?)\)\s*VALUES\s*\((.*?)\)$/is', $norm, $m)){
                $type = 'INSERT';
                $cols = array_map('trim', explode(',', $m[1]));
                foreach($cols as $c){
                    $c = trim($c, '` ');
                    if(!isset($validMap[$c])){
                        throw new \RuntimeException($this->repoError('sql_invalid_column', ['column'=>$c]));
                    }
                }
                $affected = $pdo->exec($norm);
                $ok = true;
            } else {
                throw new \RuntimeException($this->repoError('sql_only_update_insert'));
            }
        }catch(\Throwable $e){
            $error = $e->getMessage();
        }

        Audit::log('item','exec_sql',$type?:'UNKNOWN',['sql'=>$norm,'success'=>$ok,'affected'=>$affected,'error'=>$error]);
        $this->appendSqlLog($type?:'UNKNOWN',$ok,$affected,$norm,$error);

        $ctx = ['sql'=>$norm,'affected'=>$affected,'type'=>$type?:'UNKNOWN'];
        if($targetEntry !== null){
            $ctx['entry'] = $targetEntry;
        }
        if(!$ok && $error !== ''){
            $ctx['error'] = $error;
        }
        $this->appendActionLog('exec_sql.'.($ok?'success':'error'), $ctx);

        if(!$ok){
            $errorMessage = $error !== '' ? $error : Lang::get('app.common.api.errors.unknown');
            return ['success'=>false,'message'=>$this->repoError('exec_failed', ['error'=>$errorMessage])];
        }

        $after = null;
        if($type === 'UPDATE' && preg_match('/WHERE\s+`?entry`?\s*=\s*(\d+)/i', $norm, $mm)){
            $entry = (int)$mm[1];
            $st = $pdo->prepare('SELECT * FROM item_template WHERE entry=:e');
            if($st->execute([':e'=>$entry])){
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if($r){
                    $after = array_change_key_case($r, CASE_LOWER);
                }
            }
        }

        $messageKey = $type === 'INSERT' ? 'rows_inserted' : 'rows_affected';
        return [
            'success'=>true,
            'message'=>$this->repoMessage($messageKey, ['count'=>(int)$affected]),
            'type'=>$type,
            'affected'=>$affected,
            'after'=>$after,
        ];
    }

    private static function shortVal($v): string
    { if($v===null) return 'NULL'; $s=(string)$v; if(strlen($s)>120) $s=substr($s,0,117).'...'; return $s; }


    private function logsDir(): string
    { return \Acme\Panel\Support\LogPath::logsDir(true, 0777); }

    private function writeLogLine(string $file,string $action,string $stage,array $context): void
    {
        try {
            $payload = $context + [
                'admin' => $this->currentUser(),
                'server' => $this->serverId,
            ];
            if(!array_key_exists('ip',$payload)){
                $payload['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            }
            $payload = array_filter($payload, static fn($v)=>$v!==null);
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $line = sprintf('[%s] %s.%s %s', date('Y-m-d H:i:s'), $action, $stage, $json ?: '{}');
            \Acme\Panel\Support\LogPath::appendTo($this->logsDir().DIRECTORY_SEPARATOR.$file, $line, true, 0777);
        } catch(\Throwable $e){  }
    }

    private function appendActionLog(string $stage,array $context): void
    { $this->writeLogLine('item_actions.log','item',$stage,$context); }

    private function appendDeletedLog(string $action,int $id,string $sql): void
    { $this->writeLogLine('item_deleted.log','item','deleted.'.strtolower($action),['entry'=>$id,'snapshot'=>$sql]); }

    private function appendSqlLog(string $type,bool $ok,int $affected,string $sql,string $error): void
    {
        $normalized = $type?strtolower($type):'unknown';
        $stage = 'sql.'.$normalized.'.'.($ok?'success':'error');
        $context = ['type'=>$type?:'UNKNOWN','affected'=>$affected,'sql'=>$sql];
        if(!$ok && $error!==''){ $context['error']=$error; }
        $this->writeLogLine('item_sql.log','item',$stage,$context);
    }



    private function currentUser(): string
    { return $_SESSION['panel_user'] ?? ($_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown')); }
}

