<?php
/**
 * File: app/Domain/Creature/CreatureRepository.php
 * Purpose: Defines class CreatureRepository for the app/Domain/Creature module.
 * Classes:
 *   - CreatureRepository
 * Functions:
 *   - __construct()
 *   - validColumns()
 *   - search()
 *   - find()
 *   - create()
 *   - delete()
 *   - updatePartial()
 *   - modelTable()
 *   - getModels()
 *   - addModel()
 *   - editModel()
 *   - deleteModel()
 *   - normalizeModelProb()
 *   - fetchRowDiag()
 *   - execLimitedSql()
 *   - shortVal()
 *   - logsDir()
 *   - appendDeletedLog()
 *   - appendSqlLog()
 *   - currentUser()
 */

namespace Acme\Panel\Domain\Creature;

use PDO; use Acme\Panel\Support\Paginator; use Acme\Panel\Support\Audit; use Acme\Panel\Support\Snapshot; use Acme\Panel\Domain\Support\MultiServerRepository; use Acme\Panel\Core\Lang;

class CreatureRepository extends MultiServerRepository
{
    private PDO $world;
    public function __construct(){ parent::__construct(); $this->world = $this->world(); }

    public static function validColumns(): array
    {
        return [
            'entry','difficulty_entry_1','difficulty_entry_2','difficulty_entry_3','killcredit1','killcredit2','modelid1','modelid2','modelid3','modelid4','name','subname','iconname','gossip_menu_id','minlevel','maxlevel','exp','exp_req','faction','npcflag','npcflag2','speed_walk','speed_run','speed_fly','scale','rank','dmgschool','damagemodifier','baseattacktime','rangeattacktime','basevariance','rangevariance','unit_class','unit_flags','unit_flags2','dynamicflags','family','trainer_type','trainer_spell','trainer_class','trainer_race','type','type_flags','type_flags2','lootid','pickpocketloot','skinloot','resistance1','resistance2','resistance3','resistance4','resistance5','resistance6','spell1','spell2','spell3','spell4','spell5','spell6','spell7','spell8','petspelldataid','vehicleid','mingold','maxgold','ainame','movementtype','inhabittype','hoverheight','healthmodifier','manamodifier','armormodifier','experiencemodifier','racialleader','movementid','regenhealth','mechanic_immune_mask','flags_extra','scriptname','verifiedbuild'
        ];
    }

    public function search(array $opts): Paginator
    {
        $searchType = $opts['search_type'] ?? 'name';
        $searchValue = trim($opts['search_value'] ?? '');
        $page = max(1, (int)($opts['page'] ?? 1));
        $limit = max(10, min(200,(int)($opts['limit'] ?? 50)));
        $offset = ($page-1)*$limit;
        $filter_rank = isset($opts['filter_rank']) ? (int)$opts['filter_rank'] : -1;
        $filter_type = isset($opts['filter_type']) ? (int)$opts['filter_type'] : -1;
        $filter_minlevel = ($opts['filter_minlevel']!=='') ? (int)$opts['filter_minlevel'] : null;
        $filter_maxlevel = ($opts['filter_maxlevel']!=='') ? (int)$opts['filter_maxlevel'] : null;
    $sort_by = $opts['sort_by'] ?? 'entry';
    $filter_npcflag_bits = isset($opts['filter_npcflag_bits']) ? trim((string)$opts['filter_npcflag_bits']) : '';
        $sort_dir = strtoupper($opts['sort_dir'] ?? 'ASC');
        $allowedSort = ['entry','name','minlevel','maxlevel','faction','npcflag'];
        if(!in_array($sort_by,$allowedSort,true)) $sort_by='entry';
        if($sort_dir!=='ASC' && $sort_dir!=='DESC') $sort_dir='ASC';

        $where=[]; $params=[];
        if($searchValue!==''){
            if($searchType==='id'){
                $id = filter_var($searchValue,FILTER_VALIDATE_INT);
                if($id){ $where[]='entry = :eid'; $params[':eid']=$id; } else { return new Paginator([],0,$page,$limit); }
            } else { $where[]='name LIKE :name'; $params[':name']='%'.$searchValue.'%'; }
        }
        if($filter_rank!==-1){ $where[]='rank = :rank'; $params[':rank']=$filter_rank; }
        if($filter_type!==-1){ $where[]='type = :type'; $params[':type']=$filter_type; }
        if($filter_minlevel!==null){ $where[]='minlevel >= :minlevel'; $params[':minlevel']=$filter_minlevel; }
        if($filter_maxlevel!==null){ $where[]='maxlevel <= :maxlevel'; $params[':maxlevel']=$filter_maxlevel; }



        if($filter_npcflag_bits !== ''){
            $bitsRaw = array_filter(array_map('trim', explode(',', $filter_npcflag_bits)), 'strlen');
            $bits=[]; foreach($bitsRaw as $b){ if(preg_match('/^\d+$/',$b)){ $iv=(int)$b; if($iv>=0 && $iv<32) $bits[$iv]=true; }}
            if($bits){
                foreach(array_keys($bits) as $i){ $where[] = '(npcflag & '.(1<<$i).') != 0'; }
            }
        }
        $whereSql = $where?(' WHERE '.implode(' AND ',$where)) : '';

        $cntSql="SELECT COUNT(*) FROM creature_template$whereSql"; $cnt=$this->world->prepare($cntSql); foreach($params as $k=>$v){ $cnt->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);} $cnt->execute(); $total=(int)$cnt->fetchColumn();
        $sql="SELECT entry,name,subname,minlevel,maxlevel,faction,npcflag FROM creature_template $whereSql ORDER BY `$sort_by` $sort_dir LIMIT :limit OFFSET :offset";
        $st=$this->world->prepare($sql); foreach($params as $k=>$v){ $st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
        $st->bindValue(':limit',$limit,PDO::PARAM_INT); $st->bindValue(':offset',$offset,PDO::PARAM_INT); $st->execute();
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);
        return new Paginator($rows,$total,$page,$limit);
    }

    public function find(int $id): ?array
    {
        if($id<=0) return null; $st=$this->world->prepare('SELECT * FROM creature_template WHERE entry=:id'); $st->execute([':id'=>$id]); $r=$st->fetch(PDO::FETCH_ASSOC); return $r?array_change_key_case($r,CASE_LOWER):null;
    }

    public function create(int $newId, ?int $copyId=null): array
    {
        if($newId<=0) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.invalid_new_id')];
        $ex=$this->world->prepare('SELECT 1 FROM creature_template WHERE entry=:e'); $ex->execute([':e'=>$newId]); if($ex->fetch()) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.id_exists')];
        if($copyId){
            $src=$this->world->prepare('SELECT * FROM creature_template WHERE entry=:c'); $src->execute([':c'=>$copyId]); $data=$src->fetch(PDO::FETCH_ASSOC); if(!$data) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.copy_source_missing')];
            $data['entry']=$newId; $cols=array_keys($data); $ph=array_map(fn($c)=>':'.$c,$cols);
            $sql='INSERT INTO creature_template(`'.implode('`,`',$cols).'`) VALUES('.implode(',',$ph).')';
            $ins=$this->world->prepare($sql); foreach($data as $k=>$v){ $ins->bindValue(':'.$k,$v===null?null:$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR); }
            $ok=$ins->execute(); if($ok){ Audit::log('creature','create',(string)$newId,['copy'=>$copyId]); $row=$this->find($newId); if($row){ $this->appendDeletedLog('CREATE',$newId,Snapshot::buildInsert('creature_template',$row)); } return ['success'=>true,'message'=>Lang::get('app.creature.repository.success.copied',['source'=>$copyId]),'new_id'=>$newId]; }
            return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.copy_failed')];
        }
        $sql='INSERT INTO creature_template(entry,name,minlevel,maxlevel,faction,npcflag,unit_class,rank,type,scale,healthmodifier,manamodifier,armormodifier,damagemodifier,movementtype,regenhealth,ainame,verifiedbuild) VALUES(:e,:n,1,1,35,0,1,0,7,1,1,1,1,1,0,1,\'SmartAI\',12340)';
        $st=$this->world->prepare($sql); $ok=$st->execute([':e'=>$newId,':n'=>'New Creature '.$newId]);
        if($ok){ Audit::log('creature','create',(string)$newId,['blank'=>true]); $row=$this->find($newId); if($row){ $this->appendDeletedLog('CREATE',$newId,Snapshot::buildInsert('creature_template',$row)); } return ['success'=>true,'message'=>Lang::get('app.creature.repository.success.created'),'new_id'=>$newId]; }
        return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.create_failed')];
    }

    public function delete(int $id): array
    {
        if($id<=0) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.invalid_id')];
        $row=$this->find($id);
        $st=$this->world->prepare('DELETE FROM creature_template WHERE entry=:id'); $st->execute([':id'=>$id]); $cnt=$st->rowCount(); Audit::log('creature','delete',(string)$id,['affected'=>$cnt]);
        if($row && $cnt){ $this->appendDeletedLog('DELETE',$id,Snapshot::buildInsert('creature_template',$row)); }
        return ['success'=>true,'message'=>$cnt?Lang::get('app.creature.repository.success.deleted',['id'=>$id]):Lang::get('app.creature.repository.errors.no_rows_deleted')]; }

    public function updatePartial(int $id, array $changes): array
    {
        if($id<=0) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.invalid_id')]; if(!$changes) return ['success'=>true,'message'=>Lang::get('app.creature.repository.errors.no_changes')];
        $valid=array_flip(self::validColumns());

        $wanted=[]; foreach($changes as $k=>$v){ $lk=strtolower($k); if(isset($valid[$lk]) && $lk!=='entry') $wanted[$lk]=true; }
        if(!$wanted) return ['success'=>true,'message'=>Lang::get('app.creature.repository.errors.no_valid_fields')];
        $old=[]; try{
            $cols=implode(',',array_map(fn($c)=>"`$c`", array_keys($wanted)));
            $stOld=$this->world->prepare("SELECT $cols FROM creature_template WHERE entry=:id LIMIT 1");
            $stOld->execute([':id'=>$id]); $old=$stOld->fetch(PDO::FETCH_ASSOC)?:[];
        }catch(\Throwable $e){  }
        $sets=[]; $params=[':id'=>$id]; $diff=[];
        foreach($changes as $k=>$v){
            $lk=strtolower($k); if(!isset($valid[$lk])||$lk==='entry') continue; $ph=':c_'.preg_replace('/[^a-z0-9_]/','',$lk); $sets[]="`$lk`=$ph"; $params[$ph]=($v===''||$v===null)?null:$v; $newVal=$params[$ph]; $oldVal=$old[$lk]??null; if($oldVal!==$newVal){ $diff[$lk]=['old'=>$oldVal,'new'=>$newVal]; }
        }
        if(!$sets) return ['success'=>true,'message'=>Lang::get('app.creature.repository.errors.no_valid_fields')];
        if(!$diff) return ['success'=>true,'message'=>Lang::get('app.creature.repository.errors.no_value_changes')];
        $sql='UPDATE creature_template SET '.implode(',',$sets).' WHERE entry=:id'; $st=$this->world->prepare($sql); $ok=$st->execute($params);

        $trimmed=[]; $count=0; foreach($diff as $col=>$pair){ if($count>=40) { $trimmed['__more__']='truncated'; break; } $ov=$pair['old']; $nv=$pair['new']; $trimmed[$col]=[
            'old'=> self::shortVal($ov), 'new'=> self::shortVal($nv)
        ]; $count++; }
        Audit::log('creature','update',(string)$id,['changed'=>$trimmed,'success'=>$ok]);
        return $ok?['success'=>true,'message'=>Lang::get('app.creature.repository.success.updated'),'changed'=>array_keys($diff)]:['success'=>false,'message'=>Lang::get('app.creature.repository.errors.update_failed')];
    }


    private function modelTable(): string { return 'creature_template_model'; }
    public function getModels(int $cid): array
    { if($cid<=0) return []; $st=$this->world->prepare('SELECT `Idx`,`CreatureDisplayID`,`DisplayScale`,`Probability`,`VerifiedBuild` FROM `'.$this->modelTable().'` WHERE CreatureID=:c ORDER BY Idx'); $st->execute([':c'=>$cid]); return $st->fetchAll(PDO::FETCH_ASSOC); }

    public function addModel(int $cid,int $displayId,float $scale,float $prob,?int $vb): array
    {
        if($cid<=0||$displayId<=0||$scale<=0||$prob<0||$prob>1) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.model_invalid')];
        $max=$this->world->prepare('SELECT MAX(Idx) FROM `'.$this->modelTable().'` WHERE CreatureID=:c'); $max->execute([':c'=>$cid]); $mx=$max->fetchColumn(); $newIdx=$mx===null?0:((int)$mx+1); if($newIdx>3) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.model_index_limit')];
        $ins=$this->world->prepare('INSERT INTO `'.$this->modelTable().'` (CreatureID,Idx,CreatureDisplayID,DisplayScale,Probability,VerifiedBuild) VALUES(:cid,:i,:d,:s,:p,:v)');
        $ok=$ins->execute([':cid'=>$cid,':i'=>$newIdx,':d'=>$displayId,':s'=>$scale,':p'=>$prob,':v'=>$vb]);
        if($ok){ $this->normalizeModelProb($cid); Audit::log('creature_model','add',(string)$cid,['idx'=>$newIdx]); return ['success'=>true,'message'=>Lang::get('app.creature.repository.success.model_added'),'idx'=>$newIdx]; }
        return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.model_add_failed')];
    }

    public function editModel(int $cid,int $idx,int $displayId,float $scale,float $prob,?int $vb): array
    {
        $st=$this->world->prepare('UPDATE `'.$this->modelTable().'` SET CreatureDisplayID=:d,DisplayScale=:s,Probability=:p,VerifiedBuild=:v WHERE CreatureID=:c AND Idx=:i');
        $ok=$st->execute([':d'=>$displayId,':s'=>$scale,':p'=>$prob,':v'=>$vb,':c'=>$cid,':i'=>$idx]);
    if($ok){ $this->normalizeModelProb($cid); Audit::log('creature_model','edit',(string)$cid,['idx'=>$idx]); return ['success'=>true,'message'=>Lang::get('app.creature.repository.success.model_updated')]; }
    return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.model_update_failed')];
    }

    public function deleteModel(int $cid,int $idx): array
    {
        $st=$this->world->prepare('DELETE FROM `'.$this->modelTable().'` WHERE CreatureID=:c AND Idx=:i'); $ok=$st->execute([':c'=>$cid,':i'=>$idx]);
    if($ok && $st->rowCount()){ $this->normalizeModelProb($cid); Audit::log('creature_model','delete',(string)$cid,['idx'=>$idx]); return ['success'=>true,'message'=>Lang::get('app.creature.repository.success.model_deleted')]; }
    return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.model_delete_failed')];
    }

    private function normalizeModelProb(int $cid): void
    {
        $sumSt=$this->world->prepare('SELECT SUM(Probability) FROM `'.$this->modelTable().'` WHERE CreatureID=:c'); $sumSt->execute([':c'=>$cid]); $sum=(float)$sumSt->fetchColumn(); if($sum<=0) return; if(abs($sum-1.0)<0.001) return;
        $get=$this->world->prepare('SELECT Idx,Probability FROM `'.$this->modelTable().'` WHERE CreatureID=:c'); $get->execute([':c'=>$cid]); $rows=$get->fetchAll(PDO::FETCH_ASSOC); $upd=$this->world->prepare('UPDATE `'.$this->modelTable().'` SET Probability=:p WHERE CreatureID=:c AND Idx=:i');
        foreach($rows as $r){ $upd->execute([':p'=>$r['Probability']/$sum,':c'=>$cid,':i'=>$r['Idx']]); }
    }

    public function fetchRowDiag(int $id): ?array
    {
        $st=$this->world->prepare('SELECT entry,name,subname,minlevel,maxlevel,faction,npcflag FROM creature_template WHERE entry=:e');
        $st->execute([':e'=>$id]); $row=$st->fetch(PDO::FETCH_ASSOC); if(!$row) return null;
        $diag=[]; try{ $diag['database']=$this->world->query('SELECT DATABASE()')->fetchColumn(); }catch(\Throwable $e){}
        try{ $diag['hostname']=$this->world->query("SHOW VARIABLES LIKE 'hostname'")->fetch(PDO::FETCH_NUM)[1]??null; }catch(\Throwable $e){}
        try{ $diag['conn_id']=$this->world->query('SELECT CONNECTION_ID()')->fetchColumn(); }catch(\Throwable $e){}
        return ['row'=>$row,'diag'=>$diag];
    }

    public function execLimitedSql(string $sql): array
    {
        $sql=trim($sql); if($sql==='') return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.sql_empty')];
        if(preg_match('/;.+/s',$sql)) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.sql_multi')];
        $validMap=array_fill_keys(self::validColumns(),true);
        $pdo=$this->world; $type=''; $ok=false; $error=''; $affected=0; $norm=rtrim($sql,";\r\n\t ");
        try{
            if(preg_match('/^UPDATE\s+`?creature_template`?\s+SET\s+(.*?)\s+WHERE\s+/is',$norm,$m)){
                $type='UPDATE'; $assigns=array_map('trim', explode(',', $m[1]));
                foreach($assigns as $as){ if(!preg_match('/^`?(\w+)`?\s*=/', $as,$cm)) throw new \RuntimeException(Lang::get('app.creature.repository.errors.sql_parse_column',['column'=>$as])); $col=$cm[1]; if(!isset($validMap[$col])) throw new \RuntimeException(Lang::get('app.creature.repository.errors.sql_invalid_column',['column'=>$col])); }

                if(!preg_match('/WHERE\s+`?entry`?\s*=\s*(\d+)(?:\s+LIMIT\s+1)?\s*$/i',$norm)){
                    return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.sql_update_where')];
                }
                $affected=$pdo->exec($norm); $ok=true;
            } elseif(preg_match('/^INSERT\s+INTO\s+`?creature_template`?\s*\((.*?)\)\s*VALUES\s*\((.*?)\)$/is',$norm,$m)){
                $type='INSERT'; $cols=array_map('trim', explode(',', $m[1])); foreach($cols as $c){ $c=trim($c,'` '); if(!isset($validMap[$c])) throw new \RuntimeException(Lang::get('app.creature.repository.errors.sql_invalid_column',['column'=>$c])); }
                $affected=$pdo->exec($norm); $ok=true;
            } else { return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.sql_only_update_insert')]; }
        }catch(\Throwable $e){ $error=$e->getMessage(); }
        Audit::log('creature','exec_sql',$type?:'UNKNOWN',['sql'=>$norm,'success'=>$ok,'affected'=>$affected,'error'=>$error]);
        $this->appendSqlLog($type?:'UNKNOWN',$ok,$affected,$norm,$error);
        if(!$ok) return ['success'=>false,'message'=>Lang::get('app.creature.repository.errors.sql_exec_error',['error'=>$error])];
        $after=null; if($type==='UPDATE' && preg_match('/WHERE\s+`?entry`?\s*=\s*(\d+)/i',$norm,$mm)){
            $entry=(int)$mm[1]; $st=$pdo->prepare('SELECT * FROM creature_template WHERE entry=:e'); if($st->execute([':e'=>$entry])){ $r=$st->fetch(PDO::FETCH_ASSOC); if($r) $after=array_change_key_case($r,CASE_LOWER); }
        }
        $actionLabel = $type==='INSERT' ? Lang::get('app.creature.repository.success.sql_action_inserted') : Lang::get('app.creature.repository.success.sql_action_affected');
        return ['success'=>true,'message'=>Lang::get('app.creature.repository.success.sql_rows',['action'=>$actionLabel,'count'=>(int)$affected]),'type'=>$type,'affected'=>$affected,'after'=>$after];
    }

    private static function shortVal($v): string
    { if($v===null) return 'NULL'; $s=(string)$v; if(strlen($s)>120) $s=substr($s,0,117).'...'; return $s; }


    private function logsDir(): string
    { return \Acme\Panel\Support\LogPath::logsDir(true, 0777); }

    private function appendDeletedLog(string $action,int $id,string $sql): void
    { $file=$this->logsDir().DIRECTORY_SEPARATOR.'creature_deleted.log'; $user=$this->currentUser(); $line=sprintf('[%s]|%s|%s|%d|%s|%d',date('Y-m-d H:i:s'),$user,$action,$id,$sql,$this->serverId); \Acme\Panel\Support\LogPath::appendTo($file, $line, true, 0777); }

    private function appendSqlLog(string $type,bool $ok,int $affected,string $sql,string $error): void
    { $file=$this->logsDir().DIRECTORY_SEPARATOR.'creature_sql.log'; $user=$this->currentUser(); $line=sprintf('[%s]|%s|%s|%s|%d|%s|%s|%d',date('Y-m-d H:i:s'),$user,$type,$ok?'OK':'FAIL',$affected,str_replace(["\r","\n"],' ',$sql),$ok?'':$error,$this->serverId); \Acme\Panel\Support\LogPath::appendTo($file, $line, true, 0777); }

    private function currentUser(): string
    { return $_SESSION['admin_user'] ?? ($_SESSION['username'] ?? 'unknown'); }
}

